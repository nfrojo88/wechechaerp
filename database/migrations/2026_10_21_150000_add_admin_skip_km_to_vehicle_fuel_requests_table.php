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
                if (!Schema::hasColumn('vehicle_fuel_requests', 'is_km_skipped')) {
                    $table->boolean('is_km_skipped')->default(false)->after('current_odometer_km')->index();
                }
                if (!Schema::hasColumn('vehicle_fuel_requests', 'price_per_liter')) {
                    $table->decimal('price_per_liter', 10, 2)->nullable()->after('liters_requested');
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
                if (Schema::hasColumn('vehicle_fuel_requests', 'price_per_liter')) {
                    $table->dropColumn('price_per_liter');
                }
                if (Schema::hasColumn('vehicle_fuel_requests', 'is_km_skipped')) {
                    $table->dropColumn('is_km_skipped');
                }
            });
        }
    }
};
