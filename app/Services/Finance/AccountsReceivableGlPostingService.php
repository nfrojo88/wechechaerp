<?php

namespace App\Services\Finance;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Services\Finance\JournalEngine;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AccountsReceivableGlPostingService
{
    protected JournalEngine $journalEngine;

    public function __construct(?JournalEngine $journalEngine = null)
    {
        $this->journalEngine = $journalEngine ?: new JournalEngine();
    }

    /**
     * Post Client / Machine Rental Invoice.
     * DR 1200 Accounts Receivable (Gross: Base + 15% VAT)
     * CR 4200 Machine Rent Income (Base rent before VAT)
     * CR 2300 VAT Payable (15% Output VAT on base)
     */
    public function postRentalInvoice(
        string $invoiceNo,
        float $baseRentAmount,
        float $vatRatePercent = 15.00,
        int $clientId = 1,
        ?int $projectId = null,
        int $userId = 1,
        ?string $invoiceDate = null
    ): JournalEntry {
        if ($baseRentAmount <= 0) {
            throw new InvalidArgumentException("Base rental amount must be positive.");
        }

        $vatAmount    = round($baseRentAmount * ($vatRatePercent / 100), 2);
        $grossInvoice = round($baseRentAmount + $vatAmount, 2);

        $arAcc   = ChartOfAccount::where('code', '1200')->firstOrFail();
        $revAcc  = ChartOfAccount::where('code', '4200')->firstOrFail();
        $vatAcc  = ChartOfAccount::where('code', '2300')->firstOrFail();

        $lines = [
            [
                'account_id'  => $arAcc->id,
                'side'        => 'debit',
                'amount'      => $grossInvoice,
                'description' => "Invoice #{$invoiceNo} Billed to Client",
                'party_type'  => 'client',
                'party_id'    => $clientId,
                'project_id'  => $projectId,
            ],
            [
                'account_id'  => $revAcc->id,
                'side'        => 'credit',
                'amount'      => $baseRentAmount,
                'description' => "Equipment Rental Revenue on Invoice #{$invoiceNo}",
                'party_type'  => 'client',
                'party_id'    => $clientId,
                'project_id'  => $projectId,
            ],
            [
                'account_id'  => $vatAcc->id,
                'side'        => 'credit',
                'amount'      => $vatAmount,
                'description' => "Output VAT (15%) on Rental Invoice #{$invoiceNo}",
                'party_type'  => 'client',
                'party_id'    => $clientId,
                'project_id'  => $projectId,
            ],
        ];

        return $this->journalEngine->createEntry(
            'rental_invoice',
            crc32($invoiceNo),
            "Rental Invoice #{$invoiceNo}",
            $lines,
            $userId,
            $userId,
            $invoiceDate ?: now()->toDateString()
        );
    }

    /**
     * Post Customer Receipt Collection (with Client WHT deducted on base amount before VAT).
     * DR Bank (Net cash collected: Gross - WHT)
     * DR 1300 Withholding Tax Receivable (WHT deducted by client on base before VAT)
     * CR 1200 Accounts Receivable (100% Gross invoice cleared)
     */
    public function postCustomerReceipt(
        string $invoiceNo,
        float $grossInvoiceAmount,
        float $baseAmountBeforeVat,
        int $bankAccountId,
        int $clientId,
        float $clientWhtRatePercent = 2.00, // Statutory 2% on rental/goods or 3% on services
        ?string $clientWhtCertificateNo = null,
        int $userId = 1,
        ?string $receiptDate = null
    ): JournalEntry {
        if ($grossInvoiceAmount <= 0) {
            throw new InvalidArgumentException("Gross invoice collection amount must be positive.");
        }

        $whtAmount   = round($baseAmountBeforeVat * ($clientWhtRatePercent / 100), 2);
        $netCashWire = round($grossInvoiceAmount - $whtAmount, 2);

        $arAcc   = ChartOfAccount::where('code', '1200')->firstOrFail();
        $bankAcc = ChartOfAccount::findOrFail($bankAccountId);
        $whtAcc  = ChartOfAccount::where('code', '1300')->firstOrFail();

        $lines = [
            [
                'account_id'  => $bankAcc->id,
                'side'        => 'debit',
                'amount'      => $netCashWire,
                'description' => "Client Wire Collection on Invoice #{$invoiceNo}",
                'party_type'  => 'client',
                'party_id'    => $clientId,
            ],
            [
                'account_id'  => $whtAcc->id,
                'side'        => 'debit',
                'amount'      => $whtAmount,
                'description' => "Client Tax Withheld ({$clientWhtRatePercent}%) on Base ETB {$baseAmountBeforeVat} (Cert: {$clientWhtCertificateNo})",
                'party_type'  => 'client',
                'party_id'    => $clientId,
            ],
            [
                'account_id'  => $arAcc->id,
                'side'        => 'credit',
                'amount'      => $grossInvoiceAmount,
                'description' => "Clearing Trade Receivable for Invoice #{$invoiceNo}",
                'party_type'  => 'client',
                'party_id'    => $clientId,
            ],
        ];

        return $this->journalEngine->createEntry(
            'customer_receipt',
            crc32($invoiceNo . 'receipt'),
            "Customer Settlement for Invoice #{$invoiceNo}",
            $lines,
            $userId,
            $userId,
            $receiptDate ?: now()->toDateString()
        );
    }
}
