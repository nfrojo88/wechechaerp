<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PettyCashReplenishmentItem extends Model
{
    use HasFactory;

    protected $table = 'petty_cash_replenishment_items';

    protected $fillable = [
        'petty_cash_replenishment_id',
        'journal_entry_line_id',
        'entry_date',
        'reference',
        'description',
        'target_account_name',
        'amount',
        'side',
    ];

    protected $casts = [
        'amount'     => 'decimal:2',
        'entry_date' => 'date',
    ];

    public function replenishment()
    {
        return $this->belongsTo(PettyCashReplenishment::class, 'petty_cash_replenishment_id');
    }

    public function journalEntryLine()
    {
        return $this->belongsTo(JournalEntryLine::class, 'journal_entry_line_id');
    }
}
