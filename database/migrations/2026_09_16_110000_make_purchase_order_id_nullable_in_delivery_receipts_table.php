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
        Schema::table('delivery_receipts', function (Blueprint $table) {
            // Make purchase_order_id nullable
            if (Schema::hasColumn('delivery_receipts', 'purchase_order_id')) {
                try {
                    $table->unsignedBigInteger('purchase_order_id')->nullable()->change();
                } catch (\Throwable $e) {
                    try {
                        \Illuminate\Support\Facades\DB::statement('ALTER TABLE `delivery_receipts` MODIFY `purchase_order_id` BIGINT UNSIGNED NULL');
                    } catch (\Throwable $e2) {}
                }
            }

            // Widen status column from enum to varchar(50) so 'verified', 'received', 'draft', 'rejected' are all accepted
            if (Schema::hasColumn('delivery_receipts', 'status')) {
                try {
                    $table->string('status', 50)->default('verified')->change();
                } catch (\Throwable $e) {
                    try {
                        \Illuminate\Support\Facades\DB::statement("ALTER TABLE `delivery_receipts` MODIFY `status` VARCHAR(50) NOT NULL DEFAULT 'verified'");
                    } catch (\Throwable $e2) {}
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Keep flexible
    }
};