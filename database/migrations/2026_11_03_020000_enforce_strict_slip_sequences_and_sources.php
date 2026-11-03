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
        Schema::table('delivery_receipts', function (Blueprint $table) {
            if (!Schema::hasColumn('delivery_receipts', 'slip_no')) {
                $table->string('slip_no', 100)->nullable()->after('dr_no');
            }
            if (!Schema::hasColumn('delivery_receipts', 'source_type')) {
                $table->string('source_type', 50)->default('manual')->after('slip_type');
            }
            if (!Schema::hasColumn('delivery_receipts', 'void_reason')) {
                $table->text('void_reason')->nullable()->after('is_void');
            }
            if (!Schema::hasColumn('delivery_receipts', 'voided_by')) {
                $table->foreignId('voided_by')->nullable()->after('void_reason')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('delivery_receipts', 'voided_at')) {
                $table->timestamp('voided_at')->nullable()->after('voided_by');
            }
            if (!Schema::hasColumn('delivery_receipts', 'transfer_id')) {
                $table->foreignId('transfer_id')->nullable()->after('purchase_request_id')->constrained('transfers')->nullOnDelete();
            }
        });

        // Backfill slip_no and source_type for existing records without modifying history
        try {
            DB::statement("UPDATE delivery_receipts SET slip_no = dr_no WHERE slip_no IS NULL OR slip_no = ''");
        } catch (\Throwable $e) {}

        try {
            DB::statement("UPDATE delivery_receipts SET source_type = 'procurement' WHERE purchase_request_id IS NOT NULL AND (source_type IS NULL OR source_type = 'manual')");
        } catch (\Throwable $e) {}

        try {
            DB::statement("UPDATE delivery_receipts SET source_type = 'void' WHERE is_void = 1");
        } catch (\Throwable $e) {}

        try {
            DB::statement("UPDATE delivery_receipts SET source_type = 'petty_cash' WHERE (notes LIKE '%Petty Cash%' OR supplier_name LIKE '%Petty Cash%') AND (source_type IS NULL OR source_type = 'manual')");
        } catch (\Throwable $e) {}

        try {
            DB::statement("UPDATE delivery_receipts SET source_type = 'transfer_out' WHERE slip_type = 'send' AND (source_type IS NULL OR source_type = 'manual')");
        } catch (\Throwable $e) {}

        // Add index on (store_id, slip_type, slip_no)
        try {
            Schema::table('delivery_receipts', function (Blueprint $table) {
                $table->index(['store_id', 'slip_type', 'slip_no'], 'idx_store_slip_type_no');
            });
        } catch (\Throwable $e) {}
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_receipts', function (Blueprint $table) {
            try {
                $table->dropIndex('idx_store_slip_type_no');
            } catch (\Throwable $e) {}

            $table->dropColumnIfExists([
                'slip_no',
                'source_type',
                'void_reason',
                'voided_by',
                'voided_at',
                'transfer_id',
            ]);
        });
    }
};
