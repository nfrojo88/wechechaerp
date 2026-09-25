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
            // 1. Handle all COA transfers involving this account
            if (\Illuminate\Support\Facades\Schema::hasTable('coa_transfers')) {
                $transfers = \Illuminate\Support\Facades\DB::table('coa_transfers')
                    ->where('from_coa_id', $coaId)
                    ->orWhere('to_coa_id', $coaId)
                    ->get();

                $journalEntryIds = [];

                foreach ($transfers as $trf) {
                    if (!empty($trf->journal_entry_id)) {
                        $journalEntryIds[] = $trf->journal_entry_id;
                    }

                    // Revert balance on the counter-party account if requested
                    if ($reverseCounterPartyBalances) {
                        $amt = (float) $trf->amount;
                        if ($trf->from_coa_id == $coaId && !empty($trf->to_coa_id) && $trf->to_coa_id != $coaId) {
                            $other = self::find($trf->to_coa_id);
                            if ($other) {
                                if (in_array($other->type, ['asset', 'expense'])) {
                                    $other->decrement('current_balance', $amt);
                                } else {
                                    $other->increment('current_balance', $amt);
                                }
                            }
                        } elseif ($trf->to_coa_id == $coaId && !empty($trf->from_coa_id) && $trf->from_coa_id != $coaId) {
                            $other = self::find($trf->from_coa_id);
                            if ($other) {
                                if (in_array($other->type, ['asset', 'expense'])) {
                                    $other->increment('current_balance', $amt);
                                } else {
                                    $other->decrement('current_balance', $amt);
                                }
                            }
                        }
                    }

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

                // Delete associated journal entries and their lines
                if (!empty($journalEntryIds)) {
                    $journalEntryIds = array_unique($journalEntryIds);
                    if (\Illuminate\Support\Facades\Schema::hasTable('journal_entry_lines')) {
                        \Illuminate\Support\Facades\DB::table('journal_entry_lines')->whereIn('journal_entry_id', $journalEntryIds)->delete();
                    }
                    if (\Illuminate\Support\Facades\Schema::hasTable('journal_entries')) {
                        \Illuminate\Support\Facades\DB::table('journal_entries')->whereIn('id', $journalEntryIds)->delete();
                    }
                }
            }

            // 2. Delete any journal lines directly referencing this account
            if (\Illuminate\Support\Facades\Schema::hasTable('journal_entry_lines')) {
                $orphanQuery = \Illuminate\Support\Facades\DB::table('journal_entry_lines')->where('account_id', $coaId);
                if (\Illuminate\Support\Facades\Schema::hasColumn('journal_entry_lines', 'coa_id')) {
                    $orphanQuery->orWhere('coa_id', $coaId);
                }
                $jeIdsToCheck = $orphanQuery->pluck('journal_entry_id')->unique()->toArray();
                $orphanQuery->delete();

                if (\Illuminate\Support\Facades\Schema::hasTable('journal_entries') && !empty($jeIdsToCheck)) {
                    foreach ($jeIdsToCheck as $jeId) {
                        $hasLines = \Illuminate\Support\Facades\DB::table('journal_entry_lines')->where('journal_entry_id', $jeId)->exists();
                        if (!$hasLines) {
                            \Illuminate\Support\Facades\DB::table('journal_entries')->where('id', $jeId)->delete();
                        }
                    }
                }
            }

            // 3. Bank Accounts linked to this COA
            if (\Illuminate\Support\Facades\Schema::hasTable('bank_accounts')) {
                $bankAccountIds = \Illuminate\Support\Facades\DB::table('bank_accounts')->where('coa_id', $coaId)->pluck('id')->toArray();
                if (!empty($bankAccountIds)) {
                    if (\Illuminate\Support\Facades\Schema::hasTable('bank_transactions')) {
                        \Illuminate\Support\Facades\DB::table('bank_transactions')->whereIn('bank_account_id', $bankAccountIds)->delete();
                    }
                    \Illuminate\Support\Facades\DB::table('bank_accounts')->whereIn('id', $bankAccountIds)->delete();
                }
            }

            // 4. Other module references (safely nullify or delete)
            if (\Illuminate\Support\Facades\Schema::hasTable('petty_cash_replenishments')) {
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

            if (\Illuminate\Support\Facades\Schema::hasTable('credit_store_ledger') && \Illuminate\Support\Facades\Schema::hasColumn('credit_store_ledger', 'coa_account_id')) {
                \Illuminate\Support\Facades\DB::table('credit_store_ledger')->where('coa_account_id', $coaId)->update(['coa_account_id' => null]);
            }

            if (\Illuminate\Support\Facades\Schema::hasTable('tax_settlements') && \Illuminate\Support\Facades\Schema::hasColumn('tax_settlements', 'coa_id')) {
                \Illuminate\Support\Facades\DB::table('tax_settlements')->where('coa_id', $coaId)->update(['coa_id' => null]);
            }

            if (\Illuminate\Support\Facades\Schema::hasTable('office_material_requests') && \Illuminate\Support\Facades\Schema::hasColumn('office_material_requests', 'coa_id')) {
                \Illuminate\Support\Facades\DB::table('office_material_requests')->where('coa_id', $coaId)->update(['coa_id' => null]);
            }

            if (\Illuminate\Support\Facades\Schema::hasTable('stores') && \Illuminate\Support\Facades\Schema::hasColumn('stores', 'petty_cash_account_id')) {
                \Illuminate\Support\Facades\DB::table('stores')->where('petty_cash_account_id', $coaId)->update(['petty_cash_account_id' => null]);
            }

            if (\Illuminate\Support\Facades\Schema::hasTable('letters') && \Illuminate\Support\Facades\Schema::hasColumn('letters', 'chart_of_account_id')) {
                \Illuminate\Support\Facades\DB::table('letters')->where('chart_of_account_id', $coaId)->update(['chart_of_account_id' => null]);
            }

            if (\Illuminate\Support\Facades\Schema::hasTable('procurement_payments') && \Illuminate\Support\Facades\Schema::hasColumn('procurement_payments', 'chart_of_account_id')) {
                \Illuminate\Support\Facades\DB::table('procurement_payments')->where('chart_of_account_id', $coaId)->update(['chart_of_account_id' => null]);
            }

            // 5. Unparent any children
            \Illuminate\Support\Facades\DB::table('chart_of_accounts')->where('parent_id', $coaId)->update(['parent_id' => null]);

            // 6. Delete the chart of account itself (force delete)
            \Illuminate\Support\Facades\DB::table('chart_of_accounts')->where('id', $coaId)->delete();

            // 7. Log activity
            if (class_exists(\App\Models\ActivityLog::class)) {
                \App\Models\ActivityLog::log(
                    'deleted',
                    "Deleted Chart of Account [{$coa->code}] {$coa->name} and all associated transfers, journal entries, and references.",
                    'Finance / COA'
                );
            }
        });

        return true;
    }
}

