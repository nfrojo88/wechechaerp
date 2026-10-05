<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReceiptItem extends Model
{
    use HasFactory;

    protected $table = 'receipt_items';

    protected $fillable = [
        'receipt_id',
        'item_description',
        'vat_category',
        'calendar_type',
        'purchase_type',
        'uom',
        'qty',
        'unit_price',
        'total_value',
        'vat_amount',
        'value_after_vat',
        'is_flagged',
        'flag_reasons',
    ];

    protected $casts = [
        'qty'             => 'decimal:2',
        'unit_price'      => 'decimal:2',
        'total_value'     => 'decimal:2',
        'vat_amount'      => 'decimal:2',
        'value_after_vat' => 'decimal:2',
        'purchase_type'   => 'integer',
        'is_flagged'      => 'boolean',
        'flag_reasons'    => 'array',
    ];

    /**
     * Parent Receipt relationship.
     */
    public function receipt()
    {
        return $this->belongsTo(Receipt::class, 'receipt_id');
    }

    /**
     * Run arithmetic validation on this row.
     * Returns array of error messages (empty if valid).
     */
    public function validateRow(): array
    {
        $errors = [];
        $qty = (float)$this->qty;
        $unitPrice = (float)$this->unit_price;
        $totalVal = (float)$this->total_value;
        $vat = (float)$this->vat_amount;
        $afterVat = (float)$this->value_after_vat;

        // Check Qty x Unit Price = Total Value (tolerance 0.05)
        if ($qty > 0 && $unitPrice > 0) {
            $expectedTotal = round($qty * $unitPrice, 2);
            if (abs($expectedTotal - $totalVal) > 0.05) {
                $errors[] = "Qty x Unit Price ($expectedTotal) ≠ Total Value ($totalVal)";
            }
        }

        // Check Total Value x 15% = VAT (tolerance 0.05)
        if ($totalVal > 0) {
            $expectedVat = round($totalVal * 0.15, 2);
            if (abs($expectedVat - $vat) > 0.05) {
                $errors[] = "Total Value x 15% ($expectedVat) ≠ VAT ($vat)";
            }
        }

        // Check Total Value + VAT = Value After VAT (tolerance 0.05)
        if ($totalVal > 0 || $vat > 0) {
            $expectedAfter = round($totalVal + $vat, 2);
            if (abs($expectedAfter - $afterVat) > 0.05) {
                $errors[] = "Total Value + VAT ($expectedAfter) ≠ Value After VAT ($afterVat)";
            }
        }

        // Validate Receipt header parent data if loaded
        if ($this->receipt) {
            $tin = preg_replace('/[^0-9]/', '', (string)$this->receipt->vendor_tin);
            if (empty($tin)) {
                $errors[] = "Supplier TIN is missing";
            } elseif (strlen($tin) !== 10) {
                $errors[] = "Supplier TIN should be 10 digits (got " . strlen($tin) . ")";
            }

            if (empty($this->receipt->fs_no)) {
                $errors[] = "FS Number is missing";
            }

            if (empty($this->receipt->receipt_date)) {
                $errors[] = "Receipt Date is missing";
            }
        }

        return $errors;
    }
}
