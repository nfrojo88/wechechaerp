<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Receipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'receipt_number',
        'uploaded_by',
        'project_id',
        'vendor_name',
        'vendor_tin',
        'receipt_date',
        'subtotal',
        'vat_amount',
        'total_amount',
        'currency',
        'category',
        'description',
        'file_path',
        'file_type',
        'ocr_raw_text',
        'parsed_data',
        'parse_status',
        'parse_error',
        'status',
        'approved_by',
        'approved_at',
        'notes',
    ];

    protected $casts = [
        'parsed_data'  => 'array',
        'receipt_date' => 'date',
        'approved_at'  => 'datetime',
        'subtotal'     => 'decimal:2',
        'vat_amount'   => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    /**
     * Auto-generate receipt_number before create.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->receipt_number)) {
                $today  = now()->format('Ymd');
                $last   = static::whereDate('created_at', now()->toDateString())->count();
                $model->receipt_number = 'RCP-' . $today . '-' . str_pad($last + 1, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    // ── Categories (mirrors Expense::CATEGORIES) ─────────────────────────
    public const CATEGORIES = [
        'labour'        => 'Labour',
        'material'      => 'Material',
        'equipment'     => 'Equipment',
        'overhead'      => 'Overhead',
        'subcontractor' => 'Sub-Contractor',
        'food'          => 'Food & Hospitality',
        'transport'     => 'Transport',
        'utility'       => 'Utility',
        'other'         => 'Other',
    ];

    // ── Relationships ─────────────────────────────────────────────────────
    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
