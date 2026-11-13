<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PettyCashSubledger extends Model
{
    protected $table = 'petty_cash_subledgers';

    protected $fillable = [
        'code',
        'name',
        'control_account_id',
        'custodian_id',
        'project_id',
        'store_id',
        'imprest_limit',
        'opening_balance',
        'current_balance',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'imprest_limit'   => 'decimal:2',
        'opening_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'is_active'       => 'boolean',
    ];

    public function controlAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'control_account_id');
    }

    public function custodian()
    {
        return $this->belongsTo(User::class, 'custodian_id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    /**
     * Get the consolidated total of all subledger current balances.
     */
    public static function totalSubledgerBalance(): float
    {
        return (float) static::where('is_active', true)->sum('current_balance');
    }

    /**
     * Validate subledger total against the GL control account (1010).
     */
    public static function reconcileWithControlAccount(): array
    {
        $controlAccount = ChartOfAccount::where('code', '1010')->first();
        $controlBalance = $controlAccount ? (float)$controlAccount->ledger_balance : 0.0;
        $subledgerTotal = static::totalSubledgerBalance();
        $discrepancy    = round($controlBalance - $subledgerTotal, 2);

        return [
            'control_account_code'    => '1010',
            'control_account_balance' => $controlBalance,
            'subledger_total'         => $subledgerTotal,
            'discrepancy'             => $discrepancy,
            'is_reconciled'           => abs($discrepancy) < 0.01,
        ];
    }
}
