<?php

namespace App\Exports;

use App\Models\ExpenseHubVatDecision;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Services\EthiopianDate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\Xlsx\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PurchaseVatExport
{
    /**
     * Exact 15 ERA Columns matching pro.doc
     */
    public const HEADERS = [
        'VAT CATEGORY  (G=GOODS;S=SERVICES)',
        'CALENDAR TYPE (E=ETHIOPIAN;G=GREGORIAN)',
        'Types of purchase. 1 = Taxable-local Purchase of Capital Assets (Line No. 65) 2 = Taxable-imported Purchase of Capital Assets (Line No. 75) 3 = Taxable-local Purchase of Inputs (Line No. 100) 4 = Taxable-imported Purchase of Inputs (Line No. 110) 5 = Taxable-general Expense Inputs Purchase (Line No. 120) 6= Tax Exempted-purchase with no vat or uncollectible inputs (Line no. 85 or Line no. 130)    (Please type 1 or 2 or 3 or 4 or 5 or 6).This field is mandatory.',
        'TIN..This field  is not mandatory.',
        'Seller name (if Seller has no TIN or item is not locally purchased) This field is not mandatory.',
        'Date of purchase/Customs Declaration No.  Dispatched Date (Please use  dd/mm/yyyy date format).  This field is mandatory.',
        'MRC Number..This field is not mandatory.',
        'Vat receipt number/ Customs Declaration Number.This field is mandatory.',
        'Description.This field is mandatory.',
        'Unit of Measure (type ID 2-10). 2 KG 3 ML 4 GM 5 LIT 6 MT 7 PCS 8 CT 9 OTHER 10 PC This field is mandatory.',
        'Quantity. Enter number.Don\'t use comma (,) or Quatation ("") This field is  mandatory.',
        'Unit Price. Enter number only .Don\'t use comma (,) or Quatation ("") This field is  mandatory.',
        'Total value',
        'vat',
        'value after vat',
    ];

    /**
     * Build rows from Expense Hub items (Outcome 1 only: VAT Applies)
     */
    public static function buildRows(Collection $expenses): array
    {
        $rows = [];

        foreach ($expenses as $item) {
            // Strictly Outcome 1: With VAT Receipt (vat_applies)
            if ($item->vat_status !== ExpenseHubVatDecision::STATUS_VAT_APPLIES) {
                continue;
            }

            // Category G=Goods, S=Services
            $categoryRaw = strtoupper(trim((string)($item->vat_category ?: 'G')));
            $vatCategory = ($categoryRaw === 'S') ? 'S' : 'G';

            // Calendar Type E=Ethiopian, G=Gregorian
            $calType = strtoupper(trim((string)($item->calendar_type ?: 'G')));
            $calendarType = ($calType === 'E') ? 'E' : 'G';

            // FixedAsset rows use Line 65 (purchase_type = 1). Otherwise local input = 3
            $isFixedAsset = ($item->source_type === 'fixed_asset' || !empty($item->is_capex));
            $purchaseType = $isFixedAsset ? 1 : (int)($item->purchase_type ?: 3);
            if (!in_array($purchaseType, [1, 2, 3, 4, 5, 6])) {
                $purchaseType = $isFixedAsset ? 1 : 3;
            }

            // Seller TIN
            $rawTin = (string)($item->tin ?? '');
            $tinDigits = preg_replace('/[^0-9]/', '', $rawTin);
            $tinDigits = preg_replace('/^0+/', '', $tinDigits);
            $tinVal = ($tinDigits !== '') ? (int)$tinDigits : '';

            // Seller Name
            $sellerName = trim((string)($item->custom_seller_name ?: ($item->payee_supplier ?: 'Supplier')));

            // Purchase Date
            $date = $item->disbursement_date ?: $item->created_at;
            if ($calendarType === 'E') {
                $dateFormatted = $date ? EthiopianDate::formatDateEc($date, 'd/m/Y') : EthiopianDate::formatDateEc(now(), 'd/m/Y');
            } else {
                $dateFormatted = $date ? Carbon::parse($date)->format('d/m/Y') : now()->format('d/m/Y');
            }

            // MRC
            $rawMrc = trim((string)($item->mrc_no ?? ''));
            $mrcVal = ($rawMrc !== '' && $rawMrc !== '-' && strtoupper($rawMrc) !== 'N/A') ? $rawMrc : '';

            // FS Number / Voucher Number
            $rawFs = trim((string)($item->fs_no ?? ''));
            $fsNo = $rawFs ?: ($item->reference_no ?: '');

            // Description
            $desc = trim((string)($item->item_desc ?: ($item->description ?: ($item->purpose_detail ?: 'Construction Materials'))));
            $desc = mb_substr($desc, 0, 200);

            // Unit of Measure (ID 2-10)
            $uomRaw = strtoupper(trim((string)($item->uom ?? '')));
            if (is_numeric($uomRaw) && (int)$uomRaw >= 2 && (int)$uomRaw <= 10) {
                $uomId = (int)$uomRaw;
            } else {
                $uomId = match (true) {
                    str_contains($uomRaw, 'KG') || str_contains($uomRaw, 'KILO') => 2,
                    str_contains($uomRaw, 'ML') => 3,
                    str_contains($uomRaw, 'GM') || str_contains($uomRaw, 'GRAM') => 4,
                    str_contains($uomRaw, 'LIT') || str_contains($uomRaw, 'LTR') => 5,
                    str_contains($uomRaw, 'MT') || str_contains($uomRaw, 'TON') => 6,
                    str_contains($uomRaw, 'PCS') || str_contains($uomRaw, 'PIECE') => 7,
                    str_contains($uomRaw, 'CT') || str_contains($uomRaw, 'CARTON') => 8,
                    str_contains($uomRaw, 'PC') => 10,
                    default => 9, // Other
                };
            }

            // Quantities & Amounts
            $qty = (float)($item->qty > 0 ? $item->qty : 1.0);
            $baseVal = (float)$item->decision_base_amount;
            $vatVal = (float)$item->decision_vat_amount;
            $totVal = (float)$item->decision_total_amount;

            if ($baseVal <= 0 && $totVal > 0) {
                $baseVal = round($totVal / 1.15, 2);
                $vatVal = round($totVal - $baseVal, 2);
            }
            if ($vatVal <= 0 && $baseVal > 0) {
                $vatVal = round($baseVal * 0.15, 2);
            }
            if (abs(($baseVal + $vatVal) - $totVal) > 0.05) {
                $totVal = round($baseVal + $vatVal, 2);
            }

            $unitVal = round($baseVal / $qty, 2);

            $rows[] = [
                $vatCategory,
                $calendarType,
                $purchaseType,
                $tinVal,
                $sellerName,
                $dateFormatted,
                $mrcVal,
                $fsNo,
                $desc,
                $uomId,
                $qty,
                $unitVal,
                $baseVal,
                $vatVal,
                $totVal,
            ];
        }

        return $rows;
    }

    /**
     * Stream CSV download
     */
    public static function streamCsv(Collection $expenses, ?string $filename = null): StreamedResponse
    {
        $filename = $filename ?: ('Purchase_VAT_Declaration_' . now()->format('Ymd_His') . '.csv');
        $rows = self::buildRows($expenses);

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
