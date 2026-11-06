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
        if (Schema::hasTable('utility_expenses')) {
            Schema::table('utility_expenses', function (Blueprint $table) {
                if (!Schema::hasColumn('utility_expenses', 'gross_amount')) {
                    $table->decimal('gross_amount', 15, 2)->nullable()->after('amount');
                }
                if (!Schema::hasColumn('utility_expenses', 'vat_type')) {
                    $table->string('vat_type', 30)->default('none')->after('gross_amount');
                }
                if (!Schema::hasColumn('utility_expenses', 'vat_rate')) {
                    $table->decimal('vat_rate', 5, 2)->default(15.00)->after('vat_type');
                }
                if (!Schema::hasColumn('utility_expenses', 'vat_amount')) {
                    $table->decimal('vat_amount', 15, 2)->default(0.00)->after('vat_rate');
                }
                if (!Schema::hasColumn('utility_expenses', 'has_withholding')) {
                    $table->boolean('has_withholding')->default(false)->after('vat_amount');
                }
                if (!Schema::hasColumn('utility_expenses', 'withholding_rate')) {
                    $table->decimal('withholding_rate', 5, 2)->default(3.00)->after('has_withholding');
                }
                if (!Schema::hasColumn('utility_expenses', 'withholding_amount')) {
                    $table->decimal('withholding_amount', 15, 2)->default(0.00)->after('withholding_rate');
                }
                if (!Schema::hasColumn('utility_expenses', 'withholding_receipt')) {
                    $table->string('withholding_receipt', 500)->nullable()->after('withholding_amount');
                }
                if (!Schema::hasColumn('utility_expenses', 'withholding_receipt_number')) {
                    $table->string('withholding_receipt_number', 100)->nullable()->after('withholding_receipt');
                }
                if (!Schema::hasColumn('utility_expenses', 'net_amount')) {
                    $table->decimal('net_amount', 15, 2)->nullable()->after('withholding_receipt_number');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('utility_expenses')) {
            Schema::table('utility_expenses', function (Blueprint $table) {
                $columns = [
                    'gross_amount',
                    'vat_type',
                    'vat_rate',
                    'vat_amount',
                    'has_withholding',
                    'withholding_rate',
                    'withholding_amount',
                    'withholding_receipt',
                    'withholding_receipt_number',
                    'net_amount',
                ];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('utility_expenses', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
