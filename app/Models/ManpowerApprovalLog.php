<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ManpowerApprovalLog extends Model
{
    use HasFactory;

    protected $table = 'manpower_approval_logs';

    protected $fillable = [
        'record_type',
        'record_id',
        'stage',
        'action',
        'user_id',
        'rejection_reason_code',
        'comment',
        'amount_before',
        'amount_after',
        'line_adjustments',
    ];

    protected $casts = [
        'amount_before'    => 'decimal:2',
        'amount_after'     => 'decimal:2',
        'line_adjustments' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
