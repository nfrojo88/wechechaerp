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
        if (Schema::hasTable('attendance')) {
            try {
                $driver = DB::getDriverName();
                if ($driver === 'mysql') {
                    // Widen source from ENUM('manual', 'biometric', 'mobile', ...) to VARCHAR(50)
                    // This allows 'site_dispatch' and other dynamic sources without MySQL 1265 truncation errors
                    DB::statement("ALTER TABLE `attendance` MODIFY `source` VARCHAR(50) NOT NULL DEFAULT 'manual'");
                    DB::statement("ALTER TABLE `attendance` MODIFY `status` VARCHAR(50) NOT NULL DEFAULT 'present'");
                }
            } catch (\Throwable $e) {
                // Ignore if driver differs or column already altered
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('attendance')) {
            try {
                $driver = DB::getDriverName();
                if ($driver === 'mysql') {
                    DB::statement("ALTER TABLE `attendance` MODIFY `source` ENUM('manual', 'biometric', 'mobile', 'device', 'bulk_upload', 'site_dispatch') NOT NULL DEFAULT 'manual'");
                }
            } catch (\Throwable $e) {
                // Ignore
            }
        }
    }
};
