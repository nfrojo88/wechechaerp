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
        try {
            \Illuminate\Support\Facades\DB::statement('ALTER TABLE `delivery_receipts` MODIFY `purchase_order_id` BIGINT UNSIGNED NULL');
        } catch (\Throwable $e) {}

        try {
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE `delivery_receipts` MODIFY `status` VARCHAR(50) NOT NULL DEFAULT 'verified'");
        } catch (\Throwable $e) {}
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Keep flexible
    }
};