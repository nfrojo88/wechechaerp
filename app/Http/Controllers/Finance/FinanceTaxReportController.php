<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\ExpenseRequest;
use App\Models\ExpenseHubVatDecision;
use App\Models\TaxSettlement;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\ChartOfAccount;
use App\Models\IncomeRecord;
use App\Models\SystemSetting;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\CreditStorePayment;
use App\Models\CreditStoreLedger;
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
                    if (!Schema::hasColumn('expense_requests', 'is_verified_without_receipt')) {
                        $table->boolean('is_verified_without_receipt')->default(false)->index();
                    }
                    if (!Schema::hasColumn('expense_requests', 'verified_without_receipt_at')) {
                        $table->timestamp('verified_without_receipt_at')->nullable();
                    }
                    if (!Schema::hasColumn('expense_requests', 'verified_without_receipt_by')) {
                        $table->unsignedBigInteger('verified_without_receipt_by')->nullable();
                    }
                    if (!Schema::hasColumn('expense_requests', 'verified_without_receipt_notes')) {
                        $table->string('verified_without_receipt_notes', 500)->nullable();
                    }
                });
            }

            if (Schema::hasTable('expense_hub_vat_decisions')) {
                Schema::table('expense_hub_vat_decisions', function (\Illuminate\Database\Schema\Blueprint $table) {
                    if (!Schema::hasColumn('expense_hub_vat_decisions', 'is_verified_without_receipt')) {
                        $table->boolean('is_verified_without_receipt')->default(false)->index();
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

            if (Schema::hasTable('receipts')) {
                Schema::table('receipts', function (\Illuminate\Database\Schema\Blueprint $table) {
                    if (!Schema::hasColumn('receipts', 'purchasable_type')) {
                        $table->string('purchasable_type')->nullable()->index();
                    }
                    if (!Schema::hasColumn('receipts', 'purchasable_id')) {
                        $table->unsignedBigInteger('purchasable_id')->nullable()->index();
                    }
                    if (!Schema::hasColumn('receipts', 'fs_no')) {
                        $table->string('fs_no')->nullable()->index();
                    }
                    if (!Schema::hasColumn('receipts', 'mrc_no')) {
                        $table->string('mrc_no')->nullable();
                    }
                    if (!Schema::hasColumn('receipts', 'buyer_tin')) {
                        $table->string('buyer_tin')->nullable();
                    }
                    if (!Schema::hasColumn('receipts', 'tin_valid')) {
                        $table->boolean('tin_valid')->default(true);
                    }
                    if (!Schema::hasColumn('receipts', 'fs_no_raw')) {
                        $table->string('fs_no_raw')->nullable();
                    }
                    if (!Schema::hasColumn('receipts', 'fs_no_valid')) {
                        $table->boolean('fs_no_valid')->default(true);
                    }
                    if (!Schema::hasColumn('receipts', 'confidence')) {
                        $table->string('confidence', 50)->default('high');
                    }
                    if (!Schema::hasColumn('receipts', 'confidence_score')) {
                        $table->integer('confidence_score')->default(90);
                    }
                    if (!Schema::hasColumn('receipts', 'needs_review')) {
                        $table->boolean('needs_review')->default(false);
                    }
                    if (!Schema::hasColumn('receipts', 'qc_notes')) {
                        $table->text('qc_notes')->nullable();
                    }
                });
            }

            if (Schema::hasTable('vehicle_fuel_requests')) {
                Schema::table('vehicle_fuel_requests', function (\Illuminate\Database\Schema\Blueprint $table) {
                    if (!Schema::hasColumn('vehicle_fuel_requests', 'request_number')) {
                        $table->string('request_number', 50)->nullable()->index();
                    }
                    if (!Schema::hasColumn('vehicle_fuel_requests', 'fuel_station_name')) {
                        $table->string('fuel_station_name', 255)->nullable();
                    }
                    if (!Schema::hasColumn('vehicle_fuel_requests', 'fuel_station_tin')) {
                        $table->string('fuel_station_tin', 50)->nullable();
                    }
                    if (!Schema::hasColumn('vehicle_fuel_requests', 'fs_no')) {
                        $table->string('fs_no', 50)->nullable();
                    }
                    if (!Schema::hasColumn('vehicle_fuel_requests', 'mrc_no')) {
                        $table->string('mrc_no', 50)->nullable();
                    }
                });
            }

            if (Schema::hasTable('receipt_items')) {
                Schema::table('receipt_items', function (\Illuminate\Database\Schema\Blueprint $table) {
                    if (!Schema::hasColumn('receipt_items', 'uom')) {
                        $table->string('uom', 50)->default('7');
                    }
                    if (!Schema::hasColumn('receipt_items', 'calendar_type')) {
                        $table->string('calendar_type', 10)->default('E');
                    }
                    if (!Schema::hasColumn('receipt_items', 'purc_type')) {
                        $table->string('purc_type', 50)->nullable();
                    }
                    if (!Schema::hasColumn('receipt_items', 'qty')) {
                        $table->decimal('qty', 15, 2)->default(1.00);
                    }
                    if (!Schema::hasColumn('receipt_items', 'unit_price')) {
                        $table->decimal('unit_price', 15, 2)->default(0.00);
                    }
                });
            }

            if (Schema::hasTable('expense_hub_vat_decisions')) {
                Schema::table('expense_hub_vat_decisions', function (\Illuminate\Database\Schema\Blueprint $table) {
                    if (!Schema::hasColumn('expense_hub_vat_decisions', 'uom')) {
                        $table->string('uom', 20)->default('7');
                    }
                    if (!Schema::hasColumn('expense_hub_vat_decisions', 'calendar_type')) {
                        $table->string('calendar_type', 5)->default('E');
                    }
                    if (!Schema::hasColumn('expense_hub_vat_decisions', 'quantity')) {
                        $table->decimal('quantity', 12, 2)->default(1.00);
                    }
                    if (!Schema::hasColumn('expense_hub_vat_decisions', 'unit_price')) {
                        $table->decimal('unit_price', 18, 2)->default(0.00);
                    }
                    if (!Schema::hasColumn('expense_hub_vat_decisions', 'custom_seller_name')) {
                        $table->string('custom_seller_name', 255)->nullable();
                    }
                    if (!Schema::hasColumn('expense_hub_vat_decisions', 'custom_receipt_date')) {
                        $table->date('custom_receipt_date')->nullable();
                    }
                    if (!Schema::hasColumn('expense_hub_vat_decisions', 'custom_item_description')) {
                        $table->string('custom_item_description', 255)->nullable();
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

            // Sync ExpenseHubVatDecisions that do not yet have a corresponding Receipt record (avoids overwriting user edits)
            if (Schema::hasTable('expense_hub_vat_decisions') && Schema::hasTable('receipts')) {
                $decQuery = \App\Models\ExpenseHubVatDecision::query();
                $decQuery->where(function ($q) {
                    $q->where('vat_status', \App\Models\ExpenseHubVatDecision::STATUS_VAT_APPLIES);
                    if (Schema::hasColumn('expense_hub_vat_decisions', 'has_withholding')) {
                        $q->orWhere('has_withholding', true);
                    }
                });
                $decisions = $decQuery->get();
                foreach ($decisions as $dec) {
                    $pClass = match($dec->source_type) {
                        'expense_request'       => \App\Models\ExpenseRequest::class,
                        'procurement_payment'   => \App\Models\ProcurementPayment::class,
                        'credit_store_payment'  => \App\Models\CreditStorePayment::class,
                        'petty_cash_purchase'   => \App\Models\PettyCashMaterialPurchase::class,
                        'vehicle_fuel_request'  => \App\Models\VehicleFuelRequest::class,
                        default                 => null,
                    };
                    $hasReceipt = false;
                    if ($pClass && Schema::hasColumn('receipts', 'purchasable_type')) {
                        $hasReceipt = \App\Models\Receipt::where('purchasable_type', $pClass)
                            ->where('purchasable_id', $dec->source_id)
                            ->exists();
                    }
                    if (!$hasReceipt && !empty($dec->custom_fs_no)) {
                        $hasReceipt = \App\Models\Receipt::where('fs_no', $dec->custom_fs_no)->exists();
                    }
                    if (!$hasReceipt) {
                        \App\Http\Controllers\Finance\ExpenseHubController::syncVatDecisionToLedger($dec);
                    }
                }
            }

            \App\Http\Controllers\VehicleFuelRequestController::purgeTargetFuelRequest('EXP-FUEL-2026-0273');
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

        // Base Query: Strictly PAID records that have VAT, Withholding Tax, an uploaded Withholding slip, or a Tax Ledger Receipt
        $query = ExpenseRequest::with(['user', 'employee', 'paidBy', 'bankAccount', 'chartOfAccount', 'letter', 'purchaseRequest', 'taxSettlement', 'receipt', 'expenseHubVatDecision'])
            ->where('status', ExpenseRequest::STATUS_PAID)
            ->where(function ($q) {
                $q->where('has_withholding', true)
                  ->orWhere('withholding_amount', '>', 0)
                  ->orWhere('vat_amount', '>', 0)
                  ->orWhereIn('vat_type', ['exclusive', 'inclusive', 'vat_b'])
                  ->orWhere('request_number', 'like', 'EXP-TAX-%')
                  ->orWhereHas('receipt')
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
        $ocrDateFilter = function ($rq) use ($fromDate, $toDate, $periodStartDate, $periodEndDate, $periodStart, $periodEnd, $request) {
            if (!empty($fromDate) && !empty($toDate) && !$request->filled('ethiopian_year') && !$request->filled('ethiopian_month')) {
                $rq->where(function ($sub) use ($fromDate, $toDate) {
                    $sub->whereDate('receipt_date', '>=', Carbon::parse($fromDate))
                        ->whereDate('receipt_date', '<=', Carbon::parse($toDate))
                        ->orWhereDate('created_at', '>=', Carbon::parse($fromDate))
                        ->orWhereDate('created_at', '<=', Carbon::parse($toDate));
                });
            } else {
                $rq->where(function ($sub) use ($periodStartDate, $periodEndDate, $periodStart, $periodEnd) {
                    $sub->whereBetween('receipt_date', [$periodStartDate, $periodEndDate])
                        ->orWhereBetween('receipt_date', [$periodStart, $periodEnd])
                        ->orWhereBetween('created_at', [$periodStart, $periodEnd]);

                    if (Schema::hasTable('expense_requests')) {
                        $sub->orWhereExists(function ($ex) use ($periodStart, $periodEnd) {
                            $ex->select(DB::raw(1))
                               ->from('expense_requests')
                               ->where(function ($w) {
                                   $w->whereColumn('expense_requests.id', 'receipts.purchasable_id')
                                     ->orWhereColumn('expense_requests.request_number', 'receipts.receipt_number');
                               })
                               ->where(function ($mq) use ($periodStart, $periodEnd) {
                                   $mq->whereBetween('paid_at', [$periodStart, $periodEnd])
                                      ->orWhereBetween('created_at', [$periodStart, $periodEnd]);
                               });
                        });
                    }
                });
            }
        };

        $ocrQuery->whereHas('receipt', $ocrDateFilter);

        $ocrItems = $ocrQuery->paginate(20, ['*'], 'ocr_page')->withQueryString();

        $ocrStats = [
            'total_items'   => (int)\App\Models\ReceiptItem::whereHas('receipt', $ocrDateFilter)->count(),
            'total_value'   => (float)\App\Models\ReceiptItem::whereHas('receipt', $ocrDateFilter)->sum('total_value'),
            'total_vat'     => (float)\App\Models\ReceiptItem::whereHas('receipt', $ocrDateFilter)->sum('vat_amount'),
            'total_after'   => (float)\App\Models\ReceiptItem::whereHas('receipt', $ocrDateFilter)->sum('value_after_vat'),
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

        // 2. Creditable Purchases & Input VAT (Exclude "VAT B")
        $ocrItemsInMonth = \App\Models\ReceiptItem::whereHas('receipt', $ocrDateFilter)->get();

        $ocrPurchasesBase = (float)$ocrItemsInMonth->where('vat_amount', '>', 0)->sum('total_value');
        $ocrInputVat      = (float)$ocrItemsInMonth->sum('vat_amount');

        $unsettledIncomeItems  = $monthIncomeItems->filter(fn($inc) => !(bool)$inc->vat_settled);
        $unsettledExpenseItems = $allTaxItems->filter(fn($i) => !(bool)$i->vat_settled && ($i->vat_type ?? '') !== 'vat_b');
        $unsettledWhtItems     = $allTaxItems->filter(fn($i) => !(bool)$i->withholding_settled && ($i->has_withholding || (float)$i->withholding_amount > 0));

        $hasActiveOcr          = ($ocrInputVat > 0) || ($ocrPurchasesBase > 0);
        $hasActiveTransactions = $unsettledIncomeItems->isNotEmpty() || $unsettledExpenseItems->isNotEmpty() || $unsettledWhtItems->isNotEmpty() || $hasActiveOcr;
        $hasPriorSettlement    = ($selectedMonthSettlement !== null);

        $allIncomesSettled  = $monthIncomeItems->isNotEmpty() && $unsettledIncomeItems->isEmpty();
        $allExpensesSettled = ($allTaxItems->isEmpty() || $unsettledExpenseItems->isEmpty()) && !$hasActiveOcr;

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
            $netVatDetails['total_creditable_input'] = round($inputVat + $openingCredit, 2);
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
        $monthlyWhtTrend = [];
        $runningOpeningCredit = 0.0;

        $yearRangeStart = EthiopianDate::monthRange($selectedEthYear, 1)['start'];
        $yearRangeEnd   = EthiopianDate::monthRange($selectedEthYear, 13)['end'];

        $yearWhtSettlements = collect();
        if (Schema::hasTable('tax_settlements')) {
            $yearWhtSettlements = TaxSettlement::where('status', TaxSettlement::STATUS_PAID)
                ->whereIn('tax_type', ['withholding', 'both'])
                ->where(function ($q) use ($selectedEthYear, $yearRangeStart, $yearRangeEnd) {
                    $q->where('ethiopian_year', $selectedEthYear)
                      ->orWhere(function ($sq) use ($yearRangeStart, $yearRangeEnd) {
                          $sq->whereBetween('period_from', [$yearRangeStart, $yearRangeEnd]);
                      });
                })
                ->get();
        }

        $yearWhtExpenses = collect();
        if (Schema::hasTable('expense_requests')) {
            $yearWhtExpenses = ExpenseRequest::where(function ($q) {
                    $q->whereIn('status', [ExpenseRequest::STATUS_PAID, 'paid', 'PAID', 'approved', 'completed'])
                      ->orWhereNotNull('paid_at')
                      ->orWhereNotNull('payment_reference');
                })
                ->whereNotIn('status', [ExpenseRequest::STATUS_REJECTED, 'rejected', 'Rejected'])
                ->where(function ($q) use ($yearRangeStart, $yearRangeEnd) {
                    $q->whereBetween('paid_at', [$yearRangeStart, $yearRangeEnd])
                      ->orWhere(function ($sub) use ($yearRangeStart, $yearRangeEnd) {
                          $sub->whereNull('paid_at')->whereBetween('created_at', [$yearRangeStart, $yearRangeEnd]);
                      })
                      ->orWhereBetween('created_at', [$yearRangeStart, $yearRangeEnd]);
                })
                ->where(function ($q) {
                    $q->where('has_withholding', true)
                      ->orWhere('withholding_amount', '>', 0);
                })
                ->get();
        }

        $yearProcWht = collect();
        if (Schema::hasTable('procurement_payments')) {
            try {
                $yearProcWht = \App\Models\ProcurementPayment::where(function ($q) {
                        $q->where('status', 'paid')->orWhereNotNull('paid_at');
                    })
                    ->where(function ($q) use ($yearRangeStart, $yearRangeEnd) {
                        $q->whereBetween('paid_at', [$yearRangeStart, $yearRangeEnd])
                          ->orWhere(function ($sub) use ($yearRangeStart, $yearRangeEnd) {
                              $sub->whereNull('paid_at')->whereBetween('created_at', [$yearRangeStart, $yearRangeEnd]);
                          });
                    })
                    ->where(function ($q) {
                        $q->where('has_withholding', true)->orWhere('withholding_amount', '>', 0);
                    })
                    ->get();
            } catch (\Throwable $e) {}
        }

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
                        'total_creditable_input' => 0.0,
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
                        'total_creditable_input' => round($mInTotal + $runningOpeningCredit, 2),
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
                    'total_creditable_input' => round($curIn + $curOpening, 2),
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

            // Withholding Tax Monthly Trend Calculation for Month $m
            $mStart = $mRange['start'];
            $mEnd   = $mRange['end'];

            $mWhtSettlement = $yearWhtSettlements->first(function ($s) use ($selectedEthYear, $m, $mRange) {
                return ($s->ethiopian_year == $selectedEthYear && $s->ethiopian_month == $m)
                    || ($s->period_from == $mRange['start'] && $s->period_to == $mRange['end']);
            });

            $mWhtExp = $yearWhtExpenses->filter(function ($item) use ($mStart, $mEnd) {
                $itemDate = $item->paid_at ?: $item->created_at;
                if (!$itemDate) return false;
                $dt = Carbon::parse($itemDate);
                return $dt->betweenIncluded($mStart, $mEnd);
            });

            $mProcExp = $yearProcWht->filter(function ($item) use ($mStart, $mEnd) {
                $itemDate = $item->paid_at ?: $item->created_at;
                if (!$itemDate) return false;
                $dt = Carbon::parse($itemDate);
                return $dt->betweenIncluded($mStart, $mEnd);
            });

            $mWhtBase = 0.0;
            $mWhtTotal = 0.0;
            $mWht2Amt = 0.0;
            $mWht2Count = 0;
            $mWht3Amt = 0.0;
            $mWht3Count = 0;
            $mWht30Amt = 0.0;
            $mWht30Count = 0;
            $mWhtNetDisbursed = 0.0;
            $mWhtSlipsCount = 0;
            $mWhtMissingSlipsCount = 0;
            $mWhtVouchersCount = 0;

            foreach ($mWhtExp as $item) {
                $gross = (float)($item->gross_amount > 0 ? $item->gross_amount : $item->amount);
                $base = (float)($item->base_amount > 0 ? $item->base_amount : $gross);
                if ($item->vat_type === 'inclusive') {
                    $vatRate = (float)($item->vat_rate ?: 15.0);
                    $base = round($gross / (1 + ($vatRate / 100)), 2);
                }

                $wht = (float)$item->calculated_withholding_amount;
                if ($wht <= 0 && (float)($item->withholding_amount ?? 0) > 0) {
                    $wht = round((float)$item->withholding_amount, 2);
                }
                if ($wht <= 0 && $item->has_withholding) {
                    $rate = (float)($item->withholding_rate ?: 3.00);
                    $wht = round($base * ($rate / 100), 2);
                }

                $rate = (float)($item->withholding_rate ?: ($wht > 0 && $base > 0 ? round(($wht / $base) * 100, 1) : 3.00));
                $rRounded = round($rate, 1);

                if ($rRounded == 2.0) {
                    $mWht2Amt += $wht;
                    $mWht2Count++;
                } elseif ($rRounded == 30.0) {
                    $mWht30Amt += $wht;
                    $mWht30Count++;
                } else {
                    $mWht3Amt += $wht;
                    $mWht3Count++;
                }

                $mWhtBase += $base;
                $mWhtTotal += $wht;

                $net = (float)$item->effective_payable_amount;
                if ($net <= 0) {
                    $net = max(0.0, round($base - $wht, 2));
                }
                $mWhtNetDisbursed += $net;

                $hasSlip = !empty($item->withholding_receipt) || !empty($item->withholding_receipt_number);
                if ($hasSlip) {
                    $mWhtSlipsCount++;
                } else {
                    $mWhtMissingSlipsCount++;
                }
                $mWhtVouchersCount++;
            }

            foreach ($mProcExp as $item) {
                $gross = (float)$item->amount;
                $base = (float)($item->base_amount ?: $gross);
                $wht = (float)($item->withholding_amount ?: ($item->has_withholding ? round($base * (($item->withholding_rate ?: 3) / 100), 2) : 0));
                $rate = (float)($item->withholding_rate ?: ($wht > 0 && $base > 0 ? round(($wht / $base) * 100, 1) : 3.00));
                $rRounded = round($rate, 1);

                if ($rRounded == 2.0) {
                    $mWht2Amt += $wht;
                    $mWht2Count++;
                } elseif ($rRounded == 30.0) {
                    $mWht30Amt += $wht;
                    $mWht30Count++;
                } else {
                    $mWht3Amt += $wht;
                    $mWht3Count++;
                }

                $mWhtBase += $base;
                $mWhtTotal += $wht;

                $net = (float)($item->net_amount ?: max(0.0, round($base - $wht, 2)));
                $mWhtNetDisbursed += $net;

                $hasSlip = !empty($item->withholding_receipt) || !empty($item->withholding_receipt_number);
                if ($hasSlip) {
                    $mWhtSlipsCount++;
                } else {
                    $mWhtMissingSlipsCount++;
                }
                $mWhtVouchersCount++;
            }

            $mWhtBase = round($mWhtBase, 2);
            $mWhtTotal = round($mWhtTotal, 2);
            $mWht2Amt = round($mWht2Amt, 2);
            $mWht3Amt = round($mWht3Amt, 2);
            $mWht30Amt = round($mWht30Amt, 2);
            $mWhtNetDisbursed = round($mWhtNetDisbursed, 2);

            $isWhtSettled = ($mWhtSettlement !== null) || ($mWhtVouchersCount > 0 && $mWhtExp->isNotEmpty() && $mWhtExp->every(fn($e) => (bool)$e->withholding_settled));

            if ($isWhtSettled) {
                $whtStatus = 'cleared';
                $whtLabel = 'Remitted to ERCA';
                $whtBadgeClass = 'bg-success text-white';
                $whtTextClass = 'text-success';
            } elseif ($mWhtTotal > 0) {
                $whtStatus = 'pending';
                $whtLabel = 'Pending Remittance';
                $whtBadgeClass = 'bg-warning text-dark';
                $whtTextClass = 'text-warning';
            } else {
                $whtStatus = 'zero';
                $whtLabel = '0.00 ETB (No WHT)';
                $whtBadgeClass = 'bg-light text-muted border';
                $whtTextClass = 'text-muted';
            }

            $monthlyWhtTrend[$m] = [
                'month'                => $m,
                'month_name'           => EthiopianDate::MONTHS_EN[$m],
                'month_am'             => EthiopianDate::MONTHS_AM[$m],
                'start_date'           => $mRange['start_date'],
                'end_date'             => $mRange['end_date'],
                'gregorian_window'     => $mRange['start']->format('M d, Y') . ' – ' . $mRange['end']->format('M d, Y'),
                'base_amount'          => $mWhtBase,
                'total_wht'            => $mWhtTotal,
                'wht_2_amount'         => $mWht2Amt,
                'wht_2_count'          => $mWht2Count,
                'wht_3_amount'         => $mWht3Amt,
                'wht_3_count'          => $mWht3Count,
                'wht_30_amount'        => $mWht30Amt,
                'wht_30_count'         => $mWht30Count,
                'net_disbursed'        => $mWhtNetDisbursed,
                'vouchers_count'       => $mWhtVouchersCount,
                'slips_count'          => $mWhtSlipsCount,
                'missing_slips_count'  => $mWhtMissingSlipsCount,
                'is_current'           => ($m === $selectedEthMonth),
                'is_settled'           => $isWhtSettled,
                'status'               => $whtStatus,
                'label'                => $whtLabel,
                'badge_class'          => $whtBadgeClass,
                'text_class'           => $whtTextClass,
                'settlement_number'    => $mWhtSettlement?->settlement_number,
            ];
        }

        $yearlyWhtTotals = [
            'total_base'         => round((float)collect($monthlyWhtTrend)->sum('base_amount'), 2),
            'total_wht'          => round((float)collect($monthlyWhtTrend)->sum('total_wht'), 2),
            'total_2_amt'        => round((float)collect($monthlyWhtTrend)->sum('wht_2_amount'), 2),
            'total_2_count'      => (int)collect($monthlyWhtTrend)->sum('wht_2_count'),
            'total_3_amt'        => round((float)collect($monthlyWhtTrend)->sum('wht_3_amount'), 2),
            'total_3_count'      => (int)collect($monthlyWhtTrend)->sum('wht_3_count'),
            'total_30_amt'       => round((float)collect($monthlyWhtTrend)->sum('wht_30_amount'), 2),
            'total_30_count'     => (int)collect($monthlyWhtTrend)->sum('wht_30_count'),
            'total_net_disbursed'=> round((float)collect($monthlyWhtTrend)->sum('net_disbursed'), 2),
            'total_transactions' => (int)collect($monthlyWhtTrend)->sum('vouchers_count'),
            'total_slips'        => (int)collect($monthlyWhtTrend)->sum('slips_count'),
            'total_missing_slips'=> (int)collect($monthlyWhtTrend)->sum('missing_slips_count'),
        ];

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
            'monthlyWhtTrend',
            'yearlyWhtTotals',
            'isGeneralAdmin',
            'isMonthVatCleared',
            'isFreshStart',
            'selectedMonthSettlement'
        ));
    }

    /**
     * Dedicated Page: View Financial Movements & Tax Audit with full tables, audit sheet, and filters.
     */
    public function movementsPage(Request $request)
    {
        $this->ensureSchema();

        $curEth = EthiopianDate::toEthiopian(now());
        $selectedEthYear  = (int)($request->input('ethiopian_year') ?: ($request->input('year') ?: $curEth['year']));
        $selectedEthMonth = (int)($request->input('ethiopian_month') ?: ($request->input('month') ?: $curEth['month']));
        if ($selectedEthMonth < 1 || $selectedEthMonth > 13) {
            $selectedEthMonth = $curEth['month'];
        }

        $availableEthYears  = EthiopianDate::getYears();
        $availableEthMonths = EthiopianDate::getMonths();
        $ocrFilter = $request->input('ocr_filter', '');
        $vatOnly = $request->has('vat_only') 
            ? $request->boolean('vat_only') 
            : (!in_array($ocrFilter, ['all', 'tas']));
        $movementsData = $this->calculateMonthMovementsData($selectedEthYear, $selectedEthMonth, $vatOnly);

        // Previous and Next month navigation
        $prevYear  = ($selectedEthMonth === 1) ? $selectedEthYear - 1 : $selectedEthYear;
        $prevMonth = ($selectedEthMonth === 1) ? 13 : $selectedEthMonth - 1;
        $nextYear  = ($selectedEthMonth === 13) ? $selectedEthYear + 1 : $selectedEthYear;
        $nextMonth = ($selectedEthMonth === 13) ? 1 : $selectedEthMonth + 1;

        $activeTab = $request->input('tab', 'income');

        $ocrProjects = \App\Models\Project::orderBy('name')->get();
        $ocrCategories = \App\Models\Receipt::CATEGORIES;

        return view('finance.tax-deductions.movements', compact(
            'selectedEthYear',
            'selectedEthMonth',
            'availableEthYears',
            'availableEthMonths',
            'movementsData',
            'prevYear',
            'prevMonth',
            'nextYear',
            'nextMonth',
            'activeTab',
            'ocrProjects',
            'ocrCategories',
            'vatOnly'
        ));
    }

    /**
     * Download or Print Only Added Receipts in PDF form for Financial Movements & Tax Audit.
     * Includes all verified ERCA fiscal register invoices, scanned receipts, and expense vouchers with receipts on file.
     */
    public function movementsReceiptsPdf(Request $request)
    {
        $this->ensureSchema();

        $curEth = EthiopianDate::toEthiopian(now());
        $selectedEthYear  = (int)($request->input('ethiopian_year') ?: ($request->input('year') ?: $curEth['year']));
        $selectedEthMonth = (int)($request->input('ethiopian_month') ?: ($request->input('month') ?: $curEth['month']));
        if ($selectedEthMonth < 1 || $selectedEthMonth > 13) {
            $selectedEthMonth = $curEth['month'];
        }

        $receiptFilter = strtolower($request->input('type', 'all')); // 'all', 'vat', 'tas'

        // Compute movements data (retrieving all scanned receipts and expenses)
        $movementsData = $this->calculateMonthMovementsData($selectedEthYear, $selectedEthMonth, false);

        // Gather ONLY receipts that have been added
        $addedReceipts = collect();
        $seenKeys = [];

        // 1. Process all OCR / Scanned / Manual Receipts
        $allOcrList = $movementsData['all_ocr_items'] ?? ($movementsData['ocr_items'] ?? []);
        foreach ($allOcrList as $ocr) {
            $baseVal = (float)($ocr['base_value'] ?? 0);
            $vatVal = (float)($ocr['vat_amount'] ?? 0);
            $totalVal = (float)($ocr['total_value'] ?? ($ocr['value_after_vat'] ?? ($baseVal + $vatVal)));
            $isVat = !empty($ocr['is_vat_receipt']) || $vatVal > 0;

            if ($receiptFilter === 'vat' && !$isVat) {
                continue;
            }
            if ($receiptFilter === 'tas' && $isVat) {
                continue;
            }

            $fsNo = !empty($ocr['fs_no']) && $ocr['fs_no'] !== '-' ? trim($ocr['fs_no']) : '';
            $sellerTin = !empty($ocr['seller_tin']) && $ocr['seller_tin'] !== '-' ? trim($ocr['seller_tin']) : (!empty($ocr['vendor_tin']) && $ocr['vendor_tin'] !== '-' ? trim($ocr['vendor_tin']) : '');
            $recId = $ocr['receipt_id'] ?? $ocr['id'] ?? null;
            $reqNo = $ocr['request_number'] ?? '';

            // Unique deduplication key
            $key = $fsNo && $sellerTin ? "{$sellerTin}_{$fsNo}" : ($recId ? "REC_{$recId}" : ($reqNo ?: "IDX_" . count($addedReceipts)));
            if (isset($seenKeys[$key])) {
                continue;
            }
            $seenKeys[$key] = true;

            $fileUrl = $ocr['file_url'] ?? null;
            $isPdf = !empty($ocr['is_pdf']);

            $addedReceipts->push([
                'id'                 => $ocr['id'] ?? null,
                'receipt_id'         => $recId,
                'request_number'     => $reqNo ?: ('REC-' . ($ocr['id'] ?? (count($addedReceipts) + 1))),
                'fs_no'              => $fsNo ?: '-',
                'mrc_no'             => !empty($ocr['mrc_no']) && $ocr['mrc_no'] !== '-' ? $ocr['mrc_no'] : '-',
                'receipt_number'     => $ocr['receipt_number'] ?? '',
                'vendor_name'        => $ocr['vendor_name'] ?? 'Vendor',
                'vendor_tin'         => $sellerTin ?: '-',
                'seller_tin'         => $sellerTin ?: '-',
                'purchaser_tin'      => $ocr['purchaser_tin'] ?? '0054711385',
                'date_ec'            => $ocr['date_ec'] ?? '-',
                'date_gc'            => $ocr['date_gc'] ?? '-',
                'project_name'       => $ocr['project_name'] ?? 'General',
                'category'           => $ocr['category'] ?? 'materials',
                'item_description'   => $ocr['item_description'] ?? 'Purchased Goods/Services',
                'base_value'         => $baseVal,
                'vat_amount'         => $vatVal,
                'total_value'        => $totalVal,
                'is_vat_receipt'     => $isVat,
                'file_url'           => $fileUrl,
                'file_type'          => $ocr['file_type'] ?? ($isPdf ? 'pdf' : 'image'),
                'is_pdf'             => $isPdf,
                'source'             => 'ocr_receipt',
            ]);
        }

        // 2. Also check any expenses that have an attached receipt slip or verified FS No
        $allExpenses = $movementsData['all_expenses'] ?? collect();
        foreach ($allExpenses as $exp) {
            // Must have a receipt added and must NOT be verified without receipt
            if (!empty($exp['is_verified_without_receipt'])) {
                continue;
            }

            $hasReceipt = (!empty($exp['fs_no']) && $exp['fs_no'] !== '-')
                || !empty($exp['receipt_slip_url'])
                || !empty($exp['attachment_url'])
                || !empty($exp['receipt_id']);

            if (!$hasReceipt) {
                continue;
            }

            $baseVal = (float)($exp['base_amount'] ?? 0);
            $vatVal = (float)($exp['vat_amount'] ?? 0);
            $totalVal = (float)($exp['net_disbursed'] ?? ($baseVal + $vatVal));
            $isVat = ($vatVal > 0);

            if ($receiptFilter === 'vat' && !$isVat) {
                continue;
            }
            if ($receiptFilter === 'tas' && $isVat) {
                continue;
            }

            $fsNo = !empty($exp['fs_no']) && $exp['fs_no'] !== '-' ? trim($exp['fs_no']) : '';
            $sellerTin = !empty($exp['seller_tin']) && $exp['seller_tin'] !== '-' ? trim($exp['seller_tin']) : '';
            $recId = $exp['receipt_id'] ?? null;
            $reqNo = $exp['request_number'] ?? '';

            $key = $fsNo && $sellerTin ? "{$sellerTin}_{$fsNo}" : ($recId ? "REC_{$recId}" : ($reqNo ?: "EXP_" . ($exp['id'] ?? count($addedReceipts))));
            if (isset($seenKeys[$key])) {
                continue;
            }
            $seenKeys[$key] = true;

            $fileUrl = $exp['receipt_slip_url'] ?? ($exp['attachment_url'] ?? null);
            $isPdf = !empty($fileUrl) && (str_ends_with(strtolower($fileUrl), '.pdf') || str_contains(strtolower($fileUrl), '.pdf'));

            $addedReceipts->push([
                'id'                 => $exp['id'] ?? null,
                'receipt_id'         => $recId,
                'request_number'     => $reqNo ?: ('EXP-' . ($exp['id'] ?? (count($addedReceipts) + 1))),
                'fs_no'              => $fsNo ?: '-',
                'mrc_no'             => $exp['mrc_no'] ?? '-',
                'receipt_number'     => $exp['payment_reference'] ?? '',
                'vendor_name'        => $exp['payee'] ?? 'Vendor',
                'vendor_tin'         => $sellerTin ?: '-',
                'seller_tin'         => $sellerTin ?: '-',
                'purchaser_tin'      => $exp['purchaser_tin'] ?? '0054711385',
                'date_ec'            => $exp['date_ec'] ?? '-',
                'date_gc'            => $exp['date_gc'] ?? '-',
                'project_name'       => $exp['project_name'] ?? 'General',
                'category'           => $exp['category'] ?? 'materials',
                'item_description'   => $exp['description'] ?? ($exp['item_description'] ?? 'Purchased Goods/Services'),
                'base_value'         => $baseVal,
                'vat_amount'         => $vatVal,
                'total_value'        => $totalVal,
                'is_vat_receipt'     => $isVat,
                'file_url'           => $fileUrl,
                'file_type'          => $isPdf ? 'pdf' : 'image',
                'is_pdf'             => $isPdf,
                'source'             => 'expense_receipt',
            ]);
        }

        // Summary metrics for the added receipts
        $receiptsSummary = [
            'total_count'       => $addedReceipts->count(),
            'vat_count'         => $addedReceipts->where('is_vat_receipt', true)->count(),
            'tas_count'         => $addedReceipts->where('is_vat_receipt', false)->count(),
            'total_base'        => (float)$addedReceipts->sum('base_value'),
            'total_vat'         => (float)$addedReceipts->sum('vat_amount'),
            'total_gross'       => (float)$addedReceipts->sum('total_value'),
            'with_file_count'   => $addedReceipts->filter(fn($r) => !empty($r['file_url']))->count(),
        ];

        $monthInfo = $movementsData['month'] ?? [
            'title'            => "Month {$selectedEthMonth}, {$selectedEthYear} EC",
            'month_name'       => "Month {$selectedEthMonth}",
            'month_am'         => "ወር {$selectedEthMonth}",
            'gregorian_window' => "GC Window",
            'year'             => $selectedEthYear,
            'month'            => $selectedEthMonth,
        ];

        $autoDownload = $request->boolean('download');

        return view('finance.tax-deductions.receipts-pdf', compact(
            'selectedEthYear',
            'selectedEthMonth',
            'monthInfo',
            'addedReceipts',
            'receiptsSummary',
            'receiptFilter',
            'autoDownload'
        ));
    }

    /**
     * AJAX endpoint: Retrieve all itemized financial movements for a specific Ethiopian month.
     * Returns detailed transactions: Incomes (Sales), Expenses/Purchases, OCR Receipts, and Signed Net VAT reconciliation.
     */
    public function monthMovements(Request $request)
    {
        try {
            $this->ensureSchema();

            $curEth = EthiopianDate::toEthiopian(now());
            $year  = (int)($request->input('ethiopian_year') ?: ($request->query('ethiopian_year') ?: $curEth['year']));
            $month = (int)($request->input('ethiopian_month') ?: ($request->query('ethiopian_month') ?: $curEth['month']));
            $vatOnly = $request->has('vat_only') ? $request->boolean('vat_only') : true;

            $data = $this->calculateMonthMovementsData($year, $month, $vatOnly);
            return response()->json($data);
        } catch (\Throwable $e) {
            Log::error("FinanceTaxReportController monthMovements error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Movements error: ' . $e->getMessage(),
                'error'   => 'Movements error: ' . $e->getMessage(),
                'month'   => [
                    'year'             => (int)($request->query('ethiopian_year') ?: 2017),
                    'month'            => (int)($request->query('ethiopian_month') ?: 1),
                    'month_name'       => 'Month',
                    'month_am'         => 'ወር',
                    'title'            => 'Movements',
                    'gregorian_window' => 'N/A',
                    'start_date'       => now()->toDateString(),
                    'end_date'         => now()->toDateString(),
                    'days'             => 30,
                ],
                'summary' => [
                    'sales_base'             => 0,
                    'output_vat'             => 0,
                    'gross_income'           => 0,
                    'purchases_base'         => 0,
                    'input_vat'              => 0,
                    'opening_credit'         => 0,
                    'total_creditable_input' => 0,
                    'net_vat'                => 0,
                    'signed_net_formatted'   => 'ETB 0.00',
                    'status'                 => 'neutral',
                    'label'                  => 'Neutral',
                    'badge_class'            => 'bg-secondary',
                    'text_class'             => 'text-dark',
                    'is_payable'             => false,
                    'is_credit'              => false,
                    'payable_amount'         => 0,
                    'credit_carry_forward'   => 0,
                    'is_cleared'             => false,
                    'is_fresh_start'         => false,
                    'settlement_number'      => null,
                    'withholding_tax'        => 0,
                    'income_wht'             => 0,
                    'income_net'             => 0,
                    'net_disbursed'          => 0,
                    'ocr_base'               => 0,
                    'ocr_vat'                => 0,
                    'ocr_total'              => 0,
                    'income_count'           => 0,
                    'expense_count'          => 0,
                    'ocr_count'              => 0,
                    'total_count'            => 0,
                ],
                'incomes'   => [],
                'expenses'  => [],
                'ocr_items' => [],
            ], 200);
        }
    }

    /**
     * Deduplicate Scanned OCR Receipts (ERCA Cash Register Invoices).
     *
     * DUPLICATE RULE & COMMENT POLICY:
     * A receipt is a duplicate of another if BOTH normalized FS No. and Seller TIN are identical.
     *
     * IMMEDIATE BLOCK RULE:
     * Protect the FIRST original receipt. Immediately block subsequent double receipts
     * so they cannot block or replace the first receipt.
     * Blocked double receipts are strictly excluded from matrix totals and NEVER added to Excel.
     */
    public function deduplicateOcrItemsList(iterable $items): array
    {
        $normalizeFs = function ($rawFs) {
            $raw = trim((string)$rawFs);
            $stripped = preg_replace('/^FS[:\s_#-]*\s*/i', '', $raw);
            $alphanumeric = strtoupper(preg_replace('/[^0-9A-Z]/i', '', $stripped));
            $normalized = ltrim($alphanumeric, '0');
            if ($normalized === '' && $alphanumeric !== '') {
                $normalized = '0';
            }
            return $normalized;
        };

        $normalizeTin = function ($rawTin) {
            $raw = trim((string)$rawTin);
            return strtoupper(preg_replace('/[^0-9A-Z]/i', '', $raw));
        };

        $isFsMissingOrUnreadable = function ($rawFs, $normFs) {
            if ($normFs === '' || $normFs === '0') {
                return true;
            }
            $rawUpper = strtoupper(trim((string)$rawFs));
            return in_array($rawUpper, ['', '-', '--', 'N/A', 'NA', 'NULL', 'NONE', 'UNKNOWN', '?', '0']);
        };

        $isTinMissingOrUnreadable = function ($rawTin, $normTin) {
            if ($normTin === '') {
                return true;
            }
            $rawUpper = strtoupper(trim((string)$rawTin));
            if (in_array($rawUpper, ['', '-', '--', 'N/A', 'NA', 'NULL', 'NONE', 'UNKNOWN', '?'])) {
                return true;
            }
            return strlen($normTin) < 5;
        };

        $groupedByMatch = [];
        $flaggedReviewList = [];
        $uniqueCleanedList = [];

        foreach ($items as $ocr) {
            $rawFs = $ocr['fs_no'] ?? '';
            $rawTin = $ocr['seller_tin'] ?: ($ocr['vendor_tin'] ?? '');

            $normFs = $normalizeFs($rawFs);
            $normTin = $normalizeTin($rawTin);

            $fsMissing = $isFsMissingOrUnreadable($rawFs, $normFs);
            $tinMissing = $isTinMissingOrUnreadable($rawTin, $normTin);

            if ($fsMissing || $tinMissing) {
                $reasons = [];
                if ($fsMissing) $reasons[] = 'Missing or unreadable FS No.';
                if ($tinMissing) $reasons[] = 'Missing or unreadable Seller TIN';
                $ocr['needs_manual_review'] = true;
                $ocr['review_reason'] = implode(' & ', $reasons);
                $ocr['norm_fs'] = $normFs;
                $ocr['norm_tin'] = $normTin;
                $ocr['has_duplicates_blocked'] = false;
                $ocr['blocked_duplicates_count'] = 0;

                $flaggedReviewList[] = $ocr;
                $uniqueCleanedList[] = $ocr;
            } else {
                $ocr['needs_manual_review'] = false;
                $ocr['review_reason'] = null;
                $ocr['norm_fs'] = $normFs;
                $ocr['norm_tin'] = $normTin;

                $matchKey = $normTin . '___' . $normFs;
                $groupedByMatch[$matchKey][] = $ocr;
            }
        }

        $duplicatesRemoved = [];

        foreach ($groupedByMatch as $matchKey => $itemsInGroup) {
            $receiptsInGroup = [];
            foreach ($itemsInGroup as $ocr) {
                $rId = (string)($ocr['receipt_id'] ?: $ocr['id']);
                $receiptsInGroup[$rId][] = $ocr;
            }

            if (count($receiptsInGroup) === 1) {
                foreach ($itemsInGroup as $uniqueItem) {
                    $uniqueItem['has_duplicates_blocked'] = false;
                    $uniqueItem['blocked_duplicates_count'] = 0;
                    $uniqueCleanedList[] = $uniqueItem;
                }
                continue;
            }

            // Multiple receipts found with identical FS No. & Seller TIN:
            // Immediate Block Rule: Protect the FIRST original receipt, immediately block all subsequent double receipts!
            $receiptList = array_values($receiptsInGroup);
            usort($receiptList, function ($itemsA, $itemsB) {
                $a = $itemsA[0];
                $b = $itemsB[0];
                // 1. Manually inspected/edited records take highest priority
                $editA = (int)($a['manually_edited_at'] ?? 0);
                $editB = (int)($b['manually_edited_at'] ?? 0);
                if ($editA !== $editB) {
                    return $editB <=> $editA;
                }
                // 2. Keep the FIRST original receipt: earliest scan / upload timestamp first
                $uploadA = (int)($a['created_timestamp'] ?? 0);
                $uploadB = (int)($b['created_timestamp'] ?? 0);
                if ($uploadA !== $uploadB && $uploadA > 0 && $uploadB > 0) {
                    return $uploadA <=> $uploadB;
                }
                // 3. Receipt date (earliest first)
                $dateA = (int)($a['receipt_timestamp'] ?? 0);
                $dateB = (int)($b['receipt_timestamp'] ?? 0);
                if ($dateA !== $dateB && $dateA > 0 && $dateB > 0) {
                    return $dateA <=> $dateB;
                }
                // 4. Lowest receipt ID (first created in database)
                $idA = (int)($a['receipt_id'] ?? ($a['id'] ?? 0));
                $idB = (int)($b['receipt_id'] ?? ($b['id'] ?? 0));
                if ($idA !== $idB && $idA > 0 && $idB > 0) {
                    return $idA <=> $idB;
                }
                return strcmp((string)($a['id'] ?? ''), (string)($b['id'] ?? ''));
            });

            $keptReceiptItems = $receiptList[0];
            $blockedReceiptGroups = array_slice($receiptList, 1);

            foreach ($keptReceiptItems as $kItem) {
                $kItem['has_duplicates_blocked'] = true;
                $kItem['blocked_duplicates_count'] = count($blockedReceiptGroups);
                $uniqueCleanedList[] = $kItem;
            }

            $keptLeader = $keptReceiptItems[0];
            $keptDateStr = (!empty($keptLeader['receipt_date_ec']) && $keptLeader['receipt_date_ec'] !== '-') ? $keptLeader['receipt_date_ec'] : ($keptLeader['date_gc'] ?? 'No Date');
            $keptRefStr  = $keptLeader['receipt_number'] ?: ('ID #' . $keptLeader['id']);
            $keptTotalAmt = (float)collect($keptReceiptItems)->sum('total_value');
            $keptAmtStr  = number_format($keptTotalAmt, 2);

            $removedReceiptsSummary = [];
            foreach ($blockedReceiptGroups as $blockedItems) {
                $bLeader = $blockedItems[0];
                $bDateStr = (!empty($bLeader['receipt_date_ec']) && $bLeader['receipt_date_ec'] !== '-') ? $bLeader['receipt_date_ec'] : ($bLeader['date_gc'] ?? 'No Date');
                $bRefStr  = $bLeader['receipt_number'] ?: ('ID #' . $bLeader['id']);
                $bTotalAmt = (float)collect($blockedItems)->sum('total_value');
                $bAmtStr  = number_format($bTotalAmt, 2);
                $removedReceiptsSummary[] = [
                    'id'             => $bLeader['id'],
                    'receipt_id'     => $bLeader['receipt_id'] ?? null,
                    'receipt_number' => $bLeader['receipt_number'] ?? '',
                    'date'           => $bDateStr,
                    'amount'         => $bTotalAmt,
                    'ref'            => $bRefStr,
                    'label'          => "{$bRefStr} ({$bDateStr}) — ETB {$bAmtStr}",
                ];
            }

            $duplicatesRemoved[] = [
                'fs_no'            => $keptLeader['fs_no'],
                'norm_fs'          => $keptLeader['norm_fs'],
                'seller_tin'       => $keptLeader['seller_tin'] ?: ($keptLeader['vendor_tin'] ?? ''),
                'norm_tin'         => $keptLeader['norm_tin'],
                'vendor_name'      => $keptLeader['vendor_name'] ?? 'Vendor',
                'kept_receipt'     => [
                    'id'             => $keptLeader['id'],
                    'receipt_id'     => $keptLeader['receipt_id'] ?? null,
                    'receipt_number' => $keptLeader['receipt_number'] ?? '',
                    'date'           => $keptDateStr,
                    'amount'         => $keptTotalAmt,
                    'ref'            => $keptRefStr,
                    'label'          => "{$keptRefStr} ({$keptDateStr}) — ETB {$keptAmtStr}",
                ],
                'removed_receipts' => $removedReceiptsSummary,
                'removed_count'    => count($removedReceiptsSummary),
                'reason'           => 'Identical FS No. (' . $keptLeader['fs_no'] . ') & Seller TIN (' . ($keptLeader['seller_tin'] ?: $keptLeader['vendor_tin']) . '). Double receipt immediately blocked; original first receipt preserved (' . $keptRefStr . ') and double receipt excluded from Excel export.',
            ];
        }

        // Maintain original chronological/id order in unique cleaned list, prioritizing latest updated items
        $uniqueCleanedCollection = collect($uniqueCleanedList)->sortByDesc(function ($item) {
            return (int)($item['manually_edited_at'] ?: ($item['updated_timestamp'] ?: ($item['created_timestamp'] ?: ($item['receipt_timestamp'] ?: 0))));
        })->values();

        return [
            'cleaned'            => $uniqueCleanedCollection,
            'duplicates_removed' => $duplicatesRemoved,
            'flagged_review'     => $flaggedReviewList,
        ];
    }

    /**
     * Reusable computation logic for Ethiopian Month Financial Movements & Tax Audit.
     * Defaults to vatOnly = true to show ONLY verified VAT receipts in Tab 3 (Scanned OCR Receipts).
     */
    public function calculateMonthMovementsData(int $year, int $month, bool $vatOnly = true): array
    {
        $this->ensureSchema();

        $curEth = EthiopianDate::toEthiopian(now());
        if ($year <= 0) {
            $year = $curEth['year'];
        }
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

        // 2. Paid Expenses & Purchases in this Ethiopian month (Where our purchases came from, excluding VAT B)
        // Includes: All paid ExpenseRequests (created or paid in month), PettyCashMaterialPurchases, and direct project expenses
        $expenses = collect();
        if (Schema::hasTable('expense_requests')) {
            $withRelations = ['project', 'user', 'bankAccount', 'chartOfAccount', 'paidBy'];
            if (Schema::hasTable('receipts') && method_exists(ExpenseRequest::class, 'receipt')) {
                $withRelations[] = 'receipt';
            }
            if (Schema::hasTable('expense_hub_vat_decisions') && method_exists(ExpenseRequest::class, 'expenseHubVatDecision')) {
                $withRelations[] = 'expenseHubVatDecision';
            }

            $expenses = ExpenseRequest::with($withRelations)
                ->where(function ($q) {
                    $q->whereIn('status', [ExpenseRequest::STATUS_PAID, 'paid', 'PAID', 'approved', 'completed'])
                      ->orWhereNotNull('paid_at')
                      ->orWhereNotNull('payment_reference');
                })
                ->whereNotIn('status', [ExpenseRequest::STATUS_REJECTED, 'rejected', 'Rejected'])
                ->where(function ($q) use ($start, $end) {
                    $q->whereBetween('paid_at', [$start, $end])
                      ->orWhere(function ($sub) use ($start, $end) {
                          $sub->whereNull('paid_at')
                              ->whereBetween('created_at', [$start, $end]);
                      })
                      ->orWhereBetween('created_at', [$start, $end])
                      ->orWhere(function ($sub) use ($start, $end) {
                          $sub->whereNotNull('paid_at')
                              ->whereBetween('updated_at', [$start, $end]);
                      });
                })
                ->orderByRaw('COALESCE(paid_at, created_at) asc')
                ->get();
        }

        $defaultPurchaserDigits = 38480010;
        if (Schema::hasTable('system_settings')) {
            try {
                $defaultPurchaserTin = SystemSetting::get('purchaser_tin', SystemSetting::get('company_tin', '38480010'));
                $dDigits = (int)preg_replace('/^0+/', '', preg_replace('/[^0-9]/', '', (string)$defaultPurchaserTin));
                if ($dDigits) $defaultPurchaserDigits = $dDigits;
            } catch (\Throwable $e) {}
        }

        $formattedExpenses = $expenses->map(function ($item) use ($defaultPurchaserDigits) {
            // Extract linked Receipt and ExpenseHubVatDecision for TAS / e-Tax details (prioritizing the most recently updated receipt)
            $r = null;
            if (!empty($item->receipt_id) && Schema::hasTable('receipts')) {
                $r = Receipt::find($item->receipt_id);
            }
            if (!$r && Schema::hasTable('receipts')) {
                $r = Receipt::where(function ($rq) use ($item) {
                        $rq->whereIn('purchasable_type', [ExpenseRequest::class, 'App\\Models\\ExpenseRequest', 'expense_request', 'expense'])
                           ->where('purchasable_id', $item->id);
                    })
                    ->orWhere('receipt_number', $item->request_number)
                    ->orderBy('updated_at', 'desc')
                    ->orderBy('id', 'desc')
                    ->first();
            }
            if (!$r && $item->relationLoaded('receipt') && $item->receipt) {
                $r = $item->receipt;
            }
            if (!$r && !empty($item->payment_reference) && Schema::hasTable('receipts')) {
                if (preg_match('/FS[:\s]+([0-9a-zA-Z_-]+)/i', $item->payment_reference, $mFs)) {
                    $r = Receipt::where('fs_no', $mFs[1])
                        ->orWhere('fs_no_raw', $mFs[1])
                        ->orderBy('updated_at', 'desc')
                        ->orderBy('id', 'desc')
                        ->first();
                }
            }
            $dec = $item->relationLoaded('expenseHubVatDecision') ? $item->expenseHubVatDecision : null;
            if (!$dec && Schema::hasTable('expense_hub_vat_decisions')) {
                $dec = ExpenseHubVatDecision::where(function ($dq) use ($item) {
                        $dq->whereIn('source_type', ['expense_request', 'expense', ExpenseRequest::class])
                           ->where('source_id', $item->id);
                    })->latest('id')->first();
            }
            $parsed = is_string($r?->parsed_data ?? null) ? (json_decode($r->parsed_data, true) ?: []) : (array)($r?->parsed_data ?? []);
            $manuallyEditedAt = (int)($parsed['manually_edited_at'] ?? ($parsed['last_updated_at'] ?? 0));

            $payee = $r?->vendor_name
                ?: (!empty($parsed['seller_name']) ? $parsed['seller_name'] : null)
                ?: (!empty($parsed['vendor_name']) ? $parsed['vendor_name'] : null)
                ?: ($item->payee ?: ($item->account_name ?: ($item->user?->name ?: 'Vendor / Payee')));

            $itemDate = $r?->receipt_date ?: ($item->paid_at ?: $item->created_at);

            $gross = (float)($item->gross_amount > 0 ? $item->gross_amount : $item->amount);
            $vatType = $item->vat_type ?? 'none';
            $vatRate = (float)($item->vat_rate ?? 15.00);

            $baseAmount = $gross;
            $vatAmount  = 0.0;

            if ($r && (float)$r->total_amount > 0) {
                $baseAmount = (float)$r->subtotal;
                $vatAmount  = (float)$r->vat_amount;
                $gross      = (float)$r->total_amount;
            } elseif ($dec && $dec->vat_status === 'no_vat') {
                $vatAmount  = 0.0;
                $baseAmount = $gross;
            } elseif ((float)$item->vat_amount > 0 && $vatType === 'exclusive') {
                $vatAmount  = (float)$item->vat_amount;
                $baseAmount = $gross;
            } elseif ($vatType === 'inclusive') {
                $baseAmount = round($gross / (1 + ($vatRate / 100)), 2);
                $vatAmount  = round($gross - $baseAmount, 2);
            } elseif ($vatType === 'exclusive') {
                $vatAmount  = round($gross * ($vatRate / 100), 2);
                $baseAmount = $gross;
            } elseif ((float)$item->vat_amount > 0) {
                $vatAmount  = (float)$item->vat_amount;
                $baseAmount = max(0.0, round($gross - $vatAmount, 2));
            }

            $whtAmount = (float)$item->calculated_withholding_amount;
            if ($whtAmount <= 0 && (float)($item->withholding_amount ?? 0) > 0) {
                $whtAmount = round((float)$item->withholding_amount, 2);
            }
            if ($whtAmount <= 0 && $dec && (float)($dec->withholding_amount ?? 0) > 0) {
                $whtAmount = round((float)$dec->withholding_amount, 2);
            }
            if ($whtAmount <= 0 && (!empty($parsed['withholding_amount']) && (float)$parsed['withholding_amount'] > 0)) {
                $whtAmount = round((float)$parsed['withholding_amount'], 2);
            }
            if ($whtAmount <= 0 && ($item->has_withholding || (!empty($dec) && !empty($dec->has_withholding)) || (!empty($parsed['has_withholding'])))) {
                $whtRate = (float)($item->withholding_rate ?: ($dec?->withholding_rate ?: (!empty($parsed['withholding_rate']) ? $parsed['withholding_rate'] : 3.00)));
                $whtAmount = round($baseAmount * ($whtRate / 100), 2);
            }

            $hasWht = ($whtAmount > 0) || !empty($item->has_withholding) || (!empty($dec) && !empty($dec->has_withholding)) || (!empty($parsed['has_withholding']));
            $whtRate = (float)($item->withholding_rate ?: ($dec?->withholding_rate ?: (!empty($parsed['withholding_rate']) ? $parsed['withholding_rate'] : ($whtAmount > 0 && $baseAmount > 0 ? round(($whtAmount / $baseAmount) * 100, 1) : 3.00))));
            if (!$hasWht && $whtAmount <= 0) {
                $whtRate = 0.0;
                $whtAmount = 0.0;
            }

            $whtReceiptNo = $item->withholding_receipt_number ?: ($dec?->withholding_receipt_number ?: (!empty($parsed['withholding_receipt_number']) ? $parsed['withholding_receipt_number'] : '-'));
            $whtSlipUrl = $item->withholding_receipt_url ?: (!empty($item->withholding_receipt) ? \App\Services\FileUploadService::url($item->withholding_receipt) : ($dec?->withholding_receipt ? \App\Services\FileUploadService::url($dec->withholding_receipt) : (!empty($parsed['withholding_receipt']) ? \App\Services\FileUploadService::url($parsed['withholding_receipt']) : null)));

            $netDisbursed = (float)$item->effective_payable_amount;
            if ($netDisbursed <= 0 || ($hasWht && $netDisbursed > ($baseAmount + $vatAmount - $whtAmount))) {
                $netDisbursed = max(0.0, round($baseAmount + $vatAmount - $whtAmount, 2));
            }

            $accountName = '-';
            if ($item->bankAccount) {
                $accountName = $item->bankAccount->bank_name . ($item->bankAccount->account_number ? ' (' . $item->bankAccount->account_number . ')' : '');
            } elseif ($item->chartOfAccount) {
                $accountName = $item->chartOfAccount->name;
            } elseif (!empty($item->payment_method)) {
                $accountName = ucfirst(str_replace('_', ' ', $item->payment_method));
            }

            $fsNo = $r?->fs_no ?: ($dec?->custom_fs_no ?: (!empty($parsed['receipt_no']) ? $parsed['receipt_no'] : null));
            if (empty($fsNo) && !empty($item->payment_reference) && preg_match('/FS[:\s]+([^\s|]+)/i', $item->payment_reference, $mFs)) {
                $fsNo = $mFs[1];
            }

            $mrcNo = $r?->mrc_no ?: ($dec?->custom_mrc_no ?: (!empty($parsed['mrc']) ? $parsed['mrc'] : null));
            if (empty($mrcNo) && !empty($item->payment_reference) && preg_match('/MRC[:\s]+([^\s|]+)/i', $item->payment_reference, $mMrc)) {
                $mrcNo = $mMrc[1];
            }

            $sellerTin = $r?->vendor_tin ?: ($dec?->custom_seller_tin ?: (!empty($parsed['seller_tin']) ? $parsed['seller_tin'] : null));

            // Fallback 1: Extract TIN from payment reference (e.g. "FS: 00012345 | TIN: 0012345678")
            if (empty($sellerTin) && !empty($item->payment_reference) && preg_match('/TIN[:\s]+([0-9]{9,12})/i', $item->payment_reference, $mTin)) {
                $sellerTin = $mTin[1];
            }

            // Fallback 2: Check linked VehicleFuelRequest for fuel_station_tin
            if (empty($sellerTin) && Schema::hasTable('vehicle_fuel_requests')) {
                try {
                    $fuelReq = null;
                    if (Schema::hasColumn('vehicle_fuel_requests', 'expense_request_id')) {
                        $fuelReq = \App\Models\VehicleFuelRequest::where('expense_request_id', $item->id)->first();
                    }
                    if (!$fuelReq && !empty($item->request_number)) {
                        $hasReqNo = Schema::hasColumn('vehicle_fuel_requests', 'request_no');
                        $hasReqNum = Schema::hasColumn('vehicle_fuel_requests', 'request_number');
                        if ($hasReqNo || $hasReqNum) {
                            $fuelReq = \App\Models\VehicleFuelRequest::where(function ($q) use ($item, $hasReqNo, $hasReqNum) {
                                if ($hasReqNo && $hasReqNum) {
                                    $q->where('request_no', $item->request_number)
                                      ->orWhere('request_number', $item->request_number);
                                } elseif ($hasReqNo) {
                                    $q->where('request_no', $item->request_number);
                                } else {
                                    $q->where('request_number', $item->request_number);
                                }
                            })->first();
                        }
                    }
                    if ($fuelReq && !empty($fuelReq->fuel_station_tin)) {
                        $sellerTin = $fuelReq->fuel_station_tin;
                        if (empty($fsNo) && !empty($fuelReq->fs_no)) {
                            $fsNo = $fuelReq->fs_no;
                        }
                        if (empty($mrcNo) && !empty($fuelReq->mrc_no)) {
                            $mrcNo = $fuelReq->mrc_no;
                        }
                    }
                } catch (\Throwable $e) {
                    // Suppress error to avoid breaking movements page load
                }
            }

            // Fallback 3: Check Supplier tax_id / TIN by payee name
            if (empty($sellerTin) && !empty($payee) && Schema::hasTable('suppliers')) {
                $supTin = \App\Models\Supplier::where('name', $payee)->value('tax_id');
                if (!empty($supTin)) {
                    $sellerTin = $supTin;
                }
            }

            // Fallback 4: Check CreditStorePayment if linked
            if (empty($sellerTin) && !empty($item->credit_store_ledger_id) && Schema::hasTable('credit_store_payments')) {
                $cPayment = \App\Models\CreditStorePayment::where('credit_store_ledger_id', $item->credit_store_ledger_id)->whereNotNull('receipt_path')->latest('id')->first();
                if ($cPayment && $cPayment->receipt && !empty($cPayment->receipt->vendor_tin)) {
                    $sellerTin = $cPayment->receipt->vendor_tin;
                }
            }
            $purchaserTin = $r?->buyer_tin ?: (!empty($parsed['purchaser_tin']) ? $parsed['purchaser_tin'] : $defaultPurchaserDigits);

            $purcType = $dec?->purc_type ?: (!empty($parsed['purc_type']) ? $parsed['purc_type'] : null);
            if (empty($purcType)) {
                $purcNorm = QcService::normalizePurchaseType(
                    $item->category ?: 'general',
                    ($item->description ?? '') . ' ' . ($payee ?? '')
                );
                $purcType = $purcNorm['type'] ?? 'PUR_GD';
            }

            $encodedBy = !empty($parsed['encoded_by']) ? $parsed['encoded_by'] : ((Schema::hasTable('system_settings') ? SystemSetting::get('etax_encoded_by') : null) ?: 'wechacha');
            $receiptDateEc = !empty($parsed['receipt_date']) ? $parsed['receipt_date'] : ($itemDate ? EthiopianDate::formatDateEc($itemDate, 'd-m-Y') : EthiopianDate::formatDateEc(now(), 'd-m-Y'));
            $encodingDateEc = !empty($parsed['encoding_date']) ? $parsed['encoding_date'] : EthiopianDate::formatDateEc($item->created_at ?: now(), 'd-m-Y');

            $receiptSlipUrl = null;
            if ($r?->file_path) {
                try {
                    $receiptSlipUrl = $r->file_url ?: \App\Services\FileUploadService::url($r->file_path);
                } catch (\Throwable $e) {
                    $receiptSlipUrl = asset('storage/' . ltrim($r->file_path, '/'));
                }
            } elseif (!empty($item->attachment)) {
                try {
                    $receiptSlipUrl = \App\Services\FileUploadService::url($item->attachment);
                } catch (\Throwable $e) {
                    $receiptSlipUrl = asset('storage/' . ltrim($item->attachment, '/'));
                }
            } elseif (!empty($item->attachment_url)) {
                $receiptSlipUrl = $item->attachment_url;
            }

            $isFuel = (str_contains(strtoupper($item->request_number ?? ''), 'FUEL')
                || str_contains(strtoupper($item->category ?? ''), 'FUEL')
                || str_contains(strtoupper($item->description ?? ''), 'FUEL')
                || ($dec?->no_vat_reason_code === 'fuel_zero_vat')
                || ($r?->category === 'fuel'));

            $isVerifiedNoRec = (bool)($item->is_verified_without_receipt ?? ($dec?->is_verified_without_receipt ?? false));
            $hasReceipt = (bool)(
                !empty($receiptSlipUrl)
                || !empty($r?->file_path)
                || !empty($item->attachment)
                || !empty($item->attachment_url)
                || !empty($r?->id)
                || (!empty($fsNo) && $fsNo !== '-')
            );

            if ($isVerifiedNoRec) {
                $vatAmount = 0.0;
                $vatType = 'none';
                $vatRate = 0.0;
            }

            $rItem = null;
            if ($r && Schema::hasTable('receipt_items')) {
                $rItem = $r->relationLoaded('items') ? $r->items->first() : $r->items()->first();
            }

            $itemDesc = $rItem?->item_description
                ?: ($dec?->custom_item_description
                ?: (!empty($parsed['item_description']) ? $parsed['item_description']
                : ($item->description ?: '')));

            $uom = (string)($rItem?->uom ?: ($dec?->uom ?: (!empty($parsed['uom']) ? $parsed['uom'] : '7')));
            $qty = (float)($rItem?->qty ?: ($dec?->quantity ?: (!empty($parsed['qty']) ? $parsed['qty'] : 1.00)));
            $unitPrice = (float)($rItem?->unit_price ?: ($dec?->unit_price ?: (!empty($parsed['unit_price']) ? $parsed['unit_price'] : ($qty > 0 ? round($baseAmount / $qty, 2) : $baseAmount))));
            $vatCategory = (string)($rItem?->vat_category ?: ($dec?->vat_category ?: (!empty($parsed['vat_category']) ? $parsed['vat_category'] : ($vatAmount > 0 ? 'G' : 'E'))));
            $calendarType = (string)($rItem?->calendar_type ?: ($dec?->calendar_type ?: (!empty($parsed['calendar_type']) ? $parsed['calendar_type'] : 'E')));
            $purchaseType = $rItem?->purchase_type ?: ($dec?->purchase_type ?: (!empty($parsed['purchase_type']) ? $parsed['purchase_type'] : 3));

            return [
                'id'                 => $item->id,
                'request_number'     => $item->request_number,
                'payment_reference'  => $item->payment_reference ?: '-',
                'date_ec'            => $itemDate ? EthiopianDate::formatDateEc($itemDate, 'DD/MM/YYYY EC') : '-',
                'date_gc'            => $itemDate ? Carbon::parse($itemDate)->format('M d, Y') : '-',
                'raw_date'           => $itemDate ? Carbon::parse($itemDate)->toDateTimeString() : '',
                'payee'              => $payee,
                'project_name'       => $item->project?->name ?: 'General',
                'category'           => ucfirst(str_replace('_', ' ', $item->category ?: 'Expense')),
                'account_name'       => $accountName,
                'base_amount'        => $baseAmount,
                'vat_type'           => $vatType,
                'vat_rate'           => $vatRate,
                'vat_amount'         => $vatAmount,
                'has_withholding'            => (bool)$hasWht,
                'withholding_rate'           => (float)$whtRate,
                'withholding_amount'         => (float)$whtAmount,
                'withholding_receipt_number' => $whtReceiptNo,
                'withholding_receipt_url'    => $whtSlipUrl,
                'withholding_settled'        => (bool)($item->withholding_settled ?? false),
                'net_disbursed'      => $netDisbursed,
                'description'        => $itemDesc ?: ($item->description ?: '-'),
                'attachment_url'     => $receiptSlipUrl ?: $item->attachment_url,
                'status'             => $item->status,
                'is_settled'         => (bool)$item->vat_settled,
                'source_type'             => 'expense_request',
                'source_label'            => 'Expense Voucher',
                'is_vat_purchase'         => ($vatAmount > 0),
                'is_tas_purchase'         => ($vatAmount <= 0),
                'purchase_category'       => ($vatAmount > 0 ? 'vat_purchase' : 'tas_purchase'),
                'purchase_category_label' => ($vatAmount > 0 ? 'VAT Purchase (15%)' : 'TAS Purchase (0% VAT)'),
                'purchase_category_badge' => ($vatAmount > 0 ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-primary-subtle text-primary border border-primary-subtle'),
                'fs_no'                   => $fsNo ?: '-',
                'mrc_no'                  => $mrcNo ?: '-',
                'seller_tin'              => $sellerTin ?: '-',
                'purchaser_tin'           => $purchaserTin ?: $defaultPurchaserDigits,
                'purc_type'               => $purcType,
                'encoded_by'              => $encodedBy,
                'receipt_date_ec'         => $receiptDateEc,
                'encoding_date_ec'        => $encodingDateEc,
                'receipt_id'              => $r?->id,
                'receipt_slip_url'        => $receiptSlipUrl,
                'is_fuel'                 => $isFuel,
                'is_verified_without_receipt' => $isVerifiedNoRec,
                'has_receipt'                 => $hasReceipt,
                'uom'                         => $uom,
                'qty'                         => $qty,
                'unit_price'                  => $unitPrice,
                'total_value'                 => $baseAmount,
                'vat_category'                => $vatCategory,
                'calendar_type'               => $calendarType,
                'purchase_type'               => $purchaseType,
                'project_id'                  => $item->project_id ?: ($r?->project_id ?: null),
                'item_description'            => $itemDesc,
            ];
        });

        // Include Petty Cash Material Purchases in this Ethiopian month
        if (Schema::hasTable('petty_cash_material_purchases')) {
            try {
                $pettyPurchases = \App\Models\PettyCashMaterialPurchase::with(['store.project', 'purchaser', 'chartOfAccount', 'items.product'])
                    ->whereNotIn('status', ['rejected', 'cancelled'])
                    ->where(function ($q) use ($startDate, $endDate, $start, $end) {
                        $q->whereBetween('purchase_date', [$startDate, $endDate])
                          ->orWhere(function ($sub) use ($start, $end) {
                              $sub->whereNull('purchase_date')
                                  ->whereBetween('created_at', [$start, $end]);
                          });
                    })
                    ->orderByRaw('COALESCE(purchase_date, created_at) asc')
                    ->get();

                $existingExpNumbers = $formattedExpenses->pluck('request_number')->filter()->all();

                foreach ($pettyPurchases as $p) {
                    $refNo = $p->purchase_no ?: ('PCMP-' . $p->id);
                    if (in_array($refNo, $existingExpNumbers)) {
                        continue;
                    }

                    $pGross = (float)$p->total_amount;
                    $pDate = $p->purchase_date ?: $p->created_at;

                    $accountName = '-';
                    if ($p->chartOfAccount) {
                        $accountName = $p->chartOfAccount->name;
                    } elseif ($p->store) {
                        $accountName = $p->store->name . ' (Petty Cash)';
                    } else {
                        $accountName = 'Petty Cash Fund';
                    }

                    $payee = $p->supplier_name ?: ($p->purchaser?->name ?: 'Petty Cash Supplier');
                    $projectName = $p->store?->project?->name ?: ($p->store?->name ?: 'Site Store');

                    $paymentRef = '-';
                    if (!empty($p->receipt_no) && !empty($p->fs_no)) {
                        $paymentRef = 'Rec: ' . $p->receipt_no . ' / FS: ' . $p->fs_no;
                    } elseif (!empty($p->receipt_no)) {
                        $paymentRef = 'Rec: ' . $p->receipt_no;
                    } elseif (!empty($p->fs_no)) {
                        $paymentRef = 'FS: ' . $p->fs_no;
                    }

                    $desc = $p->notes;
                    if (empty($desc) && $p->items && $p->items->isNotEmpty()) {
                        $desc = $p->items->map(fn($it) => ($it->product?->name ?: ($it->remarks ?: 'Material')) . ' (' . (float)$it->quantity . ' ' . ($it->unit ?: 'pcs') . ')')->take(3)->implode(', ');
                    }
                    if (empty($desc)) {
                        $desc = 'Site Material Petty Cash Purchase';
                    }

                    $attachmentUrl = null;
                    if (!empty($p->attachment_path)) {
                        try {
                            $attachmentUrl = \App\Services\FileUploadService::url($p->attachment_path);
                        } catch (\Throwable $e) {
                            $attachmentUrl = asset('storage/' . ltrim($p->attachment_path, '/'));
                        }
                    }

                    $pcReceipt = null;
                    if (Schema::hasTable('receipts')) {
                        $pcReceipt = \App\Models\Receipt::where('purchasable_type', \App\Models\PettyCashMaterialPurchase::class)
                            ->where('purchasable_id', $p->id)
                            ->latest('id')->first();
                    }
                    $pcDec = null;
                    if (Schema::hasTable('expense_hub_vat_decisions')) {
                        $pcDec = \App\Models\ExpenseHubVatDecision::where('source_type', 'petty_cash_purchase')
                            ->where('source_id', $p->id)
                            ->latest('id')->first();
                    }
                    $pcParsed = is_string($pcReceipt?->parsed_data ?? null) ? (json_decode($pcReceipt->parsed_data, true) ?: []) : (array)($pcReceipt?->parsed_data ?? []);
                    $pcItem = $pcReceipt?->items?->first();
                    $pcUom = (string)($pcItem?->uom ?: ($pcDec?->uom ?: (!empty($pcParsed['uom']) ? $pcParsed['uom'] : '7')));
                    $pcQty = (float)($pcItem?->qty ?: ($pcDec?->quantity ?: (!empty($pcParsed['qty']) ? $pcParsed['qty'] : 1.00)));
                    $pcUnitPrice = (float)($pcItem?->unit_price ?: ($pcDec?->unit_price ?: (!empty($pcParsed['unit_price']) ? $pcParsed['unit_price'] : ($pcQty > 0 ? round($pGross / $pcQty, 2) : $pGross))));
                    $pcVatCat = (string)($pcItem?->vat_category ?: ($pcDec?->vat_category ?: (!empty($pcParsed['vat_category']) ? $pcParsed['vat_category'] : 'E')));
                    $pcCalType = (string)($pcItem?->calendar_type ?: ($pcDec?->calendar_type ?: (!empty($pcParsed['calendar_type']) ? $pcParsed['calendar_type'] : 'E')));
                    $pcPurchType = $pcItem?->purchase_type ?: ($pcDec?->purchase_type ?: (!empty($pcParsed['purchase_type']) ? $pcParsed['purchase_type'] : 6));
                    $pcDesc = $pcItem?->item_description ?: ($pcDec?->custom_item_description ?: (!empty($pcParsed['item_description']) ? $pcParsed['item_description'] : $desc));

                    $formattedExpenses->push([
                        'id'                 => 'PC-' . $p->id,
                        'request_number'     => $refNo,
                        'payment_reference'  => $paymentRef,
                        'date_ec'            => $pDate ? EthiopianDate::formatDateEc($pDate, 'DD/MM/YYYY EC') : '-',
                        'date_gc'            => $pDate ? Carbon::parse($pDate)->format('M d, Y') : '-',
                        'raw_date'           => $pDate ? Carbon::parse($pDate)->toDateTimeString() : '',
                        'payee'              => $payee,
                        'project_name'       => $projectName,
                        'category'           => 'Petty Cash Purchase',
                        'account_name'       => $accountName,
                        'base_amount'        => $pGross,
                        'vat_type'           => 'none',
                        'vat_rate'           => 0.0,
                        'vat_amount'         => 0.0,
                        'has_withholding'            => false,
                        'withholding_rate'           => 0.0,
                        'withholding_amount'         => 0.0,
                        'withholding_receipt_number' => '-',
                        'withholding_receipt_url'    => null,
                        'withholding_settled'        => false,
                        'net_disbursed'      => $pGross,
                        'description'        => $pcDesc ?: $desc,
                        'attachment_url'     => $attachmentUrl,
                        'status'             => $p->status ?: 'completed',
                        'is_settled'         => false,
                        'source_type'             => 'petty_cash',
                        'source_label'            => 'Petty Cash',
                        'is_vat_purchase'         => false,
                        'is_tas_purchase'         => true,
                        'purchase_category'       => 'tas_purchase',
                        'purchase_category_label' => 'TAS Purchase (0% VAT)',
                        'purchase_category_badge' => 'bg-primary-subtle text-primary border border-primary-subtle',
                        'fs_no'                   => $p->fs_no ?: ($p->receipt_no ?: '-'),
                        'mrc_no'                  => $p->mrc_no ?: '-',
                        'seller_tin'              => $p->supplier_tin ?: '-',
                        'purchaser_tin'           => $defaultPurchaserDigits,
                        'purc_type'               => 'PUR_GD',
                        'encoded_by'              => 'wechacha',
                        'receipt_date_ec'         => $pDate ? EthiopianDate::formatDateEc($pDate, 'd-m-Y') : EthiopianDate::formatDateEc(now(), 'd-m-Y'),
                        'encoding_date_ec'        => EthiopianDate::formatDateEc($p->created_at ?: now(), 'd-m-Y'),
                        'receipt_id'              => $pcReceipt?->id,
                        'receipt_slip_url'        => $attachmentUrl,
                        'is_fuel'                 => false,
                        'is_verified_without_receipt' => false,
                        'has_receipt'                 => (!empty($attachmentUrl) || !empty($p->receipt_no) || !empty($p->fs_no)),
                        'uom'                         => $pcUom,
                        'qty'                         => $pcQty,
                        'unit_price'                  => $pcUnitPrice,
                        'total_value'                 => $pGross,
                        'vat_category'                => $pcVatCat,
                        'calendar_type'               => $pcCalType,
                        'purchase_type'               => $pcPurchType,
                        'project_id'                  => $p->store?->project_id ?: null,
                        'item_description'            => $pcDesc,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning("monthMovements petty cash purchases mapping error: " . $e->getMessage());
            }
        }

        // Include direct project expenses in this Ethiopian month if not already captured
        if (Schema::hasTable('expenses')) {
            try {
                $directExpenses = \App\Models\Expense::with(['project', 'creator'])
                    ->whereIn('status', ['paid', 'approved', 'completed'])
                    ->where(function ($q) use ($startDate, $endDate, $start, $end) {
                        $q->whereBetween('expense_date', [$startDate, $endDate])
                          ->orWhere(function ($sub) use ($start, $end) {
                              $sub->whereNull('expense_date')
                                  ->whereBetween('created_at', [$start, $end]);
                          });
                    })
                    ->get();

                $existingDescriptions = $formattedExpenses->pluck('description')->filter()->all();
                $existingVouchers = $formattedExpenses->pluck('request_number')->filter()->all();

                foreach ($directExpenses as $de) {
                    $deDesc = $de->description ?: $de->notes;
                    $deRef  = 'EXP-DIR-' . $de->id;
                    if (in_array($deRef, $existingVouchers) || ($deDesc && in_array($deDesc, $existingDescriptions))) {
                        continue;
                    }

                    $deGross = (float)$de->amount;
                    $deDate = $de->expense_date ?: ($de->approved_at ?: $de->created_at);

                    $attachmentUrl = null;
                    if (!empty($de->receipt_path)) {
                        try {
                            $attachmentUrl = \App\Services\FileUploadService::url($de->receipt_path);
                        } catch (\Throwable $e) {
                            $attachmentUrl = asset('storage/' . ltrim($de->receipt_path, '/'));
                        }
                    }

                    $formattedExpenses->push([
                        'id'                 => 'DIR-' . $de->id,
                        'request_number'     => $deRef,
                        'payment_reference'  => '-',
                        'date_ec'            => $deDate ? EthiopianDate::formatDateEc($deDate, 'DD/MM/YYYY EC') : '-',
                        'date_gc'            => $deDate ? Carbon::parse($deDate)->format('M d, Y') : '-',
                        'raw_date'           => $deDate ? Carbon::parse($deDate)->toDateTimeString() : '',
                        'payee'              => $de->creator?->name ?: 'Direct Payee',
                        'project_name'       => $de->project?->name ?: 'General',
                        'category'           => ucfirst(str_replace('_', ' ', $de->category ?: 'Expense')),
                        'account_name'       => 'Direct Project Expense',
                        'base_amount'        => $deGross,
                        'vat_type'           => 'none',
                        'vat_rate'           => 0.0,
                        'vat_amount'         => 0.0,
                        'has_withholding'            => false,
                        'withholding_rate'           => 0.0,
                        'withholding_amount'         => 0.0,
                        'withholding_receipt_number' => '-',
                        'withholding_receipt_url'    => null,
                        'withholding_settled'        => false,
                        'net_disbursed'      => $deGross,
                        'description'        => $deDesc ?: 'Direct Expense',
                        'attachment_url'     => $attachmentUrl,
                        'status'             => $de->status ?: 'approved',
                        'is_settled'         => false,
                        'source_type'             => 'direct_expense',
                        'source_label'            => 'Direct Expense',
                        'is_vat_purchase'         => false,
                        'is_tas_purchase'         => true,
                        'purchase_category'       => 'tas_purchase',
                        'purchase_category_label' => 'TAS Purchase (0% VAT)',
                        'purchase_category_badge' => 'bg-primary-subtle text-primary border border-primary-subtle',
                        'fs_no'                   => '-',
                        'mrc_no'                  => '-',
                        'seller_tin'              => '-',
                        'purchaser_tin'           => $defaultPurchaserDigits,
                        'purc_type'               => 'PUR_GD',
                        'encoded_by'              => 'wechacha',
                        'receipt_date_ec'         => $deDate ? EthiopianDate::formatDateEc($deDate, 'd-m-Y') : EthiopianDate::formatDateEc(now(), 'd-m-Y'),
                        'encoding_date_ec'        => EthiopianDate::formatDateEc($de->created_at ?: now(), 'd-m-Y'),
                        'receipt_id'              => null,
                        'receipt_slip_url'        => $attachmentUrl,
                        'is_fuel'                 => false,
                        'is_verified_without_receipt' => empty($attachmentUrl),
                        'has_receipt'                 => !empty($attachmentUrl),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning("monthMovements direct expenses mapping error: " . $e->getMessage());
            }
        }

        // Include standalone TAS Receipts in this month (0% VAT, e.g. OCR cash register invoices or fuel slips)
        if (Schema::hasTable('receipts')) {
            try {
                $existingVouchers = $formattedExpenses->pluck('request_number')->filter()->all();
                $existingReceiptIds = $formattedExpenses->pluck('receipt_id')->filter()->all();

                $tasReceipts = Receipt::with(['project', 'uploader'])
                    ->where(function ($rq) use ($startDate, $endDate, $start, $end) {
                        $rq->where(function ($sub) use ($startDate, $endDate, $start, $end) {
                            $sub->whereBetween('receipt_date', [$startDate, $endDate])
                                ->orWhereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
                            if (Schema::hasTable('expense_requests')) {
                                $sub->orWhereExists(function ($ex) use ($start, $end) {
                                    $ex->select(DB::raw(1))
                                       ->from('expense_requests')
                                       ->where(function ($w) {
                                           $w->whereColumn('expense_requests.id', 'receipts.purchasable_id')
                                             ->orWhereColumn('expense_requests.request_number', 'receipts.receipt_number');
                                       })
                                       ->where(function ($mq) use ($start, $end) {
                                           $mq->whereBetween('paid_at', [$start, $end])
                                              ->orWhereBetween('created_at', [$start, $end]);
                                       });
                                });
                            }
                        });
                    })
                    ->where(function ($q) {
                        $q->where('vat_amount', '<=', 0)
                          ->orWhere('category', 'fuel');
                        if (Schema::hasColumn('receipts', 'tax_type')) {
                            $q->orWhere('tax_type', 'no_vat');
                        }
                    })
                    ->get();

                foreach ($tasReceipts as $tr) {
                    if (in_array($tr->id, $existingReceiptIds) || in_array($tr->receipt_number, $existingVouchers)) {
                        continue;
                    }

                    $parsed = is_string($tr->parsed_data ?? null) ? (json_decode($tr->parsed_data, true) ?: []) : (array)($tr->parsed_data ?? []);
                    $trDate = $tr->receipt_date ?: $tr->created_at;
                    $trGross = (float)($tr->total_amount > 0 ? $tr->total_amount : $tr->subtotal);

                    $slipUrl = null;
                    if ($tr->file_path) {
                        try {
                            $slipUrl = $tr->file_url ?: \App\Services\FileUploadService::url($tr->file_path);
                        } catch (\Throwable $e) {
                            $slipUrl = asset('storage/' . ltrim($tr->file_path, '/'));
                        }
                    }

                    $purcType = !empty($parsed['purc_type']) ? $parsed['purc_type'] : ($tr->category === 'fuel' ? 'PUR_GD' : 'PUR_GD');

                    $trItem = $tr->relationLoaded('items') ? $tr->items->first() : $tr->items()->first();
                    $trUom = (string)($trItem?->uom ?: (!empty($parsed['uom']) ? $parsed['uom'] : '7'));
                    $trQty = (float)($trItem?->qty ?: (!empty($parsed['qty']) ? $parsed['qty'] : 1.00));
                    $trUnitPrice = (float)($trItem?->unit_price ?: (!empty($parsed['unit_price']) ? $parsed['unit_price'] : ($trQty > 0 ? round($trGross / $trQty, 2) : $trGross)));
                    $trVatCat = (string)($trItem?->vat_category ?: (!empty($parsed['vat_category']) ? $parsed['vat_category'] : 'E'));
                    $trCalType = (string)($trItem?->calendar_type ?: (!empty($parsed['calendar_type']) ? $parsed['calendar_type'] : 'E'));
                    $trPurchType = $trItem?->purchase_type ?: (!empty($parsed['purchase_type']) ? $parsed['purchase_type'] : 6);
                    $trDesc = $trItem?->item_description ?: (!empty($parsed['item_description']) ? $parsed['item_description'] : ($tr->description ?: 'TAS Purchase Receipt (0% VAT)'));

                    $formattedExpenses->push([
                        'id'                      => 'REC-' . $tr->id,
                        'request_number'          => $tr->receipt_number ?: ('RCP-' . $tr->id),
                        'payment_reference'       => $tr->fs_no ? ('FS: ' . $tr->fs_no) : '-',
                        'date_ec'                 => $trDate ? EthiopianDate::formatDateEc($trDate, 'DD/MM/YYYY EC') : '-',
                        'date_gc'                 => $trDate ? Carbon::parse($trDate)->format('M d, Y') : '-',
                        'raw_date'                => $trDate ? Carbon::parse($trDate)->toDateTimeString() : '',
                        'payee'                   => $tr->vendor_name ?: 'Supplier / Merchant',
                        'project_name'            => $tr->project?->name ?: 'General',
                        'category'                => ucfirst($tr->category ?: 'General Receipt'),
                        'account_name'            => 'Cash / Register Receipt',
                        'base_amount'             => $trGross,
                        'vat_type'                => 'none',
                        'vat_rate'                => 0.0,
                        'vat_amount'              => 0.0,
                        'has_withholding'            => false,
                        'withholding_rate'           => 0.0,
                        'withholding_amount'         => 0.0,
                        'withholding_receipt_number' => '-',
                        'withholding_receipt_url'    => null,
                        'withholding_settled'        => false,
                        'net_disbursed'           => $trGross,
                        'description'             => $trDesc,
                        'attachment_url'          => $slipUrl,
                        'status'                  => 'approved',
                        'is_settled'              => false,
                        'source_type'             => 'receipt',
                        'source_label'            => 'Scanned Receipt',
                        'is_vat_purchase'         => false,
                        'is_tas_purchase'         => true,
                        'purchase_category'       => 'tas_purchase',
                        'purchase_category_label' => 'TAS Purchase (0% VAT)',
                        'purchase_category_badge' => 'bg-primary-subtle text-primary border border-primary-subtle',
                        'fs_no'                   => $tr->fs_no ?: ($tr->fs_no_raw ?: '-'),
                        'mrc_no'                  => $tr->mrc_no ?: (!empty($parsed['mrc']) ? $parsed['mrc'] : '-'),
                        'seller_tin'              => $tr->vendor_tin ?: (!empty($parsed['seller_tin']) ? $parsed['seller_tin'] : '-'),
                        'purchaser_tin'           => $tr->buyer_tin ?: (!empty($parsed['purchaser_tin']) ? $parsed['purchaser_tin'] : $defaultPurchaserDigits),
                        'purc_type'               => $purcType,
                        'encoded_by'              => !empty($parsed['encoded_by']) ? $parsed['encoded_by'] : 'wechacha',
                        'receipt_date_ec'         => !empty($parsed['receipt_date']) ? $parsed['receipt_date'] : ($trDate ? EthiopianDate::formatDateEc($trDate, 'd-m-Y') : EthiopianDate::formatDateEc(now(), 'd-m-Y')),
                        'encoding_date_ec'        => !empty($parsed['encoding_date']) ? $parsed['encoding_date'] : EthiopianDate::formatDateEc($tr->created_at ?: now(), 'd-m-Y'),
                        'receipt_id'              => $tr->id,
                        'receipt_slip_url'        => $slipUrl,
                        'is_fuel'                 => ($tr->category === 'fuel'),
                        'is_verified_without_receipt' => false,
                        'has_receipt'                 => true,
                        'uom'                         => $trUom,
                        'qty'                         => $trQty,
                        'unit_price'                  => $trUnitPrice,
                        'total_value'                 => $trGross,
                        'vat_category'                => $trVatCat,
                        'calendar_type'               => $trCalType,
                        'purchase_type'               => $trPurchType,
                        'project_id'                  => $tr->project_id ?: null,
                        'item_description'            => $trDesc,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning("monthMovements standalone TAS receipts error: " . $e->getMessage());
            }
        }

        // Include Procurement Payments (Purchase Requests paid by Finance) in this Ethiopian month
        if (Schema::hasTable('procurement_payments')) {
            try {
                $procPayments = \App\Models\ProcurementPayment::with([
                    'purchaseRequest.project',
                    'purchaseRequest.supplier',
                    'purchaseRequest.items.product',
                    'coaAccount',
                    'paidByUser',
                ])
                ->where(function ($q) {
                    $q->where('status', 'paid')
                      ->orWhereNotNull('paid_at');
                })
                ->where(function ($q) use ($startDate, $endDate, $start, $end) {
                    $q->whereBetween('paid_at', [$start, $end])
                      ->orWhere(function ($sub) use ($start, $end) {
                          $sub->whereNull('paid_at')
                              ->whereBetween('created_at', [$start, $end]);
                      })
                      ->orWhereBetween('created_at', [$start, $end]);
                })
                ->orderByRaw('COALESCE(paid_at, created_at) asc')
                ->get();

                $existingExpNumbers = $formattedExpenses->pluck('request_number')->filter()->all();
                $existingExpIds = $formattedExpenses->pluck('id')->filter()->all();

                foreach ($procPayments as $pp) {
                    $pr = $pp->purchaseRequest;
                    $refNo = $pr?->pr_no ? ('PR-' . $pr->pr_no) : ('PR-PAY-' . $pp->id);
                    if (in_array($refNo, $existingExpNumbers) || in_array('PROC-' . $pp->id, $existingExpIds)) {
                        continue;
                    }

                    $ppGross = (float)($pp->gross_amount > 0 ? $pp->gross_amount : $pp->amount);
                    $ppDate = $pp->paid_at ?: $pp->created_at;
                    $ppVat = (float)($pp->vat_amount ?? 0);
                    $ppBase = $ppGross;
                    if ($ppVat > 0 && $ppGross > $ppVat) {
                        $ppBase = round($ppGross - $ppVat, 2);
                    }
                    $ppWht = (float)($pp->withholding_amount ?? 0);
                    $ppNet = (float)($pp->net_amount > 0 ? $pp->net_amount : ($ppBase + $ppVat - $ppWht));

                    $supplierName = $pr?->supplier?->name ?: ($pr?->vendor_name ?: 'Procurement Supplier');
                    $supplierTin = $pr?->supplier?->tin ?: '-';
                    $projectName = $pr?->project?->name ?: 'General Procurement';
                    $accountName = $pp->coaAccount?->name ?: ($pp->method ? ucfirst($pp->method) . ' Payment' : 'Finance Procurement');

                    $ppSlipUrl = null;
                    if (Schema::hasTable('procurement_receipts')) {
                        $prReceipt = \App\Models\ProcurementReceipt::where('purchase_request_id', $pp->purchase_request_id)->latest('id')->first();
                        if ($prReceipt && $prReceipt->file_path) {
                            try {
                                $ppSlipUrl = \App\Services\FileUploadService::url($prReceipt->file_path);
                            } catch (\Throwable $e) {
                                $ppSlipUrl = asset('storage/' . ltrim($prReceipt->file_path, '/'));
                            }
                        }
                    }

                    $ppReceipt = null;
                    if (Schema::hasTable('receipts')) {
                        $ppReceipt = \App\Models\Receipt::where(function ($rq) use ($pp) {
                            $rq->where(function ($sub) use ($pp) {
                                $sub->whereIn('purchasable_type', [\App\Models\ProcurementPayment::class, 'procurement_payment', 'ProcurementPayment'])
                                    ->where('purchasable_id', $pp->id);
                            });
                        })->latest('id')->first();
                        if ($ppReceipt) {
                            if (!empty($ppReceipt->vendor_name)) $supplierName = $ppReceipt->vendor_name;
                            if (!empty($ppReceipt->vendor_tin))  $supplierTin  = $ppReceipt->vendor_tin;
                            if (!empty($ppReceipt->file_path)) {
                                try {
                                    $ppSlipUrl = $ppReceipt->file_url ?: \App\Services\FileUploadService::url($ppReceipt->file_path);
                                } catch (\Throwable $e) {
                                    $ppSlipUrl = asset('storage/' . ltrim($ppReceipt->file_path, '/'));
                                }
                            }
                        }
                    }

                    $ppDec = null;
                    if (Schema::hasTable('expense_hub_vat_decisions')) {
                        $ppDec = \App\Models\ExpenseHubVatDecision::where('source_type', 'procurement_payment')
                            ->where('source_id', $pp->id)
                            ->latest('id')
                            ->first();
                        if ($ppDec) {
                            if (!empty($ppDec->custom_seller_name)) $supplierName = $ppDec->custom_seller_name;
                            if (!empty($ppDec->custom_seller_tin))  $supplierTin  = $ppDec->custom_seller_tin;
                            if (!empty($ppDec->receipt_path) && empty($ppSlipUrl)) {
                                try {
                                    $ppSlipUrl = \App\Services\FileUploadService::url($ppDec->receipt_path);
                                } catch (\Throwable $e) {
                                    $ppSlipUrl = asset('storage/' . ltrim($ppDec->receipt_path, '/'));
                                }
                            }
                        }
                    }
                    $isVerifiedNoRec = (bool)($ppDec?->is_verified_without_receipt ?? false);
                    $hasRec = !empty($ppSlipUrl) || !empty($pp->withholding_receipt);

                    if ($isVerifiedNoRec) {
                        $ppVat = 0.0;
                    }

                    $desc = $ppReceipt?->description ?: ($ppDec?->custom_item_description ?: $pp->notes);
                    if (empty($desc) && $pr && $pr->items && $pr->items->isNotEmpty()) {
                        $desc = $pr->items->map(fn($it) => ($it->product?->name ?: ($it->specifications ?: 'Item')) . ' (' . (float)$it->quantity . ' ' . ($it->unit ?: 'pcs') . ')')->take(3)->implode(', ');
                    }
                    if (empty($desc)) {
                        $desc = 'Procurement Purchase Settlement';
                    }

                    $ppFsNo  = $ppReceipt?->fs_no ?: ($ppDec?->custom_fs_no ?: '-');
                    $ppMrcNo = $ppReceipt?->mrc_no ?: ($ppDec?->custom_mrc_no ?: '-');

                    $ppItem = $ppReceipt?->items?->first();
                    $ppParsed = is_string($ppReceipt?->parsed_data ?? null) ? (json_decode($ppReceipt->parsed_data, true) ?: []) : (array)($ppReceipt?->parsed_data ?? []);
                    $ppUom = (string)($ppItem?->uom ?: ($ppDec?->uom ?: (!empty($ppParsed['uom']) ? $ppParsed['uom'] : '7')));
                    $ppQty = (float)($ppItem?->qty ?: ($ppDec?->quantity ?: (!empty($ppParsed['qty']) ? $ppParsed['qty'] : 1.00)));
                    $ppUnitPrice = (float)($ppItem?->unit_price ?: ($ppDec?->unit_price ?: (!empty($ppParsed['unit_price']) ? $ppParsed['unit_price'] : ($ppQty > 0 ? round($ppBase / $ppQty, 2) : $ppBase))));
                    $ppVatCat = (string)($ppItem?->vat_category ?: ($ppDec?->vat_category ?: (!empty($ppParsed['vat_category']) ? $ppParsed['vat_category'] : ($ppVat > 0 ? 'G' : 'E'))));
                    $ppCalType = (string)($ppItem?->calendar_type ?: ($ppDec?->calendar_type ?: (!empty($ppParsed['calendar_type']) ? $ppParsed['calendar_type'] : 'E')));
                    $ppPurchType = $ppItem?->purchase_type ?: ($ppDec?->purchase_type ?: (!empty($ppParsed['purchase_type']) ? $ppParsed['purchase_type'] : 3));
                    $ppDesc = $ppItem?->item_description ?: ($ppDec?->custom_item_description ?: (!empty($ppParsed['item_description']) ? $ppParsed['item_description'] : $desc));

                    $formattedExpenses->push([
                        'id'                         => 'PROC-' . $pp->id,
                        'request_number'             => $refNo,
                        'payment_reference'          => 'PR #' . ($pr?->pr_no ?: $pp->purchase_request_id),
                        'date_ec'                    => $ppDate ? EthiopianDate::formatDateEc($ppDate, 'DD/MM/YYYY EC') : '-',
                        'date_gc'                    => $ppDate ? Carbon::parse($ppDate)->format('M d, Y') : '-',
                        'raw_date'                   => $ppDate ? Carbon::parse($ppDate)->toDateTimeString() : '',
                        'payee'                      => $supplierName,
                        'project_name'               => $projectName,
                        'category'                   => 'Procurement Purchase',
                        'account_name'               => $accountName,
                        'base_amount'                => $ppBase,
                        'vat_type'                   => ($ppVat > 0 ? 'exclusive' : 'none'),
                        'vat_rate'                   => ($ppVat > 0 ? (float)($pp->vat_rate ?: 15.0) : 0.0),
                        'vat_amount'                 => $ppVat,
                        'has_withholding'            => (bool)($pp->has_withholding || $ppWht > 0),
                        'withholding_rate'           => (float)($pp->withholding_rate ?: ($ppWht > 0 ? round(($ppWht / max(1, $ppBase)) * 100, 1) : 0)),
                        'withholding_amount'         => $ppWht,
                        'withholding_receipt_number' => $pp->withholding_receipt_number ?: '-',
                        'withholding_receipt_url'    => !empty($pp->withholding_receipt) ? asset('storage/' . ltrim($pp->withholding_receipt, '/')) : null,
                        'withholding_settled'        => false,
                        'net_disbursed'              => $ppNet,
                        'description'                => $ppDesc ?: $desc,
                        'attachment_url'             => $ppSlipUrl,
                        'status'                     => $pp->status ?: 'paid',
                        'is_settled'                 => false,
                        'source_type'                => 'procurement_payment',
                        'source_label'               => 'Procurement Purchase',
                        'is_vat_purchase'            => ($ppVat > 0),
                        'is_tas_purchase'            => ($ppVat <= 0),
                        'purchase_category'          => ($ppVat > 0 ? 'vat_purchase' : 'tas_purchase'),
                        'purchase_category_label'    => ($ppVat > 0 ? 'VAT Purchase (15%)' : 'TAS Purchase (0% VAT)'),
                        'purchase_category_badge'    => ($ppVat > 0 ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-primary-subtle text-primary border border-primary-subtle'),
                        'fs_no'                      => $ppFsNo,
                        'mrc_no'                     => $ppMrcNo,
                        'seller_tin'                 => $supplierTin,
                        'purchaser_tin'              => $defaultPurchaserDigits,
                        'purc_type'                  => 'PUR_GD',
                        'encoded_by'                 => 'wechacha',
                        'receipt_date_ec'            => $ppDate ? EthiopianDate::formatDateEc($ppDate, 'd-m-Y') : EthiopianDate::formatDateEc(now(), 'd-m-Y'),
                        'encoding_date_ec'           => EthiopianDate::formatDateEc($pp->created_at ?: now(), 'd-m-Y'),
                        'receipt_id'                 => $ppReceipt?->id,
                        'receipt_slip_url'           => $ppSlipUrl,
                        'is_fuel'                    => false,
                        'is_verified_without_receipt'=> $isVerifiedNoRec,
                        'has_receipt'                => $hasRec,
                        'uom'                        => $ppUom,
                        'qty'                        => $ppQty,
                        'unit_price'                 => $ppUnitPrice,
                        'total_value'                => $ppBase,
                        'vat_category'               => $ppVatCat,
                        'calendar_type'              => $ppCalType,
                        'purchase_type'              => $ppPurchType,
                        'project_id'                 => $pr?->project_id ?: ($ppReceipt?->project_id ?: null),
                        'item_description'           => $ppDesc,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning("monthMovements procurement payments mapping error: " . $e->getMessage());
            }
        }

        // Include CreditStorePayment in this Ethiopian month if not already included
        if (Schema::hasTable('credit_store_payments')) {
            try {
                $cspPayments = \App\Models\CreditStorePayment::with([
                    'ledger.project',
                    'ledger.purchaseRequest.supplier',
                    'recordedByUser',
                ])
                ->where(function ($q) use ($startDate, $endDate, $start, $end) {
                    $q->whereBetween('payment_date', [$startDate, $endDate])
                      ->orWhere(function ($sub) use ($start, $end) {
                          $sub->whereNull('payment_date')
                              ->whereBetween('created_at', [$start, $end]);
                      })
                      ->orWhereBetween('created_at', [$start, $end]);
                })
                ->get();

                $existingExpNumbers = $formattedExpenses->pluck('request_number')->filter()->all();
                $existingExpIds = $formattedExpenses->pluck('id')->filter()->all();

                foreach ($cspPayments as $csp) {
                    $ledger = $csp->ledger;
                    $refNo = $csp->reference_no ?: ($ledger?->pr_no ? ('CS-PR-' . $ledger->pr_no) : ('CS-PAY-' . $csp->id));
                    if (in_array($refNo, $existingExpNumbers) || in_array('CS-' . $csp->id, $existingExpIds)) {
                        continue;
                    }

                    $cspGross = (float)$csp->amount;
                    $cspDate = $csp->payment_date ?: $csp->created_at;
                    $cspVat = (float)($csp->vat_amount ?? 0);
                    $cspBase = (float)($csp->gross_amount > 0 ? $csp->gross_amount : max(0, $cspGross - $cspVat));
                    $cspWht = (float)($csp->withholding_amount ?? 0);
                    $cspNet = (float)($cspGross);

                    $supplierName = $ledger?->supplier_name ?: 'Credit Supplier';
                    $supplierTin = $ledger?->purchaseRequest?->supplier?->tin ?: '-';
                    $projectName = $ledger?->project?->name ?: 'General Store';

                    $attachmentUrl = null;
                    if (!empty($csp->receipt_path)) {
                        try {
                            $attachmentUrl = \App\Services\FileUploadService::url($csp->receipt_path);
                        } catch (\Throwable $e) {
                            $attachmentUrl = asset('storage/' . ltrim($csp->receipt_path, '/'));
                        }
                    }

                    $cspDec = null;
                    if (Schema::hasTable('expense_hub_vat_decisions')) {
                        $cspDec = \App\Models\ExpenseHubVatDecision::where('source_type', 'credit_store_payment')
                            ->where('source_id', $csp->id)
                            ->latest('id')
                            ->first();
                    }
                    $isVerifiedNoRec = (bool)($cspDec?->is_verified_without_receipt ?? false);
                    $hasRec = !empty($attachmentUrl) || !empty($csp->reference_no);

                    if ($isVerifiedNoRec) {
                        $cspVat = 0.0;
                    }

                    $cspReceipt = null;
                    if (Schema::hasTable('receipts')) {
                        $cspReceipt = \App\Models\Receipt::where(function ($rq) use ($csp) {
                            $rq->whereIn('purchasable_type', [\App\Models\CreditStorePayment::class, 'credit_store_payment', 'CreditStorePayment'])
                               ->where('purchasable_id', $csp->id);
                        })->latest('id')->first();
                    }
                    $cspItem = $cspReceipt?->items?->first();
                    $cspParsed = is_string($cspReceipt?->parsed_data ?? null) ? (json_decode($cspReceipt->parsed_data, true) ?: []) : (array)($cspReceipt?->parsed_data ?? []);
                    $cspUom = (string)($cspItem?->uom ?: ($cspDec?->uom ?: (!empty($cspParsed['uom']) ? $cspParsed['uom'] : '7')));
                    $cspQty = (float)($cspItem?->qty ?: ($cspDec?->quantity ?: (!empty($cspParsed['qty']) ? $cspParsed['qty'] : 1.00)));
                    $cspUnitPrice = (float)($cspItem?->unit_price ?: ($cspDec?->unit_price ?: (!empty($cspParsed['unit_price']) ? $cspParsed['unit_price'] : ($cspQty > 0 ? round($cspBase / $cspQty, 2) : $cspBase))));
                    $cspVatCat = (string)($cspItem?->vat_category ?: ($cspDec?->vat_category ?: (!empty($cspParsed['vat_category']) ? $cspParsed['vat_category'] : ($cspVat > 0 ? 'G' : 'E'))));
                    $cspCalType = (string)($cspItem?->calendar_type ?: ($cspDec?->calendar_type ?: (!empty($cspParsed['calendar_type']) ? $cspParsed['calendar_type'] : 'E')));
                    $cspPurchType = $cspItem?->purchase_type ?: ($cspDec?->purchase_type ?: (!empty($cspParsed['purchase_type']) ? $cspParsed['purchase_type'] : 3));
                    $cspDesc = $cspItem?->item_description ?: ($cspDec?->custom_item_description ?: (!empty($cspParsed['item_description']) ? $cspParsed['item_description'] : ('Credit Settlement: ' . ($ledger?->item_description ?: ($ledger?->notes ?: 'Materials')))));

                    $formattedExpenses->push([
                        'id'                         => 'CS-' . $csp->id,
                        'request_number'             => $refNo,
                        'payment_reference'          => $csp->reference_no ?: ('CS #' . $csp->id),
                        'date_ec'                    => $cspDate ? EthiopianDate::formatDateEc($cspDate, 'DD/MM/YYYY EC') : '-',
                        'date_gc'                    => $cspDate ? Carbon::parse($cspDate)->format('M d, Y') : '-',
                        'raw_date'                   => $cspDate ? Carbon::parse($cspDate)->toDateTimeString() : '',
                        'payee'                      => $supplierName,
                        'project_name'               => $projectName,
                        'category'                   => 'Credit Store Settlement',
                        'account_name'               => 'Credit Store Ledger',
                        'base_amount'                => $cspBase,
                        'vat_type'                   => ($cspVat > 0 ? 'exclusive' : 'none'),
                        'vat_rate'                   => ($cspVat > 0 ? 15.0 : 0.0),
                        'vat_amount'                 => $cspVat,
                        'has_withholding'            => (bool)($csp->has_withholding || $cspWht > 0),
                        'withholding_rate'           => (float)($csp->withholding_rate ?: ($cspWht > 0 ? round(($cspWht / max(1, $cspBase)) * 100, 1) : 0)),
                        'withholding_amount'         => $cspWht,
                        'withholding_receipt_number' => '-',
                        'withholding_receipt_url'    => null,
                        'withholding_settled'        => false,
                        'net_disbursed'              => $cspNet,
                        'description'                => $cspDesc,
                        'attachment_url'             => $attachmentUrl,
                        'status'                     => 'paid',
                        'is_settled'                 => false,
                        'source_type'                => 'credit_store_payment',
                        'source_label'               => 'Credit Settlement',
                        'is_vat_purchase'            => ($cspVat > 0),
                        'is_tas_purchase'            => ($cspVat <= 0),
                        'purchase_category'          => ($cspVat > 0 ? 'vat_purchase' : 'tas_purchase'),
                        'purchase_category_label'    => ($cspVat > 0 ? 'VAT Purchase (15%)' : 'TAS Purchase (0% VAT)'),
                        'purchase_category_badge'    => ($cspVat > 0 ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-primary-subtle text-primary border border-primary-subtle'),
                        'fs_no'                      => $csp->reference_no ?: '-',
                        'mrc_no'                     => '-',
                        'seller_tin'                 => $supplierTin,
                        'purchaser_tin'              => $defaultPurchaserDigits,
                        'purc_type'                  => 'PUR_GD',
                        'encoded_by'                 => 'wechacha',
                        'receipt_date_ec'            => $cspDate ? EthiopianDate::formatDateEc($cspDate, 'd-m-Y') : EthiopianDate::formatDateEc(now(), 'd-m-Y'),
                        'encoding_date_ec'           => EthiopianDate::formatDateEc($csp->created_at ?: now(), 'd-m-Y'),
                        'receipt_id'                 => $cspReceipt?->id,
                        'receipt_slip_url'           => $attachmentUrl,
                        'is_fuel'                    => false,
                        'is_verified_without_receipt'=> $isVerifiedNoRec,
                        'has_receipt'                => $hasRec,
                        'uom'                        => $cspUom,
                        'qty'                        => $cspQty,
                        'unit_price'                 => $cspUnitPrice,
                        'total_value'                => $cspBase,
                        'vat_category'               => $cspVatCat,
                        'calendar_type'              => $cspCalType,
                        'purchase_type'              => $cspPurchType,
                        'project_id'                 => $ledger?->project_id ?: ($cspReceipt?->project_id ?: null),
                        'item_description'           => $cspDesc,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning("monthMovements credit store payments mapping error: " . $e->getMessage());
            }
        }

        // Chronologically sort all expenses
        $formattedExpenses = $formattedExpenses->sortBy(function ($item) {
            return $item['raw_date'] ?? $item['date_gc'] ?? '';
        })->values();

        // 3. OCR Scanned Receipts in this Ethiopian month
        $ocrItems = collect();
        if (Schema::hasTable('receipt_items')) {
            try {
                $ocrQuery = \App\Models\ReceiptItem::with(['receipt', 'receipt.project', 'receipt.uploader'])
                    ->whereHas('receipt', function ($rq) use ($startDate, $endDate, $start, $end) {
                        $rq->where(function ($sub) use ($startDate, $endDate, $start, $end) {
                            $sub->whereBetween('receipt_date', [$startDate, $endDate])
                                ->orWhereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
                            if (Schema::hasTable('expense_requests')) {
                                $sub->orWhereExists(function ($ex) use ($start, $end) {
                                    $ex->select(DB::raw(1))
                                       ->from('expense_requests')
                                       ->where(function ($w) {
                                           $w->whereColumn('expense_requests.id', 'receipts.purchasable_id')
                                             ->orWhereColumn('expense_requests.request_number', 'receipts.receipt_number');
                                       })
                                       ->where(function ($mq) use ($start, $end) {
                                           $mq->whereBetween('paid_at', [$start, $end])
                                              ->orWhereBetween('created_at', [$start, $end]);
                                       });
                                });
                            }
                        });
                    })
                    ->orderBy('id', 'asc');

                $ocrItems = $ocrQuery->get();
            } catch (\Throwable $e) {
                Log::warning("monthMovements ReceiptItem query: " . $e->getMessage());
                $ocrItems = collect();
            }
        }

        // Also include standalone Receipts that don't have receipt_items (mapped safely in-memory)
        if (Schema::hasTable('receipts')) {
            try {
                $srQuery = \App\Models\Receipt::with(['project', 'uploader'])
                    ->doesntHave('items')
                    ->where(function ($rq) use ($startDate, $endDate, $start, $end) {
                        $rq->where(function ($sub) use ($startDate, $endDate, $start, $end) {
                            $sub->whereBetween('receipt_date', [$startDate, $endDate])
                                ->orWhereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
                            if (Schema::hasTable('expense_requests')) {
                                $sub->orWhereExists(function ($ex) use ($start, $end) {
                                    $ex->select(DB::raw(1))
                                       ->from('expense_requests')
                                       ->where(function ($w) {
                                           $w->whereColumn('expense_requests.id', 'receipts.purchasable_id')
                                             ->orWhereColumn('expense_requests.request_number', 'receipts.receipt_number');
                                       })
                                       ->where(function ($mq) use ($start, $end) {
                                           $mq->whereBetween('paid_at', [$start, $end])
                                              ->orWhereBetween('created_at', [$start, $end]);
                                       });
                                });
                            }
                        });
                    });

                $standaloneReceipts = $srQuery->get();

                foreach ($standaloneReceipts as $sr) {
                    $taxType = strtolower((string)($sr->tax_type ?? ''));
                    $isNoVatExplicit = ($taxType === 'no_vat')
                        || !empty($sr->no_vat_reason_code)
                        || ($sr->category === 'fuel' && (float)$sr->vat_amount <= 0);

                    $subtotal = (float)$sr->subtotal;
                    $vat = (float)$sr->vat_amount;
                    $total = (float)$sr->total_amount;
                    if ($total <= 0 && ($subtotal > 0 || $vat > 0)) {
                        $total = round($subtotal + $vat, 2);
                    }
                    if ($subtotal <= 0 && $total > 0) {
                        if ($vat > 0) {
                            $subtotal = round($total - $vat, 2);
                        } elseif (!$isNoVatExplicit && $taxType === 'vat') {
                            $subtotal = round($total / 1.15, 2);
                            $vat = round($total - $subtotal, 2);
                        } else {
                            $subtotal = $total;
                            $vat = 0.0;
                        }
                    }

                    $virtualItem = new \App\Models\ReceiptItem([
                        'receipt_id'       => $sr->id,
                        'item_description' => $sr->description ?: 'Purchased Goods/Services',
                        'vat_category'     => ($vat > 0 ? 'G' : 'E'),
                        'calendar_type'    => 'G',
                        'purchase_type'    => ($vat > 0 ? 3 : 6),
                        'uom'              => '9',
                        'qty'              => 1.00,
                        'unit_price'       => $subtotal,
                        'total_value'      => $subtotal,
                        'vat_amount'       => $vat,
                        'value_after_vat'  => $total,
                    ]);
                    $virtualItem->id = $sr->id;
                    $virtualItem->created_at = $sr->created_at;
                    $virtualItem->updated_at = $sr->updated_at ?: $sr->created_at;
                    $virtualItem->setRelation('receipt', $sr);
                    $ocrItems->push($virtualItem);
                }
            } catch (\Throwable $e) {
                Log::warning("monthMovements standalone receipts mapping error: " . $e->getMessage());
            }
        }

        // Include any CreditStorePayments in this month that have receipt_path (mapped in-memory)
        if (Schema::hasTable('credit_store_payments')) {
            try {
                $paymentSlips = \App\Models\CreditStorePayment::with(['ledger', 'ledger.project'])
                    ->whereNotNull('receipt_path')
                    ->where('receipt_path', '!=', '')
                    ->where(function ($q) use ($startDate, $endDate) {
                        $q->whereBetween('payment_date', [$startDate, $endDate])
                          ->orWhere(function ($sub) use ($startDate, $endDate) {
                              $sub->whereNull('payment_date')
                                  ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
                          });
                    })
                    ->get();

                $existingFiles = $ocrItems->map(fn($it) => $it->receipt?->file_path)->filter()->all();
                $existingCsIds = $ocrItems->map(fn($it) => ($it->receipt?->purchasable_type && str_contains($it->receipt->purchasable_type, 'CreditStorePayment')) ? $it->receipt->purchasable_id : null)->filter()->all();
                $existingReceiptNumbers = $ocrItems->map(fn($it) => $it->receipt?->receipt_number)->filter()->all();

                foreach ($paymentSlips as $p) {
                    if (in_array($p->receipt_path, $existingFiles) || in_array($p->id, $existingCsIds) || in_array('REC-PAY-' . $p->id, $existingReceiptNumbers) || in_array($p->reference_no, $existingReceiptNumbers)) {
                        continue;
                    }

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

                    $vReceipt = new \App\Models\Receipt([
                        'vendor_name'    => $ledger?->supplier_name ?: 'Credit Vendor',
                        'vendor_tin'     => '-',
                        'buyer_tin'      => '0038480010',
                        'receipt_number' => $p->reference_no ?: ('REC-PAY-' . $p->id),
                        'fs_no'          => $p->reference_no ?: '-',
                        'receipt_date'   => $p->payment_date ?: $p->created_at?->toDateString(),
                        'subtotal'       => $receiptSubtotal,
                        'vat_amount'     => $vatAmount,
                        'total_amount'   => $receiptTotal,
                        'file_path'      => $p->receipt_path,
                        'file_type'      => 'image',
                        'description'    => "Credit Payment #{$p->id} - " . ($ledger?->item_description ?: ($ledger?->notes ?: 'Materials')),
                    ]);
                    $vReceipt->id = 'P' . $p->id;
                    if ($ledger?->project) {
                        $vReceipt->setRelation('project', $ledger->project);
                    }

                    $vItem = new \App\Models\ReceiptItem([
                        'receipt_id'       => $vReceipt->id,
                        'item_description' => $ledger?->item_description ?: ($ledger?->notes ?: 'Credit settlement'),
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
                    $vItem->id = 'P' . $p->id;
                    $vItem->created_at = $p->created_at;
                    $vItem->updated_at = $p->updated_at ?: $p->created_at;
                    $vItem->setRelation('receipt', $vReceipt);

                    $ocrItems->push($vItem);
                    $existingFiles[] = $p->receipt_path;
                }
            } catch (\Throwable $e) {
                Log::warning("monthMovements paymentSlips mapping error: " . $e->getMessage());
            }
        }

        $formattedAllOcrItems = $ocrItems->map(function ($ocr) use ($defaultPurchaserDigits) {
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
            $fileUrl = null;
            if (!empty($filePath)) {
                try {
                    $fileUrl = $r->file_url ?: \App\Services\FileUploadService::url($filePath);
                } catch (\Throwable $e) {
                    $fileUrl = asset('storage/' . ltrim($filePath, '/'));
                }
            }
            $fileExt = $filePath ? strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) : '';
            $isPdf = in_array($fileExt, ['pdf']) || str_contains(strtolower($filePath ?? ''), 'pdf');

            $taxType = strtolower((string)($r?->tax_type ?? ''));
            $isNoVat = ($taxType === 'no_vat')
                || !empty($r?->no_vat_reason_code)
                || ($ocr->vat_category === 'E')
                || ((int)$ocr->purchase_type === 6 && $vatVal <= 0)
                || ($r?->category === 'fuel' && $vatVal <= 0);

            $parsed = is_string($r?->parsed_data ?? null) ? (json_decode($r->parsed_data, true) ?: []) : (array)($r?->parsed_data ?? []);
            $manuallyEditedAt = (int)($parsed['manually_edited_at'] ?? ($parsed['last_updated_at'] ?? 0));

            $fsNo = $r?->fs_no ?: ($r?->fs_no_raw ?: (!empty($parsed['receipt_no']) ? $parsed['receipt_no'] : '-'));
            $mrcNo = $r?->mrc_no ?: (!empty($parsed['mrc']) ? $parsed['mrc'] : '-');
            $sellerTin = $r?->vendor_tin ?: (!empty($parsed['seller_tin']) ? $parsed['seller_tin'] : '-');
            $purchaserTin = $r?->buyer_tin ?: (!empty($parsed['purchaser_tin']) ? $parsed['purchaser_tin'] : $defaultPurchaserDigits);
            $purcType = !empty($parsed['purc_type']) ? $parsed['purc_type'] : ($ocr->purc_type ?: ($isNoVat ? 'PUR_GD' : 'PUR_GD'));
            $encodedBy = !empty($parsed['encoded_by']) ? $parsed['encoded_by'] : ((Schema::hasTable('system_settings') ? SystemSetting::get('etax_encoded_by') : null) ?: 'wechacha');

            $vendorName = $r?->vendor_name
                ?: (!empty($parsed['seller_name']) ? $parsed['seller_name'] : (!empty($parsed['vendor_name']) ? $parsed['vendor_name'] : 'Vendor'));

            $linkedExpId = $r?->purchasable_type && (str_contains($r->purchasable_type, 'ExpenseRequest') || $r->purchasable_type === 'expense_request')
                ? $r->purchasable_id
                : null;
            $reqNo = $r?->receipt_number ?: ('REC-' . $ocr->id);

            return [
                'id'                 => $ocr->id,
                'receipt_id'         => $r?->id,
                'expense_request_id' => $linkedExpId,
                'request_number'     => $reqNo,
                'receipt_number'     => $reqNo,
                'fs_no'              => $fsNo,
                'mrc_no'             => $mrcNo,
                'vendor_name'        => $vendorName,
                'vendor_tin'         => $sellerTin,
                'seller_tin'         => $sellerTin,
                'buyer_tin'          => $purchaserTin,
                'purchaser_tin'      => $purchaserTin,
                'purc_type'          => $purcType,
                'encoded_by'         => $encodedBy,
                'date_ec'            => $date ? EthiopianDate::formatDateEc($date, 'DD/MM/YYYY EC') : '-',
                'date_gc'            => $date ? Carbon::parse($date)->format('M d, Y') : '-',
                'receipt_date_ec'    => !empty($parsed['receipt_date']) ? $parsed['receipt_date'] : ($date ? EthiopianDate::formatDateEc($date, 'd-m-Y') : '-'),
                'encoding_date_ec'   => !empty($parsed['encoding_date']) ? $parsed['encoding_date'] : (($r && $r->created_at) ? EthiopianDate::formatDateEc($r->created_at, 'd-m-Y') : '-'),
                'receipt_date_ymd'   => $date ? Carbon::parse($date)->format('Y-m-d') : '',
                'project_id'         => $r?->project_id,
                'project_name'       => $r?->project?->name ?: 'General',
                'category'           => $r?->category ?: ($ocr->vat_category ?: 'General'),
                'item_description'   => $ocr->item_description ?: ($r?->description ?: 'Purchased Goods/Services'),
                'vat_category'       => $ocr->vat_category ?: 'G',
                'purchase_type'      => (int)($ocr->purchase_type ?: 3),
                'tin_valid'          => (bool)($r?->tin_valid ?? (strlen(preg_replace('/\D/', '', $r?->vendor_tin ?? '')) === 10)),
                'fs_no_valid'        => (bool)($r?->fs_no_valid ?? (strlen(preg_replace('/\D/', '', $r?->fs_no ?? '')) === 8)),
                'base_value'         => $baseVal,
                'vat_amount'         => $vatVal,
                'total_value'        => $afterVal,
                'value_after_vat'    => $afterVal,
                'file_url'           => $fileUrl,
                'file_type'          => $isPdf ? 'pdf' : ($r?->file_type ?: 'image'),
                'is_pdf'             => $isPdf,
                'is_vat_receipt'     => ($vatVal > 0 && !$isNoVat),
                'is_no_vat'          => $isNoVat,
                'receipt_timestamp'  => $r?->receipt_date ? Carbon::parse($r->receipt_date)->timestamp : ($date ? Carbon::parse($date)->timestamp : 0),
                'created_timestamp'  => $ocr->created_at ? Carbon::parse($ocr->created_at)->timestamp : ($r?->created_at ? Carbon::parse($r->created_at)->timestamp : 0),
                'updated_timestamp'  => max(
                    $ocr->updated_at ? Carbon::parse($ocr->updated_at)->timestamp : 0,
                    $r?->updated_at ? Carbon::parse($r->updated_at)->timestamp : 0
                ),
                'manually_edited_at' => $manuallyEditedAt,
            ];
        });

        $ocrTotalScannedCount = $formattedAllOcrItems->count();

        // ── DUPLICATE CHECK FOR SCANNED OCR RECEIPTS ─────────────────────────────
        // DUPLICATE RULE & COMMENT POLICY:
        // A receipt is a duplicate of another IF AND ONLY IF BOTH:
        // 1. FS No. (fiscal receipt number)
        // 2. Seller TIN
        // are identical. If only one matches, it is NOT a duplicate. Keep both receipts.
        //
        // HOW TO COMPARE:
        // Normalize before comparing: trim spaces, remove dashes and special characters,
        // ignore case, and remove leading zeros from the FS No.
        // Compare every receipt against all other receipts in this section only.
        //
        // FLAG, DON'T GUESS:
        // If FS No. or Seller TIN is unreadable or missing, keep the receipt and flag as
        // "Needs manual review". Do NOT treat as duplicate.
        //
        // IMMEDIATE BLOCK RULE:
        // When a duplicate group is found (same FS No. + same Seller TIN), PRESERVE THE FIRST
        // ORIGINAL RECEIPT and IMMEDIATELY BLOCK subsequent double receipts so they cannot block
        // or replace the first receipt.
        // Exclude all blocked double receipts from matrix totals and NEVER add them to Excel export.

        $dedupResult = $this->deduplicateOcrItemsList($formattedAllOcrItems);
        $uniqueCleanedCollection = $dedupResult['cleaned'];
        $duplicatesRemoved = $dedupResult['duplicates_removed'];
        $flaggedReviewList = $dedupResult['flagged_review'];

        $ocrDuplicatesRemovedCount = (int)collect($duplicatesRemoved)->sum('removed_count');
        $ocrNeedsReviewCount = count($flaggedReviewList);

        // Replace $formattedAllOcrItems with the cleaned collection (one row per unique receipt, double receipts blocked)
        $formattedAllOcrItems = $uniqueCleanedCollection;

        $formattedVatOcrItems = $formattedAllOcrItems->filter(fn($it) => $it['is_vat_receipt'])->values();
        $formattedTasOcrItems = $formattedAllOcrItems->filter(fn($it) => !$it['is_vat_receipt'])->values();

        $formattedOcrItems = $vatOnly ? $formattedVatOcrItems : $formattedAllOcrItems;

        $expPurchasesBase = (float)$formattedExpenses->sum('base_amount');
        $expInputVat      = (float)$formattedExpenses->sum('vat_amount');
        $ocrPurchasesBase = (float)$formattedVatOcrItems->sum('base_value');
        $ocrInputVat      = (float)$formattedVatOcrItems->sum('vat_amount');

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
                $prevExp = ExpenseRequest::where(function ($q) {
                        $q->whereIn('status', [ExpenseRequest::STATUS_PAID, 'paid', 'PAID'])
                          ->orWhereNotNull('paid_at');
                    })
                    ->whereNotIn('status', [ExpenseRequest::STATUS_REJECTED, 'rejected', 'Rejected'])
                    ->where(function ($q) use ($prevRange) {
                        $q->whereBetween('paid_at', [$prevRange['start'], $prevRange['end']])
                          ->orWhere(function ($sub) use ($prevRange) {
                              $sub->whereNull('paid_at')
                                  ->whereBetween('created_at', [$prevRange['start'], $prevRange['end']]);
                          })
                          ->orWhereBetween('created_at', [$prevRange['start'], $prevRange['end']]);
                    })
                    ->where(function ($q) {
                        $q->whereNull('vat_type')->orWhere('vat_type', '!=', 'vat_b');
                    })
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
        $hasActiveOcr = $formattedOcrItems->isNotEmpty() || $ocrInputVat > 0;
        $hasActiveTransactions = $unsettledIncomes->isNotEmpty() || $unsettledExpenses->isNotEmpty() || $hasActiveOcr;
        $hasPriorSettlement = ($mSettlement !== null);

        $allIncomesSettled = $incomes->isNotEmpty() && $unsettledIncomes->isEmpty();
        $allExpensesSettled = ($expenses->isEmpty() || $unsettledExpenses->isEmpty()) && !$hasActiveOcr;

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

            $activePurchasesBase = max((float)$activeExpenses->sum('base_amount'), $ocrPurchasesBase);
            $activeInputVat      = max((float)$activeExpenses->sum('vat_amount'), $ocrInputVat);

            $dispSalesBase     = (float)$activeIncomes->sum('sales_base');
            $dispOutputVat     = (float)$activeIncomes->sum('output_vat');
            $dispPurchasesBase = $activePurchasesBase;
            $dispInputVat      = $activeInputVat;
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

        $totalCreditableInput = $isCleared ? 0.0 : round($dispInputVat + $dispOpeningCredit, 2);
        $summaryNet['total_creditable_input'] = $totalCreditableInput;

        // 6. Build TAS / e-Tax Purchases Collection
        // Requirement: Seller TIN is added, receipt is added/verified, covers BOTH all VAT purchases and non-VAT (0% TAS / Fuel)
        $existingTasVouchers = [];
        $existingTasReceipts = [];
        $tasPurchasesCollection = collect();

        foreach ($formattedExpenses as $exp) {
            $rawTin = (string)($exp['seller_tin'] ?? '');
            $cleanTin = preg_replace('/\D/', '', $rawTin);
            // Requirement 1: Seller TIN is added (10 digits ERCA TIN or valid digits not '-')
            $hasSellerTin = (strlen($cleanTin) === 10) || (!empty($cleanTin) && $rawTin !== '-' && strlen($cleanTin) >= 9);

            // Requirement 2: Receipt is added
            $hasReceipt = (!empty($exp['fs_no']) && $exp['fs_no'] !== '-')
                || !empty($exp['receipt_slip_url'])
                || !empty($exp['attachment_url'])
                || !empty($exp['receipt_id'])
                || (!empty($exp['payment_reference']) && $exp['payment_reference'] !== '-' && preg_match('/(rec|fs|rcp)/i', (string)$exp['payment_reference']));

            if ($hasSellerTin && $hasReceipt) {
                $tasPurchasesCollection->push($exp);
                if (!empty($exp['request_number'])) $existingTasVouchers[] = $exp['request_number'];
                if (!empty($exp['receipt_id'])) $existingTasReceipts[] = $exp['receipt_id'];
            }
        }

        // Also merge any OCR scanned receipts from $formattedAllOcrItems that have a Seller TIN and are not duplicates
        foreach ($formattedAllOcrItems as $ocr) {
            $rawTin = (string)($ocr['seller_tin'] ?? '');
            $cleanTin = preg_replace('/\D/', '', $rawTin);
            $hasSellerTin = (strlen($cleanTin) === 10) || (!empty($cleanTin) && $rawTin !== '-' && strlen($cleanTin) >= 9);
            if (!$hasSellerTin) {
                continue;
            }

            $baseVal = (float)($ocr['base_value'] ?? 0);
            $vatVal = (float)($ocr['vat_amount'] ?? 0);
            $whtVal = (float)($ocr['withholding_amount'] ?? 0);
            $totalVal = (float)($ocr['total_value'] ?? ($baseVal + $vatVal));
            $netVal = (float)($ocr['net_disbursed'] ?? max(0, $totalVal - $whtVal));

            $ocrReceiptId = $ocr['receipt_id'] ?? $ocr['id'];
            $ocrReqNo = $ocr['request_number'] ?? '';
            if (in_array($ocrReceiptId, $existingTasReceipts) || ($ocrReqNo && in_array($ocrReqNo, $existingTasVouchers))) {
                // Ensure existing entry in tasPurchasesCollection is synchronized with the latest updated OCR values
                $tasPurchasesCollection->transform(function ($tasItem) use ($ocr, $ocrReceiptId, $ocrReqNo, $baseVal, $vatVal, $netVal) {
                    $matchId = (!empty($tasItem['receipt_id']) && $tasItem['receipt_id'] == $ocrReceiptId)
                        || ($ocrReqNo && !empty($tasItem['request_number']) && $tasItem['request_number'] == $ocrReqNo);
                    if ($matchId) {
                        if (!empty($ocr['vendor_name'])) $tasItem['payee'] = $ocr['vendor_name'];
                        if (!empty($ocr['seller_tin']) && $ocr['seller_tin'] !== '-') $tasItem['seller_tin'] = $ocr['seller_tin'];
                        if (!empty($ocr['fs_no']) && $ocr['fs_no'] !== '-') $tasItem['fs_no'] = $ocr['fs_no'];
                        if (!empty($ocr['mrc_no']) && $ocr['mrc_no'] !== '-') $tasItem['mrc_no'] = $ocr['mrc_no'];
                        if (!empty($ocr['date_ec']) && $ocr['date_ec'] !== '-') $tasItem['date_ec'] = $ocr['date_ec'];
                        if (!empty($ocr['date_gc']) && $ocr['date_gc'] !== '-') $tasItem['date_gc'] = $ocr['date_gc'];
                        if (!empty($ocr['receipt_date_ec']) && $ocr['receipt_date_ec'] !== '-') $tasItem['receipt_date_ec'] = $ocr['receipt_date_ec'];
                        if (!empty($ocr['item_description'])) $tasItem['description'] = $ocr['item_description'];
                        if ($baseVal > 0) $tasItem['base_amount'] = $baseVal;
                        $tasItem['vat_amount'] = $vatVal;
                        $tasItem['net_disbursed'] = $netVal;
                        if (!empty($ocr['file_url'])) $tasItem['receipt_slip_url'] = $ocr['file_url'];
                    }
                    return $tasItem;
                });
                continue;
            }

            $tasPurchasesCollection->push([
                'id'                         => 'OCR-' . $ocr['id'],
                'request_number'             => $ocr['request_number'] ?: ('REC-' . $ocr['id']),
                'payment_reference'          => (!empty($ocr['fs_no']) && $ocr['fs_no'] !== '-') ? ('FS: ' . $ocr['fs_no']) : '-',
                'date_ec'                    => $ocr['date_ec'] ?: '-',
                'date_gc'                    => $ocr['date_gc'] ?: '-',
                'raw_date'                   => $ocr['receipt_date_ymd'] ?: '',
                'payee'                      => $ocr['vendor_name'] ?: 'Vendor',
                'project_name'               => $ocr['project_name'] ?: 'General',
                'category'                   => $ocr['category'] ?: 'materials',
                'account_name'               => 'OCR Cash Receipt',
                'base_amount'                => $baseVal,
                'vat_type'                   => ($vatVal > 0) ? 'inclusive' : 'none',
                'vat_rate'                   => ($vatVal > 0) ? 15.0 : 0.0,
                'vat_amount'                 => $vatVal,
                'has_withholding'            => !empty($ocr['has_withholding']) || $whtVal > 0,
                'withholding_rate'           => (float)($ocr['withholding_rate'] ?? 0),
                'withholding_amount'         => $whtVal,
                'withholding_receipt_number' => $ocr['withholding_receipt_number'] ?? '-',
                'withholding_receipt_url'    => $ocr['withholding_receipt_url'] ?? null,
                'withholding_settled'        => false,
                'net_disbursed'              => $netVal,
                'description'                => $ocr['item_description'] ?: 'Purchased Goods/Services',
                'attachment_url'             => $ocr['file_url'],
                'status'                     => 'verified',
                'is_settled'                 => false,
                'source_type'                => 'ocr_receipt',
                'source_label'               => 'OCR Receipt',
                'is_vat_purchase'            => (bool)$ocr['is_vat_receipt'],
                'is_tas_purchase'            => !(bool)$ocr['is_vat_receipt'],
                'purchase_category'          => $ocr['is_vat_receipt'] ? 'vat_purchase' : 'tas_purchase',
                'purchase_category_label'    => $ocr['is_vat_receipt'] ? 'VAT Purchase (15%)' : 'TAS Purchase (0% VAT)',
                'purchase_category_badge'    => $ocr['is_vat_receipt'] ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-primary-subtle text-primary border border-primary-subtle',
                'fs_no'                      => $ocr['fs_no'] ?: '-',
                'mrc_no'                     => $ocr['mrc_no'] ?: '-',
                'seller_tin'                 => $ocr['seller_tin'] ?: '-',
                'purchaser_tin'              => $ocr['purchaser_tin'] ?: $defaultPurchaserDigits,
                'purc_type'                  => $ocr['purc_type'] ?: 'PUR_GD',
                'encoded_by'                 => $ocr['encoded_by'] ?: 'wechacha',
                'receipt_date_ec'            => $ocr['receipt_date_ec'] ?: '-',
                'encoding_date_ec'           => $ocr['encoding_date_ec'] ?: '-',
                'receipt_id'                 => $ocr['receipt_id'] ?: $ocr['id'],
                'receipt_slip_url'           => $ocr['file_url'],
                'is_fuel'                    => (str_contains(strtolower($ocr['category'] ?? ''), 'fuel')),
            ]);

            $existingTasReceipts[] = $ocrReceiptId;
            if ($ocrReqNo) $existingTasVouchers[] = $ocrReqNo;
        }

        // If no records have seller TIN added yet in this month, fallback to 0% VAT purchases
        if ($tasPurchasesCollection->isEmpty()) {
            $tasPurchasesCollection = $formattedExpenses->where('is_tas_purchase', true)->values();
        } else {
            $tasPurchasesCollection = $tasPurchasesCollection->sortBy(function ($item) {
                return $item['raw_date'] ?? $item['date_gc'] ?? '';
            })->values();
        }

        $tasTotalAmount = (float)$tasPurchasesCollection->sum(function ($it) {
            return (float)($it['total_amount'] ?? ($it['gross_amount'] ?? (($it['base_amount'] ?? 0) + ($it['vat_amount'] ?? 0))));
        });

        $verifiedNoReceiptCollection = $formattedExpenses->where('is_verified_without_receipt', true)->values();
        $unverifiedExpensesCollection = $formattedExpenses->where('is_verified_without_receipt', false)->values();

        $whtExpensesCollection = $formattedExpenses->filter(function ($it) {
            return !empty($it['has_withholding']) || (float)($it['withholding_amount'] ?? 0) > 0;
        })->values();

        $wht2Collection = $whtExpensesCollection->filter(fn($it) => round((float)($it['withholding_rate'] ?? 0), 1) == 2.0)->values();
        $wht3Collection = $whtExpensesCollection->filter(fn($it) => in_array(round((float)($it['withholding_rate'] ?? 3), 1), [3.0, 0.0]))->values();
        $wht30Collection = $whtExpensesCollection->filter(fn($it) => round((float)($it['withholding_rate'] ?? 0), 1) == 30.0)->values();

        $whtSummary = [
            'total_wht_amount'    => round((float)$whtExpensesCollection->sum('withholding_amount'), 2),
            'wht_count'           => $whtExpensesCollection->count(),
            'wht_2_amount'        => round((float)$wht2Collection->sum('withholding_amount'), 2),
            'wht_2_count'         => $wht2Collection->count(),
            'wht_3_amount'        => round((float)$wht3Collection->sum('withholding_amount'), 2),
            'wht_3_count'         => $wht3Collection->count(),
            'wht_30_amount'       => round((float)$wht30Collection->sum('withholding_amount'), 2),
            'wht_30_count'        => $wht30Collection->count(),
            'with_receipt_count'  => $whtExpensesCollection->filter(fn($it) => !empty($it['withholding_receipt_url']) || (!empty($it['withholding_receipt_number']) && $it['withholding_receipt_number'] !== '-'))->count(),
            'total_base_amount'   => round((float)$whtExpensesCollection->sum('base_amount'), 2),
            'total_net_disbursed' => round((float)$whtExpensesCollection->sum('net_disbursed'), 2),
        ];

        return [
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
                'total_creditable_input' => $totalCreditableInput,
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
                'expense_count'          => $unverifiedExpensesCollection->count(),
                'vat_purchases_count'    => $unverifiedExpensesCollection->where('is_vat_purchase', true)->count(),
                'vat_purchases_base'     => (float)$unverifiedExpensesCollection->where('is_vat_purchase', true)->sum('base_amount'),
                'vat_purchases_vat'      => (float)$unverifiedExpensesCollection->where('is_vat_purchase', true)->sum('vat_amount'),
                'vat_purchases_wht'      => (float)$unverifiedExpensesCollection->where('is_vat_purchase', true)->sum('withholding_amount'),
                'vat_purchases_net'      => (float)$unverifiedExpensesCollection->where('is_vat_purchase', true)->sum('net_disbursed'),
                'tas_purchases_count'    => $tasPurchasesCollection->count(),
                'tas_purchases_base'     => (float)$tasPurchasesCollection->sum('base_amount'),
                'tas_purchases_vat'      => (float)$tasPurchasesCollection->sum('vat_amount'),
                'tas_purchases_wht'      => (float)$tasPurchasesCollection->sum('withholding_amount'),
                'tas_purchases_wht_count'=> $tasPurchasesCollection->where('withholding_amount', '>', 0)->count(),
                'tas_purchases_net'      => (float)$tasPurchasesCollection->sum('net_disbursed'),
                'ocr_count'              => $formattedOcrItems->count(),
                'ocr_vat_count'          => $formattedVatOcrItems->count(),
                'ocr_tas_count'          => $formattedTasOcrItems->count(),
                'ocr_all_count'          => $formattedAllOcrItems->count(),
                'ocr_total_scanned'      => $ocrTotalScannedCount,
                'ocr_duplicates_removed' => $ocrDuplicatesRemovedCount,
                'ocr_unique_used'        => $formattedAllOcrItems->count(),
                'ocr_needs_review_count' => $ocrNeedsReviewCount,
                'missing_receipt_count'  => $unverifiedExpensesCollection->where('has_receipt', false)->count(),
                'verified_no_receipt_count' => $verifiedNoReceiptCollection->count(),
                'wht_count'              => $whtSummary['wht_count'],
                'wht_amount'             => $whtSummary['total_wht_amount'],
                'total_count'            => $formattedIncomes->count() + $unverifiedExpensesCollection->count() + $formattedOcrItems->count(),
            ],
            'vat_only'      => $vatOnly,
            'incomes'       => $formattedIncomes->values(),
            'expenses'      => $unverifiedExpensesCollection,
            'all_expenses'  => $formattedExpenses->values(),
            'vat_purchases' => $unverifiedExpensesCollection->where('is_vat_purchase', true)->values(),
            'verified_no_receipt' => $verifiedNoReceiptCollection,
            'missing_receipt'     => $unverifiedExpensesCollection->where('has_receipt', false)->values(),
            'wht_expenses'  => $whtExpensesCollection,
            'wht_summary'   => $whtSummary,
            'tas_purchases' => $tasPurchasesCollection->values(),
            'tas_purchases_count' => $tasPurchasesCollection->count(),
            'tas_purchases_total_amount' => $tasTotalAmount,
            'tas_purchases_wht' => (float)$tasPurchasesCollection->sum('withholding_amount'),
            'tas_purchases_wht_count' => $tasPurchasesCollection->where('withholding_amount', '>', 0)->count(),
            'tas_purchases_net_total' => (float)$tasPurchasesCollection->sum('net_disbursed'),
            'ocr_items'     => $formattedOcrItems->values(),
            'vat_ocr_items' => $formattedVatOcrItems->values(),
            'tas_ocr_items' => $formattedTasOcrItems->values(),
            'all_ocr_items' => $formattedAllOcrItems->values(),
            'duplicates_removed' => $duplicatesRemoved,
            'duplicates_removed_count' => $ocrDuplicatesRemovedCount,
            'ocr_needs_manual_review' => collect($flaggedReviewList)->values(),
            'ocr_total_scanned' => $ocrTotalScannedCount,
            'ocr_unique_used'   => $formattedAllOcrItems->count(),
        ];
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
            $bankAccount = null;
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

            // Deduct from Chart of Account (COA) if applicable
            $taxCoaId = $settlement->coa_id ?? ($bankAccount?->coa_id ?? null);
            if ($payNow && $taxCoaId && $totalPayable > 0) {
                $taxCoa = ChartOfAccount::find($taxCoaId);
                if ($taxCoa) {
                    $taxCoa->decrement('current_balance', $totalPayable);
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
            $bankAccount = null;
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

            // Deduct from Chart of Account (COA) if applicable
            $taxCoaId = $settlement->coa_id ?? ($bankAccount?->coa_id ?? null);
            if ($taxCoaId && $settlement->total_tax_paid > 0) {
                $taxCoa = ChartOfAccount::find($taxCoaId);
                if ($taxCoa) {
                    $taxCoa->decrement('current_balance', $settlement->total_tax_paid);
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
        } elseif ($type === 'withholding' || $type === 'wht') {
            return $this->exportWithholdingExcel($request, $startDate, $endDate, $year, $month, $monthSuffix);
        } else {
            return $this->exportPurchaseVatExcel($request, $startDate, $endDate, $year, $month, $monthSuffix);
        }
    }

    /**
     * Export Withholding Tax (WHT) Deductions Report to Excel / CSV.
     */
    protected function exportWithholdingExcel(Request $request, $startDate, $endDate, $year, $month, $monthSuffix)
    {
        $filename = "Withholding_Tax_Report_{$year}_{$monthSuffix}.xlsx";
        $tempPath = storage_path("app/public/{$filename}");

        $headers = [
            'Voucher / Request #',
            'Receipt Date (EC)',
            'Receipt Date (GC)',
            'Supplier / Payee Name',
            'Seller TIN',
            'FS / Receipt #',
            'Category',
            'Project',
            'Taxable Base Amount (ETB)',
            'WHT Rate (%)',
            'Withholding Deducted (ETB)',
            'Net Amount Disbursed (ETB)',
            'WHT Certificate / Voucher #',
        ];

        $rows = [];
        $movements = $this->calculateMonthMovementsData($year, $month, false);
        $whtList = collect($movements['wht_expenses'] ?? []);

        // Sort latest information first (transaction date descending, then id descending)
        $whtList = $whtList->sortByDesc(function ($item) {
            $d = $item['date_gc'] ?? ($item['disbursement_date'] ?? ($item['created_at'] ?? ''));
            $ts = $d ? Carbon::parse($d)->timestamp : 0;
            $id = (int)($item['id'] ?? 0);
            return sprintf('%012d_%010d', $ts, $id);
        })->values();

        $totalBase = 0.0;
        $totalWht = 0.0;
        $totalNet = 0.0;

        foreach ($whtList as $item) {
            $baseVal = round((float)($item['base_amount'] ?? 0), 2);
            $whtRateVal = round((float)($item['withholding_rate'] ?? 0), 1);
            $whtAmtVal = round((float)($item['withholding_amount'] ?? 0), 2);
            $netVal = round((float)($item['net_disbursed'] ?? max(0, $baseVal - $whtAmtVal)), 2);

            $totalBase += $baseVal;
            $totalWht += $whtAmtVal;
            $totalNet += $netVal;

            $rows[] = [
                $item['request_number'] ?? '-',
                $item['date_ec'] ?? '-',
                $item['date_gc'] ?? '-',
                $item['payee'] ?? 'Vendor',
                $item['seller_tin'] ?? '-',
                $item['fs_no'] ?? '-',
                $item['category'] ?? 'Expense',
                $item['project_name'] ?? 'General',
                $baseVal,
                $whtRateVal,
                $whtAmtVal,
                $netVal,
                $item['withholding_receipt_number'] ?? '-',
            ];
        }

        // Add TOTAL summary row at the bottom of the Excel sheet
        if (!empty($rows)) {
            $rows[] = [
                'TOTAL',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                round($totalBase, 2),
                '',
                round($totalWht, 2),
                round($totalNet, 2),
                '',
            ];
        }

        return $this->writeExcelFile($headers, $rows, $tempPath, $filename);
    }

    /**
     * Export Sales Excel (13 Columns e-Tax format).
     */
    protected function exportSalesExcel(Request $request, string $startDate, string $endDate, int $year, ?int $month, string $monthSuffix)
    {
        $userCalendar = $this->resolveCalendarOption($request);
        $calSuffix = ($userCalendar === 'G') ? '_GC' : (($userCalendar === 'E') ? '_EC' : '');
        $filename = 'Sales_Report' . $calSuffix . $monthSuffix . '_' . now()->format('Ymd_His') . '.xlsx';
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
                ->orderByRaw('COALESCE(income_date, receipt_date) desc')
                ->orderBy('id', 'desc')
                ->get();
        }

        $defaultSellerTin = SystemSetting::get('company_tin', SystemSetting::get('purchaser_tin', '38480010'));
        $defaultSellerDigits = (int)preg_replace('/^0+/', '', preg_replace('/[^0-9]/', '', (string)$defaultSellerTin));
        if (!$defaultSellerDigits) $defaultSellerDigits = 38480010;

        $rows = [];
        $totalQty = 0.0;
        $totalPrice = 0.0;

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

            $totalQty += $qtyVal;
            $totalPrice += $priceVal;

            // Col L: Receipt Date (Format: DD/MM/YYYY EC or DD/MM/YYYY GC)
            $rDate = $inc->receipt_date ?: $inc->income_date;
            if ($userCalendar === 'G') {
                $receiptDateFormatted = $this->formatExportDate($rDate, null, 'G', '/');
            } else {
                $receiptDateFormatted = $this->formatExportDate($rDate, null, 'E', '/') . ' EC';
            }

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

        // Add TOTAL summary row at the bottom of the Excel sheet
        if (!empty($rows)) {
            $rows[] = [
                'TOTAL',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                round($totalQty, 2),
                round($totalPrice, 2),
                '',
                '',
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
        $userCalendar = $this->resolveCalendarOption($request) ?: 'E';
        $calSuffix = ($userCalendar === 'G') ? '_GC' : '_EC';
        $filename = 'Purchase_TAS' . $calSuffix . $monthSuffix . '_' . now()->format('Ymd_His') . '.xlsx';
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

        $isTasFiltered = ($request->input('category') === 'tas')
            || ($request->input('filter') === 'tas')
            || ($request->input('purchase_category') === 'tas')
            || $request->boolean('tas_only');

        $isVatFiltered = ($request->input('category') === 'vat')
            || ($request->input('filter') === 'vat')
            || ($request->input('purchase_category') === 'vat')
            || $request->boolean('vat_only');

        $ocrItems = collect();
        if (Schema::hasTable('receipt_items')) {
            $ocrQuery = ReceiptItem::with(['receipt', 'receipt.project', 'receipt.uploader'])
                ->whereHas('receipt', function ($rq) use ($startDate, $endDate) {
                    $rq->whereBetween('receipt_date', [$startDate, $endDate])
                       ->orWhere(function ($sub) use ($startDate, $endDate) {
                           $sub->whereNull('receipt_date')
                               ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
                       });
                });

            if ($isTasFiltered) {
                $ocrQuery->where(function ($q) {
                    $q->where('vat_amount', '<=', 0)
                      ->orWhereHas('receipt', function ($rq) {
                          $rq->where('vat_amount', '<=', 0);
                          if (Schema::hasColumn('receipts', 'tax_type')) {
                              $rq->orWhere('tax_type', 'no_vat');
                          }
                      });
                });
            } elseif ($isVatFiltered) {
                $ocrQuery->where(function ($q) {
                    $q->where('vat_amount', '>', 0)
                      ->orWhereHas('receipt', fn($rq) => $rq->where('vat_amount', '>', 0));
                });
            }

            $ocrItems = $ocrQuery->orderBy('id', 'asc')->get();
        }

        $standaloneReceipts = collect();
        if (Schema::hasTable('receipts')) {
            $standaloneQuery = Receipt::with(['project', 'uploader'])
                ->where(function ($rq) use ($startDate, $endDate) {
                    $rq->whereBetween('receipt_date', [$startDate, $endDate])
                       ->orWhere(function ($sub) use ($startDate, $endDate) {
                           $sub->whereNull('receipt_date')
                               ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
                       });
                })
                ->doesntHave('items');

            if ($isTasFiltered) {
                $standaloneQuery->where(function ($q) {
                    $q->where('vat_amount', '<=', 0);
                    if (Schema::hasColumn('receipts', 'tax_type')) {
                        $q->orWhere('tax_type', 'no_vat');
                    }
                });
            } elseif ($isVatFiltered) {
                $standaloneQuery->where('vat_amount', '>', 0);
            }

            $standaloneReceipts = $standaloneQuery->orderByRaw('COALESCE(receipt_date, created_at) desc')->orderBy('id', 'desc')->get();
        }

        $defaultPurchaserTin = SystemSetting::get('purchaser_tin', SystemSetting::get('company_tin', '38480010'));
        $defaultPurchaserDigits = (int)preg_replace('/^0+/', '', preg_replace('/[^0-9]/', '', (string)$defaultPurchaserTin));
        if (!$defaultPurchaserDigits) $defaultPurchaserDigits = 38480010;

        $rows = [];

        // If specific Ethiopian month is requested, export exact qualified e-Tax purchases matching movements screen
        if ($month && $month >= 1 && $month <= 13) {
            $movements = $this->calculateMonthMovementsData($year, $month, false);
            $tasPurchases = collect($movements['tas_purchases'] ?? []);
            if ($isTasFiltered) {
                $tasPurchases = $tasPurchases->filter(fn($it) => empty($it['is_vat_purchase']) || !empty($it['is_tas_purchase']));
            } elseif ($isVatFiltered) {
                $tasPurchases = $tasPurchases->filter(fn($it) => !empty($it['is_vat_purchase']));
            }

            // Sort latest information first (date descending, then id descending)
            $tasPurchases = $tasPurchases->sortByDesc(function ($item) {
                $d = $item['raw_date'] ?? ($item['date_gc'] ?? ($item['disbursement_date'] ?? ($item['created_at'] ?? '')));
                $ts = $d ? Carbon::parse($d)->timestamp : 0;
                $id = (int)($item['id'] ?? 0);
                return sprintf('%012d_%010d', $ts, $id);
            })->values();

            $totalAmount = 0.0;
            foreach ($tasPurchases as $item) {
                $rawPurchaser = (string)($item['purchaser_tin'] ?? $defaultPurchaserDigits);
                $pDigits = preg_replace('/^0+/', '', preg_replace('/[^0-9]/', '', $rawPurchaser));
                $purchaserTinVal = ($pDigits !== '') ? (int)$pDigits : $defaultPurchaserDigits;

                $rawSeller = (string)($item['seller_tin'] ?? '');
                $sDigits = preg_replace('/^0+/', '', preg_replace('/[^0-9]/', '', $rawSeller));
                $sellerTinVal = ($sDigits !== '') ? (int)$sDigits : (trim($rawSeller) ?: '');

                $amountVal = round((float)($item['total_amount'] ?? ($item['gross_amount'] ?? (($item['base_amount'] ?? 0) + ($item['vat_amount'] ?? 0)))), 2);
                $totalAmount += $amountVal;

                $receiptNo = '';
                if (!empty($item['fs_no']) && $item['fs_no'] !== '-') {
                    $cleanFs = trim($item['fs_no']);
                    if (preg_match('/^fs/i', $cleanFs)) {
                        $receiptNo = strtoupper($cleanFs);
                    } elseif (preg_match('/^\d+$/', $cleanFs)) {
                        $receiptNo = 'FS' . str_pad($cleanFs, 8, '0', STR_PAD_LEFT);
                    } else {
                        $receiptNo = $cleanFs;
                    }
                } elseif (!empty($item['payment_reference']) && $item['payment_reference'] !== '-') {
                    $receiptNo = trim($item['payment_reference']);
                } else {
                    $receiptNo = trim($item['request_number'] ?? '');
                }

                $rawDate = $item['raw_date'] ?? ($item['date_gc'] ?? null);
                $ecDate  = $item['receipt_date_ec'] ?? ($item['date_ec'] ?? null);
                $receiptDate = $this->formatExportDate($rawDate, $ecDate, $userCalendar, '-');
                $encodingDate = $this->formatExportDate(now(), $item['encoding_date_ec'] ?? null, $userCalendar, '-');
                $mrc = (!empty($item['mrc_no']) && $item['mrc_no'] !== '-') ? trim($item['mrc_no']) : '';
                $purcType = !empty($item['purc_type']) ? $item['purc_type'] : 'PUR_GD';
                $encodedBy = !empty($item['encoded_by']) ? $item['encoded_by'] : 'wechacha';

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

            if (!empty($rows)) {
                $rows[] = [
                    'TOTAL',
                    '',
                    round($totalAmount, 2),
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                ];
                return $this->writeExcelFile($headers, $rows, $tempPath, $filename);
            }
        }

        // ── Deduplicate OCR Receipts for Purchase TAS (Immediate Block Rule) ─────
        $normalizeFs = function ($rawFs) {
            $raw = trim((string)$rawFs);
            $stripped = preg_replace('/^FS[:\s_#-]*\s*/i', '', $raw);
            $alphanumeric = strtoupper(preg_replace('/[^0-9A-Z]/i', '', $stripped));
            $normalized = ltrim($alphanumeric, '0');
            return ($normalized === '' && $alphanumeric !== '') ? '0' : $normalized;
        };
        $normalizeTin = function ($rawTin) {
            return strtoupper(preg_replace('/[^0-9A-Z]/i', '', trim((string)$rawTin)));
        };

        $groupedByMatch = [];
        $uniqueCleanedItems = [];
        foreach ($ocrItems as $item) {
            $r = $item->receipt;
            $rawFs = $r ? ($r->fs_no ?: ($r->fs_no_raw ?: ($r->receipt_number ?: ''))) : '';
            $rawTin = $r ? ($r->vendor_tin ?: '') : '';
            $normFs = $normalizeFs($rawFs);
            $normTin = $normalizeTin($rawTin);

            if ($normFs === '' || $normFs === '0' || $normTin === '' || strlen($normTin) < 5) {
                $uniqueCleanedItems[] = $item;
            } else {
                $groupedByMatch[$normTin . '___' . $normFs][] = $item;
            }
        }
        foreach ($groupedByMatch as $group) {
            $receiptsInGroup = [];
            foreach ($group as $item) {
                $rId = (string)($item->receipt_id ?: ($item->receipt?->id ?: $item->id));
                $receiptsInGroup[$rId][] = $item;
            }
            if (count($receiptsInGroup) === 1) {
                foreach ($group as $it) $uniqueCleanedItems[] = $it;
                continue;
            }
            $receiptList = array_values($receiptsInGroup);
            usort($receiptList, function ($itemsA, $itemsB) {
                $itemA = $itemsA[0];
                $itemB = $itemsB[0];
                $rA = $itemA->receipt;
                $rB = $itemB->receipt;
                $uploadA = $itemA->created_at ? $itemA->created_at->timestamp : ($rA?->created_at ? $rA->created_at->timestamp : 0);
                $uploadB = $itemB->created_at ? $itemB->created_at->timestamp : ($rB?->created_at ? $rB->created_at->timestamp : 0);
                if ($uploadA !== $uploadB && $uploadA > 0 && $uploadB > 0) return $uploadA <=> $uploadB;
                return strcmp((string)$itemA->id, (string)$itemB->id);
            });
            foreach ($receiptList[0] as $keptItem) {
                $uniqueCleanedItems[] = $keptItem;
            }
        }
        $ocrItems = collect($uniqueCleanedItems);

        // Sort latest information first (receipt date descending, then id descending)
        $ocrItems = $ocrItems->sortByDesc(function ($item) {
            $r = $item->receipt;
            $d = ($r && $r->receipt_date) ? $r->receipt_date : ($item->created_at ?: ($r?->created_at ?: null));
            $ts = $d ? Carbon::parse($d)->timestamp : 0;
            $rId = (int)($r?->id ?: $item->id);
            return sprintf('%012d_%010d', $ts, $rId);
        })->values();

        foreach ($ocrItems as $item) {
            $r = $item->receipt;
            $buyerTin = $r ? ($r->buyer_tin ?: $defaultPurchaserTin) : $defaultPurchaserTin;
            $buyerDigits = preg_replace('/^0+/', '', preg_replace('/[^0-9]/', '', (string)$buyerTin));
            $purchaserTinVal = ($buyerDigits !== '') ? (int)$buyerDigits : $defaultPurchaserDigits;

            $parsed = is_string($r?->parsed_data ?? null) ? (json_decode($r->parsed_data, true) ?: []) : (array)($r?->parsed_data ?? []);
            $manuallyEditedAt = (int)($parsed['manually_edited_at'] ?? ($parsed['last_updated_at'] ?? 0));
            $rawTin = (string)($r?->vendor_tin ?: (!empty($parsed['seller_tin']) ? $parsed['seller_tin'] : ''));
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
            $rawDate = ($r && $r->receipt_date) ? $r->receipt_date : $item->created_at;
            $rawEcDate = !empty($parsed['receipt_date']) ? $parsed['receipt_date'] : null;
            $receiptDate = $this->formatExportDate($rawDate, $rawEcDate, $userCalendar, '-');
            $encodingDate = $this->formatExportDate($r?->created_at ?: now(), !empty($parsed['encoding_date']) ? $parsed['encoding_date'] : null, $userCalendar, '-');
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

            $rawDate = $r->receipt_date ?: $r->created_at;
            $rawEcDate = !empty($parsed['receipt_date']) ? $parsed['receipt_date'] : null;
            $receiptDate = $this->formatExportDate($rawDate, $rawEcDate, $userCalendar, '-');
            $encodingDate = $this->formatExportDate($r->created_at ?: now(), !empty($parsed['encoding_date']) ? $parsed['encoding_date'] : null, $userCalendar, '-');
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
            $sDay = Carbon::parse($startDate)->startOfDay();
            $eDay = Carbon::parse($endDate)->endOfDay();
            $expenses = ExpenseRequest::with(['user', 'bankAccount', 'chartOfAccount', 'paidBy'])
                ->where(function ($q) {
                    $q->whereIn('status', [ExpenseRequest::STATUS_PAID, 'paid', 'PAID'])
                      ->orWhereNotNull('paid_at');
                })
                ->whereNotIn('status', [ExpenseRequest::STATUS_REJECTED, 'rejected', 'Rejected'])
                ->where(function ($q) use ($sDay, $eDay) {
                    $q->whereBetween('paid_at', [$sDay, $eDay])
                      ->orWhere(function ($sub) use ($sDay, $eDay) {
                          $sub->whereNull('paid_at')->whereBetween('created_at', [$sDay, $eDay]);
                      })
                      ->orWhereBetween('created_at', [$sDay, $eDay]);
                })
                ->where(function ($q) {
                    $q->whereNull('vat_type')->orWhere('vat_type', '!=', 'vat_b');
                })
                ->orderByRaw('COALESCE(paid_at, created_at) desc')
                ->orderBy('id', 'desc')
                ->get();

            foreach ($expenses as $exp) {
                $gross = (float)($exp->gross_amount > 0 ? $exp->gross_amount : $exp->amount);
                $rows[] = [
                    $defaultPurchaserDigits,
                    '',
                    round($gross, 2),
                    $exp->payment_reference ?: $exp->request_number,
                    $this->formatExportDate($exp->paid_at ?: $exp->created_at, null, $userCalendar, '-'),
                    $this->formatExportDate($exp->created_at ?: now(), null, $userCalendar, '-'),
                    '',
                    'PUR_GD',
                    'wechacha',
                ];
            }
        }

        // Add TOTAL summary row at the bottom of the Excel sheet
        if (!empty($rows)) {
            $totalAmount = 0.0;
            foreach ($rows as $row) {
                $totalAmount += (float)($row[2] ?? 0);
            }
            $rows[] = [
                'TOTAL',
                '',
                round($totalAmount, 2),
                '',
                '',
                '',
                '',
                '',
                '',
            ];
        }

        return $this->writeExcelFile($headers, $rows, $tempPath, $filename);
    }

    /**
     * Export Purchase VAT Report (ERCA 15 Columns matching pro.doc).
     */
    protected function exportPurchaseVatExcel(Request $request, string $startDate, string $endDate, int $year, ?int $month, string $monthSuffix)
    {
        $userCalendar = $this->resolveCalendarOption($request);
        $calSuffix = ($userCalendar === 'E') ? '_EC' : (($userCalendar === 'G') ? '_GC' : '');
        $filename = 'Purchase_VAT_Report' . $calSuffix . $monthSuffix . '_' . now()->format('Ymd_His') . '.xlsx';
        $tempPath = storage_path('app/purch_vat_' . uniqid() . '.xlsx');

        // Exact 15-column ERCA Purchase VAT headers from pro.doc
        $headers = [
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
        ];

        // 1. Gather all itemized OCR receipts in date range
        $ocrItems = collect();
        if (Schema::hasTable('receipt_items')) {
            $ocrItems = ReceiptItem::with(['receipt', 'receipt.project', 'receipt.uploader'])
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

        // 2. Also include standalone Receipts that don't have receipt_items (virtualized as items)
        if (Schema::hasTable('receipts')) {
            $standaloneReceipts = Receipt::with(['project', 'uploader'])
                ->doesntHave('items')
                ->where(function ($rq) use ($startDate, $endDate) {
                    $rq->whereBetween('receipt_date', [$startDate, $endDate])
                       ->orWhere(function ($sub) use ($startDate, $endDate) {
                           $sub->whereNull('receipt_date')
                               ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
                       });
                })
                ->orderBy('id', 'asc')
                ->get();

            foreach ($standaloneReceipts as $sr) {
                $taxType = strtolower((string)($sr->tax_type ?? ''));
                $isNoVatExplicit = ($taxType === 'no_vat')
                    || !empty($sr->no_vat_reason_code)
                    || ($sr->category === 'fuel' && (float)$sr->vat_amount <= 0);

                $subtotal = (float)$sr->subtotal;
                $vat = (float)$sr->vat_amount;
                $total = (float)$sr->total_amount;
                if ($total <= 0 && ($subtotal > 0 || $vat > 0)) {
                    $total = round($subtotal + $vat, 2);
                }
                if ($subtotal <= 0 && $total > 0) {
                    if ($vat > 0) {
                        $subtotal = round($total - $vat, 2);
                    } elseif (!$isNoVatExplicit && $taxType === 'vat') {
                        $subtotal = round($total / 1.15, 2);
                        $vat = round($total - $subtotal, 2);
                    } else {
                        $subtotal = $total;
                        $vat = 0.0;
                    }
                }

                // Purchase VAT report only exports receipts with VAT
                if ($vat <= 0 || $isNoVatExplicit) {
                    continue;
                }

                $virtualItem = new ReceiptItem([
                    'receipt_id'       => $sr->id,
                    'item_description' => $sr->description ?: 'Purchased Goods',
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
                $virtualItem->id = $sr->id;
                $virtualItem->setRelation('receipt', $sr);
                $ocrItems->push($virtualItem);
            }
        }

        // 3. Include any CreditStorePayments in this period with receipt_path
        if (Schema::hasTable('credit_store_payments')) {
            $paymentSlips = CreditStorePayment::with(['ledger', 'ledger.project'])
                ->whereNotNull('receipt_path')
                ->where('receipt_path', '!=', '')
                ->where(function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('payment_date', [$startDate, $endDate])
                      ->orWhere(function ($sub) use ($startDate, $endDate) {
                          $sub->whereNull('payment_date')
                              ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
                      });
                })
                ->get();

            $existingFiles = $ocrItems->map(fn($it) => $it->receipt?->file_path)->filter()->all();

            foreach ($paymentSlips as $p) {
                if (in_array($p->receipt_path, $existingFiles)) {
                    continue;
                }

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

                if ($vatAmount <= 0) {
                    continue;
                }

                $vReceipt = new Receipt([
                    'vendor_name'    => $ledger?->supplier_name ?: 'Credit Vendor',
                    'vendor_tin'     => '',
                    'buyer_tin'      => '0038480010',
                    'receipt_number' => $p->reference_no ?: ('REC-PAY-' . $p->id),
                    'fs_no'          => $p->reference_no ?: '',
                    'receipt_date'   => $p->payment_date ?: $p->created_at?->toDateString(),
                    'subtotal'       => $receiptSubtotal,
                    'vat_amount'     => $vatAmount,
                    'total_amount'   => $receiptTotal,
                    'file_path'      => $p->receipt_path,
                    'file_type'      => 'image',
                    'description'    => "Credit Payment #{$p->id} - " . ($ledger?->item_description ?: ($ledger?->notes ?: 'Materials')),
                ]);
                $vReceipt->id = 'P' . $p->id;
                if ($ledger?->project) {
                    $vReceipt->setRelation('project', $ledger->project);
                }

                $vItem = new ReceiptItem([
                    'receipt_id'       => $vReceipt->id,
                    'item_description' => $ledger?->item_description ?: ($ledger?->notes ?: 'Credit settlement'),
                    'vat_category'     => 'G',
                    'calendar_type'    => 'G',
                    'purchase_type'    => 3,
                    'uom'              => '9',
                    'qty'              => 1.0,
                    'unit_price'       => $receiptSubtotal,
                    'total_value'      => $receiptSubtotal,
                    'vat_amount'       => $vatAmount,
                    'value_after_vat'  => $receiptTotal,
                ]);
                $vItem->id = 'P' . $p->id;
                $vItem->setRelation('receipt', $vReceipt);

                $ocrItems->push($vItem);
                $existingFiles[] = $p->receipt_path;
            }
        }

        // Strictly filter to only VAT receipts for Purchase VAT Excel report
        $ocrItems = $ocrItems->filter(function ($item) {
            $vatVal = (float)$item->vat_amount;
            $r = $item->receipt;
            $taxType = strtolower((string)($r?->tax_type ?? ''));
            $hasNoVatReason = !empty($r?->no_vat_reason_code);
            $isExempt = ($item->vat_category === 'E') || ((int)$item->purchase_type === 6 && $vatVal <= 0);
            $isFuelNoVat = ($r?->category === 'fuel' && $vatVal <= 0);

            return $vatVal > 0 && $taxType !== 'no_vat' && !$hasNoVatReason && !$isExempt && !$isFuelNoVat;
        });

        // ── Deduplicate OCR Receipts (Immediate Block Rule & Excel Protection) ────
        // Double receipts with identical FS No. & Seller TIN:
        // Keep the first original receipt, immediately block subsequent double receipts,
        // and strictly exclude blocked double receipts from being added to Excel!
        $normalizeFs = function ($rawFs) {
            $raw = trim((string)$rawFs);
            $stripped = preg_replace('/^FS[:\s_#-]*\s*/i', '', $raw);
            $alphanumeric = strtoupper(preg_replace('/[^0-9A-Z]/i', '', $stripped));
            $normalized = ltrim($alphanumeric, '0');
            return ($normalized === '' && $alphanumeric !== '') ? '0' : $normalized;
        };

        $normalizeTin = function ($rawTin) {
            return strtoupper(preg_replace('/[^0-9A-Z]/i', '', trim((string)$rawTin)));
        };

        $groupedByMatch = [];
        $uniqueCleanedItems = [];

        foreach ($ocrItems as $item) {
            $r = $item->receipt;
            $rawFs = $r ? ($r->fs_no ?: ($r->fs_no_raw ?: ($r->receipt_number ?: ''))) : '';
            $rawTin = $r ? ($r->vendor_tin ?: '') : '';

            $normFs = $normalizeFs($rawFs);
            $normTin = $normalizeTin($rawTin);

            $fsMissing = ($normFs === '' || $normFs === '0' || in_array(strtoupper(trim((string)$rawFs)), ['', '-', '--', 'N/A', 'NA', 'NULL', 'NONE', 'UNKNOWN', '?', '0']));
            $tinMissing = ($normTin === '' || strlen($normTin) < 5 || in_array(strtoupper(trim((string)$rawTin)), ['', '-', '--', 'N/A', 'NA', 'NULL', 'NONE', 'UNKNOWN', '?']));

            if ($fsMissing || $tinMissing) {
                // Flag, don't guess — keep unreadable receipts
                $uniqueCleanedItems[] = $item;
            } else {
                $matchKey = $normTin . '___' . $normFs;
                $groupedByMatch[$matchKey][] = $item;
            }
        }

        foreach ($groupedByMatch as $matchKey => $group) {
            // Group by distinct receipt ID
            $receiptsInGroup = [];
            foreach ($group as $item) {
                $rId = (string)($item->receipt_id ?: ($item->receipt?->id ?: $item->id));
                $receiptsInGroup[$rId][] = $item;
            }

            if (count($receiptsInGroup) === 1) {
                foreach ($group as $it) {
                    $uniqueCleanedItems[] = $it;
                }
                continue;
            }

            // Duplicate receipts found: Keep the FIRST original receipt, block double receipts!
            $receiptList = array_values($receiptsInGroup);
            usort($receiptList, function ($itemsA, $itemsB) {
                $itemA = $itemsA[0];
                $itemB = $itemsB[0];
                $rA = $itemA->receipt;
                $rB = $itemB->receipt;

                $parsedA = is_string($rA?->parsed_data ?? null) ? (json_decode($rA->parsed_data, true) ?: []) : (array)($rA?->parsed_data ?? []);
                $parsedB = is_string($rB?->parsed_data ?? null) ? (json_decode($rB->parsed_data, true) ?: []) : (array)($rB?->parsed_data ?? []);
                $editA = (int)($parsedA['manually_edited_at'] ?? ($parsedA['last_updated_at'] ?? 0));
                $editB = (int)($parsedB['manually_edited_at'] ?? ($parsedB['last_updated_at'] ?? 0));
                if ($editA !== $editB) {
                    return $editB <=> $editA;
                }

                $uploadA = $itemA->created_at ? $itemA->created_at->timestamp : ($rA?->created_at ? $rA->created_at->timestamp : 0);
                $uploadB = $itemB->created_at ? $itemB->created_at->timestamp : ($rB?->created_at ? $rB->created_at->timestamp : 0);
                if ($uploadA !== $uploadB && $uploadA > 0 && $uploadB > 0) {
                    return $uploadA <=> $uploadB;
                }

                $dateA = $rA?->receipt_date ? Carbon::parse($rA->receipt_date)->timestamp : 0;
                $dateB = $rB?->receipt_date ? Carbon::parse($rB->receipt_date)->timestamp : 0;
                if ($dateA !== $dateB && $dateA > 0 && $dateB > 0) {
                    return $dateA <=> $dateB;
                }

                $idA = (int)($rA?->id ?: $itemA->id);
                $idB = (int)($rB?->id ?: $itemB->id);
                if ($idA !== $idB && $idA > 0 && $idB > 0) {
                    return $idA <=> $idB;
                }
                return strcmp((string)$itemA->id, (string)$itemB->id);
            });

            // Keep only the first original receipt; immediately block double receipts (DO NOT ADD TO EXCEL)
            foreach ($receiptList[0] as $keptItem) {
                $uniqueCleanedItems[] = $keptItem;
            }
        }

        $ocrItems = collect($uniqueCleanedItems);

        // Sort latest information first (receipt date descending, then id descending)
        $ocrItems = $ocrItems->sortByDesc(function ($item) {
            $r = $item->receipt;
            $d = ($r && $r->receipt_date) ? $r->receipt_date : ($item->created_at ?: ($r?->created_at ?: null));
            $ts = $d ? Carbon::parse($d)->timestamp : 0;
            $rId = (int)($r?->id ?: $item->id);
            return sprintf('%012d_%010d', $ts, $rId);
        })->values();

        // Apply filters if requested
        if ($request->filled('search')) {
            $s = strtolower(trim((string)$request->search));
            $ocrItems = $ocrItems->filter(function ($item) use ($s) {
                $r = $item->receipt;
                return str_contains(strtolower((string)$item->item_description), $s)
                    || str_contains(strtolower((string)($r?->fs_no ?? '')), $s)
                    || str_contains(strtolower((string)($r?->vendor_name ?? '')), $s)
                    || str_contains(strtolower((string)($r?->vendor_tin ?? '')), $s)
                    || str_contains(strtolower((string)($r?->mrc_no ?? '')), $s);
            });
        }

        if ($request->filled('category')) {
            $catFilter = strtolower(trim((string)$request->category));
            $ocrItems = $ocrItems->filter(function ($item) use ($catFilter) {
                $r = $item->receipt;
                return strtolower((string)($r?->category ?? '')) === $catFilter
                    || strtolower((string)($item->vat_category ?? '')) === $catFilter;
            });
        }

        if ($request->boolean('valid_only')) {
            $ocrItems = $ocrItems->filter(function ($item) {
                $r = $item->receipt;
                $tinDigits = preg_replace('/[^0-9]/', '', (string)($r?->vendor_tin ?? ''));
                $fsDigits = preg_replace('/[^0-9]/', '', (string)($r?->fs_no ?? ''));
                return strlen($tinDigits) === 10 && strlen($fsDigits) === 8;
            });
        }

        $rows = [];
        $totalQty = 0.0;
        $totalBase = 0.0;
        $totalVat = 0.0;
        $totalAfterVat = 0.0;

        foreach ($ocrItems as $item) {
            $r = $item->receipt;

            // 1. VAT CATEGORY: G or S
            $cat = strtoupper(trim((string)($item->vat_category ?: ($r?->vat_category ?: 'G'))));
            $vatCategory = ($cat === 'S' || str_starts_with($cat, 'SERV')) ? 'S' : 'G';

            // 2. CALENDAR TYPE: G (default) or E
            if ($userCalendar !== null) {
                $calendarType = $userCalendar;
            } else {
                $calType = strtoupper(trim((string)($item->calendar_type ?: ($request->input('calendar_type') ?: 'G'))));
                $calendarType = ($calType === 'E') ? 'E' : 'G';
            }

            // 6. Date of purchase (dd/mm/yyyy format matching calendar type)
            $rawDate = $r?->receipt_date ?: $item->created_at;
            $rawEcDate = $item->receipt_date_ec ?? ($r?->receipt_date_ec ?? null);
            $dateFormatted = $this->formatExportDate($rawDate, $rawEcDate, $calendarType, '/');

            // 4. TIN: clean digits only or empty
            $rawTin = $r ? (string)$r->vendor_tin : '';
            $tinDigits = preg_replace('/[^0-9]/', '', $rawTin);
            $tinDigits = preg_replace('/^0+/', '', $tinDigits);
            $tinVal = ($tinDigits !== '') ? (int)$tinDigits : '';

            // 5. Seller name
            $seller = $r ? trim((string)$r->vendor_name) : '';

            // 7. MRC Number: clean string or empty
            $rawMrc = $r ? trim((string)$r->mrc_no) : '';
            $mrcVal = ($rawMrc !== '' && $rawMrc !== '-' && strtoupper($rawMrc) !== 'N/A') ? $rawMrc : '';

            // 8. VAT receipt number / Customs Declaration Number
            $rawFs = $r ? trim((string)($r->fs_no ?: ($r->fs_no_raw ?: ($r->receipt_number ?: '')))) : '';
            if ($rawFs === '' || $rawFs === '-') {
                $rawFs = $r ? trim((string)$r->receipt_number) : 'FS00000000';
            }

            // 9. Description
            $desc = trim((string)($item->item_description ?: ($r?->description ?: 'Purchased Goods')));

            // 10. Unit of Measure (ID 2-10: 2 KG 3 ML 4 GM 5 LIT 6 MT 7 PCS 8 CT 9 OTHER 10 PC)
            $uomRaw = strtoupper(trim((string)$item->uom));
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
                    default => 9, // 9 = OTHER
                };
            }

            // Amounts calculation
            $baseVal  = (float)$item->total_value;
            $vatVal   = (float)$item->vat_amount;
            $afterVal = (float)$item->value_after_vat;
            $unitVal  = (float)$item->unit_price;
            $qty      = (float)$item->qty > 0 ? (float)$item->qty : 1.0;

            if ($baseVal > 0 && $vatVal > 0) {
                $calcBase = round($baseVal / 1.15, 2);
                if (abs(($baseVal - $vatVal) - $calcBase) <= 0.05 && abs(round($calcBase * 0.15, 2) - $vatVal) <= 0.05) {
                    $afterVal = $baseVal;
                    $baseVal  = $calcBase;
                    $unitVal  = round($calcBase / $qty, 2);
                }
            }

            if ($afterVal <= 0 && $baseVal > 0) {
                if ($vatVal <= 0) {
                    $vatVal = round($baseVal * 0.15, 2);
                }
                $afterVal = round($baseVal + $vatVal, 2);
            } elseif ($baseVal <= 0 && $afterVal > 0) {
                if ($vatVal > 0) {
                    $baseVal = round($afterVal - $vatVal, 2);
                } else {
                    $baseVal = round($afterVal / 1.15, 2);
                    $vatVal  = round($afterVal - $baseVal, 2);
                }
            }

            if ($unitVal <= 0 && $baseVal > 0) {
                $unitVal = round($baseVal / $qty, 2);
            }

            if (abs(($baseVal + $vatVal) - $afterVal) > 0.05) {
                $afterVal = round($baseVal + $vatVal, 2);
            }

            // 3. Purchase Type (1-6)
            $pType = (int)($item->purchase_type ?: 3);
            if ($pType < 1 || $pType > 6) {
                $pType = 3;
            }
            if ($vatVal <= 0 && $pType === 3) {
                $pType = 6;
            }

            $qtyVal   = (floor($qty) == $qty) ? (int)$qty : round($qty, 4);
            $unitVal  = round($unitVal, 2);
            $baseVal  = round($baseVal, 2);
            $vatVal   = round($vatVal, 2);
            $afterVal = round($afterVal, 2);

            $totalQty += $qtyVal;
            $totalBase += $baseVal;
            $totalVat += $vatVal;
            $totalAfterVat += $afterVal;

            $rows[] = [
                $vatCategory,
                $calendarType,
                $pType,
                $tinVal,
                $seller,
                $dateFormatted,
                $mrcVal,
                $rawFs,
                $desc,
                $uomId,
                $qtyVal,
                $unitVal,
                $baseVal,
                $vatVal,
                $afterVal,
            ];
        }

        // Fallback: if no OCR items found, query paid Expense Requests with VAT
        if (empty($rows) && Schema::hasTable('expense_requests')) {
            $sDay = Carbon::parse($startDate)->startOfDay();
            $eDay = Carbon::parse($endDate)->endOfDay();
            $expenses = ExpenseRequest::with(['user', 'bankAccount', 'chartOfAccount', 'paidBy'])
                ->where(function ($q) {
                    $q->whereIn('status', [ExpenseRequest::STATUS_PAID, 'paid', 'PAID'])
                      ->orWhereNotNull('paid_at');
                })
                ->whereNotIn('status', [ExpenseRequest::STATUS_REJECTED, 'rejected', 'Rejected'])
                ->where(function ($q) use ($sDay, $eDay) {
                    $q->whereBetween('paid_at', [$sDay, $eDay])
                      ->orWhere(function ($sub) use ($sDay, $eDay) {
                          $sub->whereNull('paid_at')->whereBetween('created_at', [$sDay, $eDay]);
                      })
                      ->orWhereBetween('created_at', [$sDay, $eDay]);
                })
                ->where(function ($q) {
                    $q->whereNull('vat_type')->orWhere('vat_type', '!=', 'vat_b');
                })
                ->where('vat_amount', '>', 0)
                ->orderByRaw('COALESCE(paid_at, created_at) desc')
                ->orderBy('id', 'desc')
                ->get();

            foreach ($expenses as $exp) {
                $gross = (float)($exp->gross_amount > 0 ? $exp->gross_amount : $exp->amount);
                $vatAmount = (float)$exp->vat_amount;
                $base = round($gross - $vatAmount, 2);
                $date = $exp->paid_at ?: $exp->created_at;
                $dateFormatted = $date ? Carbon::parse($date)->format('d/m/Y') : now()->format('d/m/Y');

                $totalQty += 1;
                $totalBase += $base;
                $totalVat += $vatAmount;
                $totalAfterVat += $gross;

                $rows[] = [
                    'G',
                    'G',
                    3,
                    '',
                    $exp->account_name ?: ($exp->user?->name ?: 'Vendor'),
                    $dateFormatted,
                    '',
                    (string)($exp->payment_reference ?: $exp->request_number),
                    (string)($exp->description ?: 'Purchased Goods'),
                    9,
                    1,
                    $base,
                    $base,
                    $vatAmount,
                    $gross,
                ];
            }
        }

        // Add TOTAL summary row at the bottom of the Excel sheet
        if (!empty($rows)) {
            $rows[] = [
                'TOTAL',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                round($totalQty, 4),
                '',
                round($totalBase, 2),
                round($totalVat, 2),
                round($totalAfterVat, 2),
            ];
        }

        return $this->writeExcelFile($headers, $rows, $tempPath, $filename);
    }

    /**
     * Resolve requested calendar format ('E' for Ethiopian, 'G' for Gregorian, or null if unspecified).
     */
    protected function resolveCalendarOption(Request $request): ?string
    {
        $raw = strtolower(trim((string)($request->input('calendar') ?: ($request->input('calendar_type') ?: ''))));
        if (in_array($raw, ['e', 'ec', 'ethiopian', 'ethiopia'])) {
            return 'E';
        }
        if (in_array($raw, ['g', 'gc', 'gregorian'])) {
            return 'G';
        }
        return null;
    }

    /**
     * Format date according to target calendar ('E' or 'G') with desired separator ('/' or '-').
     */
    protected function formatExportDate($rawDate, ?string $rawEcDate, string $targetCalendar, string $separator = '/'): string
    {
        if ($targetCalendar === 'G') {
            // Gregorian Calendar requested
            // 1. If we have a valid Gregorian date string/Carbon
            if (!empty($rawDate) && $rawDate !== '-') {
                try {
                    return Carbon::parse($rawDate)->format('d' . $separator . 'm' . $separator . 'Y');
                } catch (\Throwable $e) {
                    // continue
                }
            }
            // 2. If we only have Ethiopian date string like DD-MM-YYYY or DD/MM/YYYY
            if (!empty($rawEcDate) && $rawEcDate !== '-') {
                $clean = trim(preg_replace('/\s*EC$/i', '', (string)$rawEcDate));
                if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $clean, $m)) {
                    $d = (int)$m[1];
                    $mo = (int)$m[2];
                    $y = (int)$m[3];
                    if ($y >= 1900 && $y <= 2100 && $mo >= 1 && $mo <= 13 && $d >= 1 && $d <= 30) {
                        try {
                            $gcYmd = EthiopianDate::toGregorian($y, $mo, $d);
                            return Carbon::parse($gcYmd)->format('d' . $separator . 'm' . $separator . 'Y');
                        } catch (\Throwable $e) {
                            // continue
                        }
                    }
                }
            }
            return now()->format('d' . $separator . 'm' . $separator . 'Y');
        }

        // Ethiopian Calendar requested ('E')
        // 1. If we already have a valid Ethiopian date string
        if (!empty($rawEcDate) && $rawEcDate !== '-') {
            $clean = trim(preg_replace('/\s*EC$/i', '', (string)$rawEcDate));
            if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $clean, $m)) {
                $d = str_pad($m[1], 2, '0', STR_PAD_LEFT);
                $mo = str_pad($m[2], 2, '0', STR_PAD_LEFT);
                $y = $m[3];
                return "{$d}{$separator}{$mo}{$separator}{$y}";
            }
        }
        // 2. Format from Gregorian date
        if (!empty($rawDate) && $rawDate !== '-') {
            try {
                return EthiopianDate::formatDateEc($rawDate, 'd' . $separator . 'm' . $separator . 'Y');
            } catch (\Throwable $e) {
                // continue
            }
        }
        return EthiopianDate::formatDateEc(now(), 'd' . $separator . 'm' . $separator . 'Y');
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

            $totalGross = 0.0;
            $totalVat = 0.0;
            $totalWht = 0.0;
            $totalNet = 0.0;

            foreach ($records as $item) {
                $gross = (float)($item->gross_amount > 0 ? $item->gross_amount : $item->amount);
                $vatAmt = (float)($item->vat_amount ?? 0);
                $wht = (float)$item->calculated_withholding_amount;
                $net = (float)$item->effective_payable_amount;

                $totalGross += $gross;
                $totalVat += $vatAmt;
                $totalWht += $wht;
                $totalNet += $net;

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
                    number_format($vatAmt, 2, '.', ''),
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

            if ($records->isNotEmpty()) {
                fputcsv($handle, [
                    'TOTAL',
                    '',
                    '',
                    '',
                    '',
                    number_format($totalGross, 2, '.', ''),
                    '',
                    '',
                    number_format($totalVat, 2, '.', ''),
                    '',
                    number_format($totalWht, 2, '.', ''),
                    '',
                    number_format($totalNet, 2, '.', ''),
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
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
            'no_vat_reason_code' => 'nullable|string|max:50',
            'no_vat_reason_note' => 'nullable|string|max:500',
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
            $taxType     = $validated['tax_type'] ?? 'vat';
            $category    = $validated['category'] ?? 'material';

            // Handle Tax Treatment & Exemption (ERCA Line 6 for Fuel / Non-VAT)
            if ($taxType === 'no_vat' || $category === 'fuel') {
                $taxType           = 'no_vat';
                $vatAmount         = 0.00;
                $subtotal          = $totalAmount;
                $hasWithholding    = false;
                $withholdingAmount = 0.00;
                $purcTypeCode      = 6; // ERCA Line 6 Tax Exempted
                $vatCategory       = 'E';
                $noVatReasonCode   = ($validated['no_vat_reason_code'] ?? null) ?: 'exempt_item';
                $noVatReasonNote   = ($validated['no_vat_reason_note'] ?? null) ?: ($category === 'fuel' ? 'Retail pump fuel purchase (legally VAT-exempt under Ethiopian Proclamation)' : 'Legally VAT-Exempt goods/services');
            } elseif ($taxType === 'withholding') {
                $vatAmount         = 0.00;
                $subtotal          = QcService::parseMoney($validated['subtotal'] ?? $totalAmount);
                $hasWithholding    = true;
                $withholdingAmount = QcService::parseMoney($validated['withholding_amount'] ?? round($subtotal * 0.03, 2));
                $purcNorm          = QcService::normalizePurchaseType($validated['purchase_type'] ?? null, ($validated['description'] ?? '') . ' ' . ($validated['vendor_name'] ?? ''));
                $purcTypeCode      = $purcNorm['code'];
                $vatCategory       = $purcNorm['vat_category'];
                $noVatReasonCode   = null;
                $noVatReasonNote   = null;
            } else {
                $vatAmount = QcService::parseMoney($validated['vat_amount'] ?? 0);
                if ($vatAmount <= 0) {
                    $subtotal  = round($totalAmount / 1.15, 2);
                    $vatAmount = round($totalAmount - $subtotal, 2);
                } else {
                    $subtotal  = QcService::parseMoney($validated['subtotal'] ?? max(0, $totalAmount - $vatAmount));
                }
                $hasWithholding    = (bool)($validated['has_withholding'] ?? ($taxType === 'both'));
                $withholdingAmount = QcService::parseMoney($validated['withholding_amount'] ?? ($hasWithholding ? round($subtotal * 0.03, 2) : 0));
                $purcNorm          = QcService::normalizePurchaseType($validated['purchase_type'] ?? null, ($validated['description'] ?? '') . ' ' . ($validated['vendor_name'] ?? ''));
                $purcTypeCode      = $purcNorm['code'];
                $vatCategory       = $purcNorm['vat_category'];
                $noVatReasonCode   = null;
                $noVatReasonNote   = null;
            }

            $ext = $filePath ? strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) : 'jpg';
            $isPdf = $ext === 'pdf';

            $receiptNumber = 'RCP-' . $targetDate->format('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

            $vendorName = ($validated['vendor_name'] ?? '') ?: ($category === 'fuel' ? 'Fuel Station' : 'General Merchant');
            $itemDesc   = ($validated['description'] ?? '') ?: ($category === 'fuel' ? 'Vehicle Fuel Receipt' : 'Purchased Materials / VAT Invoice');

            // 1. Create Receipt (for ERCA Line 100 / Line 6 VAT declaration)
            $receipt = new Receipt([
                'receipt_number'   => $receiptNumber,
                'uploaded_by'      => Auth::id() ?: 1,
                'project_id'       => $validated['project_id'] ?? null,
                'vendor_name'      => $vendorName,
                'vendor_tin'       => $validated['vendor_tin'] ?? null,
                'tin_valid'        => true,
                'buyer_tin'        => ($validated['buyer_tin'] ?? '') ?: '38480010',
                'fs_no'            => $fsNo,
                'fs_no_raw'        => $fsNoRaw,
                'fs_no_valid'      => true,
                'mrc_no'           => $validated['mrc_no'] ?? null,
                'receipt_date'     => $targetDate->toDateString(),
                'subtotal'         => $subtotal,
                'vat_amount'       => $vatAmount,
                'total_amount'     => $totalAmount,
                'currency'         => 'ETB',
                'category'         => $category,
                'description'      => $itemDesc,
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
                'item_description' => $itemDesc,
                'vat_category'     => $vatCategory,
                'calendar_type'    => 'E',
                'purchase_type'    => $purcTypeCode,
                'uom'              => $category === 'fuel' ? 'LTR' : '9',
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
            $taxExp->category          = ucfirst($category);
            $taxExp->description       = $itemDesc . ' (FS: ' . $fsNo . ')';
            $taxExp->amount            = $totalAmount;
            $taxExp->gross_amount      = $totalAmount;
            $taxExp->vat_type          = $vatAmount > 0 ? 'exclusive' : 'none';
            $taxExp->vat_rate          = $vatAmount > 0 ? 15.00 : 0.00;
            $taxExp->vat_amount        = $vatAmount;
            $taxExp->has_withholding   = $hasWithholding;
            $taxExp->withholding_rate  = $hasWithholding ? 3.00 : 0.00;
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

            // Link Receipt to ExpenseRequest
            $receipt->purchasable_type = ExpenseRequest::class;
            $receipt->purchasable_id   = $taxExp->id;
            $receipt->save();

            // 4. Create / Update ExpenseHubVatDecision with Reason for No VAT
            if (Schema::hasTable('expense_hub_vat_decisions')) {
                ExpenseHubVatDecision::updateOrCreate(
                    [
                        'source_type' => 'expense_request',
                        'source_id'   => $taxExp->id,
                    ],
                    [
                        'vat_status'              => $vatAmount > 0 ? ExpenseHubVatDecision::STATUS_VAT_APPLIES : ExpenseHubVatDecision::STATUS_NO_VAT,
                        'vat_rate'                => $vatAmount > 0 ? 15.00 : 0.00,
                        'is_inclusive'            => true,
                        'has_withholding'         => $hasWithholding,
                        'withholding_rate'        => $hasWithholding ? 3.00 : 0.00,
                        'withholding_amount'      => $withholdingAmount,
                        'base_amount'             => $subtotal,
                        'vat_amount'              => $vatAmount,
                        'total_amount'            => $totalAmount,
                        'no_vat_reason_code'      => $vatAmount <= 0 ? ($noVatReasonCode ?: 'exempt_item') : null,
                        'no_vat_reason_note'      => $vatAmount <= 0 ? ($noVatReasonNote ?: 'Legally VAT-Exempt goods/services (Proclamation)') : null,
                        'purchase_type'           => $purcTypeCode,
                        'vat_category'            => $vatCategory,
                        'calendar_type'           => 'E',
                        'custom_seller_tin'       => $validated['vendor_tin'] ?? null,
                        'custom_fs_no'            => $fsNo,
                        'custom_mrc_no'           => $validated['mrc_no'] ?? null,
                        'custom_item_description' => $itemDesc,
                        'quantity'                => 1.0,
                        'unit_price'              => $subtotal,
                        'uom'                     => $category === 'fuel' ? 'LTR' : '9',
                        'decided_by'              => Auth::id() ?: 1,
                        'decided_at'              => now(),
                    ]
                );
            }
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

    /**
     * AJAX endpoint: Update an existing OCR Receipt Item and its parent Receipt record directly from the tax audit modal.
     */
    public function updateOcrItem(Request $request)
    {
        $this->ensureSchema();

        $request->validate([
            'id'                         => 'nullable',
            'receipt_id'                 => 'nullable',
            'expense_request_id'         => 'nullable',
            'request_number'             => 'nullable|string|max:100',
            'receipt_file'               => 'nullable|file|mimes:jpeg,jpg,png,webp,pdf|max:25600',
            'receipt_path'               => 'nullable|string|max:500',
            'seller_name'                => 'nullable|string|max:255',
            'supplier_tin'               => 'nullable|string|max:50',
            'receipt_date'               => 'nullable|string',
            'receipt_date_ec'            => 'nullable|string|max:100',
            'mrc_no'                     => 'nullable|string|max:100',
            'fs_no'                      => 'nullable|string|max:100',
            'item_description'           => 'nullable|string|max:500',
            'vat_category'               => 'nullable|string',
            'calendar_type'              => 'nullable|string|max:10',
            'purchase_type'              => 'nullable',
            'project_id'                 => 'nullable',
            'category'                   => 'nullable|string|max:100',
            'uom'                        => 'nullable|string|max:20',
            'qty'                        => 'nullable|numeric|min:0',
            'unit_price'                 => 'nullable|numeric|min:0',
            'total_value'                => 'nullable|numeric|min:0',
            'vat_amount'                 => 'nullable|numeric|min:0',
            'value_after_vat'            => 'nullable|numeric|min:0',
            'is_verified_without_receipt'=> 'nullable',
            'has_withholding'            => 'nullable',
            'withholding_rate'           => 'nullable|numeric|min:0|max:100',
            'withholding_amount'         => 'nullable|numeric|min:0',
            'withholding_receipt_number' => 'nullable|string|max:100',
            'withholding_file'           => 'nullable|file|mimes:jpeg,jpg,png,webp,pdf|max:25600',
            'withholding_receipt_path'   => 'nullable|string|max:500',
        ]);

        try {
            $uploadedPath = null;
            if ($request->hasFile('receipt_file')) {
                try {
                    $uploadedPath = \App\Services\FileUploadService::upload($request->file('receipt_file'), 'receipts');
                } catch (\Throwable $e) {
                    Log::warning('updateOcrItem file upload error: ' . $e->getMessage());
                }
            } elseif ($request->filled('receipt_path')) {
                $uploadedPath = trim((string)$request->input('receipt_path'));
            }

            $uploadedWhtPath = null;
            if ($request->hasFile('withholding_file')) {
                try {
                    $uploadedWhtPath = \App\Services\FileUploadService::upload($request->file('withholding_file'), 'withholding');
                } catch (\Throwable $e) {
                    Log::warning('updateOcrItem withholding file upload error: ' . $e->getMessage());
                }
            } elseif ($request->filled('withholding_receipt_path')) {
                $uploadedWhtPath = trim((string)$request->input('withholding_receipt_path'));
            }

            $hasWhtInput = $request->input('has_withholding');
            $hasWht = ($hasWhtInput === '1' || $hasWhtInput === 1 || $hasWhtInput === true || $hasWhtInput === 'true' || $hasWhtInput === 'on');
            $whtRate = $request->filled('withholding_rate') ? (float)$request->input('withholding_rate') : null;
            $whtAmount = $request->filled('withholding_amount') ? (float)$request->input('withholding_amount') : null;
            $whtReceiptNo = $request->filled('withholding_receipt_number') ? trim((string)$request->input('withholding_receipt_number')) : null;

            // Auto-enable withholding if positive withholding amount is submitted
            if ($whtAmount !== null && $whtAmount > 0) {
                $hasWht = true;
            }

            $rawId            = $request->input('id');
            $rawReceiptId     = $request->input('receipt_id');
            $rawExpenseReqId  = $request->input('expense_request_id');
            $rawRequestNumber = $request->input('request_number');
            $rawFsNo          = $request->input('fs_no');

            $receipt     = null;
            $item        = null;
            $expReq      = null;
            $procPayment = null;
            $csPayment   = null;
            $pcPurchase  = null;

            $isExplicitExpReq = !empty($rawExpenseReqId) 
                || (is_string($rawReceiptId) && str_starts_with(strtoupper($rawReceiptId), 'EXP-')) 
                || (is_string($rawId) && str_starts_with(strtoupper($rawId), 'EXP-'));
            $isExplicitProc = (is_string($rawReceiptId) && str_starts_with(strtoupper($rawReceiptId), 'PROC-')) 
                || (is_string($rawId) && str_starts_with(strtoupper($rawId), 'PROC-'));
            $isExplicitCs = (is_string($rawReceiptId) && (str_starts_with(strtoupper($rawReceiptId), 'CS-') || preg_match('/^P\d+$/i', $rawReceiptId))) 
                || (is_string($rawId) && str_starts_with(strtoupper($rawId), 'CS-'));
            $isExplicitPc = (is_string($rawReceiptId) && str_starts_with(strtoupper($rawReceiptId), 'PC-')) 
                || (is_string($rawId) && str_starts_with(strtoupper($rawId), 'PC-'));
            $isExplicitOcr = (is_string($rawId) && str_starts_with(strtoupper($rawId), 'OCR-')) 
                || (is_string($rawReceiptId) && str_starts_with(strtoupper($rawReceiptId), 'OCR-'));

            // If explicitly targeting an ExpenseRequest, resolve it FIRST before trying ReceiptItem
            if ($isExplicitExpReq && Schema::hasTable('expense_requests')) {
                $candExpIds = array_values(array_filter([$rawExpenseReqId, $rawReceiptId, $rawId, $rawRequestNumber]));
                foreach ($candExpIds as $ceid) {
                    if (preg_match('/^EXP-(\d+)$/i', (string)$ceid, $mExp)) {
                        $expReq = \App\Models\ExpenseRequest::find($mExp[1]);
                        if ($expReq) break;
                    } elseif (is_numeric($ceid)) {
                        $expReq = \App\Models\ExpenseRequest::find($ceid);
                        if ($expReq) break;
                    } elseif (str_starts_with(strtoupper((string)$ceid), 'EXP-')) {
                        $expReq = \App\Models\ExpenseRequest::where('request_number', (string)$ceid)->first()
                            ?: \App\Models\ExpenseRequest::where('request_number', 'like', '%' . $ceid . '%')->first();
                        if ($expReq) break;
                    }
                }
                if (!$expReq && !empty($rawRequestNumber)) {
                    $expReq = \App\Models\ExpenseRequest::where('request_number', (string)$rawRequestNumber)
                        ->orWhere('payment_reference', 'like', '%' . $rawRequestNumber . '%')
                        ->first();
                }

                if ($expReq) {
                    if (!empty($expReq->receipt_id)) {
                        $receipt = \App\Models\Receipt::find($expReq->receipt_id);
                    }
                    if (!$receipt) {
                        $receipt = \App\Models\Receipt::where(function ($q) use ($expReq) {
                            $q->where(function ($sub) use ($expReq) {
                                $sub->whereIn('purchasable_type', [\App\Models\ExpenseRequest::class, 'expense_request', 'ExpenseRequest'])
                                    ->where('purchasable_id', $expReq->id);
                            });
                            if (!empty($expReq->request_number)) {
                                $q->orWhere('receipt_number', $expReq->request_number);
                            }
                        })->latest('id')->first();
                    }
                    if ($receipt) {
                        $item = $receipt->items()->first();
                    }
                }
            }

            // 1. Resolve parent Receipt if receipt_id is provided
            if (!$receipt && !empty($rawReceiptId) && Schema::hasTable('receipts')) {
                $cleanRawRId = preg_replace('/^REC-/i', '', (string)$rawReceiptId);
                if (is_numeric($cleanRawRId)) {
                    $receipt = \App\Models\Receipt::with('items')->find($cleanRawRId);
                    if ($receipt) {
                        if (!empty($rawId)) {
                            $cleanRawId = preg_replace('/^OCR-/i', '', (string)$rawId);
                            if (is_numeric($cleanRawId) && Schema::hasTable('receipt_items')) {
                                $candItem = \App\Models\ReceiptItem::find($cleanRawId);
                                if ($candItem && $candItem->receipt_id == $receipt->id) {
                                    $item = $candItem;
                                }
                            }
                        }
                        if (!$item) {
                            $item = $receipt->items()->first();
                        }
                    }
                }
            }

            // 2. Resolve through ReceiptItem (only for OCR items or when not explicit non-OCR expense/payment)
            if (!$receipt && !$item && !$isExplicitExpReq && !$isExplicitProc && !$isExplicitCs && !$isExplicitPc) {
                $candItemIds = array_values(array_filter([$rawId, $rawReceiptId]));
                foreach ($candItemIds as $cid) {
                    $cleanCid = preg_replace('/^OCR-/i', '', (string)$cid);
                    if (is_numeric($cleanCid) && Schema::hasTable('receipt_items')) {
                        $item = \App\Models\ReceiptItem::with('receipt')->find($cleanCid);
                        if ($item) {
                            $receipt = $item->receipt ?: \App\Models\Receipt::find($item->receipt_id);
                            if ($receipt) break;
                        }
                    }
                }
            }

            // 3. Resolve parent Receipt (numeric, REC-xx, receipt_number, or fs_no)
            if (!$receipt && Schema::hasTable('receipts')) {
                $candReceiptIds = array_values(array_filter([$rawReceiptId, $rawId, $rawRequestNumber, $rawFsNo]));
                foreach ($candReceiptIds as $crid) {
                    $cleanRId = preg_replace('/^REC-/i', '', (string)$crid);
                    if (is_numeric($cleanRId)) {
                        $receipt = \App\Models\Receipt::find($cleanRId);
                        if ($receipt) break;
                    }
                    $receipt = \App\Models\Receipt::where('receipt_number', (string)$crid)->latest('id')->first();
                    if ($receipt) break;

                    // Match by fs_no or fs_no_raw (e.g. fs00016028 or 00016028)
                    $cleanFs = preg_replace('/\D/', '', (string)$crid);
                    if (!empty($cleanFs)) {
                        $receipt = \App\Models\Receipt::where('fs_no', $cleanFs)
                            ->orWhere('fs_no_raw', (string)$crid)
                            ->orWhere('fs_no', (string)$crid)
                            ->latest('id')
                            ->first();
                        if ($receipt) break;
                    }
                }
            }

            // 3. Resolve ExpenseRequest (EXP-xx, numeric id, or request_number)
            if (Schema::hasTable('expense_requests')) {
                $candExpIds = array_values(array_filter([$rawExpenseReqId, $rawReceiptId, $rawId, $rawRequestNumber]));
                foreach ($candExpIds as $ceid) {
                    if (preg_match('/^EXP-(\d+)$/i', (string)$ceid, $mExp)) {
                        $expReq = \App\Models\ExpenseRequest::find($mExp[1]);
                        if ($expReq) break;
                    } elseif (is_numeric($ceid)) {
                        $expReq = \App\Models\ExpenseRequest::find($ceid);
                        if ($expReq) break;
                    } elseif (str_starts_with(strtoupper((string)$ceid), 'EXP-')) {
                        $expReq = \App\Models\ExpenseRequest::where('request_number', (string)$ceid)->first()
                            ?: \App\Models\ExpenseRequest::where('request_number', 'like', '%' . $ceid . '%')->first();
                        if ($expReq) break;
                    }
                }
                if (!$expReq && !empty($rawRequestNumber)) {
                    $expReq = \App\Models\ExpenseRequest::where('request_number', (string)$rawRequestNumber)
                        ->orWhere('payment_reference', 'like', '%' . $rawRequestNumber . '%')
                        ->first();
                    if (!$expReq && preg_match('/(\d{4,10})/', (string)$rawRequestNumber, $mDigits)) {
                        $expReq = \App\Models\ExpenseRequest::where('payment_reference', 'like', '%' . $mDigits[1] . '%')->first();
                    }
                }

                if (!$receipt && $expReq) {
                    $receipt = \App\Models\Receipt::where('purchasable_type', \App\Models\ExpenseRequest::class)
                        ->where('purchasable_id', $expReq->id)
                        ->first()
                        ?: \App\Models\Receipt::where('purchasable_type', 'expense_request')
                            ->where('purchasable_id', $expReq->id)
                            ->first()
                        ?: \App\Models\Receipt::where('receipt_number', $expReq->request_number)->first();
                }
            }

            // 4. Resolve ProcurementPayment (PROC-xx, PP-xx, or PR-xx)
            if (Schema::hasTable('procurement_payments')) {
                $candProcIds = array_values(array_filter([$rawExpenseReqId, $rawReceiptId, $rawId, $rawRequestNumber]));
                foreach ($candProcIds as $cpid) {
                    if (preg_match('/^PROC-(\d+)$/i', (string)$cpid, $mProc)) {
                        $procPayment = \App\Models\ProcurementPayment::find($mProc[1]);
                        if ($procPayment) break;
                    }
                }
                if (!$procPayment && !empty($rawRequestNumber)) {
                    $procPayment = \App\Models\ProcurementPayment::where('notes', 'like', '%' . $rawRequestNumber . '%')
                        ->orWhereHas('purchaseRequest', function ($pq) use ($rawRequestNumber) {
                            if (Schema::hasColumn('purchase_requests', 'pr_no')) {
                                $pq->where('pr_no', $rawRequestNumber);
                            }
                        })
                        ->first();
                }
                if (!$receipt && $procPayment) {
                    $receipt = \App\Models\Receipt::where('purchasable_type', \App\Models\ProcurementPayment::class)
                        ->where('purchasable_id', $procPayment->id)
                        ->first()
                        ?: \App\Models\Receipt::where('purchasable_type', 'procurement_payment')
                            ->where('purchasable_id', $procPayment->id)
                            ->first();
                }
            }

            // 5. Resolve CreditStorePayment (CS-xx, Pxx, or reference_no)
            if (Schema::hasTable('credit_store_payments')) {
                $candCsIds = array_values(array_filter([$rawExpenseReqId, $rawReceiptId, $rawId, $rawRequestNumber]));
                foreach ($candCsIds as $csid) {
                    if (preg_match('/^CS-(\d+)$/i', (string)$csid, $mCs)) {
                        $csPayment = \App\Models\CreditStorePayment::find($mCs[1]);
                        if ($csPayment) break;
                    } elseif (preg_match('/^P(\d+)$/i', (string)$csid, $mP)) {
                        $csPayment = \App\Models\CreditStorePayment::find($mP[1]);
                        if ($csPayment) break;
                    }
                }
                if (!$csPayment && !empty($rawRequestNumber)) {
                    $csPayment = \App\Models\CreditStorePayment::where('reference_no', (string)$rawRequestNumber)->first();
                }
                if (!$receipt && $csPayment) {
                    $receipt = \App\Models\Receipt::where('purchasable_type', \App\Models\CreditStorePayment::class)
                        ->where('purchasable_id', $csPayment->id)
                        ->first()
                        ?: \App\Models\Receipt::where('purchasable_type', 'credit_store_payment')
                            ->where('purchasable_id', $csPayment->id)
                            ->first();
                }
            }

            // 6. Resolve PettyCashMaterialPurchase (PC-xx)
            if (Schema::hasTable('petty_cash_material_purchases')) {
                $candPcIds = array_values(array_filter([$rawExpenseReqId, $rawReceiptId, $rawId, $rawRequestNumber]));
                foreach ($candPcIds as $pcid) {
                    if (preg_match('/^PC-(\d+)$/i', (string)$pcid, $mPc)) {
                        $pcPurchase = \App\Models\PettyCashMaterialPurchase::find($mPc[1]);
                        if ($pcPurchase) break;
                    }
                }
            }

            // 7. Resolve via fs_no match in Receipts
            if (!$receipt && Schema::hasTable('receipts')) {
                $candFsList = array_values(array_filter([$rawFsNo, $rawRequestNumber, $rawReceiptId, $rawId]));
                foreach ($candFsList as $cFs) {
                    $cleanFs = preg_replace('/\D/', '', (string)$cFs);
                    if (!empty($cleanFs)) {
                        $receipt = \App\Models\Receipt::where('fs_no', $cleanFs)
                            ->orWhere('fs_no_raw', (string)$cFs)
                            ->orWhere('fs_no', (string)$cFs)
                            ->first();
                        if ($receipt) break;
                    }
                }
            }

            // 8. Dynamic creation fallback if receipt is not found yet - NEVER fail with record not found
            if (!$receipt && Schema::hasTable('receipts')) {
                $candidateReceiptNo = $rawRequestNumber ?: ($rawReceiptId ?: null);
                if (empty($candidateReceiptNo) || \App\Models\Receipt::where('receipt_number', $candidateReceiptNo)->exists()) {
                    $candidateReceiptNo = 'RCP-' . now()->format('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
                }

                $purchasableType = null;
                $purchasableId = null;
                $defaultCategory = 'materials';
                $defaultVendor = $request->seller_name ?: 'Vendor';

                if ($expReq) {
                    $purchasableType = \App\Models\ExpenseRequest::class;
                    $purchasableId   = $expReq->id;
                    $defaultCategory = $expReq->category ?: 'general';
                    $defaultVendor   = $expReq->payee ?: $defaultVendor;
                } elseif ($procPayment) {
                    $purchasableType = \App\Models\ProcurementPayment::class;
                    $purchasableId   = $procPayment->id;
                    $defaultCategory = 'procurement';
                } elseif ($csPayment) {
                    $purchasableType = \App\Models\CreditStorePayment::class;
                    $purchasableId   = $csPayment->id;
                    $defaultCategory = 'credit_settlement';
                }

                $receipt = new \App\Models\Receipt([
                    'purchasable_type' => $purchasableType,
                    'purchasable_id'   => $purchasableId,
                    'receipt_number'   => $candidateReceiptNo,
                    'vendor_name'      => $request->seller_name ?: $defaultVendor,
                    'vendor_tin'       => $request->supplier_tin ?: null,
                    'fs_no'            => $request->fs_no ?: null,
                    'fs_no_raw'        => $request->fs_no ?: null,
                    'mrc_no'           => $request->mrc_no ?: null,
                    'uploaded_by'      => Auth::id() ?: 1,
                    'project_id'       => $request->project_id ?: ($expReq?->project_id ?? null),
                    'file_path'        => $uploadedPath ?: ($expReq?->attachment ?? ''),
                    'file_type'        => 'image',
                    'category'         => $request->category ?: $defaultCategory,
                    'parsed_data'      => [
                        'tax_type'  => ((float)($request->vat_amount ?? 0) <= 0) ? 'no_vat' : 'vat_standard',
                        'purc_type' => (string)($request->purchase_type ?: 'PUR_GD')
                    ],
                    'subtotal'         => (float)($request->total_value ?? 0),
                    'vat_amount'       => (float)($request->vat_amount ?? 0),
                    'total_amount'     => (float)($request->value_after_vat ?? 0),
                ]);
                $receipt->save();
            }

            // 9. Ensure Receipt has an associated ReceiptItem
            if ($receipt) {
                if (!$item || $item->receipt_id !== $receipt->id) {
                    $item = $receipt->items()->first();
                }
                if (!$item && Schema::hasTable('receipt_items')) {
                    $rawVatCat = strtoupper(trim((string)($request->vat_category ?: '')));
                    if (!in_array($rawVatCat, ['G', 'S', 'E'])) {
                        $rawVatCat = ((float)($request->vat_amount ?? 0) > 0) ? 'G' : 'E';
                    }
                    $item = new \App\Models\ReceiptItem([
                        'receipt_id'       => $receipt->id,
                        'item_description' => $request->item_description ?: ($receipt->description ?: 'Purchased Goods/Services'),
                        'vat_category'     => $rawVatCat,
                        'calendar_type'    => 'G',
                        'purchase_type'    => is_numeric($request->purchase_type) ? (int)$request->purchase_type : 3,
                        'purc_type'        => (string)($request->purchase_type ?: 'PUR_GD'),
                        'uom'              => '9',
                        'qty'              => (float)($request->qty ?: 1),
                        'unit_price'       => (float)($request->unit_price ?: ($request->total_value ?: 0)),
                        'total_value'      => (float)($request->total_value ?: 0),
                        'vat_amount'       => (float)($request->vat_amount ?: 0),
                        'value_after_vat'  => (float)($request->value_after_vat ?: 0),
                    ]);
                    $item->save();
                }
                if ($item) {
                    $item->setRelation('receipt', $receipt);
                }
            }

            if (!$item || !$receipt) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice or receipt record not found for update.',
                ], 404);
            }

            $totalVal = round((float)($request->total_value ?? 0), 2);
            $vat      = round((float)($request->vat_amount ?? 0), 2);
            $valAfter = round((float)($request->value_after_vat ?? 0), 2);

            // Auto-reconcile totals if one was omitted or zero
            if ($valAfter <= 0 && $totalVal > 0) {
                $valAfter = round($totalVal + $vat, 2);
            } elseif ($totalVal <= 0 && $valAfter > 0) {
                $totalVal = max(0, round($valAfter - $vat, 2));
            }

            $qty       = round((float)($request->qty ?: 1), 2);
            $unitPrice = round((float)($request->unit_price ?: ($qty > 0 ? ($totalVal / $qty) : $totalVal)), 2);

            $purchaseTypeInt = is_numeric($request->purchase_type) ? (int)$request->purchase_type : 3;
            $rawVatCat = strtoupper(trim((string)($request->vat_category ?: '')));
            if (in_array($rawVatCat, ['G', 'S', 'E'])) {
                $vatCategory = $rawVatCat;
            } else {
                $vatCategory = $item->vat_category ?: ($vat > 0 ? 'G' : 'E');
            }

            $rawUom = $request->input('uom');
            if ($rawUom !== null && $rawUom !== '') {
                $item->uom = (string)$rawUom;
            }
            $rawCal = strtoupper(trim((string)$request->input('calendar_type', '')));
            if (in_array($rawCal, ['E', 'G'])) {
                $item->calendar_type = $rawCal;
            }

            $item->item_description = $request->item_description ?: ($item->item_description ?: 'Purchased Goods/Services');
            $item->vat_category     = $vatCategory;
            $item->purchase_type    = $purchaseTypeInt;
            if (Schema::hasColumn('receipt_items', 'purc_type')) {
                $item->purc_type    = (string)($request->purchase_type ?: ($item->purc_type ?: 'PUR_GD'));
            }
            $item->qty              = $qty;
            $item->unit_price       = $unitPrice;
            $item->total_value      = $totalVal;
            $item->vat_amount       = $vat;
            $item->value_after_vat  = $valAfter;
            $item->save();

            $oldFsNo = $receipt->fs_no;
            $receiptUpdates = [];
            if ($request->filled('seller_name')) {
                $receiptUpdates['vendor_name'] = trim((string)$request->seller_name);
            }
            if ($request->has('supplier_tin')) {
                $cleanTin = preg_replace('/\D/', '', (string)$request->supplier_tin);
                $receiptUpdates['vendor_tin'] = $cleanTin ?: trim((string)$request->supplier_tin);
                $receiptUpdates['tin_valid']  = (strlen($cleanTin) === 10);
            }
            if ($request->has('mrc_no')) {
                $receiptUpdates['mrc_no'] = trim((string)$request->mrc_no);
            }
            if ($request->has('fs_no')) {
                $cleanFs = preg_replace('/\D/', '', (string)$request->fs_no);
                $receiptUpdates['fs_no']       = $cleanFs ?: trim((string)$request->fs_no);
                $receiptUpdates['fs_no_raw']   = trim((string)$request->fs_no);
                $receiptUpdates['fs_no_valid'] = (strlen($cleanFs) === 8);
            }
            $rawEcDate  = trim((string)$request->input('receipt_date_ec'));
            $rawGcDate  = trim((string)$request->input('receipt_date'));
            $rawDate    = !empty($rawEcDate) ? $rawEcDate : $rawGcDate;
            $parsedDate = null;
            if (!empty($rawDate)) {
                // 1. Check if DD/MM/YYYY or DD-MM-YYYY (Ethiopian format like 11/02/2019)
                if (preg_match('/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4})/', $rawDate, $mDmy)) {
                    $parsedDate = EthiopianDate::parseEcToGregorian($rawDate);
                }
                // 2. Check if YYYY-MM-DD
                elseif (preg_match('/^(\d{4})[\/\-\.](\d{1,2})[\/\-\.](\d{1,2})/', $rawDate, $mYmd)) {
                    $yr = (int)$mYmd[1];
                    $mo = (int)$mYmd[2];
                    $da = (int)$mYmd[3];
                    $curEthYear = EthiopianDate::currentYear();
                    // If year is in Ethiopian era (e.g. 2010..2024, distinct from Gregorian 2025+)
                    if ($yr >= ($curEthYear - 8) && $yr <= ($curEthYear + 2) && $yr < 2025) {
                        $parsedDate = EthiopianDate::toGregorian($yr, $mo, $da);
                    } else {
                        try {
                            $parsedDate = Carbon::createFromDate($yr, $mo, $da)->format('Y-m-d');
                        } catch (\Throwable $e) {
                            $parsedDate = null;
                        }
                    }
                }

                if (!$parsedDate) {
                    $parsedDate = EthiopianDate::parseEcToGregorian($rawDate);
                }
                if (!$parsedDate) {
                    try {
                        $parsedDate = Carbon::parse($rawDate)->format('Y-m-d');
                    } catch (\Throwable $e) {
                        $parsedDate = null;
                    }
                }
                if ($parsedDate) {
                    $receiptUpdates['receipt_date'] = $parsedDate;
                }
            }
            if ($request->has('project_id')) {
                $receiptUpdates['project_id'] = $request->project_id ? (int)$request->project_id : null;
            }
            if ($request->filled('category')) {
                $receiptUpdates['category'] = $request->category;
            }
            if ($request->filled('item_description')) {
                $receiptUpdates['description'] = $request->item_description;
            }
            if (!empty($uploadedPath)) {
                $receiptUpdates['file_path'] = $uploadedPath;
            }

            // Merge with parsed_data JSON so that e-Tax exports and movements views immediately reflect the latest edits
            $parsed = is_string($receipt->parsed_data) ? (json_decode($receipt->parsed_data, true) ?: []) : (array)($receipt->parsed_data ?? []);
            $parsed['manually_edited'] = true;
            $parsed['manually_edited_at'] = (int)now()->timestamp;
            $parsed['last_updated_at'] = (int)now()->timestamp;

            if ($request->filled('seller_name')) {
                $parsed['seller_name'] = trim((string)$request->seller_name);
                $parsed['vendor_name'] = trim((string)$request->seller_name);
            }
            if ($request->has('supplier_tin')) {
                $cleanTin = preg_replace('/\D/', '', (string)$request->supplier_tin);
                $parsed['seller_tin'] = $cleanTin ?: trim((string)$request->supplier_tin);
            }
            if ($request->has('fs_no')) {
                $cleanFs = preg_replace('/\D/', '', (string)$request->fs_no);
                $parsed['receipt_no'] = $cleanFs ?: trim((string)$request->fs_no);
                $parsed['fs_no']      = $cleanFs ?: trim((string)$request->fs_no);
            }
            if ($request->has('mrc_no')) {
                $parsed['mrc']    = trim((string)$request->mrc_no);
                $parsed['mrc_no'] = trim((string)$request->mrc_no);
            }
            if ($parsedDate) {
                $parsed['receipt_date'] = EthiopianDate::formatDateEc($parsedDate, 'd-m-Y');
            }
            if ($rawUom !== null && $rawUom !== '') {
                $parsed['uom'] = (string)$rawUom;
            }
            if (!empty($rawCal)) {
                $parsed['calendar_type'] = $rawCal;
            }
            if ($request->has('qty')) {
                $parsed['qty'] = $qty;
            }
            if ($request->has('unit_price')) {
                $parsed['unit_price'] = $unitPrice;
            }
            if ($request->filled('vat_category')) {
                $parsed['vat_category'] = $vatCategory;
            }
            if ($request->filled('purchase_type')) {
                $parsed['purc_type'] = (string)$request->purchase_type;
                $parsed['purchase_type'] = $purchaseTypeInt;
            }
            if ($request->filled('item_description')) {
                $parsed['item_description'] = $request->item_description;
            }
            $parsed['tax_type'] = ($vat <= 0) ? 'no_vat' : 'vat_standard';
            if ($vat <= 0) {
                $parsed['no_vat_reason_code'] = $parsed['no_vat_reason_code'] ?? 'fuel_zero_vat';
            }
            // Reconcile withholding calculation based on totalVal (base)
            if ($hasWht) {
                if ($whtRate === null || $whtRate <= 0) {
                    $whtRate = 3.00;
                }
                if ($whtAmount === null || $whtAmount <= 0) {
                    $whtAmount = round($totalVal * ($whtRate / 100), 2);
                }
            } else {
                $whtRate = 0.0;
                $whtAmount = 0.0;
                $whtReceiptNo = null;
            }

            if ($request->has('has_withholding') || $request->has('withholding_amount') || $request->has('withholding_rate') || $uploadedWhtPath || $request->has('withholding_receipt_number')) {
                $parsed['has_withholding'] = $hasWht;
                $parsed['withholding_rate'] = $whtRate ?: 0;
                $parsed['withholding_amount'] = $whtAmount ?: 0;
                $parsed['withholding_receipt_number'] = $whtReceiptNo;
                if ($uploadedWhtPath) {
                    $parsed['withholding_receipt'] = $uploadedWhtPath;
                } elseif (!$hasWht) {
                    unset($parsed['withholding_receipt']);
                }
                if (Schema::hasColumn('receipts', 'has_withholding')) {
                    $receiptUpdates['has_withholding'] = $hasWht;
                }
                if (Schema::hasColumn('receipts', 'withholding_rate')) {
                    $receiptUpdates['withholding_rate'] = $whtRate ?: 0;
                }
                if (Schema::hasColumn('receipts', 'withholding_amount')) {
                    $receiptUpdates['withholding_amount'] = $whtAmount ?: 0;
                }
            }
            $receiptUpdates['parsed_data'] = $parsed;

            if (Schema::hasColumn('receipts', 'tax_type')) {
                $receiptUpdates['tax_type'] = ($vat <= 0) ? 'no_vat' : 'vat_standard';
            }
            if (Schema::hasColumn('receipts', 'no_vat_reason_code') && $vat <= 0) {
                $receiptUpdates['no_vat_reason_code'] = 'fuel_zero_vat';
            }

            if ($receipt->items()->count() > 1) {
                $receipt->items()->where('id', '!=', $item->id)->delete();
            }

            $receiptUpdates['subtotal']     = $totalVal;
            $receiptUpdates['vat_amount']   = $vat;
            $receiptUpdates['total_amount'] = $valAfter;

            $receipt->update($receiptUpdates);
            $receipt->touch();

            // Sync linked ExpenseRequest and ExpenseHubVatDecision
            try {
                $linkedExp = $expReq;
                if (!$linkedExp && $receipt->purchasable_type && (str_contains($receipt->purchasable_type, 'ExpenseRequest') || $receipt->purchasable_type === 'expense_request') && $receipt->purchasable_id) {
                    $linkedExp = \App\Models\ExpenseRequest::find($receipt->purchasable_id);
                }
                if (!$linkedExp && !empty($receipt->receipt_number)) {
                    $linkedExp = \App\Models\ExpenseRequest::where('request_number', $receipt->receipt_number)->first();
                }
                if (!$linkedExp && empty($receipt->purchasable_type) && !empty($oldFsNo)) {
                    $linkedExp = \App\Models\ExpenseRequest::where('payment_reference', 'like', '%' . $oldFsNo . '%')->latest('id')->first();
                }
                if (!$linkedExp && empty($receipt->purchasable_type) && !empty($receipt->fs_no)) {
                    $linkedExp = \App\Models\ExpenseRequest::where('payment_reference', 'like', '%' . $receipt->fs_no . '%')->latest('id')->first();
                }

                if ($linkedExp && (empty($receipt->purchasable_type) || str_contains($receipt->purchasable_type, 'ExpenseRequest') || $receipt->purchasable_type === 'expense_request')) {
                    $receipt->purchasable_type = \App\Models\ExpenseRequest::class;
                    $receipt->purchasable_id   = $linkedExp->id;
                    $receipt->save();

                    if (Schema::hasColumn('expense_requests', 'receipt_id')) {
                        $linkedExp->receipt_id = $receipt->id;
                    }

                    $linkedExp->amount       = $totalVal;
                    $linkedExp->vat_amount   = $vat;
                    $linkedExp->gross_amount = $valAfter;
                    if ($request->has('has_withholding') || $request->has('withholding_amount') || $request->has('withholding_rate') || $uploadedWhtPath || $request->has('withholding_receipt_number')) {
                        if (Schema::hasColumn('expense_requests', 'has_withholding')) {
                            $linkedExp->has_withholding = $hasWht;
                        }
                        if ($whtRate !== null && Schema::hasColumn('expense_requests', 'withholding_rate')) {
                            $linkedExp->withholding_rate = $whtRate;
                        }
                        if ($whtAmount !== null && Schema::hasColumn('expense_requests', 'withholding_amount')) {
                            $linkedExp->withholding_amount = $whtAmount;
                        }
                        if ($whtReceiptNo !== null && Schema::hasColumn('expense_requests', 'withholding_receipt_number')) {
                            $linkedExp->withholding_receipt_number = $whtReceiptNo;
                        }
                        if ($uploadedWhtPath && Schema::hasColumn('expense_requests', 'withholding_receipt')) {
                            $linkedExp->withholding_receipt = $uploadedWhtPath;
                        }
                    }
                    if ($request->has('is_verified_without_receipt') && Schema::hasColumn('expense_requests', 'is_verified_without_receipt')) {
                        $isVerifiedVal = filter_var($request->input('is_verified_without_receipt'), FILTER_VALIDATE_BOOLEAN);
                        $linkedExp->is_verified_without_receipt = $isVerifiedVal;
                        $linkedExp->verified_without_receipt_at = $isVerifiedVal ? now() : null;
                        $linkedExp->verified_without_receipt_by = $isVerifiedVal ? (Auth::id() ?: 1) : null;
                        if ($isVerifiedVal && Schema::hasColumn('expense_requests', 'verified_without_receipt_notes')) {
                            $linkedExp->verified_without_receipt_notes = 'Verified via ERCA Inspector modal';
                        }
                    }
                    $linkedExp->net_amount   = max(0, $valAfter - (float)($linkedExp->withholding_amount ?? 0));
                    if ($request->filled('seller_name')) {
                        $cleanSeller = trim((string)$request->seller_name);
                        $linkedExp->payee = $cleanSeller;
                        if (Schema::hasColumn('expense_requests', 'account_name')) {
                            $linkedExp->account_name = $cleanSeller;
                        }
                    }
                    // Do NOT overwrite accounting payment dates created_at or paid_at on ExpenseRequest
                    if (Schema::hasColumn('expense_requests', 'vat_type')) {
                        $linkedExp->vat_type = ($vat > 0) ? 'exclusive' : 'none';
                    }
                    if (Schema::hasColumn('expense_requests', 'vat_rate')) {
                        $linkedExp->vat_rate = ($vat > 0) ? 15.00 : 0.00;
                    }
                    if ($request->filled('item_description')) {
                        $linkedExp->description = trim((string)$request->item_description);
                    }
                    if ($request->filled('category')) {
                        $linkedExp->category = trim((string)$request->category);
                    }
                    $refParts = [];
                    if (!empty($receipt->fs_no)) {
                        $refParts[] = 'FS: ' . $receipt->fs_no;
                    }
                    if (!empty($receipt->vendor_tin)) {
                        $refParts[] = 'TIN: ' . $receipt->vendor_tin;
                    }
                    if (!empty($receipt->mrc_no)) {
                        $refParts[] = 'MRC: ' . $receipt->mrc_no;
                    }
                    if (!empty($refParts)) {
                        $linkedExp->payment_reference = implode(' | ', $refParts);
                    }
                    if (!empty($uploadedPath)) {
                        $linkedExp->attachment = $uploadedPath;
                    }
                    $linkedExp->save();

                    if (Schema::hasTable('expense_hub_vat_decisions')) {
                        $vatDec = \App\Models\ExpenseHubVatDecision::firstOrNew([
                            'source_type' => 'expense_request',
                            'source_id'   => $linkedExp->id,
                        ]);
                        if ($receipt->fs_no) $vatDec->custom_fs_no = $receipt->fs_no;
                        if ($receipt->vendor_tin) $vatDec->custom_seller_tin = $receipt->vendor_tin;
                        if ($receipt->mrc_no) $vatDec->custom_mrc_no = $receipt->mrc_no;
                        if ($request->filled('seller_name') && Schema::hasColumn('expense_hub_vat_decisions', 'custom_seller_name')) {
                            $vatDec->custom_seller_name = trim((string)$request->seller_name);
                        }
                        if (!empty($uploadedPath) && Schema::hasColumn('expense_hub_vat_decisions', 'receipt_path')) {
                            $vatDec->receipt_path = $uploadedPath;
                        }
                        if ($rawUom !== null && $rawUom !== '' && Schema::hasColumn('expense_hub_vat_decisions', 'uom')) {
                            $vatDec->uom = (string)$rawUom;
                        }
                        if (!empty($rawCal) && Schema::hasColumn('expense_hub_vat_decisions', 'calendar_type')) {
                            $vatDec->calendar_type = $rawCal;
                        }
                        if ($request->has('qty') && Schema::hasColumn('expense_hub_vat_decisions', 'quantity')) {
                            $vatDec->quantity = $qty;
                        }
                        if ($request->has('unit_price') && Schema::hasColumn('expense_hub_vat_decisions', 'unit_price')) {
                            $vatDec->unit_price = $unitPrice;
                        }
                        if ($request->filled('vat_category') && Schema::hasColumn('expense_hub_vat_decisions', 'vat_category')) {
                            $vatDec->vat_category = $vatCategory;
                        }
                        if ($request->filled('purchase_type')) {
                            $vatDec->purc_type = (string)$request->purchase_type;
                            if (Schema::hasColumn('expense_hub_vat_decisions', 'purchase_type')) {
                                $vatDec->purchase_type = $purchaseTypeInt;
                            }
                        }
                        if ($request->filled('item_description') && Schema::hasColumn('expense_hub_vat_decisions', 'custom_item_description')) {
                            $vatDec->custom_item_description = (string)$request->item_description;
                        }
                        if ($parsedDate && Schema::hasColumn('expense_hub_vat_decisions', 'custom_receipt_date')) {
                            $vatDec->custom_receipt_date = $parsedDate;
                        }
                        $vatDec->vat_amount = $vat;
                        $vatDec->base_amount = $totalVal;
                        $vatDec->total_amount = $valAfter;
                        if ($request->has('has_withholding') && Schema::hasColumn('expense_hub_vat_decisions', 'has_withholding')) {
                            $vatDec->has_withholding = $hasWht;
                        }
                        if ($whtRate !== null && Schema::hasColumn('expense_hub_vat_decisions', 'withholding_rate')) {
                            $vatDec->withholding_rate = $whtRate;
                        }
                        if ($whtAmount !== null && Schema::hasColumn('expense_hub_vat_decisions', 'withholding_amount')) {
                            $vatDec->withholding_amount = $whtAmount;
                        }
                        if ($whtReceiptNo !== null && Schema::hasColumn('expense_hub_vat_decisions', 'withholding_receipt_number')) {
                            $vatDec->withholding_receipt_number = $whtReceiptNo;
                        }
                        if ($uploadedWhtPath && Schema::hasColumn('expense_hub_vat_decisions', 'withholding_receipt')) {
                            $vatDec->withholding_receipt = $uploadedWhtPath;
                        }
                        if (Schema::hasColumn('expense_hub_vat_decisions', 'net_payable_amount')) {
                            $vatDec->net_payable_amount = max(0, $valAfter - (float)($linkedExp->withholding_amount ?? 0));
                        }
                        if ($request->has('is_verified_without_receipt') && Schema::hasColumn('expense_hub_vat_decisions', 'is_verified_without_receipt')) {
                            $isVerifiedVal = filter_var($request->input('is_verified_without_receipt'), FILTER_VALIDATE_BOOLEAN);
                            $vatDec->is_verified_without_receipt = $isVerifiedVal;
                            if ($isVerifiedVal) {
                                $vatDec->vat_status = 'no_vat';
                                $vatDec->vat_rate = 0.00;
                                $vatDec->vat_amount = 0.00;
                                $vatDec->purchase_type = 6;
                                $vatDec->no_vat_reason_code = 'no_fiscal_receipt';
                                $vatDec->decided_by = Auth::id() ?: 1;
                                $vatDec->decided_at = now();
                            }
                        }
                        $vatDec->vat_status = ($vat > 0) ? ExpenseHubVatDecision::STATUS_VAT_APPLIES : ExpenseHubVatDecision::STATUS_NO_VAT;
                        $vatDec->decision = ($vat > 0) ? 'vat_applicable' : 'no_vat';
                        if ($vat <= 0) {
                            $vatDec->vat_status = 'no_vat';
                        }
                        $vatDec->save();
                    }

                    if (Schema::hasTable('vehicle_fuel_requests')) {
                        try {
                            $fuelReq = null;
                            if (Schema::hasColumn('vehicle_fuel_requests', 'expense_request_id')) {
                                $fuelReq = \App\Models\VehicleFuelRequest::where('expense_request_id', $linkedExp->id)->first();
                            }
                            if (!$fuelReq && !empty($linkedExp->request_number)) {
                                $hasReqNo = Schema::hasColumn('vehicle_fuel_requests', 'request_no');
                                $hasReqNum = Schema::hasColumn('vehicle_fuel_requests', 'request_number');
                                if ($hasReqNo || $hasReqNum) {
                                    $fuelReq = \App\Models\VehicleFuelRequest::where(function ($q) use ($linkedExp, $hasReqNo, $hasReqNum) {
                                        if ($hasReqNo && $hasReqNum) {
                                            $q->where('request_no', $linkedExp->request_number)
                                              ->orWhere('request_number', $linkedExp->request_number);
                                        } elseif ($hasReqNo) {
                                            $q->where('request_no', $linkedExp->request_number);
                                        } else {
                                            $q->where('request_number', $linkedExp->request_number);
                                        }
                                    })->first();
                                }
                            }
                            if ($fuelReq) {
                                if (!empty($receipt->vendor_tin) && Schema::hasColumn('vehicle_fuel_requests', 'fuel_station_tin')) {
                                    $fuelReq->fuel_station_tin = $receipt->vendor_tin;
                                }
                                if (!empty($receipt->fs_no) && Schema::hasColumn('vehicle_fuel_requests', 'fs_no')) {
                                    $fuelReq->fs_no = $receipt->fs_no;
                                }
                                if (!empty($receipt->mrc_no) && Schema::hasColumn('vehicle_fuel_requests', 'mrc_no')) {
                                    $fuelReq->mrc_no = $receipt->mrc_no;
                                }
                                if ($request->filled('seller_name') && Schema::hasColumn('vehicle_fuel_requests', 'fuel_station_name')) {
                                    $fuelReq->fuel_station_name = trim((string)$request->seller_name);
                                }
                                $fuelReq->save();
                            }
                        } catch (\Throwable $e) {
                            Log::warning('updateOcrItem fuelReq sync error: ' . $e->getMessage());
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('updateOcrItem linkedExp sync error: ' . $e->getMessage());
            }

            // Sync linked CreditStorePayment
            $csTarget = $csPayment;
            try {
                if (!$csTarget && $receipt->purchasable_type && str_contains($receipt->purchasable_type, 'CreditStorePayment') && $receipt->purchasable_id) {
                    $csTarget = \App\Models\CreditStorePayment::find($receipt->purchasable_id);
                }
                if ($csTarget) {
                    if ($receipt->fs_no) {
                        $csTarget->reference_no = $receipt->fs_no;
                    }
                    if (!empty($uploadedPath) && Schema::hasColumn('credit_store_payments', 'receipt_path')) {
                        $csTarget->receipt_path = $uploadedPath;
                    }
                    $csTarget->amount = $totalVal;
                    if (Schema::hasColumn('credit_store_payments', 'gross_amount')) {
                        $csTarget->gross_amount = $valAfter;
                    }
                    if (Schema::hasColumn('credit_store_payments', 'vat_amount')) {
                        $csTarget->vat_amount = $vat;
                    }
                    if (Schema::hasColumn('credit_store_payments', 'vat_type')) {
                        $csTarget->vat_type = ($vat > 0) ? 'exclusive' : 'none';
                    }
                    if (Schema::hasColumn('credit_store_payments', 'vat_rate')) {
                        $csTarget->vat_rate = ($vat > 0) ? 15.00 : 0.00;
                    }
                    if ($request->has('has_withholding') || $request->has('withholding_amount') || $request->has('withholding_rate') || $uploadedWhtPath || $request->has('withholding_receipt_number')) {
                        if (Schema::hasColumn('credit_store_payments', 'has_withholding')) {
                            $csTarget->has_withholding = $hasWht;
                        }
                        if ($whtRate !== null && Schema::hasColumn('credit_store_payments', 'withholding_rate')) {
                            $csTarget->withholding_rate = $whtRate;
                        }
                        if ($whtAmount !== null && Schema::hasColumn('credit_store_payments', 'withholding_amount')) {
                            $csTarget->withholding_amount = $whtAmount;
                        }
                        if ($whtReceiptNo !== null && Schema::hasColumn('credit_store_payments', 'withholding_receipt_number')) {
                            $csTarget->withholding_receipt_number = $whtReceiptNo;
                        }
                        if ($uploadedWhtPath && Schema::hasColumn('credit_store_payments', 'withholding_receipt')) {
                            $csTarget->withholding_receipt = $uploadedWhtPath;
                        }
                    }
                    if (Schema::hasColumn('credit_store_payments', 'net_amount')) {
                        $csTarget->net_amount = max(0, $valAfter - (float)($csTarget->withholding_amount ?? 0));
                    }
                    $csTarget->save();

                    if (Schema::hasTable('expense_hub_vat_decisions')) {
                        $csDec = \App\Models\ExpenseHubVatDecision::firstOrNew([
                            'source_type' => 'credit_store_payment',
                            'source_id'   => $csTarget->id,
                        ]);
                        if ($receipt->fs_no) $csDec->custom_fs_no = $receipt->fs_no;
                        if ($receipt->vendor_tin) $csDec->custom_seller_tin = $receipt->vendor_tin;
                        if ($receipt->mrc_no) $csDec->custom_mrc_no = $receipt->mrc_no;
                        if ($request->filled('seller_name') && Schema::hasColumn('expense_hub_vat_decisions', 'custom_seller_name')) {
                            $csDec->custom_seller_name = trim((string)$request->seller_name);
                        }
                        if ($request->filled('item_description') && Schema::hasColumn('expense_hub_vat_decisions', 'custom_item_description')) {
                            $csDec->custom_item_description = (string)$request->item_description;
                        }
                        if ($parsedDate && Schema::hasColumn('expense_hub_vat_decisions', 'custom_receipt_date')) {
                            $csDec->custom_receipt_date = $parsedDate;
                        }
                        if (!empty($uploadedPath) && Schema::hasColumn('expense_hub_vat_decisions', 'receipt_path')) {
                            $csDec->receipt_path = $uploadedPath;
                        }
                        $csDec->vat_amount = $vat;
                        $csDec->base_amount = $totalVal;
                        $csDec->total_amount = $valAfter;
                        $csDec->vat_status = ($vat > 0) ? 'vat_applies' : 'no_vat';
                        $csDec->save();
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('updateOcrItem credit payment sync error: ' . $e->getMessage());
            }

            // Sync linked ProcurementPayment
            $procTarget = $procPayment;
            try {
                if (!$procTarget && $receipt->purchasable_type && str_contains($receipt->purchasable_type, 'ProcurementPayment') && $receipt->purchasable_id) {
                    $procTarget = \App\Models\ProcurementPayment::with('purchaseRequest')->find($receipt->purchasable_id);
                }
                if ($procTarget) {
                    if ($request->filled('seller_name') && $procTarget->purchase_request_id && Schema::hasTable('purchase_requests')) {
                        \App\Models\PurchaseRequest::where('id', $procTarget->purchase_request_id)
                            ->update(['vendor_name' => trim((string)$request->seller_name)]);
                    }
                    if ($request->filled('item_description') && $procTarget->purchase_request_id && Schema::hasTable('purchase_requests')) {
                        \App\Models\PurchaseRequest::where('id', $procTarget->purchase_request_id)
                            ->update(['justification' => trim((string)$request->item_description)]);
                    }
                    if (Schema::hasTable('expense_hub_vat_decisions')) {
                        $procDec = \App\Models\ExpenseHubVatDecision::firstOrNew([
                            'source_type' => 'procurement_payment',
                            'source_id'   => $procTarget->id,
                        ]);
                        if ($receipt->fs_no) $procDec->custom_fs_no = $receipt->fs_no;
                        if ($receipt->vendor_tin) $procDec->custom_seller_tin = $receipt->vendor_tin;
                        if ($receipt->mrc_no) $procDec->custom_mrc_no = $receipt->mrc_no;
                        if ($request->filled('seller_name') && Schema::hasColumn('expense_hub_vat_decisions', 'custom_seller_name')) {
                            $procDec->custom_seller_name = trim((string)$request->seller_name);
                        }
                        if ($request->filled('item_description') && Schema::hasColumn('expense_hub_vat_decisions', 'custom_item_description')) {
                            $procDec->custom_item_description = (string)$request->item_description;
                        }
                        if ($parsedDate && Schema::hasColumn('expense_hub_vat_decisions', 'custom_receipt_date')) {
                            $procDec->custom_receipt_date = $parsedDate;
                        }
                        if (!empty($uploadedPath) && Schema::hasColumn('expense_hub_vat_decisions', 'receipt_path')) {
                            $procDec->receipt_path = $uploadedPath;
                        }
                        $procDec->vat_amount = $vat;
                        $procDec->base_amount = $totalVal;
                        $procDec->total_amount = $valAfter;
                        $procDec->vat_status = ($vat > 0) ? 'vat_applies' : 'no_vat';
                        if ($request->has('has_withholding') && Schema::hasColumn('expense_hub_vat_decisions', 'has_withholding')) {
                            $procDec->has_withholding = $hasWht;
                        }
                        if ($whtRate !== null && Schema::hasColumn('expense_hub_vat_decisions', 'withholding_rate')) {
                            $procDec->withholding_rate = $whtRate;
                        }
                        if ($whtAmount !== null && Schema::hasColumn('expense_hub_vat_decisions', 'withholding_amount')) {
                            $procDec->withholding_amount = $whtAmount;
                        }
                        if ($whtReceiptNo !== null && Schema::hasColumn('expense_hub_vat_decisions', 'withholding_receipt_number')) {
                            $procDec->withholding_receipt_number = $whtReceiptNo;
                        }
                        if ($uploadedWhtPath && Schema::hasColumn('expense_hub_vat_decisions', 'withholding_receipt')) {
                            $procDec->withholding_receipt = $uploadedWhtPath;
                        }
                        $procDec->save();
                    }

                    $procTarget->amount = $totalVal;
                    if (Schema::hasColumn('procurement_payments', 'gross_amount')) {
                        $procTarget->gross_amount = $valAfter;
                    }
                    if (Schema::hasColumn('procurement_payments', 'vat_amount')) {
                        $procTarget->vat_amount = $vat;
                    }
                    if (Schema::hasColumn('procurement_payments', 'vat_type')) {
                        $procTarget->vat_type = ($vat > 0) ? 'exclusive' : 'none';
                    }
                    if (Schema::hasColumn('procurement_payments', 'vat_rate')) {
                        $procTarget->vat_rate = ($vat > 0) ? 15.00 : 0.00;
                    }
                    if ($request->has('has_withholding') || $request->has('withholding_amount') || $request->has('withholding_rate') || $uploadedWhtPath || $request->has('withholding_receipt_number')) {
                        if (Schema::hasColumn('procurement_payments', 'has_withholding')) {
                            $procTarget->has_withholding = $hasWht;
                        }
                        if ($whtRate !== null && Schema::hasColumn('procurement_payments', 'withholding_rate')) {
                            $procTarget->withholding_rate = $whtRate;
                        }
                        if ($whtAmount !== null && Schema::hasColumn('procurement_payments', 'withholding_amount')) {
                            $procTarget->withholding_amount = $whtAmount;
                        }
                        if ($whtReceiptNo !== null && Schema::hasColumn('procurement_payments', 'withholding_receipt_number')) {
                            $procTarget->withholding_receipt_number = $whtReceiptNo;
                        }
                        if ($uploadedWhtPath && Schema::hasColumn('procurement_payments', 'withholding_receipt')) {
                            $procTarget->withholding_receipt = $uploadedWhtPath;
                        }
                    }
                    if (Schema::hasColumn('procurement_payments', 'net_amount')) {
                        $procTarget->net_amount = max(0, $valAfter - (float)($procTarget->withholding_amount ?? 0));
                    }
                    $procTarget->save();
                }
            } catch (\Throwable $e) {
                Log::warning('updateOcrItem procurement payment sync error: ' . $e->getMessage());
            }

            // Sync linked PettyCashMaterialPurchase
            $pcTarget = $pcPurchase;
            try {
                if (!$pcTarget && $receipt->purchasable_type && str_contains($receipt->purchasable_type, 'PettyCashMaterialPurchase') && $receipt->purchasable_id) {
                    $pcTarget = \App\Models\PettyCashMaterialPurchase::find($receipt->purchasable_id);
                }
                if ($pcTarget) {
                    if ($receipt->vendor_name) $pcTarget->supplier_name = $receipt->vendor_name;
                    if ($receipt->vendor_tin) $pcTarget->supplier_tin = $receipt->vendor_tin;
                    if ($receipt->fs_no) {
                        $pcTarget->fs_no = $receipt->fs_no;
                        $pcTarget->receipt_no = $receipt->fs_no;
                    }
                    if (!empty($uploadedPath)) {
                        $pcTarget->attachment_path = $uploadedPath;
                    }
                    $pcTarget->total_amount = $valAfter;
                    $pcTarget->save();
                }
            } catch (\Throwable $e) {
                Log::warning('updateOcrItem petty cash sync error: ' . $e->getMessage());
            }

            $item->refresh();
            $receipt->refresh();

            $calcFileUrl = null;
            if (!empty($receipt->file_path)) {
                try {
                    $calcFileUrl = \App\Services\FileUploadService::url($receipt->file_path);
                } catch (\Throwable $e) {
                    $calcFileUrl = Str::startsWith($receipt->file_path, ['http://', 'https://'])
                        ? $receipt->file_path
                        : asset('storage/' . ltrim($receipt->file_path, '/'));
                }
            }

            $calcWhtUrl = null;
            if (!empty($uploadedWhtPath)) {
                try {
                    $calcWhtUrl = \App\Services\FileUploadService::url($uploadedWhtPath);
                } catch (\Throwable $e) {
                    $calcWhtUrl = asset('storage/' . ltrim($uploadedWhtPath, '/'));
                }
            } elseif ($linkedExp?->withholding_receipt_url) {
                $calcWhtUrl = $linkedExp->withholding_receipt_url;
            } elseif (!empty($linkedExp?->withholding_receipt)) {
                try {
                    $calcWhtUrl = \App\Services\FileUploadService::url($linkedExp->withholding_receipt);
                } catch (\Throwable $e) {
                    $calcWhtUrl = asset('storage/' . ltrim($linkedExp->withholding_receipt, '/'));
                }
            } elseif (!empty($procTarget?->withholding_receipt)) {
                try {
                    $calcWhtUrl = \App\Services\FileUploadService::url($procTarget->withholding_receipt);
                } catch (\Throwable $e) {
                    $calcWhtUrl = asset('storage/' . ltrim($procTarget->withholding_receipt, '/'));
                }
            } elseif (!empty($csTarget?->withholding_receipt)) {
                try {
                    $calcWhtUrl = \App\Services\FileUploadService::url($csTarget->withholding_receipt);
                } catch (\Throwable $e) {
                    $calcWhtUrl = asset('storage/' . ltrim($csTarget->withholding_receipt, '/'));
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Invoice / Receipt #{$receipt->id} updated successfully and reconciled with system tax records.",
                'item'    => [
                    'id'                         => $item->id,
                    'receipt_id'                 => $receipt->id,
                    'expense_request_id'         => $linkedExp?->id,
                    'request_number'             => $linkedExp?->request_number ?: ($procTarget?->purchaseRequest?->pr_no ?: ($csTarget?->reference_no ?: $receipt->receipt_number)),
                    'file_path'                  => $receipt->file_path,
                    'file_url'                   => $calcFileUrl,
                    'vendor_name'                => $receipt->vendor_name ?: 'Unknown Vendor',
                    'vendor_tin'                 => $receipt->vendor_tin ?: '-',
                    'seller_tin'                 => $receipt->vendor_tin ?: '-',
                    'tin_valid'                  => (bool)($receipt->tin_valid ?? (strlen(preg_replace('/\D/', '', $receipt->vendor_tin ?? '')) === 10)),
                    'fs_no'                      => $receipt->fs_no ?: '-',
                    'fs_no_raw'                  => $receipt->fs_no_raw ?: ($receipt->fs_no ?: '-'),
                    'fs_no_valid'                => (bool)($receipt->fs_no_valid ?? (strlen(preg_replace('/\D/', '', $receipt->fs_no ?? '')) === 8)),
                    'mrc_no'                     => $receipt->mrc_no ?: '-',
                    'receipt_date_ymd'           => $receipt->receipt_date ? Carbon::parse($receipt->receipt_date)->format('Y-m-d') : '',
                    'receipt_date_ec'            => $receipt->receipt_date ? EthiopianDate::formatDateEc($receipt->receipt_date, 'DD/MM/YYYY EC') : '',
                    'date_ec'                    => $receipt->receipt_date ? EthiopianDate::formatDateEc($receipt->receipt_date, 'DD/MM/YYYY EC') : '-',
                    'date_gc'                    => $receipt->receipt_date ? Carbon::parse($receipt->receipt_date)->format('M d, Y') : '-',
                    'item_description'           => $item->item_description,
                    'uom'                        => (string)($item->uom ?: '7'),
                    'calendar_type'              => (string)($item->calendar_type ?: 'E'),
                    'qty'                        => (float)($item->qty ?: 1.00),
                    'unit_price'                 => (float)($item->unit_price ?: 0.00),
                    'vat_category'               => $item->vat_category ?: 'G',
                    'purchase_type'              => (string)($item->purchase_type ?: 3),
                    'purc_type'                  => (string)($item->purchase_type ?: 3),
                    'project_id'                 => $receipt->project_id,
                    'project_name'               => $receipt->project?->name ?: 'General',
                    'category'                   => $receipt->category ?: 'materials',
                    'base_value'                 => (float)$item->total_value,
                    'vat_amount'                 => (float)$item->vat_amount,
                    'total_value'                => (float)$item->value_after_vat,
                    'value_after_vat'            => (float)$item->value_after_vat,
                    'has_withholding'            => (bool)($linkedExp?->has_withholding ?? ($procTarget?->has_withholding ?? ($csTarget?->has_withholding ?? $hasWht))),
                    'withholding_rate'           => (float)($linkedExp?->withholding_rate ?? ($procTarget?->withholding_rate ?? ($csTarget?->withholding_rate ?? ($whtRate ?: 0)))),
                    'withholding_amount'         => (float)($linkedExp?->withholding_amount ?? ($procTarget?->withholding_amount ?? ($csTarget?->withholding_amount ?? ($whtAmount ?: 0)))),
                    'withholding_receipt_number' => (string)($linkedExp?->withholding_receipt_number ?? ($procTarget?->withholding_receipt_number ?? ($csTarget?->withholding_receipt_number ?? ($whtReceiptNo ?: '')))),
                    'withholding_receipt_url'    => $calcWhtUrl,
                    'net_disbursed'              => (float)($linkedExp?->net_amount ?? ($procTarget?->net_amount ?? ($csTarget?->net_amount ?? max(0, $valAfter - ($whtAmount ?: 0))))),
                    'is_vat_receipt'             => ($vat > 0 && $vatCategory !== 'E'),
                    'is_no_vat'                  => ($vat <= 0 || $vatCategory === 'E'),
                    'is_verified_without_receipt' => (bool)($linkedExp?->is_verified_without_receipt ?? (filter_var($request->input('is_verified_without_receipt'), FILTER_VALIDATE_BOOLEAN))),
                ]
            ]);
        } catch (\Throwable $e) {
            Log::error('FinanceTaxReportController updateOcrItem error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update invoice item: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * AJAX endpoint to toggle or mark an expense as Verified Without Receipt (operational 0% VAT / exempt).
     */
    public function verifyWithoutReceipt(Request $request)
    {
        $this->ensureSchema();

        $validated = $request->validate([
            'expense_id'     => 'nullable',
            'request_number' => 'nullable|string',
            'is_verified'    => 'nullable',
            'notes'          => 'nullable|string|max:500',
        ]);

        $rawId = $request->input('expense_id');
        $reqNo = $request->input('request_number');
        $isVerified = filter_var($request->input('is_verified', true), FILTER_VALIDATE_BOOLEAN);
        $notes = $request->input('notes', 'Verified as operational expense without fiscal receipt (0% VAT / Exempt)');

        $expReq = null;
        if (!empty($rawId)) {
            $cleanId = preg_replace('/^EXP-/i', '', (string)$rawId);
            if (is_numeric($cleanId)) {
                $expReq = \App\Models\ExpenseRequest::find($cleanId);
            }
        }
        if (!$expReq && !empty($reqNo)) {
            $expReq = \App\Models\ExpenseRequest::where('request_number', $reqNo)->first();
        }

        if ($expReq) {
            if (Schema::hasColumn('expense_requests', 'is_verified_without_receipt')) {
                $expReq->is_verified_without_receipt = $isVerified;
                $expReq->verified_without_receipt_at = $isVerified ? now() : null;
                $expReq->verified_without_receipt_by = $isVerified ? (Auth::id() ?: 1) : null;
                if ($isVerified && Schema::hasColumn('expense_requests', 'verified_without_receipt_notes')) {
                    $expReq->verified_without_receipt_notes = $notes;
                }
                $expReq->save();
            }

            if (Schema::hasTable('expense_hub_vat_decisions')) {
                $dec = \App\Models\ExpenseHubVatDecision::firstOrNew([
                    'source_type' => 'expense_request',
                    'source_id'   => $expReq->id,
                ]);
                if (Schema::hasColumn('expense_hub_vat_decisions', 'is_verified_without_receipt')) {
                    $dec->is_verified_without_receipt = $isVerified;
                }
                if ($isVerified) {
                    $dec->vat_status = 'no_vat';
                    $dec->vat_rate = 0.00;
                    $dec->vat_amount = 0.00;
                    $dec->purchase_type = 6;
                    $dec->no_vat_reason_code = 'no_fiscal_receipt';
                    $dec->decided_by = Auth::id() ?: 1;
                    $dec->decided_at = now();
                } else {
                    $dec->vat_status = 'pending_decision';
                    $dec->no_vat_reason_code = null;
                    $dec->decided_by = null;
                    $dec->decided_at = null;
                }
                $dec->save();
            }

            return response()->json([
                'success' => true,
                'message' => $isVerified ? 'Expense verified without receipt (operational 0% VAT).' : 'Verification without receipt removed.',
                'is_verified_without_receipt' => $isVerified,
                'expense_id' => $expReq->id,
                'request_number' => $expReq->request_number,
            ]);
        }

        // Check if it's a Procurement Payment (e.g. PROC-12)
        if (!empty($rawId) && preg_match('/^PROC-(\d+)$/i', (string)$rawId, $mProc)) {
            if (Schema::hasTable('expense_hub_vat_decisions')) {
                $dec = \App\Models\ExpenseHubVatDecision::firstOrNew([
                    'source_type' => 'procurement_payment',
                    'source_id'   => $mProc[1],
                ]);
                if (Schema::hasColumn('expense_hub_vat_decisions', 'is_verified_without_receipt')) {
                    $dec->is_verified_without_receipt = $isVerified;
                }
                if ($isVerified) {
                    $dec->vat_status = 'no_vat';
                    $dec->vat_rate = 0.00;
                    $dec->vat_amount = 0.00;
                    $dec->purchase_type = 6;
                    $dec->no_vat_reason_code = 'no_fiscal_receipt';
                    $dec->decided_by = Auth::id() ?: 1;
                    $dec->decided_at = now();
                } else {
                    $dec->vat_status = 'pending_decision';
                    $dec->no_vat_reason_code = null;
                    $dec->decided_by = null;
                    $dec->decided_at = null;
                }
                $dec->save();

                return response()->json([
                    'success' => true,
                    'message' => $isVerified ? 'Procurement purchase verified without receipt.' : 'Verification removed.',
                    'is_verified_without_receipt' => $isVerified,
                    'expense_id' => $rawId,
                ]);
            }
        }

        // Check if it's a Credit Store Payment (e.g. CS-12)
        if (!empty($rawId) && preg_match('/^CS-(\d+)$/i', (string)$rawId, $mCs)) {
            if (Schema::hasTable('expense_hub_vat_decisions')) {
                $dec = \App\Models\ExpenseHubVatDecision::firstOrNew([
                    'source_type' => 'credit_store_payment',
                    'source_id'   => $mCs[1],
                ]);
                if (Schema::hasColumn('expense_hub_vat_decisions', 'is_verified_without_receipt')) {
                    $dec->is_verified_without_receipt = $isVerified;
                }
                if ($isVerified) {
                    $dec->vat_status = 'no_vat';
                    $dec->vat_rate = 0.00;
                    $dec->vat_amount = 0.00;
                    $dec->purchase_type = 6;
                    $dec->no_vat_reason_code = 'no_fiscal_receipt';
                    $dec->decided_by = Auth::id() ?: 1;
                    $dec->decided_at = now();
                } else {
                    $dec->vat_status = 'pending_decision';
                    $dec->no_vat_reason_code = null;
                    $dec->decided_by = null;
                    $dec->decided_at = null;
                }
                $dec->save();

                return response()->json([
                    'success' => true,
                    'message' => $isVerified ? 'Credit purchase verified without receipt.' : 'Verification removed.',
                    'is_verified_without_receipt' => $isVerified,
                    'expense_id' => $rawId,
                ]);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'Expense or purchase record not found for verification.',
        ], 404);
    }
}
