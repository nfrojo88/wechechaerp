<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Make legacy 'code' column nullable (and other old NOT-NULL columns)
     * so the new ProductSeeder can insert using only the new schema fields.
     * Uses raw ALTER TABLE to avoid requiring doctrine/dbal.
     */
    public function up(): void
    {
        if (!Schema::hasTable('products') || DB::getDriverName() === 'sqlite') {
            return;
        }

        $columns = DB::select("SHOW COLUMNS FROM `products`");
        $colMap   = [];
        foreach ($columns as $col) {
            $colMap[$col->Field] = $col;
        }

        $alters = [];

        // Make 'code' nullable
        if (isset($colMap['code'])) {
            $alters[] = "MODIFY COLUMN `code` VARCHAR(50) NULL DEFAULT NULL";
        }

        // Make 'category' nullable (it might already be, but ensure it)
        if (isset($colMap['category']) && $colMap['category']->Null === 'NO') {
            $alters[] = "MODIFY COLUMN `category` VARCHAR(100) NULL DEFAULT NULL";
        }

        // Make 'description' nullable
        if (isset($colMap['description']) && $colMap['description']->Null === 'NO') {
            $alters[] = "MODIFY COLUMN `description` TEXT NULL";
        }

        // Make 'specification' nullable
        if (isset($colMap['specification']) && $colMap['specification']->Null === 'NO') {
            $alters[] = "MODIFY COLUMN `specification` VARCHAR(500) NULL DEFAULT NULL";
        }

        // Give 'standard_cost' a default of 0
        if (isset($colMap['standard_cost'])) {
            $alters[] = "MODIFY COLUMN `standard_cost` DECIMAL(15,2) NULL DEFAULT NULL";
        }

        // Give 'current_cost' a default of 0
        if (isset($colMap['current_cost'])) {
            $alters[] = "MODIFY COLUMN `current_cost` DECIMAL(15,2) NULL DEFAULT NULL";
        }

        // Give 'min_stock_level' a default of 0
        if (isset($colMap['min_stock_level'])) {
            $alters[] = "MODIFY COLUMN `min_stock_level` DECIMAL(15,3) NOT NULL DEFAULT '0'";
        }

        // Give 'reorder_level' a sensible default
        if (isset($colMap['reorder_level']) && str_contains(strtolower($colMap['reorder_level']->Type), 'decimal')) {
            $alters[] = "MODIFY COLUMN `reorder_level` DECIMAL(15,3) NOT NULL DEFAULT '0'";
        }

        // Give 'is_active' a default of 1
        if (isset($colMap['is_active'])) {
            $alters[] = "MODIFY COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT '1'";
        }

        // Give 'product_type' a default of 'material'
        if (isset($colMap['product_type'])) {
            $alters[] = "MODIFY COLUMN `product_type` VARCHAR(20) NOT NULL DEFAULT 'material'";
        }

        if (!empty($alters)) {
            DB::statement("ALTER TABLE `products` " . implode(', ', $alters));
        }
    }

    public function down(): void
    {
        // Intentionally left blank
    }
};
