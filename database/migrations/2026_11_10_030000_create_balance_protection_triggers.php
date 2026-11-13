<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Enforces database-level blocking of direct writes to chart_of_accounts.current_balance
     * and bank_accounts.current_balance via raw SQL, DB::table, or bypass updates.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::unprepared("DROP TRIGGER IF EXISTS trg_coa_block_direct_balance_update;");
            DB::unprepared("
                CREATE TRIGGER trg_coa_block_direct_balance_update
                BEFORE UPDATE ON chart_of_accounts
                FOR EACH ROW
                BEGIN
                    IF NOT (OLD.current_balance <=> NEW.current_balance) THEN
                        IF @gl_posting_in_progress IS NULL OR @gl_posting_in_progress <> 1 THEN
                            SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'Direct update of chart_of_accounts.current_balance is blocked. Balances derive only from posted journals.';
                        END IF;
                    END IF;
                END;
            ");

            DB::unprepared("DROP TRIGGER IF EXISTS trg_bank_account_block_direct_balance_update;");
            DB::unprepared("
                CREATE TRIGGER trg_bank_account_block_direct_balance_update
                BEFORE UPDATE ON bank_accounts
                FOR EACH ROW
                BEGIN
                    IF NOT (OLD.current_balance <=> NEW.current_balance) THEN
                        IF @gl_posting_in_progress IS NULL OR @gl_posting_in_progress <> 1 THEN
                            SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'Direct update of bank_accounts.current_balance is blocked. Balances derive only from posted journals.';
                        END IF;
                    END IF;
                END;
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::unprepared("DROP TRIGGER IF EXISTS trg_coa_block_direct_balance_update;");
            DB::unprepared("DROP TRIGGER IF EXISTS trg_bank_account_block_direct_balance_update;");
        }
    }
};
