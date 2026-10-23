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
        'tin_valid',
        'buyer_tin',
        'fs_no',
        'fs_no_raw',
        'fs_no_valid',
        'mrc_no',
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
        'ocr_engine',
        'confidence',
        'confidence_score',
        'needs_review',
        'parsed_data',
        'parse_status',
        'parse_error',
        'status',
        'approved_by',
        'approved_at',
        'notes',
        'qc_notes',
        'purchasable_type',
        'purchasable_id',
    ];

    protected $casts = [
        'parsed_data'      => 'array',
        'receipt_date'     => 'date',
        'approved_at'      => 'datetime',
        'subtotal'         => 'decimal:2',
        'vat_amount'       => 'decimal:2',
        'total_amount'     => 'decimal:2',
        'confidence_score' => 'integer',
        'tin_valid'        => 'boolean',
        'fs_no_valid'      => 'boolean',
        'needs_review'     => 'boolean',
    ];

    protected $appends = [
        'file_url',
    ];

    /**
     * Auto-generate receipt_number and fill mandatory defaults before create.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->uploaded_by)) {
                $model->uploaded_by = \Illuminate\Support\Facades\Auth::id() 
                    ?: (\App\Models\User::value('id') ?: 1);
            }

            if ($model->file_path === null) {
                $model->file_path = '';
            }

            if (empty($model->receipt_number)) {
                $today  = now()->format('Ymd');
                $last   = static::whereDate('created_at', now()->toDateString())->count();
                $seq    = $last + 1;
                $candidate = 'RCP-' . $today . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
                while (static::where('receipt_number', $candidate)->exists()) {
                    $seq++;
                    $candidate = 'RCP-' . $today . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
                }
                $model->receipt_number = $candidate;
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

    public function items()
    {
        return $this->hasMany(ReceiptItem::class, 'receipt_id');
    }

    public function purchasable(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get accessible public URL for the receipt image or PDF slip.
     */
    public function getFileUrlAttribute(): ?string
    {
        if (empty($this->file_path)) {
            return null;
        }

        try {
            return \App\Services\FileUploadService::url($this->file_path);
        } catch (\Throwable $e) {
            return asset('storage/' . ltrim($this->file_path, '/'));
        }
    }

    public function getTaxTypeAttribute($value)
    {
        if ($value !== null && $value !== '') {
            return $value;
        }
        $parsed = is_string($this->parsed_data) ? (json_decode($this->parsed_data, true) ?: []) : (array)($this->parsed_data ?? []);
        if (!empty($parsed['tax_type'])) {
            return $parsed['tax_type'];
        }
        return ((float)$this->vat_amount <= 0 || $this->category === 'fuel') ? 'no_vat' : 'vat_standard';
    }

    public function setTaxTypeAttribute($value)
    {
        if (\Illuminate\Support\Facades\Schema::hasColumn($this->getTable(), 'tax_type')) {
            $this->attributes['tax_type'] = $value;
        } else {
            $parsed = is_string($this->parsed_data) ? (json_decode($this->parsed_data, true) ?: []) : (array)($this->parsed_data ?? []);
            $parsed['tax_type'] = $value;
            $this->parsed_data = $parsed;
        }
    }

    public function getNoVatReasonCodeAttribute($value)
    {
        if ($value !== null && $value !== '') {
            return $value;
        }
        $parsed = is_string($this->parsed_data) ? (json_decode($this->parsed_data, true) ?: []) : (array)($this->parsed_data ?? []);
        return $parsed['no_vat_reason_code'] ?? null;
    }

    public function setNoVatReasonCodeAttribute($value)
    {
        if (\Illuminate\Support\Facades\Schema::hasColumn($this->getTable(), 'no_vat_reason_code')) {
            $this->attributes['no_vat_reason_code'] = $value;
        } else {
            $parsed = is_string($this->parsed_data) ? (json_decode($this->parsed_data, true) ?: []) : (array)($this->parsed_data ?? []);
            $parsed['no_vat_reason_code'] = $value;
            $this->parsed_data = $parsed;
        }
    }
}

