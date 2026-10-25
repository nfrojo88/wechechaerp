<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('procurement_receipts')) {
            Schema::table('procurement_receipts', function (Blueprint $table) {
                if (!Schema::hasColumn('procurement_receipts', 'withholding_receipt_path')) {
                    $table->string('withholding_receipt_path', 500)->nullable()->after('original_filename');
                }
                if (!Schema::hasColumn('procurement_receipts', 'withholding_receipt_number')) {
                    $table->string('withholding_receipt_number', 100)->nullable()->after('withholding_receipt_path');
                }
                if (!Schema::hasColumn('procurement_receipts', 'has_withholding')) {
                    $table->boolean('has_withholding')->default(false)->after('withholding_receipt_number');
                }
                if (!Schema::hasColumn('procurement_receipts', 'withholding_rate')) {
                    $table->decimal('withholding_rate', 5, 2)->default(2.00)->after('has_withholding');
                }
                if (!Schema::hasColumn('procurement_receipts', 'withholding_amount')) {
                    $table->decimal('withholding_amount', 18, 2)->default(0)->after('withholding_rate');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('procurement_receipts')) {
            Schema::table('procurement_receipts', function (Blueprint $table) {
                $cols = ['withholding_receipt_path', 'withholding_receipt_number', 'has_withholding', 'withholding_rate', 'withholding_amount'];
                foreach ($cols as $col) {
                    if (Schema::hasColumn('procurement_receipts', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
