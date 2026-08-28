<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PettyCashReplenishment extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'petty_cash_replenishments';

    protected $fillable = [
        'request_no',
        'chart_of_account_id',
        'requested_by',
        'requested_amount',
        'current_balance_at_request',
        'total_expenses_amount',
        'period_start_date',
        'period_end_date',
        'start_journal_line_id',
        'end_journal_line_id',
        'status',
        'notes',
        'attachment_path',
        'finance_head_id',
        'fulfilled_amount',
        'source_coa_id',
        'journal_entry_id',
        'finance_notes',
        'fulfillment_reference',
        'fulfilled_at',
        'rejected_at',
        'rejection_reason',
    ];

    protected $casts = [
        'requested_amount'           => 'decimal:2',
        'current_balance_at_request' => 'decimal:2',
        'total_expenses_amount'      => 'decimal:2',
        'fulfilled_amount'           => 'decimal:2',
        'period_start_date'          => 'datetime',
        'period_end_date'            => 'datetime',
        'fulfilled_at'               => 'datetime',
        'rejected_at'                => 'datetime',
    ];

    // Status Constants
    public const STATUS_PENDING   = 'pending';
    public const STATUS_FULFILLED = 'fulfilled';
    public const STATUS_REJECTED  = 'rejected';

    public function account()
    {
        return $this->belongsTo(ChartOfAccount::class, 'chart_of_account_id');
    }

    public function chartOfAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'chart_of_account_id');
    }


    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function financeHead()
    {
        return $this->belongsTo(User::class, 'finance_head_id');
    }

    public function sourceCoa()
    {
        return $this->belongsTo(ChartOfAccount::class, 'source_coa_id');
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function items()
    {
        return $this->hasMany(PettyCashReplenishmentItem::class, 'petty_cash_replenishment_id');
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        if (!$this->attachment_path) {
            return null;
        }
        return \App\Services\FileUploadService::url($this->attachment_path);
    }
}
