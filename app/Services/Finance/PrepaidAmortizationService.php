<?php

namespace App\Services\Finance;

use App\Models\PrepaidBondAmortizationSchedule;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;
use Exception;

class PrepaidAmortizationService
{
    protected JournalEngine $journalEngine;

    public function __construct(JournalEngine $journalEngine)
    {
        $this->journalEngine = $journalEngine;
    }

    /**
     * Amortize performance bond fees only over the validity period.
     * Posts:
     *   DR 6651 Advance & Performance Bond Expense (tagged with project)
     *   CR 1280 Prepaid Leases & Performance Bonds
     *
     * Idempotent: Does not amortize beyond total fee amount.
     */
    public function amortizeMonthlyBondFee(
        PrepaidBondAmortizationSchedule $schedule,
        string $periodDate,
        int $userId
    ): ?JournalEntry {
        return DB::transaction(function () use ($schedule, $periodDate, $userId) {
            $totalFee = (float) $schedule->total_fee_amount;
            $amortizedSoFar = (float) $schedule->amortized_amount;
            $remaining = max(0.0, round($totalFee - $amortizedSoFar, 2));

            if ($remaining <= 0.001) {
                return null; // Fully amortized
            }

            $monthlyAmortization = (float) $schedule->monthly_amortization_amount;
            $amortizationAmount = min($monthlyAmortization, $remaining);

            if ($amortizationAmount <= 0.001) {
                return null;
            }

            // Post balanced journal
            $entry = $this->journalEngine->createEntry([
                'entry_date'     => $periodDate,
                'reference_type' => 'BondFeeAmortization',
                'reference_id'   => $schedule->id,
                'description'    => "Performance Bond Fee Amortization: {$schedule->name} [{$schedule->bond_reference}]",
                'created_by'     => $userId,
                'project_id'     => $schedule->project_id,
                'cost_center_id' => $schedule->cost_center_id,
                'site_id'        => $schedule->site_id,
                'lines'          => [
                    [
                        'account_id'     => $schedule->expense_account_id,
                        'side'           => 'debit',
                        'amount'         => $amortizationAmount,
                        'description'    => "Bond fee amortized: {$schedule->bond_reference}",
                        'project_id'     => $schedule->project_id,
                        'cost_center_id' => $schedule->cost_center_id,
                        'site_id'        => $schedule->site_id,
                    ],
                    [
                        'account_id'     => $schedule->prepayment_account_id,
                        'side'           => 'credit',
                        'amount'         => $amortizationAmount,
                        'description'    => "Relief of prepaid bond fee asset",
                        'project_id'     => $schedule->project_id,
                        'cost_center_id' => $schedule->cost_center_id,
                        'site_id'        => $schedule->site_id,
                    ],
                ],
            ], approvedBy: $userId);

            $schedule->amortized_amount += $amortizationAmount;
            $schedule->save();

            return $entry;
        });
    }
}
