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
        if (Schema::hasTable('fixed_asset_assignments')) {
            try {
                // Primary method: Modify column directly via raw SQL
                DB::statement("ALTER TABLE `fixed_asset_assignments` MODIFY `employee_id` BIGINT UNSIGNED NULL;");
            } catch (\Throwable $e) {
                try {
                    // Fallback for MySQL environments requiring foreign key drop before alter
                    DB::statement("ALTER TABLE `fixed_asset_assignments` DROP FOREIGN KEY `fixed_asset_assignments_employee_id_foreign`;");
                    DB::statement("ALTER TABLE `fixed_asset_assignments` MODIFY `employee_id` BIGINT UNSIGNED NULL;");
                    DB::statement("ALTER TABLE `fixed_asset_assignments` ADD CONSTRAINT `fixed_asset_assignments_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;");
                } catch (\Throwable $e2) {
                    try {
                        Schema::table('fixed_asset_assignments', function (Blueprint $table) {
                            $table->unsignedBigInteger('employee_id')->nullable()->change();
                        });
                    } catch (\Throwable $e3) {
                        // Suppressed if already nullable or doctrine/dbal not present
                    }
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Safe no-op to prevent data loss for transfer records where employee_id is legitimately null
    }
};
