<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\CreditStoreLedger;
use App\Models\CreditStorePayment;
use App\Models\ChartOfAccount;
use App\Models\BankAccount;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Project;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CreditStoreController extends Controller
{
    public function index(Request $request)
    {
        $query = CreditStoreLedger::with(['purchaseRequest', 'project', 'coaAccount', 'authorizedByUser', 'payments'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function($q) use ($s) {
                $q->where('pr_no', 'like', "%{$s}%")
                  ->orWhere('supplier_name', 'like', "%{$s}%")
                  ->orWhereHas('project', fn($pq) => $pq->where('name', 'like', "%{$s}%"));
            });
        }

        $ledgers = $query->paginate(20)->withQueryString();

        // Metrics
        $totalCredit      = CreditStoreLedger::sum('credit_amount');
        $totalPaid        = CreditStoreLedger::sum('paid_amount');
        $totalOutstanding = max(0, $totalCredit - $totalPaid);
        $countOutstanding = CreditStoreLedger::whereIn('status', ['outstanding', 'partially_paid'])->count();
        $countFullyPaid   = CreditStoreLedger::where('status', 'fully_paid')->count();

        $projects = Project::orderBy('name')->get();

        return view('finance.credit-store.index', compact(
            'ledgers',
            'totalCredit',
            'totalPaid',
            'totalOutstanding',
            'countOutstanding',
            'countFullyPaid',
            'projects'
        ));
    }

    public function show(CreditStoreLedger $creditStore)
    {
        $ledger = $creditStore->load([
            'purchaseRequest.items.product',
            'purchaseRequest.proformaInvoices.supplier',
            'project',
            'coaAccount',
            'authorizedByUser',
            'payments.coaAccount',
            'payments.bankAccount',
            'payments.recordedByUser',
            'payments.journalEntry',
        ]);

        $coaAccounts = ChartOfAccount::where('is_active', true)
            ->where('code', '!=', '5110')
            ->orderBy('code')
            ->get();

        $bankAccounts = BankAccount::orderBy('bank_name')->get();

        return view('finance.credit-store.show', compact('ledger', 'coaAccounts', 'bankAccounts'));
    }

    public function recordPayment(Request $request, CreditStoreLedger $creditStore)
    {
        $ledger = $creditStore;
        $remaining = $ledger->remaining_amount;

        $request->validate([
            'amount'         => 'required|numeric|min:0.01|max:' . ($remaining > 0 ? $remaining : 999999999),
            'payment_date'   => 'required|date',
            'payment_method' => 'required|string|in:cash,bank_transfer,cheque,other',
            'coa_account_id' => 'nullable|exists:chart_of_accounts,id',
            'bank_account_id'=> 'nullable|exists:bank_accounts,id',
            'reference_no'   => 'nullable|string|max:150',
            'receipt_file'   => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
            'notes'          => 'nullable|string',
        ]);

        $amount = (float)$request->amount;
        $filePath = null;
        $originalFilename = null;

        if ($request->hasFile('receipt_file')) {
            $file = $request->file('receipt_file');
            $filePath = FileUploadService::upload($file, 'credit_receipts');
            $originalFilename = $file->getClientOriginalName();
        }

        DB::transaction(function () use ($ledger, $request, $amount, $filePath, $originalFilename) {
            // 1. Create Payment Record
            $payment = CreditStorePayment::create([
                'credit_store_ledger_id' => $ledger->id,
                'payment_date'           => $request->payment_date,
                'amount'                 => $amount,
                'payment_method'         => $request->payment_method,
                'bank_account_id'        => $request->bank_account_id,
                'coa_account_id'         => $request->coa_account_id,
                'reference_no'           => $request->reference_no,
                'receipt_path'           => $filePath,
                'original_filename'      => $originalFilename,
                'notes'                  => $request->notes,
                'recorded_by'            => Auth::id(),
            ]);

            // 2. Create Journal Entry
            $fundingCoaId = $request->coa_account_id;
            if (!$fundingCoaId && $request->bank_account_id) {
                $bank = BankAccount::find($request->bank_account_id);
                $fundingCoaId = $bank?->coa_id;
            }

            $creditCoaId = $ledger->coa_account_id;
            if (!$creditCoaId) {
                $c = ChartOfAccount::where('code', '5110')->first();
                $creditCoaId = $c?->id;
            }

            if ($creditCoaId && $fundingCoaId) {
                try {
                    $entryNo = 'CR-PAY-' . date('Ymd') . '-' . str_pad(JournalEntry::count() + 1, 5, '0', STR_PAD_LEFT);
                    $journal = JournalEntry::create([
                        'entry_no'       => $entryNo,
                        'entry_date'     => $request->payment_date,
                        'reference_type' => 'credit_store_payment',
                        'reference_id'   => $payment->id,
                        'description'    => "Credit payment for PR #{$ledger->pr_no} (" . ($ledger->supplier_name ?: 'Supplier') . ")",
                        'status'         => 'posted',
                        'created_by'     => Auth::id(),
                        'posted_at'      => now(),
                    ]);

                    // Debit: Cost of Material By Credit 5110
                    JournalEntryLine::create([
                        'journal_entry_id' => $journal->id,
                        'account_id'       => $creditCoaId,
                        'side'             => 'debit',
                        'amount'           => $amount,
                        'description'      => "Credit liquidation — PR #{$ledger->pr_no}",
                    ]);

                    // Credit: Funding Source (Bank / Cash account)
                    JournalEntryLine::create([
                        'journal_entry_id' => $journal->id,
                        'account_id'       => $fundingCoaId,
                        'side'             => 'credit',
                        'amount'           => $amount,
                        'description'      => "Disbursement for credit purchase PR #{$ledger->pr_no} (" . ucfirst(str_replace('_', ' ', $request->payment_method)) . ")",
                    ]);

                    // Decrement funding source balance
                    ChartOfAccount::where('id', $fundingCoaId)->decrement('current_balance', $amount);

                    $payment->update(['journal_entry_id' => $journal->id]);
                } catch (\Throwable $je) {
                    \Illuminate\Support\Facades\Log::error("CreditPaymentJournalEntry error: " . $je->getMessage());
                }
            }

            // 3. Record in Expense ledger so it counts in company expenses
            try {
                Expense::create([
                    'project_id'   => $ledger->project_id,
                    'category'     => 'material',
                    'description'  => "Credit Settlement: PR #{$ledger->pr_no} (" . ($ledger->supplier_name ?: 'Material Purchase') . ")" . ($request->reference_no ? " [Ref: {$request->reference_no}]" : ""),
                    'amount'       => $amount,
                    'expense_date' => $request->payment_date,
                    'status'       => 'approved',
                    'created_by'   => Auth::id(),
                    'approved_by'  => Auth::id(),
                    'approved_at'  => now(),
                    'notes'        => $request->notes,
                ]);
            } catch (\Throwable $ex) {
                \Illuminate\Support\Facades\Log::error("CreditExpenseCreate error: " . $ex->getMessage());
            }

            // 4. Update Ledger balances and status
            $newPaid = (float)$ledger->paid_amount + $amount;
            $newStatus = ($newPaid >= (float)$ledger->credit_amount) ? 'fully_paid' : 'partially_paid';

            $ledger->update([
                'paid_amount' => $newPaid,
                'status'      => $newStatus,
            ]);
        });

        return redirect()->route('finance.credit-store.show', $ledger)
            ->with('success', "Payment of " . number_format($amount, 2) . " ETB recorded successfully. Deducted from Credit Ledger and logged into Expenses.");
    }
}
