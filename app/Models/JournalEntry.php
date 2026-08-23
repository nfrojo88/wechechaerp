<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalEntry extends Model
{
    protected $fillable = [
        'entry_no', 'entry_date', 'reference_type', 'reference_id',
        'description', 'status', 'created_by', 'approved_by', 'posted_at',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'posted_at'  => 'datetime',
    ];

    public function createdBy()  { return $this->belongsTo(User::class, 'created_by'); }
    public function creator()    { return $this->belongsTo(User::class, 'created_by'); }
    public function approvedBy() { return $this->belongsTo(User::class, 'approved_by'); }

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
