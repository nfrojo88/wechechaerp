<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_payments', function (Blueprint $table) {
            if (!Schema::hasColumn('procurement_payments', 'gross_amount')) {
                $table->decimal('gross_amount', 18, 2)->nullable()->after('amount');
            }
            if (!Schema::hasColumn('procurement_payments', 'vat_type')) {
                $table->string('vat_type', 30)->default('none')->after('gross_amount');
            }
            if (!Schema::hasColumn('procurement_payments', 'vat_rate')) {
                $table->decimal('vat_rate', 5, 2)->default(15.00)->after('vat_type');
            }
            if (!Schema::hasColumn('procurement_payments', 'vat_amount')) {
                $table->decimal('vat_amount', 18, 2)->default(0)->after('vat_rate');
            }
            if (!Schema::hasColumn('procurement_payments', 'has_withholding')) {
                $table->boolean('has_withholding')->default(false)->after('vat_amount');
            }
            if (!Schema::hasColumn('procurement_payments', 'withholding_rate')) {
                $table->decimal('withholding_rate', 5, 2)->default(2.00)->after('has_withholding');
            }
            if (!Schema::hasColumn('procurement_payments', 'withholding_amount')) {
                $table->decimal('withholding_amount', 18, 2)->default(0)->after('withholding_rate');
            }
            if (!Schema::hasColumn('procurement_payments', 'withholding_receipt')) {
                $table->string('withholding_receipt', 500)->nullable()->after('withholding_amount');
            }
            if (!Schema::hasColumn('procurement_payments', 'withholding_receipt_number')) {
                $table->string('withholding_receipt_number', 100)->nullable()->after('withholding_receipt');
            }
            if (!Schema::hasColumn('procurement_payments', 'net_amount')) {
                $table->decimal('net_amount', 18, 2)->nullable()->after('withholding_receipt_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('procurement_payments', function (Blueprint $table) {
            $cols = [
                'gross_amount', 'vat_type', 'vat_rate', 'vat_amount',
                'has_withholding', 'withholding_rate', 'withholding_amount',
                'withholding_receipt', 'withholding_receipt_number', 'net_amount'
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('procurement_payments', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
