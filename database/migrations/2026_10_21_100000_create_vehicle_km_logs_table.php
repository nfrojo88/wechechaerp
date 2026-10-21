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
        if (!Schema::hasTable('vehicle_km_logs')) {
            Schema::create('vehicle_km_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('fixed_asset_unit_id')->index();
                $table->unsignedBigInteger('driver_employee_id')->nullable()->index();
                $table->unsignedBigInteger('logged_by_user_id')->nullable()->index();
                $table->integer('odometer_km')->index();
                $table->date('km_date')->index();
                $table->string('odometer_photo', 500)->nullable();
                $table->string('source', 50)->default('direct_log')->index(); // direct_log | fuel_request | maintenance | initial
                $table->integer('km_difference')->nullable(); // difference from previous log
                $table->boolean('is_large_jump')->default(false)->index();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('fixed_asset_unit_id')->references('id')->on('fixed_asset_units')->onDelete('cascade');
                $table->foreign('driver_employee_id')->references('id')->on('employees')->onDelete('set null');
                $table->foreign('logged_by_user_id')->references('id')->on('users')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_km_logs');
    }
};
