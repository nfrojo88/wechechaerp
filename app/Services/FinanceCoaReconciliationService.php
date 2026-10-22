<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\ExpenseRequest;
use App\Models\OfficeMaterialRequest;
use App\Models\PettyCashReplenishment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class FinanceCoaReconciliationService
{
    /**
     * Reconcile and deduct historical paid requests from their corresponding COA accounts.
     * Safe, idempotent, and runs once per paid record.
     */
    public static function reconcileHistoricalPayments(): array
    {
        $stats = [
            'expense_requests_count'  => 0,
            'expense_requests_amount' => 0.0,
            'office_requests_count'   => 0,
            'office_requests_amount'  => 0.0,
            'replenishments_count'    => 0,
            'replenishments_amount'   => 0.0,
            'accounts_updated'        => [],
        ];

        try {
            self::ensureSchema();

            DB::transaction(function () use (&$stats) {
                // 1. Reconcile Expense Requests
                if (Schema::hasTable('expense_requests')) {
                    $expenseRequests = ExpenseRequest::where(function ($q) {
                        $q->whereIn(DB::raw('LOWER(status)'), ['paid', 'approved (paid)'])
                          ->orWhereNotNull('paid_at');
                    })
                    ->where(function ($q) {
                        $q->where('coa_deducted', false)
                          ->orWhereNull('coa_deducted');
                    })
                    ->lockForUpdate()
                    ->get();

                    foreach ($expenseRequests as $exp) {
                        $disbursed = (float)($exp->net_amount > 0 ? $exp->net_amount : ($exp->amount > 0 ? $exp->amount : $exp->gross_amount));
                        if ($disbursed <= 0) {
                            $exp->update(['coa_deducted' => true]);
                            continue;
                        }

                        $isPettyCash = str_starts_with($exp->request_number, 'EXP-PCR-')
                            || ($exp->category === ExpenseRequest::CATEGORY_OTHER && $exp->other_reason === 'Petty Cash Replenishment')
                            || str_contains($exp->description ?? '', 'Petty Cash Replenishment')
                            || str_contains($exp->category ?? '', 'Petty Cash Replenishment');

                        if ($isPettyCash) {
                            $sourceCoa = null;
                            if (!empty($exp->coa_id)) {
                                $sourceCoa = ChartOfAccount::find($exp->coa_id);
                            }
                            if (!$sourceCoa && !empty($exp->bank_account_id)) {
                                $bankAcc = BankAccount::find($exp->bank_account_id);
                                if ($bankAcc?->coa_id) {
                                    $sourceCoa = ChartOfAccount::find($bankAcc->coa_id);
                                }
                            }
                            if (!$sourceCoa && Schema::hasTable('petty_cash_replenishments')) {
                                $replenishment = PettyCashReplenishment::where('request_no', $exp->request_number)->first();
                                if ($replenishment?->source_coa_id) {
                                    $sourceCoa = ChartOfAccount::find($replenishment->source_coa_id);
                                }
                            }

                            $destPettyId = $exp->chart_of_account_id ?? ($exp->petty_cash_owner_id ?? null);
                            if ($sourceCoa && $sourceCoa->id != $destPettyId) {
                                $sourceCoa->decrement('current_balance', $disbursed);
                                $stats['expense_requests_count']++;
                                $stats['expense_requests_amount'] += $disbursed;
                                $stats['accounts_updated'][$sourceCoa->id] = ($stats['accounts_updated'][$sourceCoa->id] ?? 0) + $disbursed;
                            }
                            $exp->update(['coa_deducted' => true]);
                            continue;
                        }

                        // Standard Expense Request
                        $targetCoa = null;
                        $coaId = $exp->chart_of_account_id ?: $exp->coa_id;
                        if ($coaId) {
                            $targetCoa = ChartOfAccount::find($coaId);
                        }

                        if (!$targetCoa && $exp->bank_account_id) {
                            $bankAcc = BankAccount::find($exp->bank_account_id);
                            if ($bankAcc) {
                                if ($bankAcc->coa_id) {
                                    $targetCoa = ChartOfAccount::find($bankAcc->coa_id);
                                } else {
                                    $targetCoa = self::matchCoaForBank($bankAcc);
                                    if ($targetCoa) {
                                        $bankAcc->update(['coa_id' => $targetCoa->id]);
                                    }
                                }
                            }
                        }

                        if (!$targetCoa) {
                            $targetCoa = ChartOfAccount::where('type', 'asset')
                                ->where(function($q) {
                                    $q->where('code', 'like', '10%')
                                      ->orWhere('name', 'like', '%Bank%')
                                      ->orWhere('name', 'like', '%Petty Cash%')
                                      ->orWhere('name', 'like', '%Cash%');
                                })
                                ->first();
                        }

                        if ($targetCoa) {
                            $targetCoa->decrement('current_balance', $disbursed);
                            $stats['expense_requests_count']++;
                            $stats['expense_requests_amount'] += $disbursed;
                            $stats['accounts_updated'][$targetCoa->id] = ($stats['accounts_updated'][$targetCoa->id] ?? 0) + $disbursed;

                            $exp->update([
                                'coa_deducted'        => true,
                                'chart_of_account_id' => $exp->chart_of_account_id ?: $targetCoa->id,
                                'coa_id'              => $exp->coa_id ?: $targetCoa->id,
                            ]);
                        } else {
                            $exp->update(['coa_deducted' => true]);
                        }
                    }
                }

                // 2. Reconcile Office Material Requests
                if (Schema::hasTable('office_material_requests')) {
                    $officeRequests = OfficeMaterialRequest::where(function ($q) {
                        $q->where(DB::raw('LOWER(status)'), 'paid')
                          ->orWhereNotNull('paid_at');
                    })
                    ->where(function ($q) {
                        $q->where('coa_deducted', false)
                          ->orWhereNull('coa_deducted');
                    })
                    ->lockForUpdate()
                    ->get();

                    foreach ($officeRequests as $off) {
                        $amt = (float)$off->amount;
                        if ($amt <= 0) {
                            $off->update(['coa_deducted' => true]);
                            continue;
                        }

                        $targetCoa = null;
                        if (!empty($off->coa_id)) {
                            $targetCoa = ChartOfAccount::find($off->coa_id);
                        }
                        if (!$targetCoa && !empty($off->bank_account_id)) {
                            $bankAcc = BankAccount::find($off->bank_account_id);
                            if ($bankAcc?->coa_id) {
                                $targetCoa = ChartOfAccount::find($bankAcc->coa_id);
                            } else if ($bankAcc) {
                                $targetCoa = self::matchCoaForBank($bankAcc);
                                if ($targetCoa) {
                                    $bankAcc->update(['coa_id' => $targetCoa->id]);
                                }
                            }
                        }
                        if (!$targetCoa) {
                            $targetCoa = ChartOfAccount::where('type', 'asset')
                                ->where(function($q) {
                                    $q->where('code', 'like', '10%')
                                      ->orWhere('name', 'like', '%Bank%')
                                      ->orWhere('name', 'like', '%Petty Cash%')
                                      ->orWhere('name', 'like', '%Cash%');
                                })
                                ->first();
                        }

                        if ($targetCoa) {
                            $targetCoa->decrement('current_balance', $amt);
                            $stats['office_requests_count']++;
                            $stats['office_requests_amount'] += $amt;
                            $stats['accounts_updated'][$targetCoa->id] = ($stats['accounts_updated'][$targetCoa->id] ?? 0) + $amt;

                            $off->update([
                                'coa_deducted' => true,
                                'coa_id'       => $off->coa_id ?: $targetCoa->id,
                            ]);
                        } else {
                            $off->update(['coa_deducted' => true]);
                        }
                    }
                }

                // 3. Reconcile Petty Cash Replenishments
                if (Schema::hasTable('petty_cash_replenishments')) {
                    $replenishments = PettyCashReplenishment::where(function ($q) {
                        $q->where(DB::raw('LOWER(status)'), 'fulfilled')
                          ->orWhereNotNull('fulfilled_at');
                    })
                    ->where(function ($q) {
                        $q->where('coa_deducted', false)
                          ->orWhereNull('coa_deducted');
                    })
                    ->lockForUpdate()
                    ->get();

                    foreach ($replenishments as $pcr) {
                        $amt = (float)($pcr->fulfilled_amount > 0 ? $pcr->fulfilled_amount : $pcr->requested_amount);
                        if ($amt <= 0) {
                            $pcr->update(['coa_deducted' => true]);
                            continue;
                        }

                        $sourceCoa = null;
                        if (!empty($pcr->source_coa_id)) {
                            $sourceCoa = ChartOfAccount::find($pcr->source_coa_id);
                        }

                        if ($sourceCoa && $sourceCoa->id != $pcr->chart_of_account_id) {
                            $sourceCoa->decrement('current_balance', $amt);
                            $stats['replenishments_count']++;
                            $stats['replenishments_amount'] += $amt;
                            $stats['accounts_updated'][$sourceCoa->id] = ($stats['accounts_updated'][$sourceCoa->id] ?? 0) + $amt;
                        }
                        $pcr->update(['coa_deducted' => true]);
                    }
                }
            });

            if ($stats['expense_requests_count'] > 0 || $stats['office_requests_count'] > 0 || $stats['replenishments_count'] > 0) {
                Log::info("FinanceCoaReconciliationService: Reconciled historical paid records.", $stats);
            }
        } catch (\Throwable $e) {
            Log::error("FinanceCoaReconciliationService failed: " . $e->getMessage());
        }

        return $stats;
    }

    /**
     * Resolve the corresponding Chart of Account for a BankAccount by name or standard bank aliases.
     */
    public static function matchCoaForBank(BankAccount $bankAcc): ?ChartOfAccount
    {
        // 1. Direct match by bank_name or account_name
        $targetCoa = ChartOfAccount::where('name', 'like', '%' . $bankAcc->bank_name . '%')
            ->orWhere('name', 'like', '%' . $bankAcc->account_name . '%')
            ->first();

        if ($targetCoa) {
            return $targetCoa;
        }

        // 2. Acronym and alias matching
        $aliases = [
            'Commercial Bank of Ethiopia' => ['CBE', 'Commercial Bank'],
            'Bank of Abyssinia'          => ['BOA', 'Abyssinia'],
            'Awash Bank'                 => ['Awash'],
            'Dashen Bank'                => ['Dashen'],
            'Nib International Bank'     => ['Nib'],
            'United Bank'                => ['Hibret', 'United'],
            'Cooperative Bank of Oromia' => ['Coop', 'Cooperative'],
            'Zemen Bank'                 => ['Zemen'],
            'Wegagen Bank'               => ['Wegagen'],
        ];

        foreach ($aliases as $full => $abbrs) {
            $isFullMatch = str_contains(strtolower($bankAcc->bank_name), strtolower($full))
                || str_contains(strtolower($bankAcc->account_name), strtolower($full));

            if ($isFullMatch) {
                foreach ($abbrs as $abbr) {
                    $targetCoa = ChartOfAccount::where('name', 'like', "%{$abbr}%")->first();
                    if ($targetCoa) return $targetCoa;
                }
            }

            foreach ($abbrs as $abbr) {
                if (str_contains(strtolower($bankAcc->bank_name), strtolower($abbr))
                    || str_contains(strtolower($bankAcc->account_name), strtolower($abbr))) {
                    $targetCoa = ChartOfAccount::where('name', 'like', "%{$full}%")
                        ->orWhere('name', 'like', "%{$abbr}%")
                        ->first();
                    if ($targetCoa) return $targetCoa;
                }
            }
        }

        // 3. Fallback: Any asset account with 'Bank' or code starting with 10
        return ChartOfAccount::where('type', 'asset')
            ->where(function ($q) {
                $q->where('code', 'like', '10%')
                  ->orWhere('name', 'like', '%Bank%');
            })
            ->first();
    }

    /**
     * Ensure the coa_deducted column exists across tables safely.
     */
    public static function ensureSchema(): void
    {
        try {
            if (Schema::hasTable('expense_requests') && !Schema::hasColumn('expense_requests', 'coa_deducted')) {
                Schema::table('expense_requests', function ($table) {
                    $table->boolean('coa_deducted')->default(false)->after('paid_at');
                });
            }

            if (Schema::hasTable('office_material_requests') && !Schema::hasColumn('office_material_requests', 'coa_deducted')) {
                Schema::table('office_material_requests', function ($table) {
                    $table->boolean('coa_deducted')->default(false)->after('paid_at');
                });
            }

            if (Schema::hasTable('petty_cash_replenishments') && !Schema::hasColumn('petty_cash_replenishments', 'coa_deducted')) {
                Schema::table('petty_cash_replenishments', function ($table) {
                    $table->boolean('coa_deducted')->default(false)->after('fulfilled_at');
                });
            }
        } catch (\Throwable $e) {
            Log::warning("FinanceCoaReconciliationService schema ensure warning: " . $e->getMessage());
        }
    }
}
