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
        'assigned_to', 'normal_side', 'is_control_account', 'subledger_type'
    ];

    protected $casts = ['is_active' => 'boolean', 'is_system' => 'boolean'];

    protected static function booted()
    {
        static::updating(function ($account) {
            // Model Guard: Block any external write to current_balance unless explicitly authorized by JournalEngine
            if ($account->isDirty('current_balance')) {
                if (!app()->bound('gl_posting_in_progress') || !app('gl_posting_in_progress')) {
                    throw new \RuntimeException("Direct update of chart_of_accounts.current_balance is strictly prohibited. Balances derive exclusively from posted journal lines via JournalEngine.");
                }
            }
        });
    }

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

    /**
     * Compute authoritative ledger balance strictly from posted journal lines.
     * Uses real column names: journal_entry_lines.side and journal_entry_lines.amount.
     */
    public function getLedgerBalanceAttribute(): float
    {
        $normalSide = $this->normal_side;
        if (empty($normalSide) || !in_array($normalSide, ['debit', 'credit'])) {
            $normalSide = in_array($this->type, ['liability', 'equity', 'revenue']) ? 'credit' : 'debit';
        }

        $res = JournalEntryLine::selectRaw("
            COUNT(*) as line_count,
            SUM(CASE WHEN journal_entry_lines.side = 'debit'  THEN journal_entry_lines.amount ELSE 0 END) as debits,
            SUM(CASE WHEN journal_entry_lines.side = 'credit' THEN journal_entry_lines.amount ELSE 0 END) as credits
        ")
        ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
        ->where('journal_entries.status', 'posted')
        ->where('journal_entry_lines.account_id', $this->id)
        ->first();

        $lineCount = (int) ($res->line_count ?? 0);
        if ($lineCount === 0 && (float)$this->current_balance != 0) {
            return (float) $this->current_balance;
        }

        $debits  = (float) ($res->debits ?? 0.0);
        $credits = (float) ($res->credits ?? 0.0);

        return $normalSide === 'debit' ? round($debits - $credits, 2) : round($credits - $debits, 2);
    }

    /**
     * Compute authoritative ledger balances for multiple accounts in a single grouped query.
     * Prevents N+1 database queries across large account lists.
     *
     * @param iterable|array $accounts Collection or array of ChartOfAccount models or account IDs
     * @return array [account_id => ledger_balance]
     */
    public static function getBatchLedgerBalances($accounts = []): array
    {
        $accList = collect($accounts);
        if ($accList->isEmpty()) {
            return [];
        }

        if (is_numeric($accList->first())) {
            $accountModels = self::whereIn('id', $accList)->get()->keyBy('id');
            $ids = $accList->toArray();
        } else {
            $accountModels = $accList->keyBy('id');
            $ids = $accList->pluck('id')->filter()->toArray();
        }

        if (empty($ids)) {
            return [];
        }

        $res = JournalEntryLine::selectRaw("
            journal_entry_lines.account_id,
            COUNT(*) as line_count,
            SUM(CASE WHEN journal_entry_lines.side = 'debit'  THEN journal_entry_lines.amount ELSE 0 END) as debits,
            SUM(CASE WHEN journal_entry_lines.side = 'credit' THEN journal_entry_lines.amount ELSE 0 END) as credits
        ")
        ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
        ->where('journal_entries.status', 'posted')
        ->whereIn('journal_entry_lines.account_id', $ids)
        ->groupBy('journal_entry_lines.account_id')
        ->get()
        ->keyBy('account_id');

        $balances = [];
        foreach ($accountModels as $id => $acc) {
            $line = $res->get($id);
            $lineCount = (int) ($line->line_count ?? 0);
            if ($lineCount === 0 && (float)$acc->current_balance != 0) {
                $balances[$id] = (float) $acc->current_balance;
                continue;
            }

            $normalSide = $acc->normal_side;
            if (empty($normalSide) || !in_array($normalSide, ['debit', 'credit'])) {
                $normalSide = in_array($acc->type, ['liability', 'equity', 'revenue']) ? 'credit' : 'debit';
            }

            $debits  = (float) ($line->debits ?? 0.0);
            $credits = (float) ($line->credits ?? 0.0);
            $balances[$id] = $normalSide === 'debit' ? round($debits - $credits, 2) : round($credits - $debits, 2);
        }

        return $balances;
    }



    /**
     * Permanently delete a Chart of Account and cascade-clean all transfer history,
     * associated journal entries/lines, bank accounts, and module references.
     * Optionally reverses the balance impact on counter-party accounts.
     */
    public static function deleteAccountAndRelated($coaOrCode, bool $reverseCounterPartyBalances = true): bool
    {
        $coa = $coaOrCode instanceof self
            ? $coaOrCode
            : self::withTrashed()->where('code', $coaOrCode)->orWhere('id', $coaOrCode)->first();

        if (!$coa) {
            return false;
        }

        $coaId = $coa->id;

        \Illuminate\Support\Facades\DB::transaction(function () use ($coa, $coaId, $reverseCounterPartyBalances) {
            $driver = \Illuminate\Support\Facades\DB::connection()->getDriverName();
            try {
                if ($driver === 'mysql') {
                    \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=0;');
                } elseif ($driver === 'sqlite') {
                    \Illuminate\Support\Facades\DB::statement('PRAGMA foreign_keys = OFF;');
                }

                $journalEntryIds = [];

                // 1. Handle all COA transfers involving this account
                if (\Illuminate\Support\Facades\Schema::hasTable('coa_transfers')) {
                    $transfers = \Illuminate\Support\Facades\DB::table('coa_transfers')
                        ->where('from_coa_id', $coaId)
                        ->orWhere('to_coa_id', $coaId)
                        ->get();

                    foreach ($transfers as $trf) {
                        if (!empty($trf->journal_entry_id)) {
                            $journalEntryIds[] = $trf->journal_entry_id;
                        }

                        // Balances derive dynamically from posted lines; no manual decrement needed

                        // Delete attachment if exists
                        if (!empty($trf->attachment_path) && class_exists(\App\Services\FileUploadService::class)) {
                            \App\Services\FileUploadService::delete($trf->attachment_path);
                        }
                    }

                    // Delete the transfers themselves
                    \Illuminate\Support\Facades\DB::table('coa_transfers')
                        ->where('from_coa_id', $coaId)
                        ->orWhere('to_coa_id', $coaId)
                        ->delete();
                }

                // 2. Delete any journal lines directly referencing this account
                if (\Illuminate\Support\Facades\Schema::hasTable('journal_entry_lines')) {
                    $orphanQuery = \Illuminate\Support\Facades\DB::table('journal_entry_lines')->where('account_id', $coaId);
                    if (\Illuminate\Support\Facades\Schema::hasColumn('journal_entry_lines', 'coa_id')) {
                        $orphanQuery->orWhere('coa_id', $coaId);
                    }
                    $jeIdsFromLines = $orphanQuery->pluck('journal_entry_id')->unique()->toArray();
                    $orphanQuery->delete();

                    $journalEntryIds = array_unique(array_merge($journalEntryIds, $jeIdsFromLines));
                }

                // Clean up empty journal entries if present
                if (\Illuminate\Support\Facades\Schema::hasTable('journal_entries') && !empty($journalEntryIds)) {
                    // Nullify references to these journal entries before deleting
                    if (\Illuminate\Support\Facades\Schema::hasTable('income_records') && \Illuminate\Support\Facades\Schema::hasColumn('income_records', 'journal_entry_id')) {
                        \Illuminate\Support\Facades\DB::table('income_records')->whereIn('journal_entry_id', $journalEntryIds)->update(['journal_entry_id' => null]);
                    }
                    if (\Illuminate\Support\Facades\Schema::hasTable('petty_cash_replenishments') && \Illuminate\Support\Facades\Schema::hasColumn('petty_cash_replenishments', 'journal_entry_id')) {
                        \Illuminate\Support\Facades\DB::table('petty_cash_replenishments')->whereIn('journal_entry_id', $journalEntryIds)->update(['journal_entry_id' => null]);
                    }

                    foreach ($journalEntryIds as $jeId) {
                        $hasLines = \Illuminate\Support\Facades\DB::table('journal_entry_lines')->where('journal_entry_id', $jeId)->exists();
                        if (!$hasLines) {
                            \Illuminate\Support\Facades\DB::table('journal_entries')->where('id', $jeId)->delete();
                        }
                    }
                }

                // 3. Bank Accounts linked to this COA: Safely unlink coa_id without destroying bank accounts or violating foreign keys
                if (\Illuminate\Support\Facades\Schema::hasTable('bank_accounts')) {
                    \Illuminate\Support\Facades\DB::table('bank_accounts')->where('coa_id', $coaId)->update(['coa_id' => null]);
                }

                // 4. Safely nullify or clean all referencing module tables
                if (\Illuminate\Support\Facades\Schema::hasTable('petty_cash_replenishments')) {
                    $pcrIds = \Illuminate\Support\Facades\DB::table('petty_cash_replenishments')
                        ->where('chart_of_account_id', $coaId)
                        ->orWhere('source_coa_id', $coaId)
                        ->pluck('id')->toArray();

                    if (!empty($pcrIds) && \Illuminate\Support\Facades\Schema::hasTable('petty_cash_replenishment_items')) {
                        \Illuminate\Support\Facades\DB::table('petty_cash_replenishment_items')->whereIn('petty_cash_replenishment_id', $pcrIds)->delete();
                    }

                    if (\Illuminate\Support\Facades\Schema::hasColumn('petty_cash_replenishments', 'chart_of_account_id')) {
                        \Illuminate\Support\Facades\DB::table('petty_cash_replenishments')->where('chart_of_account_id', $coaId)->delete();
                    }
                    if (\Illuminate\Support\Facades\Schema::hasColumn('petty_cash_replenishments', 'source_coa_id')) {
                        \Illuminate\Support\Facades\DB::table('petty_cash_replenishments')->where('source_coa_id', $coaId)->update(['source_coa_id' => null]);
                    }
                }

                if (\Illuminate\Support\Facades\Schema::hasTable('expense_requests')) {
                    if (\Illuminate\Support\Facades\Schema::hasColumn('expense_requests', 'coa_id')) {
                        \Illuminate\Support\Facades\DB::table('expense_requests')->where('coa_id', $coaId)->update(['coa_id' => null]);
                    }
                    if (\Illuminate\Support\Facades\Schema::hasColumn('expense_requests', 'chart_of_account_id')) {
                        \Illuminate\Support\Facades\DB::table('expense_requests')->where('chart_of_account_id', $coaId)->update(['chart_of_account_id' => null]);
                    }
                }

                if (\Illuminate\Support\Facades\Schema::hasTable('income_records') && \Illuminate\Support\Facades\Schema::hasColumn('income_records', 'chart_of_account_id')) {
                    \Illuminate\Support\Facades\DB::table('income_records')->where('chart_of_account_id', $coaId)->update(['chart_of_account_id' => null]);
                }

                if (\Illuminate\Support\Facades\Schema::hasTable('projects') && \Illuminate\Support\Facades\Schema::hasColumn('projects', 'petty_cash_account_id')) {
                    \Illuminate\Support\Facades\DB::table('projects')->where('petty_cash_account_id', $coaId)->update(['petty_cash_account_id' => null]);
                }

                if (\Illuminate\Support\Facades\Schema::hasTable('stores') && \Illuminate\Support\Facades\Schema::hasColumn('stores', 'petty_cash_account_id')) {
                    \Illuminate\Support\Facades\DB::table('stores')->where('petty_cash_account_id', $coaId)->update(['petty_cash_account_id' => null]);
                }

                if (\Illuminate\Support\Facades\Schema::hasTable('petty_cash_material_purchases') && \Illuminate\Support\Facades\Schema::hasColumn('petty_cash_material_purchases', 'chart_of_account_id')) {
                    \Illuminate\Support\Facades\DB::table('petty_cash_material_purchases')->where('chart_of_account_id', $coaId)->update(['chart_of_account_id' => null]);
                }

                if (\Illuminate\Support\Facades\Schema::hasTable('credit_store_ledger') && \Illuminate\Support\Facades\Schema::hasColumn('credit_store_ledger', 'coa_account_id')) {
                    \Illuminate\Support\Facades\DB::table('credit_store_ledger')->where('coa_account_id', $coaId)->update(['coa_account_id' => null]);
                }

                if (\Illuminate\Support\Facades\Schema::hasTable('tax_settlements') && \Illuminate\Support\Facades\Schema::hasColumn('tax_settlements', 'coa_id')) {
                    \Illuminate\Support\Facades\DB::table('tax_settlements')->where('coa_id', $coaId)->update(['coa_id' => null]);
                }

                if (\Illuminate\Support\Facades\Schema::hasTable('office_material_requests') && \Illuminate\Support\Facades\Schema::hasColumn('office_material_requests', 'coa_id')) {
                    \Illuminate\Support\Facades\DB::table('office_material_requests')->where('coa_id', $coaId)->update(['coa_id' => null]);
                }

                if (\Illuminate\Support\Facades\Schema::hasTable('letters') && \Illuminate\Support\Facades\Schema::hasColumn('letters', 'chart_of_account_id')) {
                    \Illuminate\Support\Facades\DB::table('letters')->where('chart_of_account_id', $coaId)->update(['chart_of_account_id' => null]);
                }

                if (\Illuminate\Support\Facades\Schema::hasTable('procurement_payments') && \Illuminate\Support\Facades\Schema::hasColumn('procurement_payments', 'chart_of_account_id')) {
                    \Illuminate\Support\Facades\DB::table('procurement_payments')->where('chart_of_account_id', $coaId)->update(['chart_of_account_id' => null]);
                }

                if (\Illuminate\Support\Facades\Schema::hasTable('procurement_purchase_orders') && \Illuminate\Support\Facades\Schema::hasColumn('procurement_purchase_orders', 'coa_account_id')) {
                    \Illuminate\Support\Facades\DB::table('procurement_purchase_orders')->where('coa_account_id', $coaId)->update(['coa_account_id' => null]);
                }

                // 5. Unparent any children
                \Illuminate\Support\Facades\DB::table('chart_of_accounts')->where('parent_id', $coaId)->update(['parent_id' => null]);

                // 6. Delete the chart of account itself (force delete)
                \Illuminate\Support\Facades\DB::table('chart_of_accounts')->where('id', $coaId)->delete();

                // 7. Log activity
                if (class_exists(\App\Models\ActivityLog::class)) {
                    \App\Models\ActivityLog::log(
                        'deleted',
                        "Deleted Chart of Account [{$coa->code}] {$coa->name} and all associated references.",
                        'Finance / COA'
                    );
                }
            } finally {
                if ($driver === 'mysql') {
                    \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=1;');
                } elseif ($driver === 'sqlite') {
                    \Illuminate\Support\Facades\DB::statement('PRAGMA foreign_keys = ON;');
                }
            }
        });

        return true;
    }
}

