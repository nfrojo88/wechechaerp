<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('maintenance_requests')) {
            // Alter columns to VARCHAR to prevent MySQL enum truncation errors
            try {
                DB::statement("ALTER TABLE `maintenance_requests` MODIFY `issue_type` VARCHAR(100) NOT NULL DEFAULT 'other'");
                DB::statement("ALTER TABLE `maintenance_requests` MODIFY `status` VARCHAR(100) NOT NULL DEFAULT 'pending'");
                DB::statement("ALTER TABLE `maintenance_requests` MODIFY `urgency` VARCHAR(100) NOT NULL DEFAULT 'normal'");
            } catch (\Throwable $e) {
                // Fallback for non-MySQL or platforms where DB statement syntax differs
                Schema::table('maintenance_requests', function (Blueprint $table) {
                    $table->string('issue_type', 100)->default('other')->change();
                    $table->string('status', 100)->default('pending')->change();
                    $table->string('urgency', 100)->default('normal')->change();
                });
            }
        }
    }

    public function down(): void
    {
        // No-op to preserve string flexibility
    }
};
