<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChartOfAccount extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code', 'name', 'parent_id', 'type', 'subtype', 'is_active',
        'is_system', 'opening_balance', 'current_balance', 'description', 'sort_order',
        'assigned_to'
    ];

    protected $casts = ['is_active' => 'boolean', 'is_system' => 'boolean'];

    public function parent()    { return $this->belongsTo(ChartOfAccount::class, 'parent_id'); }
    public function children()  { return $this->hasMany(ChartOfAccount::class, 'parent_id'); }
    public function bankAccounts() { return $this->hasMany(BankAccount::class, 'coa_id'); }

    public function journalLines()
    {
        return $this->hasMany(JournalEntryLine::class, 'account_id');
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function replenishments()
    {
        return $this->hasMany(PettyCashReplenishment::class, 'chart_of_account_id');
    }
}
