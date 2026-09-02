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
        if (Schema::hasTable('letters')) {
            Schema::table('letters', function (Blueprint $table) {
                if (!Schema::hasColumn('letters', 'payment_amount')) {
                    $table->decimal('payment_amount', 15, 2)->nullable()->after('closing_notes');
                }
                if (!Schema::hasColumn('letters', 'payment_reference')) {
                    $table->string('payment_reference', 100)->nullable()->after('payment_amount');
                }
                if (!Schema::hasColumn('letters', 'paid_from_account')) {
                    $table->string('paid_from_account', 150)->nullable()->after('payment_reference');
                }
                if (!Schema::hasColumn('letters', 'chart_of_account_id')) {
                    $table->foreignId('chart_of_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete()->after('paid_from_account');
                }
                if (!Schema::hasColumn('letters', 'bank_account_id')) {
                    $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete()->after('chart_of_account_id');
                }
                if (!Schema::hasColumn('letters', 'expense_request_id')) {
                    $table->foreignId('expense_request_id')->nullable()->constrained('expense_requests')->nullOnDelete()->after('bank_account_id');
                }
                if (!Schema::hasColumn('letters', 'expense_id')) {
                    $table->foreignId('expense_id')->nullable()->constrained('expenses')->nullOnDelete()->after('expense_request_id');
                }
                if (!Schema::hasColumn('letters', 'payment_voucher_path')) {
                    $table->string('payment_voucher_path', 500)->nullable()->after('expense_id');
                }
                if (!Schema::hasColumn('letters', 'paid_at')) {
                    $table->timestamp('paid_at')->nullable()->after('payment_voucher_path');
                }
                if (!Schema::hasColumn('letters', 'paid_by')) {
                    $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete()->after('paid_at');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('letters')) {
            Schema::table('letters', function (Blueprint $table) {
                $columns = [
                    'payment_amount',
                    'payment_reference',
                    'paid_from_account',
                    'chart_of_account_id',
                    'bank_account_id',
                    'expense_request_id',
                    'expense_id',
                    'payment_voucher_path',
                    'paid_at',
                    'paid_by',
                ];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('letters', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
