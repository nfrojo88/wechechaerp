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

        $coaAccounts = ChartOfAccount::with('manager')->where('is_active', true)
            ->where('code', '!=', '5110')
            ->orderBy('code')
            ->get();

        $cashAccounts = ChartOfAccount::with('manager')->where('is_active', true)
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

        $bankAccounts = BankAccount::with(['assignedStaff', 'coa.manager'])->orderBy('bank_name')->get();

        // Finance Staff users available for payment assignment
        $financeStaff = User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['Finance staff', 'finance_staff', 'Finance head', 'finance_head', 'cashier', 'accountant', 'admin', 'global_admin']);
        })->orWhereHas('employee', function($q) {
            $q->where('department', 'like', '%Finance%');
        })->orderBy('name')->get();

        // Ensure any user assigned as custodian to these accounts is included in the staff list
        $custodianIds = $coaAccounts->pluck('assigned_to')
            ->concat($cashAccounts->pluck('assigned_to'))
            ->concat($bankAccounts->pluck('assigned_to'))
            ->concat($bankAccounts->pluck('coa.assigned_to'))
            ->filter()
            ->unique();

        if ($custodianIds->isNotEmpty()) {
            $custodians = User::whereIn('id', $custodianIds)->get();
            $financeStaff = $financeStaff->concat($custodians)->unique('id')->sortBy('name')->values();
        }

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
            'assigned_finance_staff_id' => 'nullable|exists:users,id',
            'category'                  => 'nullable|string',
            'notes'                     => 'nullable|string|max:1000',
        ]);

        $amount = (float)$request->amount;

        // Resolve funding accounts
        $bankAccountId = null;
        $fundingCoaId = null;
        $bank = null;
        $coa = null;

        if ($request->filled('account_source')) {
            $parts = explode(':', $request->account_source);
            if (count($parts) === 2) {
                if ($parts[0] === 'bank') {
                    $bankAccountId = (int)$parts[1];
                    $bank = BankAccount::with('coa')->find($bankAccountId);
                    $fundingCoaId = $bank?->coa_id;
                } elseif ($parts[0] === 'coa') {
                    $fundingCoaId = (int)$parts[1];
                    $coa = ChartOfAccount::find($fundingCoaId);
                }
            }
        }

        // Auto-resolve assigned staff from COA / Bank if not explicitly provided
        $assignedStaffId = $request->assigned_finance_staff_id;
        if (!$assignedStaffId) {
            if ($bank) {
                $assignedStaffId = $bank->assigned_to ?? $bank->coa?->assigned_to;
            }
            if (!$assignedStaffId && $coa) {
                $assignedStaffId = $coa->assigned_to;
            }
            if (!$assignedStaffId && $fundingCoaId && !$coa) {
                $coa = ChartOfAccount::find($fundingCoaId);
                $assignedStaffId = $coa?->assigned_to;
            }
            if (!$assignedStaffId) {
                $assignedStaffId = Auth::id() ?: (User::where('is_active', true)->value('id') ?: 1);
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
        $assignedStaff = User::findOrFail($assignedStaffId);

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
            'amount'                     => 'required|numeric|min:0.01|max:' . ($remaining > 0 ? $remaining : 999999999),
            'payment_date'               => 'required|date',
            'payment_method'             => 'required|string|in:cash,bank_transfer,cheque,other',
            'account_source'             => 'nullable|string',
            'coa_account_id'             => 'nullable|exists:chart_of_accounts,id',
            'bank_account_id'            => 'nullable|exists:bank_accounts,id',
            'vat_type'                   => 'nullable|string|in:none,exclusive,vat_b,inclusive',
            'vat_rate'                   => 'nullable|numeric',
            'has_withholding'            => 'nullable|boolean',
            'withholding_rate'           => 'nullable|numeric',
            'withholding_receipt'        => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
            'withholding_receipt_number' => 'nullable|string|max:100',
            'no_receipt'                 => 'nullable|boolean',
            'no_receipt_reason'          => 'nullable|string|max:255',
            'reference_no'               => 'nullable|string|max:150',
            'receipt_file'               => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
            'notes'                      => 'nullable|string',
        ]);

        $gross = (float)$request->amount;
        $vatType = $request->input('vat_type', 'none');
        $vatRate = (float)$request->input('vat_rate', 15.00);
        $hasWithholding = $request->boolean('has_withholding');
        $withholdingRate = (float)$request->input('withholding_rate', 3.00);

        $vatAmount = 0.0;
        $baseAmount = $gross;
        $withholdingAmount = 0.0;
        $netAmount = $gross;

        if ($vatType === 'exclusive') {
            $vatAmount = round($gross * ($vatRate / 100), 2);
            $baseAmount = $gross;
            $totalGrossWithVat = $gross + $vatAmount;
            if ($hasWithholding) {
                $withholdingAmount = round($baseAmount * ($withholdingRate / 100), 2);
            }
            $netAmount = round($totalGrossWithVat - $withholdingAmount, 2);
        } elseif ($vatType === 'inclusive' || $vatType === 'vat_b') {
            $baseAmount = round($gross / (1 + ($vatRate / 100)), 2);
            $vatAmount = round($gross - $baseAmount, 2);
            if ($hasWithholding) {
                $withholdingAmount = round($baseAmount * ($withholdingRate / 100), 2);
            }
            $netAmount = round($gross - $withholdingAmount, 2);
        } else {
            $baseAmount = $gross;
            $vatAmount = 0.0;
            if ($hasWithholding) {
                $withholdingAmount = round($baseAmount * ($withholdingRate / 100), 2);
            }
            $netAmount = round($gross - $withholdingAmount, 2);
        }

        $disbursedAmount = $netAmount > 0 ? $netAmount : $gross;
        $settledCreditAmount = $gross;

        $filePath = null;
        $originalFilename = null;
        $isNoReceipt = $request->boolean('no_receipt');

        if (!$isNoReceipt && $request->hasFile('receipt_file')) {
            $file = $request->file('receipt_file');
            $filePath = FileUploadService::upload($file, 'credit_receipts');
            $originalFilename = $file->getClientOriginalName();
        }

        $withholdingReceiptPath = null;
        if ($request->hasFile('withholding_receipt')) {
            $wFile = $request->file('withholding_receipt');
            $withholdingReceiptPath = FileUploadService::upload($wFile, 'withholding_receipts');
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

        $taxSummary = [];
        if ($vatType !== 'none' && $vatAmount > 0) {
            $taxSummary[] = "VAT ({$vatType}): +ETB " . number_format($vatAmount, 2);
        }
        if ($hasWithholding && $withholdingAmount > 0) {
            $taxSummary[] = "WHT (3%): -ETB " . number_format($withholdingAmount, 2);
        }
        if (!empty($taxSummary)) {
            $paymentNotes = trim($paymentNotes . " [" . implode(', ', $taxSummary) . " | Disbursed: ETB " . number_format($disbursedAmount, 2) . "]");
        }

        DB::transaction(function () use (
            $ledger, $request, $settledCreditAmount, $gross, $vatType, $vatRate, $vatAmount,
            $hasWithholding, $withholdingRate, $withholdingAmount, $disbursedAmount,
            $filePath, $originalFilename, $withholdingReceiptPath, $bankAccountId, $fundingCoaId, $paymentNotes
        ) {
            // 1. Create Payment Record
            $paymentData = [
                'credit_store_ledger_id' => $ledger->id,
                'payment_date'           => $request->payment_date,
                'amount'                 => $settledCreditAmount,
                'payment_method'         => $request->payment_method,
                'bank_account_id'        => $bankAccountId,
                'coa_account_id'         => $fundingCoaId,
                'reference_no'           => $request->reference_no,
                'receipt_path'           => $filePath,
                'original_filename'      => $originalFilename,
                'notes'                  => $paymentNotes,
                'recorded_by'            => Auth::id(),
            ];

            if (\Illuminate\Support\Facades\Schema::hasColumn('credit_store_payments', 'gross_amount')) {
                $paymentData['gross_amount'] = $gross;
                $paymentData['vat_type'] = $vatType;
                $paymentData['vat_rate'] = $vatRate;
                $paymentData['vat_amount'] = $vatAmount;
                $paymentData['has_withholding'] = $hasWithholding;
                $paymentData['withholding_rate'] = $withholdingRate;
                $paymentData['withholding_amount'] = $withholdingAmount;
                $paymentData['withholding_receipt'] = $withholdingReceiptPath;
                $paymentData['withholding_receipt_number'] = $request->input('withholding_receipt_number');
                $paymentData['net_amount'] = $disbursedAmount;
            }

            $payment = CreditStorePayment::create($paymentData);

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

                    // Debit: Cost of Material By Credit 5110 (Full settlement value)
                    JournalEntryLine::create([
                        'journal_entry_id' => $journal->id,
                        'account_id'       => $creditCoaId,
                        'side'             => 'debit',
                        'amount'           => $settledCreditAmount,
                        'description'      => "Credit liquidation — PR #{$ledger->pr_no}",
                    ]);

                    // Credit: Funding Source (Actual cash disbursed)
                    JournalEntryLine::create([
                        'journal_entry_id' => $journal->id,
                        'account_id'       => $fundingCoaId,
                        'side'             => 'credit',
                        'amount'           => $disbursedAmount,
                        'description'      => "Disbursement for credit purchase PR #{$ledger->pr_no} (" . ucfirst(str_replace('_', ' ', $request->payment_method)) . ")",
                    ]);

                    // If withholding tax deducted: Credit Withholding Tax account
                    if ($withholdingAmount > 0) {
                        $whtAccount = ChartOfAccount::where('name', 'like', '%Withholding%')
                            ->orWhere('code', '1300')
                            ->orWhere('code', 'like', '2%')
                            ->first();
                        $whtAccountId = $whtAccount?->id ?: $creditCoaId;

                        JournalEntryLine::create([
                            'journal_entry_id' => $journal->id,
                            'account_id'       => $whtAccountId,
                            'side'             => 'credit',
                            'amount'           => $withholdingAmount,
                            'description'      => "Withholding tax deducted (3%) — PR #{$ledger->pr_no}",
                        ]);
                    }

                    // Decrement funding source balance by actual disbursed cash
                    ChartOfAccount::where('id', $fundingCoaId)->decrement('current_balance', $disbursedAmount);
                    if ($bankAccountId) {
                        BankAccount::where('id', $bankAccountId)->decrement('current_balance', $disbursedAmount);
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
                    'amount'       => $settledCreditAmount,
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

            // 4. Auto-log Paid ExpenseRequest to sync with Tax Reports & Expenses Analytics
            try {
                $prClean = $ledger->pr_no ? preg_replace('/[^0-9]/', '', $ledger->pr_no) : $ledger->id;
                $expReqNo = 'EXP-QP-' . ($prClean ?: $ledger->id) . '-' . str_pad(ExpenseRequest::count() + 1, 2, '0', STR_PAD_LEFT);
                while (ExpenseRequest::where('request_number', $expReqNo)->exists()) {
                    $expReqNo = 'EXP-QP-' . ($prClean ?: $ledger->id) . '-' . rand(10, 99);
                }

                $expReqData = [
                    'request_number'             => $expReqNo,
                    'user_id'                    => Auth::id(),
                    'purchase_request_id'        => $ledger->purchase_request_id,
                    'project_id'                 => $ledger->project_id,
                    'category'                   => 'Material (Credit Settlement)',
                    'other_reason'               => 'Quick Pay Credit Purchase Settlement',
                    'description'                => "Credit Purchase Settlement (Quick Pay): PR #{$ledger->pr_no}" . ($ledger->supplier_name ? " — Supplier: {$ledger->supplier_name}" : ''),
                    'amount'                     => $settledCreditAmount,
                    'gross_amount'               => $gross,
                    'vat_type'                   => $vatType,
                    'vat_rate'                   => $vatRate,
                    'vat_amount'                 => $vatAmount,
                    'has_withholding'            => $hasWithholding,
                    'withholding_rate'           => $withholdingRate,
                    'withholding_amount'         => $withholdingAmount,
                    'net_amount'                 => $disbursedAmount,
                    'status'                     => ExpenseRequest::STATUS_PAID,
                    'paid_by'                    => Auth::id(),
                    'paid_at'                    => now(),
                    'finance_head_id'            => Auth::id(),
                    'finance_staff_id'           => Auth::id(),
                    'bank_account_id'            => $bankAccountId,
                    'coa_id'                     => $fundingCoaId,
                    'chart_of_account_id'        => $fundingCoaId,
                    'payment_reference'          => $request->reference_no,
                    'payment_notes'              => $paymentNotes,
                    'attachment'                 => $filePath,
                    'withholding_receipt'        => $withholdingReceiptPath,
                    'withholding_receipt_number' => $request->input('withholding_receipt_number'),
                ];

                if (\Illuminate\Support\Facades\Schema::hasColumn('expense_requests', 'credit_store_ledger_id')) {
                    $expReqData['credit_store_ledger_id'] = $ledger->id;
                }

                $expenseRequest = ExpenseRequest::create($expReqData);
                if (\Illuminate\Support\Facades\Schema::hasColumn('credit_store_payments', 'expense_request_id')) {
                    $payment->update(['expense_request_id' => $expenseRequest->id]);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("QuickPay ExpenseRequest create: " . $e->getMessage());
            }

            // 5. Update Ledger balances and status
            $newPaid = (float)$ledger->paid_amount + $settledCreditAmount;
            $newStatus = ($newPaid >= (float)$ledger->credit_amount) ? 'fully_paid' : 'partially_paid';

            $ledger->update([
                'paid_amount' => $newPaid,
                'status'      => $newStatus,
            ]);
        });

        $receiptMsg = $isNoReceipt ? " (without receipt)" : "";
        $taxMsg = "";
        if ($withholdingAmount > 0) {
            $taxMsg = " with 3% Withholding Tax deducted (-ETB " . number_format($withholdingAmount, 2) . ", Net Disbursed: ETB " . number_format($disbursedAmount, 2) . ")";
        }
        return redirect()->route('finance.credit-store.show', $ledger)
            ->with('success', "Payment of " . number_format($settledCreditAmount, 2) . " ETB recorded successfully{$receiptMsg}{$taxMsg}. Deducted from Credit Ledger and logged into Expenses.");
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