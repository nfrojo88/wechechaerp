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
        if (Schema::hasTable('vehicle_fuel_requests')) {
            Schema::table('vehicle_fuel_requests', function (Blueprint $table) {
                if (!Schema::hasColumn('vehicle_fuel_requests', 'fs_no')) {
                    $table->string('fs_no', 50)->nullable()->after('fuel_station_tin')->index();
                }
                if (!Schema::hasColumn('vehicle_fuel_requests', 'mrc_no')) {
                    $table->string('mrc_no', 50)->nullable()->after('fs_no');
                }
                if (!Schema::hasColumn('vehicle_fuel_requests', 'tax_type')) {
                    $table->string('tax_type', 30)->default('no_vat')->after('mrc_no')->index();
                }
                if (!Schema::hasColumn('vehicle_fuel_requests', 'no_vat_reason_code')) {
                    $table->string('no_vat_reason_code', 50)->nullable()->after('tax_type')->index();
                }
                if (!Schema::hasColumn('vehicle_fuel_requests', 'no_vat_reason_note')) {
                    $table->string('no_vat_reason_note', 255)->nullable()->after('no_vat_reason_code');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('vehicle_fuel_requests')) {
            Schema::table('vehicle_fuel_requests', function (Blueprint $table) {
                $columns = ['fs_no', 'mrc_no', 'tax_type', 'no_vat_reason_code', 'no_vat_reason_note'];
                foreach ($columns as $col) {
                    if (Schema::hasColumn('vehicle_fuel_requests', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
