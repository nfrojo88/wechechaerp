<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entry_lines', function (Blueprint $table) {
            // Covering index for high-volume ledger balance aggregation
            // (account_id, journal_entry_id, side, amount)
            $table->index(['account_id', 'journal_entry_id', 'side', 'amount'], 'idx_jel_covering_acc_je_side_amt');
            
            // Ensure foreign key / join index exists if missing
            $existing = DB::select("SHOW INDEXES FROM journal_entry_lines WHERE Key_name = 'idx_jel_entry_account'");
            if (empty($existing)) {
                $table->index(['journal_entry_id', 'account_id'], 'idx_jel_entry_account');
            }
        });
    }

    public function down(): void
    {
        Schema::table('journal_entry_lines', function (Blueprint $table) {
            try {
                $table->dropIndex('idx_jel_covering_acc_je_side_amt');
            } catch (\Throwable $e) {}
        });
    }
};
