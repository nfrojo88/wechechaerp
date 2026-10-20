<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpenseHubVatAuditLog extends Model
{
    public $timestamps = false;

    protected $table = 'expense_hub_vat_audit_logs';

    protected $fillable = [
        'decision_id',
        'source_type',
        'source_id',
        'action',
        'old_values',
        'new_values',
        'performed_by',
        'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    public function decision()
    {
        return $this->belongsTo(ExpenseHubVatDecision::class, 'decision_id');
    }

    public function performer()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
