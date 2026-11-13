<?php

namespace App\Services\Finance;

use App\Models\ChartOfAccount;
use App\Models\ConstructionContract;
use App\Models\ContractBudgetRevision;
use App\Models\FinancialPeriod;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\RevenueRecognitionRun;
use App\Services\Finance\JournalEngine;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class RevenueRecognitionService
{
    protected JournalEngine $journalEngine;

    public function __construct(?JournalEngine $journalEngine = null)
    {
        $this->journalEngine = $journalEngine ?: new JournalEngine();
    }

    /**
     * Compute Percentage of Completion (PoC) using input cost-to-cost method.
     * Excludes uninstalled site stock per IFRS 15 / IAS 11 principles.
     * Respects approved variations only (unapproved revisions excluded).
     *
     * @param int $contractId
     * @param string|null $asOfDate
     * @return array
     */
    public function calculatePoc(int $contractId, ?string $asOfDate = null): array
    {
        $contract = ConstructionContract::findOrFail($contractId);
        $asOfDate = $asOfDate ?: Carbon::now()->toDateString();

        // 1. Contract Price: Base contract value PLUS only APPROVED variations
        $approvedVariations = (float) ContractBudgetRevision::where('contract_id', $contract->id)
            ->where('is_approved', true)
            ->whereIn('revision_type', ['scope_variation', 'price_escalation', 'claims'])
            ->sum(DB::raw('revised_contract_value - previous_contract_value'));

        $contractPrice = round((float)$contract->contract_value_exc_vat + max(0.0, $approvedVariations), 2);

        // 2. Budget at Completion (BAC)
        $bac = (float)$contract->current_budget_cost;
        if ($bac <= 0.0) {
            $bac = (float)$contract->original_budget_cost;
        }

        // 3. Query Incurred WIP Debits to Account 1260
        $wipAcc = ChartOfAccount::where('code', '1260')->first();
        $wipAccountId = $wipAcc ? $wipAcc->id : 0;

        $incurredQuery = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->where('jel.contract_id', $contract->id)
            ->where('jel.account_id', $wipAccountId)
            ->where('jel.side', 'debit')
            ->where('je.status', 'posted')
            ->where('je.entry_date', '<=', $asOfDate);

        // Fetch all qualifying vs uninstalled lines
        $wipLines = $incurredQuery->select(
            'jel.amount',
            'jel.is_installed',
            'jel.exclude_from_poc',
            'jel.description'
        )->get();

        $qualifyingIncurredCost = 0.0;
        $uninstalledMaterialCost = 0.0;

        foreach ($wipLines as $line) {
            $isUninstalled = (isset($line->is_installed) && !$line->is_installed)
                || (isset($line->exclude_from_poc) && $line->exclude_from_poc)
                || str_contains(strtolower((string)$line->description), 'uninstalled');

            if ($isUninstalled) {
                $uninstalledMaterialCost += (float)$line->amount;
            } else {
                $qualifyingIncurredCost += (float)$line->amount;
            }
        }

        $qualifyingIncurredCost = round($qualifyingIncurredCost, 2);
        $uninstalledMaterialCost = round($uninstalledMaterialCost, 2);

        // 4. Percentage of Completion (PoC)
        $poc = 0.0;
        if ($bac > 0.0) {
            $poc = min(1.0, round($qualifyingIncurredCost / $bac, 4));
        }

        // 5. Cumulative Recognised Revenue
        $cumulativeRevenue = round($contractPrice * $poc, 2);

        // 6. Previously Recognised Revenue from prior posted runs
        $previouslyRecognised = (float) RevenueRecognitionRun::where('contract_id', $contract->id)
            ->where('is_posted', true)
            ->where('as_of_date', '<', $asOfDate)
            ->sum('catch_up_revenue');

        // 7. Catch-up Revenue for this period
        $catchUpRevenue = round($cumulativeRevenue - $previouslyRecognised, 2);

        // 8. Cumulative Billed / Advances for Contract-Level Netting
        $contractAssetAcc = ChartOfAccount::where('code', '1210')->first();
        $arAccount = ChartOfAccount::where('code', '1200')->first();
        $advanceAccount = ChartOfAccount::where('code', '2110')->first();

        $cumulativeBilled = 0.0;
        // Billed certified works relieve Contract Asset 1210 on the credit side
        if ($contractAssetAcc) {
            $cumulativeBilled = (float) DB::table('journal_entry_lines as jel')
                ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
                ->where('jel.contract_id', $contract->id)
                ->where('jel.account_id', $contractAssetAcc->id)
                ->where('jel.side', 'credit')
                ->where('je.status', 'posted')
                ->where('je.entry_date', '<=', $asOfDate)
                ->sum('jel.amount');
        }

        if ($cumulativeBilled == 0.0 && \Illuminate\Support\Facades\Schema::hasTable('client_ipcs')) {
            $cumulativeBilled = (float) DB::table('client_ipcs')
                ->where('contract_id', $contract->id)
                ->where('is_posted', true)
                ->where('period_to', '<=', $asOfDate)
                ->sum('gross_certified_amount');
        }

        if ($cumulativeBilled == 0.0 && $arAccount) {
            $cumulativeBilled = (float) DB::table('journal_entry_lines as jel')
                ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
                ->where('jel.contract_id', $contract->id)
                ->where('jel.account_id', $arAccount->id)
                ->where('jel.side', 'debit')
                ->where('je.status', 'posted')
                ->where('je.entry_date', '<=', $asOfDate)
                ->sum('jel.amount');
        }

        if ($cumulativeBilled == 0.0 && $advanceAccount) {
            $cumulativeBilled = (float) DB::table('journal_entry_lines as jel')
                ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
                ->where('jel.contract_id', $contract->id)
                ->where('jel.account_id', $advanceAccount->id)
                ->where('jel.side', 'credit')
                ->where('je.status', 'posted')
                ->where('je.entry_date', '<=', $asOfDate)
                ->sum('jel.amount');
        }

        // Contract-Level Netting (Asset vs Liability)
        $netAsset = 0.0;
        $netLiability = 0.0;
        if ($cumulativeRevenue >= $cumulativeBilled) {
            $netAsset = round($cumulativeRevenue - $cumulativeBilled, 2);
        } else {
            $netLiability = round($cumulativeBilled - $cumulativeRevenue, 2);
        }

        return [
            'contract_id'                   => $contract->id,
            'contract_number'               => $contract->contract_number,
            'contract_price'                => $contractPrice,
            'approved_variations'           => $approvedVariations,
            'current_budget_cost'           => $bac,
            'qualifying_incurred_cost'      => $qualifyingIncurredCost,
            'uninstalled_material_cost'     => $uninstalledMaterialCost,
            'poc_percentage'                => $poc,
            'cumulative_revenue'            => $cumulativeRevenue,
            'previously_recognised_revenue' => $previouslyRecognised,
            'catch_up_revenue'              => $catchUpRevenue,
            'cumulative_billed'             => round($cumulativeBilled, 2),
            'net_asset_amount'              => $netAsset,
            'net_liability_amount'          => $netLiability,
            'as_of_date'                    => $asOfDate,
        ];
    }

    /**
     * Post Revenue Recognition Catch-Up Journal Entry for a Contract and Period.
     * Strictly idempotent per (contract_id, financial_period_id).
     * Enforces feature flag.
     *
     * @param int $contractId
     * @param int $financialPeriodId
     * @param int $userId
     * @param bool $force
     * @return JournalEntry
     * @throws RuntimeException|InvalidArgumentException
     */
    public function recognizeRevenue(
        int $contractId,
        int $financialPeriodId,
        int $userId = 1,
        bool $force = false
    ): JournalEntry {
        // 1. Single Authoritative Feature Flag Check (Off by default)
        $enabled = (bool) config('finance.modules.revenue_recognition', false);

        if (!$enabled && !$force) {
            throw new RuntimeException("Revenue recognition GL posting is disabled by feature flag 'finance.modules.revenue_recognition'.");
        }

        // 2. Check Idempotency per Contract & Financial Period
        $existingRun = RevenueRecognitionRun::where('contract_id', $contractId)
            ->where('financial_period_id', $financialPeriodId)
            ->first();

        if ($existingRun && $existingRun->is_posted && $existingRun->journal_entry_id) {
            $existingEntry = JournalEntry::find($existingRun->journal_entry_id);
            if ($existingEntry) {
                return $existingEntry; // Idempotent return without duplicate posting
            }
        }

        $contract = ConstructionContract::findOrFail($contractId);
        $period   = FinancialPeriod::findOrFail($financialPeriodId);
        $asOfDate = $period->end_date ? Carbon::parse($period->end_date)->toDateString() : Carbon::now()->toDateString();

        // 3. Compute PoC & Catch-Up Revenue
        $pocData = $this->calculatePoc($contractId, $asOfDate);
        $catchUpRev = (float)$pocData['catch_up_revenue'];

        if ($catchUpRev <= 0.0) {
            throw new InvalidArgumentException("Catch-up revenue for Contract [{$contract->contract_number}] in period [{$period->code}] is zero or negative (ETB {$catchUpRev}). Nothing to recognise.");
        }

        // 4. Resolve Required Chart of Accounts
        $contractAssetAcc = ChartOfAccount::where('code', '1210')->firstOrFail();
        $revenueAcc       = ChartOfAccount::where('code', '4100')->first()
                         ?: ChartOfAccount::where('type', 'revenue')->firstOrFail();
        $cosAcc           = ChartOfAccount::where('code', '5010')->first()
                         ?: ChartOfAccount::where('type', 'expense')->where('subtype', 'cost_of_sale')->firstOrFail();
        $wipAcc           = ChartOfAccount::where('code', '1260')->firstOrFail();

        // Calculate incremental qualifying cost for this period to relieve WIP to Cost of Sales
        $priorIncurredCost = (float) RevenueRecognitionRun::where('contract_id', $contract->id)
            ->where('is_posted', true)
            ->where('financial_period_id', '!=', $period->id)
            ->sum('qualifying_incurred_cost');
        
        $incrementalCost = max(0.0, round($pocData['qualifying_incurred_cost'] - $priorIncurredCost, 2));

        // 5. Construct Double-Entry Journal Lines
        // Leg 1: Revenue Recognition
        // DR 1210 Contract Asset (IFRS 15 Unbilled Revenue)
        // CR 4100 Construction Contract Revenue
        $lines = [
            [
                'account_id'      => $contractAssetAcc->id,
                'side'            => 'debit',
                'amount'          => $catchUpRev,
                'description'     => "IFRS 15 Unbilled Contract Asset — PoC " . ($pocData['poc_percentage'] * 100) . "% [Period {$period->code}]",
                'contract_id'     => $contract->id,
                'project_id'      => $contract->project_id,
                'cost_center_id'  => $contract->cost_center_id,
            ],
            [
                'account_id'      => $revenueAcc->id,
                'side'            => 'credit',
                'amount'          => $catchUpRev,
                'description'     => "Construction Contract Revenue Recognised — PoC " . ($pocData['poc_percentage'] * 100) . "% [Period {$period->code}]",
                'contract_id'     => $contract->id,
                'project_id'      => $contract->project_id,
                'cost_center_id'  => $contract->cost_center_id,
            ],
        ];

        // Leg 2: Cost of Sales Matching (if incremental WIP cost incurred)
        // DR 5010 Construction Cost of Sales
        // CR 1260 Construction WIP Relief
        if ($incrementalCost > 0.0) {
            $lines[] = [
                'account_id'      => $cosAcc->id,
                'side'            => 'debit',
                'amount'          => $incrementalCost,
                'description'     => "Construction Cost of Sales — Period {$period->code} Incurred Costs",
                'contract_id'     => $contract->id,
                'project_id'      => $contract->project_id,
                'cost_center_id'  => $contract->cost_center_id,
            ];
            $lines[] = [
                'account_id'      => $wipAcc->id,
                'side'            => 'credit',
                'amount'          => $incrementalCost,
                'description'     => "Relief of Construction WIP to COS — Period {$period->code}",
                'contract_id'     => $contract->id,
                'project_id'      => $contract->project_id,
                'cost_center_id'  => $contract->cost_center_id,
            ];
        }

        // 6. Post Journal via JournalEngine
        $desc = "IFRS 15 Revenue Recognition & Cost Matching for Contract [{$contract->contract_number}] — Period {$period->code}";
        $journalEntry = $this->journalEngine->createEntry(
            'rev_rec_poc',
            $contract->id,
            $desc,
            $lines,
            $userId,
            $userId,
            $asOfDate
        );

        // 7. Record / Update RevenueRecognitionRun Record
        if ($existingRun) {
            $existingRun->update([
                'as_of_date'                    => $asOfDate,
                'contract_price'                => $pocData['contract_price'],
                'current_budget_cost'           => $pocData['current_budget_cost'],
                'qualifying_incurred_cost'      => $pocData['qualifying_incurred_cost'],
                'uninstalled_material_cost'     => $pocData['uninstalled_material_cost'],
                'poc_percentage'                => $pocData['poc_percentage'],
                'cumulative_revenue'            => $pocData['cumulative_revenue'],
                'previously_recognised_revenue' => $pocData['previously_recognised_revenue'],
                'catch_up_revenue'              => $catchUpRev,
                'cumulative_billed'             => $pocData['cumulative_billed'],
                'net_asset_amount'              => $pocData['net_asset_amount'],
                'net_liability_amount'          => $pocData['net_liability_amount'],
                'journal_entry_id'              => $journalEntry->id,
                'is_posted'                     => true,
            ]);
        } else {
            RevenueRecognitionRun::create([
                'contract_id'                   => $contract->id,
                'financial_period_id'           => $period->id,
                'as_of_date'                    => $asOfDate,
                'contract_price'                => $pocData['contract_price'],
                'current_budget_cost'           => $pocData['current_budget_cost'],
                'qualifying_incurred_cost'      => $pocData['qualifying_incurred_cost'],
                'uninstalled_material_cost'     => $pocData['uninstalled_material_cost'],
                'poc_percentage'                => $pocData['poc_percentage'],
                'cumulative_revenue'            => $pocData['cumulative_revenue'],
                'previously_recognised_revenue' => $pocData['previously_recognised_revenue'],
                'catch_up_revenue'              => $catchUpRev,
                'cumulative_billed'             => $pocData['cumulative_billed'],
                'net_asset_amount'              => $pocData['net_asset_amount'],
                'net_liability_amount'          => $pocData['net_liability_amount'],
                'journal_entry_id'              => $journalEntry->id,
                'is_posted'                     => true,
                'created_by'                    => $userId,
            ]);
        }

        return $journalEntry;
    }
}
