<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcurementReceipt extends Model
{
    protected $fillable = [
        'purchase_request_id', 'file_path', 'original_filename', 'notes',
        'uploaded_by', 'verified_by', 'verification_status',
        'verification_notes', 'verified_at',
        'audit_escalated_3day_at', 'audit_escalated_5day_at',
        'withholding_receipt_path', 'withholding_receipt_number',
        'has_withholding', 'withholding_rate', 'withholding_amount',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
        'audit_escalated_3day_at' => 'datetime',
        'audit_escalated_5day_at' => 'datetime',
        'has_withholding' => 'boolean',
        'withholding_rate' => 'decimal:2',
        'withholding_amount' => 'decimal:2',
    ];

    public function purchaseRequest() { return $this->belongsTo(PurchaseRequest::class); }
    public function uploadedBy()      { return $this->belongsTo(User::class, 'uploaded_by'); }
    public function verifiedBy()      { return $this->belongsTo(User::class, 'verified_by'); }

    public function getWithholdingReceiptUrlAttribute(): ?string
    {
        $path = $this->withholding_receipt_path
            ?? $this->purchaseRequest?->payment?->withholding_receipt;
        return $path ? \App\Services\FileUploadService::url($path) : null;
    }
}
