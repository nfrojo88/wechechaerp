<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\ExpenseRequest;
use App\Models\TaxSettlement;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\ChartOfAccount;
use App\Models\IncomeRecord;
use App\Models\SystemSetting;
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
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Cell;

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

            if (Schema::hasTable('tax_settlements')) {
                Schema::table('tax_settlements', function (\Illuminate\Database\Schema\Blueprint $table) {
                    if (!Schema::hasColumn('tax_settlements', 'ethiopian_year')) {
                        $table->integer('ethiopian_year')->nullable()->index();
                    }
                    if (!Schema::hasColumn('tax_settlements', 'ethiopian_month')) {
                        $table->integer('ethiopian_month')->nullable()->index();
                    }
                });
            }

            if (Schema::hasTable('income_records')) {
                Schema::table('income_records', function (\Illuminate\Database\Schema\Blueprint $table) {
                    if (!Schema::hasColumn('income_records', 'vat_settled')) {
                        $table->boolean('vat_settled')->default(false)->index();
                    }
                    if (!Schema::hasColumn('income_records', 'vat_settlement_id')) {
                        $table->unsignedBigInteger('vat_settlement_id')->nullable()->index();
                    }
                    if (!Schema::hasColumn('income_records', 'tax_settled_at')) {
                        $table->timestamp('tax_settled_at')->nullable();
                    }
                });
            }

            // Sync/heal: If any IncomeRecord or ExpenseRequest was created AFTER a TaxSettlement was paid/created,
            // but was inadvertently linked or marked settled, restore it to active/unsettled!
            // When a month is cleared, subsequent new transactions must start adding up fresh from zero.
            if (Schema::hasTable('tax_settlements')) {
                $paidSettlements = TaxSettlement::where('status', TaxSettlement::STATUS_PAID)
                    ->whereIn('tax_type', ['vat', 'both'])
                    ->get();
                foreach ($paidSettlements as $st) {
                    $settleTime = $st->paid_at ?: $st->created_at;
                    if (!$settleTime) continue;

                    if (Schema::hasTable('income_records')) {
                        // Restore any income records created AFTER this settlement to active/unsettled
                        \App\Models\IncomeRecord::where('vat_settled', true)
                            ->where('created_at', '>', Carbon::parse($settleTime)->addSeconds(5))
                            ->where(function ($q) use ($st) {
                                $q->where('vat_settlement_id', $st->id)
                                  ->orWhereNull('vat_settlement_id');
                            })
                            ->update([
                                'vat_settled'       => false,
                                'vat_settlement_id' => null,
                                'tax_settled_at'    => null,
                            ]);
                    }

                    if (Schema::hasTable('expense_requests')) {
                        // Restore any expense requests created AFTER this settlement to active/unsettled
                        ExpenseRequest::where('created_at', '>', Carbon::parse($settleTime)->addSeconds(5))
                            ->where(function ($q) use ($st) {
                                $q->where('vat_settlement_id', $st->id)
                                  ->orWhere('withholding_settlement_id', $st->id)
                                  ->orWhere('tax_settlement_id', $st->id);
                            })
                            ->update([
                                'vat_settled'               => false,
                                'withholding_settled'       => false,
                                'vat_settlement_id'         => null,
                                'withholding_settlement_id' => null,
                                'tax_settlement_id'         => null,
                                'vat_settled_at'            => null,
                                'withholding_settled_at'    => null,
                                'tax_settled_at'            => null,
                            ]);
                    }
                }
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

        $totalWhtTransactions = $allTaxItems->filter(fn($item) => ($item->has_withholding || (float)$item->withholding_amount > 0) && ($cycle !== 'active' || !$item->withholding_settled))->count();
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

        // Self-heal any existing records where inclusive VAT gross was stored in total_value (e.g. 550.00 instead of 478.26)
        try {
            \App\Models\ReceiptItem::where('vat_amount', '>', 0)
                ->where('value_after_vat', '>', DB::raw('total_value'))
                ->limit(50)
                ->get()
                ->each(function ($item) {
                    $totalVal = (float)$item->total_value;
                    $vat = (float)$item->vat_amount;
                    if ($totalVal > 0 && $vat > 0) {
                        $calcBase = round($totalVal / 1.15, 2);
                        if (abs(($totalVal - $vat) - $calcBase) <= 0.05 && abs(round($calcBase * 0.15, 2) - $vat) <= 0.05) {
                            $item->total_value = $calcBase;
                            $qty = (float)$item->qty > 0 ? (float)$item->qty : 1.0;
                            $item->unit_price = round($calcBase / $qty, 2);
                            $item->value_after_vat = $totalVal;
                            $item->save();
                            if ($item->receipt_id) {
                                $r = \App\Models\Receipt::find($item->receipt_id);
                                if ($r && abs((float)$r->total_amount - $totalVal) <= 0.05) {
                                    $r->update([
                                        'subtotal'     => $calcBase,
                                        'vat_amount'   => $vat,
                                        'total_amount' => $totalVal,
                                    ]);
                                }
                            }
                        }
                    }
                });
        } catch (\Throwable $e) {}

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

        // Check if selected Ethiopian month has been cleared/settled
        $selectedMonthSettlement = TaxSettlement::where('status', TaxSettlement::STATUS_PAID)
            ->whereIn('tax_type', ['vat', 'both'])
            ->where(function ($q) use ($selectedEthYear, $selectedEthMonth, $periodStart, $periodEnd) {
                $q->where(function ($sq) use ($selectedEthYear, $selectedEthMonth) {
                    $sq->where('ethiopian_year', $selectedEthYear)
                       ->where('ethiopian_month', $selectedEthMonth);
                })->orWhere(function ($sq) use ($periodStart, $periodEnd) {
                    $sq->where('period_from', $periodStart)
                       ->where('period_to', $periodEnd);
                });
            })
            ->latest('id')
            ->first();

        // 1. Company Income (Sales base & Output VAT)
        $monthIncomeItems = \App\Models\IncomeRecord::where(function ($iq) use ($periodStartDate, $periodEndDate) {
            $iq->whereBetween('income_date', [$periodStartDate, $periodEndDate])
               ->orWhere(function ($sub) use ($periodStartDate, $periodEndDate) {
                   $sub->whereNull('income_date')->whereBetween('receipt_date', [$periodStartDate, $periodEndDate]);
               });
        })->get();

        $unsettledIncomeItems  = $monthIncomeItems->filter(fn($inc) => !(bool)$inc->vat_settled);
        $unsettledExpenseItems = $allTaxItems->filter(fn($i) => !(bool)$i->vat_settled && ($i->vat_type ?? '') !== 'vat_b');
        $unsettledWhtItems     = $allTaxItems->filter(fn($i) => !(bool)$i->withholding_settled && ($i->has_withholding || (float)$i->withholding_amount > 0));

        $hasActiveTransactions = $unsettledIncomeItems->isNotEmpty() || $unsettledExpenseItems->isNotEmpty() || $unsettledWhtItems->isNotEmpty();
        $hasPriorSettlement    = ($selectedMonthSettlement !== null);

        $allIncomesSettled  = $monthIncomeItems->isNotEmpty() && $unsettledIncomeItems->isEmpty();
        $allExpensesSettled = $allTaxItems->isEmpty() || $unsettledExpenseItems->isEmpty();

        // Fully cleared: Has a prior settlement (or all historical records marked settled) AND NO active unsettled transactions
        $isMonthVatCleared = ($hasPriorSettlement || ($allIncomesSettled && $allExpensesSettled)) && !$hasActiveTransactions;

        // Fresh Start: Prior settlement exists, but new active transactions have been added and are accruing fresh from zero
        $isFreshStart = $hasPriorSettlement && $hasActiveTransactions;

        $salesBase = (float)$monthIncomeItems->sum(function ($inc) use ($cycle) {
            if ($cycle === 'active' && $inc->vat_settled) return 0.0;
            return (float)($inc->unit_price > 0 && $inc->quantity > 0 ? ($inc->unit_price * $inc->quantity) : $inc->amount);
        });
        $outputVat = (float)$monthIncomeItems->sum(function ($inc) use ($cycle) {
            if ($cycle === 'active' && $inc->vat_settled) return 0.0;
            $gross = (float)($inc->unit_price > 0 && $inc->quantity > 0 ? ($inc->unit_price * $inc->quantity) : $inc->amount);
            return round($gross * 0.15, 2);
        });

        // 2. Creditable Purchases & Input VAT (Exclude "VAT B")
        $ocrItemsInMonth = \App\Models\ReceiptItem::whereHas('receipt', fn($rq) => 
            $rq->whereBetween('receipt_date', [$periodStartDate, $periodEndDate])
        )->get();

        $ocrPurchasesBase = (float)$ocrItemsInMonth->where('vat_amount', '>', 0)->sum('total_value');
        $ocrInputVat      = (float)$ocrItemsInMonth->sum('vat_amount');

        $expensePurchasesBase = (float)$allTaxItems->filter(fn($i) => ($i->vat_type ?? '') !== 'vat_b')->sum(function ($i) use ($cycle) {
            if ($cycle === 'active' && $i->vat_settled) return 0.0;
            $gross = (float)($i->gross_amount > 0 ? $i->gross_amount : $i->amount);
            if (($i->vat_type ?? '') === 'inclusive') return round($gross / 1.15, 2);
            return $gross;
        });
        $expenseInputVat = $totalVatAmount; // Already strictly excludes VAT B and excludes settled in active cycle

        if ($vatSource === 'expense') {
            $purchasesBase = $expensePurchasesBase;
            $inputVat      = $expenseInputVat;
        } else {
            $purchasesBase = $ocrPurchasesBase > 0 ? $ocrPurchasesBase : $expensePurchasesBase;
            $inputVat      = $ocrInputVat > 0 ? $ocrInputVat : $expenseInputVat;
        }

        if ($cycle === 'active' && $isMonthVatCleared) {
            $salesBase     = 0.0;
            $outputVat     = 0.0;
            $purchasesBase = 0.0;
            $inputVat      = 0.0;
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
        $prevOutputVat = round($prevIncomeSum * 0.15, 2);

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

        // When the month is cleared or is a Fresh Start from zero, opening credit carryover is reset to 0.00
        if (($cycle === 'active' && $isMonthVatCleared) || $isFreshStart) {
            $openingCredit = 0.0;
        }

        // 4. Signed Net VAT Calculation
        if ($cycle === 'active' && $isMonthVatCleared) {
            $netVatDetails = [
                'output_vat'             => 0.0,
                'input_vat'              => 0.0,
                'opening_credit'         => 0.0,
                'total_creditable_input' => 0.0,
                'net'                    => 0.0,
                'signed_net_formatted'   => '0.00',
                'status'                 => 'cleared',
                'label'                  => 'Cleared to 0.00 ETB (Settled)',
                'badge_class'            => 'bg-success text-white',
                'text_class'             => 'text-success',
                'is_payable'             => false,
                'is_credit'              => false,
                'credit_carry_forward'   => 0.0,
                'amount_payable'         => 0.0,
                'payable_amount'         => 0.0,
                'is_cleared'             => true,
                'is_fresh_start'         => false,
                'settlement_number'      => $selectedMonthSettlement?->settlement_number,
            ];
            $signedNetVat = 0.0;
        } else {
            $netVatDetails = EthiopianDate::calculateNetVat($inputVat, $outputVat, $openingCredit);
            $netVatDetails['is_cleared'] = false;
            $netVatDetails['is_fresh_start'] = $isFreshStart;
            $netVatDetails['settlement_number'] = $selectedMonthSettlement?->settlement_number;
            $signedNetVat  = $netVatDetails['net'];
        }

        // 5. Withholding tax (3%) & Net Disbursed for the month (stays separate line, not part of Net)
        $incomeWithholding = (float)$monthIncomeItems->sum(function ($inc) use ($cycle) {
            if ($cycle === 'active' && $inc->vat_settled) return 0.0;
            $gross = (float)($inc->unit_price > 0 && $inc->quantity > 0 ? ($inc->unit_price * $inc->quantity) : $inc->amount);
            return round($gross * 0.03, 2);
        });
        $monthWithholding = round($totalWithholdingAmount + $incomeWithholding, 2);
        $monthNetDisbursed = $totalNetDisbursed;

        if ($cycle === 'active' && $isMonthVatCleared) {
            $monthWithholding  = 0.0;
            $incomeWithholding = 0.0;
            $monthNetDisbursed = 0.0;
        }

        // 6. 12-Month Trend Table (Meskerem to Pagume, months 1 to 13 of selected Ethiopian Year)
        $monthlyTrend = [];
        $runningOpeningCredit = 0.0;
        for ($m = 1; $m <= 13; $m++) {
            $mRange = EthiopianDate::monthRange($selectedEthYear, $m);

            // Settlement check for this month
            $mSettlement = TaxSettlement::where('status', TaxSettlement::STATUS_PAID)
                ->whereIn('tax_type', ['vat', 'both'])
                ->where(function ($q) use ($selectedEthYear, $m, $mRange) {
                    $q->where(function($sq) use ($selectedEthYear, $m) {
                        $sq->where('ethiopian_year', $selectedEthYear)->where('ethiopian_month', $m);
                    })->orWhere(function($sq) use ($mRange) {
                        $sq->where('period_from', $mRange['start'])->where('period_to', $mRange['end']);
                    });
                })
                ->latest('id')
                ->first();

            $mIncomeAll = \App\Models\IncomeRecord::where(function ($iq) use ($mRange) {
                $iq->whereBetween('income_date', [$mRange['start_date'], $mRange['end_date']])
                   ->orWhere(function ($sub) use ($mRange) {
                       $sub->whereNull('income_date')->whereBetween('receipt_date', [$mRange['start_date'], $mRange['end_date']]);
                   });
            })->get();

            $mIncTotal = (float)$mIncomeAll->sum(function ($inc) {
                return (float)($inc->unit_price > 0 && $inc->quantity > 0 ? ($inc->unit_price * $inc->quantity) : $inc->amount);
            });
            $mOutTotal = round($mIncTotal * 0.15, 2);

            $mIncomeUnsettled = $mIncomeAll->filter(fn($inc) => !(bool)$inc->vat_settled);
            $mIncActive = (float)$mIncomeUnsettled->sum(function ($inc) {
                return (float)($inc->unit_price > 0 && $inc->quantity > 0 ? ($inc->unit_price * $inc->quantity) : $inc->amount);
            });
            $mOutActive = round($mIncActive * 0.15, 2);

            $mExpAll = ExpenseRequest::where('status', ExpenseRequest::STATUS_PAID)
                ->whereBetween('created_at', [$mRange['start'], $mRange['end']])
                ->where('vat_type', '!=', 'vat_b')
                ->get();
            $mExpUnsettled = $mExpAll->filter(fn($i) => !(bool)$i->vat_settled);

            $calcVatFunc = function($items) {
                return (float)$items->sum(function ($item) {
                    if ((float)$item->vat_amount > 0 && ($item->vat_type ?? '') === 'exclusive') return (float)$item->vat_amount;
                    $gross = (float)($item->gross_amount > 0 ? $item->gross_amount : $item->amount);
                    if (($item->vat_type ?? '') === 'inclusive') return round($gross - ($gross / 1.15), 2);
                    if (($item->vat_type ?? '') === 'exclusive') return round($gross * 0.15, 2);
                    return 0.0;
                });
            };

            $mExpInTotal  = $calcVatFunc($mExpAll);
            $mExpInActive = $calcVatFunc($mExpUnsettled);

            $mOcrIn = (float)\App\Models\ReceiptItem::whereHas('receipt', fn($rq) => 
                $rq->whereBetween('receipt_date', [$mRange['start_date'], $mRange['end_date']])
            )->sum('vat_amount');

            $mInTotal  = max($mExpInTotal, $mOcrIn);
            $mInActive = max($mExpInActive, $mOcrIn);

            $mHasUnsettled = $mIncomeUnsettled->isNotEmpty() || $mExpUnsettled->isNotEmpty();
            $mHasPriorSettlement = ($mSettlement !== null);
            $mIsFullyCleared = ($mHasPriorSettlement || ($mIncomeAll->isNotEmpty() && $mIncomeUnsettled->isEmpty() && $mExpUnsettled->isEmpty())) && !$mHasUnsettled;
            $mIsFreshStart = $mHasPriorSettlement && $mHasUnsettled;

            if ($mIsFullyCleared) {
                if ($cycle === 'active') {
                    $monthlyTrend[$m] = [
                        'month'                => $m,
                        'month_name'           => EthiopianDate::MONTHS_EN[$m],
                        'month_am'             => EthiopianDate::MONTHS_AM[$m],
                        'start_date'           => $mRange['start_date'],
                        'end_date'             => $mRange['end_date'],
                        'output_vat'           => 0.0,
                        'input_vat'            => 0.0,
                        'opening_credit'       => 0.0,
                        'net'                  => 0.0,
                        'signed_net_formatted' => '0.00',
                        'status'               => 'cleared',
                        'label'                => 'Cleared (0.00 ETB)',
                        'badge_class'          => 'bg-success text-white',
                        'text_class'           => 'text-success',
                        'is_payable'           => false,
                        'is_credit'            => false,
                        'amount_payable'       => 0.0,
                        'payable_amount'       => 0.0,
                        'is_current'           => ($m === $selectedEthMonth),
                        'is_cleared'           => true,
                        'is_fresh_start'       => false,
                        'settlement_number'    => $mSettlement?->settlement_number,
                    ];
                    $runningOpeningCredit = 0.0;
                } else {
                    $monthlyTrend[$m] = [
                        'month'                => $m,
                        'month_name'           => EthiopianDate::MONTHS_EN[$m],
                        'month_am'             => EthiopianDate::MONTHS_AM[$m],
                        'start_date'           => $mRange['start_date'],
                        'end_date'             => $mRange['end_date'],
                        'output_vat'           => $mOutTotal,
                        'input_vat'            => $mInTotal,
                        'opening_credit'       => $runningOpeningCredit,
                        'net'                  => 0.0,
                        'signed_net_formatted' => '0.00 (Settled)',
                        'status'               => 'cleared',
                        'label'                => 'VAT Settled (Cleared)',
                        'badge_class'          => 'bg-success text-white',
                        'text_class'           => 'text-success',
                        'is_payable'           => false,
                        'is_credit'            => false,
                        'amount_payable'       => 0.0,
                        'payable_amount'       => 0.0,
                        'is_current'           => ($m === $selectedEthMonth),
                        'is_cleared'           => true,
                        'is_fresh_start'       => false,
                        'settlement_number'    => $mSettlement?->settlement_number,
                    ];
                    $runningOpeningCredit = 0.0;
                }
            } else {
                $curOut = ($cycle === 'active') ? $mOutActive : $mOutTotal;
                $curIn  = ($cycle === 'active') ? $mInActive : $mInTotal;
                $curOpening = ($cycle === 'active' && $mIsFreshStart) ? 0.0 : $runningOpeningCredit;
                $mNetCalc = EthiopianDate::calculateNetVat($curIn, $curOut, $curOpening);

                $monthlyTrend[$m] = [
                    'month'                => $m,
                    'month_name'           => EthiopianDate::MONTHS_EN[$m],
                    'month_am'             => EthiopianDate::MONTHS_AM[$m],
                    'start_date'           => $mRange['start_date'],
                    'end_date'             => $mRange['end_date'],
                    'output_vat'           => $curOut,
                    'input_vat'            => $curIn,
                    'opening_credit'       => $curOpening,
                    'net'                  => $mNetCalc['net'],
                    'signed_net_formatted' => $mNetCalc['signed_net_formatted'],
                    'status'               => $mNetCalc['status'],
                    'label'                => $mNetCalc['label'],
                    'badge_class'          => $mNetCalc['badge_class'],
                    'text_class'           => $mNetCalc['text_class'],
                    'is_payable'           => $mNetCalc['is_payable'],
                    'is_credit'            => $mNetCalc['is_credit'],
                    'amount_payable'       => $mNetCalc['amount_payable'],
                    'payable_amount'       => $mNetCalc['payable_amount'],
                    'is_current'           => ($m === $selectedEthMonth),
                    'is_cleared'           => false,
                    'is_fresh_start'       => $mIsFreshStart,
                    'settlement_number'    => $mSettlement?->settlement_number,
                ];

                $runningOpeningCredit = $mNetCalc['credit_carry_forward'];
            }
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

        $authUser = Auth::user();
        $isGeneralAdmin = (bool)$authUser;

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
            'incomeWithholding',
            'monthNetDisbursed',
            'monthlyTrend',
            'isGeneralAdmin',
            'isMonthVatCleared',
            'isFreshStart',
            'selectedMonthSettlement'
        ));
    }

    /**
     * AJAX endpoint: Retrieve all itemized financial movements for a specific Ethiopian month.
     * Returns detailed transactions: Incomes (Sales), Expenses/Purchases, OCR Receipts, and Signed Net VAT reconciliation.
     */
    public function monthMovements(Request $request)
    {
        $curEth = EthiopianDate::toEthiopian(now());
        $year  = (int)($request->query('ethiopian_year') ?: $curEth['year']);
        $month = (int)($request->query('ethiopian_month') ?: $curEth['month']);

        if ($month < 1 || $month > 13) {
            $month = $curEth['month'];
        }

        $mRange = EthiopianDate::monthRange($year, $month);
        $startDate = $mRange['start_date'];
        $endDate   = $mRange['end_date'];
        $start     = $mRange['start'];
        $end       = $mRange['end'];

        // 1. Incomes for this Ethiopian month (Where our income came from)
        $incomes = collect();
        if (Schema::hasTable('income_records')) {
            $incomes = \App\Models\IncomeRecord::with(['project', 'bankAccount', 'chartOfAccount', 'createdBy'])
                ->where(function ($iq) use ($startDate, $endDate) {
                    $iq->whereBetween('income_date', [$startDate, $endDate])
                       ->orWhere(function ($sub) use ($startDate, $endDate) {
                           $sub->whereNull('income_date')->whereBetween('receipt_date', [$startDate, $endDate]);
                       });
                })
                ->orderBy('income_date', 'asc')
                ->orderBy('id', 'asc')
                ->get();
        }

        $formattedIncomes = $incomes->map(function ($inc) {
            $date = $inc->income_date ?: $inc->receipt_date;
            $gross = (float)($inc->unit_price > 0 && $inc->quantity > 0 ? ($inc->unit_price * $inc->quantity) : $inc->amount);
            $vat = round($gross * 0.15, 2);
            $wht = round($gross * 0.03, 2);
            $net = round($gross + $vat - $wht, 2);

            $accountName = '-';
            if ($inc->bankAccount) {
                $accountName = $inc->bankAccount->bank_name . ($inc->bankAccount->account_number ? ' (' . $inc->bankAccount->account_number . ')' : '');
            } elseif ($inc->chartOfAccount) {
                $accountName = $inc->chartOfAccount->name;
            } elseif (!empty($inc->payment_method)) {
                $accountName = ucfirst($inc->payment_method);
            }

            return [
                'id'                 => $inc->id,
                'income_no'          => $inc->income_no ?: ('INC-' . $inc->id),
                'receipt_no'         => $inc->receipt_no ?: ($inc->mrc_no ?: '-'),
                'date_ec'            => EthiopianDate::formatDateEc($date, 'DD/MM/YYYY EC'),
                'date_gc'            => $date ? Carbon::parse($date)->format('M d, Y') : '-',
                'buyer_name'         => $inc->buyer_name ?: ($inc->payer ?: 'Client / Customer'),
                'buyer_tin'          => $inc->buyer_tin ?: '-',
                'project_name'       => $inc->project?->name ?: 'General / Head Office',
                'account_name'       => $accountName,
                'category'           => ucfirst($inc->category ?: 'General'),
                'sales_base'         => $gross,
                'output_vat'         => $vat,
                'withholding_amount' => $wht,
                'net_total'          => $net,
                'gross_amount'       => $gross,
                'description'        => $inc->description ?: ($inc->notes ?: '-'),
                'receipt_url'                => $inc->receipt_url ?: ($inc->receipt_file_path ? asset('storage/' . $inc->receipt_file_path) : null),
                'withholding_receipt_url'    => $inc->withholding_receipt_url,
                'withholding_receipt_number' => $inc->withholding_receipt_number,
                'has_withholding'            => (bool)($inc->has_withholding || !empty($inc->withholding_receipt)),
                'is_settled'                 => (bool)$inc->vat_settled,
            ];
        });

        $totalSalesBase = (float)$formattedIncomes->sum('sales_base');
        $totalOutputVat = (float)$formattedIncomes->sum('output_vat');
        $totalGrossIncome = (float)$formattedIncomes->sum('gross_amount');

        // 2. Paid Expenses in this Ethiopian month (Where our purchases came from, excluding VAT B)
        $expenses = collect();
        if (Schema::hasTable('expense_requests')) {
            $expenses = ExpenseRequest::with(['project', 'user', 'bankAccount', 'chartOfAccount', 'paidBy'])
                ->where('status', ExpenseRequest::STATUS_PAID)
                ->whereBetween('created_at', [$start, $end])
                ->where('vat_type', '!=', 'vat_b')
                ->orderBy('created_at', 'asc')
                ->get();
        }

        $formattedExpenses = $expenses->map(function ($item) {
            $gross = (float)($item->gross_amount > 0 ? $item->gross_amount : $item->amount);
            $vatType = $item->vat_type ?? 'none';
            $vatRate = (float)($item->vat_rate ?? 15.00);

            $baseAmount = $gross;
            $vatAmount  = 0.0;

            if ((float)$item->vat_amount > 0 && $vatType === 'exclusive') {
                $vatAmount  = (float)$item->vat_amount;
                $baseAmount = $gross;
            } elseif ($vatType === 'inclusive') {
                $baseAmount = round($gross / (1 + ($vatRate / 100)), 2);
                $vatAmount  = round($gross - $baseAmount, 2);
            } elseif ($vatType === 'exclusive') {
                $vatAmount  = round($gross * ($vatRate / 100), 2);
                $baseAmount = $gross;
            }

            $whtAmount = (float)$item->calculated_withholding_amount;
            $netDisbursed = (float)$item->effective_payable_amount;

            $accountName = '-';
            if ($item->bankAccount) {
                $accountName = $item->bankAccount->bank_name . ($item->bankAccount->account_number ? ' (' . $item->bankAccount->account_number . ')' : '');
            } elseif ($item->chartOfAccount) {
                $accountName = $item->chartOfAccount->name;
            } elseif (!empty($item->payment_method)) {
                $accountName = ucfirst(str_replace('_', ' ', $item->payment_method));
            }

            $payee = $item->account_name ?: ($item->user?->name ?: 'Vendor / Payee');

            return [
                'id'                 => $item->id,
                'request_number'     => $item->request_number,
                'payment_reference'  => $item->payment_reference ?: '-',
                'date_ec'            => EthiopianDate::formatDateEc($item->created_at, 'DD/MM/YYYY EC'),
                'date_gc'            => $item->created_at->format('M d, Y'),
                'payee'              => $payee,
                'project_name'       => $item->project?->name ?: 'General',
                'category'           => ucfirst(str_replace('_', ' ', $item->category ?: 'Expense')),
                'account_name'       => $accountName,
                'base_amount'        => $baseAmount,
                'vat_type'           => $vatType,
                'vat_rate'           => $vatRate,
                'vat_amount'         => $vatAmount,
                'has_withholding'    => (bool)$item->has_withholding,
                'withholding_amount' => $whtAmount,
                'net_disbursed'      => $netDisbursed,
                'description'        => $item->description ?: '-',
                'attachment_url'     => $item->attachment_url,
                'status'             => $item->status,
                'is_settled'         => (bool)$item->vat_settled,
            ];
        });

        // 3. OCR Scanned Receipts in this Ethiopian month
        $ocrItems = collect();
        if (Schema::hasTable('receipt_items')) {
            // Auto-sync any standalone receipts in this period to have a ReceiptItem
            if (Schema::hasTable('receipts')) {
                $standaloneReceipts = \App\Models\Receipt::doesntHave('items')
                    ->where(function ($rq) use ($startDate, $endDate) {
                        $rq->whereBetween('receipt_date', [$startDate, $endDate])
                           ->orWhere(function ($sub) use ($startDate, $endDate) {
                               $sub->whereNull('receipt_date')
                                   ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
                           });
                    })
                    ->get();

                foreach ($standaloneReceipts as $sr) {
                    $subtotal = (float)$sr->subtotal;
                    $vat = (float)$sr->vat_amount;
                    $total = (float)$sr->total_amount;
                    if ($total <= 0 && ($subtotal > 0 || $vat > 0)) {
                        $total = round($subtotal + $vat, 2);
                    }
                    if ($subtotal <= 0 && $total > 0) {
                        if ($vat > 0) {
                            $subtotal = round($total - $vat, 2);
                        } else {
                            $subtotal = round($total / 1.15, 2);
                            $vat = round($total - $subtotal, 2);
                        }
                    }
                    \App\Models\ReceiptItem::create([
                        'receipt_id'       => $sr->id,
                        'item_description' => $sr->description ?: 'Purchased Goods/Services',
                        'vat_category'     => 'G',
                        'calendar_type'    => 'G',
                        'purchase_type'    => 3,
                        'uom'              => '9',
                        'qty'              => 1.00,
                        'unit_price'       => $subtotal,
                        'total_value'      => $subtotal,
                        'vat_amount'       => $vat,
                        'value_after_vat'  => $total,
                    ]);
                }
            }

            // Auto-sync any CreditStorePayments in this month that have receipt_path but no linked Receipt
            if (Schema::hasTable('credit_store_payments') && Schema::hasTable('receipts')) {
                $unlinkedPayments = \App\Models\CreditStorePayment::with('ledger')
                    ->whereNotNull('receipt_path')
                    ->where('receipt_path', '!=', '')
                    ->whereBetween('payment_date', [$startDate, $endDate])
                    ->whereDoesntHave('receiptRecords')
                    ->get();

                foreach ($unlinkedPayments as $p) {
                    $ledger = $p->ledger;
                    $gross = (float)$p->amount;
                    $vatRate = (float)($p->vat_rate ?: 15.00);
                    $vatIncluded = ((float)($p->vat_amount ?? 0) > 0) || in_array($p->vat_type, ['inclusive', 'vat_b', 'exclusive']);
                    
                    if ($vatIncluded && in_array($p->vat_type, ['inclusive', 'vat_b'])) {
                        $receiptSubtotal = (float)($p->gross_amount > 0 ? $p->gross_amount : round($gross / (1 + ($vatRate / 100)), 2));
                        $vatAmount = (float)($p->vat_amount > 0 ? $p->vat_amount : round($gross - $receiptSubtotal, 2));
                        $receiptTotal = $gross;
                    } elseif ($vatIncluded && $p->vat_type === 'exclusive') {
                        $receiptSubtotal = $gross;
                        $vatAmount = (float)($p->vat_amount > 0 ? $p->vat_amount : round($gross * ($vatRate / 100), 2));
                        $receiptTotal = round($gross + $vatAmount, 2);
                    } else {
                        $receiptSubtotal = $gross;
                        $vatAmount = 0.0;
                        $receiptTotal = $gross;
                    }

                    $cleanFs = preg_replace('/[^0-9]/', '', (string)$p->reference_no);
                    $newReceipt = \App\Models\Receipt::create([
                        'purchasable_type' => \App\Models\CreditStorePayment::class,
                        'purchasable_id'   => $p->id,
                        'vendor_name'      => $ledger?->supplier_name ?: 'Credit Vendor',
                        'vendor_tin'       => '-',
                        'tin_valid'        => false,
                        'buyer_tin'        => '0038480010',
                        'receipt_number'   => $p->reference_no ?: ('REC-PAY-' . $p->id),
                        'fs_no'            => $p->reference_no ?: '-',
                        'fs_no_raw'        => $p->reference_no ?: '-',
                        'fs_no_valid'      => (strlen($cleanFs) === 8),
                        'receipt_date'     => $p->payment_date ?: now()->toDateString(),
                        'subtotal'         => $receiptSubtotal,
                        'vat_amount'       => $vatAmount,
                        'total_amount'     => $receiptTotal,
                        'currency'         => 'ETB',
                        'category'         => 'material',
                        'description'      => "Credit Payment #{$p->id} - " . ($ledger?->item_description ?: 'Materials'),
                        'file_path'        => $p->receipt_path,
                        'file_type'        => 'image',
                        'status'           => 'approved',
                        'is_verified'      => true,
                        'qc_notes'         => 'Synced from Credit Purchases Ledger payment receipt',
                        'created_at'       => $p->created_at ?: now(),
                    ]);

                    \App\Models\ReceiptItem::create([
                        'receipt_id'       => $newReceipt->id,
                        'item_description' => $ledger?->item_description ?: 'Credit settlement',
                        'vat_category'     => $vatIncluded ? 'G' : 'E',
                        'calendar_type'    => 'G',
                        'purchase_type'    => $vatIncluded ? 3 : 6,
                        'uom'              => '9',
                        'qty'              => 1.0,
                        'unit_price'       => $receiptSubtotal,
                        'total_value'      => $receiptSubtotal,
                        'vat_amount'       => $vatAmount,
                        'value_after_vat'  => $receiptTotal,
                    ]);
                }
            }

            $ocrItems = \App\Models\ReceiptItem::with(['receipt', 'receipt.project', 'receipt.uploader'])
                ->whereHas('receipt', function ($rq) use ($startDate, $endDate) {
                    $rq->whereBetween('receipt_date', [$startDate, $endDate])
                       ->orWhere(function ($sub) use ($startDate, $endDate) {
                           $sub->whereNull('receipt_date')
                               ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
                       });
                })
                ->orderBy('id', 'asc')
                ->get();
        }

        $formattedOcrItems = $ocrItems->map(function ($ocr) {
            $r = $ocr->receipt;
            $date = $r?->receipt_date ?: $ocr->created_at;

            $baseVal  = (float)$ocr->total_value;
            $vatVal   = (float)$ocr->vat_amount;
            $afterVal = (float)$ocr->value_after_vat;

            // In-memory fallback guard if base was gross (e.g. 550) + vat (71.74) = afterVal (621.74)
            if ($baseVal > 0 && $vatVal > 0) {
                $calcBase = round($baseVal / 1.15, 2);
                if (abs(($baseVal - $vatVal) - $calcBase) <= 0.05 && abs(round($calcBase * 0.15, 2) - $vatVal) <= 0.05) {
                    $afterVal = $baseVal;
                    $baseVal  = $calcBase;
                }
            }

            $filePath = $r?->file_path;
            $fileUrl = $r?->file_url ?: ($filePath ? \App\Services\FileUploadService::url($filePath) : null);
            $fileExt = $filePath ? strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) : '';
            $isPdf = in_array($fileExt, ['pdf']) || str_contains(strtolower($filePath ?? ''), 'pdf');

            return [
                'id'               => $ocr->id,
                'receipt_id'       => $r?->id,
                'receipt_number'   => $r?->receipt_number ?: ('REC-' . $ocr->id),
                'fs_no'            => $r?->fs_no ?: ($r?->fs_no_raw ?: '-'),
                'mrc_no'           => $r?->mrc_no ?: '-',
                'vendor_name'      => $r?->vendor_name ?: 'Vendor',
                'vendor_tin'       => $r?->vendor_tin ?: '-',
                'buyer_tin'        => $r?->buyer_tin ?: '0038480010',
                'date_ec'          => $date ? EthiopianDate::formatDateEc($date, 'DD/MM/YYYY EC') : '-',
                'date_gc'          => $date ? Carbon::parse($date)->format('M d, Y') : '-',
                'project_name'     => $r?->project?->name ?: 'General',
                'category'         => $r?->category ?: ($ocr->vat_category ?: 'General'),
                'item_description' => $ocr->item_description ?: ($r?->description ?: 'Purchased Goods/Services'),
                'base_value'       => $baseVal,
                'vat_amount'       => $vatVal,
                'value_after_vat'  => $afterVal,
                'file_url'         => $fileUrl,
                'file_type'        => $isPdf ? 'pdf' : ($r?->file_type ?: 'image'),
                'is_pdf'           => $isPdf,
            ];
        });

        $expPurchasesBase = (float)$formattedExpenses->sum('base_amount');
        $expInputVat      = (float)$formattedExpenses->sum('vat_amount');
        $ocrPurchasesBase = (float)$formattedOcrItems->sum('base_value');
        $ocrInputVat      = (float)$formattedOcrItems->sum('vat_amount');

        $effectivePurchasesBase = max($expPurchasesBase, $ocrPurchasesBase);
        $effectiveInputVat      = max($expInputVat, $ocrInputVat);
        $totalWithholding       = round((float)$formattedExpenses->sum('withholding_amount') + (float)$formattedIncomes->sum('withholding_amount'), 2);
        $totalNetDisbursed      = (float)$formattedExpenses->sum('net_disbursed');

        // 4. Calculate Opening Credit from previous month
        $openingCredit = 0.0;
        if (Schema::hasTable('income_records') || Schema::hasTable('expense_requests') || Schema::hasTable('receipt_items')) {
            $prevYear  = ($month === 1) ? $year - 1 : $year;
            $prevMonth = ($month === 1) ? 13 : $month - 1;
            $prevRange = EthiopianDate::monthRange($prevYear, $prevMonth);

            $prevIncomeSum = 0.0;
            if (Schema::hasTable('income_records')) {
                $prevIncomeSum = (float)\App\Models\IncomeRecord::where(function ($iq) use ($prevRange) {
                    $iq->whereBetween('income_date', [$prevRange['start_date'], $prevRange['end_date']])
                       ->orWhere(function ($sub) use ($prevRange) {
                           $sub->whereNull('income_date')->whereBetween('receipt_date', [$prevRange['start_date'], $prevRange['end_date']]);
                       });
                })->sum('amount');
            }
            $prevOutputVat = round($prevIncomeSum * 0.15, 2);

            $prevExpIn = 0.0;
            if (Schema::hasTable('expense_requests')) {
                $prevExp = ExpenseRequest::where('status', ExpenseRequest::STATUS_PAID)
                    ->whereBetween('created_at', [$prevRange['start'], $prevRange['end']])
                    ->where('vat_type', '!=', 'vat_b')
                    ->get();
                $prevExpIn = (float)$prevExp->sum(function ($item) {
                    if ((float)$item->vat_amount > 0 && ($item->vat_type ?? '') === 'exclusive') return (float)$item->vat_amount;
                    $gross = (float)($item->gross_amount > 0 ? $item->gross_amount : $item->amount);
                    if (($item->vat_type ?? '') === 'inclusive') return round($gross - ($gross / 1.15), 2);
                    if (($item->vat_type ?? '') === 'exclusive') return round($gross * 0.15, 2);
                    return 0.0;
                });
            }

            $prevOcrIn = 0.0;
            if (Schema::hasTable('receipt_items')) {
                $prevOcrIn = (float)\App\Models\ReceiptItem::whereHas('receipt', fn($rq) => 
                    $rq->whereBetween('receipt_date', [$prevRange['start_date'], $prevRange['end_date']])
                )->sum('vat_amount');
            }
            $prevEffectiveInput = max($prevExpIn, $prevOcrIn);

            $openingCredit = max(0.0, round($prevEffectiveInput - $prevOutputVat, 2));
        }

        // 5. Net VAT Calculation
        $mSettlement = TaxSettlement::where('status', TaxSettlement::STATUS_PAID)
            ->whereIn('tax_type', ['vat', 'both'])
            ->where(function ($q) use ($year, $month, $mRange) {
                $q->where(function ($sq) use ($year, $month) {
                    $sq->where('ethiopian_year', $year)->where('ethiopian_month', $month);
                })->orWhere(function ($sq) use ($mRange) {
                    $sq->where('period_from', $mRange['start'])->where('period_to', $mRange['end']);
                });
            })
            ->latest('id')
            ->first();

        $unsettledIncomes = $incomes->filter(fn($i) => !(bool)$i->vat_settled);
        $unsettledExpenses = $expenses->filter(fn($e) => !(bool)$e->vat_settled);
        $hasActiveTransactions = $unsettledIncomes->isNotEmpty() || $unsettledExpenses->isNotEmpty();
        $hasPriorSettlement = ($mSettlement !== null);

        $allIncomesSettled = $incomes->isNotEmpty() && $unsettledIncomes->isEmpty();
        $allExpensesSettled = $expenses->isEmpty() || $unsettledExpenses->isEmpty();

        $isCleared = ($hasPriorSettlement || ($allIncomesSettled && $allExpensesSettled)) && !$hasActiveTransactions;
        $isFreshStart = $hasPriorSettlement && $hasActiveTransactions;

        if ($isCleared) {
            $summaryNet = [
                'net'                    => 0.0,
                'signed_net_formatted'   => '0.00 (Cleared)',
                'status'                 => 'cleared',
                'label'                  => 'Cleared (0.00 ETB)',
                'badge_class'            => 'bg-success text-white',
                'text_class'             => 'text-success',
                'is_payable'             => false,
                'is_credit'              => false,
                'payable_amount'         => 0.0,
                'credit_carry_forward'   => 0.0,
                'total_creditable_input' => 0.0,
            ];
            $dispSalesBase     = 0.0;
            $dispOutputVat     = 0.0;
            $dispPurchasesBase = 0.0;
            $dispInputVat      = 0.0;
            $dispOpeningCredit = 0.0;
            $dispWithholding   = 0.0;
        } elseif ($isFreshStart) {
            $activeIncomes = $formattedIncomes->filter(fn($i) => !$i['is_settled']);
            $activeExpenses = $formattedExpenses->filter(fn($e) => !$e['is_settled']);

            $dispSalesBase     = (float)$activeIncomes->sum('sales_base');
            $dispOutputVat     = (float)$activeIncomes->sum('output_vat');
            $dispPurchasesBase = (float)$activeExpenses->sum('base_amount');
            $dispInputVat      = (float)$activeExpenses->sum('vat_amount');
            $dispOpeningCredit = 0.0;
            $dispWithholding   = round((float)$activeExpenses->sum('withholding_amount') + (float)$activeIncomes->sum('withholding_amount'), 2);

            $summaryNet = EthiopianDate::calculateNetVat($dispInputVat, $dispOutputVat, 0.0);
        } else {
            $dispSalesBase     = $totalSalesBase;
            $dispOutputVat     = $totalOutputVat;
            $dispPurchasesBase = $effectivePurchasesBase;
            $dispInputVat      = $effectiveInputVat;
            $dispOpeningCredit = $openingCredit;
            $dispWithholding   = $totalWithholding;

            $summaryNet = EthiopianDate::calculateNetVat($effectiveInputVat, $totalOutputVat, $openingCredit);
        }

        return response()->json([
            'success' => true,
            'month' => [
                'year'             => $year,
                'month'            => $month,
                'month_name'       => EthiopianDate::MONTHS_EN[$month],
                'month_am'         => EthiopianDate::MONTHS_AM[$month],
                'title'            => EthiopianDate::MONTHS_EN[$month] . ' (' . EthiopianDate::MONTHS_AM[$month] . ') ' . $year . ' EC',
                'gregorian_window' => $mRange['start']->format('M d, Y') . ' – ' . $mRange['end']->format('M d, Y') . ' (' . $mRange['days'] . ' Days)',
                'start_date'       => $startDate,
                'end_date'         => $endDate,
                'days'             => $mRange['days'],
            ],
            'summary' => [
                'sales_base'             => $dispSalesBase,
                'output_vat'             => $dispOutputVat,
                'gross_income'           => $totalGrossIncome,
                'purchases_base'         => $dispPurchasesBase,
                'input_vat'              => $dispInputVat,
                'opening_credit'         => $dispOpeningCredit,
                'total_creditable_input' => $summaryNet['total_creditable_input'] ?? 0.0,
                'net_vat'                => $summaryNet['net'],
                'signed_net_formatted'   => $summaryNet['signed_net_formatted'],
                'status'                 => $summaryNet['status'],
                'label'                  => $summaryNet['label'],
                'badge_class'            => $summaryNet['badge_class'],
                'text_class'             => $summaryNet['text_class'],
                'is_payable'             => $summaryNet['is_payable'],
                'is_credit'              => $summaryNet['is_credit'],
                'payable_amount'         => $summaryNet['payable_amount'],
                'credit_carry_forward'   => $summaryNet['credit_carry_forward'],
                'is_cleared'             => $isCleared,
                'is_fresh_start'         => $isFreshStart,
                'settlement_number'      => $mSettlement?->settlement_number,
                'withholding_tax'        => $dispWithholding,
                'income_wht'             => (float)$formattedIncomes->sum('withholding_amount'),
                'income_net'             => (float)$formattedIncomes->sum('net_total'),
                'net_disbursed'          => $totalNetDisbursed,
                'ocr_base'               => $ocrPurchasesBase,
                'ocr_vat'                => $ocrInputVat,
                'ocr_total'              => round($ocrPurchasesBase + $ocrInputVat, 2),
                'income_count'           => $formattedIncomes->count(),
                'expense_count'          => $formattedExpenses->count(),
                'ocr_count'              => $formattedOcrItems->count(),
                'total_count'            => $formattedIncomes->count() + $formattedExpenses->count() + $formattedOcrItems->count(),
            ],
            'incomes'   => $formattedIncomes,
            'expenses'  => $formattedExpenses,
            'ocr_items' => $formattedOcrItems,
        ]);
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

            // Also link / settle IncomeRecord if VAT or both
            if (in_array($taxType, ['vat', 'both']) && Schema::hasTable('income_records')) {
                $incPayload = ['vat_settlement_id' => $settlement->id];
                if ($payNow) {
                    $incPayload['vat_settled']    = true;
                    $incPayload['tax_settled_at'] = now();
                }
                \App\Models\IncomeRecord::where('vat_settled', false)
                    ->where(function ($iq) use ($periodFrom, $periodTo) {
                        $iq->whereBetween('income_date', [$periodFrom, $periodTo])
                           ->orWhere(function ($sub) use ($periodFrom, $periodTo) {
                               $sub->whereNull('income_date')->whereBetween('receipt_date', [$periodFrom, $periodTo]);
                           });
                    })
                    ->update($incPayload);
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

            // Mark linked income records as settled based on tax_type
            if (in_array($taxType, ['vat', 'both']) && Schema::hasTable('income_records')) {
                \App\Models\IncomeRecord::where('vat_settlement_id', $settlement->id)->update([
                    'vat_settled'    => true,
                    'tax_settled_at' => now(),
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

            if (in_array($taxType, ['vat', 'both']) && Schema::hasTable('income_records')) {
                \App\Models\IncomeRecord::where('vat_settlement_id', $settlement->id)
                    ->where('vat_settled', false)
                    ->update(['vat_settlement_id' => null]);
            }

            $settlement->update(['status' => TaxSettlement::STATUS_CANCELLED]);
        });

        return back()->with('success', "Tax Payment Request #{$settlement->settlement_number} cancelled. Records released back to active pool.");
    }

    /**
     * General Admin: Clear VAT & Withholding Tax deductions for a specific Ethiopian month.
     * Stamps matching records as settled so the active ledger starts from zero (0.00 ETB).
     */
    public function adminClearMonth(Request $request)
    {
        $this->ensureSchema();

        $user = Auth::user();
        if (!$user) {
            abort(401, 'Please log in to continue.');
        }

        $validated = $request->validate([
            'scope'           => 'nullable|in:month,all_active',
            'ethiopian_year'  => 'nullable|integer|min:2010|max:2050',
            'ethiopian_month' => 'nullable|integer|min:1|max:13',
            'clear_target'    => 'required|in:both,vat,withholding',
            'action_type'     => 'required|in:clear,reopen',
            'reference'       => 'nullable|string|max:100',
            'admin_notes'     => 'nullable|string|max:1000',
            'attachment'      => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $curEth      = EthiopianDate::toEthiopian(now());
        $ethYear     = (int)($request->input('ethiopian_year') ?: $curEth['year']);
        $ethMonth    = (int)($request->input('ethiopian_month') ?: $curEth['month']);
        $scope       = $request->input('scope', 'month');
        $clearTarget = $validated['clear_target'];
        $actionType  = $validated['action_type'];

        $monthRange      = EthiopianDate::monthRange($ethYear, $ethMonth);
        $periodStart     = $monthRange['start'];
        $periodEnd       = $monthRange['end'];
        $periodStartDate = $monthRange['start_date'];
        $periodEndDate   = $monthRange['end_date'];
        $monthLabel  = ($scope === 'all_active')
            ? 'All Current Active Unsettled Liabilities'
            : ((EthiopianDate::MONTHS_EN[$ethMonth] ?? "Month {$ethMonth}") . ' (' . (EthiopianDate::MONTHS_AM[$ethMonth] ?? '') . ') ' . $ethYear);

        $targetLabel = $clearTarget === 'vat' ? 'VAT' : ($clearTarget === 'withholding' ? '3% Withholding Tax' : 'VAT & 3% Withholding Tax');

        if ($actionType === 'reopen') {
            // Re-open previously settled records back to active pool
            $expQuery = ExpenseRequest::where('status', ExpenseRequest::STATUS_PAID);

            if ($scope === 'month') {
                $expQuery->where(function ($q) use ($periodStart, $periodEnd) {
                    $q->whereBetween('created_at', [$periodStart, $periodEnd])
                      ->orWhereBetween('paid_at', [$periodStart, $periodEnd]);
                });
            }

            if ($clearTarget === 'vat') {
                $expQuery->where('vat_settled', true);
            } elseif ($clearTarget === 'withholding') {
                $expQuery->where('withholding_settled', true);
            } else {
                $expQuery->where(function($q) {
                    $q->where('vat_settled', true)->orWhere('withholding_settled', true);
                });
            }

            $records = $expQuery->get();

            // Re-open income records
            $incRecords = collect();
            if (Schema::hasTable('income_records') && in_array($clearTarget, ['vat', 'both', 'withholding'])) {
                $incQuery = \App\Models\IncomeRecord::where('vat_settled', true);
                if ($scope === 'month') {
                    $incQuery->where(function ($iq) use ($periodStartDate, $periodEndDate) {
                        $iq->whereBetween('income_date', [$periodStartDate, $periodEndDate])
                           ->orWhere(function ($sub) use ($periodStartDate, $periodEndDate) {
                               $sub->whereNull('income_date')->whereBetween('receipt_date', [$periodStartDate, $periodEndDate]);
                           });
                    });
                }
                $incRecords = $incQuery->get();
            }

            // Check if any admin clearance settlement exists for this period
            $settleQuery = TaxSettlement::where(function ($q) use ($periodStart, $periodEnd, $ethYear, $ethMonth, $scope) {
                if ($scope === 'month') {
                    $q->where(function ($sq) use ($ethYear, $ethMonth) {
                        $sq->where('ethiopian_year', $ethYear)->where('ethiopian_month', $ethMonth);
                    })->orWhere(function ($sq) use ($periodStart, $periodEnd) {
                        $sq->where('period_from', $periodStart)->where('period_to', $periodEnd);
                    });
                }
            });
            if ($clearTarget !== 'both') {
                $settleQuery->whereIn('tax_type', [$clearTarget, 'both']);
            }
            $existingSettlements = $settleQuery->get();

            if ($records->isEmpty() && $incRecords->isEmpty() && $existingSettlements->isEmpty()) {
                return back()->with('info', "No settled records found for {$monthLabel} to re-open.");
            }

            if ($records->isNotEmpty()) {
                $recordIds = $records->pluck('id');
                if ($clearTarget === 'vat') {
                    ExpenseRequest::whereIn('id', $recordIds)->update([
                        'vat_settled' => false,
                        'vat_settled_at' => null,
                        'vat_settlement_id' => null,
                    ]);
                } elseif ($clearTarget === 'withholding') {
                    ExpenseRequest::whereIn('id', $recordIds)->update([
                        'withholding_settled' => false,
                        'withholding_settled_at' => null,
                        'withholding_settlement_id' => null,
                    ]);
                } else {
                    ExpenseRequest::whereIn('id', $recordIds)->update([
                        'vat_settled' => false,
                        'withholding_settled' => false,
                        'vat_settled_at' => null,
                        'withholding_settled_at' => null,
                        'tax_settled_at' => null,
                        'tax_settlement_id' => null,
                        'vat_settlement_id' => null,
                        'withholding_settlement_id' => null,
                    ]);
                }
            }

            if ($incRecords->isNotEmpty()) {
                \App\Models\IncomeRecord::whereIn('id', $incRecords->pluck('id'))->update([
                    'vat_settled' => false,
                    'vat_settlement_id' => null,
                    'tax_settled_at' => null,
                ]);
            }

            // Mark any settlements for this period as cancelled
            $settleQuery->update(['status' => TaxSettlement::STATUS_CANCELLED]);

            $totalRestored = $records->count() + $incRecords->count();
            return back()->with('success', "Tax records for {$monthLabel} have been re-opened to active unsettled status ({$totalRestored} records restored).");
        }

        // Action: CLEAR (stamp to start from zero)
        $expQuery = ExpenseRequest::where('status', ExpenseRequest::STATUS_PAID);

        if ($scope === 'month') {
            $expQuery->where(function ($q) use ($periodStart, $periodEnd) {
                $q->whereBetween('created_at', [$periodStart, $periodEnd])
                  ->orWhereBetween('paid_at', [$periodStart, $periodEnd]);
            });
        }

        if ($clearTarget === 'vat') {
            $expQuery->where('vat_settled', false)
                     ->whereNull('vat_settlement_id')
                     ->where(function ($q) {
                         $q->where('vat_amount', '>', 0)
                           ->orWhereIn('vat_type', ['exclusive', 'inclusive', 'vat_b']);
                     });
        } elseif ($clearTarget === 'withholding') {
            $expQuery->where('withholding_settled', false)
                     ->whereNull('withholding_settlement_id')
                     ->where(function ($q) {
                         $q->where('has_withholding', true)
                           ->orWhere('withholding_amount', '>', 0);
                     });
        } else {
            $expQuery->where(function ($q) {
                $q->where(function ($sub) {
                    $sub->where('vat_settled', false)->whereNull('vat_settlement_id')
                        ->where(function ($v) { $v->where('vat_amount', '>', 0)->orWhereIn('vat_type', ['exclusive', 'inclusive', 'vat_b']); });
                })->orWhere(function ($sub) {
                    $sub->where('withholding_settled', false)->whereNull('withholding_settlement_id')
                        ->where(function ($w) { $w->where('has_withholding', true)->orWhere('withholding_amount', '>', 0); });
                });
            });
        }

        $records = $expQuery->orderBy('created_at')->get();

        // Income Records for VAT / Both
        $incRecords = collect();
        if (Schema::hasTable('income_records') && in_array($clearTarget, ['vat', 'both', 'withholding'])) {
            $incQuery = \App\Models\IncomeRecord::where('vat_settled', false);
            if ($scope === 'month') {
                $incQuery->where(function ($iq) use ($periodStartDate, $periodEndDate) {
                    $iq->whereBetween('income_date', [$periodStartDate, $periodEndDate])
                       ->orWhere(function ($sub) use ($periodStartDate, $periodEndDate) {
                           $sub->whereNull('income_date')->whereBetween('receipt_date', [$periodStartDate, $periodEndDate]);
                       });
                });
            }
            $incRecords = $incQuery->orderBy('income_date')->get();
        }

        if ($records->isEmpty() && $incRecords->isEmpty()) {
            return back()->with('info', "The active {$targetLabel} liability for {$monthLabel} is already at ZERO (0.00 ETB). No unsettled deductions or output VAT exist.");
        }

        // Calculate amounts
        $expBase = $records->sum(fn($i) => (float)($i->gross_amount > 0 ? $i->gross_amount : $i->amount));
        $incBase = $incRecords->sum(function ($inc) {
            return (float)($inc->unit_price > 0 && $inc->quantity > 0 ? ($inc->unit_price * $inc->quantity) : $inc->amount);
        });
        $totalBase = round($expBase + $incBase, 2);

        $inputVatSum = $records->sum(function ($item) {
            if ($item->vat_settled) return 0.0;
            if ((float)$item->vat_amount > 0 && ($item->vat_type ?? '') === 'exclusive') return (float)$item->vat_amount;
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

        $outputVatSum = $incRecords->sum(function ($inc) {
            $gross = (float)($inc->unit_price > 0 && $inc->quantity > 0 ? ($inc->unit_price * $inc->quantity) : $inc->amount);
            return round($gross * 0.15, 2);
        });

        $vatSum = 0.0;
        if (in_array($clearTarget, ['vat', 'both'])) {
            $vatSum = $outputVatSum > 0 ? $outputVatSum : $inputVatSum;
        }

        $expWht = $records->sum(function ($item) {
            if ($item->withholding_settled) return 0.0;
            return (float)$item->calculated_withholding_amount;
        });
        $incWht = $incRecords->sum(function ($inc) {
            if ($inc->vat_settled) return 0.0;
            $gross = (float)($inc->unit_price > 0 && $inc->quantity > 0 ? ($inc->unit_price * $inc->quantity) : $inc->amount);
            return round($gross * 0.03, 2);
        });
        $whtSum = round($expWht + $incWht, 2);

        $totalPayable = 0.0;
        if ($clearTarget === 'vat') {
            $totalPayable = max(0.0, round($outputVatSum - $inputVatSum, 2));
            if ($totalPayable == 0.0 && $outputVatSum > 0) $totalPayable = $outputVatSum;
            $whtSum = 0.0;
        } elseif ($clearTarget === 'withholding') {
            $totalPayable = $whtSum;
            $vatSum = 0.0;
        } else {
            $netVat = max(0.0, round($outputVatSum - $inputVatSum, 2));
            if ($netVat == 0.0 && $outputVatSum > 0) $netVat = $outputVatSum;
            $totalPayable = round($netVat + $whtSum, 2);
        }

        $totalCount = $records->count() + $incRecords->count();

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $filename = 'tax_clearance_' . time() . '_' . Str::random(6) . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('uploads/tax_receipts'), $filename);
            $attachmentPath = 'uploads/tax_receipts/' . $filename;
        }

        $allMinDate = collect([$records->min('created_at'), $incRecords->min('income_date')])->filter()->min();
        $allMaxDate = collect([$records->max('created_at'), $incRecords->max('income_date')])->filter()->max();
        $effStart = ($scope === 'all_active') ? ($allMinDate ?: now()) : $periodStart;
        $effEnd   = ($scope === 'all_active') ? ($allMaxDate ?: now()) : $periodEnd;

        return DB::transaction(function () use (
            $records, $incRecords, $clearTarget, $totalBase, $vatSum, $whtSum, $totalPayable,
            $totalCount, $effStart, $effEnd, $ethYear, $ethMonth, $scope, $monthLabel,
            $user, $attachmentPath, $validated, $targetLabel
        ) {
            $prefix = $clearTarget === 'vat' ? 'CLR-VAT-' : ($clearTarget === 'withholding' ? 'CLR-WHT-' : 'CLR-TAX-');
            $settlementNumber = $prefix . $ethYear . str_pad($ethMonth, 2, '0', STR_PAD_LEFT) . '-' . str_pad(rand(100, 999), 3, '0', STR_PAD_LEFT);
            while (TaxSettlement::where('settlement_number', $settlementNumber)->exists()) {
                $settlementNumber = $prefix . $ethYear . str_pad($ethMonth, 2, '0', STR_PAD_LEFT) . '-' . str_pad(rand(100, 999), 3, '0', STR_PAD_LEFT);
            }

            $customRef = !empty($validated['reference']) ? $validated['reference'] : 'TAX-CLEARED-START-ZERO';
            $notes = "Cleared to start from zero for {$monthLabel} by {$user->name}." . (!empty($validated['admin_notes']) ? " Note: " . $validated['admin_notes'] : "");

            $settlement = TaxSettlement::create([
                'settlement_number'         => $settlementNumber,
                'tax_type'                  => $clearTarget,
                'ethiopian_year'            => $ethYear,
                'ethiopian_month'           => $scope === 'month' ? $ethMonth : null,
                'period_from'               => $effStart,
                'period_to'                 => $effEnd,
                'total_base_amount'         => $totalBase,
                'vat_amount'                => $vatSum,
                'withholding_amount'        => $whtSum,
                'total_tax_paid'            => $totalPayable,
                'records_count'             => $totalCount,
                'status'                    => TaxSettlement::STATUS_PAID,
                'finance_head_id'           => $user->id,
                'assigned_finance_staff_id' => $user->id,
                'paid_by'                   => $user->id,
                'paid_at'                   => now(),
                'payment_method'            => 'admin_cleared',
                'payment_reference'         => $customRef,
                'attachment'                => $attachmentPath,
                'payment_notes'             => $notes,
            ]);

            $recordIds = $records->pluck('id');
            if ($recordIds->isNotEmpty()) {
                if ($clearTarget === 'vat') {
                    ExpenseRequest::whereIn('id', $recordIds)->update([
                        'vat_settled'       => true,
                        'vat_settled_at'    => now(),
                        'vat_settlement_id' => $settlement->id,
                        'tax_settled_at'    => now(),
                    ]);
                } elseif ($clearTarget === 'withholding') {
                    ExpenseRequest::whereIn('id', $recordIds)->update([
                        'withholding_settled'       => true,
                        'withholding_settled_at'    => now(),
                        'withholding_settlement_id' => $settlement->id,
                        'tax_settled_at'            => now(),
                    ]);
                } else {
                    ExpenseRequest::whereIn('id', $recordIds)->update([
                        'vat_settled'               => true,
                        'withholding_settled'       => true,
                        'vat_settled_at'            => now(),
                        'withholding_settled_at'    => now(),
                        'tax_settled_at'            => now(),
                        'tax_settlement_id'         => $settlement->id,
                        'vat_settlement_id'         => $settlement->id,
                        'withholding_settlement_id' => $settlement->id,
                    ]);
                }
            }

            if ($incRecords->isNotEmpty() && in_array($clearTarget, ['vat', 'both', 'withholding'])) {
                \App\Models\IncomeRecord::whereIn('id', $incRecords->pluck('id'))->update([
                    'vat_settled'       => true,
                    'vat_settlement_id' => $settlement->id,
                    'tax_settled_at'    => now(),
                ]);
            }

            return back()->with('success', "{$targetLabel} for {$monthLabel} has been cleared to ZERO. {$totalCount} records marked as settled. Ref: {$settlementNumber}");
        });
    }

    /**
     * Export Financial Movements & Tax Audit to Excel (.xlsx) or CSV fallback.
     * Supported Types:
     * 1. 'sales': Exact 13 Columns e-Tax Sales Format
     *    Seller TIN | Buyer TIN | Buyer Name | Transaction | Receipt Category | Receipt Used | MRC | Receipt No | Commodity Type | Quantity | Price | Receipt Date | Encoded By
     * 2. 'purchase_tas': Exact 9 Columns e-Tax Purchase Declaration Format
     *    Purchaser TIN | Seller TIN | Amount | Receipt No | Receipt Date | Encoding Date | MRC | PurcType | Encoded By
     * 3. 'purchase_vat': ERCA 15 Columns Purchase VAT Report
     */
    public function exportExcel(Request $request)
    {
        $this->ensureSchema();

        $type = strtolower((string)$request->input('type', 'sales'));
        $curEth = EthiopianDate::toEthiopian(now());
        $year  = (int)($request->input('ethiopian_year') ?: $curEth['year']);
        $rawMonth = $request->input('ethiopian_month');
        $month = ($rawMonth !== null && $rawMonth !== '' && $rawMonth !== 'all') ? (int)$rawMonth : null;

        if ($month && $month >= 1 && $month <= 13) {
            $mRange = EthiopianDate::monthRange($year, $month);
            $startDate = $mRange['start_date'];
            $endDate   = $mRange['end_date'];
            $monthSuffix = '_' . $year . '_M' . str_pad($month, 2, '0', STR_PAD_LEFT);
        } else {
            $rStart = EthiopianDate::monthRange($year, 1);
            $rEnd   = EthiopianDate::monthRange($year, 13);
            $startDate = $rStart['start_date'];
            $endDate   = $rEnd['end_date'];
            $monthSuffix = '_' . $year . '_All';
        }

        // Explicit override if custom dates supplied
        $df = $request->input('from_date') ?: ($request->input('date_from') ?: $request->input('start_date'));
        $dt = $request->input('to_date') ?: ($request->input('date_to') ?: $request->input('end_date'));
        if (!empty($df) && !empty($dt)) {
            $startDate = $df;
            $endDate   = $dt;
        }

        if ($type === 'sales') {
            return $this->exportSalesExcel($request, $startDate, $endDate, $year, $month, $monthSuffix);
        } elseif ($type === 'purchase_tas' || $type === 'purchases_tas' || $type === 'tas') {
            return $this->exportPurchaseTasExcel($request, $startDate, $endDate, $year, $month, $monthSuffix);
        } else {
            return $this->exportPurchaseVatExcel($request, $startDate, $endDate, $year, $month, $monthSuffix);
        }
    }

    /**
     * Export Sales Excel (13 Columns e-Tax format).
     */
    protected function exportSalesExcel(Request $request, string $startDate, string $endDate, int $year, ?int $month, string $monthSuffix)
    {
        $filename = 'Sales_Report' . $monthSuffix . '_' . now()->format('Ymd_His') . '.xlsx';
        $tempPath = storage_path('app/sales_export_' . uniqid() . '.xlsx');

        $headers = [
            'Seller TIN',
            'Buyer TIN',
            'Buyer Name',
            'Transaction',
            'Receipt Category',
            'Receipt Used',
            'MRC',
            'Receipt No',
            'Commodity Type',
            'Quantity',
            'Price',
            'Receipt Date',
            'Encoded By',
        ];

        $incomes = collect();
        if (Schema::hasTable('income_records')) {
            $incomes = IncomeRecord::with(['project', 'createdBy', 'bankAccount', 'chartOfAccount'])
                ->where(function ($iq) use ($startDate, $endDate) {
                    $iq->whereBetween('income_date', [$startDate, $endDate])
                       ->orWhere(function ($sub) use ($startDate, $endDate) {
                           $sub->whereNull('income_date')->whereBetween('receipt_date', [$startDate, $endDate]);
                       });
                })
                ->orderBy('income_date', 'asc')
                ->orderBy('id', 'asc')
                ->get();
        }

        $defaultSellerTin = SystemSetting::get('company_tin', SystemSetting::get('purchaser_tin', '38480010'));
        $defaultSellerDigits = (int)preg_replace('/^0+/', '', preg_replace('/[^0-9]/', '', (string)$defaultSellerTin));
        if (!$defaultSellerDigits) $defaultSellerDigits = 38480010;

        $rows = [];
        foreach ($incomes as $inc) {
            // Col A: Seller TIN
            $rawSeller = $inc->seller_tin ?: $defaultSellerTin;
            $sellerDigits = preg_replace('/^0+/', '', preg_replace('/[^0-9]/', '', (string)$rawSeller));
            $sellerTinVal = ($sellerDigits !== '') ? (int)$sellerDigits : $defaultSellerDigits;

            // Col B: Buyer TIN
            $rawBuyer = (string)($inc->buyer_tin ?? '');
            $buyerDigits = preg_replace('/^0+/', '', preg_replace('/[^0-9]/', '', $rawBuyer));
            $buyerTinVal = ($buyerDigits !== '') ? (int)$buyerDigits : (trim($rawBuyer) ?: '');

            // Col C: Buyer Name
            $buyerName = trim((string)$inc->buyer_name);
            if (empty($buyerName) && $inc->project) {
                $buyerName = trim((string)($inc->project->client_name ?: $inc->project->name));
            }
            if (empty($buyerName)) {
                $buyerName = trim((string)$inc->payer) ?: 'Walk-in Customer';
            }

            // Col D: Transaction
            $transaction = trim((string)($inc->transaction_type ?: 'Cash Sales'));

            // Col E: Receipt Category
            $receiptCategory = trim((string)($inc->receipt_category ?: 'Cash Sales Fiscal Receipt'));

            // Col F: Receipt Used
            $receiptUsed = trim((string)($inc->receipt_used ?: 'Fiscal Cash Register'));

            // Col G: MRC
            $mrc = trim((string)($inc->mrc_no ?? ''));

            // Col H: Receipt No
            $receiptNo = $inc->formatted_receipt_no ?: trim((string)($inc->receipt_no ?: ($inc->income_no ?: ('INC-' . $inc->id))));

            // Col I: Commodity Type
            $commodityType = trim((string)($inc->commodity_type ?? ''));
            if (empty($commodityType)) {
                $cat = strtolower((string)$inc->category);
                $commodityType = in_array($cat, ['construction', 'goods']) ? 'Goods' : 'Services';
            }

            // Col J: Quantity
            $qtyVal = round((float)($inc->quantity > 0 ? $inc->quantity : 1.00), 2);

            // Col K: Price
            $priceVal = round((float)($inc->unit_price > 0 ? $inc->unit_price : $inc->amount), 2);

            // Col L: Receipt Date (Format: DD/MM/YYYY EC)
            $rDate = $inc->receipt_date ?: $inc->income_date;
            $receiptDateFormatted = $rDate ? EthiopianDate::formatDateEc($rDate, 'DD/MM/YYYY EC') : EthiopianDate::formatDateEc(now(), 'DD/MM/YYYY EC');

            // Col M: Encoded By
            $encodedBy = trim((string)($inc->encoded_by ?: ($request->input('encoded_by') ?: (
                SystemSetting::get('etax_encoded_by') ?: (
                    $inc->createdBy && !in_array(strtolower($inc->createdBy->name), ['admin', 'global admin', 'system'])
                        ? strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $inc->createdBy->name))
                        : 'wechacha'
                )
            ))));
            if (empty($encodedBy)) $encodedBy = 'wechacha';

            $rows[] = [
                $sellerTinVal,
                $buyerTinVal,
                $buyerName,
                $transaction,
                $receiptCategory,
                $receiptUsed,
                $mrc,
                $receiptNo,
                $commodityType,
                $qtyVal,
                $priceVal,
                $receiptDateFormatted,
                $encodedBy,
            ];
        }

        return $this->writeExcelFile($headers, $rows, $tempPath, $filename);
    }

    /**
     * Export Purchase TAS Excel (9 Columns e-Tax format).
     * Purchaser TIN | Seller TIN | Amount | Receipt No | Receipt Date | Encoding Date | MRC | PurcType | Encoded By
     */
    protected function exportPurchaseTasExcel(Request $request, string $startDate, string $endDate, int $year, ?int $month, string $monthSuffix)
    {
        $filename = 'Purchase_TAS' . $monthSuffix . '_' . now()->format('Ymd_His') . '.xlsx';
        $tempPath = storage_path('app/purch_tas_' . uniqid() . '.xlsx');

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

        $ocrItems = collect();
        if (Schema::hasTable('receipt_items')) {
            $ocrItems = ReceiptItem::with(['receipt', 'receipt.project', 'receipt.uploader'])
                ->whereHas('receipt', fn($rq) => $rq->whereBetween('receipt_date', [$startDate, $endDate]))
                ->orderBy('id', 'asc')
                ->get();
        }

        $standaloneReceipts = collect();
        if (Schema::hasTable('receipts')) {
            $standaloneReceipts = Receipt::with(['project', 'uploader'])
                ->whereBetween('receipt_date', [$startDate, $endDate])
                ->doesntHave('items')
                ->orderBy('id', 'asc')
                ->get();
        }

        $defaultPurchaserTin = SystemSetting::get('purchaser_tin', SystemSetting::get('company_tin', '38480010'));
        $defaultPurchaserDigits = (int)preg_replace('/^0+/', '', preg_replace('/[^0-9]/', '', (string)$defaultPurchaserTin));
        if (!$defaultPurchaserDigits) $defaultPurchaserDigits = 38480010;

        $rows = [];
        foreach ($ocrItems as $item) {
            $r = $item->receipt;
            $buyerTin = $r ? ($r->buyer_tin ?: $defaultPurchaserTin) : $defaultPurchaserTin;
            $buyerDigits = preg_replace('/^0+/', '', preg_replace('/[^0-9]/', '', (string)$buyerTin));
            $purchaserTinVal = ($buyerDigits !== '') ? (int)$buyerDigits : $defaultPurchaserDigits;

            $rawTin = $r ? (string)$r->vendor_tin : '';
            $sellerDigits = preg_replace('/^0+/', '', preg_replace('/[^0-9]/', '', $rawTin));
            $sellerTinVal = ($sellerDigits !== '') ? (int)$sellerDigits : (trim($rawTin) ?: '');

            $amountVal = (float)($item->value_after_vat > 0 ? $item->value_after_vat : ($item->total_value > 0 ? $item->total_value : ($r ? $r->total_amount : 0)));
            if ($item->total_value > 0 && $item->vat_amount > 0) {
                $calcBase = round($item->total_value / 1.15, 2);
                if (abs(($item->total_value - $item->vat_amount) - $calcBase) <= 0.05 && abs(round($calcBase * 0.15, 2) - $item->vat_amount) <= 0.05) {
                    $amountVal = (float)$item->total_value;
                }
            }
            $amountVal = round($amountVal, 2);

            $receiptNo = '';
            if ($r) {
                if (!empty($r->fs_no_raw)) {
                    $receiptNo = trim($r->fs_no_raw);
                } elseif (!empty($r->fs_no)) {
                    $cleanFs = trim($r->fs_no);
                    if (preg_match('/^fs/i', $cleanFs)) {
                        $receiptNo = strtoupper($cleanFs);
                    } elseif (preg_match('/^\d+$/', $cleanFs)) {
                        $receiptNo = 'FS' . str_pad($cleanFs, 8, '0', STR_PAD_LEFT);
                    } else {
                        $receiptNo = $cleanFs;
                    }
                } else {
                    $receiptNo = trim((string)$r->receipt_number);
                }
            }

            $parsed = is_string($r->parsed_data ?? null) ? (json_decode($r->parsed_data, true) ?: []) : (array)($r->parsed_data ?? []);
            $receiptDate = !empty($parsed['receipt_date'])
                ? EthiopianDate::formatDateEc($parsed['receipt_date'], 'DD/MM/YYYY EC')
                : ($r && $r->receipt_date ? EthiopianDate::formatDateEc($r->receipt_date, 'DD/MM/YYYY EC') : EthiopianDate::formatDateEc(now(), 'DD/MM/YYYY EC'));
            $encodingDate = !empty($parsed['encoding_date'])
                ? EthiopianDate::formatDateEc($parsed['encoding_date'], 'DD/MM/YYYY EC')
                : (($r && $r->created_at) ? EthiopianDate::formatDateEc($r->created_at, 'DD/MM/YYYY EC') : EthiopianDate::formatDateEc(now(), 'DD/MM/YYYY EC'));
            $mrc = $r && !empty($r->mrc_no) ? trim((string)$r->mrc_no) : (!empty($parsed['mrc']) ? trim((string)$parsed['mrc']) : '');

            $purcNorm = QcService::normalizePurchaseType(
                $item->purchase_type ?: ($item->vat_category ?: (!empty($parsed['purc_type']) ? $parsed['purc_type'] : ($r->category ?? null))),
                ($item->item_description ?? '') . ' ' . ($r ? $r->vendor_name : '')
            );
            $purcType = $purcNorm['type'];

            $encodedBy = $request->input('encoded_by') ?: (
                !empty($parsed['encoded_by']) ? strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $parsed['encoded_by'])) : (
                    SystemSetting::get('etax_encoded_by') ?: (
                        $r && $r->uploader && !in_array(strtolower($r->uploader->name), ['admin', 'global admin', 'system'])
                            ? strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $r->uploader->name))
                            : 'wechacha'
                    )
                )
            );
            if (empty($encodedBy)) $encodedBy = 'wechacha';

            $rows[] = [
                $purchaserTinVal,
                $sellerTinVal,
                $amountVal,
                $receiptNo,
                $receiptDate,
                $encodingDate,
                $mrc,
                $purcType,
                $encodedBy,
            ];
        }

        foreach ($standaloneReceipts as $r) {
            $parsed = is_string($r->parsed_data ?? null) ? (json_decode($r->parsed_data, true) ?: []) : (array)($r->parsed_data ?? []);
            $buyerTin = $r->buyer_tin ?: (!empty($parsed['purchaser_tin']) ? $parsed['purchaser_tin'] : $defaultPurchaserTin);
            $buyerDigits = preg_replace('/^0+/', '', preg_replace('/[^0-9]/', '', (string)$buyerTin));
            $purchaserTinVal = ($buyerDigits !== '') ? (int)$buyerDigits : $defaultPurchaserDigits;

            $rawTin = (string)($r->vendor_tin ?: (!empty($parsed['seller_tin']) ? $parsed['seller_tin'] : ''));
            $sellerDigits = preg_replace('/^0+/', '', preg_replace('/[^0-9]/', '', $rawTin));
            $sellerTinVal = ($sellerDigits !== '') ? (int)$sellerDigits : (trim($rawTin) ?: '');

            $amountVal = round((float)$r->total_amount, 2);

            $receiptNo = trim((string)($r->fs_no_raw ?: ($r->fs_no ?: ($parsed['receipt_no'] ?? $r->receipt_number))));
            if (!empty($r->fs_no) && preg_match('/^\d+$/', $r->fs_no)) {
                $receiptNo = 'FS' . str_pad($r->fs_no, 8, '0', STR_PAD_LEFT);
            }

            $receiptDate = !empty($parsed['receipt_date'])
                ? EthiopianDate::formatDateEc($parsed['receipt_date'], 'DD/MM/YYYY EC')
                : ($r->receipt_date ? EthiopianDate::formatDateEc($r->receipt_date, 'DD/MM/YYYY EC') : EthiopianDate::formatDateEc(now(), 'DD/MM/YYYY EC'));
            $encodingDate = !empty($parsed['encoding_date'])
                ? EthiopianDate::formatDateEc($parsed['encoding_date'], 'DD/MM/YYYY EC')
                : ($r->created_at ? EthiopianDate::formatDateEc($r->created_at, 'DD/MM/YYYY EC') : EthiopianDate::formatDateEc(now(), 'DD/MM/YYYY EC'));
            $mrc = trim((string)($r->mrc_no ?: (!empty($parsed['mrc']) ? $parsed['mrc'] : '')));

            $purcNorm = QcService::normalizePurchaseType(!empty($parsed['purc_type']) ? $parsed['purc_type'] : $r->category, ($r->description ?? '') . ' ' . $r->vendor_name);
            $purcType = $purcNorm['type'];

            $encodedBy = !empty($parsed['encoded_by']) ? strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $parsed['encoded_by'])) : (
                $r->uploader && !in_array(strtolower($r->uploader->name), ['admin', 'global admin', 'system'])
                    ? strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $r->uploader->name))
                    : 'wechacha'
            );
            if (empty($encodedBy)) $encodedBy = 'wechacha';

            $rows[] = [
                $purchaserTinVal,
                $sellerTinVal,
                $amountVal,
                $receiptNo,
                $receiptDate,
                $encodingDate,
                $mrc,
                $purcType,
                $encodedBy,
            ];
        }

        // Fallback to paid expense requests if no receipts present
        if (empty($rows) && Schema::hasTable('expense_requests')) {
            $expenses = ExpenseRequest::with(['user', 'bankAccount', 'chartOfAccount', 'paidBy'])
                ->where('status', ExpenseRequest::STATUS_PAID)
                ->whereBetween('created_at', [Carbon::parse($startDate)->startOfDay(), Carbon::parse($endDate)->endOfDay()])
                ->where('vat_type', '!=', 'vat_b')
                ->orderBy('created_at', 'asc')
                ->get();

            foreach ($expenses as $exp) {
                $gross = (float)($exp->gross_amount > 0 ? $exp->gross_amount : $exp->amount);
                $rows[] = [
                    $defaultPurchaserDigits,
                    '',
                    round($gross, 2),
                    $exp->payment_reference ?: $exp->request_number,
                    $exp->paid_at ? EthiopianDate::formatDateEc($exp->paid_at, 'DD/MM/YYYY EC') : EthiopianDate::formatDateEc($exp->created_at, 'DD/MM/YYYY EC'),
                    EthiopianDate::formatDateEc($exp->created_at, 'DD/MM/YYYY EC'),
                    '',
                    'PUR_GD',
                    'wechacha',
                ];
            }
        }

        return $this->writeExcelFile($headers, $rows, $tempPath, $filename);
    }

    /**
     * Export Purchase VAT Report (ERCA 15 Columns).
     */
    protected function exportPurchaseVatExcel(Request $request, string $startDate, string $endDate, int $year, ?int $month, string $monthSuffix)
    {
        $filename = 'Purchase_VAT_Report' . $monthSuffix . '_' . now()->format('Ymd_His') . '.xlsx';
        $tempPath = storage_path('app/purch_vat_' . uniqid() . '.xlsx');

        $headers = [
            "VAT CATEGORY\n(G=GOODS;S=SERVICES)",
            "CALENDAR TYPE\n(E=ETHIOPIAN;G=GREGORIAN)",
            "Types of purchase.\n1 = Taxable-local Purchase of Capital Assets (Line No. 65)\n2 = Taxable-imported Purchase of Capital Assets (Line No. 75)\n3 = Taxable-local Purchase of Inputs (Line No. 100)\n4 = Taxable-imported Purchase of Inputs (Line No. 110)\n5 = Taxable-general Expense Inputs Purchase (Line No. 120)\n6 = Tax Exempted-purchase with no vat or uncollectible inputs\n(Please type 1-6). Mandatory.",
            "TIN\nMandatory for local VAT",
            "Seller name\nRegistered Business Trade Name",
            "Date of purchase\nDispatched Date (DD/MM/YYYY)",
            "MRC Number\nMachine Registration Code",
            "Vat receipt number / FS No\nFiscal Receipt Number",
            "Description / Item\nExact purchased material",
            "Unit of Measure\n(2 KG, 5 LIT, 7 PCS, 9 OTHER, 10 PC)",
            "Quantity\nNumeric only",
            "Unit Price\nNumeric only",
            "Total value\nTaxable Subtotal",
            "VAT (15%)\nValue Added Tax",
            "Value after vat\nGrand Total",
        ];

        $ocrItems = collect();
        if (Schema::hasTable('receipt_items')) {
            $ocrItems = ReceiptItem::with(['receipt', 'receipt.project', 'receipt.uploader'])
                ->whereHas('receipt', fn($rq) => $rq->whereBetween('receipt_date', [$startDate, $endDate]))
                ->orderBy('id', 'asc')
                ->get();
        }

        $rows = [];
        foreach ($ocrItems as $item) {
            $r = $item->receipt;
            $dateFormatted = $r && $r->receipt_date ? EthiopianDate::formatDateEc($r->receipt_date, 'DD/MM/YYYY EC') : EthiopianDate::formatDateEc(now(), 'DD/MM/YYYY EC');
            $rawTin = $r ? (string)$r->vendor_tin : '';
            $rawFs  = $r ? (string)$r->fs_no : '';
            $seller = $r ? (string)$r->vendor_name : '';
            $mrc    = $r ? (string)$r->mrc_no : '';

            $tinDigits = preg_replace('/[^0-9]/', '', $rawTin);
            $tinText = (strlen($tinDigits) === 10) ? $tinDigits : $rawTin;

            $fsDigits = preg_replace('/[^0-9]/', '', $rawFs);
            $fsText = (strlen($fsDigits) === 8) ? $fsDigits : $rawFs;

            $baseVal = (float)$item->total_value;
            $vatVal = (float)$item->vat_amount;
            $afterVal = (float)$item->value_after_vat;
            $unitVal = (float)$item->unit_price;

            if ($baseVal > 0 && $vatVal > 0) {
                $calcBase = round($baseVal / 1.15, 2);
                if (abs(($baseVal - $vatVal) - $calcBase) <= 0.05 && abs(round($calcBase * 0.15, 2) - $vatVal) <= 0.05) {
                    $afterVal = $baseVal;
                    $baseVal = $calcBase;
                    $unitVal = ((float)$item->qty > 0) ? round($calcBase / (float)$item->qty, 2) : $calcBase;
                }
            }

            $rows[] = [
                (string)($item->vat_category ?: 'G'),
                (string)($item->calendar_type ?: 'E'),
                (int)($item->purchase_type ?: 3),
                (string)$tinText,
                $seller,
                $dateFormatted,
                $mrc,
                (string)$fsText,
                (string)($item->item_description ?: 'Material'),
                (string)($item->uom ?: '9'),
                (float)$item->qty,
                $unitVal,
                $baseVal,
                $vatVal,
                $afterVal,
            ];
        }

        if (empty($rows) && Schema::hasTable('expense_requests')) {
            $expenses = ExpenseRequest::with(['user', 'bankAccount', 'chartOfAccount', 'paidBy'])
                ->where('status', ExpenseRequest::STATUS_PAID)
                ->whereBetween('created_at', [Carbon::parse($startDate)->startOfDay(), Carbon::parse($endDate)->endOfDay()])
                ->where('vat_type', '!=', 'vat_b')
                ->where('vat_amount', '>', 0)
                ->orderBy('created_at', 'asc')
                ->get();

            foreach ($expenses as $exp) {
                $gross = (float)($exp->gross_amount > 0 ? $exp->gross_amount : $exp->amount);
                $vatAmount = (float)$exp->vat_amount;
                $base = round($gross - $vatAmount, 2);
                $rows[] = [
                    'G',
                    'E',
                    3,
                    '',
                    $exp->account_name ?: ($exp->user?->name ?: 'Vendor'),
                    $exp->paid_at ? EthiopianDate::formatDateEc($exp->paid_at, 'DD/MM/YYYY EC') : EthiopianDate::formatDateEc($exp->created_at, 'DD/MM/YYYY EC'),
                    '',
                    $exp->payment_reference ?: $exp->request_number,
                    $exp->description ?: 'Purchased Goods',
                    '9',
                    1.0,
                    $base,
                    $base,
                    $vatAmount,
                    $gross,
                ];
            }
        }

        return $this->writeExcelFile($headers, $rows, $tempPath, $filename);
    }

    /**
     * Helper to write OpenSpout Excel (.xlsx) file or stream fallback CSV with BOM.
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
            Log::error('FinanceTaxReportController Excel export error: ' . $e->getMessage());

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
                fputs($handle, "\xEF\xBB\xBF");
                fputcsv($handle, $headers);
                foreach ($rows as $row) {
                    fputcsv($handle, $row);
                }
                fclose($handle);
            }, 200, $csvHeaders);
        }
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
