<?php

namespace App\Services\Finance;

use App\Models\FixedAssetRegister;
use App\Models\JournalEntry;
use App\Models\FinancialPeriod;
use Illuminate\Support\Facades\DB;
use Exception;

class FixedAssetDepreciationService
{
    protected JournalEngine $journalEngine;

    public function __construct(JournalEngine $journalEngine)
    {
        $this->journalEngine = $journalEngine;
    }

    /**
     * Compute and post monthly depreciation for an asset.
     * Supports:
     * - 'straight_line': (Cost - Salvage) / (Useful Life Years * 12)
     * - 'declining_balance': (NBV) * (Declining Rate / 12)
     * - 'units_of_production': (Cost - Salvage) / Total Expected Units * Hours Input
     *
     * Starts from audited cutover accumulated depreciation.
     * Never depreciates below salvage value.
     */
    public function postMonthlyDepreciation(
        FixedAssetRegister $asset,
        string $periodDate,
        int $userId,
        ?float $hoursConsumedThisMonth = null
    ): ?JournalEntry {
        return DB::transaction(function () use ($asset, $periodDate, $userId, $hoursConsumedThisMonth) {
            $cost = (float) $asset->historical_cost;
            $salvage = (float) $asset->salvage_value;
            $currentAccDepr = (float) $asset->current_accumulated_depreciation;

            // Depreciable base remaining before hitting salvage floor
            $maxAllowableDepreciation = max(0.0, round($cost - $salvage - $currentAccDepr, 2));

            if ($maxAllowableDepreciation <= 0.001) {
                return null; // Fully depreciated
            }

            $deprAmount = 0.0;

            if ($asset->depreciation_method === 'units_of_production') {
                if ($hoursConsumedThisMonth === null || $hoursConsumedThisMonth <= 0) {
                    throw new Exception("Units of Production depreciation for asset {$asset->asset_code} requires operating hours input.");
                }

                $totalUnits = (float) $asset->total_expected_units;
                if ($totalUnits <= 0) {
                    throw new Exception("Asset {$asset->asset_code} has invalid total expected units ({$totalUnits}).");
                }

                $ratePerUnit = ($cost - $salvage) / $totalUnits;
                $deprAmount = round($hoursConsumedThisMonth * $ratePerUnit, 2);
            } elseif ($asset->depreciation_method === 'declining_balance') {
                $annualRate = (float) $asset->declining_rate_percent / 100.0;
                $netBookValue = $asset->net_book_value;
                $deprAmount = round(($netBookValue * $annualRate) / 12.0, 2);
            } else {
                // Default: Straight-Line
                $usefulLifeYears = (float) $asset->useful_life_years;
                if ($usefulLifeYears <= 0) {
                    throw new Exception("Asset {$asset->asset_code} requires useful life in years for Straight-Line depreciation.");
                }
                $annualDepr = ($cost - $salvage) / $usefulLifeYears;
                $deprAmount = round($annualDepr / 12.0, 2);
            }

            // Cap depreciation at salvage value floor
            $deprAmount = min($deprAmount, $maxAllowableDepreciation);

            if ($deprAmount <= 0.001) {
                return null;
            }

            // Post balanced journal entry
            $entry = $this->journalEngine->createEntry([
                'entry_date'     => $periodDate,
                'reference_type' => 'FixedAssetDepreciation',
                'reference_id'   => $asset->id,
                'description'    => "Monthly Depreciation: {$asset->name} [{$asset->asset_code}] ({$asset->depreciation_method})",
                'created_by'     => $userId,
                'project_id'     => $asset->project_id,
                'cost_center_id' => $asset->cost_center_id,
                'site_id'        => $asset->site_id,
                'lines'          => [
                    [
                        'account_id'     => $asset->depreciation_expense_account_id,
                        'side'           => 'debit',
                        'amount'         => $deprAmount,
                        'description'    => "Depreciation expense for {$asset->name}",
                        'project_id'     => $asset->project_id,
                        'cost_center_id' => $asset->cost_center_id,
                        'site_id'        => $asset->site_id,
                    ],
                    [
                        'account_id'     => $asset->accumulated_depr_account_id,
                        'side'           => 'credit',
                        'amount'         => $deprAmount,
                        'description'    => "Accumulated depreciation reserve for {$asset->name}",
                        'project_id'     => $asset->project_id,
                        'cost_center_id' => $asset->cost_center_id,
                        'site_id'        => $asset->site_id,
                    ],
                ],
            ], approvedBy: $userId);

            // Update asset register
            $asset->current_accumulated_depreciation += $deprAmount;
            if ($hoursConsumedThisMonth) {
                $asset->cumulative_units_consumed += $hoursConsumedThisMonth;
            }
            $asset->save();

            return $entry;
        });
    }
}
