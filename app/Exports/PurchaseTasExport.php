<?php

namespace App\Exports;

use App\Models\ExpenseHubVatDecision;
use App\Models\SystemSetting;
use App\Services\EthiopianDate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PurchaseTasExport
{
    /**
     * Exact 9 Columns required by e-Tax TAS format
     */
    public const HEADERS = [
        'Purchaser TIN',
        'Seller TIN',
        'Amount',
        'Receipt No',
        'Receipt Date',
        'Encoding Date',
        'MRC',
        'PurcType',
        'Encoded By',
    ];

    /**
     * Build rows from Expense Hub items (Outcome 2 only: No VAT / TAS 0%)
     */
    public static function buildRows(Collection $expenses, ?string $calendarOverride = null): array
    {
        $rawCal = strtolower(trim((string)$calendarOverride));
        $targetCal = in_array($rawCal, ['g', 'gc', 'gregorian']) ? 'G' : 'E';

        $defaultPurchaserTin = SystemSetting::get('purchaser_tin', SystemSetting::get('company_tin', '38480010'));
        $defaultPurchaserDigits = (int)preg_replace('/^0+/', '', preg_replace('/[^0-9]/', '', (string)$defaultPurchaserTin));
        if (!$defaultPurchaserDigits) {
            $defaultPurchaserDigits = 38480010;
        }

        $rows = [];

        foreach ($expenses as $item) {
            // Strictly Outcome 2: Without VAT receipt (no_vat, but NOT verified without receipt which is outcome 4)
            if ($item->vat_status !== ExpenseHubVatDecision::STATUS_NO_VAT || !empty($item->is_verified_without_receipt)) {
                continue;
            }

            // Col 1: Purchaser TIN
            $purchaserTin = $defaultPurchaserDigits;

            // Col 2: Seller TIN
            $rawTin = (string)($item->tin ?? '');
            $sellerDigits = preg_replace('/^0+/', '', preg_replace('/[^0-9]/', '', $rawTin));
            $sellerTinVal = ($sellerDigits !== '') ? (int)$sellerDigits : (trim($rawTin) ?: '');

            // Col 3: Amount
            $amountVal = round((float)($item->decision_total_amount ?: $item->amount), 2);

            // Col 4: Receipt No
            $rawFs = trim((string)($item->fs_no ?? ''));
            $receiptNo = $rawFs ?: ($item->reference_no ?: '');

            // Col 5: Receipt Date
            $date = $item->disbursement_date ?: $item->created_at;
            if ($targetCal === 'G') {
                $receiptDate = $date ? Carbon::parse($date)->format('d-m-Y') : now()->format('d-m-Y');
                $encodingDate = now()->format('d-m-Y');
            } else {
                $receiptDate = $date ? EthiopianDate::formatDateEc($date, 'd-m-Y') : EthiopianDate::formatDateEc(now(), 'd-m-Y');
                $encodingDate = EthiopianDate::formatDateEc(now(), 'd-m-Y');
            }

            // Col 7: MRC
            $rawMrc = trim((string)($item->mrc_no ?? ''));
            $mrcVal = ($rawMrc !== '' && $rawMrc !== '-' && strtoupper($rawMrc) !== 'N/A') ? $rawMrc : '';

            // Col 8: PurcType (PUR_GD / PUR_SER)
            $purcType = ($item->purc_type ?? null) ?: ((($item->vat_category ?? null) === 'S') ? 'PUR_SER' : 'PUR_GD');

            // Col 9: Encoded By
            $encodedBy = 'wechacha';

            $rows[] = [
                $purchaserTin,
                $sellerTinVal,
                $amountVal,
                $receiptNo,
                $receiptDate,
                $encodingDate,
                $mrcVal,
                $purcType,
                $encodedBy,
            ];
        }

        return $rows;
    }

    /**
     * Stream CSV download
     */
    public static function streamCsv(Collection $expenses, ?string $filename = null, ?string $calendarOverride = null): StreamedResponse
    {
        $rawCal = strtolower(trim((string)$calendarOverride));
        $calSuffix = in_array($rawCal, ['g', 'gc', 'gregorian']) ? '_GC' : '_EC';
        $filename = $filename ?: ('Purchase_TAS' . $calSuffix . '_' . now()->format('Ymd_His') . '.csv');
        $rows = self::buildRows($expenses, $calendarOverride);

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
