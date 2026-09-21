<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('stores') && !Schema::hasColumn('stores', 'petty_cash_account_id')) {
            Schema::table('stores', function (Blueprint $table) {
                $table->foreignId('petty_cash_account_id')->nullable()->after('notes')->constrained('chart_of_accounts')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('stores') && Schema::hasColumn('stores', 'petty_cash_account_id')) {
            Schema::table('stores', function (Blueprint $table) {
                $table->dropForeign(['petty_cash_account_id']);
                $table->dropColumn('petty_cash_account_id');
            });
        }
    }
};
