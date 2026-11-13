<?php

namespace App\Services\Finance;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Services\Finance\JournalEngine;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProcurementGlPostingService
{
    protected JournalEngine $journalEngine;

    public function __construct(?JournalEngine $journalEngine = null)
    {
        $this->journalEngine = $journalEngine ?: new JournalEngine();
    }

    /**
     * Step 1: Goods Receipt Note (GRN) Intake into Store.
     * DR 1250 Inventory on Hand
     * CR 2003 Goods Received Not Invoiced (GRNI)
     */
    public function postGoodsReceipt(
        string $grnNo,
        float $poCost,
        int $supplierId,
        ?int $projectId = null,
        ?int $siteId = null,
        ?int $costCenterId = null,
        int $userId = 1,
        ?string $entryDate = null
    ): JournalEntry {
        if ($poCost <= 0) {
            throw new InvalidArgumentException("PO cost must be greater than zero.");
        }

        $invAcc  = ChartOfAccount::where('code', '1250')->firstOrFail();
        $grniAcc = ChartOfAccount::where('code', '2003')->firstOrFail();

        $lines = [
            [
                'account_id'     => $invAcc->id,
                'side'           => 'debit',
                'amount'         => $poCost,
                'description'    => "Store Receipt GRN #{$grnNo}",
                'project_id'     => $projectId,
                'site_id'        => $siteId,
                'cost_center_id' => $costCenterId,
                'party_type'     => 'supplier',
                'party_id'       => $supplierId,
            ],
            [
                'account_id'     => $grniAcc->id,
                'side'           => 'credit',
                'amount'         => $poCost,
                'description'    => "GRNI Accrual for GRN #{$grnNo}",
                'party_type'     => 'supplier',
                'party_id'       => $supplierId,
            ],
        ];

        return $this->journalEngine->createEntry(
            'grn_receipt',
            crc32($grnNo),
            "Store Intake GRN #{$grnNo}",
            $lines,
            $userId,
            $userId,
            $entryDate ?: now()->toDateString()
        );
    }

    /**
     * Step 2: Vendor Invoice Matching.
     * DR 2003 Goods Received Not Invoiced (clearing GRNI)
     * DR 1230 Input VAT Receivable (15% VAT on base cost before VAT)
     * CR 2001 Accounts Payable (Gross payable to supplier)
     */
    public function postVendorInvoiceMatch(
        string $invoiceNo,
        string $grnNo,
        float $baseAmountBeforeVat,
        float $vatAmount,
        int $supplierId,
        int $userId = 1,
        ?string $entryDate = null
    ): JournalEntry {
        if ($baseAmountBeforeVat <= 0) {
            throw new InvalidArgumentException("Base invoice amount must be positive.");
        }

        $grossPayable = round($baseAmountBeforeVat + $vatAmount, 2);

        $grniAcc = ChartOfAccount::where('code', '2003')->firstOrFail();
        $vatAcc  = ChartOfAccount::where('code', '1230')->firstOrFail();
        $apAcc   = ChartOfAccount::where('code', '2001')->firstOrFail();

        $lines = [
            [
                'account_id'  => $grniAcc->id,
                'side'        => 'debit',
                'amount'      => $baseAmountBeforeVat,
                'description' => "Clearing GRNI for GRN #{$grnNo} on Invoice #{$invoiceNo}",
                'party_type'  => 'supplier',
                'party_id'    => $supplierId,
            ],
            [
                'account_id'  => $apAcc->id,
                'side'        => 'credit',
                'amount'      => $grossPayable,
                'description' => "Vendor Payable Invoice #{$invoiceNo}",
                'party_type'  => 'supplier',
                'party_id'    => $supplierId,
            ],
        ];

        if ($vatAmount > 0) {
            // Insert VAT line
            array_splice($lines, 1, 0, [[
                'account_id'  => $vatAcc->id,
                'side'        => 'debit',
                'amount'      => $vatAmount,
                'description' => "Input VAT (15%) on Invoice #{$invoiceNo}",
                'party_type'  => 'supplier',
                'party_id'    => $supplierId,
            ]]);
        }

        return $this->journalEngine->createEntry(
            'vendor_invoice',
            crc32($invoiceNo),
            "Vendor Tax Invoice Match #{$invoiceNo} (GRN #{$grnNo})",
            $lines,
            $userId,
            $userId,
            $entryDate ?: now()->toDateString()
        );
    }

    /**
     * Step 3: Store Issue Voucher (SIV) to Site Project.
     * DR 1260 Construction Work-in-Progress (WIP) by Project
     * CR 1250 Inventory on Hand — Stores
     */
    public function postStoreIssue(
        string $sivNo,
        float $materialCost,
        int $projectId,
        ?int $siteId = null,
        ?int $costCenterId = null,
        int $userId = 1,
        ?string $entryDate = null
    ): JournalEntry {
        if ($materialCost <= 0) {
            throw new InvalidArgumentException("Material cost must be positive.");
        }

        $wipAcc = ChartOfAccount::where('code', '1260')->firstOrFail();
        $invAcc = ChartOfAccount::where('code', '1250')->firstOrFail();

        $lines = [
            [
                'account_id'     => $wipAcc->id,
                'side'           => 'debit',
                'amount'         => $materialCost,
                'description'    => "Store Issue Voucher SIV #{$sivNo} to Project #{$projectId}",
                'project_id'     => $projectId,
                'site_id'        => $siteId,
                'cost_center_id' => $costCenterId,
            ],
            [
                'account_id'     => $invAcc->id,
                'side'           => 'credit',
                'amount'         => $materialCost,
                'description'    => "Material Relief from Store for SIV #{$sivNo}",
                'project_id'     => $projectId,
                'site_id'        => $siteId,
                'cost_center_id' => $costCenterId,
            ],
        ];

        return $this->journalEngine->createEntry(
            'siv_issue',
            crc32($sivNo),
            "Store Issue SIV #{$sivNo} to Project #{$projectId}",
            $lines,
            $userId,
            $userId,
            $entryDate ?: now()->toDateString()
        );
    }

    /**
     * Step 4: Supplier Payment Disbursement (with WHT calculated strictly on base before VAT).
     * DR 2001 Accounts Payable (Gross liability)
     * CR Bank (Net disbursed)
     * CR 2600 WHT Payable (3% or 2% on base before VAT)
     */
    public function postSupplierPayment(
        string $invoiceNo,
        float $grossInvoiceAmount,
        float $baseAmountBeforeVat,
        int $bankAccountId,
        int $supplierId,
        bool $hasValidTin = true,
        string $itemType = 'goods',
        ?string $invoiceDate = null,
        int $userId = 1,
        ?string $paymentDate = null
    ): JournalEntry {
        $apAcc   = ChartOfAccount::where('code', '2001')->firstOrFail();
        $bankAcc = ChartOfAccount::findOrFail($bankAccountId);
        $whtAcc  = ChartOfAccount::where('code', '2600')->firstOrFail();

        // 1. Calculate WHT rate on base amount before VAT strictly driven by config
        $configKey = ($itemType === 'services') ? 'wht_services' : 'wht_goods';
        $whtRate = 0.0;
        if (!$hasValidTin) {
            $whtRate = (float) config("finance.tax.{$configKey}.non_tin_rate", 0.30) * 100;
        } else {
            $stdRate = (float) config("finance.tax.{$configKey}.standard_rate", ($itemType === 'services' ? 0.03 : 0.02)) * 100;
            $threshold = (float) config("finance.tax.{$configKey}.threshold", ($itemType === 'services' ? 3000.0 : 10000.0));

            // Optional DB table override if present
            $taxCode = ($itemType === 'services') ? 'WHT_SERVICES_3PCT' : 'WHT_GOODS_2PCT';
            $dbRate = DB::table('tax_rates')->where('tax_code', $taxCode)->first();
            if ($dbRate) {
                $stdRate = (float) $dbRate->standard_rate_percent;
                $threshold = (float) $dbRate->threshold_amount;
            }

            if ($baseAmountBeforeVat >= $threshold) {
                $whtRate = $stdRate;
            }
        }

        $whtAmount = round($baseAmountBeforeVat * ($whtRate / 100), 2);
        $netDisbursed = round($grossInvoiceAmount - $whtAmount, 2);

        // Pre-8-July Rule: Payments for pre-8-July invoices always debit 2001 AP, never expense!
        $isPreOpening = $invoiceDate && Carbon::parse($invoiceDate)->lt(Carbon::parse('2026-07-08'));
        $paymentDesc  = $isPreOpening
            ? "Pre-8-July Historical Invoice #{$invoiceNo} Settlement"
            : "Payment for Supplier Invoice #{$invoiceNo}";

        $lines = [
            [
                'account_id'  => $apAcc->id,
                'side'        => 'debit',
                'amount'      => $grossInvoiceAmount,
                'description' => "{$paymentDesc} (Gross AP cleared)",
                'party_type'  => 'supplier',
                'party_id'    => $supplierId,
            ],
            [
                'account_id'  => $bankAcc->id,
                'side'        => 'credit',
                'amount'      => $netDisbursed,
                'description' => "Net wire transfer to supplier",
                'party_type'  => 'supplier',
                'party_id'    => $supplierId,
            ],
        ];

        if ($whtAmount > 0) {
            $lines[] = [
                'account_id'  => $whtAcc->id,
                'side'        => 'credit',
                'amount'      => $whtAmount,
                'description' => "Withholding Tax ({$whtRate}%) on pre-VAT base ETB {$baseAmountBeforeVat}",
                'party_type'  => 'supplier',
                'party_id'    => $supplierId,
            ];
        }

        return $this->journalEngine->createEntry(
            'supplier_payment',
            crc32($invoiceNo . ($paymentDate ?: now()->toDateString())),
            "Supplier Payment #{$invoiceNo}",
            $lines,
            $userId,
            $userId,
            $paymentDate ?: now()->toDateString()
        );
    }
}
