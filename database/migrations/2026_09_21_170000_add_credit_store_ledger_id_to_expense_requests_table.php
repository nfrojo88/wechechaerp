<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('expense_requests')) {
            Schema::table('expense_requests', function (Blueprint $table) {
                if (!Schema::hasColumn('expense_requests', 'credit_store_ledger_id')) {
                    $table->foreignId('credit_store_ledger_id')->nullable()->after('purchase_request_id')->constrained('credit_store_ledgers')->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('credit_store_payments')) {
            Schema::table('credit_store_payments', function (Blueprint $table) {
                if (!Schema::hasColumn('credit_store_payments', 'expense_request_id')) {
                    $table->foreignId('expense_request_id')->nullable()->after('credit_store_ledger_id')->constrained('expense_requests')->nullOnDelete();
                }
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('credit_store_payments')) {
            Schema::table('credit_store_payments', function (Blueprint $table) {
                if (Schema::hasColumn('credit_store_payments', 'expense_request_id')) {
                    $table->dropForeign(['expense_request_id']);
                    $table->dropColumn('expense_request_id');
                }
            });
        }

        if (Schema::hasTable('expense_requests')) {
            Schema::table('expense_requests', function (Blueprint $table) {
                if (Schema::hasColumn('expense_requests', 'credit_store_ledger_id')) {
                    $table->dropForeign(['credit_store_ledger_id']);
                    $table->dropColumn('credit_store_ledger_id');
                }
            });
        }
    }
};
