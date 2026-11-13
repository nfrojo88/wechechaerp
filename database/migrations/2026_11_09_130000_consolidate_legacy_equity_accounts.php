<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Consolidate legacy beginning-equity accounts (3149, 3151, 3152) into 3152 Retained Earnings (Prior Years).
     * Enforces audit logging and preserves the original account id in original_account_id column.
     */
    public function up(): void
    {
        // 1. Ensure account 3152 is standardized as Retained Earnings (Prior Years)
        $acc3152 = DB::table('chart_of_accounts')->where('code', '3152')->first();
        if ($acc3152) {
            DB::table('chart_of_accounts')
                ->where('code', '3152')
                ->update([
                    'name'        => 'Retained Earnings (Prior Years)',
                    'type'        => 'equity',
                    'subtype'     => 'retained_earnings',
                    'description' => 'Consolidated cumulative prior-period retained surplus/deficit',
                    'normal_side' => 'credit',
                    'is_active'   => true,
                ]);
        } else {
            $id = DB::table('chart_of_accounts')->insertGetId([
                'code'            => '3152',
                'name'            => 'Retained Earnings (Prior Years)',
                'type'            => 'equity',
                'subtype'         => 'retained_earnings',
                'description'     => 'Consolidated cumulative prior-period retained surplus/deficit',
                'normal_side'     => 'credit',
                'opening_balance' => 0.00,
                'current_balance' => 0.00,
                'is_active'       => true,
                'is_system'       => true,
                'sort_order'      => 3152,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
            $acc3152 = DB::table('chart_of_accounts')->where('id', $id)->first();
        }

        // 2. Remap any historical journal lines from 3149 and 3151 to 3152 with audit tracking
        $legacyAccounts = DB::table('chart_of_accounts')
            ->whereIn('code', ['3149', '3151'])
            ->get();

        $legacyAccountIds = $legacyAccounts->pluck('id')->toArray();

        if (!empty($legacyAccountIds) && $acc3152) {
            // Find all affected lines before remapping
            $affectedLines = DB::table('journal_entry_lines')
                ->whereIn('account_id', $legacyAccountIds)
                ->get();

            foreach ($affectedLines as $line) {
                // Log audit trail for each remapped line
                if (Schema::hasTable('financial_audit_trails')) {
                    DB::table('financial_audit_trails')->insert([
                        'auditable_type' => 'App\\Models\\JournalEntryLine',
                        'auditable_id'   => $line->id,
                        'action'         => 'equity_remap',
                        'user_id'        => null,
                        'user_role'      => 'system_migration',
                        'reason'         => "Remapped from legacy account ID {$line->account_id} to 3152 Retained Earnings (Prior Years)",
                        'payload'        => json_encode([
                            'journal_entry_id'    => $line->journal_entry_id,
                            'original_account_id' => $line->account_id,
                            'new_account_id'      => $acc3152->id,
                            'amount'              => $line->amount,
                            'side'                => $line->side,
                        ]),
                        'ip_address'     => '127.0.0.1',
                        'created_at'     => now(),
                        'updated_at'     => now(),
                    ]);
                }
            }

            // Remap lines while preserving original_account_id if column exists
            $updateData = ['account_id' => $acc3152->id];
            if (Schema::hasColumn('journal_entry_lines', 'original_account_id')) {
                DB::table('journal_entry_lines')
                    ->whereIn('account_id', $legacyAccountIds)
                    ->update([
                        'original_account_id' => DB::raw('account_id'),
                        'account_id'          => $acc3152->id,
                    ]);
            } else {
                DB::table('journal_entry_lines')
                    ->whereIn('account_id', $legacyAccountIds)
                    ->update($updateData);
            }

            // Deprecate 3149 and 3151
            DB::table('chart_of_accounts')
                ->whereIn('code', ['3149', '3151'])
                ->update([
                    'is_active'       => false,
                    'opening_balance' => 0.00,
                    'current_balance' => 0.00,
                    'description'     => 'Deprecated: Consolidated into 3152 Retained Earnings (Prior Years)',
                    'updated_at'      => now(),
                ]);
        }

        // 3. Ensure 3100 Paid-Up Capital and 3150 Legal Reserve are standardized
        DB::table('chart_of_accounts')
            ->where('code', '3100')
            ->update([
                'name'        => 'Paid-Up Capital',
                'type'        => 'equity',
                'subtype'     => 'paid_up_capital',
                'normal_side' => 'credit',
                'is_active'   => true,
            ]);

        DB::table('chart_of_accounts')
            ->where('code', '3150')
            ->update([
                'name'        => 'Legal Reserve',
                'type'        => 'equity',
                'subtype'     => 'legal_reserve',
                'normal_side' => 'credit',
                'is_active'   => true,
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore remapped lines if original_account_id was preserved
        if (Schema::hasColumn('journal_entry_lines', 'original_account_id')) {
            DB::table('journal_entry_lines')
                ->whereNotNull('original_account_id')
                ->update([
                    'account_id'          => DB::raw('original_account_id'),
                    'original_account_id' => null,
                ]);
        }

        // Re-enable legacy accounts 3149 & 3151
        DB::table('chart_of_accounts')
            ->where('code', '3149')
            ->update([
                'is_active'   => true,
                'description' => 'equity dose not closed',
            ]);

        DB::table('chart_of_accounts')
            ->where('code', '3151')
            ->update([
                'is_active'   => true,
                'description' => 'equity dose not closed',
            ]);

        // Revert 3152 name
        DB::table('chart_of_accounts')
            ->where('code', '3152')
            ->update([
                'name'        => 'Beginning Equity',
                'description' => 'equity-retend earning',
            ]);

        // Revert 3100 name
        DB::table('chart_of_accounts')
            ->where('code', '3100')
            ->update([
                'name' => 'Registered Capital',
            ]);
    }
};
