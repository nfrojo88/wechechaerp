<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\ExpenseRequest;
use App\Models\TaxSettlement;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\ChartOfAccount;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\User;
use App\Services\ReceiptOcrService;
use App\Services\QcService;
use App\Services\EthiopianDate;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceTaxReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Defensive self-healing schema creation if migrations haven't run on the environment yet.
     */
    protected function ensureSchema(): void
    {
        try {
            if (!Schema::hasTable('tax_settlements')) {
                Schema::create('tax_settlements', function (\Illuminate\Database\Schema\Blueprint $table) {
                    $table->id();
                    $table->string('settlement_number', 50)->unique();
                    $table->string('tax_type', 30)->default('both'); // both, vat, withholding
                    $table->timestamp('period_from')->nullable();
                    $table->timestamp('period_to')->nullable();
                    $table->decimal('total_base_amount', 14, 2)->default(0);
                    $table->decimal('vat_amount', 14, 2)->default(0);
                    $table->decimal('withholding_amount', 14, 2)->default(0);
                    $table->decimal('total_tax_paid', 14, 2)->default(0);
                    $table->integer('records_count')->default(0);
                    $table->string('status', 30)->default('pending_payment');
                    $table->foreignId('finance_head_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->foreignId('assigned_finance_staff_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
                    $table->timestamp('paid_at')->nullable();
                    $table->string('payment_method', 50)->nullable();
                    $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
                    $table->foreignId('coa_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
                    $table->string('payment_reference', 100)->nullable();
                    $table->string('attachment', 255)->nullable();
                    $table->text('payment_notes')->nullable();
                    $table->timestamps();
                });
            }

            if (Schema::hasTable('expense_requests')) {
                Schema::table('expense_requests', function (\Illuminate\Database\Schema\Blueprint $table) {
                    if (!Schema::hasColumn('expense_requests', 'tax_settlement_id')) {
                        $table->unsignedBigInteger('tax_settlement_id')->nullable()->index();
                    }
                    if (!Schema::hasColumn('expense_requests', 'vat_settlement_id')) {
                        $table->unsignedBigInteger('vat_settlement_id')->nullable()->index();
                    }
                    if (!Schema::hasColumn('expense_requests', 'withholding_settlement_id')) {
                        $table->unsignedBigInteger('withholding_settlement_id')->nullable()->index();
                    }
                    if (!Schema::hasColumn('expense_requests', 'vat_settled')) {
                        $table->boolean('vat_settled')->default(false)->index();
                    }
                    if (!Schema::hasColumn('expense_requests', 'withholding_settled')) {
                        $table->boolean('withholding_settled')->default(false)->index();
                    }
                    if (!Schema::hasColumn('expense_requests', 'tax_settled_at')) {
                        $table->timestamp('tax_settled_at')->nullable();
                    }
                    if (!Schema::hasColumn('expense_requests', 'vat_settled_at')) {
                        $table->timestamp('vat_settled_at')->nullable();
                    }
                    if (!Schema::hasColumn('expense_requests', 'withholding_settled_at')) {
                        $table->timestamp('withholding_settled_at')->nullable();
                    }
                });
            }
        } catch (\Throwable $e) {
            // Silently ignore if already created
        }
    }

    /**
     * Display VAT and Withholding Tax deduction ledger, settlements, and analytics.
     */
    public function index(Request $request)
    {
        $this->ensureSchema();

        $tab            = $request->input('tab', 'all');
        $cycle          = $request->input('cycle', 'active'); // 'active' (starts from zero after settlement), 'all', 'settled'
        $search         = $request->input('search');
        $category       = $request->input('category');
        $vatType        = $request->input('vat_type');
        $hasWithholding = $request->input('has_withholding');
        $fromDate       = $request->input('from_date');
        $toDate         = $request->input('to_date');
        $accountId      = $request->input('account_id');

        // Base Query: Strictly PAID records that have VAT, Withholding Tax, or an uploaded Withholding slip
        $query = ExpenseRequest::with(['user', 'employee', 'paidBy', 'bankAccount', 'chartOfAccount', 'letter', 'purchaseRequest', 'taxSettlement'])
            ->where('status', ExpenseRequest::STATUS_PAID)
            ->where(function ($q) {
                $q->where('has_withholding', true)
                  ->orWhere('withholding_amount', '>', 0)
                  ->orWhere('vat_amount', '>', 0)
                  ->orWhereIn('vat_type', ['exclusive', 'inclusive', 'vat_b'])
                  ->orWhere(function ($sq) {
                      $sq->whereNotNull('withholding_receipt')->where('withholding_receipt', '!=', '');
                  });
            });

        // Separate cycle filtering so VAT and Withholding settlements operate independently!
        if ($cycle === 'active') {
            if ($tab === 'vat') {
                $query->where('vat_settled', false)->whereNull('vat_settlement_id');
            } elseif ($tab === 'withholding') {
                $query->where('withholding_settled', false)->whereNull('withholding_settlement_id');
            } elseif ($tab === 'slips') {
                $query->where('withholding_settled', false)->whereNull('withholding_settlement_id');
            } else {
                // 'all': Show records that have either unsettled VAT OR unsettled Withholding
                $query->where(function ($q) {
                    $q->where(function ($sub) {
                        $sub->where('vat_settled', false)
                            ->whereNull('vat_settlement_id')
                            ->where(function ($v) {
                                $v->where('vat_amount', '>', 0)
                                  ->orWhereIn('vat_type', ['exclusive', 'inclusive', 'vat_b']);
                            });
                    })->orWhere(function ($sub) {
                        $sub->where('withholding_settled', false)
                            ->whereNull('withholding_settlement_id')
                            ->where(function ($w) {
                                $w->where('has_withholding', true)
                                  ->orWhere('withholding_amount', '>', 0);
                            });
                    });
                });
            }
        } elseif ($cycle === 'settled') {
            if ($tab === 'vat') {
                $query->where('vat_settled', true);
            } elseif ($tab === 'withholding' || $tab === 'slips') {
                $query->where('withholding_settled', true);
            } else {
                $query->where(function ($q) {
                    $q->where('vat_settled', true)
                      ->orWhere('withholding_settled', true);
                });
            }
        }
        // 'all' cycle displays both active and settled without cycle restriction

        // Tab Filtering
        if ($tab === 'withholding') {
            $query->where(function ($q) {
                $q->where('has_withholding', true)
                  ->orWhere('withholding_amount', '>', 0);
            });
        } elseif ($tab === 'vat') {
            $query->where(function ($q) {
                $q->where('vat_amount', '>', 0)
                  ->orWhereIn('vat_type', ['exclusive', 'inclusive', 'vat_b']);
            });
        } elseif ($tab === 'slips') {
            $query->whereNotNull('withholding_receipt')->where('withholding_receipt', '!=', '');
        }

        // Search Filter
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('request_number', 'like', "%{$search}%")
                  ->orWhere('payment_reference', 'like', "%{$search}%")
                  ->orWhere('withholding_receipt_number', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        // Category Filter
        if (!empty($category)) {
            $query->where('category', $category);
        }

        // VAT Type Filter
        if (!empty($vatType)) {
            $query->where('vat_type', $vatType);
        }

        // Withholding Filter
        if ($hasWithholding !== null && $hasWithholding !== '') {
            $query->where('has_withholding', (bool)$hasWithholding);
        }

        // Ethiopian Year & Month Selector (the period is always one Ethiopian month)
        $curEth = EthiopianDate::toEthiopian(now());
        $selectedEthYear  = (int)($request->input('ethiopian_year') ?: ($request->input('year') ?: $curEth['year']));
        $selectedEthMonth = (int)($request->input('ethiopian_month') ?: ($request->input('month') ?: $curEth['month']));
        if ($selectedEthMonth < 1 || $selectedEthMonth > 13) {
            $selectedEthMonth = $curEth['month'];
        }

        $monthRange = EthiopianDate::monthRange($selectedEthYear, $selectedEthMonth);
        $periodStart     = $monthRange['start'];
        $periodEnd       = $monthRange['end'];
        $periodStartDate = $monthRange['start_date'];
        $periodEndDate   = $monthRange['end_date'];

        // Period filter strictly bounded to the Ethiopian month (or legacy fallback if explicitly requested)
        if (!empty($fromDate) && !empty($toDate) && !$request->filled('ethiopian_year') && !$request->filled('ethiopian_month')) {
            $query->whereDate('created_at', '>=', Carbon::parse($fromDate))
                  ->whereDate('created_at', '<=', Carbon::parse($toDate));
        } else {
            $query->whereBetween('created_at', [$periodStart, $periodEnd]);
        }

        // Funding Account Filter
        if (!empty($accountId)) {
            $query->where(function ($q) use ($accountId) {
                $q->where('bank_account_id', $accountId)
                  ->orWhere('coa_id', $accountId)
                  ->orWhere('chart_of_account_id', $accountId);
            });
        }

        // Summary Aggregates for the currently filtered view
        $summaryQuery = clone $query;
        $allTaxItems = $summaryQuery->get();

        $totalRecords = $allTaxItems->count();
        $totalGrossBase = $allTaxItems->sum(function ($item) {
            return (float)($item->gross_amount > 0 ? $item->gross_amount : $item->amount);
        });

        // Creditable Input VAT from paid expenses in this period (Strictly excluding VAT B!)
        $totalVatAmount = $allTaxItems->sum(function ($item) use ($cycle) {
            if ($cycle === 'active' && $item->vat_settled) return 0.0;
            // Exclude "VAT B" from creditable Input VAT
            if (($item->vat_type ?? '') === 'vat_b') return 0.0;

            if ((float)$item->vat_amount > 0 && ($item->vat_type ?? '') === 'exclusive') {
                return (float)$item->vat_amount;
            }
            $gross = (float)($item->gross_amount > 0 ? $item->gross_amount : $item->amount);
            $vatType = $item->vat_type ?? 'none';
            $vatRate = (float)($item->vat_rate ?? 15.00);
            if ($vatType === 'inclusive') {
                $base = round($gross / (1 + ($vatRate / 100)), 2);
                return round($gross - $base, 2);
            } elseif ($vatType === 'exclusive') {
                return round($gross * ($vatRate / 100), 2);
            }
            return 0.0;
        });

        $totalWithholdingAmount = $allTaxItems->sum(function ($item) use ($cycle) {
            if ($cycle === 'active' && $item->withholding_settled) return 0.0;
            return (float)$item->calculated_withholding_amount;
        });

        $totalNetDisbursed = $allTaxItems->sum(function ($item) {
            return (float)$item->effective_payable_amount;
        });

        $totalWhtTransactions = $allTaxItems->filter(fn($item) => ($item->has_withholding || (float)$item->withholding_amount > 0) && (!$cycle === 'active' || !$item->withholding_settled))->count();
        $slipsAttachedCount = $allTaxItems->filter(fn($item) => ($item->has_withholding || (float)$item->withholding_amount > 0) && !empty($item->withholding_receipt))->count();
        $missingSlipsCount = max(0, $totalWhtTransactions - $slipsAttachedCount);

        // ── SEPARATED UNSETTLED METRICS ──────────────────────────────────────────
        // 1. Unsettled VAT Pool (excluding VAT B from creditable pool)
        $unsettledVatItems = ExpenseRequest::where('status', ExpenseRequest::STATUS_PAID)
            ->where('vat_settled', false)
            ->whereNull('vat_settlement_id')
            ->where(function ($q) {
                $q->where('vat_amount', '>', 0)
                  ->orWhereIn('vat_type', ['exclusive', 'inclusive']);
            })
            ->where('vat_type', '!=', 'vat_b')
            ->get();

        $unsettledVatCount = $unsettledVatItems->count();
        $unsettledVatBaseAmount = $unsettledVatItems->sum(fn($i) => (float)($i->gross_amount > 0 ? $i->gross_amount : $i->amount));
        $unsettledVatAmount = $unsettledVatItems->sum(function ($item) {
            if (($item->vat_type ?? '') === 'vat_b') return 0.0;
            if ((float)$item->vat_amount > 0 && ($item->vat_type ?? '') === 'exclusive') return (float)$item->vat_amount;
            $gross = (float)($item->gross_amount > 0 ? $item->gross_amount : $item->amount);
            $vatType = $item->vat_type ?? 'none';
            $vatRate = (float)($item->vat_rate ?? 15.00);
            if ($vatType === 'inclusive') {
                $base = round($gross / (1 + ($vatRate / 100)), 2);
                return round($gross - $base, 2);
            } elseif ($vatType === 'exclusive') {
                return round($gross * ($vatRate / 100), 2);
            }
            return 0.0;
        });

        // 2. Unsettled Withholding Tax Pool
        $unsettledWhtItems = ExpenseRequest::where('status', ExpenseRequest::STATUS_PAID)
            ->where('withholding_settled', false)
            ->whereNull('withholding_settlement_id')
            ->where(function ($q) {
                $q->where('has_withholding', true)
                  ->orWhere('withholding_amount', '>', 0);
            })
            ->get();

        $unsettledWhtCount = $unsettledWhtItems->count();
        $unsettledWhtBaseAmount = $unsettledWhtItems->sum(fn($i) => (float)($i->gross_amount > 0 ? $i->gross_amount : $i->amount));
        $unsettledWhtAmount = $unsettledWhtItems->sum(fn($i) => (float)$i->calculated_withholding_amount);

        // Combined totals for convenience
        $unsettledTotalTax = $unsettledVatAmount + $unsettledWhtAmount;
        $unsettledCount = ExpenseRequest::where('status', ExpenseRequest::STATUS_PAID)
            ->where(function ($q) {
                $q->where(function ($sub) {
                    $sub->where('vat_settled', false)->whereNull('vat_settlement_id')
                        ->where(function ($v) { $v->where('vat_amount', '>', 0)->orWhereIn('vat_type', ['exclusive', 'inclusive']); });
                })->orWhere(function ($sub) {
                    $sub->where('withholding_settled', false)->whereNull('withholding_settlement_id')
                        ->where(function ($w) { $w->where('has_withholding', true)->orWhere('withholding_amount', '>', 0); });
                });
            })->count();

        // Active pending settlement(s) assigned to finance staff
        $pendingSettlements = TaxSettlement::with(['financeHead', 'assignedStaff', 'bankAccount', 'chartOfAccount'])
            ->where('status', TaxSettlement::STATUS_PENDING)
            ->latest()
            ->get();

        // Completed settlements history
        $settlements = TaxSettlement::with(['financeHead', 'assignedStaff', 'payer', 'bankAccount', 'chartOfAccount'])
            ->latest()
            ->paginate(15, ['*'], 'settlements_page');

        // Paginated tax deduction records
        $records = $query->latest()->paginate(20)->withQueryString();

        // ── OCR SCANNED RECEIPTS & ERCA VAT DECLARATION (LED BY OCR) ─────────
        $vatSource = $request->query('vat_source', 'ocr');

        $ocrQuery = \App\Models\ReceiptItem::with(['receipt', 'receipt.project', 'receipt.uploader'])
            ->whereHas('receipt')
            ->latest('id');

        if (!empty($search)) {
            $s = trim($search);
            $ocrQuery->where(function($q) use ($s) {
                $q->where('item_description', 'like', "%{$s}%")
                  ->orWhereHas('receipt', function($rq) use ($s) {
                      $rq->where('fs_no', 'like', "%{$s}%")
                         ->orWhere('fs_no_raw', 'like', "%{$s}%")
                         ->orWhere('vendor_name', 'like', "%{$s}%")
                         ->orWhere('vendor_tin', 'like', "%{$s}%")
                         ->orWhere('buyer_tin', 'like', "%{$s}%")
                         ->orWhere('mrc_no', 'like', "%{$s}%");
                  });
            });
        }

        if (!empty($category)) {
            $ocrQuery->where(function($q) use ($category) {
                $q->where('vat_category', $category)
                  ->orWhereHas('receipt', fn($rq) => $rq->where('category', $category));
            });
        }

        // Bounded to Ethiopian month
        if (!empty($fromDate) && !empty($toDate) && !$request->filled('ethiopian_year') && !$request->filled('ethiopian_month')) {
            $ocrQuery->whereHas('receipt', fn($rq) => $rq->whereDate('receipt_date', '>=', Carbon::parse($fromDate))->whereDate('receipt_date', '<=', Carbon::parse($toDate)));
        } else {
            $ocrQuery->whereHas('receipt', fn($rq) => $rq->whereBetween('receipt_date', [$periodStartDate, $periodEndDate]));
        }

        $ocrItems = $ocrQuery->paginate(20, ['*'], 'ocr_page')->withQueryString();

        $ocrStats = [
            'total_items'   => (int)\App\Models\ReceiptItem::whereHas('receipt', fn($rq) => $rq->whereBetween('receipt_date', [$periodStartDate, $periodEndDate]))->count(),
            'total_value'   => (float)\App\Models\ReceiptItem::whereHas('receipt', fn($rq) => $rq->whereBetween('receipt_date', [$periodStartDate, $periodEndDate]))->sum('total_value'),
            'total_vat'     => (float)\App\Models\ReceiptItem::whereHas('receipt', fn($rq) => $rq->whereBetween('receipt_date', [$periodStartDate, $periodEndDate]))->sum('vat_amount'),
            'total_after'   => (float)\App\Models\ReceiptItem::whereHas('receipt', fn($rq) => $rq->whereBetween('receipt_date', [$periodStartDate, $periodEndDate]))->sum('value_after_vat'),
            'flagged_count' => (int)\App\Models\ReceiptItem::where('is_flagged', true)->count(),
        ];

        // ═════════════════════════════════════════════════════════════════════
        // MONTHLY VAT: ERCA LINE 100 & SIGNED NET VAT
        // Output VAT = 15% VAT from Company Income receipts in the month
        // Input VAT  = creditable 15% VAT from paid expenses, petty cash, procurement, and OCR receipts. Exclude "VAT B".
        // Net        = Input VAT - Output VAT (factoring in opening credit)
        // ═════════════════════════════════════════════════════════════════════

        // 1. Company Income (Sales base & Output VAT)
        $monthIncomeItems = \App\Models\IncomeRecord::where(function ($iq) use ($periodStartDate, $periodEndDate) {
            $iq->whereBetween('income_date', [$periodStartDate, $periodEndDate])
               ->orWhere(function ($sub) use ($periodStartDate, $periodEndDate) {
                   $sub->whereNull('income_date')->whereBetween('receipt_date', [$periodStartDate, $periodEndDate]);
               });
        })->get();

        $salesBase = (float)$monthIncomeItems->sum(function ($inc) {
            $gross = (float)($inc->unit_price > 0 && $inc->quantity > 0 ? ($inc->unit_price * $inc->quantity) : $inc->amount);
            return round($gross / 1.15, 2);
        });
        $outputVat = (float)$monthIncomeItems->sum(function ($inc) {
            $gross = (float)($inc->unit_price > 0 && $inc->quantity > 0 ? ($inc->unit_price * $inc->quantity) : $inc->amount);
            $base = round($gross / 1.15, 2);
            return round($gross - $base, 2);
        });

        // 2. Creditable Purchases & Input VAT (Exclude "VAT B")
        $ocrItemsInMonth = \App\Models\ReceiptItem::whereHas('receipt', fn($rq) => 
            $rq->whereBetween('receipt_date', [$periodStartDate, $periodEndDate])
        )->get();

        $ocrPurchasesBase = (float)$ocrItemsInMonth->sum('total_value');
        $ocrInputVat      = (float)$ocrItemsInMonth->sum('vat_amount');

        $expensePurchasesBase = (float)$allTaxItems->filter(fn($i) => ($i->vat_type ?? '') !== 'vat_b')->sum(function ($i) {
            $gross = (float)($i->gross_amount > 0 ? $i->gross_amount : $i->amount);
            if (($i->vat_type ?? '') === 'inclusive') return round($gross / 1.15, 2);
            return $gross;
        });
        $expenseInputVat = $totalVatAmount; // Already strictly excludes VAT B above

        if ($vatSource === 'expense') {
            $purchasesBase = $expensePurchasesBase;
            $inputVat      = $expenseInputVat;
        } else {
            $purchasesBase = $ocrPurchasesBase > 0 ? $ocrPurchasesBase : $expensePurchasesBase;
            $inputVat      = $ocrInputVat > 0 ? $ocrInputVat : $expenseInputVat;
        }

        // 3. Opening Credit: Carry forward positive credit from immediately preceding Ethiopian month
        $prevYear  = ($selectedEthMonth === 1) ? $selectedEthYear - 1 : $selectedEthYear;
        $prevMonth = ($selectedEthMonth === 1) ? 13 : $selectedEthMonth - 1;
        $prevMonthRange = EthiopianDate::monthRange($prevYear, $prevMonth);

        $prevIncomeSum = (float)\App\Models\IncomeRecord::where(function ($iq) use ($prevMonthRange) {
            $iq->whereBetween('income_date', [$prevMonthRange['start_date'], $prevMonthRange['end_date']])
               ->orWhere(function ($sub) use ($prevMonthRange) {
                   $sub->whereNull('income_date')->whereBetween('receipt_date', [$prevMonthRange['start_date'], $prevMonthRange['end_date']]);
               });
        })->sum('amount');
        $prevOutputVat = round($prevIncomeSum - ($prevIncomeSum / 1.15), 2);

        $prevExpenseItems = ExpenseRequest::where('status', ExpenseRequest::STATUS_PAID)
            ->whereBetween('created_at', [$prevMonthRange['start'], $prevMonthRange['end']])
            ->where('vat_type', '!=', 'vat_b')
            ->get();
        $prevExpInputVat = (float)$prevExpenseItems->sum(function ($item) {
            if ((float)$item->vat_amount > 0 && ($item->vat_type ?? '') === 'exclusive') return (float)$item->vat_amount;
            $gross = (float)($item->gross_amount > 0 ? $item->gross_amount : $item->amount);
            if (($item->vat_type ?? '') === 'inclusive') return round($gross - ($gross / 1.15), 2);
            if (($item->vat_type ?? '') === 'exclusive') return round($gross * 0.15, 2);
            return 0.0;
        });
        $prevOcrInputVat = (float)\App\Models\ReceiptItem::whereHas('receipt', fn($rq) => 
            $rq->whereBetween('receipt_date', [$prevMonthRange['start_date'], $prevMonthRange['end_date']])
        )->sum('vat_amount');

        $effectivePrevInput = max($prevExpInputVat, $prevOcrInputVat);
        $openingCredit = max(0.0, round($effectivePrevInput - $prevOutputVat, 2));

        // 4. Signed Net VAT Calculation
        $netVatDetails = EthiopianDate::calculateNetVat($inputVat, $outputVat, $openingCredit);
        $signedNetVat  = $netVatDetails['net'];

        // 5. Withholding tax (3%) & Net Disbursed for the month (stays separate line, not part of Net)
        $monthWithholding = $totalWithholdingAmount;
        $monthNetDisbursed = $totalNetDisbursed;

        // 6. 12-Month Trend Table (Meskerem to Pagume, months 1 to 13 of selected Ethiopian Year)
        $monthlyTrend = [];
        $runningOpeningCredit = 0.0;
        for ($m = 1; $m <= 13; $m++) {
            $mRange = EthiopianDate::monthRange($selectedEthYear, $m);
            $mInc = (float)\App\Models\IncomeRecord::where(function ($iq) use ($mRange) {
                $iq->whereBetween('income_date', [$mRange['start_date'], $mRange['end_date']])
                   ->orWhere(function ($sub) use ($mRange) {
                       $sub->whereNull('income_date')->whereBetween('receipt_date', [$mRange['start_date'], $mRange['end_date']]);
                   });
            })->sum('amount');
            $mOut = round($mInc - ($mInc / 1.15), 2);

            $mExp = ExpenseRequest::where('status', ExpenseRequest::STATUS_PAID)
                ->whereBetween('created_at', [$mRange['start'], $mRange['end']])
                ->where('vat_type', '!=', 'vat_b')
                ->get();
            $mExpIn = (float)$mExp->sum(function ($item) {
                if ((float)$item->vat_amount > 0 && ($item->vat_type ?? '') === 'exclusive') return (float)$item->vat_amount;
                $gross = (float)($item->gross_amount > 0 ? $item->gross_amount : $item->amount);
                if (($item->vat_type ?? '') === 'inclusive') return round($gross - ($gross / 1.15), 2);
                if (($item->vat_type ?? '') === 'exclusive') return round($gross * 0.15, 2);
                return 0.0;
            });
            $mOcrIn = (float)\App\Models\ReceiptItem::whereHas('receipt', fn($rq) => 
                $rq->whereBetween('receipt_date', [$mRange['start_date'], $mRange['end_date']])
            )->sum('vat_amount');
            $mIn = max($mExpIn, $mOcrIn);

            $mNetCalc = EthiopianDate::calculateNetVat($mIn, $mOut, $runningOpeningCredit);

            $monthlyTrend[$m] = [
                'month'                => $m,
                'month_name'           => EthiopianDate::MONTHS_EN[$m],
                'month_am'             => EthiopianDate::MONTHS_AM[$m],
                'start_date'           => $mRange['start_date'],
                'end_date'             => $mRange['end_date'],
                'output_vat'           => $mOut,
                'input_vat'            => $mIn,
                'opening_credit'       => $runningOpeningCredit,
                'net'                  => $mNetCalc['net'],
                'signed_net_formatted' => $mNetCalc['signed_net_formatted'],
                'status'               => $mNetCalc['status'],
                'label'                => $mNetCalc['label'],
                'badge_class'          => $mNetCalc['badge_class'],
                'text_class'           => $mNetCalc['text_class'],
                'is_payable'           => $mNetCalc['is_payable'],
                'is_credit'            => $mNetCalc['is_credit'],
                'is_current'           => ($m === $selectedEthMonth),
            ];

            $runningOpeningCredit = $mNetCalc['credit_carry_forward'];
        }

        $availableEthYears  = EthiopianDate::getYears();
        $availableEthMonths = EthiopianDate::getMonths();

        $ocrProjects = \App\Models\Project::orderBy('name')->get();
        $ocrCategories = \App\Models\Receipt::CATEGORIES;

        // Available Bank Accounts & Chart of Accounts
        $bankAccounts = BankAccount::with(['assignedStaff', 'coa.manager'])->orderBy('bank_name')->get();
        $chartOfAccounts = ChartOfAccount::with('manager')->where('is_active', true)->orderBy('code')->get();

        // Available Finance Staff for Assignment
        $financeStaff = User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['Finance staff', 'finance_staff', 'Finance head', 'finance_head', 'cashier', 'accountant', 'admin', 'global_admin']);
        })->orWhereHas('employee', function($q) {
            $q->where('department', 'like', '%Finance%');
        })->orderBy('name')->get();

        $custodianIds = $chartOfAccounts->pluck('assigned_to')
            ->concat($bankAccounts->pluck('assigned_to'))
            ->concat($bankAccounts->map(fn($b) => $b->coa?->assigned_to))
            ->filter()->unique();

        if ($custodianIds->isNotEmpty()) {
            $custodians = User::whereIn('id', $custodianIds)->get();
            $financeStaff = $financeStaff->concat($custodians)->unique('id')->sortBy('name')->values();
        }

        if ($financeStaff->isEmpty()) {
            $financeStaff = User::where('is_active', true)->orderBy('name')->get();
        }

        return view('finance.tax-deductions.index', compact(
            'records',
            'tab',
            'cycle',
            'totalRecords',
            'totalGrossBase',
            'totalVatAmount',
            'totalWithholdingAmount',
            'totalNetDisbursed',
            'totalWhtTransactions',
            'slipsAttachedCount',
            'missingSlipsCount',
            'unsettledCount',
            'unsettledVatCount',
            'unsettledVatBaseAmount',
            'unsettledVatAmount',
            'unsettledWhtCount',
            'unsettledWhtBaseAmount',
            'unsettledWhtAmount',
            'unsettledTotalTax',
            'pendingSettlements',
            'settlements',
            'bankAccounts',
            'chartOfAccounts',
            'financeStaff',
            'ocrItems',
            'ocrStats',
            'ocrProjects',
            'ocrCategories',
            'vatSource',
            'selectedEthYear',
            'selectedEthMonth',
            'monthRange',
            'availableEthYears',
            'availableEthMonths',
            'salesBase',
            'outputVat',
            'purchasesBase',
            'inputVat',
            'openingCredit',
            'signedNetVat',
            'netVatDetails',
            'monthWithholding',
            'monthNetDisbursed',
            'monthlyTrend'
        ));
    }

    /**
     * Initiate a Tax Payment / Remittance for VAT or Withholding (Finance Head assigns Finance Staff or pays immediately).
     */
    public function initiateSettlement(Request $request)
    {
        $this->ensureSchema();

        $validated = $request->validate([
            'tax_type'                  => 'required|in:both,vat,withholding',
            'assigned_finance_staff_id' => 'nullable|exists:users,id',
            'account_source'            => 'nullable|string',
            'bank_account_id'           => 'nullable|exists:bank_accounts,id',
            'coa_id'                    => 'nullable|exists:chart_of_accounts,id',
            'pay_now'                   => 'nullable|boolean',
            'payment_reference'         => 'nullable|string|max:100',
            'payment_date'              => 'nullable|date',
            'attachment'                => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'payment_notes'             => 'nullable|string|max:1000',
        ]);

        $taxType = $validated['tax_type'];
        $payNow  = $request->boolean('pay_now');

        // Resolve funding bank/coa
        $bankAccountId = $validated['bank_account_id'] ?? null;
        $coaId         = $validated['coa_id'] ?? null;

        if (!empty($validated['account_source'])) {
            $parts = explode(':', $validated['account_source']);
            if (count($parts) === 2) {
                if ($parts[0] === 'bank') {
                    $bankAccountId = (int)$parts[1];
                    $bank = BankAccount::find($bankAccountId);
                    $coaId = $bank?->coa_id;
                } elseif ($parts[0] === 'coa') {
                    $coaId = (int)$parts[1];
                }
            }
        }

        // Query unsettled records based on the specific tax_type selected
        $query = ExpenseRequest::where('status', ExpenseRequest::STATUS_PAID);

        if ($taxType === 'vat') {
            $query->where('vat_settled', false)
                  ->whereNull('vat_settlement_id')
                  ->where(function ($q) {
                      $q->where('vat_amount', '>', 0)
                        ->orWhereIn('vat_type', ['exclusive', 'inclusive', 'vat_b']);
                  });
        } elseif ($taxType === 'withholding') {
            $query->where('withholding_settled', false)
                  ->whereNull('withholding_settlement_id')
                  ->where(function ($q) {
                      $q->where('has_withholding', true)
                        ->orWhere('withholding_amount', '>', 0);
                  });
        } else {
            $query->where(function ($q) {
                $q->where(function ($sub) {
                    $sub->where('vat_settled', false)->whereNull('vat_settlement_id')
                        ->where(function ($v) { $v->where('vat_amount', '>', 0)->orWhereIn('vat_type', ['exclusive', 'inclusive', 'vat_b']); });
                })->orWhere(function ($sub) {
                    $sub->where('withholding_settled', false)->whereNull('withholding_settlement_id')
                        ->where(function ($w) { $w->where('has_withholding', true)->orWhere('withholding_amount', '>', 0); });
                });
            });
        }

        $records = $query->orderBy('created_at')->get();

        if ($records->isEmpty()) {
            $taxName = $taxType === 'vat' ? 'VAT' : ($taxType === 'withholding' ? '3% Withholding Tax' : 'tax');
            return back()->with('error', "No unsettled {$taxName} records found to pay in this cycle.");
        }

        // Calculate amounts
        $totalBase = $records->sum(fn($i) => (float)($i->gross_amount > 0 ? $i->gross_amount : $i->amount));
        $vatSum = $records->sum(function ($item) {
            if ($item->vat_settled) return 0.0;
            if ((float)$item->vat_amount > 0) return (float)$item->vat_amount;
            $gross = (float)($item->gross_amount > 0 ? $item->gross_amount : $item->amount);
            $vatType = $item->vat_type ?? 'none';
            $vatRate = (float)($item->vat_rate ?? 15.00);
            if (in_array($vatType, ['inclusive', 'vat_b'])) {
                $base = round($gross / (1 + ($vatRate / 100)), 2);
                return round($gross - $base, 2);
            } elseif ($vatType === 'exclusive') {
                return round($gross * ($vatRate / 100), 2);
            }
            return 0.0;
        });

        $whtSum = $records->sum(function ($item) {
            if ($item->withholding_settled) return 0.0;
            return (float)$item->calculated_withholding_amount;
        });

        $totalPayable = 0.0;
        if ($taxType === 'vat') {
            $totalPayable = $vatSum;
            $whtSum = 0.0;
        } elseif ($taxType === 'withholding') {
            $totalPayable = $whtSum;
            $vatSum = 0.0;
        } else {
            $totalPayable = round($vatSum + $whtSum, 2);
        }

        // Resolve assigned staff
        $assignedStaffId = $validated['assigned_finance_staff_id'] ?? null;
        if (!$assignedStaffId) {
            if ($bankAccountId) {
                $bank = BankAccount::find($bankAccountId);
                $assignedStaffId = $bank?->assigned_to;
            }
            if (!$assignedStaffId && $coaId) {
                $coa = ChartOfAccount::find($coaId);
                $assignedStaffId = $coa?->assigned_to;
            }
            if (!$assignedStaffId) {
                $assignedStaffId = Auth::id();
            }
        }

        $periodFrom = $records->min('created_at');
        $periodTo   = $records->max('created_at');

        return DB::transaction(function () use (
            $records, $taxType, $totalBase, $vatSum, $whtSum, $totalPayable,
            $periodFrom, $periodTo, $assignedStaffId, $bankAccountId, $coaId,
            $payNow, $request, $validated
        ) {
            $prefix = $taxType === 'vat' ? 'TAX-VAT-' : ($taxType === 'withholding' ? 'TAX-WHT-' : 'TAX-REM-');
            $settlementCount = TaxSettlement::whereYear('created_at', date('Y'))->whereMonth('created_at', date('m'))->count() + 1;
            $settlementNumber = $prefix . date('Ym') . '-' . str_pad($settlementCount, 3, '0', STR_PAD_LEFT);
            while (TaxSettlement::where('settlement_number', $settlementNumber)->exists()) {
                $settlementCount++;
                $settlementNumber = $prefix . date('Ym') . '-' . str_pad($settlementCount, 3, '0', STR_PAD_LEFT);
            }

            $attachmentPath = null;
            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $filename = 'tax_slip_' . time() . '_' . Str::random(6) . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('uploads/tax_receipts'), $filename);
                $attachmentPath = 'uploads/tax_receipts/' . $filename;
            }

            $settlement = TaxSettlement::create([
                'settlement_number'         => $settlementNumber,
                'tax_type'                  => $taxType,
                'period_from'               => $periodFrom,
                'period_to'                 => $periodTo,
                'total_base_amount'         => $totalBase,
                'vat_amount'                => $vatSum,
                'withholding_amount'        => $whtSum,
                'total_tax_paid'            => $totalPayable,
                'records_count'             => $records->count(),
                'status'                    => $payNow ? TaxSettlement::STATUS_PAID : TaxSettlement::STATUS_PENDING,
                'finance_head_id'           => Auth::id(),
                'assigned_finance_staff_id' => $assignedStaffId,
                'paid_by'                   => $payNow ? Auth::id() : null,
                'paid_at'                   => $payNow ? Carbon::parse($validated['payment_date'] ?? now()) : null,
                'payment_method'            => $validated['payment_method'] ?? 'bank_transfer',
                'bank_account_id'           => $bankAccountId,
                'coa_id'                    => $coaId,
                'payment_reference'         => $validated['payment_reference'] ?? ($payNow ? 'TAX-PAID-' . date('Ymd') : null),
                'attachment'                => $attachmentPath,
                'payment_notes'             => $validated['payment_notes'] ?? null,
            ]);

            // Link matching expense requests based on the specific tax_type
            $recordIds = $records->pluck('id');
            if ($taxType === 'vat') {
                $updatePayload = ['vat_settlement_id' => $settlement->id];
                if ($payNow) {
                    $updatePayload['vat_settled'] = true;
                    $updatePayload['vat_settled_at'] = now();
                    $updatePayload['tax_settled_at'] = now();
                }
                ExpenseRequest::whereIn('id', $recordIds)->update($updatePayload);
            } elseif ($taxType === 'withholding') {
                $updatePayload = ['withholding_settlement_id' => $settlement->id];
                if ($payNow) {
                    $updatePayload['withholding_settled'] = true;
                    $updatePayload['withholding_settled_at'] = now();
                    $updatePayload['tax_settled_at'] = now();
                }
                ExpenseRequest::whereIn('id', $recordIds)->update($updatePayload);
            } else {
                $updatePayload = [
                    'tax_settlement_id'         => $settlement->id,
                    'vat_settlement_id'         => $settlement->id,
                    'withholding_settlement_id' => $settlement->id,
                ];
                if ($payNow) {
                    $updatePayload['vat_settled']            = true;
                    $updatePayload['withholding_settled']    = true;
                    $updatePayload['vat_settled_at']         = now();
                    $updatePayload['withholding_settled_at'] = now();
                    $updatePayload['tax_settled_at']         = now();
                }
                ExpenseRequest::whereIn('id', $recordIds)->update($updatePayload);
            }

            // If Paid Now with Bank Account, decrement balance & record transaction
            if ($payNow && $bankAccountId && $totalPayable > 0) {
                $bankAccount = BankAccount::find($bankAccountId);
                if ($bankAccount) {
                    $bankAccount->decrement('current_balance', $totalPayable);
                    $newBalance = $bankAccount->fresh()->current_balance;

                    $taxDesc = match($taxType) {
                        'vat' => "VAT Payment (15%) to ERCA: ETB {$totalPayable}",
                        'withholding' => "3% Withholding Tax Remittance to ERCA: ETB {$totalPayable}",
                        default => "Tax Settlement (VAT & WHT) to ERCA: ETB {$totalPayable}"
                    };

                    BankTransaction::create([
                        'bank_account_id'  => $bankAccount->id,
                        'transaction_date' => ($validated['payment_date'] ?? now()->toDateString()),
                        'type'             => 'withdrawal',
                        'amount'           => $totalPayable,
                        'balance_after'    => $newBalance,
                        'reference_no'     => $settlement->payment_reference ?? $settlementNumber,
                        'reference_type'   => 'TaxSettlement',
                        'reference_id'     => $settlement->id,
                        'description'      => "{$taxDesc} (Settlement #{$settlementNumber})",
                        'is_reconciled'    => true,
                    ]);
                }
            }

            $taxLabel = $taxType === 'vat' ? 'VAT (15%)' : ($taxType === 'withholding' ? '3% Withholding Tax' : 'VAT & Withholding Tax');
            if ($payNow) {
                return redirect()->route('finance.tax-deductions.index', ['cycle' => 'active', 'tab' => $taxType === 'vat' ? 'vat' : ($taxType === 'withholding' ? 'withholding' : 'all')])
                    ->with('success', "{$taxLabel} Payment #{$settlementNumber} for ETB " . number_format($totalPayable, 2) . " processed! Active balance has reset to ETB 0.00 and starts from zero.");
            } else {
                $staffName = $settlement->assignedStaff?->name ?? 'Finance Staff';
                return redirect()->route('finance.tax-deductions.index', ['cycle' => 'active', 'tab' => $taxType === 'vat' ? 'vat' : ($taxType === 'withholding' ? 'withholding' : 'all')])
                    ->with('success', "{$taxLabel} Payment Request #{$settlementNumber} for ETB " . number_format($totalPayable, 2) . " created and assigned to {$staffName}. Once they submit the payment slip, the balance resets to zero.");
            }
        });
    }

    /**
     * Assigned Finance Staff records the actual payment and uploads receipt slip to complete the settlement.
     */
    public function recordPayment(Request $request, TaxSettlement $settlement)
    {
        $this->ensureSchema();

        if ($settlement->status === TaxSettlement::STATUS_PAID) {
            return back()->with('error', 'This tax payment has already been marked as paid.');
        }

        $validated = $request->validate([
            'payment_reference' => 'required|string|max:100',
            'payment_date'      => 'required|date',
            'payment_method'    => 'nullable|string|max:50',
            'account_source'    => 'nullable|string',
            'bank_account_id'   => 'nullable|exists:bank_accounts,id',
            'coa_id'            => 'nullable|exists:chart_of_accounts,id',
            'attachment'        => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'payment_notes'     => 'nullable|string|max:1000',
        ]);

        $bankAccountId = $validated['bank_account_id'] ?? $settlement->bank_account_id;
        $coaId         = $validated['coa_id'] ?? $settlement->coa_id;

        if (!empty($validated['account_source'])) {
            $parts = explode(':', $validated['account_source']);
            if (count($parts) === 2) {
                if ($parts[0] === 'bank') {
                    $bankAccountId = (int)$parts[1];
                    $bank = BankAccount::find($bankAccountId);
                    $coaId = $bank?->coa_id;
                } elseif ($parts[0] === 'coa') {
                    $coaId = (int)$parts[1];
                }
            }
        }

        return DB::transaction(function () use ($request, $validated, $settlement, $bankAccountId, $coaId) {
            $attachmentPath = null;
            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $filename = 'tax_slip_' . time() . '_' . Str::random(6) . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('uploads/tax_receipts'), $filename);
                $attachmentPath = 'uploads/tax_receipts/' . $filename;
            }

            $settlement->update([
                'status'            => TaxSettlement::STATUS_PAID,
                'paid_by'           => Auth::id(),
                'paid_at'           => Carbon::parse($validated['payment_date']),
                'payment_reference' => $validated['payment_reference'],
                'payment_method'    => $validated['payment_method'] ?? 'bank_transfer',
                'bank_account_id'   => $bankAccountId,
                'coa_id'            => $coaId,
                'attachment'        => $attachmentPath,
                'payment_notes'     => $validated['payment_notes'] ?? null,
            ]);

            // Mark linked expense requests as settled based on tax_type
            $taxType = $settlement->tax_type;
            if ($taxType === 'vat') {
                ExpenseRequest::where('vat_settlement_id', $settlement->id)->update([
                    'vat_settled'    => true,
                    'vat_settled_at' => now(),
                    'tax_settled_at' => now(),
                ]);
            } elseif ($taxType === 'withholding') {
                ExpenseRequest::where('withholding_settlement_id', $settlement->id)->update([
                    'withholding_settled'    => true,
                    'withholding_settled_at' => now(),
                    'tax_settled_at'         => now(),
                ]);
            } else {
                ExpenseRequest::where('tax_settlement_id', $settlement->id)->update([
                    'vat_settled'            => true,
                    'withholding_settled'    => true,
                    'vat_settled_at'         => now(),
                    'withholding_settled_at' => now(),
                    'tax_settled_at'         => now(),
                ]);
            }

            // Deduct from bank account if applicable
            if ($bankAccountId && $settlement->total_tax_paid > 0) {
                $bankAccount = BankAccount::find($bankAccountId);
                if ($bankAccount) {
                    $bankAccount->decrement('current_balance', $settlement->total_tax_paid);
                    $newBalance = $bankAccount->fresh()->current_balance;

                    $taxDesc = match($taxType) {
                        'vat' => "VAT Payment (15%) to ERCA: ETB {$settlement->total_tax_paid}",
                        'withholding' => "3% Withholding Tax Remittance to ERCA: ETB {$settlement->total_tax_paid}",
                        default => "Tax Settlement to ERCA: ETB {$settlement->total_tax_paid}"
                    };

                    BankTransaction::create([
                        'bank_account_id'  => $bankAccount->id,
                        'transaction_date' => Carbon::parse($validated['payment_date'])->toDateString(),
                        'type'             => 'withdrawal',
                        'amount'           => $settlement->total_tax_paid,
                        'balance_after'    => $newBalance,
                        'reference_no'     => $validated['payment_reference'],
                        'reference_type'   => 'TaxSettlement',
                        'reference_id'     => $settlement->id,
                        'description'      => "{$taxDesc} (Settlement #{$settlement->settlement_number})",
                        'is_reconciled'    => true,
                    ]);
                }
            }

            $taxLabel = $taxType === 'vat' ? 'VAT (15%)' : ($taxType === 'withholding' ? '3% Withholding Tax' : 'Tax');
            return redirect()->route('finance.tax-deductions.index', ['cycle' => 'active', 'tab' => $taxType === 'vat' ? 'vat' : ($taxType === 'withholding' ? 'withholding' : 'all')])
                ->with('success', "Payment for {$taxLabel} Settlement #{$settlement->settlement_number} (ETB " . number_format($settlement->total_tax_paid, 2) . ") confirmed and uploaded! Active {$taxLabel} balance has reset to ETB 0.00.");
        });
    }

    /**
     * Cancel a pending tax settlement and release locked expense requests back to active pool.
     */
    public function cancelSettlement(TaxSettlement $settlement)
    {
        $this->ensureSchema();

        if ($settlement->status === TaxSettlement::STATUS_PAID) {
            return back()->with('error', 'Cannot cancel an already completed tax payment.');
        }

        DB::transaction(function () use ($settlement) {
            $taxType = $settlement->tax_type;
            if ($taxType === 'vat') {
                ExpenseRequest::where('vat_settlement_id', $settlement->id)
                    ->where('vat_settled', false)
                    ->update(['vat_settlement_id' => null]);
            } elseif ($taxType === 'withholding') {
                ExpenseRequest::where('withholding_settlement_id', $settlement->id)
                    ->where('withholding_settled', false)
                    ->update(['withholding_settlement_id' => null]);
            } else {
                ExpenseRequest::where('tax_settlement_id', $settlement->id)
                    ->where('vat_settled', false)
                    ->where('withholding_settled', false)
                    ->update([
                        'tax_settlement_id'         => null,
                        'vat_settlement_id'         => null,
                        'withholding_settlement_id' => null,
                    ]);
            }

            $settlement->update(['status' => TaxSettlement::STATUS_CANCELLED]);
        });

        return back()->with('success', "Tax Payment Request #{$settlement->settlement_number} cancelled. Records released back to active pool.");
    }

    /**
     * Export VAT and Withholding Tax deductions ledger to CSV.
     */
    public function exportCsv(Request $request)
    {
        $this->ensureSchema();

        $tab            = $request->input('tab', 'all');
        $cycle          = $request->input('cycle', 'active');
        $search         = $request->input('search');
        $category       = $request->input('category');
        $fromDate       = $request->input('from_date') ?: ($request->input('date_from') ?: $request->input('start_date'));
        $toDate         = $request->input('to_date') ?: ($request->input('date_to') ?: $request->input('end_date'));
        $singleDate     = $request->input('date') ?: $request->input('single_date');

        if (!empty($singleDate)) {
            $fromDate = $fromDate ?: $singleDate;
            $toDate   = $toDate ?: $singleDate;
        }

        // If exporting VAT tab and not specifically requested as internal expense requests, delegate to OCR CSV
        if ($tab === 'vat' && $request->input('vat_source') !== 'expense') {
            return app(\App\Http\Controllers\Admin\OCRReceiptScannerController::class)->exportCsv($request);
        }

        // Base Query: Strictly PAID records that have VAT, Withholding Tax, or an uploaded Withholding slip
        $query = ExpenseRequest::with(['user', 'employee', 'paidBy', 'bankAccount', 'chartOfAccount', 'letter', 'purchaseRequest'])
            ->where('status', ExpenseRequest::STATUS_PAID)
            ->where(function ($q) {
                $q->where('has_withholding', true)
                  ->orWhere('withholding_amount', '>', 0)
                  ->orWhere('vat_amount', '>', 0)
                  ->orWhereIn('vat_type', ['exclusive', 'inclusive', 'vat_b'])
                  ->orWhere(function ($sq) {
                      $sq->whereNotNull('withholding_receipt')->where('withholding_receipt', '!=', '');
                  });
            });

        if ($cycle === 'active') {
            if ($tab === 'vat') {
                $query->where('vat_settled', false)->whereNull('vat_settlement_id');
            } elseif ($tab === 'withholding' || $tab === 'slips') {
                $query->where('withholding_settled', false)->whereNull('withholding_settlement_id');
            } else {
                $query->where(function ($q) {
                    $q->where(function ($sub) {
                        $sub->where('vat_settled', false)->whereNull('vat_settlement_id')
                            ->where(function ($v) { $v->where('vat_amount', '>', 0)->orWhereIn('vat_type', ['exclusive', 'inclusive', 'vat_b']); });
                    })->orWhere(function ($sub) {
                        $sub->where('withholding_settled', false)->whereNull('withholding_settlement_id')
                            ->where(function ($w) { $w->where('has_withholding', true)->orWhere('withholding_amount', '>', 0); });
                    });
                });
            }
        } elseif ($cycle === 'settled') {
            if ($tab === 'vat') {
                $query->where('vat_settled', true);
            } elseif ($tab === 'withholding' || $tab === 'slips') {
                $query->where('withholding_settled', true);
            } else {
                $query->where(function ($q) {
                    $q->where('vat_settled', true)->orWhere('withholding_settled', true);
                });
            }
        }

        if ($tab === 'withholding') {
            $query->where(function ($q) {
                $q->where('has_withholding', true)
                  ->orWhere('withholding_amount', '>', 0);
            });
        } elseif ($tab === 'vat') {
            $query->where(function ($q) {
                $q->where('vat_amount', '>', 0)
                  ->orWhereIn('vat_type', ['exclusive', 'inclusive', 'vat_b']);
            });
        } elseif ($tab === 'slips') {
            $query->whereNotNull('withholding_receipt')->where('withholding_receipt', '!=', '');
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('request_number', 'like', "%{$search}%")
                  ->orWhere('payment_reference', 'like', "%{$search}%")
                  ->orWhere('withholding_receipt_number', 'like', "%{$search}%");
            });
        }

        if (!empty($category)) {
            $query->where('category', $category);
        }

        // Ethiopian month selector for export
        $curEth = EthiopianDate::toEthiopian(now());
        $selectedEthYear  = (int)($request->input('ethiopian_year') ?: ($request->input('year') ?: $curEth['year']));
        $selectedEthMonth = (int)($request->input('ethiopian_month') ?: ($request->input('month') ?: $curEth['month']));

        if ($selectedEthMonth >= 1 && $selectedEthMonth <= 13) {
            $mRange = EthiopianDate::monthRange($selectedEthYear, $selectedEthMonth);
            $query->whereBetween('created_at', [$mRange['start'], $mRange['end']]);
        } elseif (!empty($fromDate) && !empty($toDate)) {
            $query->whereDate('created_at', '>=', Carbon::parse($fromDate))
                  ->whereDate('created_at', '<=', Carbon::parse($toDate));
        }

        $records = $query->latest()->get();

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="tax_deductions_report_' . date('Ymd_His') . '.csv"',
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        return response()->stream(function () use ($records) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                'Request #',
                'Date (EC)',
                'Beneficiary / Requester',
                'Category',
                'Description',
                'Gross / Base Amount (ETB)',
                'VAT Type',
                'VAT Rate (%)',
                'VAT Amount (ETB)',
                'VAT Settled',
                'Withholding Tax (3% WHT ETB)',
                'WHT Settled',
                'Net Disbursed / Paid (ETB)',
                'Payment Reference',
                'Paid At (EC)',
                'Paying Account',
                'WHT Receipt Serial #',
                'WHT Receipt Attached',
                'Status',
            ]);

            foreach ($records as $item) {
                $gross = (float)($item->gross_amount > 0 ? $item->gross_amount : $item->amount);
                $wht = (float)$item->calculated_withholding_amount;
                $net = (float)$item->effective_payable_amount;
                $vatLabel = match ($item->vat_type) {
                    'exclusive' => '15% VAT Added',
                    'inclusive' => '15% VAT Included',
                    'vat_b'     => '15% VAT B (Non-creditable)',
                    default     => 'No VAT (0%)',
                };

                $dateEc = $item->created_at ? EthiopianDate::formatDateEc($item->created_at, 'DD/MM/YYYY EC') : 'N/A';
                $paidAtEc = $item->paid_at ? EthiopianDate::formatDateEc($item->paid_at, 'DD/MM/YYYY EC') : 'Pending';

                fputcsv($handle, [
                    $item->request_number,
                    $dateEc,
                    $item->user->name ?? 'N/A',
                    $item->category,
                    $item->description,
                    number_format($gross, 2, '.', ''),
                    $vatLabel,
                    number_format((float)($item->vat_rate ?? 15.00), 2, '.', ''),
                    number_format((float)($item->vat_amount ?? 0), 2, '.', ''),
                    $item->vat_settled ? 'YES' : 'NO',
                    number_format($wht, 2, '.', ''),
                    $item->withholding_settled ? 'YES' : 'NO',
                    number_format($net, 2, '.', ''),
                    $item->payment_reference ?? 'N/A',
                    $paidAtEc,
                    $item->chartOfAccount->name ?? ($item->bankAccount->bank_name ?? 'Default Petty Cash'),
                    $item->withholding_receipt_number ?? 'N/A',
                    !empty($item->withholding_receipt) ? 'YES' : 'NO',
                    $item->status,
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * AJAX OCR Receipt Extraction for VAT & Tax Deductions Ledger.
     * Takes an uploaded receipt image or PDF and extracts vendor, TIN, FS#, MRC#, amount, VAT, and date.
     */
    public function scanReceipt(Request $request)
    {
        $this->ensureSchema();

        $request->validate([
            'receipt_file' => 'required|file|mimes:jpeg,jpg,png,webp,pdf|max:25600',
        ]);

        try {
            $file     = $request->file('receipt_file');
            $ext      = strtolower($file->getClientOriginalExtension());
            $filename = 'tax_rcpt_' . now()->format('Ymd_His') . '_' . uniqid() . '.' . $ext;
            $path     = $file->storeAs('receipts', $filename, 'public');
            $fileUrl  = asset('storage/' . $path);

            $ocrService = app(ReceiptOcrService::class);
            $scan       = $ocrService->scan($file->getRealPath());

            // Extract normalized FS No
            $rawFs  = trim((string)($scan['fs_no'] ?? ''));
            $fsNorm = QcService::normalizeFsNo($rawFs);
            $fsNo   = $fsNorm['value'] ?: $rawFs;
            if (empty($fsNo) && !empty($scan['extracted']['receipt_no'])) {
                $fsNo = $scan['extracted']['receipt_no'];
            }

            // Extract normalized Supplier TIN
            $rawTin      = trim((string)($scan['supplier_tin'] ?? ($scan['extracted']['supplier_tin'] ?? ($scan['extracted']['merchant_tin'] ?? ''))));
            $tinNorm     = QcService::normalizeTin($rawTin);
            $supplierTin = $tinNorm['value'] ?: $rawTin;

            $vendorName  = trim((string)($scan['vendor_name'] ?? ($scan['extracted']['merchant_name'] ?? '')));
            $mrcNo       = trim((string)($scan['mrc_no'] ?? ($scan['extracted']['machine_no'] ?? ($scan['extracted']['mrc_no'] ?? ''))));
            $items       = $scan['items'] ?? ($scan['extracted']['items'] ?? []);
            $totalAmount = QcService::parseMoney($scan['total_amount'] ?? ($scan['extracted']['total_amount'] ?? ($scan['extracted']['total'] ?? ($scan['extracted']['grand_total'] ?? 0))));
            $subtotal    = QcService::parseMoney($scan['subtotal'] ?? ($scan['extracted']['subtotal'] ?? ($scan['extracted']['taxable_amount'] ?? ($scan['extracted']['net_amount'] ?? 0))));
            $vatAmount   = QcService::parseMoney($scan['vat_amount'] ?? ($scan['extracted']['vat_amount'] ?? ($scan['extracted']['vat'] ?? ($scan['extracted']['tax'] ?? 0))));

            // If amounts are missing, aggregate from items array
            if ($totalAmount == 0 && $subtotal == 0 && !empty($items)) {
                foreach ($items as $it) {
                    $itemVal = QcService::parseMoney($it['total_value'] ?? ((float)($it['qty'] ?? 1) * (float)($it['unit_price'] ?? 0)));
                    $itemVat = QcService::parseMoney($it['vat'] ?? round($itemVal * 0.15, 2));
                    $itemAfter = QcService::parseMoney($it['value_after_vat'] ?? ($itemVal + $itemVat));
                    $subtotal += $itemVal;
                    $vatAmount += $itemVat;
                    $totalAmount += $itemAfter;
                }
            }

            // Cross-calculate and reconcile Subtotal, VAT, and Total with Ethiopian 15% VAT standard
            if ($totalAmount > 0 && $subtotal > 0 && abs($totalAmount - $subtotal) < 0.02 && $vatAmount == 0) {
                // If OCR set subtotal equal to total_amount, calculate base and 15% VAT
                $subtotal  = round($totalAmount / 1.15, 2);
                $vatAmount = round($totalAmount - $subtotal, 2);
            } elseif ($subtotal == 0 && $totalAmount > 0) {
                if ($vatAmount > 0) {
                    $subtotal = round($totalAmount - $vatAmount, 2);
                } else {
                    $subtotal = round($totalAmount / 1.15, 2);
                    $vatAmount = round($totalAmount - $subtotal, 2);
                }
            } elseif ($totalAmount == 0 && $subtotal > 0) {
                if ($vatAmount > 0) {
                    $totalAmount = round($subtotal + $vatAmount, 2);
                } else {
                    $vatAmount = round($subtotal * 0.15, 2);
                    $totalAmount = round($subtotal + $vatAmount, 2);
                }
            } elseif ($vatAmount == 0 && $subtotal > 0 && $totalAmount > $subtotal) {
                $vatAmount = round($totalAmount - $subtotal, 2);
            } elseif ($vatAmount > 0 && $totalAmount == 0 && $subtotal == 0) {
                $subtotal = round($vatAmount / 0.15, 2);
                $totalAmount = round($subtotal + $vatAmount, 2);
            }

            // Detect ERCA Purchase Type (PUR_GD vs PUR_SR) using centralized QcService
            $purcNorm = QcService::normalizePurchaseType(
                $scan['purchase_type'] ?? ($scan['extracted']['purchase_type'] ?? ($scan['vat_category'] ?? null)),
                $vendorName . ' ' . ($scan['description'] ?? '') . ' ' . implode(' ', array_column($items, 'item_description'))
            );
            $purchaseType = $purcNorm['type'];

            $rawDate     = $scan['receipt_date_ymd'] ?? ($scan['extracted']['receipt_date'] ?? null);
            $receiptDate = date('Y-m-d');
            if (!empty($rawDate)) {
                try {
                    $receiptDate = Carbon::parse($rawDate)->format('Y-m-d');
                } catch (\Throwable $e) {}
            }

            $buyerTin = trim((string)($scan['buyer_tin'] ?? '38480010'));
            if (empty($buyerTin) || $buyerTin === '0038480010') {
                $buyerTin = '38480010';
            }

            return response()->json([
                'success'           => true,
                'file_path'         => $path,
                'file_url'          => $fileUrl,
                'receipt_date'      => $receiptDate,
                'fs_no'             => $fsNo,
                'mrc_no'            => $mrcNo,
                'vendor_tin'        => $supplierTin,
                'vendor_name'       => $vendorName ?: 'General Merchant',
                'buyer_tin'         => $buyerTin,
                'purchase_type'     => $purchaseType,
                'total_amount'      => $totalAmount,
                'subtotal'          => $subtotal,
                'vat_amount'        => $vatAmount,
                'withholding_amount'=> round($subtotal * 0.03, 2),
                'items'             => $items,
                'is_duplicate'      => $scan['is_duplicate'] ?? false,
                'duplicate_message' => $scan['duplicate_message'] ?? null,
                'confidence'        => $scan['confidence'] ?? 'high',
                'confidence_score'  => $scan['confidence_score'] ?? 90,
                'engine'            => $scan['engine'] ?? 'AI Vision OCR',
            ]);
        } catch (\Throwable $e) {
            Log::error('FinanceTaxReportController scanReceipt failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'OCR processing failed: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Store and create Receipt & Tax Deduction record for the specified receipt date.
     */
    public function storeReceipt(Request $request)
    {
        $this->ensureSchema();

        $validated = $request->validate([
            'receipt_date'       => 'required|date',
            'fs_no'              => 'nullable|string',
            'mrc_no'             => 'nullable|string',
            'vendor_name'        => 'nullable|string',
            'vendor_tin'         => 'nullable|string',
            'buyer_tin'          => 'nullable|string',
            'total_amount'       => 'required|numeric|min:0.01',
            'subtotal'           => 'nullable|numeric|min:0',
            'vat_amount'         => 'nullable|numeric|min:0',
            'has_withholding'    => 'nullable|boolean',
            'withholding_amount' => 'nullable|numeric|min:0',
            'tax_type'           => 'nullable|string',
            'project_id'         => 'nullable|exists:projects,id',
            'category'           => 'nullable|string',
            'description'        => 'nullable|string',
            'purchase_type'      => 'nullable|string',
            'file_path'          => 'nullable|string',
            'receipt_file'       => 'nullable|file|mimes:jpeg,jpg,png,webp,pdf|max:25600',
        ]);

        try {
            $filePath = $validated['file_path'] ?? null;
            if ($request->hasFile('receipt_file')) {
                $file     = $request->file('receipt_file');
                $ext      = strtolower($file->getClientOriginalExtension());
                $filename = 'tax_rcpt_' . now()->format('Ymd_His') . '_' . uniqid() . '.' . $ext;
                $filePath = $file->storeAs('receipts', $filename, 'public');
            }

            $targetDateStr = $validated['receipt_date'];
            if (!empty($request->input('receipt_date_ec'))) {
                $parsed = EthiopianDate::parseEcToGregorian($request->input('receipt_date_ec'));
                if ($parsed) {
                    $targetDateStr = $parsed;
                }
            } elseif (preg_match('/^\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4}$/', trim($validated['receipt_date']))) {
                $parsed = EthiopianDate::parseEcToGregorian($validated['receipt_date']);
                if ($parsed) {
                    $targetDateStr = $parsed;
                }
            }
            $targetDate = Carbon::parse($targetDateStr);
            $fsNoRaw    = trim($validated['fs_no'] ?? '');
            $fsNorm     = QcService::normalizeFsNo($fsNoRaw);
            $fsNo       = $fsNorm['value'] ?: $fsNoRaw;

            $totalAmount = QcService::parseMoney($validated['total_amount']);
            $vatAmount   = QcService::parseMoney($validated['vat_amount'] ?? 0);
            $subtotal    = QcService::parseMoney($validated['subtotal'] ?? max(0, $totalAmount - $vatAmount));

            if ($vatAmount == 0 && ($validated['tax_type'] ?? 'vat') !== 'withholding') {
                $subtotal  = round($totalAmount / 1.15, 2);
                $vatAmount = round($totalAmount - $subtotal, 2);
            }

            $hasWithholding    = (bool)($validated['has_withholding'] ?? (($validated['tax_type'] ?? '') === 'withholding' || ($validated['tax_type'] ?? '') === 'both'));
            $withholdingAmount = QcService::parseMoney($validated['withholding_amount'] ?? ($hasWithholding ? round($subtotal * 0.03, 2) : 0));

            $ext = $filePath ? strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) : 'jpg';
            $isPdf = $ext === 'pdf';

            $receiptNumber = 'RCP-' . $targetDate->format('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

            $purcNorm = QcService::normalizePurchaseType(
                $validated['purchase_type'] ?? null,
                ($validated['description'] ?? '') . ' ' . ($validated['vendor_name'] ?? '')
            );
            $purcTypeCode = $purcNorm['code'];
            $vatCategory  = $purcNorm['vat_category'];

            // 1. Create Receipt (for ERCA Line 100 VAT declaration)
            $receipt = new Receipt([
                'receipt_number'   => $receiptNumber,
                'uploaded_by'      => Auth::id() ?: 1,
                'project_id'       => $validated['project_id'] ?? null,
                'vendor_name'      => $validated['vendor_name'] ?: 'General Merchant',
                'vendor_tin'       => $validated['vendor_tin'] ?? null,
                'tin_valid'        => true,
                'buyer_tin'        => $validated['buyer_tin'] ?: '38480010',
                'fs_no'            => $fsNo,
                'fs_no_raw'        => $fsNoRaw,
                'fs_no_valid'      => true,
                'mrc_no'           => $validated['mrc_no'] ?? null,
                'receipt_date'     => $targetDate->toDateString(),
                'subtotal'         => $subtotal,
                'vat_amount'       => $vatAmount,
                'total_amount'     => $totalAmount,
                'currency'         => 'ETB',
                'category'         => $validated['category'] ?? 'material',
                'description'      => $validated['description'] ?: 'Purchased Materials / VAT Invoice',
                'file_path'        => $filePath,
                'file_type'        => $isPdf ? 'pdf' : 'image',
                'status'           => 'approved',
                'approved_by'      => Auth::id() ?: 1,
                'approved_at'      => $targetDate->copy()->setTime(12, 0, 0),
                'created_at'       => $targetDate->copy()->setTime(12, 0, 0),
                'updated_at'       => $targetDate->copy()->setTime(12, 0, 0),
            ]);
            $receipt->save();

            // 2. Create ReceiptItem
            $receiptItem = new ReceiptItem([
                'receipt_id'       => $receipt->id,
                'item_description' => $validated['description'] ?: 'Purchased Materials',
                'vat_category'     => $vatCategory,
                'calendar_type'    => 'E',
                'purchase_type'    => $purcTypeCode,
                'uom'              => '9',
                'qty'              => 1.00,
                'unit_price'       => $subtotal,
                'total_value'      => $subtotal,
                'vat_amount'       => $vatAmount,
                'value_after_vat'  => $totalAmount,
                'is_flagged'       => false,
                'created_at'       => $targetDate->copy()->setTime(12, 0, 0),
                'updated_at'       => $targetDate->copy()->setTime(12, 0, 0),
            ]);
            $receiptItem->save();

            // 3. Create ExpenseRequest so it appears in the Tax Deductions Ledger on this date
            $taxExp = new ExpenseRequest();
            $taxExp->request_number    = 'EXP-TAX-' . $targetDate->format('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
            $taxExp->user_id           = Auth::id() ?: 1;
            $taxExp->project_id        = $validated['project_id'] ?? null;
            $taxExp->category          = ucfirst($validated['category'] ?? 'Material');
            $taxExp->description       = $validated['description'] ?: ($receipt->vendor_name . ' (FS: ' . $fsNo . ')');
            $taxExp->amount            = $totalAmount;
            $taxExp->gross_amount      = $totalAmount;
            $taxExp->vat_type          = $vatAmount > 0 ? 'exclusive' : 'none';
            $taxExp->vat_rate          = 15.00;
            $taxExp->vat_amount        = $vatAmount;
            $taxExp->has_withholding   = $hasWithholding;
            $taxExp->withholding_rate  = 3.00;
            $taxExp->withholding_amount= $withholdingAmount;
            $taxExp->net_amount        = max(0, $totalAmount - $withholdingAmount);
            $taxExp->payment_reference = 'FS: ' . $fsNo . ($receipt->mrc_no ? ' | MRC: ' . $receipt->mrc_no : '');
            $taxExp->attachment        = $filePath;
            $taxExp->status            = ExpenseRequest::STATUS_PAID;
            $taxExp->paid_by           = Auth::id() ?: 1;
            $taxExp->paid_at           = $targetDate->copy()->setTime(12, 0, 0);
            $taxExp->created_at        = $targetDate->copy()->setTime(12, 0, 0);
            $taxExp->updated_at        = $targetDate->copy()->setTime(12, 0, 0);
            $taxExp->save();

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success'    => true,
                    'message'    => "Receipt created successfully for date {$targetDate->format('d/m/Y')}!",
                    'receipt'    => $receipt,
                    'item'       => $receiptItem,
                    'expense_id' => $taxExp->id,
                ]);
            }

            return redirect()->route('finance.tax-deductions.index', ['tab' => 'vat'])
                ->with('success', "Receipt #{$fsNo} ({$receipt->vendor_name}) created successfully for date {$targetDate->format('M d, Y')}!");
        } catch (\Throwable $e) {
            Log::error('FinanceTaxReportController storeReceipt failed: ' . $e->getMessage());
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to save receipt: ' . $e->getMessage(),
                ], 422);
            }
            return back()->with('error', 'Failed to save receipt: ' . $e->getMessage())->withInput();
        }
    }
}
