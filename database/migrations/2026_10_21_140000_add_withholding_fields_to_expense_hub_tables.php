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
        if (Schema::hasTable('expense_hub_vat_decisions')) {
            Schema::table('expense_hub_vat_decisions', function (Blueprint $table) {
                if (!Schema::hasColumn('expense_hub_vat_decisions', 'has_withholding')) {
                    $table->boolean('has_withholding')->default(false)->after('vat_status')->index();
                }
                if (!Schema::hasColumn('expense_hub_vat_decisions', 'withholding_rate')) {
                    $table->decimal('withholding_rate', 5, 2)->default(3.00)->after('has_withholding');
                }
                if (!Schema::hasColumn('expense_hub_vat_decisions', 'withholding_amount')) {
                    $table->decimal('withholding_amount', 18, 2)->default(0.00)->after('withholding_rate');
                }
                if (!Schema::hasColumn('expense_hub_vat_decisions', 'withholding_receipt')) {
                    $table->string('withholding_receipt', 500)->nullable()->after('withholding_amount');
                }
                if (!Schema::hasColumn('expense_hub_vat_decisions', 'withholding_receipt_number')) {
                    $table->string('withholding_receipt_number', 100)->nullable()->after('withholding_receipt');
                }
                if (!Schema::hasColumn('expense_hub_vat_decisions', 'net_payable_amount')) {
                    $table->decimal('net_payable_amount', 18, 2)->nullable()->after('withholding_receipt_number');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('expense_hub_vat_decisions')) {
            Schema::table('expense_hub_vat_decisions', function (Blueprint $table) {
                $columns = [
                    'has_withholding',
                    'withholding_rate',
                    'withholding_amount',
                    'withholding_receipt',
                    'withholding_receipt_number',
                    'net_payable_amount',
                ];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('expense_hub_vat_decisions', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
