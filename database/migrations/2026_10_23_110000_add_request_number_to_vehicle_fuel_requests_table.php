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
                if (!Schema::hasColumn('vehicle_fuel_requests', 'request_number')) {
                    $table->string('request_number', 50)->nullable()->index();
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
                if (Schema::hasColumn('vehicle_fuel_requests', 'request_number')) {
                    $table->dropColumn('request_number');
                }
            });
        }
    }
};
