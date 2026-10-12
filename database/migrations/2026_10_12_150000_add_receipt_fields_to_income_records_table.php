<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('income_records')) {
            try {
                if (DB::getDriverName() === 'mysql') {
                    DB::statement("ALTER TABLE income_records MODIFY category VARCHAR(50) NOT NULL DEFAULT 'other'");
                }
            } catch (\Throwable $e) {}

            Schema::table('income_records', function (Blueprint $table) {
                if (!Schema::hasColumn('income_records', 'seller_tin')) {
                    $table->string('seller_tin', 50)->nullable()->default('38480010')->after('category');
                }
                if (!Schema::hasColumn('income_records', 'buyer_tin')) {
                    $table->string('buyer_tin', 50)->nullable()->index()->after('seller_tin');
                }
                if (!Schema::hasColumn('income_records', 'buyer_name')) {
                    $table->string('buyer_name', 255)->nullable()->after('buyer_tin');
                }
                if (!Schema::hasColumn('income_records', 'transaction_type')) {
                    $table->string('transaction_type', 100)->nullable()->default('Cash Sales')->after('buyer_name');
                }
                if (!Schema::hasColumn('income_records', 'receipt_category')) {
                    $table->string('receipt_category', 100)->nullable()->default('Cash Sales Fiscal Receipt')->after('transaction_type');
                }
                if (!Schema::hasColumn('income_records', 'receipt_used')) {
                    $table->string('receipt_used', 100)->nullable()->default('Fiscal Cash Register')->after('receipt_category');
                }
                if (!Schema::hasColumn('income_records', 'mrc_no')) {
                    $table->string('mrc_no', 100)->nullable()->after('receipt_used');
                }
                if (!Schema::hasColumn('income_records', 'receipt_no')) {
                    $table->string('receipt_no', 100)->nullable()->index()->after('mrc_no');
                }
                if (!Schema::hasColumn('income_records', 'commodity_type')) {
                    $table->string('commodity_type', 100)->nullable()->default('Services')->after('receipt_no');
                }
                if (!Schema::hasColumn('income_records', 'quantity')) {
                    $table->decimal('quantity', 12, 2)->default(1.00)->after('commodity_type');
                }
                if (!Schema::hasColumn('income_records', 'unit_price')) {
                    $table->decimal('unit_price', 18, 2)->nullable()->after('quantity');
                }
                if (!Schema::hasColumn('income_records', 'receipt_date')) {
                    $table->date('receipt_date')->nullable()->after('unit_price');
                }
                if (!Schema::hasColumn('income_records', 'encoded_by')) {
                    $table->string('encoded_by', 100)->nullable()->default('wechacha')->after('receipt_date');
                }
                if (!Schema::hasColumn('income_records', 'receipt_file_path')) {
                    $table->string('receipt_file_path', 255)->nullable()->after('encoded_by');
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
                $columns = [
                    'seller_tin', 'buyer_tin', 'buyer_name', 'transaction_type',
                    'receipt_category', 'receipt_used', 'mrc_no', 'receipt_no',
                    'commodity_type', 'quantity', 'unit_price', 'receipt_date',
                    'encoded_by', 'receipt_file_path',
                ];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('income_records', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
