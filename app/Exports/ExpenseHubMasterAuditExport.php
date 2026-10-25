<?php

namespace App\Exports;

use App\Models\ExpenseHubVatDecision;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseHubMasterAuditExport
{
    /**
     * Exact 22 Columns for Internal Corporate Tax Audit
     */
    public const HEADERS = [
        'Disbursement Date',
        'Source Module',
        'Reference No',
        'Project / Site',
        'Supplier / Payee',
        'Vendor TIN',
        'Gross Amount (ETB)',
        'VAT Status',
        'Verified w/o Receipt',
        'Base Subtotal (ETB)',
        'VAT Amount (15%)',
        'Total Amount (ETB)',
        'Has Withholding',
        'WHT Rate (%)',
        'Withholding Amount (3%)',
        'Net Disbursed to Vendor (ETB)',
        'Withholding Slip Ref',
        'FS Number',
        'MRC Number',
        'No-VAT Reason / Audit Memo',
        'Compliance Audit Warnings',
        'Line-Item Purpose / Description',
    ];

    /**
     * Build 22-column rows from Expense Hub items (includes all 4 outcomes)
     */
    public static function buildRows(Collection $expenses): array
    {
        $rows = [];

        foreach ($expenses as $item) {
            $isVerifiedNoSlip = !empty($item->is_verified_without_receipt);

            $reasonDisplay = '';
            if ($isVerifiedNoSlip) {
                $reasonDisplay = 'Verified Without Receipt: ' . ($item->reason_note ?: ($item->reason_code ? (ExpenseHubVatDecision::NO_VAT_REASONS[$item->reason_code] ?? $item->reason_code) : 'Audited operational expense'));
            } elseif ($item->vat_status === ExpenseHubVatDecision::STATUS_NO_VAT) {
                $reasonDisplay = $item->reason_code ? (ExpenseHubVatDecision::NO_VAT_REASONS[$item->reason_code] ?? $item->reason_code) : ($item->reason_note ?: 'Exempt / 0% TAS Purchase');
            }

            $whtAmount = (float)($item->withholding_amount ?? 0);
            $hasWht = !empty($item->has_withholding) || $whtAmount > 0;
            $whtRate = $hasWht ? (float)($item->withholding_rate ?: 3.00) : 0.00;
            $gross = (float)($item->amount ?? 0);
            $netDisbursed = (float)($item->net_payable_amount ?? ($gross - $whtAmount));

            $rows[] = [
                $item->disbursement_date ?: 'N/A',
                $item->source_module_label ?? 'Disbursement',
                $item->reference_no ?? '—',
                $item->project_name ?? 'General / Head Office',
                $item->payee_supplier ?? '—',
                $item->tin ?: 'N/A',
                number_format($gross, 2, '.', ''),
                ucwords(str_replace('_', ' ', $item->vat_status ?? 'pending_decision')),
                $isVerifiedNoSlip ? 'YES (No Slip)' : 'NO',
                number_format((float)($item->decision_base_amount ?? 0), 2, '.', ''),
                number_format((float)($item->decision_vat_amount ?? 0), 2, '.', ''),
                number_format((float)($item->decision_total_amount ?? $gross), 2, '.', ''),
                $hasWht ? 'YES' : 'NO',
                number_format($whtRate, 2, '.', ''),
                number_format($whtAmount, 2, '.', ''),
                number_format($netDisbursed, 2, '.', ''),
                $item->withholding_receipt_number ?: ($item->withholding_receipt ? 'Slip Attached' : 'None'),
                $item->fs_no ?: 'N/A',
                $item->mrc_no ?: 'N/A',
                $reasonDisplay ?: 'N/A',
                !empty($item->warnings) ? implode('; ', $item->warnings) : 'None',
                $item->description ?: ($item->purpose_detail ?: 'Operational Expenditure'),
            ];
        }

        return $rows;
    }

    /**
     * Stream CSV download
     */
    public static function streamCsv(Collection $expenses, ?string $filename = null): StreamedResponse
    {
        $filename = $filename ?: ('Expense_Hub_Master_Audit_' . now()->format('Ymd_His') . '.csv');
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
