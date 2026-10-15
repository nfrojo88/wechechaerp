<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalEntryLine extends Model
{
    protected $fillable = ['journal_entry_id', 'account_id', 'side', 'amount', 'description'];

    public function journalEntry() { return $this->belongsTo(JournalEntry::class); }
    public function account()      { return $this->belongsTo(ChartOfAccount::class, 'account_id'); }
    public function coa()          { return $this->belongsTo(ChartOfAccount::class, 'account_id'); }
}
