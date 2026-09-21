<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\CreditStoreLedger;
use App\Models\CreditStorePayment;
use App\Models\ChartOfAccount;
use App\Models\BankAccount;
use App\Models\Expense;
use App\Models\ExpenseRequest;
use App\Models\User;
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

        $coaAccounts = ChartOfAccount::where('is_active', true)
            ->where('code', '!=', '5110')
            ->orderBy('code')
            ->get();

        $cashAccounts = ChartOfAccount::where('is_active', true)
            ->where('code', '!=', '5110')
            ->where(function ($q) {
                $q->where('type', 'asset')
                  ->where(function ($sub) {
                      $sub->whereIn('subtype', ['cash_and_bank', 'cash', 'current_asset'])
                          ->orWhere('code', 'like', '1000%')
                          ->orWhere('code', 'like', '1010%')
                          ->orWhere('name', 'like', '%cash%')
                          ->orWhere('name', 'like', '%petty%');
                  });
            })
            ->orderBy('code')
            ->get();

        $bankAccounts = BankAccount::orderBy('bank_name')->get();

        return view('finance.credit-store.index', compact(
            'ledgers',
            'totalCredit',
            'totalPaid',
            'totalOutstanding',
            'countOutstanding',
            'countFullyPaid',
            'projects',
            'coaAccounts',
            'cashAccounts',
            'bankAccounts'
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

        $cashAccounts = ChartOfAccount::where('is_active', true)
            ->where('code', '!=', '5110')
            ->where(function ($q) {
                $q->where('type', 'asset')
                  ->where(function ($sub) {
                      $sub->whereIn('subtype', ['cash_and_bank', 'cash', 'current_asset'])
                          ->orWhere('code', 'like', '1000%')
                          ->orWhere('code', 'like', '1010%')
                          ->orWhere('name', 'like', '%cash%')
                          ->orWhere('name', 'like', '%petty%');
                  });
            })
            ->orderBy('code')
            ->get();

        $bankAccounts = BankAccount::orderBy('bank_name')->get();

        // Finance Staff users available for payment assignment
        $financeStaff = User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['Finance staff', 'finance_staff', 'Finance head', 'finance_head', 'cashier', 'accountant', 'admin', 'global_admin']);
        })->orWhereHas('employee', function($q) {
            $q->where('department', 'like', '%Finance%');
        })->orderBy('name')->get();

        if ($financeStaff->isEmpty()) {
            $financeStaff = User::where('is_active', true)->orderBy('name')->get();
        }

        // Fetch linked expense requests for this credit store
        $linkedExpenseRequests = ExpenseRequest::with(['assignedFinanceStaff', 'paidBy', 'chartOfAccount', 'bankAccount'])
            ->where(function($q) use ($creditStore) {
                if (\Illuminate\Support\Facades\Schema::hasColumn('expense_requests', 'credit_store_ledger_id')) {
                    $q->where('credit_store_ledger_id', $creditStore->id);
                }
                if ($creditStore->purchase_request_id) {
                    $q->orWhere('purchase_request_id', $creditStore->purchase_request_id);
                }
                $q->orWhere('description', 'like', "%PR #{$creditStore->pr_no}%");
            })
            ->latest()
            ->get();

        return view('finance.credit-store.show', compact('ledger', 'coaAccounts', 'cashAccounts', 'bankAccounts', 'financeStaff', 'linkedExpenseRequests'));
    }

    /**
     * Finance Head assigns a payment amount from COA/Bank to a Finance Staff member.
     * This creates an ExpenseRequest so the assigned person can process it in the Expenses section with VAT & Withholding Tax.
     */
    public function assignExpense(Request $request, CreditStoreLedger $creditStore)
    {
        $ledger = $creditStore;
        $remaining = $ledger->remaining_amount;

        $request->validate([
            'amount'                    => 'required|numeric|min:0.01|max:' . ($remaining > 0 ? $remaining : 999999999),
            'account_source'            => 'required|string',
            'assigned_finance_staff_id' => 'required|exists:users,id',
            'category'                  => 'nullable|string',
            'notes'                     => 'nullable|string|max:1000',
        ]);

        $amount = (float)$request->amount;

        // Resolve funding accounts
        $bankAccountId = null;
        $fundingCoaId = null;

        if ($request->filled('account_source')) {
            $parts = explode(':', $request->account_source);
            if (count($parts) === 2) {
                if ($parts[0] === 'bank') {
                    $bankAccountId = (int)$parts[1];
                    $bank = BankAccount::find($bankAccountId);
                    $fundingCoaId = $bank?->coa_id;
                } elseif ($parts[0] === 'coa') {
                    $fundingCoaId = (int)$parts[1];
                }
            }
        }

        // Generate Expense Request Number
        $prClean = $ledger->pr_no ? preg_replace('/[^0-9]/', '', $ledger->pr_no) : $ledger->id;
        $seq = ExpenseRequest::where('category', 'like', '%Credit%')->count() + 1;
        $reqNo = 'EXP-CR-' . ($prClean ?: $ledger->id) . '-' . str_pad($seq, 2, '0', STR_PAD_LEFT);

        while (ExpenseRequest::where('request_number', $reqNo)->exists()) {
            $seq++;
            $reqNo = 'EXP-CR-' . ($prClean ?: $ledger->id) . '-' . str_pad($seq, 2, '0', STR_PAD_LEFT);
        }

        $category = $request->input('category', 'Material (Credit Settlement)');
        $assignedStaff = User::find($request->assigned_finance_staff_id);

        $createData = [
            'request_number'            => $reqNo,
            'user_id'                   => Auth::id(),
            'purchase_request_id'       => $ledger->purchase_request_id,
            'project_id'                => $ledger->project_id,
            'category'                  => $category,
            'other_reason'              => 'Credit Store Purchase Settlement',
            'description'               => "Credit Purchase Settlement: PR #{$ledger->pr_no}"
                                          . ($ledger->supplier_name ? " — Supplier: {$ledger->supplier_name}" : '')
                                          . ($ledger->project ? " — Project: {$ledger->project->name}" : ''),
            'amount'                    => $amount,
            'gross_amount'              => $amount,
            'vat_type'                  => 'none',
            'has_withholding'           => false,
            'net_amount'                => $amount,
            'status'                    => ExpenseRequest::STATUS_ASSIGNED, // 'Assigned to Finance'
            'finance_head_id'           => Auth::id(),
            'bank_account_id'           => $bankAccountId,
            'coa_id'                    => $fundingCoaId,
            'chart_of_account_id'       => $fundingCoaId,
            'assigned_finance_staff_id' => $assignedStaff->id,
            'finance_staff_id'          => $assignedStaff->id,
            'finance_assigned_at'       => now(),
            'notes'                     => $request->notes ?? "Assigned from Credit Store Ledger for PR #{$ledger->pr_no}",
        ];

        if (\Illuminate\Support\Facades\Schema::hasColumn('expense_requests', 'credit_store_ledger_id')) {
            $createData['credit_store_ledger_id'] = $ledger->id;
        }

        $expenseReq = ExpenseRequest::create($createData);

        return back()->with('success', "✅ Payment of ETB " . number_format($amount, 2) . " assigned to {$assignedStaff->name} (Request #{$reqNo}). It is now available in the Expenses section to process with VAT and 3% Withholding Tax.");
    }

    public function recordPayment(Request $request, CreditStoreLedger $creditStore)
    {
        $ledger = $creditStore;
        $remaining = $ledger->remaining_amount;

        $request->validate([
            'amount'            => 'required|numeric|min:0.01|max:' . ($remaining > 0 ? $remaining : 999999999),
            'payment_date'      => 'required|date',
            'payment_method'    => 'required|string|in:cash,bank_transfer,cheque,other',
            'account_source'    => 'nullable|string',
            'coa_account_id'    => 'nullable|exists:chart_of_accounts,id',
            'bank_account_id'   => 'nullable|exists:bank_accounts,id',
            'no_receipt'        => 'nullable|boolean',
            'no_receipt_reason' => 'nullable|string|max:255',
            'reference_no'      => 'nullable|string|max:150',
            'receipt_file'      => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
            'notes'             => 'nullable|string',
        ]);

        $amount = (float)$request->amount;
        $filePath = null;
        $originalFilename = null;

        $isNoReceipt = $request->boolean('no_receipt');

        if (!$isNoReceipt && $request->hasFile('receipt_file')) {
            $file = $request->file('receipt_file');
            $filePath = FileUploadService::upload($file, 'credit_receipts');
            $originalFilename = $file->getClientOriginalName();
        }

        // Resolve funding accounts
        $bankAccountId = $request->bank_account_id;
        $fundingCoaId = $request->coa_account_id;

        if ($request->filled('account_source')) {
            $parts = explode(':', $request->account_source);
            if (count($parts) === 2) {
                if ($parts[0] === 'bank') {
                    $bankAccountId = (int)$parts[1];
                    $bank = BankAccount::find($bankAccountId);
                    $fundingCoaId = $bank?->coa_id;
                } elseif ($parts[0] === 'coa') {
                    $fundingCoaId = (int)$parts[1];
                }
            }
        } elseif (!$fundingCoaId && $bankAccountId) {
            $bank = BankAccount::find($bankAccountId);
            $fundingCoaId = $bank?->coa_id;
        }

        $paymentNotes = $request->notes ?? '';
        if ($isNoReceipt) {
            $reason = $request->filled('no_receipt_reason') ? " ({$request->no_receipt_reason})" : "";
            $paymentNotes = trim($paymentNotes . " [Paid without receipt{$reason}]");
        }

        DB::transaction(function () use ($ledger, $request, $amount, $filePath, $originalFilename, $bankAccountId, $fundingCoaId, $paymentNotes) {
            // 1. Create Payment Record
            $payment = CreditStorePayment::create([
                'credit_store_ledger_id' => $ledger->id,
                'payment_date'           => $request->payment_date,
                'amount'                 => $amount,
                'payment_method'         => $request->payment_method,
                'bank_account_id'        => $bankAccountId,
                'coa_account_id'         => $fundingCoaId,
                'reference_no'           => $request->reference_no,
                'receipt_path'           => $filePath,
                'original_filename'      => $originalFilename,
                'notes'                  => $paymentNotes,
                'recorded_by'            => Auth::id(),
            ]);

            // 2. Create Journal Entry
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
                    if ($bankAccountId) {
                        BankAccount::where('id', $bankAccountId)->decrement('current_balance', $amount);
                    }

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
                    'notes'        => $paymentNotes,
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

        $receiptMsg = $isNoReceipt ? " (without receipt)" : "";
        return redirect()->route('finance.credit-store.show', $ledger)
            ->with('success', "Payment of " . number_format($amount, 2) . " ETB recorded successfully{$receiptMsg}. Deducted from Credit Ledger and logged into Expenses.");
    }

    public function batchPayment(Request $request)
    {
        $request->validate([
            'selected_ids'        => 'required|array|min:1',
            'selected_ids.*'      => 'exists:credit_store_ledgers,id',
            'amounts'             => 'required|array',
            'payment_date'        => 'required|date',
            'payment_method'      => 'required|string|in:cash,bank_transfer,cheque,other',
            'account_source'      => 'nullable|string',
            'coa_account_id'      => 'nullable|exists:chart_of_accounts,id',
            'bank_account_id'     => 'nullable|exists:bank_accounts,id',
            'no_receipt'          => 'nullable|boolean',
            'no_receipt_reason'   => 'nullable|string|max:255',
            'reference_no'        => 'nullable|string|max:150',
            'receipt_file'        => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
            'notes'               => 'nullable|string',
        ]);

        $isNoReceipt = $request->boolean('no_receipt');
        $filePath = null;
        $originalFilename = null;

        if (!$isNoReceipt && $request->hasFile('receipt_file')) {
            $file = $request->file('receipt_file');
            $filePath = FileUploadService::upload($file, 'credit_receipts');
            $originalFilename = $file->getClientOriginalName();
        }

        // Resolve funding accounts
        $bankAccountId = $request->bank_account_id;
        $fundingCoaId = $request->coa_account_id;

        if ($request->filled('account_source')) {
            $parts = explode(':', $request->account_source);
            if (count($parts) === 2) {
                if ($parts[0] === 'bank') {
                    $bankAccountId = (int)$parts[1];
                    $bank = BankAccount::find($bankAccountId);
                    $fundingCoaId = $bank?->coa_id;
                } elseif ($parts[0] === 'coa') {
                    $fundingCoaId = (int)$parts[1];
                }
            }
        } elseif (!$fundingCoaId && $bankAccountId) {
            $bank = BankAccount::find($bankAccountId);
            $fundingCoaId = $bank?->coa_id;
        }

        $baseNotes = $request->notes ?? '';
        if ($isNoReceipt) {
            $reason = $request->filled('no_receipt_reason') ? " ({$request->no_receipt_reason})" : "";
            $baseNotes = trim($baseNotes . " [Batch settlement paid without receipt{$reason}]");
        } else {
            $baseNotes = trim($baseNotes . " (Batch settlement with shared receipt)");
        }

        $totalPaidSum = 0;
        $processedCount = 0;

        DB::transaction(function () use ($request, $filePath, $originalFilename, $bankAccountId, $fundingCoaId, $baseNotes, &$totalPaidSum, &$processedCount) {
            foreach ($request->selected_ids as $ledgerId) {
                $ledger = CreditStoreLedger::lockForUpdate()->find($ledgerId);
                if (!$ledger) {
                    continue;
                }

                $amount = isset($request->amounts[$ledgerId]) ? (float)$request->amounts[$ledgerId] : (float)$ledger->remaining_amount;

                if ($amount <= 0) {
                    continue;
                }

                // Cap amount at remaining balance
                $amount = min($amount, (float)$ledger->remaining_amount);
                if ($amount <= 0) {
                    continue;
                }

                // 1. Create Payment Record
                $payment = CreditStorePayment::create([
                    'credit_store_ledger_id' => $ledger->id,
                    'payment_date'           => $request->payment_date,
                    'amount'                 => $amount,
                    'payment_method'         => $request->payment_method,
                    'bank_account_id'        => $bankAccountId,
                    'coa_account_id'         => $fundingCoaId,
                    'reference_no'           => $request->reference_no,
                    'receipt_path'           => $filePath,
                    'original_filename'      => $originalFilename,
                    'notes'                  => $baseNotes,
                    'recorded_by'            => Auth::id(),
                ]);

                // 2. Double-entry Journal Entry
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
                            'description'    => "Batch Credit settlement for PR #{$ledger->pr_no} (" . ($ledger->supplier_name ?: 'Supplier') . ")",
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

                        // Credit: Funding Source
                        JournalEntryLine::create([
                            'journal_entry_id' => $journal->id,
                            'account_id'       => $fundingCoaId,
                            'side'             => 'credit',
                            'amount'           => $amount,
                            'description'      => "Disbursement for credit purchase PR #{$ledger->pr_no} (" . ucfirst(str_replace('_', ' ', $request->payment_method)) . ")",
                        ]);

                        ChartOfAccount::where('id', $fundingCoaId)->decrement('current_balance', $amount);
                        if ($bankAccountId) {
                            BankAccount::where('id', $bankAccountId)->decrement('current_balance', $amount);
                        }

                        $payment->update(['journal_entry_id' => $journal->id]);
                    } catch (\Throwable $je) {
                        \Illuminate\Support\Facades\Log::error("BatchCreditPaymentJournalEntry error: " . $je->getMessage());
                    }
                }

                // 3. Log to Company Expenses
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
                        'notes'        => $baseNotes,
                    ]);
                } catch (\Throwable $ex) {
                    \Illuminate\Support\Facades\Log::error("BatchCreditExpenseCreate error: " . $ex->getMessage());
                }

                // 4. Update Ledger balances and status
                $newPaid = (float)$ledger->paid_amount + $amount;
                $newStatus = ($newPaid >= (float)$ledger->credit_amount) ? 'fully_paid' : 'partially_paid';

                $ledger->update([
                    'paid_amount' => $newPaid,
                    'status'      => $newStatus,
                ]);

                $totalPaidSum += $amount;
                $processedCount++;
            }
        });

        $receiptText = $isNoReceipt ? "without receipt" : "with shared receipt attached";
        return redirect()->route('finance.credit-store.index')
            ->with('success', "Batch credit settlement complete! Recorded payment for {$processedCount} credit purchase(s) totaling " . number_format($totalPaidSum, 2) . " ETB {$receiptText}.");
    }
}