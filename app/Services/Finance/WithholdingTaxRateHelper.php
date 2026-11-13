<?php

namespace App\Services\Finance;

/**
 * Authoritative Withholding Tax (WHT) Rate Lookup Helper.
 * Centralizes statutory rate evaluation across all finance modules.
 */
class WithholdingTaxRateHelper
{
    /**
     * Resolve applicable withholding tax rate.
     *
     * @param string $itemType 'goods' or 'services'
     * @param bool $hasValidTin
     * @param float $baseAmount Pre-VAT base amount
     * @return array
     */
    public static function resolveRate(string $itemType = 'services', bool $hasValidTin = true, float $baseAmount = 0.0): array
    {
        $normalizedType = strtolower($itemType) === 'goods' ? 'wht_goods' : 'wht_services';
        $config = config("finance.tax.{$normalizedType}");

        $standardRate = (float)($config['standard_rate'] ?? ($normalizedType === 'wht_goods' ? 0.02 : 0.03));
        $nonTinRate   = (float)($config['non_tin_rate'] ?? 0.30);
        $threshold       = (float)($config['threshold'] ?? ($normalizedType === 'wht_goods' ? 10000.00 : 3000.00));
        $thresholdStatus = $config['threshold_status'] ?? 'to_be_verified';
        $legalBasis      = $config['legal_basis'] ?? 'Proclamation 979/2016';
        $status          = $config['status'] ?? 'to_be_verified';

        if (!$hasValidTin) {
            return [
                'rate_percent'      => round($nonTinRate * 100, 2),
                'rate_decimal'      => $nonTinRate,
                'standard_rate'     => round($standardRate * 100, 2),
                'is_applicable'     => true,
                'threshold'         => 0.00,
                'threshold_status'  => 'to_be_verified',
                'exceeds_threshold' => true,
                'legal_basis'       => $legalBasis . ' (Non-TIN Rate)',
                'status'            => $status,
            ];
        }

        $exceeds = ($baseAmount >= $threshold) || ($baseAmount <= 0.0);

        return [
            'rate_percent'      => $exceeds ? round($standardRate * 100, 2) : 0.00,
            'rate_decimal'      => $exceeds ? $standardRate : 0.00,
            'standard_rate'     => round($standardRate * 100, 2),
            'is_applicable'     => $exceeds,
            'threshold'         => $threshold,
            'threshold_status'  => 'to_be_verified',
            'exceeds_threshold' => $exceeds,
            'legal_basis'       => $legalBasis,
            'status'            => $status,
        ];
    }

    /**
     * Get default standard statutory rate percent (e.g. 2.00 or 3.00).
     */
    public static function getStandardRatePercent(string $itemType = 'services'): float
    {
        return self::resolveRate($itemType, true, 0.0)['standard_rate'];
    }

    /**
     * Calculate WHT amount on a given pre-VAT base.
     */
    public static function computeWithholdingAmount(float $preVatBase, string $itemType = 'services', bool $hasValidTin = true): float
    {
        $info = self::resolveRate($itemType, $hasValidTin, $preVatBase);
        if (!$info['is_applicable']) {
            return 0.00;
        }

        return round($preVatBase * $info['rate_decimal'], 2);
    }

    /**
     * Resolve withholding rate from an item (array or object).
     * If withholding_rate is explicitly set and positive, returns that rate.
     * If rate is null or not provided, derives rate from stored classification (purchase_type, item_type, category).
     * If unclassified and rate is null, raises InvalidArgumentException (no silent default).
     *
     * @param array|object $item
     * @return float
     * @throws \InvalidArgumentException
     */
    public static function resolveRateFromItem($item): float
    {
        $rate = is_array($item) ? ($item['withholding_rate'] ?? null) : ($item->withholding_rate ?? null);
        if ($rate !== null && is_numeric($rate) && (float)$rate > 0) {
            return (float)$rate;
        }

        // Check stored classification
        $type = is_array($item) 
            ? ($item['purchase_type'] ?? ($item['item_type'] ?? ($item['type'] ?? ($item['category'] ?? ($item['utility_type'] ?? ($item['expense_type'] ?? null))))))
            : ($item->purchase_type ?? ($item->item_type ?? ($item->type ?? ($item->category ?? ($item->utility_type ?? ($item->expense_type ?? null))))));

        if ($type !== null) {
            $typeStr = strtolower(trim((string)$type));
            if (in_array($typeStr, ['goods', 'material', 'materials', 'supplies', '1', 'asset', 'equipment', 'store', 'hardware', 'cement', 'steel', 'sand', 'gravel', 'fuel'])) {
                return self::getStandardRatePercent('goods');
            }
            if (in_array($typeStr, ['services', 'service', 'subcontract', 'labor', 'labour', '2', '3', 'utility', 'utilities', 'electricity', 'water', 'internet', 'telephone', 'telecom', 'rental', 'transport', 'maintenance', 'consultancy', 'legal', 'audit', 'professional', 'freight'])) {
                return self::getStandardRatePercent('services');
            }
        }

        throw new \InvalidArgumentException(
            "Null withholding rate and unclassified item: cannot determine statutory WHT rate without explicit rate or stored classification."
        );
    }
}
