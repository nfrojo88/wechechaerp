<?php

namespace App\Services\Finance;

use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\ClientIpc;
use App\Models\ClientIpcCollection;
use App\Models\ConstructionContract;
use App\Models\ContractRetentionRelease;
use App\Models\FinancialPeriod;
use App\Models\JournalEntry;
use App\Services\Finance\JournalEngine;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class IpcBillingPostingService
{
    protected JournalEngine $journalEngine;

    public function __construct(?JournalEngine $journalEngine = null)
    {
        $this->journalEngine = $journalEngine ?: new JournalEngine();
    }

    /**
     * Post a Certified Interim Payment Certificate (IPC) to General Ledger.
     * Strictly idempotent per IPC.
     * Enforces feature flag and VAT base sign-off gate.
     *
     * @param int $ipcId
     * @param int $userId
     * @param bool $force
     * @return JournalEntry
     * @throws RuntimeException|InvalidArgumentException
     */
    public function postIpc(int $ipcId, int $userId = 1, bool $force = false): JournalEntry
    {
        // 1. Feature Flag Check (Off by default)
        $enabled = (bool) config('finance.modules.ipc_billing', false);
        if (!$enabled && !$force) {
            throw new RuntimeException("IPC GL posting is disabled by feature flag 'finance.modules.ipc_billing'.");
        }

        $ipc = ClientIpc::with(['contract', 'period'])->findOrFail($ipcId);
        $contract = $ipc->contract;

        if (!$contract) {
            throw new InvalidArgumentException("IPC #{$ipc->id} is not linked to any construction contract.");
        }

        // 2. Gate: VAT Base Sign-Off Requirement
        $vatBaseStatus = $contract->vat_base_status ?? 'to_be_verified';
        if ($vatBaseStatus !== 'signed_off' && !$force) {
            throw new RuntimeException("IPC posting is blocked: Contract [{$contract->contract_number}] vat_base_status is '{$vatBaseStatus}'. Formal tax advisor sign-off required before GL posting.");
        }

        // 3. Gate: Certification Status Requirement
        if (!$ipc->isCertified() && !$force) {
            throw new RuntimeException("IPC [{$ipc->ipc_no}] is not certified (status: '{$ipc->certification_status}'). Official supervising engineer certification is strictly required.");
        }

        // 4. Idempotency Check
        if ($ipc->is_posted && $ipc->journal_entry_id) {
            $existingEntry = JournalEntry::find($ipc->journal_entry_id);
            if ($existingEntry) {
                return $existingEntry;
            }
        }

        $period = $ipc->period;
        $entryDate = $ipc->submission_date 
            ? Carbon::parse($ipc->submission_date)->toDateString() 
            : ($ipc->period_to ? Carbon::parse($ipc->period_to)->toDateString() : Carbon::now()->toDateString());
        $gross = (float)($ipc->gross_certified_amount > 0 ? $ipc->gross_certified_amount : $ipc->gross_amount);

        if ($gross <= 0.0) {
            throw new InvalidArgumentException("IPC [{$ipc->ipc_no}] gross certified amount must be strictly positive (ETB {$gross}).");
        }

        // 5. Retention Calculation
        $retentionRate = (float)($ipc->retention_rate_percent > 0 ? $ipc->retention_rate_percent : ($contract->retention_rate_percent ?: 5.00));
        $retentionAmount = round($gross * ($retentionRate / 100), 2);

        // Cap check if configured
        if ($contract->retention_cap_amount && (float)$contract->retention_cap_amount > 0) {
            $priorRetention = (float) ClientIpc::where('contract_id', $contract->id)
                ->where('is_posted', true)
                ->where('id', '!=', $ipc->id)
                ->sum('retention_amount');
            $maxAllowed = max(0.0, (float)$contract->retention_cap_amount - $priorRetention);
            $retentionAmount = min($retentionAmount, $maxAllowed);
        }

        // 6. Advance Recovery Calculation
        $advanceRecoveryRate = (float)($ipc->advance_recovery_rate_percent > 0 ? $ipc->advance_recovery_rate_percent : ($contract->advance_recovery_rate_percent ?: 20.00));
        $advancePaymentTotal = (float)($contract->advance_payment_amount ?? 0.0);
        $priorRecovered = (float) ClientIpc::where('contract_id', $contract->id)
            ->where('is_posted', true)
            ->where('id', '!=', $ipc->id)
            ->sum('advance_recovery_amount');
        
        $unrecoveredAdvance = max(0.0, $advancePaymentTotal - $priorRecovered);
        $advanceRecoveryAmount = 0.0;
        if ($unrecoveredAdvance > 0.0) {
            $advanceRecoveryAmount = min($unrecoveredAdvance, round($gross * ($advanceRecoveryRate / 100), 2));
        }

        // 7. VAT Base Determination (net_certified, full_certified, certified_less_advance_only)
        $vatBaseType = $contract->vat_base_type ?: ($ipc->vat_base_type ?: 'net_certified');
        $vatRate = (float)($ipc->vat_rate_percent > 0 ? $ipc->vat_rate_percent : ($contract->vat_rate_percent ?: 15.00));

        $vatBase = match ($vatBaseType) {
            'full_certified'               => $gross,
            'certified_less_advance_only'  => max(0.0, $gross - $advanceRecoveryAmount),
            default                        => max(0.0, $gross - $retentionAmount - $advanceRecoveryAmount), // 'net_certified'
        };

        $vatAmount = round($vatBase * ($vatRate / 100), 2);

        // 8. Net Certified Payable by Client (Accounts Receivable)
        $netPayableByClient = round(($gross - $retentionAmount - $advanceRecoveryAmount) + $vatAmount, 2);

        // 9. Resolve Authoritative Chart of Accounts
        $arAcc        = ChartOfAccount::where('code', '1200')->firstOrFail();
        $retentionAcc = ChartOfAccount::where('code', '1215')->firstOrFail();
        $advanceAcc   = ChartOfAccount::where('code', '2110')->firstOrFail();
        $assetAcc     = ChartOfAccount::where('code', '1210')->firstOrFail();
        $vatAcc       = ChartOfAccount::where('code', '2300')->first()
                     ?: ChartOfAccount::where('type', 'liability')->where('subtype', 'tax')->firstOrFail();

        // 10. Construct Double-Entry Journal Lines
        // Debits:
        // DR 1200 Accounts Receivable: Net due from client
        // DR 1215 Client Retention Receivable: Retained warranty defect float
        // DR 2110 Client Advance Payment Liability: Amortization of advance liability
        // Credits:
        // CR 1210 Contract Asset: Relief of IFRS 15 unbilled contract asset
        // CR 2300 Output VAT Payable: VAT payable to Ethiopian MOR
        $lines = [
            [
                'account_id'     => $arAcc->id,
                'side'           => 'debit',
                'amount'         => $netPayableByClient,
                'description'    => "IPC #{$ipc->ipc_number} Net Receivable [Contract {$contract->contract_number}]",
                'contract_id'    => $contract->id,
                'project_id'     => $contract->project_id,
                'cost_center_id' => $contract->cost_center_id,
            ],
        ];

        if ($retentionAmount > 0.0) {
            $lines[] = [
                'account_id'     => $retentionAcc->id,
                'side'           => 'debit',
                'amount'         => $retentionAmount,
                'description'    => "Retention Withheld by Client ({$retentionRate}%) — IPC #{$ipc->ipc_number}",
                'contract_id'    => $contract->id,
                'project_id'     => $contract->project_id,
                'cost_center_id' => $contract->cost_center_id,
            ];
        }

        if ($advanceRecoveryAmount > 0.0) {
            $lines[] = [
                'account_id'     => $advanceAcc->id,
                'side'           => 'debit',
                'amount'         => $advanceRecoveryAmount,
                'description'    => "Advance Recovery ({$advanceRecoveryRate}%) — IPC #{$ipc->ipc_number}",
                'contract_id'    => $contract->id,
                'project_id'     => $contract->project_id,
                'cost_center_id' => $contract->cost_center_id,
            ];
        }

        $lines[] = [
            'account_id'     => $assetAcc->id,
            'side'           => 'credit',
            'amount'         => $gross,
            'description'    => "Relief of Contract Asset via Certified IPC #{$ipc->ipc_number} [Contract {$contract->contract_number}]",
            'contract_id'    => $contract->id,
            'project_id'     => $contract->project_id,
            'cost_center_id' => $contract->cost_center_id,
        ];

        if ($vatAmount > 0.0) {
            $lines[] = [
                'account_id'     => $vatAcc->id,
                'side'           => 'credit',
                'amount'         => $vatAmount,
                'description'    => "Output VAT 15% on IPC #{$ipc->ipc_number} [Base: {$vatBaseType}]",
                'contract_id'    => $contract->id,
                'project_id'     => $contract->project_id,
                'cost_center_id' => $contract->cost_center_id,
            ];
        }

        // 11. Post Journal Entry via JournalEngine
        $desc = "Certified IPC #{$ipc->ipc_number} Billing for Contract [{$contract->contract_number}] ({$ipc->ipc_no})";
        $journalEntry = $this->journalEngine->createEntry(
            'ipc_billing',
            $ipc->id,
            $desc,
            $lines,
            $userId,
            $userId,
            $entryDate
        );

        // 12. Update IPC Record
        $ipc->update([
            'gross_certified_amount'        => $gross,
            'retention_rate_percent'        => $retentionRate,
            'retention_amount'              => $retentionAmount,
            'advance_recovery_rate_percent' => $advanceRecoveryRate,
            'advance_recovery_amount'       => $advanceRecoveryAmount,
            'vat_base_type'                 => $vatBaseType,
            'vat_base_status'               => $vatBaseStatus,
            'vat_rate_percent'              => $vatRate,
            'vat_amount'                    => $vatAmount,
            'net_certified_payable'         => $netPayableByClient,
            'is_posted'                     => true,
            'journal_entry_id'              => $journalEntry->id,
        ]);

        return $journalEntry;
    }

    /**
     * Record and Post Client Collection for an IPC with Client Withholding Tax.
     * Enforces strict WHT receipt number requirement.
     *
     * @param int $ipcId
     * @param int $bankAccountId
     * @param float $grossCollectedAmount
     * @param float $clientWhtRatePercent
     * @param string $whtReceiptNumber
     * @param string|null $collectionDate
     * @param int $userId
     * @param bool $force
     * @return ClientIpcCollection
     * @throws InvalidArgumentException|RuntimeException
     */
    public function postCollection(
        int $ipcId,
        int $bankAccountId,
        float $grossCollectedAmount,
        float $clientWhtRatePercent = 2.00,
        string $whtReceiptNumber = '',
        ?string $collectionDate = null,
        int $userId = 1,
        bool $force = false
    ): ClientIpcCollection {
        $enabled = (bool) config('finance.modules.ipc_billing', false);
        if (!$enabled && !$force) {
            throw new RuntimeException("IPC GL posting is disabled by feature flag 'finance.modules.ipc_billing'.");
        }

        $whtReceiptNumber = trim($whtReceiptNumber);
        if (empty($whtReceiptNumber)) {
            throw new InvalidArgumentException("Client withholding tax deduction strictly requires an official withholding tax receipt number.");
        }

        $ipc = ClientIpc::with('contract')->findOrFail($ipcId);
        $bankAccount = BankAccount::findOrFail($bankAccountId);
        $collectionDate = $collectionDate ?: Carbon::now()->toDateString();

        // Calculate WHT on pre-VAT base
        // In Ethiopian contracts, client withholds 2% (or 3%) on the pre-VAT base
        $vatRate = (float)($ipc->vat_rate_percent ?: 15.00);
        $preVatBase = round($grossCollectedAmount / (1 + ($vatRate / 100)), 2);
        $whtAmount = round($preVatBase * ($clientWhtRatePercent / 100), 2);
        $netBankAmount = round($grossCollectedAmount - $whtAmount, 2);

        // Resolve Accounts
        $bankCoa = ChartOfAccount::find($bankAccount->coa_id) 
                ?: ChartOfAccount::where('code', '1020')->firstOrFail();
        $whtReceivableAcc = ChartOfAccount::where('code', '1300')->firstOrFail();
        $arAcc = ChartOfAccount::where('code', '1200')->firstOrFail();

        // Construct Double-Entry Journal Lines:
        // DR Bank Account: Net Cash Received
        // DR 1300 Prepaid / WHT Receivable: Client WHT Deducted
        // CR 1200 Accounts Receivable: Full Gross IPC Settlement
        $lines = [
            [
                'account_id'     => $bankCoa->id,
                'side'           => 'debit',
                'amount'         => $netBankAmount,
                'description'    => "Bank Collection for IPC #{$ipc->ipc_number} [Net of WHT]",
                'contract_id'    => $ipc->contract_id,
                'project_id'     => $ipc->project_id,
            ],
            [
                'account_id'     => $whtReceivableAcc->id,
                'side'           => 'debit',
                'amount'         => $whtAmount,
                'description'    => "Client WHT Receivable ({$clientWhtRatePercent}%) — Receipt #{$whtReceiptNumber} [IPC #{$ipc->ipc_number}]",
                'contract_id'    => $ipc->contract_id,
                'project_id'     => $ipc->project_id,
            ],
            [
                'account_id'     => $arAcc->id,
                'side'           => 'credit',
                'amount'         => $grossCollectedAmount,
                'description'    => "Settlement of Accounts Receivable — IPC #{$ipc->ipc_number}",
                'contract_id'    => $ipc->contract_id,
                'project_id'     => $ipc->project_id,
            ],
        ];

        $desc = "Client Payment Collection for IPC #{$ipc->ipc_number} (WHT Receipt: {$whtReceiptNumber})";
        $journalEntry = $this->journalEngine->createEntry(
            'ipc_collection',
            $ipc->id,
            $desc,
            $lines,
            $userId,
            $userId,
            $collectionDate
        );

        $collection = ClientIpcCollection::create([
            'client_ipc_id'           => $ipc->id,
            'bank_account_id'         => $bankAccount->id,
            'collection_date'         => $collectionDate,
            'gross_collected_amount'  => $grossCollectedAmount,
            'client_wht_rate_percent' => $clientWhtRatePercent,
            'client_wht_amount'       => $whtAmount,
            'wht_receipt_number'      => $whtReceiptNumber,
            'net_bank_amount'         => $netBankAmount,
            'is_posted'               => true,
            'journal_entry_id'        => $journalEntry->id,
            'created_by'              => $userId,
        ]);

        return $collection;
    }

    /**
     * Post Retention Release upon Substantial Completion or Final Defect Liability End.
     *
     * @param int $contractId
     * @param string $releaseStage 'substantial_completion' or 'final_completion'
     * @param float $releaseAmount
     * @param string $certificateReference
     * @param string|null $releaseDate
     * @param int $userId
     * @param bool $force
     * @return ContractRetentionRelease
     */
    public function postRetentionRelease(
        int $contractId,
        string $releaseStage,
        float $releaseAmount,
        string $certificateReference,
        ?string $releaseDate = null,
        int $userId = 1,
        bool $force = false
    ): ContractRetentionRelease {
        $enabled = (bool) config('finance.modules.ipc_billing', false);
        if (!$enabled && !$force) {
            throw new RuntimeException("IPC GL posting is disabled by feature flag 'finance.modules.ipc_billing'.");
        }

        $contract = ConstructionContract::findOrFail($contractId);
        $releaseDate = $releaseDate ?: Carbon::now()->toDateString();

        if ($releaseAmount <= 0.0) {
            throw new InvalidArgumentException("Retention release amount must be strictly positive (ETB {$releaseAmount}).");
        }

        // Check total retained balance
        $retentionAcc = ChartOfAccount::where('code', '1215')->firstOrFail();
        $arAcc        = ChartOfAccount::where('code', '1200')->firstOrFail();

        // Double Entry:
        // DR 1200 Accounts Receivable: Retention billed back to client for disbursement
        // CR 1215 Client Retention Receivable: Relief of retained warranty asset
        $lines = [
            [
                'account_id'     => $arAcc->id,
                'side'           => 'debit',
                'amount'         => $releaseAmount,
                'description'    => "Retention Released for Billing — Stage [{$releaseStage}] (Cert: {$certificateReference})",
                'contract_id'    => $contract->id,
                'project_id'     => $contract->project_id,
            ],
            [
                'account_id'     => $retentionAcc->id,
                'side'           => 'credit',
                'amount'         => $releaseAmount,
                'description'    => "Relief of Client Retention Asset — Stage [{$releaseStage}] [Contract {$contract->contract_number}]",
                'contract_id'    => $contract->id,
                'project_id'     => $contract->project_id,
            ],
        ];

        $desc = "Retention Release [Stage: {$releaseStage}] for Contract [{$contract->contract_number}] (Ref: {$certificateReference})";
        $journalEntry = $this->journalEngine->createEntry(
            'retention_release',
            $contract->id,
            $desc,
            $lines,
            $userId,
            $userId,
            $releaseDate
        );

        $release = ContractRetentionRelease::create([
            'contract_id'           => $contract->id,
            'release_stage'         => $releaseStage,
            'release_amount'        => $releaseAmount,
            'release_date'          => $releaseDate,
            'certificate_reference' => $certificateReference,
            'is_posted'             => true,
            'journal_entry_id'      => $journalEntry->id,
            'approved_by'           => $userId,
            'created_by'            => $userId,
        ]);

        return $release;
    }
}