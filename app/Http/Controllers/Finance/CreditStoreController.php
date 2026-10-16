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
use App\Models\Receipt;
use App\Models\ReceiptItem;
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
            'purchaser_tin'              => 'nullable|string|max:30',
            'seller_tin'                 => 'nullable|string|max:30',
            'supplier_tin'               => 'nullable|string|max:30',
            'receipt_no'                 => 'nullable|string|max:50',
            'fs_no'                      => 'nullable|string|max:50',
            'vendor_name'                => 'nullable|string|max:255',
            'mrc'                        => 'nullable|string|max:100',
            'mrc_no'                     => 'nullable|string|max:100',
            'receipt_date'               => 'nullable|string|max:50',
            'encoding_date'              => 'nullable|string|max:50',
            'purc_type'                  => 'nullable|string|max:10',
            'purchase_type'              => 'nullable|string|max:10',
            'encoded_by'                 => 'nullable|string|max:100',
            'subtotal'                   => 'nullable|numeric',
            'total_amount'               => 'nullable|numeric',
            'is_verified'                => 'nullable|boolean',
            'vat_included'               => 'nullable',
            'ocr_items_json'             => 'nullable|string',
            'ocr_engine'                 => 'nullable|string|max:100',
            'ocr_confidence'             => 'nullable|string|max:50',
            'ocr_confidence_score'       => 'nullable|numeric',
            'receipt_file'               => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
            'notes'                      => 'nullable|string',
        ]);

        $gross = (float)$request->amount;
        $vatType = $request->input('vat_type', 'none');
        $vatRate = (float)$request->input('vat_rate', 15.00);
        $hasWithholding = $request->boolean('has_withholding');
        $withholdingRate = (float)$request->input('withholding_rate', 3.00);
        $vatIncluded = $request->boolean('vat_included', ($vatType !== 'none'));

        if (!$vatIncluded || $vatType === 'none') {
            $vatType = 'none';
        }

        $vatAmount = 0.0;
        $baseAmount = $gross;
        $withholdingAmount = 0.0;
        $netAmount = $gross;

        if ($vatIncluded && $vatType === 'exclusive') {
            $vatAmount = $request->filled('vat_amount') && (float)$request->vat_amount > 0 
                ? round((float)$request->vat_amount, 2) 
                : round($gross * ($vatRate / 100), 2);
            $baseAmount = $gross;
            $totalGrossWithVat = $gross + $vatAmount;
            if ($hasWithholding) {
                $withholdingAmount = round($baseAmount * ($withholdingRate / 100), 2);
            }
            $netAmount = round($totalGrossWithVat - $withholdingAmount, 2);
        } elseif ($vatIncluded && ($vatType === 'inclusive' || $vatType === 'vat_b')) {
            $baseAmount = round($gross / (1 + ($vatRate / 100)), 2);
            $vatAmount = round($gross - $baseAmount, 2);
            if ($hasWithholding) {
                $withholdingAmount = round($baseAmount * ($withholdingRate / 100), 2);
            }
            $netAmount = round($gross - $withholdingAmount, 2);
        } else {
            $vatType = 'none';
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
        if ($vatIncluded && $vatType !== 'none' && $vatAmount > 0) {
            $taxSummary[] = "VAT ({$vatType}): +ETB " . number_format($vatAmount, 2) . " [Line 100 VAT Section]";
        } else {
            $taxSummary[] = "No VAT [TAS Declaration Excel]";
        }
        if ($hasWithholding && $withholdingAmount > 0) {
            $taxSummary[] = "WHT (3%): -ETB " . number_format($withholdingAmount, 2);
        }
        if (!empty($taxSummary)) {
            $paymentNotes = trim($paymentNotes . " [" . implode(', ', $taxSummary) . " | Disbursed: ETB " . number_format($disbursedAmount, 2) . "]");
        }

        DB::transaction(function () use (
            $ledger, $request, $settledCreditAmount, $gross, $vatType, $vatRate, $vatAmount, $vatIncluded,
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

            // Auto-link to central Receipt table for fiscal tracking and duplicate detection
            if ($filePath) {
                try {
                    $ocrService = app(\App\Services\ReceiptOcrService::class);
                    $fsNo = $request->input('receipt_no') ?: ($request->input('fs_no') ?: (preg_match('/(?:FS-?|fs-?)([0-9]{8})/i', $request->reference_no ?? '', $m) ? $m[1] : null));
                    $purchaserTin = trim((string)($request->input('purchaser_tin') ?: ($request->input('buyer_tin') ?: '0038480010')));
                    $supplierTin = trim((string)($request->input('seller_tin') ?: ($request->input('supplier_tin') ?: ($request->input('vendor_tin') ?: ''))));
                    $vendorName = trim((string)($request->input('vendor_name') ?: ($ledger->vendor_name ?: ($ledger->supplier_name ?: 'Credit Vendor'))));
                    $mrcNo = trim((string)($request->input('mrc') ?: ($request->input('mrc_no') ?: ($request->input('machine_no') ?: ''))));
                    $receiptDate = $request->input('receipt_date') ?: ($request->payment_date ?: now()->toDateString());
                    $encodingDate = $request->input('encoding_date') ?: now()->toDateString();
                    $encodedBy = trim((string)($request->input('encoded_by') ?: (Auth::user()?->name ?: 'wechacha')));
                    $purchaseType = (int)($request->input('purc_type') ?: ($request->input('purchase_type') ?: ($vatIncluded ? 3 : 6)));
                    $effectiveVat = $vatIncluded ? (float)$vatAmount : 0.00;

                    // Parse itemized materials from OCR studio if submitted
                    $items = [];
                    if ($request->filled('ocr_items_json')) {
                        $decoded = json_decode($request->input('ocr_items_json'), true);
                        if (is_array($decoded) && !empty($decoded)) {
                            foreach ($decoded as $it) {
                                $itQty = (float)($it['qty'] ?? 1);
                                $itUnit = (float)($it['unit_price'] ?? 0);
                                $itTotal = (float)($it['total_value'] ?? round($itQty * $itUnit, 2));
                                $itVat = $vatIncluded ? (float)($it['vat'] ?? round($itTotal * 0.15, 2)) : 0.00;
                                $itAfter = $vatIncluded ? (float)($it['value_after_vat'] ?? round($itTotal + $itVat, 2)) : $itTotal;
                                $items[] = [
                                    'item_description' => $it['item_description'] ?? ($ledger->item_description ?: 'Material item'),
                                    'uom'              => (string)($it['uom'] ?? '9'),
                                    'qty'              => $itQty,
                                    'unit_price'       => $itUnit,
                                    'total_value'      => $itTotal,
                                    'vat'              => $itVat,
                                    'value_after_vat'  => $itAfter,
                                    'purchase_type'    => $purchaseType,
                                    'vat_category'     => $vatIncluded ? 'G' : 'E',
                                ];
                            }
                        }
                    }

                    if (empty($items)) {
                        $items[] = [
                            'item_description' => $ledger->item_description ?: 'Credit settlement',
                            'uom'              => '9',
                            'qty'              => 1.0,
                            'unit_price'       => (float)$settledCreditAmount,
                            'total_value'      => (float)$settledCreditAmount,
                            'vat'              => $effectiveVat,
                            'value_after_vat'  => (float)($settledCreditAmount + $effectiveVat),
                            'purchase_type'    => $purchaseType,
                            'vat_category'     => $vatIncluded ? 'G' : 'E',
                        ];
                    }

                    $isVerified = $request->boolean('is_verified', true);
                    $receiptSubtotal = $request->filled('subtotal') ? (float)$request->input('subtotal') : (float)($gross ?: $settledCreditAmount);
                    $receiptVat = $vatIncluded ? ($request->filled('vat_amount') ? (float)$request->input('vat_amount') : $effectiveVat) : 0.00;
                    $receiptTotal = $request->filled('total_amount') ? (float)$request->input('total_amount') : round($receiptSubtotal + $receiptVat, 2);

                    $cleanTin = preg_replace('/[^0-9]/', '', $supplierTin);
                    $cleanFs = preg_replace('/[^0-9]/', '', (string)$fsNo);

                    $scanData = [
                        'vendor_name'       => $vendorName,
                        'supplier_tin'      => $supplierTin,
                        'seller_tin'        => $supplierTin,
                        'tin_valid'         => !empty($cleanTin) && strlen($cleanTin) === 10,
                        'buyer_tin'         => $purchaserTin,
                        'purchaser_tin'     => $purchaserTin,
                        'fs_no'             => $fsNo ?: ($request->reference_no ?: 'CR-PAY'),
                        'fs_no_raw'         => $fsNo ?: ($request->reference_no ?: 'CR-PAY'),
                        'receipt_no'        => $fsNo ?: ($request->reference_no ?: 'CR-PAY'),
                        'fs_no_valid'       => !empty($cleanFs) && strlen($cleanFs) === 8,
                        'mrc_no'            => $mrcNo,
                        'mrc'               => $mrcNo,
                        'receipt_date_ymd'  => $receiptDate,
                        'receipt_date'      => $receiptDate,
                        'encoding_date'     => $encodingDate,
                        'purchase_type'     => $purchaseType,
                        'purc_type'         => $purchaseType,
                        'encoded_by'        => $encodedBy,
                        'subtotal'          => round($receiptSubtotal, 2),
                        'vat_amount'        => round($receiptVat, 2),
                        'total_amount'      => round($receiptTotal, 2),
                        'currency'          => 'ETB',
                        'description'       => "Credit Payment for {$vendorName} (" . ($ledger->item_description ?: 'Ledger #' . $ledger->id) . ")",
                        'engine'            => $request->input('ocr_engine') ?: 'Receipt OCR Studio',
                        'engine_slug'       => 'credit_payment',
                        'confidence'        => $isVerified ? 'high' : ($request->input('ocr_confidence') ?: 'high'),
                        'confidence_score'  => $isVerified ? 100 : (int)($request->input('ocr_confidence_score') ?: 95),
                        'is_verified'       => $isVerified,
                        'qc_notes'          => "Credit store payment receipt #{$payment->id}" . ($vatIncluded ? " [15% VAT Included - Sent to ERCA Line 100 VAT Section]" : " [No VAT - Sent to Purchase TAS Excel (Type {$purchaseType})]") . ($isVerified ? " [Verified by User]" : ""),
                        'vat_included'      => $vatIncluded,
                        'has_vat'           => $vatIncluded,
                        'vat_type'          => $vatType,
                        'items'             => $items,
                    ];

                    $ocrService->saveReceipt(
                        $scanData,
                        $filePath,
                        $payment,
                        Auth::id(),
                        $ledger->project_id ?? null,
                        'credit_settlement',
                        $receiptDate
                    );

                    if (\Illuminate\Support\Facades\Schema::hasColumn('credit_store_payments', 'vat_amount')) {
                        $payment->update([
                            'gross_amount' => $receiptSubtotal,
                            'vat_amount'   => $receiptVat,
                            'vat_type'     => $vatType,
                        ]);
                    }
                } catch (\Throwable $e) {
                    Log::warning("Could not persist linked Receipt for Credit Payment #{$payment->id}: " . $e->getMessage());
                }
            }

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
            'purchaser_tin'       => 'nullable|string|max:30',
            'seller_tin'          => 'nullable|string|max:30',
            'supplier_tin'        => 'nullable|string|max:30',
            'receipt_no'          => 'nullable|string|max:50',
            'fs_no'               => 'nullable|string|max:50',
            'vendor_name'         => 'nullable|string|max:255',
            'mrc'                 => 'nullable|string|max:100',
            'mrc_no'              => 'nullable|string|max:100',
            'receipt_date'        => 'nullable|string|max:50',
            'encoding_date'       => 'nullable|string|max:50',
            'purc_type'           => 'nullable|string|max:10',
            'purchase_type'       => 'nullable|string|max:10',
            'encoded_by'          => 'nullable|string|max:100',
            'subtotal'            => 'nullable|numeric',
            'vat_amount'          => 'nullable|numeric',
            'total_amount'        => 'nullable|numeric',
            'vat_included'        => 'nullable',
            'ocr_items_json'      => 'nullable|string',
            'ocr_engine'          => 'nullable|string|max:100',
            'ocr_confidence'      => 'nullable|string|max:50',
            'ocr_confidence_score'=> 'nullable|numeric',
            'is_verified'         => 'nullable|boolean',
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

        $firstPayment = null;

        DB::transaction(function () use ($request, $filePath, $originalFilename, $bankAccountId, $fundingCoaId, $baseNotes, &$totalPaidSum, &$processedCount, &$firstPayment) {
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

                if (!$firstPayment) {
                    $firstPayment = $payment;
                }

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

        // Auto-link shared receipt to central Receipt table for fiscal tracking and duplicate detection
        if ($filePath && $firstPayment) {
            try {
                $ocrService = app(\App\Services\ReceiptOcrService::class);
                $fsNo = $request->input('receipt_no') ?: ($request->input('fs_no') ?: (preg_match('/(?:FS-?|fs-?)([0-9]{8})/i', $request->reference_no ?? '', $m) ? $m[1] : null));
                $purchaserTin = trim((string)($request->input('purchaser_tin') ?: ($request->input('buyer_tin') ?: '0038480010')));
                $supplierTin = trim((string)($request->input('seller_tin') ?: ($request->input('supplier_tin') ?: ($request->input('vendor_tin') ?: ''))));
                $vendorName = trim((string)($request->input('vendor_name') ?: 'Batch Credit Settlement'));
                $mrcNo = trim((string)($request->input('mrc') ?: ($request->input('mrc_no') ?: ($request->input('machine_no') ?: ''))));
                $receiptDate = $request->input('receipt_date') ?: ($request->payment_date ?: now()->toDateString());
                $encodingDate = $request->input('encoding_date') ?: now()->toDateString();
                $encodedBy = trim((string)($request->input('encoded_by') ?: (Auth::user()?->name ?: 'wechacha')));
                $vatIncluded = $request->boolean('vat_included', false);
                $purchaseType = (int)($request->input('purc_type') ?: ($request->input('purchase_type') ?: ($vatIncluded ? 3 : 6)));
                $vatAmount = $vatIncluded ? (float)$request->input('vat_amount', round($totalPaidSum * 0.15, 2)) : 0.00;

                $items = [];
                if ($request->filled('ocr_items_json')) {
                    $decoded = json_decode($request->input('ocr_items_json'), true);
                    if (is_array($decoded) && !empty($decoded)) {
                        foreach ($decoded as $it) {
                            $itQty = (float)($it['qty'] ?? 1);
                            $itUnit = (float)($it['unit_price'] ?? 0);
                            $itTotal = (float)($it['total_value'] ?? round($itQty * $itUnit, 2));
                            $itVat = $vatIncluded ? (float)($it['vat'] ?? round($itTotal * 0.15, 2)) : 0.00;
                            $itAfter = $vatIncluded ? (float)($it['value_after_vat'] ?? round($itTotal + $itVat, 2)) : $itTotal;
                            $items[] = [
                                'item_description' => $it['item_description'] ?? 'Batch settlement item',
                                'uom'              => (string)($it['uom'] ?? '9'),
                                'qty'              => $itQty,
                                'unit_price'       => $itUnit,
                                'total_value'      => $itTotal,
                                'vat'              => $itVat,
                                'value_after_vat'  => $itAfter,
                                'purchase_type'    => $purchaseType,
                                'vat_category'     => $vatIncluded ? 'G' : 'E',
                            ];
                        }
                    }
                }

                if (empty($items)) {
                    $items[] = [
                        'item_description' => "Batch credit settlement ({$processedCount} items)",
                        'uom'              => '9',
                        'qty'              => 1.0,
                        'unit_price'       => (float)$totalPaidSum,
                        'total_value'      => (float)$totalPaidSum,
                        'vat'              => $vatAmount,
                        'value_after_vat'  => (float)($totalPaidSum + $vatAmount),
                        'purchase_type'    => $purchaseType,
                        'vat_category'     => $vatIncluded ? 'G' : 'E',
                    ];
                }

                $isVerified = $request->boolean('is_verified', true);
                $receiptSubtotal = $request->filled('subtotal') ? (float)$request->input('subtotal') : (float)$totalPaidSum;
                $receiptVat = $vatIncluded ? ($request->filled('vat_amount') ? (float)$request->input('vat_amount') : $vatAmount) : 0.00;
                $receiptTotal = $request->filled('total_amount') ? (float)$request->input('total_amount') : round($receiptSubtotal + $receiptVat, 2);

                $cleanTin = preg_replace('/[^0-9]/', '', $supplierTin);
                $cleanFs = preg_replace('/[^0-9]/', '', (string)$fsNo);

                $scanData = [
                    'vendor_name'       => $vendorName,
                    'supplier_tin'      => $supplierTin,
                    'seller_tin'        => $supplierTin,
                    'tin_valid'         => !empty($cleanTin) && strlen($cleanTin) === 10,
                    'buyer_tin'         => $purchaserTin,
                    'purchaser_tin'     => $purchaserTin,
                    'fs_no'             => $fsNo ?: ($request->reference_no ?: 'BATCH-CR'),
                    'fs_no_raw'         => $fsNo ?: ($request->reference_no ?: 'BATCH-CR'),
                    'receipt_no'        => $fsNo ?: ($request->reference_no ?: 'BATCH-CR'),
                    'fs_no_valid'       => !empty($cleanFs) && strlen($cleanFs) === 8,
                    'mrc_no'            => $mrcNo,
                    'mrc'               => $mrcNo,
                    'receipt_date_ymd'  => $receiptDate,
                    'receipt_date'      => $receiptDate,
                    'encoding_date'     => $encodingDate,
                    'purchase_type'     => $purchaseType,
                    'purc_type'         => $purchaseType,
                    'encoded_by'        => $encodedBy,
                    'subtotal'          => round($receiptSubtotal, 2),
                    'vat_amount'        => round($receiptVat, 2),
                    'total_amount'      => round($receiptTotal, 2),
                    'currency'          => 'ETB',
                    'description'       => "Batch credit settlement for {$processedCount} purchases",
                    'engine'            => $request->input('ocr_engine') ?: 'Batch Receipt OCR Studio',
                    'engine_slug'       => 'credit_batch',
                    'confidence'        => $isVerified ? 'high' : ($request->input('ocr_confidence') ?: 'high'),
                    'confidence_score'  => $isVerified ? 100 : (int)($request->input('ocr_confidence_score') ?: 95),
                    'is_verified'       => $isVerified,
                    'qc_notes'          => "Batch settlement shared receipt ({$processedCount} purchases)" . ($vatIncluded ? " [15% VAT Included - Sent to ERCA Line 100 VAT Section]" : " [No VAT - Sent to Purchase TAS Excel (Type {$purchaseType})]") . ($isVerified ? " [Verified by User]" : ""),
                    'vat_included'      => $vatIncluded,
                    'has_vat'           => $vatIncluded,
                    'items'             => $items,
                ];

                $ocrService->saveReceipt(
                    $scanData,
                    $filePath,
                    $firstPayment,
                    Auth::id(),
                    null,
                    'credit_settlement',
                    $receiptDate
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Could not persist linked Receipt for Batch Credit Payment: " . $e->getMessage());
            }
        }

        $receiptText = $isNoReceipt ? "without receipt" : "with shared receipt attached";
        return redirect()->route('finance.credit-store.index')
            ->with('success', "Batch credit settlement complete! Recorded payment for {$processedCount} credit purchase(s) totaling " . number_format($totalPaidSum, 2) . " ETB {$receiptText}.");
    }

    /**
     * Attach a scanned/verified receipt to an existing credit store payment.
     */
    public function attachReceipt(Request $request, CreditStoreLedger $creditStore, CreditStorePayment $payment)
    {
        $ledger = $creditStore;

        $request->validate([
            'receipt_file'        => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
            'purchaser_tin'       => 'nullable|string|max:30',
            'seller_tin'          => 'nullable|string|max:30',
            'supplier_tin'        => 'nullable|string|max:30',
            'vendor_tin'          => 'nullable|string|max:30',
            'receipt_no'          => 'nullable|string|max:50',
            'fs_no'               => 'nullable|string|max:50',
            'vendor_name'         => 'nullable|string|max:255',
            'mrc'                 => 'nullable|string|max:100',
            'mrc_no'              => 'nullable|string|max:100',
            'receipt_date'        => 'nullable|string|max:50',
            'encoding_date'       => 'nullable|string|max:50',
            'purc_type'           => 'nullable|string|max:10',
            'purchase_type'       => 'nullable|string|max:10',
            'encoded_by'          => 'nullable|string|max:100',
            'subtotal'            => 'nullable|numeric',
            'vat_amount'          => 'nullable|numeric',
            'total_amount'        => 'nullable|numeric',
            'vat_included'        => 'nullable',
            'ocr_items_json'      => 'nullable|string',
            'ocr_engine'          => 'nullable|string|max:100',
            'ocr_confidence'      => 'nullable|string|max:50',
            'ocr_confidence_score'=> 'nullable|numeric',
            'is_verified'         => 'nullable|boolean',
        ]);

        $file = $request->file('receipt_file');
        $filePath = FileUploadService::upload($file, 'credit_receipts');
        $originalFilename = $file->getClientOriginalName();

        $payment->update([
            'receipt_path'      => $filePath,
            'original_filename' => $originalFilename,
        ]);

        try {
            $ocrService = app(\App\Services\ReceiptOcrService::class);
            $fsNo = $request->input('receipt_no') ?: ($request->input('fs_no') ?: ($payment->reference_no ?: 'CR-PAY'));
            $purchaserTin = trim((string)($request->input('purchaser_tin') ?: ($request->input('buyer_tin') ?: '0038480010')));
            $supplierTin = trim((string)($request->input('seller_tin') ?: ($request->input('supplier_tin') ?: ($request->input('vendor_tin') ?: ''))));
            $vendorName = trim((string)($request->input('vendor_name') ?: ($ledger->vendor_name ?: ($ledger->supplier_name ?: 'Credit Vendor'))));
            $mrcNo = trim((string)($request->input('mrc') ?: ($request->input('mrc_no') ?: '')));
            $receiptDate = $request->input('receipt_date') ?: ($payment->payment_date ?: now()->toDateString());
            $encodingDate = $request->input('encoding_date') ?: now()->toDateString();
            $encodedBy = trim((string)($request->input('encoded_by') ?: (Auth::user()?->name ?: 'wechacha')));
            $vatIncluded = $request->boolean('vat_included', ((float)($payment->vat_amount ?? 0) > 0));
            $purchaseType = (int)($request->input('purc_type') ?: ($request->input('purchase_type') ?: ($vatIncluded ? 3 : 6)));
            $isVerified = $request->boolean('is_verified', true);
            $receiptSubtotal = $request->filled('subtotal') ? (float)$request->input('subtotal') : (float)$payment->amount;
            $vatAmount = $vatIncluded ? (float)$request->input('vat_amount', $payment->vat_amount ?? round($receiptSubtotal * 0.15, 2)) : 0.00;
            $receiptTotal = $request->filled('total_amount') ? (float)$request->input('total_amount') : round($receiptSubtotal + $vatAmount, 2);

            // Parse itemized materials from OCR studio if submitted
            $items = [];
            if ($request->filled('ocr_items_json')) {
                $decoded = json_decode($request->input('ocr_items_json'), true);
                if (is_array($decoded) && !empty($decoded)) {
                    foreach ($decoded as $it) {
                        $itQty = (float)($it['qty'] ?? 1);
                        $itUnit = (float)($it['unit_price'] ?? 0);
                        $itTotal = (float)($it['total_value'] ?? round($itQty * $itUnit, 2));
                        $itVat = $vatIncluded ? (float)($it['vat'] ?? round($itTotal * 0.15, 2)) : 0.00;
                        $itAfter = $vatIncluded ? (float)($it['value_after_vat'] ?? round($itTotal + $itVat, 2)) : $itTotal;
                        $items[] = [
                            'item_description' => $it['item_description'] ?? ($ledger->item_description ?: 'Material item'),
                            'uom'              => (string)($it['uom'] ?? '9'),
                            'qty'              => $itQty,
                            'unit_price'       => $itUnit,
                            'total_value'      => $itTotal,
                            'vat'              => $itVat,
                            'value_after_vat'  => $itAfter,
                            'purchase_type'    => $purchaseType,
                            'vat_category'     => $vatIncluded ? 'G' : 'E',
                        ];
                    }
                }
            }

            if (empty($items)) {
                $items[] = [
                    'item_description' => $ledger->item_description ?: 'Credit settlement',
                    'uom'              => '9',
                    'qty'              => 1.0,
                    'unit_price'       => (float)$receiptSubtotal,
                    'total_value'      => (float)$receiptSubtotal,
                    'vat'              => $vatAmount,
                    'value_after_vat'  => (float)($receiptSubtotal + $vatAmount),
                    'purchase_type'    => $purchaseType,
                    'vat_category'     => $vatIncluded ? 'G' : 'E',
                ];
            }

            $cleanTin = preg_replace('/[^0-9]/', '', $supplierTin);
            $cleanFs = preg_replace('/[^0-9]/', '', (string)$fsNo);

            $scanData = [
                'vendor_name'       => $vendorName,
                'supplier_tin'      => $supplierTin,
                'seller_tin'        => $supplierTin,
                'tin_valid'         => !empty($cleanTin) && strlen($cleanTin) === 10,
                'buyer_tin'         => $purchaserTin,
                'purchaser_tin'     => $purchaserTin,
                'fs_no'             => $fsNo,
                'fs_no_raw'         => $fsNo,
                'receipt_no'        => $fsNo,
                'fs_no_valid'       => !empty($cleanFs) && strlen($cleanFs) === 8,
                'mrc_no'            => $mrcNo,
                'mrc'               => $mrcNo,
                'receipt_date_ymd'  => $receiptDate,
                'receipt_date'      => $receiptDate,
                'encoding_date'     => $encodingDate,
                'purchase_type'     => $purchaseType,
                'purc_type'         => $purchaseType,
                'encoded_by'        => $encodedBy,
                'subtotal'          => round($receiptSubtotal, 2),
                'vat_amount'        => round($vatAmount, 2),
                'total_amount'      => round($receiptTotal, 2),
                'currency'          => 'ETB',
                'description'       => "Receipt attached for {$vendorName} (#{$payment->id})",
                'engine'            => $request->input('ocr_engine') ?: 'Receipt OCR Studio',
                'engine_slug'       => 'credit_payment',
                'confidence'        => $isVerified ? 'high' : ($request->input('ocr_confidence') ?: 'high'),
                'confidence_score'  => $isVerified ? 100 : (int)($request->input('ocr_confidence_score') ?: 95),
                'is_verified'       => $isVerified,
                'qc_notes'          => "Receipt attached to payment #{$payment->id}" . ($vatIncluded ? " [15% VAT Included - Sent to ERCA Line 100 VAT Section]" : " [No VAT - Sent to Purchase TAS Excel (Type {$purchaseType})]") . ($isVerified ? " [Verified by User]" : ""),
                'vat_included'      => $vatIncluded,
                'has_vat'           => $vatIncluded,
                'items'             => $items,
            ];

            $ocrService->saveReceipt(
                $scanData,
                $filePath,
                $payment,
                Auth::id(),
                $ledger->project_id ?? null,
                'credit_settlement',
                $receiptDate
            );

            $paymentUpdate = [
                'vat_type'   => $vatIncluded ? 'exclusive' : 'none',
                'vat_amount' => $vatAmount,
            ];
            if ($fsNo) $paymentUpdate['reference_no'] = $fsNo;
            if (\Illuminate\Support\Facades\Schema::hasColumn('credit_store_payments', 'gross_amount')) {
                $paymentUpdate['gross_amount'] = $receiptSubtotal;
            }
            $payment->update($paymentUpdate);

            if ($payment->expense_request_id) {
                ExpenseRequest::where('id', $payment->expense_request_id)->update([
                    'attachment'   => $filePath,
                    'gross_amount' => $receiptSubtotal,
                    'vat_amount'   => $vatAmount,
                    'vat_type'     => $vatIncluded ? 'exclusive' : 'none',
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning("Could not persist linked Receipt for attached payment #{$payment->id}: " . $e->getMessage());
        }

        return redirect()->route('finance.credit-store.show', $ledger)
            ->with('success', 'Receipt attached and verified via Receipt OCR Studio successfully!');
    }

    /**
     * Global Admin Only: Correct VAT, taxes, and receipt fields on a Credit Store Payment (even when Fully Paid).
     */
    public function correctVat(Request $request, CreditStoreLedger $creditStore, CreditStorePayment $payment)
    {
        $user = Auth::user();
        if (!$user || !$user->isGlobalAdmin()) {
            abort(403, 'Unauthorized. Only Global Admins can edit or correct VAT on credit store payments.');
        }

        $ledger = $creditStore;

        $request->validate([
            'amount'                     => 'required|numeric|min:0.01',
            'vat_type'                   => 'required|string|in:none,exclusive,vat_b,inclusive',
            'vat_rate'                   => 'nullable|numeric|min:0',
            'vat_amount'                 => 'nullable|numeric|min:0',
            'has_withholding'            => 'nullable|boolean',
            'withholding_rate'           => 'nullable|numeric|min:0',
            'withholding_amount'         => 'nullable|numeric|min:0',
            'withholding_receipt'        => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
            'withholding_receipt_number' => 'nullable|string|max:100',
            'supplier_tin'               => 'nullable|string|max:30',
            'seller_tin'                 => 'nullable|string|max:30',
            'fs_no'                      => 'nullable|string|max:50',
            'receipt_no'                 => 'nullable|string|max:50',
            'mrc_no'                     => 'nullable|string|max:100',
            'receipt_date'               => 'nullable|string|max:50',
            'purc_type'                  => 'nullable|string|max:10',
            'correction_reason'          => 'required|string|max:500',
        ]);

        $gross = (float)$request->amount;
        $vatType = $request->vat_type;
        $vatRate = (float)$request->input('vat_rate', 15.00);
        $hasWithholding = $request->boolean('has_withholding');
        $withholdingRate = (float)$request->input('withholding_rate', 3.00);

        $vatAmount = 0.0;
        $baseAmount = $gross;
        $withholdingAmount = 0.0;
        $netAmount = $gross;

        if ($vatType === 'exclusive') {
            $vatAmount = $request->filled('vat_amount') 
                ? round((float)$request->vat_amount, 2) 
                : round($gross * ($vatRate / 100), 2);
            $baseAmount = $gross;
            $totalGrossWithVat = $gross + $vatAmount;
            if ($hasWithholding) {
                $withholdingAmount = $request->filled('withholding_amount') 
                    ? round((float)$request->withholding_amount, 2) 
                    : round($baseAmount * ($withholdingRate / 100), 2);
            }
            $netAmount = round($totalGrossWithVat - $withholdingAmount, 2);
        } elseif ($vatType === 'inclusive' || $vatType === 'vat_b') {
            $baseAmount = round($gross / (1 + ($vatRate / 100)), 2);
            $vatAmount = $request->filled('vat_amount') 
                ? round((float)$request->vat_amount, 2) 
                : round($gross - $baseAmount, 2);
            if ($hasWithholding) {
                $withholdingAmount = $request->filled('withholding_amount') 
                    ? round((float)$request->withholding_amount, 2) 
                    : round($baseAmount * ($withholdingRate / 100), 2);
            }
            $netAmount = round($gross - $withholdingAmount, 2);
        } else {
            $vatType = 'none';
            $baseAmount = $gross;
            $vatAmount = 0.0;
            if ($hasWithholding) {
                $withholdingAmount = $request->filled('withholding_amount') 
                    ? round((float)$request->withholding_amount, 2) 
                    : round($baseAmount * ($withholdingRate / 100), 2);
            }
            $netAmount = round($gross - $withholdingAmount, 2);
        }

        $withholdingReceiptPath = $payment->withholding_receipt;
        if ($request->hasFile('withholding_receipt')) {
            $wFile = $request->file('withholding_receipt');
            $withholdingReceiptPath = FileUploadService::upload($wFile, 'withholding_receipts');
        }

        $vatIncluded = ($vatType !== 'none' && $vatAmount > 0);
        $purchaseType = (int)($request->input('purc_type') ?: ($vatIncluded ? 3 : 6));
        $totalReceiptVal = ($vatType === 'exclusive') ? round($gross + $vatAmount, 2) : $gross;

        $adminName = $user->name ?: 'Global Admin';
        $reason = trim($request->correction_reason);
        $auditLog = "[VAT Corrected by {$adminName} on " . now()->format('Y-m-d H:i') . ": {$reason} | Mode: {$vatType}, VAT: ETB " . number_format($vatAmount, 2) . ", WHT: ETB " . number_format($withholdingAmount, 2) . ", Net: ETB " . number_format($netAmount, 2) . "]";
        $updatedNotes = trim(($payment->notes ? $payment->notes . "\n" : "") . $auditLog);

        $fsNo = $request->input('receipt_no') ?: ($request->input('fs_no') ?: $payment->reference_no);
        $supplierTin = trim((string)($request->input('seller_tin') ?: ($request->input('supplier_tin') ?: '')));
        $mrcNo = trim((string)($request->input('mrc_no') ?: ''));
        $receiptDate = $request->input('receipt_date') ?: ($payment->payment_date ? $payment->payment_date->toDateString() : now()->toDateString());

        DB::transaction(function () use (
            $payment, $ledger, $gross, $vatType, $vatRate, $vatAmount, $hasWithholding,
            $withholdingRate, $withholdingAmount, $withholdingReceiptPath, $request,
            $netAmount, $fsNo, $updatedNotes, $baseAmount, $vatIncluded, $purchaseType,
            $totalReceiptVal, $supplierTin, $mrcNo, $receiptDate
        ) {
            // 1. Update Payment Record
            $paymentData = [
                'amount'       => $gross,
                'reference_no' => $fsNo ?: $payment->reference_no,
                'notes'        => $updatedNotes,
            ];

            if (\Illuminate\Support\Facades\Schema::hasColumn('credit_store_payments', 'gross_amount')) {
                $paymentData['gross_amount']               = $baseAmount;
                $paymentData['vat_type']                   = $vatType;
                $paymentData['vat_rate']                   = $vatRate;
                $paymentData['vat_amount']                 = $vatAmount;
                $paymentData['has_withholding']            = $hasWithholding;
                $paymentData['withholding_rate']           = $withholdingRate;
                $paymentData['withholding_amount']         = $withholdingAmount;
                $paymentData['withholding_receipt']        = $withholdingReceiptPath;
                $paymentData['withholding_receipt_number'] = $request->input('withholding_receipt_number', $payment->withholding_receipt_number);
                $paymentData['net_amount']                 = $netAmount;
            }

            $payment->update($paymentData);

            // 2. Synchronize linked central Receipt and ReceiptItems
            $receipts = Receipt::where(function($q) use ($payment) {
                $q->where('purchasable_type', CreditStorePayment::class)
                  ->where('purchasable_id', $payment->id);
            });
            if ($payment->receipt_path) {
                $receipts = $receipts->orWhere('file_path', $payment->receipt_path);
            }
            $receiptRecords = $receipts->get();

            foreach ($receiptRecords as $receipt) {
                $cleanTin = $supplierTin ? preg_replace('/[^0-9]/', '', $supplierTin) : preg_replace('/[^0-9]/', '', (string)$receipt->vendor_tin);
                $cleanFs = $fsNo ? preg_replace('/[^0-9]/', '', (string)$fsNo) : preg_replace('/[^0-9]/', '', (string)$receipt->fs_no);

                $receiptUpdate = [
                    'subtotal'     => $baseAmount,
                    'vat_amount'   => $vatAmount,
                    'total_amount' => $totalReceiptVal,
                    'qc_notes'     => "Credit payment receipt #{$payment->id} [VAT Corrected by Global Admin to {$vatType}: ETB " . number_format($vatAmount, 2) . " - " . ($vatIncluded ? "Sent to ERCA Line 100 VAT Section (PurcType 3)" : "Sent to Purchase TAS Excel (PurcType {$purchaseType})") . "]",
                ];

                if (!empty($supplierTin)) {
                    $receiptUpdate['vendor_tin'] = $supplierTin;
                    $receiptUpdate['tin_valid']  = (strlen($cleanTin) === 10);
                }
                if (!empty($fsNo)) {
                    $receiptUpdate['fs_no']       = $fsNo;
                    $receiptUpdate['fs_no_raw']   = $fsNo;
                    $receiptUpdate['fs_no_valid'] = (strlen($cleanFs) === 8);
                }
                if (!empty($mrcNo)) {
                    $receiptUpdate['mrc_no'] = $mrcNo;
                }
                if (!empty($receiptDate)) {
                    $receiptUpdate['receipt_date'] = $receiptDate;
                }

                $parsed = is_string($receipt->parsed_data) ? (json_decode($receipt->parsed_data, true) ?: []) : (array)($receipt->parsed_data ?? []);
                $parsed['vat_type']      = $vatType;
                $parsed['vat_amount']    = $vatAmount;
                $parsed['subtotal']      = $baseAmount;
                $parsed['total_amount']  = $totalReceiptVal;
                $parsed['purc_type']     = $purchaseType;
                $parsed['purchase_type'] = $purchaseType;
                $parsed['vat_included']  = $vatIncluded;
                if (!empty($supplierTin)) $parsed['seller_tin'] = $supplierTin;
                if (!empty($fsNo)) $parsed['receipt_no'] = $fsNo;
                if (!empty($mrcNo)) $parsed['mrc'] = $mrcNo;
                if (!empty($receiptDate)) $parsed['receipt_date'] = $receiptDate;
                $receiptUpdate['parsed_data'] = $parsed;

                $receipt->update($receiptUpdate);

                // Update items in receipt_items
                foreach ($receipt->items as $it) {
                    $it->update([
                        'vat_category'    => $vatIncluded ? 'G' : 'E',
                        'purchase_type'   => $purchaseType,
                        'vat_amount'      => $vatAmount,
                        'value_after_vat' => $totalReceiptVal,
                    ]);
                }
            }

            // 3. Update linked ExpenseRequest if present
            if ($payment->expense_request_id) {
                try {
                    ExpenseRequest::where('id', $payment->expense_request_id)->update([
                        'vat_type'           => $vatType,
                        'vat_rate'           => $vatRate,
                        'vat_amount'         => $vatAmount,
                        'has_withholding'    => $hasWithholding,
                        'withholding_rate'   => $withholdingRate,
                        'withholding_amount' => $withholdingAmount,
                        'gross_amount'       => $baseAmount,
                        'amount'             => $gross,
                    ]);
                } catch (\Throwable $ex) {}
            }

            // 4. Recalculate Ledger paid amount & status
            $totalPaid = (float)$ledger->payments()->sum('amount');
            $newStatus = ($totalPaid >= (float)$ledger->credit_amount) ? 'fully_paid' : ($totalPaid > 0 ? 'partially_paid' : 'unpaid');
            $ledger->update([
                'paid_amount' => $totalPaid,
                'status'      => $newStatus,
            ]);
        });

        $routeMsg = $vatIncluded 
            ? "Saved into ERCA Line 100 VAT Section (+ETB " . number_format($vatAmount, 2) . ")" 
            : "Declared in Purchase TAS Excel (Zero VAT / PurcType {$purchaseType})";

        return redirect()->route('finance.credit-store.show', $ledger)
            ->with('success', "✅ Global Admin VAT correction applied for Payment #{$payment->id}! VAT updated to " . strtoupper(str_replace('_', ' ', $vatType)) . " (ETB " . number_format($vatAmount, 2) . "). {$routeMsg}.");
    }
}