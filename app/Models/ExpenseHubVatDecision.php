<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExpenseHubVatDecision extends Model
{
    use HasFactory;

    protected $table = 'expense_hub_vat_decisions';

    protected $fillable = [
        'source_type',
        'source_id',
        'vat_status',
        'has_withholding',
        'withholding_rate',
        'withholding_amount',
        'withholding_receipt',
        'withholding_receipt_number',
        'net_payable_amount',
        'vat_rate',
        'is_inclusive',
        'base_amount',
        'vat_amount',
        'total_amount',
        'no_vat_reason_code',
        'no_vat_reason_note',
        'is_verified_without_receipt',
        'receipt_path',
        'ocr_confidence_score',
        'raw_ocr_payload',
        'vat_category',
        'purc_type',
        'calendar_type',
        'purchase_type',
        'custom_seller_name',
        'custom_seller_tin',
        'custom_fs_no',
        'custom_mrc_no',
        'custom_item_description',
        'custom_receipt_date',
        'uom',
        'quantity',
        'unit_price',
        'is_locked',
        'locked_at',
        'locked_by',
        'decided_by',
        'decided_at',
    ];

    protected $casts = [
        'is_inclusive'                => 'boolean',
        'is_locked'                   => 'boolean',
        'is_verified_without_receipt' => 'boolean',
        'has_withholding'             => 'boolean',
        'ocr_confidence_score'        => 'integer',
        'raw_ocr_payload'             => 'array',
        'withholding_rate'   => 'decimal:2',
        'withholding_amount' => 'decimal:2',
        'net_payable_amount' => 'decimal:2',
        'vat_rate'           => 'decimal:2',
        'base_amount'  => 'decimal:2',
        'vat_amount'   => 'decimal:2',
        'total_amount' => 'decimal:2',
        'quantity'     => 'decimal:2',
        'unit_price'   => 'decimal:2',
        'locked_at'    => 'datetime',
        'decided_at'   => 'datetime',
    ];

    public const STATUS_VAT_APPLIES       = 'vat_applies';
    public const STATUS_NO_VAT            = 'no_vat';
    public const STATUS_PENDING_DECISION  = 'pending_decision';

    public const NO_VAT_REASONS = [
        'fuel_zero_vat'      => 'Fuel / Petroleum Receipt (0% VAT / TAS Purchase)',
        'tas_purchase'       => 'TAS Purchase with Fiscal Receipt (0% VAT / Exempt)',
        'no_tin'             => 'Vendor has no valid TIN',
        'no_fiscal_receipt'  => 'No official Fiscal Receipt (FS/Cash Register)',
        'exempt_item'        => 'Legally VAT-Exempt goods/services (Proclamation)',
        'salary_payroll'     => 'Employee Salary / Labor Allowance',
        'informal_vendor'    => 'Informal / Small-holder Vendor without receipt',
        'tax_settlement'     => 'Government Tax Remittance (MOR/ERCA)',
        'other'              => 'Other justification (see note)',
    ];

    public const PURCHASE_TYPES = [
        1 => '1 - Taxable-local Purchase of Capital Assets (Line 65)',
        2 => '2 - Taxable-imported Purchase of Capital Assets (Line 75)',
        3 => '3 - Taxable-local Purchase of Inputs (Line 100)',
        4 => '4 - Taxable-imported Purchase of Inputs (Line 110)',
        5 => '5 - Taxable-general Expense Inputs Purchase (Line 120)',
        6 => '6 - Tax Exempted-purchase with no vat or uncollectible inputs',
    ];

    public function decidedByUser()
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function lockedByUser()
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function auditLogs()
    {
        return $this->hasMany(ExpenseHubVatAuditLog::class, 'decision_id');
    }

    /**
     * Helper to compute base, VAT, and total based on rate and inclusive/exclusive toggle.
     */
    public static function computeVatAmounts(float $amount, float $rate = 15.00, bool $isInclusive = true): array
    {
        if ($amount <= 0 || $rate <= 0) {
            return [
                'base'  => round($amount, 2),
                'vat'   => 0.00,
                'total' => round($amount, 2),
            ];
        }

        if ($isInclusive) {
            $base = round($amount / (1 + ($rate / 100)), 2);
            $vat  = round($amount - $base, 2);
            return [
                'base'  => $base,
                'vat'   => $vat,
                'total' => round($amount, 2),
            ];
        } else {
            $base = round($amount, 2);
            $vat  = round($amount * ($rate / 100), 2);
            return [
                'base'  => $base,
                'vat'   => $vat,
                'total' => round($base + $vat, 2),
            ];
        }
    }
}
