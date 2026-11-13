<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientIpcCollection extends Model
{
    use HasFactory;

    protected $table = 'client_ipc_collections';

    protected $fillable = [
        'client_ipc_id',
        'bank_account_id',
        'collection_date',
        'gross_collected_amount',
        'client_wht_rate_percent',
        'client_wht_amount',
        'wht_receipt_number',
        'net_bank_amount',
        'is_posted',
        'journal_entry_id',
        'created_by',
    ];

    protected $casts = [
        'collection_date'         => 'date',
        'gross_collected_amount'  => 'decimal:2',
        'client_wht_rate_percent' => 'decimal:2',
        'client_wht_amount'       => 'decimal:2',
        'net_bank_amount'         => 'decimal:2',
        'is_posted'               => 'boolean',
    ];

    public function ipc(): BelongsTo
    {
        return $this->belongsTo(ClientIpc::class, 'client_ipc_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}