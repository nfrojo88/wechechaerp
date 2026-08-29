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
        Schema::table('expense_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('expense_requests', 'gross_amount')) {
                $table->decimal('gross_amount', 14, 2)->nullable()->after('amount');
            }
            if (!Schema::hasColumn('expense_requests', 'vat_type')) {
                $table->string('vat_type', 30)->default('none')->after('gross_amount'); // none, inclusive, exclusive, vat_b
            }
            if (!Schema::hasColumn('expense_requests', 'vat_rate')) {
                $table->decimal('vat_rate', 5, 2)->default(15.00)->after('vat_type');
            }
            if (!Schema::hasColumn('expense_requests', 'vat_amount')) {
                $table->decimal('vat_amount', 14, 2)->default(0)->after('vat_rate');
            }
            if (!Schema::hasColumn('expense_requests', 'has_withholding')) {
                $table->boolean('has_withholding')->default(false)->after('vat_amount');
            }
            if (!Schema::hasColumn('expense_requests', 'withholding_rate')) {
                $table->decimal('withholding_rate', 5, 2)->default(3.00)->after('has_withholding');
            }

            if (!Schema::hasColumn('expense_requests', 'withholding_amount')) {
                $table->decimal('withholding_amount', 14, 2)->default(0)->after('withholding_rate');
            }
            if (!Schema::hasColumn('expense_requests', 'net_amount')) {
                $table->decimal('net_amount', 14, 2)->nullable()->after('withholding_amount');
            }
            if (!Schema::hasColumn('expense_requests', 'service_type')) {
                $table->string('service_type', 100)->nullable()->after('net_amount');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expense_requests', function (Blueprint $table) {
            $columns = [
                'gross_amount', 'vat_type', 'vat_rate', 'vat_amount',
                'has_withholding', 'withholding_rate', 'withholding_amount',
                'net_amount', 'service_type'
            ];
            foreach ($columns as $col) {
                if (Schema::hasColumn('expense_requests', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
