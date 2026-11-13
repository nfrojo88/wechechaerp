<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalEntryLine extends Model
{
    protected $fillable = [
        'journal_entry_id', 'account_id', 'original_account_id', 'side', 'amount', 'description',
        'project_id', 'contract_id', 'cost_center_id', 'site_id', 'party_type', 'party_id',
        'is_installed', 'exclude_from_poc'
    ];

    public function journalEntry() { return $this->belongsTo(JournalEntry::class); }
    public function account()      { return $this->belongsTo(ChartOfAccount::class, 'account_id'); }
    public function coa()          { return $this->belongsTo(ChartOfAccount::class, 'account_id'); }
}
