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

    protected static function booted()
    {
        static::created(function (Store $store) {
            try {
                $store->autoCreateSitePettyCash();
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Failed to auto-create site petty cash for store #{$store->id}: " . $e->getMessage());
            }
        });
    }

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

    /**
     * Resolve or automatically create a dedicated Site Petty Cash account for this store/site.
     */
    public function autoCreateSitePettyCash(?User $custodian = null): ChartOfAccount
    {
        // 1. Direct link on store
        if (\Illuminate\Support\Facades\Schema::hasColumn('stores', 'petty_cash_account_id') && !empty($this->petty_cash_account_id)) {
            $existing = ChartOfAccount::find($this->petty_cash_account_id);
            if ($existing && $existing->code !== '1010') {
                $this->syncProjectPettyCash($existing->id);
                return $existing;
            }
        }

        // 2. Find by store pattern: [STORE:{id}] or code 1010-{id} or 'Site Petty Cash - {store->name}'
        $patternAccount = ChartOfAccount::where('type', 'asset')
            ->where('code', '!=', '1010')
            ->where(function ($q) {
                $q->where('code', '1010-' . str_pad($this->id, 3, '0', STR_PAD_LEFT))
                  ->orWhere('code', 'PC-' . $this->id)
                  ->orWhere('name', 'Site Petty Cash - ' . $this->name)
                  ->orWhere('description', 'like', '%[STORE:' . $this->id . ']%');
            })
            ->first();

        if ($patternAccount) {
            if (\Illuminate\Support\Facades\Schema::hasColumn('stores', 'petty_cash_account_id') && $this->petty_cash_account_id !== $patternAccount->id) {
                try {
                    $this->updateQuietly(['petty_cash_account_id' => $patternAccount->id]);
                } catch (\Throwable $e) {}
            }
            $this->syncProjectPettyCash($patternAccount->id);
            return $patternAccount;
        }

        // 3. If custodian or manager assigned has a dedicated petty cash account
        $targetUser = $custodian ?: ($this->manager ?: ($this->manager_id ? User::find($this->manager_id) : null));
        if ($targetUser) {
            $userAssigned = ChartOfAccount::where('assigned_to', $targetUser->id)
                ->where('code', '!=', '1010')
                ->where(function($q) {
                    $q->where('subtype', 'petty_cash')
                      ->orWhere('subtype', 'cash')
                      ->orWhere('name', 'like', '%petty%');
                })
                ->first();
            if ($userAssigned) {
                if (\Illuminate\Support\Facades\Schema::hasColumn('stores', 'petty_cash_account_id')) {
                    try {
                        $this->updateQuietly(['petty_cash_account_id' => $userAssigned->id]);
                    } catch (\Throwable $e) {}
                }
                $this->syncProjectPettyCash($userAssigned->id);
                return $userAssigned;
            }
        }

        // 4. Automatically generate a unique account code (e.g. 1010-001)
        $storeNum = $this->id ? str_pad($this->id, 3, '0', STR_PAD_LEFT) : rand(100, 999);
        $baseCode = '1010-' . $storeNum;
        $code = $baseCode;
        $counter = 1;
        while (ChartOfAccount::where('code', $code)->exists()) {
            $code = $baseCode . '-' . $counter;
            $counter++;
        }

        // Clean name (avoid double "Site Petty Cash" prefix)
        $cleanName = preg_replace('/^Site Petty Cash\s*[-:]?\s*/i', '', $this->name);
        $accountName = 'Site Petty Cash - ' . $cleanName;

        // Resolve corporate parent account (code 1010) if exists
        $parentCoaId = ChartOfAccount::where('code', '1010')->value('id');

        $siteAccount = ChartOfAccount::create([
            'code'            => $code,
            'name'            => $accountName,
            'type'            => 'asset',
            'subtype'         => 'cash',
            'parent_id'       => $parentCoaId,
            'is_active'       => true,
            'is_system'       => false,
            'opening_balance' => 0.00,
            'current_balance' => 0.00,
            'description'     => "Dedicated Site Petty Cash fund for Store/Site: {$this->name} ({$this->code}) [STORE:{$this->id}]",
            'assigned_to'     => $this->manager_id ?? $custodian?->id ?? auth()->id(),
            'sort_order'      => 10,
        ]);

        if (\Illuminate\Support\Facades\Schema::hasColumn('stores', 'petty_cash_account_id')) {
            try {
                $this->updateQuietly(['petty_cash_account_id' => $siteAccount->id]);
            } catch (\Throwable $e) {}
        }

        $this->syncProjectPettyCash($siteAccount->id);

        return $siteAccount;
    }

    /**
     * Synchronize linked project's petty cash account reference.
     */
    protected function syncProjectPettyCash(int $accountId): void
    {
        if (!empty($this->project_id) && \Illuminate\Support\Facades\Schema::hasTable('projects') && \Illuminate\Support\Facades\Schema::hasColumn('projects', 'petty_cash_account_id')) {
            try {
                \Illuminate\Support\Facades\DB::table('projects')
                    ->where('id', $this->project_id)
                    ->whereNull('petty_cash_account_id')
                    ->update(['petty_cash_account_id' => $accountId]);
            } catch (\Throwable $e) {}
        }
    }
}
