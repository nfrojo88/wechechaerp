<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalEntry extends Model
{
    protected $fillable = [
        'entry_no', 'entry_date', 'reference_type', 'reference_id',
        'description', 'status', 'created_by', 'approved_by', 'posted_at',
        'is_locked', 'locked_at', 'locked_by',
        'reopened_at', 'reopened_by', 'reopen_reason',
        'is_reversal', 'reversal_of_id', 'reversed_by_id',
    ];

    protected $casts = [
        'entry_date'   => 'date',
        'posted_at'    => 'datetime',
        'locked_at'    => 'datetime',
        'reopened_at'  => 'datetime',
        'is_locked'    => 'boolean',
        'is_reversal'  => 'boolean',
    ];

    public function createdBy()    { return $this->belongsTo(User::class, 'created_by'); }
    public function creator()      { return $this->belongsTo(User::class, 'created_by'); }
    public function approvedBy()   { return $this->belongsTo(User::class, 'approved_by'); }
    public function lockedByUser() { return $this->belongsTo(User::class, 'locked_by'); }
    public function reversedBy()   { return $this->belongsTo(JournalEntry::class, 'reversed_by_id'); }
    public function reversalOf()   { return $this->belongsTo(JournalEntry::class, 'reversal_of_id'); }

    public function lines()
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    public function getTotalDebitsAttribute()
    {
        return $this->lines()->where('side', 'debit')->sum('amount');
    }

    public function getTotalCreditsAttribute()
    {
        return $this->lines()->where('side', 'credit')->sum('amount');
    }
}
