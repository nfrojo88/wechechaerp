<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Store extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'address',
        'type',
        'is_active',
        'project_id',
        'manager_id',
        'notes',
        'petty_cash_account_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function inventory()
    {
        return $this->hasMany(Inventory::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function slipSequences()
    {
        return $this->hasMany(SlipSequence::class);
    }

    public function pettyCashAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'petty_cash_account_id');
    }
}
