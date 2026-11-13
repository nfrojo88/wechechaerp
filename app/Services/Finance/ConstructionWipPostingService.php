<?php

namespace App\Services\Finance;

use App\Models\ChartOfAccount;
use App\Models\ConstructionContract;
use App\Models\ContractBudgetRevision;
use App\Models\JournalEntry;
use App\Services\Finance\JournalEngine;
use App\Exceptions\Finance\AmbiguousContractException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Exception;

class ConstructionWipPostingService
{
    protected JournalEngine $journalEngine;

    public function __construct(?JournalEngine $journalEngine = null)
    {
        $this->journalEngine = $journalEngine ?: new JournalEngine();
    }

    /**
     * Resolve and validate contract ID for a project.
     * Auto-resolves if project has exactly 1 active contract.
     * Throws AmbiguousContractException if project has multiple active contracts.
     *
     * @param int $projectId
     * @param int|null $contractId
     * @return int
     * @throws AmbiguousContractException|InvalidArgumentException
     */
    public function resolveContractId(int $projectId, ?int $contractId = null): int
    {
        if ($contractId !== null && $contractId > 0) {
            $contract = ConstructionContract::findOrFail($contractId);
            if ((int)$contract->project_id !== (int)$projectId) {
                throw new InvalidArgumentException("Contract #{$contractId} does not belong to Project #{$projectId}.");
            }
            return $contract->id;
        }

        $activeContracts = ConstructionContract::where('project_id', $projectId)
            ->whereIn('status', ['active', 'pending', 'approved', 'in_progress'])
            ->get();

        if ($activeContracts->isEmpty()) {
            throw new InvalidArgumentException("No active construction contract found for Project #{$projectId}.");
        }

        if ($activeContracts->count() > 1) {
            $ids = $activeContracts->pluck('id')->toArray();
            throw new AmbiguousContractException($projectId, $ids);
        }

        return $activeContracts->first()->id;
    }

    /**
     * Post SIV Store Material Issue directly into Construction Work-in-Progress (1260 WIP).
     * Enforces contract_id and project_id dimensions.
     *
     * DR 1260 Construction WIP (with contract_id, project_id, is_installed)
     * CR 1250 Inventory on Hand - Stores
     */
    public function postMaterialIssueToWip(
        ?int $contractId,
        int $projectId,
        float $amount,
        string $sivNumber,
        ?int $costCenterId = null,
        bool $isInstalled = true,
        int $userId = 1,
        ?string $entryDate = null
    ): JournalEntry {
        if ($amount <= 0) {
            throw new InvalidArgumentException("Material issue amount must be greater than zero.");
        }

        $resolvedContractId = $this->resolveContractId($projectId, $contractId);
        $contract = ConstructionContract::findOrFail($resolvedContractId);
        $wipAcc   = ChartOfAccount::where('code', '1260')->firstOrFail();
        $storeAcc = ChartOfAccount::where('code', '1250')->firstOrFail();

        $entryDate = $entryDate ?: Carbon::now()->toDateString();
        $desc = "Store Material Issue SIV #{$sivNumber} to Contract [{$contract->contract_number}]" . 
                ($isInstalled ? " (Installed/Incorporated)" : " (Uninstalled Site Stock)");

        $lines = [
            [
                'account_id'      => $wipAcc->id,
                'side'            => 'debit',
                'amount'          => $amount,
                'description'     => "Direct Material to WIP - SIV #{$sivNumber}",
                'contract_id'     => $contract->id,
                'project_id'      => $projectId,
                'cost_center_id'  => $costCenterId,
                'is_installed'    => $isInstalled,
                'exclude_from_poc'=> !$isInstalled,
            ],
            [
                'account_id'      => $storeAcc->id,
                'side'            => 'credit',
                'amount'          => $amount,
                'description'     => "Relief of stores inventory for SIV #{$sivNumber}",
                'project_id'      => $projectId,
                'cost_center_id'  => $costCenterId,
                'is_installed'    => $isInstalled,
                'exclude_from_poc'=> !$isInstalled,
            ],
        ];

        return $this->journalEngine->createEntry(
            'siv_material_issue',
            crc32($sivNumber),
            $desc,
            $lines,
            $userId,
            $userId,
            $entryDate
        );
    }

    /**
     * Post Direct Site Labor Costs to Construction WIP (1260).
     *
     * DR 1260 Construction WIP (contract_id, project_id)
     * CR 2251 Accrued Salaries & Wages (or 2006 Net Salaries)
     */
    public function postDirectLaborToWip(
        ?int $contractId,
        int $projectId,
        float $amount,
        string $payrollRef,
        ?int $costCenterId = null,
        int $userId = 1,
        ?string $entryDate = null
    ): JournalEntry {
        if ($amount <= 0) {
            throw new InvalidArgumentException("Direct labor amount must be greater than zero.");
        }

        $resolvedContractId = $this->resolveContractId($projectId, $contractId);
        $contract = ConstructionContract::findOrFail($resolvedContractId);
        $wipAcc   = ChartOfAccount::where('code', '1260')->firstOrFail();
        
        $creditAcc = ChartOfAccount::where('code', '2251')->first()
                  ?: ChartOfAccount::where('code', '2006')->firstOrFail();

        $entryDate = $entryDate ?: Carbon::now()->toDateString();

        $lines = [
            [
                'account_id'     => $wipAcc->id,
                'side'           => 'debit',
                'amount'         => $amount,
                'description'    => "Direct Site Labor Allocation [Ref: {$payrollRef}]",
                'contract_id'    => $contract->id,
                'project_id'     => $projectId,
                'cost_center_id' => $costCenterId,
            ],
            [
                'account_id'     => $creditAcc->id,
                'side'           => 'credit',
                'amount'         => $amount,
                'description'    => "Labor liability allocation [Ref: {$payrollRef}]",
                'project_id'     => $projectId,
                'cost_center_id' => $costCenterId,
            ],
        ];

        return $this->journalEngine->createEntry(
            'direct_labor_wip',
            crc32($payrollRef),
            "Direct Site Labor Allocation to Contract [{$contract->contract_number}]",
            $lines,
            $userId,
            $userId,
            $entryDate
        );
    }

    /**
     * Post Subcontractor Work Performed to Construction WIP (1260).
     *
     * DR 1260 Construction WIP (contract_id, project_id, party_type=subcontractor)
     * CR 2255 Accrued Subcontractor Costs (or 2005 Subcontractor Payables)
     */
    public function postSubcontractCostToWip(
        ?int $contractId,
        int $projectId,
        int $subcontractorId,
        float $amount,
        string $claimRef,
        ?int $costCenterId = null,
        int $userId = 1,
        ?string $entryDate = null
    ): JournalEntry {
        if ($amount <= 0) {
            throw new InvalidArgumentException("Subcontract cost must be greater than zero.");
        }

        $resolvedContractId = $this->resolveContractId($projectId, $contractId);
        $contract = ConstructionContract::findOrFail($resolvedContractId);
        $wipAcc   = ChartOfAccount::where('code', '1260')->firstOrFail();
        $subAcc   = ChartOfAccount::where('code', '2255')->first()
                 ?: ChartOfAccount::where('code', '2005')->firstOrFail();

        $entryDate = $entryDate ?: Carbon::now()->toDateString();

        $lines = [
            [
                'account_id'     => $wipAcc->id,
                'side'           => 'debit',
                'amount'         => $amount,
                'description'    => "Subcontractor Work Certified [Claim: {$claimRef}]",
                'contract_id'    => $contract->id,
                'project_id'     => $projectId,
                'cost_center_id' => $costCenterId,
                'party_type'     => 'subcontractor',
                'party_id'       => $subcontractorId,
            ],
            [
                'account_id'     => $subAcc->id,
                'side'           => 'credit',
                'amount'         => $amount,
                'description'    => "Subcontractor liability accrual [Claim: {$claimRef}]",
                'project_id'     => $projectId,
                'cost_center_id' => $costCenterId,
                'party_type'     => 'subcontractor',
                'party_id'       => $subcontractorId,
            ],
        ];

        return $this->journalEngine->createEntry(
            'subcontract_wip',
            crc32($claimRef),
            "Subcontractor Work Accrual to Contract [{$contract->contract_number}]",
            $lines,
            $userId,
            $userId,
            $entryDate
        );
    }

    /**
     * Post Internal Equipment / Plant Machinery Usage to Construction WIP (1260).
     *
     * DR 1260 Construction WIP (contract_id, project_id)
     * CR 1455 Accumulated Depreciation (or 5400/6900 Machine Rent Clearing)
     */
    public function postEquipmentCostToWip(
        ?int $contractId,
        int $projectId,
        int $assetAccountId,
        float $amount,
        string $logRef,
        ?int $costCenterId = null,
        int $userId = 1,
        ?string $entryDate = null
    ): JournalEntry {
        if ($amount <= 0) {
            throw new InvalidArgumentException("Equipment cost must be greater than zero.");
        }

        $resolvedContractId = $this->resolveContractId($projectId, $contractId);
        $contract = ConstructionContract::findOrFail($resolvedContractId);
        $wipAcc   = ChartOfAccount::where('code', '1260')->firstOrFail();
        $equipAcc = ChartOfAccount::findOrFail($assetAccountId);

        $entryDate = $entryDate ?: Carbon::now()->toDateString();

        $lines = [
            [
                'account_id'     => $wipAcc->id,
                'side'           => 'debit',
                'amount'         => $amount,
                'description'    => "Heavy Plant Usage Allocation [Log: {$logRef}]",
                'contract_id'    => $contract->id,
                'project_id'     => $projectId,
                'cost_center_id' => $costCenterId,
            ],
            [
                'account_id'     => $equipAcc->id,
                'side'           => 'credit',
                'amount'         => $amount,
                'description'    => "Equipment recovery/depreciation [Log: {$logRef}]",
                'project_id'     => $projectId,
                'cost_center_id' => $costCenterId,
            ],
        ];

        return $this->journalEngine->createEntry(
            'equipment_wip',
            crc32($logRef),
            "Equipment Plant Allocation to Contract [{$contract->contract_number}]",
            $lines,
            $userId,
            $userId,
            $entryDate
        );
    }

    /**
     * Log and formally approve a Contract Budget / ETC / Variation Revision.
     */
    public function recordBudgetRevision(
        int $contractId,
        string $revisionType,
        float $revisedBudgetCost,
        float $revisedContractValue,
        string $justification,
        int $userId,
        bool $autoApprove = false,
        ?int $approvedBy = null
    ): ContractBudgetRevision {
        $contract = ConstructionContract::findOrFail($contractId);

        $revisionCount = $contract->budgetRevisions()->count() + 1;
        $revisionNo = 'REV-' . str_pad($revisionCount, 3, '0', STR_PAD_LEFT);

        $revision = ContractBudgetRevision::create([
            'contract_id'             => $contract->id,
            'revision_no'             => $revisionNo,
            'revision_type'           => $revisionType,
            'previous_budget_cost'    => $contract->current_budget_cost,
            'revised_budget_cost'     => $revisedBudgetCost,
            'previous_contract_value' => $contract->contract_value_exc_vat,
            'revised_contract_value'  => $revisedContractValue,
            'justification'           => $justification,
            'is_approved'             => $autoApprove,
            'approved_by'             => $autoApprove ? ($approvedBy ?: $userId) : null,
            'approved_at'             => $autoApprove ? now() : null,
            'created_by'              => $userId,
        ]);

        if ($autoApprove) {
            $contract->update([
                'current_budget_cost'    => $revisedBudgetCost,
                'contract_value_exc_vat' => $revisedContractValue,
            ]);
        }

        return $revision;
    }
}
