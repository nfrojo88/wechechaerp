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
        if (Schema::hasTable('income_records')) {
            Schema::table('income_records', function (Blueprint $table) {
                if (!Schema::hasColumn('income_records', 'chart_of_account_id')) {
                    $table->foreignId('chart_of_account_id')->nullable()->after('bank_account_id')->constrained('chart_of_accounts')->nullOnDelete();
                }
                if (!Schema::hasColumn('income_records', 'is_deposited')) {
                    $table->boolean('is_deposited')->default(false)->after('status');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('income_records')) {
            Schema::table('income_records', function (Blueprint $table) {
                if (Schema::hasColumn('income_records', 'chart_of_account_id')) {
                    $table->dropForeign(['chart_of_account_id']);
                    $table->dropColumn('chart_of_account_id');
                }
                if (Schema::hasColumn('income_records', 'is_deposited')) {
                    $table->dropColumn('is_deposited');
                }
            });
        }
    }
};
