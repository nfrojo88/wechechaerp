<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractRetentionRelease extends Model
{
    use HasFactory;

    protected $table = 'contract_retention_releases';

    protected $fillable = [
        'contract_id',
        'release_stage',
        'release_amount',
        'release_date',
        'certificate_reference',
        'is_posted',
        'journal_entry_id',
        'approved_by',
        'created_by',
    ];

    protected $casts = [
        'release_date'   => 'date',
        'release_amount' => 'decimal:2',
        'is_posted'      => 'boolean',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(ConstructionContract::class, 'contract_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}