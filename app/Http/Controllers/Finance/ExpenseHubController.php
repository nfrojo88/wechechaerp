<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\ExpenseHubVatDecision;
use App\Models\ExpenseHubVatAuditLog;
use App\Models\ExpenseHubMonthLock;
use App\Models\Project;
use App\Models\User;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\ExpenseRequest;
use App\Models\ProcurementPayment;
use App\Models\CreditStorePayment;
use App\Models\PettyCashMaterialPurchase;
use Illuminate\Support\Facades\Schema;
use App\Services\ExpenseHubQueryService;
use App\Services\EthiopianDate;
use App\Services\ReceiptOcrService;
use App\Services\QcService;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\SystemSetting;
use Symfony\Component\HttpFoundation\StreamedResponse;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Cell;

class ExpenseHubController extends Controller
{
    protected ExpenseHubQueryService $queryService;

    public function __construct(ExpenseHubQueryService $queryService)
    {
        $this->middleware('auth');
        $this->queryService = $queryService;
    }

    /**
     * Runtime self-healing database schema verification.
     */
    public function ensureSchema(): void
    {
        try {
            if (Schema::hasTable('expense_hub_vat_decisions')) {
                Schema::table('expense_hub_vat_decisions', function ($table) {
                    if (!Schema::hasColumn('expense_hub_vat_decisions', 'has_withholding')) {
                        $table->boolean('has_withholding')->default(false)->after('vat_status')->index();
                    }
                    if (!Schema::hasColumn('expense_hub_vat_decisions', 'withholding_rate')) {
                        $table->decimal('withholding_rate', 5, 2)->default(3.00)->after('has_withholding');
                    }
                    if (!Schema::hasColumn('expense_hub_vat_decisions', 'withholding_amount')) {
                        $table->decimal('withholding_amount', 18, 2)->default(0.00)->after('withholding_rate');
                    }
                    if (!Schema::hasColumn('expense_hub_vat_decisions', 'withholding_receipt')) {
                        $table->string('withholding_receipt', 500)->nullable()->after('withholding_amount');
                    }
                    if (!Schema::hasColumn('expense_hub_vat_decisions', 'withholding_receipt_number')) {
                        $table->string('withholding_receipt_number', 100)->nullable()->after('withholding_receipt');
                    }
                    if (!Schema::hasColumn('expense_hub_vat_decisions', 'net_payable_amount')) {
                        $table->decimal('net_payable_amount', 18, 2)->nullable()->after('withholding_receipt_number');
                    }
                    if (!Schema::hasColumn('expense_hub_vat_decisions', 'custom_receipt_date')) {
                        $table->string('custom_receipt_date', 30)->nullable()->after('custom_item_description');
                    }
                    if (!Schema::hasColumn('expense_hub_vat_decisions', 'custom_seller_name')) {
                        $table->string('custom_seller_name', 255)->nullable()->after('purchase_type');
                    }
                    if (!Schema::hasColumn('expense_hub_vat_decisions', 'purc_type')) {
                        $table->string('purc_type', 20)->default('PUR_GD')->after('vat_category');
                    }
                    if (!Schema::hasColumn('expense_hub_vat_decisions', 'receipt_path')) {
                        $table->string('receipt_path', 500)->nullable()->after('custom_fs_no');
                    }
                    if (!Schema::hasColumn('expense_hub_vat_decisions', 'is_verified_without_receipt')) {
                        $table->boolean('is_verified_without_receipt')->default(false)->after('no_vat_reason_note')->index();
                    }
                });
            }

            \App\Http\Controllers\VehicleFuelRequestController::purgeTargetFuelRequest('EXP-FUEL-2026-0273');
        } catch (\Throwable $e) {}
    }

    /**
     * Display the Expense Hub Dashboard
     */
    public function index(Request $request)
    {
        $this->ensureSchema();
        $user = Auth::user();

        // 1. Parse active tab & filters
        $activeTab = $request->input('tab', 'all_expenses'); // all_expenses, vat_report, vat_summary

        $filters = [
            'date_from'     => $request->input('date_from'),
            'date_to'       => $request->input('date_to'),
            'project_id'    => $request->input('project_id'),
            'source_module' => $request->input('source_module'),
            'vat_status'    => $request->input('vat_status'),
            'withholding'   => $request->input('withholding'),
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
            $savedRelativePath = null;
            $stripped = null;

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

            // Determine if receipt is a 0% VAT / Fuel / Tax-Exempt TAS purchase
            $descLower = strtolower(($scan['description'] ?? '') . ' ' . $vendorName);
            $isFuelOrExempt = str_contains($descLower, 'fuel')
                || str_contains($descLower, 'diesel')
                || str_contains($descLower, 'benzin')
                || str_contains($descLower, 'oil')
                || str_contains($descLower, 'petroleum')
                || str_contains($descLower, 'totalenergies')
                || str_contains($descLower, 'oillibya')
                || str_contains($descLower, 'nogp')
                || str_contains($descLower, 'station')
                || ($scan['is_non_vat'] ?? false)
                || (($scan['vat_category'] ?? '') === 'E');

            // Cross-calculate 15% VAT only if taxable VAT slip, not if 0% VAT / TAS purchase
            if ($isFuelOrExempt || ($vatAmount == 0 && ($scan['is_non_vat'] ?? false))) {
                $vatAmount = 0.00;
                $subtotal  = $totalAmount > 0 ? $totalAmount : $subtotal;
                $totalAmount = $subtotal;
            } elseif ($totalAmount > 0 && $vatAmount == 0 && $subtotal == 0) {
                // If not explicitly exempt, standard VAT calculation
                $subtotal  = round($totalAmount / 1.15, 2);
                $vatAmount = round($totalAmount - $subtotal, 2);
            } elseif ($subtotal > 0 && $vatAmount == 0 && $totalAmount > $subtotal) {
                $vatAmount = round($totalAmount - $subtotal, 2);
            } elseif ($totalAmount == 0 && $subtotal > 0 && $vatAmount > 0) {
                $totalAmount = round($subtotal + $vatAmount, 2);
            }

            // PurcType: PUR_GD (Goods/Fuel) vs PUR_SER (Services) for pro.doc e-Tax export
            $rawPurcType = $scan['purchase_type'] ?? '';
            $purcType = ($rawPurcType === 'PUR_SR' || $rawPurcType === 'PUR_SER') ? 'PUR_SER' : 'PUR_GD';

            // Purchase Type: 1 = Capital, 2 = Goods Line 70, 3 = Services Line 75, 6 = Exempt
            $purchaseType = $scan['purchase_type_int'] ?? ($vatAmount == 0 ? 6 : 3);
            if ($rawPurcType === 'PUR_SR' && $vatAmount > 0) {
                $purchaseType = 3;
            } elseif ($vatAmount == 0) {
                $purchaseType = 6;
            }

            $isNoVat = ($vatAmount <= 0.00);

            // Verified if TIN, FS No, or total amount is extracted
            $isVerified = (!empty($fsNo) || !empty($supplierTin) || $totalAmount > 0);

            $receiptDateYmd = $scan['receipt_date_ymd'] ?? now()->toDateString();
            $receiptDateEc  = EthiopianDate::formatDateEc($receiptDateYmd, 'd-m-Y');

            return response()->json([
                'success'           => true,
                'is_verified'       => $isVerified,
                'is_no_vat'         => $isNoVat,
                'purc_type'         => $purcType,
                'file_url'          => $fileUrl,
                'file_path'         => $savedRelativePath ?: ($stripped ?: null),
                'vendor_name'       => $vendorName,
                'supplier_tin'      => $supplierTin,
                'tin_valid'         => $tinNorm['is_valid'] ?? (!empty($supplierTin) && strlen($supplierTin) === 10),
                'fs_no'             => $fsNo,
                'fs_no_valid'       => $fsNorm['is_valid'] ?? !empty($fsNo),
                'mrc_no'            => $mrcNo,
                'receipt_date'      => $receiptDateYmd,
                'receipt_date_ec'   => $receiptDateEc,
                'subtotal'          => $subtotal,
                'vat_amount'        => $vatAmount,
                'total_amount'      => $totalAmount,
                'vat_rate'          => $vatAmount > 0 ? 15.00 : 0.00,
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
        $this->ensureSchema();
        $user = Auth::user();
        if (!$user->hasAnyRole(['global_admin', 'admin', 'finance_head', 'finance', 'auditor']) && !$user->can('finance.approve')) {
            abort(403, 'Unauthorized to record VAT decisions.');
        }

        $validated = $request->validate([
            'source_type'                => 'required|string|max:60',
            'source_id'                  => 'required|integer',
            'vat_status'                 => 'required|in:vat_applies,no_vat,pending_decision,verified_no_receipt',
            'amount'                     => 'required|numeric|min:0',
            'vat_rate'                   => 'nullable|numeric|min:0|max:100',
            'is_inclusive'               => 'nullable|boolean',
            'has_withholding'            => 'nullable|boolean',
            'withholding_rate'           => 'nullable|numeric|min:0|max:100',
            'withholding_amount'         => 'nullable|numeric|min:0',
            'withholding_receipt'        => 'nullable|string|max:500',
            'withholding_receipt_number' => 'nullable|string|max:100',
            'withholding_receipt_file'   => 'nullable|file|mimes:jpeg,jpg,png,webp,pdf|max:25600',
            'receipt_file'               => 'nullable|file|mimes:jpeg,jpg,png,webp,pdf|max:25600',
            'receipt_path'               => 'nullable|string|max:500',
            'is_verified_without_receipt'=> 'nullable|boolean',
            'no_vat_reason_code'         => 'nullable|string|max:50',
            'no_vat_reason_note'         => 'nullable|string|max:500',
            'vat_category'               => 'nullable|in:G,S',
            'purc_type'                  => 'nullable|string|in:PUR_GD,PUR_SER',
            'purchase_type'              => 'nullable|integer|between:1,6',
            'custom_seller_tin'          => 'nullable|string|max:30',
            'custom_fs_no'               => 'nullable|string|max:50',
            'custom_mrc_no'              => 'nullable|string|max:50',
            'custom_item_description'    => 'nullable|string|max:255',
            'custom_receipt_date'        => 'nullable|string|max:30',
            'custom_seller_name'         => 'nullable|string|max:255',
            'uom'                        => 'nullable|string|max:20',
            'quantity'                   => 'nullable|numeric|min:0.01',
            'unit_price'                 => 'nullable|numeric|min:0',
        ]);

        $vatStatus = $validated['vat_status'];
        $amount    = (float) $validated['amount'];
        $vatRate   = isset($validated['vat_rate']) ? (float)$validated['vat_rate'] : 15.00;
        $isInclusive = $request->boolean('is_inclusive', true);

        // Normalize "Verified without receipt"
        $isVerifiedWithoutReceipt = $request->boolean('is_verified_without_receipt')
            || $vatStatus === 'verified_no_receipt'
            || in_array($validated['no_vat_reason_code'] ?? '', ['no_fiscal_receipt', 'informal_vendor'])
            || str_contains(strtolower($validated['no_vat_reason_note'] ?? ''), 'without receipt');

        if ($vatStatus === 'verified_no_receipt') {
            $vatStatus = ExpenseHubVatDecision::STATUS_NO_VAT;
        }

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

        // Handle direct Receipt upload
        $receiptPath = $request->input('receipt_path') ?: ($existing?->receipt_path ?: null);
        if ($request->hasFile('receipt_file')) {
            $receiptPath = FileUploadService::upload(
                $request->file('receipt_file'),
                'receipts'
            );
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

        // Compute Withholding Tax (Standard 3% in Ethiopia calculated on Base/Subtotal)
        $hasWithholding = $request->boolean('has_withholding') || !empty($withholdingReceiptPath) || !empty($request->input('withholding_receipt_number'));
        $withholdingRate = $hasWithholding ? (float)($request->input('withholding_rate') ?: 3.00) : 0.00;
        if ($hasWithholding) {
            if ($request->filled('withholding_amount')) {
                $withholdingAmount = (float)$request->input('withholding_amount');
            } else {
                $withholdingAmount = round($baseAmount * ($withholdingRate / 100), 2);
            }
        } else {
            $withholdingAmount = 0.00;
        }
        $netPayableAmount = max(0, $totalAmount - $withholdingAmount);
        $withholdingReceiptNo = $hasWithholding ? $request->input('withholding_receipt_number') : null;

        $decisionData = [
            'vat_status'                 => $vatStatus,
            'vat_rate'                   => $vatRate,
            'is_inclusive'               => $isInclusive,
            'has_withholding'            => $hasWithholding,
            'withholding_rate'           => $withholdingRate,
            'withholding_amount'         => $withholdingAmount,
            'withholding_receipt'        => $withholdingReceiptPath,
            'withholding_receipt_number' => $withholdingReceiptNo,
            'net_payable_amount'         => $netPayableAmount,
            'base_amount'                => $baseAmount,
            'vat_amount'                 => $vatAmount,
            'total_amount'               => $totalAmount,
            'is_verified_without_receipt'=> $isVerifiedWithoutReceipt,
            'receipt_path'               => $receiptPath,
            'no_vat_reason_code'         => $vatStatus === 'no_vat' ? ($validated['no_vat_reason_code'] ?? ($isVerifiedWithoutReceipt ? 'no_fiscal_receipt' : null)) : null,
            'no_vat_reason_note'         => $vatStatus === 'no_vat' ? ($validated['no_vat_reason_note'] ?? ($isVerifiedWithoutReceipt ? 'Audited and verified without receipt (Approved operational expense)' : null)) : null,
            'vat_category'               => $validated['vat_category'] ?? 'G',
            'purc_type'                  => $validated['purc_type'] ?? (($validated['vat_category'] ?? 'G') === 'S' ? 'PUR_SER' : 'PUR_GD'),
            'calendar_type'              => 'E',
            'purchase_type'              => $validated['purchase_type'] ?? ($vatStatus === 'no_vat' ? 6 : 3),
            'custom_seller_name'         => $validated['custom_seller_name'] ?? null,
            'custom_seller_tin'          => $validated['custom_seller_tin'] ?? null,
            'custom_fs_no'               => $validated['custom_fs_no'] ?? null,
            'custom_mrc_no'              => $validated['custom_mrc_no'] ?? null,
            'custom_item_description'    => $validated['custom_item_description'] ?? null,
            'custom_receipt_date'        => $validated['custom_receipt_date'] ?? null,
            'uom'                        => $validated['uom'] ?? '9',
            'quantity'                   => $validated['quantity'] ?? 1.0,
            'unit_price'                 => $validated['unit_price'] ?? ($baseAmount / max((float)($validated['quantity'] ?? 1), 1)),
            'decided_by'                 => $user->id,
            'decided_at'                 => now(),
        ];

        $decision = null;
        DB::transaction(function () use ($validated, $decisionData, $existing, $user, &$decision) {
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

        if ($decision) {
            self::syncVatDecisionToLedger(
                $decision,
                $receiptPath ?: $request->input('receipt_path'),
                $request->input('custom_receipt_date'),
                $request->input('custom_seller_name'),
                $request->input('custom_item_description'),
                $request->input('purc_type')
            );
        }

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
            'vat_status'         => 'required|in:vat_applies,no_vat,pending_decision,verified_no_receipt',
            'vat_rate'           => 'nullable|numeric|min:0|max:100',
            'is_inclusive'       => 'nullable|boolean',
            'has_withholding'    => 'nullable|boolean',
            'withholding_rate'   => 'nullable|numeric|min:0|max:100',
            'no_vat_reason_code' => 'nullable|string|max:50',
            'no_vat_reason_note' => 'nullable|string|max:500',
        ]);

        $rawVatStatus = $validated['vat_status'];
        $isVerifiedWithoutReceipt = ($rawVatStatus === 'verified_no_receipt')
            || $request->boolean('is_verified_without_receipt')
            || in_array($validated['no_vat_reason_code'] ?? '', ['no_fiscal_receipt', 'informal_vendor']);
        $vatStatus   = ($rawVatStatus === 'verified_no_receipt') ? ExpenseHubVatDecision::STATUS_NO_VAT : $rawVatStatus;
        $vatRate     = isset($validated['vat_rate']) ? (float)$validated['vat_rate'] : 15.00;
        $isInclusive = $request->boolean('is_inclusive', true);
        $count = 0;

        DB::transaction(function () use ($validated, $vatStatus, $isVerifiedWithoutReceipt, $vatRate, $isInclusive, $user, $request, &$count) {
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
                    'vat_status'                  => $vatStatus,
                    'vat_rate'                    => $vatRate,
                    'is_inclusive'                => $isInclusive,
                    'is_verified_without_receipt' => $isVerifiedWithoutReceipt,
                    'base_amount'                 => $baseAmount,
                    'vat_amount'                  => $vatAmount,
                    'total_amount'                => $totalAmount,
                    'no_vat_reason_code'          => $vatStatus === 'no_vat' ? ($validated['no_vat_reason_code'] ?? ($isVerifiedWithoutReceipt ? 'no_fiscal_receipt' : null)) : null,
                    'no_vat_reason_note'          => $vatStatus === 'no_vat' ? ($validated['no_vat_reason_note'] ?? ($isVerifiedWithoutReceipt ? 'Audited and verified without receipt' : null)) : null,
                    'decided_by'                  => $user->id,
                    'decided_at'                  => now(),
                ];

                if ($request->has('has_withholding')) {
                    $hasWht = $request->boolean('has_withholding');
                    $whtRate = (float)($request->input('withholding_rate') ?: 3.00);
                    $whtAmt = $hasWht ? round($baseAmount * ($whtRate / 100), 2) : 0.00;
                    $decisionData['has_withholding']    = $hasWht;
                    $decisionData['withholding_rate']   = $whtRate;
                    $decisionData['withholding_amount'] = $whtAmt;
                    $decisionData['net_payable_amount'] = max(0, $totalAmount - $whtAmt);
                }

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

                self::syncVatDecisionToLedger($decision);

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

            // Official 15 ERA Columns matching pro.doc
            fputcsv($handle, [
                'VAT CATEGORY  (G=GOODS;S=SERVICES)',
                'CALENDAR TYPE (E=ETHIOPIAN;G=GREGORIAN)',
                'Types of purchase. 1 = Taxable-local Purchase of Capital Assets (Line No. 65) 2 = Taxable-imported Purchase of Capital Assets (Line No. 75) 3 = Taxable-local Purchase of Inputs (Line No. 100) 4 = Taxable-imported Purchase of Inputs (Line No. 110) 5 = Taxable-general Expense Inputs Purchase (Line No. 120) 6= Tax Exempted-purchase with no vat or uncollectible inputs (Line no. 85 or Line no. 130)    (Please type 1 or 2 or 3 or 4 or 5 or 6).This field is mandatory.',
                'TIN..This field  is not mandatory.',
                'Seller name (if Seller has no TIN or item is not locally purchased) This field is not mandatory.',
                'Date of purchase/Customs Declaration No.  Dispatched Date (Please use  dd/mm/yyyy date format).  This field is mandatory.',
                'MRC Number..This field is not mandatory.',
                'Vat receipt number/ Customs Declaration Number.This field is mandatory.',
                'Description.This field is mandatory.',
                'Unit of Measure (type ID 2-10). 2 KG 3 ML 4 GM 5 LIT 6 MT 7 PCS 8 CT 9 OTHER 10 PC This field is mandatory.',
                'Quantity. Enter number.Don\'t use comma (,) or Quatation ("") This field is  mandatory.',
                'Unit Price. Enter number only .Don\'t use comma (,) or Quatation ("") This field is  mandatory.',
                'Total value',
                'vat',
                'value after vat',
            ]);

            foreach ($expenses as $item) {
                $rawTin = (string) ($item->tin ?? '');
                $tinDigits = preg_replace('/[^0-9]/', '', $rawTin);
                $tinDigits = preg_replace('/^0+/', '', $tinDigits);
                $tinVal = ($tinDigits !== '') ? (int)$tinDigits : '';

                $calType = strtoupper(trim((string)($item->calendar_type ?: 'G')));
                $calendarType = ($calType === 'E') ? 'E' : 'G';

                $date = $item->disbursement_date ?: $item->created_at;
                if ($calendarType === 'E') {
                    $dateFormatted = $date ? EthiopianDate::formatDateEc($date, 'd/m/Y') : EthiopianDate::formatDateEc(now(), 'd/m/Y');
                } else {
                    $dateFormatted = $date ? Carbon::parse($date)->format('d/m/Y') : now()->format('d/m/Y');
                }

                $baseVal = (float) $item->decision_base_amount;
                $vatVal  = (float) $item->decision_vat_amount;
                $totVal  = (float) $item->decision_total_amount;
                $qty     = (float) ($item->qty > 0 ? $item->qty : 1);
                $unitVal = round($baseVal / $qty, 2);

                if (abs(($baseVal + $vatVal) - $totVal) > 0.05) {
                    $totVal = round($baseVal + $vatVal, 2);
                }

                $uomRaw = strtoupper(trim((string)($item->uom ?? '')));
                if (is_numeric($uomRaw) && (int)$uomRaw >= 2 && (int)$uomRaw <= 10) {
                    $uomId = (int)$uomRaw;
                } else {
                    $uomId = match (true) {
                        str_contains($uomRaw, 'KG') || str_contains($uomRaw, 'KILO') => 2,
                        str_contains($uomRaw, 'ML') => 3,
                        str_contains($uomRaw, 'GM') || str_contains($uomRaw, 'GRAM') => 4,
                        str_contains($uomRaw, 'LIT') || str_contains($uomRaw, 'LTR') || $uomRaw === 'L' => 5,
                        str_contains($uomRaw, 'MT') || str_contains($uomRaw, 'TON') => 6,
                        str_contains($uomRaw, 'PCS') || str_contains($uomRaw, 'PIECE') || str_contains($uomRaw, 'PCS.') => 7,
                        str_contains($uomRaw, 'CT') || str_contains($uomRaw, 'CARTON') || str_contains($uomRaw, 'BOX') => 8,
                        $uomRaw === 'PC' => 10,
                        default => 9,
                    };
                }

                $pType = (int)($item->purchase_type ?: 3);
                if ($pType < 1 || $pType > 6) $pType = 3;
                if ($vatVal <= 0 && $pType === 3) $pType = 6;

                $rawMrc = trim((string)($item->mrc_no ?? ''));
                $mrcVal = ($rawMrc !== '' && $rawMrc !== '-' && strtoupper($rawMrc) !== 'N/A') ? $rawMrc : '';

                $rawFs = trim((string)($item->fs_no ?? ''));

                fputcsv($handle, [
                    $item->vat_category ?: 'G',
                    $calendarType,
                    $pType,
                    $tinVal,
                    trim((string)($item->payee_supplier ?? '')),
                    $dateFormatted,
                    $mrcVal,
                    $rawFs,
                    trim((string)($item->item_desc ?: ($item->description ?? 'Purchased Goods'))),
                    $uomId,
                    (floor($qty) == $qty) ? (int)$qty : round($qty, 4),
                    round($unitVal, 2),
                    round($baseVal, 2),
                    round($vatVal, 2),
                    round($totVal, 2),
                ]);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', "attachment; filename=\"{$filename}\"");

        return $response;
    }

    /**
     * Export Purchase TAS Excel (9 Columns e-Tax format matching pro.doc).
     * Purchaser TIN | Seller TIN | Amount | Receipt No | Receipt Date | Encoding Date | MRC | PurcType | Encoded By
     */
    public function exportTasExcel(Request $request)
    {
        $filters = $request->only(['date_from', 'date_to', 'project_id', 'source_module', 'category', 'supplier', 'search']);
        $filters['vat_status'] = ExpenseHubVatDecision::STATUS_NO_VAT;

        $expenses = $this->queryService->getExpenses($filters, Auth::user());

        $defaultPurchaserTin = SystemSetting::get('purchaser_tin', SystemSetting::get('company_tin', '38480010'));
        $defaultPurchaserDigits = (int)preg_replace('/^0+/', '', preg_replace('/[^0-9]/', '', (string)$defaultPurchaserTin));
        if (!$defaultPurchaserDigits) $defaultPurchaserDigits = 38480010;

        $filename = 'Purchase_TAS_' . now()->format('Ymd_His') . '.xlsx';
        $tempPath = storage_path('app/hub_purch_tas_' . uniqid() . '.xlsx');

        $headers = [
            'Purchaser TIN',
            'Seller TIN',
            'Amount',
            'Receipt No',
            'Receipt Date',
            'Encoding Date',
            'MRC',
            'PurcType',
            'Encoded By',
        ];

        $rows = [];
        foreach ($expenses as $item) {
            $rawTin = (string)($item->tin ?? '');
            $sellerDigits = preg_replace('/^0+/', '', preg_replace('/[^0-9]/', '', $rawTin));
            $sellerTinVal = ($sellerDigits !== '') ? (int)$sellerDigits : (trim($rawTin) ?: '');

            $amountVal = round((float)($item->decision_total_amount ?: $item->amount), 2);

            $rawFs = trim((string)($item->fs_no ?? ''));
            $receiptNo = $rawFs ?: ($item->reference_no ?: '');

            $date = $item->disbursement_date ?: $item->created_at;
            $receiptDate = $date ? EthiopianDate::formatDateEc($date, 'd-m-Y') : EthiopianDate::formatDateEc(now(), 'd-m-Y');
            $encodingDate = EthiopianDate::formatDateEc(now(), 'd-m-Y');

            $rawMrc = trim((string)($item->mrc_no ?? ''));
            $mrcVal = ($rawMrc !== '' && $rawMrc !== '-' && strtoupper($rawMrc) !== 'N/A') ? $rawMrc : '';

            $purcType = $item->purc_type ?: ($item->vat_category === 'S' ? 'PUR_SER' : 'PUR_GD');

            $encodedBy = 'wechacha';

            $rows[] = [
                $defaultPurchaserDigits,
                $sellerTinVal,
                $amountVal,
                $receiptNo,
                $receiptDate,
                $encodingDate,
                $mrcVal,
                $purcType,
                $encodedBy,
            ];
        }

        return $this->writeExcelFile($headers, $rows, $tempPath, $filename);
    }

    /**
     * Helper to write an XLSX spreadsheet with automatic CSV streaming fallback.
     */
    protected function writeExcelFile(array $headers, array $rows, string $tempPath, string $filename)
    {
        try {
            $writer = new XlsxWriter();
            $writer->openToFile($tempPath);

            $headerCells = array_map(fn($h) => Cell::fromValue($h), $headers);
            $writer->addRow(new Row($headerCells));

            foreach ($rows as $row) {
                $rowCells = array_map(fn($v) => Cell::fromValue($v), $row);
                $writer->addRow(new Row($rowCells));
            }

            $writer->close();

            return response()->download($tempPath, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);

        } catch (\Throwable $e) {
            Log::error('ExpenseHubController Excel export error: ' . $e->getMessage());

            // CSV Fallback with UTF-8 BOM
            $csvHeaders = [
                'Content-Type'        => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . str_replace('.xlsx', '.csv', $filename) . '"',
                'Pragma'              => 'no-cache',
                'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
                'Expires'             => '0',
            ];

            return response()->stream(function () use ($headers, $rows) {
                $handle = fopen('php://output', 'w');
                fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
                fputcsv($handle, $headers);
                foreach ($rows as $row) {
                    fputcsv($handle, $row);
                }
                fclose($handle);
            }, 200, $csvHeaders);
        }
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

    /**
     * Synchronize a VAT Decision to the ERCA Line 100 Receipt tables and source model
     * so it immediately reflects in the VAT & Withholding Tax Deductions Ledger.
     */
    public static function syncVatDecisionToLedger(
        ExpenseHubVatDecision $decision,
        ?string $receiptPath = null,
        ?string $customReceiptDate = null,
        ?string $customSellerName = null,
        ?string $customDescription = null,
        ?string $purcType = null
    ): void {
        try {
            $sourceType   = $decision->source_type;
            $sourceId     = (int)$decision->source_id;
            $vatStatus    = $decision->vat_status;
            $vatAmount    = (float)$decision->vat_amount;
            $baseAmount   = (float)$decision->base_amount;
            $totalAmount  = (float)$decision->total_amount;
            $isInclusive  = (bool)$decision->is_inclusive;
            $vatRate      = (float)($decision->vat_rate > 0 ? $decision->vat_rate : 15.00);

            $purchasableClass = match($sourceType) {
                'expense_request'       => ExpenseRequest::class,
                'procurement_payment'   => ProcurementPayment::class,
                'credit_store_payment'  => CreditStorePayment::class,
                'petty_cash_purchase'   => PettyCashMaterialPurchase::class,
                default                 => null,
            };

            $hasWht       = (bool)$decision->has_withholding;
            $whtRate      = (float)($decision->withholding_rate ?: 3.00);
            $whtAmount    = (float)($decision->withholding_amount ?: 0.00);
            $whtReceipt   = $decision->withholding_receipt ?: null;
            $whtReceiptNo = $decision->withholding_receipt_number ?: null;

            if ($vatStatus === ExpenseHubVatDecision::STATUS_VAT_APPLIES) {
                $targetDate  = $customReceiptDate;
                $projectId   = null;
                $vendorName  = $customSellerName;
                $itemDesc    = $customDescription ?: $decision->custom_item_description;

                // 1. Update underlying source model
                if ($sourceType === 'expense_request') {
                    $req = ExpenseRequest::with(['user', 'receipt'])->find($sourceId);
                    if ($req) {
                        $req->vat_amount = $vatAmount;
                        $req->vat_type   = $isInclusive ? 'inclusive' : 'exclusive';
                        $req->vat_rate   = $vatRate;
                        if ($totalAmount > 0) {
                            $req->gross_amount = $totalAmount;
                        }
                        if (!empty($decision->custom_fs_no) && empty($req->payment_reference)) {
                            $req->payment_reference = 'FS: ' . $decision->custom_fs_no . ($decision->custom_mrc_no ? ' | MRC: ' . $decision->custom_mrc_no : '');
                        }
                        $req->has_withholding    = $hasWht;
                        $req->withholding_rate   = $whtRate;
                        $req->withholding_amount = $whtAmount;
                        if ($whtReceipt) {
                            $req->withholding_receipt = $whtReceipt;
                        }
                        if ($whtReceiptNo) {
                            $req->withholding_receipt_number = $whtReceiptNo;
                        }
                        $req->net_amount = max(0, ($req->gross_amount ?: $req->amount) - $whtAmount);
                        $req->save();

                        $targetDate  = $targetDate ?: ($req->paid_at ? Carbon::parse($req->paid_at)->toDateString() : ($req->created_at ? Carbon::parse($req->created_at)->toDateString() : null));
                        $projectId   = $projectId ?: $req->project_id;
                        $vendorName  = $vendorName ?: ($req->user?->name ?: 'Vendor');
                        $itemDesc    = $itemDesc ?: ($req->description ?: 'Expense Voucher');
                        if ($receiptPath) {
                            $req->attachment = $receiptPath;
                            $req->save();
                        } else {
                            $receiptPath = $req->attachment;
                        }
                    }
                } elseif ($sourceType === 'procurement_payment') {
                    $payment = ProcurementPayment::with('purchaseRequest')->find($sourceId);
                    if ($payment) {
                        $payment->vat_amount   = $vatAmount;
                        $payment->vat_type     = $isInclusive ? 'inclusive' : 'exclusive';
                        $payment->vat_rate     = $vatRate;
                        if ($totalAmount > 0) {
                            $payment->gross_amount = $totalAmount;
                        }
                        $payment->has_withholding    = $hasWht;
                        $payment->withholding_rate   = $whtRate;
                        $payment->withholding_amount = $whtAmount;
                        if ($whtReceipt) {
                            $payment->withholding_receipt = $whtReceipt;
                        }
                        if ($whtReceiptNo) {
                            $payment->withholding_receipt_number = $whtReceiptNo;
                        }
                        $payment->net_amount = max(0, ($payment->gross_amount ?: $payment->amount) - $whtAmount);
                        if ($receiptPath && Schema::hasColumn('procurement_payments', 'receipt_path')) {
                            $payment->receipt_path = $receiptPath;
                        }
                        $payment->save();

                        // Also update linked PR ExpenseRequest if one exists
                        if ($payment->purchase_request_id) {
                            $prExpUpdate = [
                                'vat_amount'         => $vatAmount,
                                'vat_type'           => $isInclusive ? 'inclusive' : 'exclusive',
                                'vat_rate'           => $vatRate,
                                'has_withholding'    => $hasWht,
                                'withholding_rate'   => $whtRate,
                                'withholding_amount' => $whtAmount,
                            ];
                            if ($whtReceipt) $prExpUpdate['withholding_receipt'] = $whtReceipt;
                            if ($whtReceiptNo) $prExpUpdate['withholding_receipt_number'] = $whtReceiptNo;
                            if ($receiptPath) $prExpUpdate['attachment'] = $receiptPath;
                            ExpenseRequest::where('purchase_request_id', $payment->purchase_request_id)->update($prExpUpdate);
                        }

                        $targetDate  = $targetDate ?: ($payment->paid_at ? Carbon::parse($payment->paid_at)->toDateString() : ($payment->created_at ? Carbon::parse($payment->created_at)->toDateString() : null));
                        $projectId   = $projectId ?: $payment->purchaseRequest?->project_id;
                        $vendorName  = $vendorName ?: ($payment->purchaseRequest?->vendor_name ?: 'Procurement Vendor');
                        $itemDesc    = $itemDesc ?: ($payment->purchaseRequest?->justification ?: 'Procurement Material Purchase');
                    }
                } elseif ($sourceType === 'credit_store_payment') {
                    $p = CreditStorePayment::with('ledger')->find($sourceId);
                    if ($p) {
                        $p->vat_amount = $vatAmount;
                        $p->vat_type   = $isInclusive ? 'inclusive' : 'exclusive';
                        $p->vat_rate   = $vatRate;
                        $p->has_withholding    = $hasWht;
                        $p->withholding_rate   = $whtRate;
                        $p->withholding_amount = $whtAmount;
                        if ($whtReceipt) {
                            $p->withholding_receipt = $whtReceipt;
                        }
                        if ($whtReceiptNo) {
                            $p->withholding_receipt_number = $whtReceiptNo;
                        }
                        $p->net_amount = max(0, ($p->gross_amount ?: $p->amount) - $whtAmount);
                        if ($receiptPath && Schema::hasColumn('credit_store_payments', 'receipt_path')) {
                            $p->receipt_path = $receiptPath;
                        }
                        $p->save();

                        $targetDate  = $targetDate ?: ($p->payment_date ? Carbon::parse($p->payment_date)->toDateString() : ($p->created_at ? Carbon::parse($p->created_at)->toDateString() : null));
                        $projectId   = $projectId ?: $p->ledger?->project_id;
                        $vendorName  = $vendorName ?: ($p->ledger?->supplier_name ?: 'Credit Supplier');
                        $itemDesc    = $itemDesc ?: ($p->ledger?->item_description ?: 'Credit Store Settlement');
                        $receiptPath = $receiptPath ?: $p->receipt_path;
                    }
                } elseif ($sourceType === 'petty_cash_purchase') {
                    $pc = PettyCashMaterialPurchase::find($sourceId);
                    if ($pc) {
                        $pc->vat_amount = $vatAmount;
                        if ($receiptPath) {
                            if (Schema::hasColumn('petty_cash_material_purchases', 'attachment_path')) {
                                $pc->attachment_path = $receiptPath;
                            }
                            if (Schema::hasColumn('petty_cash_material_purchases', 'receipt_path')) {
                                $pc->receipt_path = $receiptPath;
                            }
                        }
                        $pc->save();

                        $targetDate  = $targetDate ?: ($pc->purchase_date ? Carbon::parse($pc->purchase_date)->toDateString() : ($pc->created_at ? Carbon::parse($pc->created_at)->toDateString() : null));
                        $projectId   = $projectId ?: $pc->project_id;
                        $vendorName  = $vendorName ?: ($pc->vendor_name ?: 'Petty Cash Vendor');
                        $itemDesc    = $itemDesc ?: ($pc->item_name ?: 'Petty Cash Purchase');
                        $receiptPath = $receiptPath ?: ($pc->receipt_path ?: ($pc->attachment_path ?? null));
                    }
                }

                if (!$targetDate) {
                    $targetDate = now()->toDateString();
                }
                if (!$vendorName) {
                    $vendorName = 'Supplier';
                }
                if (!$itemDesc) {
                    $itemDesc = 'Purchased Materials / VAT Invoice';
                }

                // 2. Sync to Receipt (ERCA Line 100 VAT declaration table)
                if (Schema::hasTable('receipts')) {
                    $receipt = null;
                    if ($purchasableClass && $sourceId && Schema::hasColumn('receipts', 'purchasable_type')) {
                        $receipt = Receipt::where('purchasable_type', $purchasableClass)
                            ->where('purchasable_id', $sourceId)
                            ->first();
                    }
                    if (!$receipt && !empty($decision->custom_fs_no)) {
                        $receipt = Receipt::where('fs_no', $decision->custom_fs_no)->first();
                    }
                    if (!$receipt) {
                        $receipt = new Receipt();
                        $receipt->receipt_number = 'RCP-HUB-' . Carbon::parse($targetDate)->format('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
                    }

                    $receipt->uploaded_by      = $decision->decided_by ?: (Auth::id() ?: 1);
                    $receipt->approved_by      = $decision->decided_by ?: (Auth::id() ?: 1);
                    $receipt->approved_at      = Carbon::parse($targetDate)->setTime(12, 0, 0);
                    $receipt->status           = 'approved';
                    if ($purchasableClass && Schema::hasColumn('receipts', 'purchasable_type')) {
                        $receipt->purchasable_type = $purchasableClass;
                        $receipt->purchasable_id   = $sourceId;
                    }
                    if ($projectId) {
                        $receipt->project_id   = $projectId;
                    }
                    $receipt->vendor_name      = $vendorName;
                    $receipt->vendor_tin       = $decision->custom_seller_tin ?: ($receipt->vendor_tin ?: null);
                    $receipt->tin_valid        = !empty($receipt->vendor_tin);
                    $receipt->buyer_tin        = '0038480010';
                    $receipt->fs_no            = $decision->custom_fs_no ?: ($receipt->fs_no ?: null);
                    $receipt->fs_no_raw        = $decision->custom_fs_no ?: ($receipt->fs_no_raw ?: null);
                    $receipt->fs_no_valid      = !empty($receipt->fs_no);
                    $receipt->mrc_no           = $decision->custom_mrc_no ?: ($receipt->mrc_no ?: null);
                    $receipt->receipt_date     = Carbon::parse($targetDate)->toDateString();
                    $receipt->subtotal         = $baseAmount;
                    $receipt->vat_amount       = $vatAmount;
                    $receipt->total_amount     = $totalAmount;
                    $receipt->currency         = 'ETB';
                    $receipt->category         = 'material';
                    $receipt->description      = $itemDesc;
                    if ($receiptPath) {
                        $receipt->file_path    = $receiptPath;
                        $receipt->file_type    = str_ends_with(strtolower($receiptPath), '.pdf') ? 'pdf' : 'image';
                    }
                    $receipt->save();

                    // 3. Sync to ReceiptItem
                    if (Schema::hasTable('receipt_items')) {
                        $receiptItem = ReceiptItem::where('receipt_id', $receipt->id)->first();
                        if (!$receiptItem) {
                            $receiptItem = new ReceiptItem();
                            $receiptItem->receipt_id = $receipt->id;
                        }
                        $receiptItem->item_description = $itemDesc;
                        $receiptItem->vat_category     = $decision->vat_category ?: 'G';
                        $receiptItem->calendar_type    = 'E';
                        $receiptItem->purchase_type    = (int)($decision->purchase_type ?: 3);
                        $receiptItem->uom              = $decision->uom ?: '9';
                        $receiptItem->qty              = (float)($decision->quantity ?: 1.0);
                        $receiptItem->unit_price       = (float)($decision->unit_price ?: $baseAmount);
                        $receiptItem->total_value      = $baseAmount;
                        $receiptItem->vat_amount       = $vatAmount;
                        $receiptItem->value_after_vat  = $totalAmount;
                        $receiptItem->is_flagged       = false;
                        $receiptItem->save();
                    }
                }
            } elseif ($vatStatus === ExpenseHubVatDecision::STATUS_NO_VAT) {
                $targetDate  = $customReceiptDate;
                $projectId   = null;
                $vendorName  = $customSellerName;
                $itemDesc    = $customDescription ?: $decision->custom_item_description;

                // 1. Clear VAT from source model and zero out receipt VAT, while syncing Withholding Tax
                if ($sourceType === 'expense_request') {
                    $req = ExpenseRequest::find($sourceId);
                    if ($req) {
                        $req->vat_amount = 0;
                        $req->vat_type   = 'none';
                        if (!empty($decision->custom_fs_no) && empty($req->payment_reference)) {
                            $req->payment_reference = 'FS: ' . $decision->custom_fs_no . ($decision->custom_mrc_no ? ' | MRC: ' . $decision->custom_mrc_no : '');
                        }
                        $req->has_withholding    = $hasWht;
                        $req->withholding_rate   = $whtRate;
                        $req->withholding_amount = $whtAmount;
                        if ($whtReceipt) $req->withholding_receipt = $whtReceipt;
                        if ($whtReceiptNo) $req->withholding_receipt_number = $whtReceiptNo;
                        $req->net_amount = max(0, ($req->gross_amount ?: $req->amount) - $whtAmount);
                        $req->save();

                        $targetDate  = $targetDate ?: ($req->paid_at ? Carbon::parse($req->paid_at)->toDateString() : ($req->created_at ? Carbon::parse($req->created_at)->toDateString() : null));
                        $projectId   = $projectId ?: $req->project_id;
                        $vendorName  = $vendorName ?: ($req->user?->name ?: 'Vendor');
                        $itemDesc    = $itemDesc ?: ($req->description ?: 'Operational Expense / Voucher');
                        if ($receiptPath) {
                            $req->attachment = $receiptPath;
                            $req->save();
                        } else {
                            $receiptPath = $req->attachment;
                        }
                    }
                } elseif ($sourceType === 'procurement_payment') {
                    $payment = ProcurementPayment::find($sourceId);
                    if ($payment) {
                        $payment->vat_amount = 0;
                        $payment->vat_type   = 'none';
                        $payment->has_withholding    = $hasWht;
                        $payment->withholding_rate   = $whtRate;
                        $payment->withholding_amount = $whtAmount;
                        if ($whtReceipt) $payment->withholding_receipt = $whtReceipt;
                        if ($whtReceiptNo) $payment->withholding_receipt_number = $whtReceiptNo;
                        $payment->net_amount = max(0, ($payment->gross_amount ?: $payment->amount) - $whtAmount);
                        if ($receiptPath && Schema::hasColumn('procurement_payments', 'receipt_path')) {
                            $payment->receipt_path = $receiptPath;
                        }
                        $payment->save();

                        if ($payment->purchase_request_id) {
                            $prExpUpdate = [
                                'vat_amount'         => 0,
                                'vat_type'           => 'none',
                                'has_withholding'    => $hasWht,
                                'withholding_rate'   => $whtRate,
                                'withholding_amount' => $whtAmount,
                            ];
                            if ($whtReceipt) $prExpUpdate['withholding_receipt'] = $whtReceipt;
                            if ($whtReceiptNo) $prExpUpdate['withholding_receipt_number'] = $whtReceiptNo;
                            if ($receiptPath) $prExpUpdate['attachment'] = $receiptPath;
                            ExpenseRequest::where('purchase_request_id', $payment->purchase_request_id)->update($prExpUpdate);
                        }

                        $targetDate  = $targetDate ?: ($payment->paid_at ? Carbon::parse($payment->paid_at)->toDateString() : ($payment->created_at ? Carbon::parse($payment->created_at)->toDateString() : null));
                        $projectId   = $projectId ?: $payment->purchaseRequest?->project_id;
                        $vendorName  = $vendorName ?: ($payment->purchaseRequest?->vendor_name ?: 'Procurement Vendor');
                        $itemDesc    = $itemDesc ?: ($payment->purchaseRequest?->justification ?: 'Procurement Material Purchase');
                    }
                } elseif ($sourceType === 'credit_store_payment') {
                    $p = CreditStorePayment::find($sourceId);
                    if ($p) {
                        $p->vat_amount = 0;
                        $p->vat_type   = 'none';
                        $p->has_withholding    = $hasWht;
                        $p->withholding_rate   = $whtRate;
                        $p->withholding_amount = $whtAmount;
                        if ($whtReceipt) $p->withholding_receipt = $whtReceipt;
                        if ($whtReceiptNo) $p->withholding_receipt_number = $whtReceiptNo;
                        $p->net_amount = max(0, ($p->gross_amount ?: $p->amount) - $whtAmount);
                        if ($receiptPath && Schema::hasColumn('credit_store_payments', 'receipt_path')) {
                            $p->receipt_path = $receiptPath;
                        }
                        $p->save();

                        $targetDate  = $targetDate ?: ($p->payment_date ? Carbon::parse($p->payment_date)->toDateString() : ($p->created_at ? Carbon::parse($p->created_at)->toDateString() : null));
                        $projectId   = $projectId ?: $p->ledger?->project_id;
                        $vendorName  = $vendorName ?: ($p->ledger?->supplier_name ?: 'Credit Supplier');
                        $itemDesc    = $itemDesc ?: ($p->ledger?->item_description ?: 'Credit Store Settlement');
                        $receiptPath = $receiptPath ?: $p->receipt_path;
                    }
                } elseif ($sourceType === 'petty_cash_purchase') {
                    $pc = PettyCashMaterialPurchase::find($sourceId);
                    if ($pc) {
                        $pc->vat_amount = 0;
                        if ($receiptPath) {
                            if (Schema::hasColumn('petty_cash_material_purchases', 'attachment_path')) {
                                $pc->attachment_path = $receiptPath;
                            }
                            if (Schema::hasColumn('petty_cash_material_purchases', 'receipt_path')) {
                                $pc->receipt_path = $receiptPath;
                            }
                        }
                        $pc->save();

                        $targetDate  = $targetDate ?: ($pc->purchase_date ? Carbon::parse($pc->purchase_date)->toDateString() : ($pc->created_at ? Carbon::parse($pc->created_at)->toDateString() : null));
                        $projectId   = $projectId ?: $pc->project_id;
                        $vendorName  = $vendorName ?: ($pc->vendor_name ?: 'Petty Cash Vendor');
                        $itemDesc    = $itemDesc ?: ($pc->item_name ?: 'Petty Cash Purchase');
                        $receiptPath = $receiptPath ?: ($pc->receipt_path ?: ($pc->attachment_path ?? null));
                    }
                }

                if (!$targetDate) {
                    $targetDate = now()->toDateString();
                }
                if (!$vendorName) {
                    $vendorName = 'Supplier';
                }
                if (!$itemDesc) {
                    $itemDesc = 'Expense Purchase (0% VAT)';
                }

                $hasReceiptData = !empty($decision->custom_fs_no) || !empty($decision->custom_seller_tin) || !empty($receiptPath) || !empty($decision->custom_mrc_no);

                // 2. Sync to Receipt (ERCA TAS Purchase / 0% VAT declaration)
                if (Schema::hasTable('receipts')) {
                    $receipt = null;
                    if ($purchasableClass && $sourceId && Schema::hasColumn('receipts', 'purchasable_type')) {
                        $receipt = Receipt::where('purchasable_type', $purchasableClass)
                            ->where('purchasable_id', $sourceId)
                            ->first();
                    }
                    if (!$receipt && !empty($decision->custom_fs_no)) {
                        $receipt = Receipt::where('fs_no', $decision->custom_fs_no)->first();
                    }
                    if (!$receipt && $hasReceiptData) {
                        $receipt = new Receipt();
                        $receipt->receipt_number   = 'RCP-HUB-' . Carbon::parse($targetDate)->format('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
                    }

                    if ($receipt) {
                        $isFuel = (str_contains(strtoupper($itemDesc), 'FUEL')
                            || (isset($req) && str_contains(strtoupper($req->request_number ?? ''), 'FUEL'))
                            || ($decision->no_vat_reason_code === 'fuel_zero_vat'));

                        $resolvedPurcType = $purcType ?: ($decision->purc_type ?: ($isFuel ? 'PUR_GD' : 'PUR_GD'));

                        $receipt->uploaded_by      = $decision->decided_by ?: (Auth::id() ?: 1);
                        $receipt->approved_by      = $decision->decided_by ?: (Auth::id() ?: 1);
                        $receipt->approved_at      = Carbon::parse($targetDate)->setTime(12, 0, 0);
                        $receipt->status           = 'approved';
                        if ($purchasableClass && Schema::hasColumn('receipts', 'purchasable_type')) {
                            $receipt->purchasable_type = $purchasableClass;
                            $receipt->purchasable_id   = $sourceId;
                        }
                        if ($projectId) {
                            $receipt->project_id   = $projectId;
                        }
                        $receipt->category         = $isFuel ? 'fuel' : 'material';
                        $receipt->description      = $itemDesc;
                        $receipt->vendor_name      = $vendorName;
                        $receipt->receipt_date     = Carbon::parse($targetDate)->toDateString();
                        $receipt->vat_amount       = 0.00;
                        $receipt->subtotal         = $totalAmount;
                        $receipt->total_amount     = $totalAmount;
                        $receipt->currency         = 'ETB';
                        $receipt->tax_type         = 'no_vat';
                        $receipt->buyer_tin        = SystemSetting::get('purchaser_tin', '38480010');
                        if (Schema::hasColumn('receipts', 'no_vat_reason_code')) {
                            $receipt->no_vat_reason_code = $decision->no_vat_reason_code ?: ($isFuel ? 'fuel_zero_vat' : 'tas_purchase');
                        }

                        if (!empty($decision->custom_fs_no)) {
                            $receipt->fs_no       = $decision->custom_fs_no;
                            $receipt->fs_no_raw   = $decision->custom_fs_no;
                            $receipt->fs_no_valid = true;
                        }
                        if (!empty($decision->custom_mrc_no)) {
                            $receipt->mrc_no = $decision->custom_mrc_no;
                        }
                        if (!empty($decision->custom_seller_tin)) {
                            $receipt->vendor_tin = $decision->custom_seller_tin;
                            $receipt->tin_valid  = true;
                        }
                        if ($receiptPath) {
                            $receipt->file_path    = $receiptPath;
                            $receipt->file_type    = str_ends_with(strtolower($receiptPath), '.pdf') ? 'pdf' : 'image';
                        }

                        $purchaserDigits = (int)preg_replace('/^0+/', '', preg_replace('/[^0-9]/', '', (string)$receipt->buyer_tin));
                        if (!$purchaserDigits) $purchaserDigits = 38480010;

                        $sellerDigits = preg_replace('/^0+/', '', preg_replace('/[^0-9]/', '', (string)$receipt->vendor_tin));
                        $sellerTinVal = ($sellerDigits !== '') ? (int)$sellerDigits : (trim((string)$receipt->vendor_tin) ?: '');

                        $receiptNo = trim((string)($receipt->fs_no_raw ?: ($receipt->fs_no ?: $receipt->receipt_number)));

                        // Exact 9 columns required by pro.doc e-Tax format
                        $parsedData = [
                            'purchaser_tin' => $purchaserDigits,
                            'seller_tin'    => $sellerTinVal,
                            'amount'        => $totalAmount,
                            'receipt_no'    => $receiptNo,
                            'receipt_date'  => EthiopianDate::formatDateEc($receipt->receipt_date, 'd-m-Y'),
                            'encoding_date' => EthiopianDate::formatDateEc(now(), 'd-m-Y'),
                            'mrc'           => trim((string)$receipt->mrc_no),
                            'purc_type'     => $resolvedPurcType,
                            'encoded_by'    => 'wechacha',
                        ];
                        $receipt->parsed_data = json_encode($parsedData);
                        $receipt->save();

                        // 3. Sync to ReceiptItem
                        if (Schema::hasTable('receipt_items')) {
                            $receiptItem = ReceiptItem::where('receipt_id', $receipt->id)->first();
                            if (!$receiptItem) {
                                $receiptItem = new ReceiptItem();
                                $receiptItem->receipt_id = $receipt->id;
                            }
                            $receiptItem->item_description = $receipt->description;
                            $receiptItem->vat_category     = 'E';
                            $receiptItem->calendar_type    = 'E';
                            $receiptItem->purchase_type    = 6; // Exempt / no vat
                            $receiptItem->uom              = $decision->uom ?: '9';
                            $receiptItem->qty              = (float)($decision->quantity ?: 1.0);
                            $receiptItem->unit_price       = (float)($decision->unit_price ?: $totalAmount);
                            $receiptItem->total_value      = $totalAmount;
                            $receiptItem->vat_amount       = 0.00;
                            $receiptItem->value_after_vat  = $totalAmount;
                            $receiptItem->is_flagged       = false;
                            if (Schema::hasColumn('receipt_items', 'purc_type')) {
                                $receiptItem->purc_type    = $resolvedPurcType;
                            }
                            $receiptItem->save();
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('syncVatDecisionToLedger failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        }
    }
}
