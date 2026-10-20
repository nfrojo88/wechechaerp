<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\ExpenseHubVatDecision;
use App\Models\ExpenseHubVatAuditLog;
use App\Models\ExpenseHubMonthLock;
use App\Models\Project;
use App\Models\User;
use App\Services\ExpenseHubQueryService;
use App\Services\EthiopianDate;
use App\Services\ReceiptOcrService;
use App\Services\QcService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseHubController extends Controller
{
    protected ExpenseHubQueryService $queryService;

    public function __construct(ExpenseHubQueryService $queryService)
    {
        $this->middleware('auth');
        $this->queryService = $queryService;
    }

    /**
     * Display the Expense Hub Dashboard
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        // 1. Parse active tab & filters
        $activeTab = $request->input('tab', 'all_expenses'); // all_expenses, vat_report, vat_summary

        $filters = [
            'date_from'     => $request->input('date_from'),
            'date_to'       => $request->input('date_to'),
            'project_id'    => $request->input('project_id'),
            'source_module' => $request->input('source_module'),
            'vat_status'    => $request->input('vat_status'),
            'category'      => $request->input('category'),
            'supplier'      => $request->input('supplier'),
            'search'        => $request->input('search'),
        ];

        // 2. Query all cash-basis disbursements (read-only)
        $allExpenses = $this->queryService->getExpenses($filters, $user);

        // 3. Compute Top Summary Cards
        $summary = $this->queryService->getSummaryCards($allExpenses);

        // 4. Compute Monthly VAT Summary
        $monthlyVatSummary = $this->queryService->getMonthlyVatSummary($allExpenses);

        // 5. Separate collection for Official VAT Report (only where vat_status = vat_applies)
        $vatReportItems = $allExpenses->where('vat_status', ExpenseHubVatDecision::STATUS_VAT_APPLIES)->values();

        // 6. Paginate results for Tab 1 (All Expenses)
        $page = (int) $request->input('page', 1);
        $perPage = 25;
        $paginatedExpenses = new LengthAwarePaginator(
            $allExpenses->forPage($page, $perPage),
            $allExpenses->count(),
            $perPage,
            $page,
            ['path' => route('finance.expense-hub.index'), 'query' => $request->query()]
        );

        // Dropdown data
        $projects = Project::orderBy('name')->get();
        $sourceModules = [
            'procurement_payments'          => 'Procurement Payments',
            'credit_store_payments'         => 'Credit Store Settlements',
            'expense_requests'              => 'Expense Requests / Vouchers',
            'petty_cash_material_purchases' => 'Petty Cash Purchases',
            'payrolls'                      => 'Payroll Disbursements',
            'employee_advances'             => 'Salary Advances',
            'ipc_records'                   => 'Subcontractor IPCs',
            'tax_settlements'               => 'Tax Settlements',
            'fixed_assets'                  => 'Fixed Assets (CapEx)',
        ];

        $reasons = ExpenseHubVatDecision::NO_VAT_REASONS;
        $purchaseTypes = ExpenseHubVatDecision::PURCHASE_TYPES;

        $canDecideVat = $user->hasAnyRole(['global_admin', 'admin', 'finance_head', 'finance', 'auditor']) || $user->can('finance.approve');
        $isFinanceHead = $user->hasAnyRole(['global_admin', 'admin', 'finance_head']);

        return view('finance.expense-hub.index', compact(
            'paginatedExpenses',
            'allExpenses',
            'vatReportItems',
            'summary',
            'monthlyVatSummary',
            'activeTab',
            'filters',
            'projects',
            'sourceModules',
            'reasons',
            'purchaseTypes',
            'canDecideVat',
            'isFinanceHead'
        ));
    }

    /**
     * Scan receipt via AI OCR for Expense Hub VAT Decision Engine
     */
    public function scanReceipt(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasAnyRole(['global_admin', 'admin', 'finance_head', 'finance', 'auditor']) && !$user->can('finance.approve')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to perform OCR scans.',
            ], 403);
        }

        $request->validate([
            'receipt_file' => 'nullable|file|mimes:jpeg,jpg,png,webp,pdf|max:25600',
            'receipt_path' => 'nullable|string|max:500',
        ]);

        try {
            $scanPath = null;
            $fileUrl  = null;

            if ($request->hasFile('receipt_file')) {
                $file = $request->file('receipt_file');
                $ext = strtolower($file->getClientOriginalExtension());
                $filename = 'hub_rcpt_' . now()->format('Ymd_His') . '_' . uniqid() . '.' . $ext;
                $savedRelativePath = $file->storeAs('receipts', $filename, 'public');
                $scanPath = $file->getRealPath();
                $fileUrl = asset('storage/' . $savedRelativePath);
            } elseif ($request->filled('receipt_path')) {
                $rawPath = $request->input('receipt_path');
                $stripped = ltrim(preg_replace('#^(storage/|/storage/)#i', '', $rawPath), '/');

                if (Storage::disk('public')->exists($stripped)) {
                    $scanPath = Storage::disk('public')->path($stripped);
                } elseif (file_exists(public_path('storage/' . $stripped))) {
                    $scanPath = public_path('storage/' . $stripped);
                } elseif (file_exists($rawPath)) {
                    $scanPath = $rawPath;
                } else {
                    $scanPath = $stripped;
                }

                $fileUrl = asset('storage/' . $stripped);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select a receipt file or provide an attached receipt.',
                ], 422);
            }

            $ocrService = app(ReceiptOcrService::class);
            $scan = $ocrService->scan($scanPath);

            // Extract normalized TIN
            $rawTin      = trim((string)($scan['supplier_tin'] ?? ($scan['extracted']['supplier_tin'] ?? ($scan['extracted']['merchant_tin'] ?? ''))));
            $tinNorm     = class_exists(QcService::class) ? QcService::normalizeTin($rawTin) : ['value' => $rawTin, 'is_valid' => strlen($rawTin) === 10];
            $supplierTin = $tinNorm['value'] ?: $rawTin;

            // Extract normalized FS No
            $rawFs  = trim((string)($scan['fs_no'] ?? ($scan['extracted']['fs_no'] ?? ($scan['extracted']['receipt_no'] ?? ''))));
            $fsNorm = class_exists(QcService::class) ? QcService::normalizeFsNo($rawFs) : ['value' => $rawFs, 'is_valid' => !empty($rawFs)];
            $fsNo   = $fsNorm['value'] ?: $rawFs;

            $mrcNo       = trim((string)($scan['mrc_no'] ?? ($scan['extracted']['machine_no'] ?? ($scan['extracted']['mrc_no'] ?? ''))));
            $vendorName  = trim((string)($scan['vendor_name'] ?? ($scan['extracted']['merchant_name'] ?? '')));
            $subtotal    = (float)($scan['subtotal'] ?? 0);
            $vatAmount   = (float)($scan['vat_amount'] ?? 0);
            $totalAmount = (float)($scan['total_amount'] ?? 0);

            // Cross-calculate 15% VAT if needed
            if ($totalAmount > 0 && $vatAmount == 0 && $subtotal == 0) {
                $subtotal  = round($totalAmount / 1.15, 2);
                $vatAmount = round($totalAmount - $subtotal, 2);
            } elseif ($subtotal > 0 && $vatAmount == 0 && $totalAmount > $subtotal) {
                $vatAmount = round($totalAmount - $subtotal, 2);
            } elseif ($totalAmount == 0 && $subtotal > 0 && $vatAmount > 0) {
                $totalAmount = round($subtotal + $vatAmount, 2);
            }

            // Purchase Type: 1 = Capital, 2 = Goods Line 70, 3 = Services Line 75
            $purchaseType = $scan['purchase_type_int'] ?? 2;
            if (($scan['purchase_type'] ?? '') === 'PUR_SR') {
                $purchaseType = 3;
            }

            // Verified if TIN, FS No, or VAT Amount are extracted
            $isVerified = (!empty($fsNo) || !empty($supplierTin) || $vatAmount > 0);

            return response()->json([
                'success'           => true,
                'is_verified'       => $isVerified,
                'file_url'          => $fileUrl,
                'vendor_name'       => $vendorName,
                'supplier_tin'      => $supplierTin,
                'tin_valid'         => $tinNorm['is_valid'] ?? (!empty($supplierTin) && strlen($supplierTin) === 10),
                'fs_no'             => $fsNo,
                'fs_no_valid'       => $fsNorm['is_valid'] ?? !empty($fsNo),
                'mrc_no'            => $mrcNo,
                'receipt_date'      => $scan['receipt_date_ymd'] ?? now()->toDateString(),
                'subtotal'          => $subtotal,
                'vat_amount'        => $vatAmount,
                'total_amount'      => $totalAmount,
                'vat_rate'          => 15.00,
                'is_inclusive'      => true,
                'purchase_type'     => $purchaseType,
                'description'       => $scan['description'] ?? '',
                'engine'            => $scan['engine'] ?? 'AI OCR Vision Engine',
                'confidence'        => $scan['confidence'] ?? 'high',
                'confidence_score'  => $scan['confidence_score'] ?? 95,
                'is_duplicate'      => $scan['is_duplicate'] ?? false,
                'duplicate_message' => $scan['duplicate_message'] ?? null,
                'warnings'          => $scan['warnings'] ?? [],
            ]);
        } catch (\Throwable $e) {
            Log::error('ExpenseHub OCR failure: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'OCR Scan failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Save VAT Decision for a single row
     */
    public function saveDecision(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasAnyRole(['global_admin', 'admin', 'finance_head', 'finance', 'auditor']) && !$user->can('finance.approve')) {
            abort(403, 'Unauthorized to record VAT decisions.');
        }

        $validated = $request->validate([
            'source_type'             => 'required|string|max:60',
            'source_id'               => 'required|integer',
            'vat_status'              => 'required|in:vat_applies,no_vat,pending_decision',
            'amount'                  => 'required|numeric|min:0',
            'vat_rate'                => 'nullable|numeric|min:0|max:100',
            'is_inclusive'            => 'nullable|boolean',
            'no_vat_reason_code'      => 'nullable|string|max:50',
            'no_vat_reason_note'      => 'nullable|string|max:500',
            'vat_category'            => 'nullable|in:G,S',
            'purchase_type'           => 'nullable|integer|between:1,6',
            'custom_seller_tin'       => 'nullable|string|max:30',
            'custom_fs_no'            => 'nullable|string|max:50',
            'custom_mrc_no'           => 'nullable|string|max:50',
            'custom_item_description' => 'nullable|string|max:255',
            'uom'                     => 'nullable|string|max:20',
            'quantity'                => 'nullable|numeric|min:0.01',
            'unit_price'              => 'nullable|numeric|min:0',
        ]);

        $vatStatus = $validated['vat_status'];
        $amount    = (float) $validated['amount'];
        $vatRate   = isset($validated['vat_rate']) ? (float)$validated['vat_rate'] : 15.00;
        $isInclusive = $request->boolean('is_inclusive', true);

        // Check if existing record is locked
        $existing = ExpenseHubVatDecision::where('source_type', $validated['source_type'])
            ->where('source_id', $validated['source_id'])
            ->first();

        if ($existing && $existing->is_locked && !$user->hasAnyRole(['global_admin', 'admin', 'finance_head'])) {
            return response()->json([
                'success' => false,
                'message' => 'This expense row belongs to a closed month and is locked. Contact Finance Head to unlock.',
            ], 422);
        }

        // Compute Base & VAT amounts
        if ($vatStatus === ExpenseHubVatDecision::STATUS_VAT_APPLIES) {
            $calc = ExpenseHubVatDecision::computeVatAmounts($amount, $vatRate, $isInclusive);
            $baseAmount  = $calc['base'];
            $vatAmount   = $calc['vat'];
            $totalAmount = $calc['total'];
        } else {
            $baseAmount  = $amount;
            $vatAmount   = 0.00;
            $totalAmount = $amount;
        }

        $decisionData = [
            'vat_status'              => $vatStatus,
            'vat_rate'                => $vatRate,
            'is_inclusive'            => $isInclusive,
            'base_amount'             => $baseAmount,
            'vat_amount'              => $vatAmount,
            'total_amount'            => $totalAmount,
            'no_vat_reason_code'      => $vatStatus === 'no_vat' ? ($validated['no_vat_reason_code'] ?? null) : null,
            'no_vat_reason_note'      => $vatStatus === 'no_vat' ? ($validated['no_vat_reason_note'] ?? null) : null,
            'vat_category'            => $validated['vat_category'] ?? 'G',
            'calendar_type'           => 'E',
            'purchase_type'           => $validated['purchase_type'] ?? 3,
            'custom_seller_tin'       => $validated['custom_seller_tin'] ?? null,
            'custom_fs_no'            => $validated['custom_fs_no'] ?? null,
            'custom_mrc_no'           => $validated['custom_mrc_no'] ?? null,
            'custom_item_description' => $validated['custom_item_description'] ?? null,
            'uom'                     => $validated['uom'] ?? '9',
            'quantity'                => $validated['quantity'] ?? 1.0,
            'unit_price'              => $validated['unit_price'] ?? ($baseAmount / max((float)($validated['quantity'] ?? 1), 1)),
            'decided_by'              => $user->id,
            'decided_at'              => now(),
        ];

        DB::transaction(function () use ($validated, $decisionData, $existing, $user) {
            $decision = ExpenseHubVatDecision::updateOrCreate(
                [
                    'source_type' => $validated['source_type'],
                    'source_id'   => $validated['source_id'],
                ],
                $decisionData
            );

            // Audit log
            ExpenseHubVatAuditLog::create([
                'decision_id'  => $decision->id,
                'source_type'  => $validated['source_type'],
                'source_id'    => $validated['source_id'],
                'action'       => $existing ? 'status_changed' : 'created',
                'old_values'   => $existing ? $existing->only(['vat_status', 'base_amount', 'vat_amount', 'no_vat_reason_code']) : null,
                'new_values'   => $decisionData,
                'performed_by' => $user->id,
            ]);
        });

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'VAT decision saved successfully.',
            ]);
        }

        return back()->with('success', 'VAT decision saved successfully.');
    }

    /**
     * Bulk apply VAT decision to multiple selected rows
     */
    public function bulkDecision(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasAnyRole(['global_admin', 'admin', 'finance_head', 'finance']) && !$user->can('finance.approve')) {
            abort(403, 'Unauthorized for bulk VAT decisions.');
        }

        $validated = $request->validate([
            'selected_items'     => 'required|array|min:1',
            'selected_items.*'   => 'required|string', // Format: "source_type:source_id:amount"
            'vat_status'         => 'required|in:vat_applies,no_vat,pending_decision',
            'vat_rate'           => 'nullable|numeric|min:0|max:100',
            'is_inclusive'       => 'nullable|boolean',
            'no_vat_reason_code' => 'nullable|string|max:50',
            'no_vat_reason_note' => 'nullable|string|max:500',
        ]);

        $vatStatus   = $validated['vat_status'];
        $vatRate     = isset($validated['vat_rate']) ? (float)$validated['vat_rate'] : 15.00;
        $isInclusive = $request->boolean('is_inclusive', true);
        $count = 0;

        DB::transaction(function () use ($validated, $vatStatus, $vatRate, $isInclusive, $user, &$count) {
            foreach ($validated['selected_items'] as $itemStr) {
                $parts = explode(':', $itemStr);
                if (count($parts) < 3) continue;

                $sourceType = $parts[0];
                $sourceId   = (int) $parts[1];
                $amount     = (float) $parts[2];

                $existing = ExpenseHubVatDecision::where('source_type', $sourceType)
                    ->where('source_id', $sourceId)
                    ->first();

                // Skip locked records if not finance head
                if ($existing && $existing->is_locked && !$user->hasAnyRole(['global_admin', 'admin', 'finance_head'])) {
                    continue;
                }

                if ($vatStatus === ExpenseHubVatDecision::STATUS_VAT_APPLIES) {
                    $calc = ExpenseHubVatDecision::computeVatAmounts($amount, $vatRate, $isInclusive);
                    $baseAmount  = $calc['base'];
                    $vatAmount   = $calc['vat'];
                    $totalAmount = $calc['total'];
                } else {
                    $baseAmount  = $amount;
                    $vatAmount   = 0.00;
                    $totalAmount = $amount;
                }

                $decisionData = [
                    'vat_status'         => $vatStatus,
                    'vat_rate'           => $vatRate,
                    'is_inclusive'       => $isInclusive,
                    'base_amount'        => $baseAmount,
                    'vat_amount'         => $vatAmount,
                    'total_amount'       => $totalAmount,
                    'no_vat_reason_code' => $vatStatus === 'no_vat' ? ($validated['no_vat_reason_code'] ?? null) : null,
                    'no_vat_reason_note' => $vatStatus === 'no_vat' ? ($validated['no_vat_reason_note'] ?? null) : null,
                    'decided_by'         => $user->id,
                    'decided_at'         => now(),
                ];

                $decision = ExpenseHubVatDecision::updateOrCreate(
                    [
                        'source_type' => $sourceType,
                        'source_id'   => $sourceId,
                    ],
                    $decisionData
                );

                ExpenseHubVatAuditLog::create([
                    'decision_id'  => $decision->id,
                    'source_type'  => $sourceType,
                    'source_id'    => $sourceId,
                    'action'       => 'bulk_updated',
                    'old_values'   => $existing ? $existing->only(['vat_status', 'base_amount', 'vat_amount']) : null,
                    'new_values'   => $decisionData,
                    'performed_by' => $user->id,
                ]);

                $count++;
            }
        });

        return back()->with('success', "Bulk VAT decision successfully applied to {$count} expense records.");
    }

    /**
     * Export All Expenses Hub table to CSV / Excel
     */
    public function exportHubExcel(Request $request)
    {
        $filters = $request->only(['date_from', 'date_to', 'project_id', 'source_module', 'vat_status', 'category', 'supplier', 'search']);
        $expenses = $this->queryService->getExpenses($filters, Auth::user());

        $filename = 'Expense_Hub_Export_' . now()->format('Ymd_His') . '.csv';

        $response = new StreamedResponse(function () use ($expenses) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

            fputcsv($handle, [
                'Disbursement Date',
                'Source Module',
                'Reference / Source ID',
                'Project / Site',
                'Supplier / Payee',
                'Vendor TIN',
                'Gross Amount (ETB)',
                'VAT Status',
                'Base Subtotal (ETB)',
                'VAT Amount (15%)',
                'Total Amount (ETB)',
                'No-VAT Reason',
                'Payment Method',
                'Paid By',
                'Warnings',
                'Description',
            ]);

            foreach ($expenses as $item) {
                fputcsv($handle, [
                    $item->disbursement_date ?: 'N/A',
                    $item->source_module_label,
                    $item->reference_no,
                    $item->project_name,
                    $item->payee_supplier,
                    $item->tin ?: 'N/A',
                    number_format($item->amount, 2, '.', ''),
                    ucwords(str_replace('_', ' ', $item->vat_status)),
                    number_format($item->decision_base_amount, 2, '.', ''),
                    number_format($item->decision_vat_amount, 2, '.', ''),
                    number_format($item->decision_total_amount, 2, '.', ''),
                    $item->reason_code ? (ExpenseHubVatDecision::NO_VAT_REASONS[$item->reason_code] ?? $item->reason_code) : ($item->reason_note ?: 'N/A'),
                    ucwords(str_replace('_', ' ', $item->payment_method ?? 'Cash')),
                    $item->paid_by_name,
                    !empty($item->warnings) ? implode('; ', $item->warnings) : 'None',
                    $item->description,
                ]);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', "attachment; filename=\"{$filename}\"");

        return $response;
    }

    /**
     * Export Official 15-Column Ministry of Revenue / ERCA Purchase VAT Report
     * Uses the exact columns from FinanceTaxReportController::exportPurchaseVatExcel()
     */
    public function exportVatExcel(Request $request)
    {
        $filters = $request->only(['date_from', 'date_to', 'project_id', 'source_module', 'category', 'supplier', 'search']);
        $filters['vat_status'] = ExpenseHubVatDecision::STATUS_VAT_APPLIES;

        $expenses = $this->queryService->getExpenses($filters, Auth::user());

        $filename = 'Purchase_VAT_Declaration_' . now()->format('Ymd_His') . '.csv';

        $response = new StreamedResponse(function () use ($expenses) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

            // Official 15 ERA Columns matching FinanceTaxReportController.php
            fputcsv($handle, [
                "VAT CATEGORY (G=GOODS;S=SERVICES)",
                "CALENDAR TYPE (E=ETHIOPIAN;G=GREGORIAN)",
                "Types of purchase (1-6)",
                "TIN",
                "Seller name",
                "Date of purchase",
                "MRC Number",
                "Vat receipt number / FS No",
                "Description / Item",
                "Unit of Measure",
                "Quantity",
                "Unit Price",
                "Total value",
                "VAT (15%)",
                "Value after vat",
            ]);

            foreach ($expenses as $item) {
                $rawTin = (string) ($item->tin ?? '');
                $tinDigits = preg_replace('/[^0-9]/', '', $rawTin);
                $tinText = (strlen($tinDigits) === 10) ? $tinDigits : $rawTin;

                // Format Ethiopian Date
                $ethDate = $item->disbursement_date
                    ? EthiopianDate::formatDateEc($item->disbursement_date, 'DD/MM/YYYY EC')
                    : EthiopianDate::formatDateEc(now(), 'DD/MM/YYYY EC');

                $baseVal = (float) $item->decision_base_amount;
                $vatVal  = (float) $item->decision_vat_amount;
                $totVal  = (float) $item->decision_total_amount;
                $qty     = (float) ($item->qty > 0 ? $item->qty : 1);
                $unitVal = round($baseVal / $qty, 2);

                fputcsv($handle, [
                    $item->vat_category ?: 'G',
                    $item->calendar_type ?: 'E',
                    $item->purchase_type ?: 3,
                    $tinText ?: 'N/A',
                    $item->payee_supplier,
                    $ethDate,
                    $item->mrc_no ?: '',
                    $item->fs_no ?: '',
                    $item->item_desc ?: $item->description,
                    $item->uom ?: '9',
                    number_format($qty, 2, '.', ''),
                    number_format($unitVal, 2, '.', ''),
                    number_format($baseVal, 2, '.', ''),
                    number_format($vatVal, 2, '.', ''),
                    number_format($totVal, 2, '.', ''),
                ]);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', "attachment; filename=\"{$filename}\"");

        return $response;
    }

    /**
     * Lock Month (Finance Head only)
     */
    public function lockMonth(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasAnyRole(['global_admin', 'admin', 'finance_head'])) {
            abort(403, 'Only Finance Head or Admin can lock a month.');
        }

        $validated = $request->validate([
            'year'     => 'required|integer',
            'month'    => 'required|integer|between:1,13',
            'calendar' => 'nullable|string|in:EC,GC',
        ]);

        $calendar = $validated['calendar'] ?? 'GC';

        ExpenseHubMonthLock::updateOrCreate(
            [
                'year'     => $validated['year'],
                'month'    => $validated['month'],
                'calendar' => $calendar,
            ],
            [
                'is_locked' => true,
                'locked_by' => $user->id,
                'locked_at' => now(),
            ]
        );

        return back()->with('success', "Month {$validated['month']}/{$validated['year']} ({$calendar}) has been locked for VAT filings.");
    }

    /**
     * Unlock Month (Finance Head only)
     */
    public function unlockMonth(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasAnyRole(['global_admin', 'admin', 'finance_head'])) {
            abort(403, 'Only Finance Head or Admin can unlock a month.');
        }

        $validated = $request->validate([
            'year'          => 'required|integer',
            'month'         => 'required|integer|between:1,13',
            'calendar'      => 'nullable|string|in:EC,GC',
            'unlock_reason' => 'required|string|max:500',
        ]);

        $calendar = $validated['calendar'] ?? 'GC';

        $lock = ExpenseHubMonthLock::where('year', $validated['year'])
            ->where('month', $validated['month'])
            ->where('calendar', $calendar)
            ->first();

        if ($lock) {
            $lock->update([
                'is_locked'     => false,
                'unlocked_by'   => $user->id,
                'unlocked_at'   => now(),
                'unlock_reason' => $validated['unlock_reason'],
            ]);
        }

        return back()->with('success', "Month {$validated['month']}/{$validated['year']} ({$calendar}) has been unlocked.");
    }
}
