<?php

namespace App\Services\Finance;

use App\Models\AccrualEntry;
use App\Models\FinancialPeriod;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Exception;

class AccrualEngineService
{
    protected JournalEngine $journalEngine;

    public function __construct(JournalEngine $journalEngine)
    {
        $this->journalEngine = $journalEngine;
    }

    /**
     * Post a period-end operational accrual.
     * Supports:
     * - 'payroll_unapproved': DR 6100 Salary Expense / CR 2251 Accrued Salaries (Only if payroll unapproved)
     * - 'subcontractor_unbilled': DR 1260 WIP or 6000 OPEX / CR 2255 Accrued Subcontractor Costs (Dedicated 225x, NO 2050)
     * - 'utilities': DR 6400 Utility Expense / CR 2252 Accrued Utilities
     * - 'bank_interest': DR 6600 Interest Expense / CR 2253 Accrued Bank Interest
     * - 'audit_fees': DR 6450 Audit Expense / CR 2254 Accrued Audit & Legal Fees
     *
     * Note: GRNI is strictly excluded (handled naturally by three-way matching).
     */
    public function postAccrual(
        FinancialPeriod $period,
        string $accrualType,
        float $amount,
        int $userId,
        ?int $projectId = null,
        ?int $costCenterId = null,
        ?int $siteId = null,
        ?string $notes = null
    ): ?AccrualEntry {
        if ($amount <= 0) {
            return null;
        }

        if ($period->is_locked) {
            throw new Exception("Cannot post accrual to locked period {$period->name}.");
        }

        return DB::transaction(function () use (
            $period, $accrualType, $amount, $userId, $projectId, $costCenterId, $siteId, $notes
        ) {
            // Salary accruals only allowed for unapproved payroll
            if ($accrualType === 'payroll_unapproved') {
                $hasApprovedPayroll = DB::table('journal_entries')
                    ->whereIn('reference_type', ['payroll', 'PayrollGlPosting'])
                    ->where('status', 'posted')
                    ->whereDate('entry_date', '>=', $period->start_date)
                    ->whereDate('entry_date', '<=', $period->end_date)
                    ->exists();

                if ($hasApprovedPayroll) {
                    // Payroll already posted for this period; skip accrual to prevent double counting
                    return null;
                }
            }

            // Resolve dedicated accounts
            [$expenseCode, $accrualCode] = match ($accrualType) {
                'payroll_unapproved'    => ['6100', '2251'], // 2251 Accrued Salaries & Wages
                'subcontractor_unbilled'=> ['1260', '2255'], // 2255 Accrued Subcontractor Costs (NO 2050)
                'utilities'             => ['6400', '2252'], // 2252 Accrued Utilities
                'bank_interest'         => ['6600', '2253'], // 2253 Accrued Bank Interest
                'audit_fees'            => ['6450', '2254'], // 2254 Accrued Audit & Legal Fees
                default => throw new Exception("Unsupported accrual type: {$accrualType}"),
            };

            $expenseAcc = ChartOfAccount::where('code', $expenseCode)->firstOrFail();
            $accrualAcc = ChartOfAccount::where('code', $accrualCode)->firstOrFail();

            $description = "Period-End Accrual [{$period->code}]: " . ucfirst(str_replace('_', ' ', $accrualType));

            // Post balanced journal entry
            $je = $this->journalEngine->createEntry([
                'entry_date'     => $period->end_date,
                'reference_type' => 'AccrualPosting',
                'reference_id'   => $period->id,
                'description'    => $description,
                'created_by'     => $userId,
                'project_id'     => $projectId,
                'cost_center_id' => $costCenterId,
                'site_id'        => $siteId,
                'lines'          => [
                    [
                        'account_id'     => $expenseAcc->id,
                        'side'           => 'debit',
                        'amount'         => $amount,
                        'description'    => "Accrued cost: {$accrualType}",
                        'project_id'     => $projectId,
                        'cost_center_id' => $costCenterId,
                        'site_id'        => $siteId,
                    ],
                    [
                        'account_id'     => $accrualAcc->id,
                        'side'           => 'credit',
                        'amount'         => $amount,
                        'description'    => "Accrual liability: [{$accrualAcc->code}] {$accrualAcc->name}",
                        'project_id'     => $projectId,
                        'cost_center_id' => $costCenterId,
                        'site_id'        => $siteId,
                    ],
                ],
            ], approvedBy: $userId);

            // Record accrual entry tracking record
            return AccrualEntry::create([
                'financial_period_id' => $period->id,
                'accrual_type'        => $accrualType,
                'journal_entry_id'    => $je->id,
                'amount'              => $amount,
                'expense_account_id'  => $expenseAcc->id,
                'accrual_account_id'  => $accrualAcc->id,
                'project_id'          => $projectId,
                'cost_center_id'      => $costCenterId,
                'site_id'             => $siteId,
                'is_reversed'         => false,
                'notes'               => $notes,
            ]);
        });
    }

    /**
     * Execute idempotent auto-reversals on Day 1 of the subsequent period.
     * Requires:
     * - Next period exists and is OPEN (is_locked = 0).
     * - Reversal is idempotent (skips already reversed entries).
     * - Links reversal journal back to original accrual entry.
     */
    public function reversePeriodAccruals(
        FinancialPeriod $currentPeriod,
        FinancialPeriod $nextPeriod,
        int $userId,
        ?string $specificType = null
    ): array {
        if ($nextPeriod->is_locked) {
            throw new Exception("Cannot post auto-reversal to locked subsequent period {$nextPeriod->name}.");
        }

        $query = AccrualEntry::where('financial_period_id', $currentPeriod->id)
            ->where('is_reversed', false);

        if ($specificType) {
            $query->where('accrual_type', $specificType);
        }

        $pendingAccruals = $query->get();
        $reversedJournals = [];

        foreach ($pendingAccruals as $accrual) {
            DB::transaction(function () use ($accrual, $nextPeriod, $userId, &$reversedJournals) {
                // Double check idempotency under transaction lock
                $accrual->refresh();
                if ($accrual->is_reversed) {
                    return;
                }

                $originalJe = $accrual->journalEntry;
                if (!$originalJe) {
                    return;
                }

                // Create exact opposite lines for reversal
                $reversalDate = $nextPeriod->start_date;
                $description = "Auto-Reversal [Day 1 {$nextPeriod->code}]: Reversal of {$originalJe->entry_no}";

                $reversalJe = $this->journalEngine->createEntry([
                    'entry_date'     => $reversalDate,
                    'reference_type' => 'AccrualAutoReversal',
                    'reference_id'   => $accrual->id,
                    'description'    => $description,
                    'created_by'     => $userId,
                    'project_id'     => $accrual->project_id,
                    'cost_center_id' => $accrual->cost_center_id,
                    'site_id'        => $accrual->site_id,
                    'lines'          => [
                        [
                            'account_id'     => $accrual->accrual_account_id,
                            'side'           => 'debit', // Clears accrual liability
                            'amount'         => (float) $accrual->amount,
                            'description'    => "Reversal clearing accrual liability [{$accrual->accrualAccount?->code}]",
                            'project_id'     => $accrual->project_id,
                            'cost_center_id' => $accrual->cost_center_id,
                            'site_id'        => $accrual->site_id,
                        ],
                        [
                            'account_id'     => $accrual->expense_account_id,
                            'side'           => 'credit', // Offsets subsequent live invoice/payroll
                            'amount'         => (float) $accrual->amount,
                            'description'    => "Reversal offset on expense [{$accrual->expenseAccount?->code}]",
                            'project_id'     => $accrual->project_id,
                            'cost_center_id' => $accrual->cost_center_id,
                            'site_id'        => $accrual->site_id,
                        ],
                    ],
                ], approvedBy: $userId);

                // Update cross-link references
                $reversalJe->update([
                    'is_reversal'    => true,
                    'reversal_of_id' => $originalJe->id,
                ]);

                $originalJe->update([
                    'reversed_by_id' => $reversalJe->id,
                ]);

                $accrual->update([
                    'is_reversed'                => true,
                    'reversal_journal_entry_id'  => $reversalJe->id,
                    'reversed_at'                => now(),
                    'reversed_by'                => $userId,
                ]);

                $reversedJournals[] = $reversalJe;
            });
        }

        return $reversedJournals;
    }
}
