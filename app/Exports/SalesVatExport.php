<?php

namespace App\Exports;

use App\Models\IncomeRecord;
use App\Models\SystemSetting;
use App\Services\EthiopianDate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesVatExport
{
    /**
     * Exact 13 Columns required by e-Tax Sales Return
     */
    public const HEADERS = [
        'Seller TIN',
        'Buyer TIN',
        'Buyer Name',
        'Transaction',
        'Receipt Category',
        'Receipt Used',
        'MRC',
        'Receipt No',
        'Commodity Type',
        'Quantity',
        'Price',
        'Receipt Date',
        'Encoded By',
    ];

    /**
     * Build rows from IncomeRecord items
     */
    public static function buildRows(Collection $incomes): array
    {
        $defaultSellerTin = SystemSetting::get('company_tin', SystemSetting::get('purchaser_tin', '38480010'));
        $defaultSellerDigits = (int)preg_replace('/^0+/', '', preg_replace('/[^0-9]/', '', (string)$defaultSellerTin));
        if (!$defaultSellerDigits) {
            $defaultSellerDigits = 38480010;
        }

        $rows = [];

        foreach ($incomes as $inc) {
            // Col 1: Seller TIN
            $rawSeller = $inc->seller_tin ?: $defaultSellerTin;
            $sellerDigits = preg_replace('/^0+/', '', preg_replace('/[^0-9]/', '', (string)$rawSeller));
            $sellerTinVal = ($sellerDigits !== '') ? (int)$sellerDigits : $defaultSellerDigits;

            // Col 2: Buyer TIN
            $rawBuyer = (string)($inc->buyer_tin ?: ($inc->tin ?: ''));
            $buyerDigits = preg_replace('/^0+/', '', preg_replace('/[^0-9]/', '', $rawBuyer));
            $buyerTinVal = ($buyerDigits !== '') ? (int)$buyerDigits : (trim($rawBuyer) ?: '');

            // Col 3: Buyer Name
            $buyerName = trim((string)($inc->buyer_name ?: ($inc->payer ?: 'Client / Customer')));

            // Col 4: Transaction Type
            $trans = 'TR_CR';

            // Col 5: Receipt Category
            $recCat = 'RC_REG';

            // Col 6: Receipt Used
            $recUsed = 'RU_CASH';

            // Col 7: MRC
            $mrcVal = trim((string)($inc->mrc_no ?: ''));

            // Col 8: Receipt No
            $receiptNo = trim((string)($inc->receipt_no ?: ($inc->income_no ?: '')));

            // Col 9: Commodity Type
            $commodity = 'COMM_SER';

            // Col 10: Quantity
            $qty = (float)($inc->quantity > 0 ? $inc->quantity : 1);

            // Col 11: Price
            $gross = (float)($inc->unit_price > 0 && $inc->quantity > 0 ? ($inc->unit_price * $inc->quantity) : $inc->amount);
            $price = round($gross, 2);

            // Col 12: Receipt Date (EC d-m-Y)
            $date = $inc->income_date ?: ($inc->receipt_date ?: $inc->created_at);
            $receiptDate = $date ? EthiopianDate::formatDateEc($date, 'd-m-Y') : EthiopianDate::formatDateEc(now(), 'd-m-Y');

            // Col 13: Encoded By
            $encodedBy = 'wechacha';

            $rows[] = [
                $sellerTinVal,
                $buyerTinVal,
                $buyerName,
                $trans,
                $recCat,
                $recUsed,
                $mrcVal,
                $receiptNo,
                $commodity,
                $qty,
                $price,
                $receiptDate,
                $encodedBy,
            ];
        }

        return $rows;
    }

    /**
     * Stream CSV download
     */
    public static function streamCsv(Collection $incomes, ?string $filename = null): StreamedResponse
    {
        $filename = $filename ?: ('Sales_Report_' . now()->format('Ymd_His') . '.csv');
        $rows = self::buildRows($incomes);

        $response = new StreamedResponse(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
            fputcsv($handle, self::HEADERS);
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', "attachment; filename=\"{$filename}\"");
        return $response;
    }
}
