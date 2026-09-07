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
    ];

    protected $casts = [
        'verified_at' => 'datetime',
        'audit_escalated_3day_at' => 'datetime',
        'audit_escalated_5day_at' => 'datetime',
    ];

    public function purchaseRequest() { return $this->belongsTo(PurchaseRequest::class); }
    public function uploadedBy()      { return $this->belongsTo(User::class, 'uploaded_by'); }
    public function verifiedBy()      { return $this->belongsTo(User::class, 'verified_by'); }
}
