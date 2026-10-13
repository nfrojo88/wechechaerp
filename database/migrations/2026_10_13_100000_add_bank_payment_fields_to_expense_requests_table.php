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
        if (Schema::hasTable('expense_requests')) {
            Schema::table('expense_requests', function (Blueprint $table) {
                if (!Schema::hasColumn('expense_requests', 'is_bank_payment')) {
                    $table->boolean('is_bank_payment')->default(false)->after('net_amount');
                }
                if (!Schema::hasColumn('expense_requests', 'bank_name')) {
                    $table->string('bank_name', 255)->nullable()->after('is_bank_payment');
                }
                if (!Schema::hasColumn('expense_requests', 'account_number')) {
                    $table->string('account_number', 100)->nullable()->after('bank_name');
                }
                if (!Schema::hasColumn('expense_requests', 'account_name')) {
                    $table->string('account_name', 255)->nullable()->after('account_number');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('expense_requests')) {
            Schema::table('expense_requests', function (Blueprint $table) {
                if (Schema::hasColumn('expense_requests', 'account_name')) {
                    $table->dropColumn('account_name');
                }
                if (Schema::hasColumn('expense_requests', 'account_number')) {
                    $table->dropColumn('account_number');
                }
                if (Schema::hasColumn('expense_requests', 'bank_name')) {
                    $table->dropColumn('bank_name');
                }
                if (Schema::hasColumn('expense_requests', 'is_bank_payment')) {
                    $table->dropColumn('is_bank_payment');
                }
            });
        }
    }
};
