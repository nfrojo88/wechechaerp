<?php

namespace App\Services\Finance;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Services\Finance\JournalEngine;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TaxRemittanceService
{
    protected JournalEngine $journalEngine;

    public function __construct(?JournalEngine $journalEngine = null)
    {
        $this->journalEngine = $journalEngine ?: new JournalEngine();
    }

    /**
     * Post authoritative monthly VAT remittance offsetting Output VAT (2300) with Input VAT (1230).
     * If Output > Input: Disburses net difference via bank.
     * If Input >= Output: Zero net cash; offsets Output VAT completely; remainder carries forward in 1230.
     */
    public function postVatRemittance(
        float $outputVatAmount,
        float $inputVatClaimedAmount,
        int $bankAccountId,
        int $userId = 1,
        ?string $entryDate = null,
        ?string $returnPeriod = null
    ): array {
        if ($outputVatAmount <= 0) {
            throw new InvalidArgumentException("Output VAT amount must be greater than zero.");
        }

        $vatPayableAcc    = ChartOfAccount::where('code', '2300')->firstOrFail();
        $vatReceivableAcc = ChartOfAccount::where('code', '1230')->firstOrFail();
        $bankAcc          = ChartOfAccount::findOrFail($bankAccountId);

        $offsetAmount = min($outputVatAmount, $inputVatClaimedAmount);
        $netCashDue   = round($outputVatAmount - $offsetAmount, 2);
        $carryforward = round(max(0.00, $inputVatClaimedAmount - $outputVatAmount), 2);

        $lines = [];

        // 1. Debit Output VAT to clear liability
        $lines[] = [
            'account_id'  => $vatPayableAcc->id,
            'side'        => 'debit',
            'amount'      => $outputVatAmount,
            'description' => "Monthly VAT Return for {$returnPeriod} (Gross Output VAT)",
        ];

        // 2. Credit Input VAT for the offset amount claimed
        if ($offsetAmount > 0) {
            $lines[] = [
                'account_id'  => $vatReceivableAcc->id,
                'side'        => 'credit',
                'amount'      => $offsetAmount,
                'description' => "Input VAT Credit Claimed against Output VAT",
            ];
        }

        // 3. Credit Bank for net cash remitted to ERCA (if any)
        if ($netCashDue > 0) {
            $lines[] = [
                'account_id'  => $bankAcc->id,
                'side'        => 'credit',
                'amount'      => $netCashDue,
                'description' => "Net VAT Remittance wire to Ethiopian Ministry of Revenue",
            ];
        }

        $entry = $this->journalEngine->createEntry(
            'vat_remittance',
            crc32('vat_return_' . ($returnPeriod ?: now()->format('Y_m'))),
            "VAT Return Remittance {$returnPeriod}",
            $lines,
            $userId,
            $userId,
            $entryDate ?: now()->toDateString()
        );

        return [
            'journal_entry'       => $entry,
            'output_vat'          => $outputVatAmount,
            'input_vat_claimed'   => $offsetAmount,
            'net_cash_remitted'   => $netCashDue,
            'vat_carryforward'    => $carryforward,
        ];
    }

    /**
     * Post Withholding Tax Remittance to ERCA.
     * DR 2600 WHT Payable / CR Bank
     */
    public function postWhtRemittance(
        float $whtAmount,
        int $bankAccountId,
        int $userId = 1,
        ?string $entryDate = null,
        ?string $returnPeriod = null
    ): JournalEntry {
        if ($whtAmount <= 0) {
            throw new InvalidArgumentException("WHT amount must be positive.");
        }

        $whtAcc  = ChartOfAccount::where('code', '2600')->firstOrFail();
        $bankAcc = ChartOfAccount::findOrFail($bankAccountId);

        $lines = [
            [
                'account_id'  => $whtAcc->id,
                'side'        => 'debit',
                'amount'      => $whtAmount,
                'description' => "Withholding Tax Remittance for {$returnPeriod} to ERCA",
            ],
            [
                'account_id'  => $bankAcc->id,
                'side'        => 'credit',
                'amount'      => $whtAmount,
                'description' => "Bank payment for WHT remittance",
            ],
        ];

        return $this->journalEngine->createEntry(
            'wht_remittance',
            crc32('wht_return_' . ($returnPeriod ?: now()->format('Y_m'))),
            "Withholding Tax Remittance {$returnPeriod}",
            $lines,
            $userId,
            $userId,
            $entryDate ?: now()->toDateString()
        );
    }
}
