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
        if (Schema::hasTable('products')) {
            try {
                DB::statement("ALTER TABLE `products` MODIFY `max_stock` DECIMAL(15,2) NULL DEFAULT 100.00");
                DB::statement("ALTER TABLE `products` MODIFY `reorder_level` DECIMAL(15,2) NULL DEFAULT 20.00");
                DB::statement("ALTER TABLE `products` MODIFY `unit_price` DECIMAL(15,2) NULL DEFAULT 0.00");
                DB::statement("ALTER TABLE `products` MODIFY `selling_price` DECIMAL(15,2) NULL DEFAULT 0.00");
                DB::statement("ALTER TABLE `products` MODIFY `standard_length` DECIMAL(15,2) NULL DEFAULT 0.00");
                DB::statement("ALTER TABLE `products` MODIFY `standard_width` DECIMAL(15,3) NULL DEFAULT 0.000");
                DB::statement("ALTER TABLE `products` MODIFY `purchase_threshold` DECIMAL(15,2) NULL DEFAULT 5.00");
            } catch (\Throwable $e) {
                Schema::table('products', function (Blueprint $table) {
                    $table->decimal('max_stock', 15, 2)->nullable()->default(100.00)->change();
                    $table->decimal('reorder_level', 15, 2)->nullable()->default(20.00)->change();
                    $table->decimal('unit_price', 15, 2)->nullable()->default(0.00)->change();
                    $table->decimal('selling_price', 15, 2)->nullable()->default(0.00)->change();
                    $table->decimal('standard_length', 15, 2)->nullable()->default(0.00)->change();
                    $table->decimal('standard_width', 15, 3)->nullable()->default(0.000)->change();
                    $table->decimal('purchase_threshold', 15, 2)->nullable()->default(5.00)->change();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op to preserve nullability flexibility
    }
};
