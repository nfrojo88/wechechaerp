<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('procurement_sms_logs')) {
            Schema::table('procurement_sms_logs', function (Blueprint $table) {
                if (!Schema::hasColumn('procurement_sms_logs', 'material_request_id')) {
                    $table->unsignedBigInteger('material_request_id')->nullable()->after('purchase_request_id');
                }
            });

            // For MySQL, allow purchase_request_id to be nullable and expand status enum to string
            try {
                DB::statement("ALTER TABLE `procurement_sms_logs` MODIFY `purchase_request_id` BIGINT UNSIGNED NULL;");
            } catch (\Throwable $e) {}

            try {
                DB::statement("ALTER TABLE `procurement_sms_logs` MODIFY `status` VARCHAR(32) NOT NULL DEFAULT 'sent';");
            } catch (\Throwable $e) {}
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('procurement_sms_logs')) {
            Schema::table('procurement_sms_logs', function (Blueprint $table) {
                if (Schema::hasColumn('procurement_sms_logs', 'material_request_id')) {
                    $table->dropColumn('material_request_id');
                }
            });
        }
    }
};
