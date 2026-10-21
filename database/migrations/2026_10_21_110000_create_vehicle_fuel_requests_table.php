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
        if (!Schema::hasTable('vehicle_fuel_requests')) {
            Schema::create('vehicle_fuel_requests', function (Blueprint $table) {
                $table->id();
                $table->string('request_no', 50)->unique()->index();
                $table->unsignedBigInteger('fixed_asset_unit_id')->index();
                $table->unsignedBigInteger('driver_employee_id')->nullable()->index();
                $table->unsignedBigInteger('requested_by_user_id')->nullable()->index();
                $table->unsignedBigInteger('driver_booking_id')->nullable()->index();
                $table->unsignedBigInteger('project_id')->nullable()->index();
                $table->unsignedBigInteger('expense_request_id')->nullable()->index();

                // Request details
                $table->integer('current_odometer_km');
                $table->string('fuel_type', 30)->default('diesel'); // diesel | benzine
                $table->decimal('liters_requested', 10, 2);
                $table->decimal('estimated_amount', 15, 2);
                $table->text('reason')->nullable();
                $table->string('status', 50)->default('pending_finance')->index(); // pending_finance | assigned | paid | receipt_attached | closed | rejected

                // Service reminder snapshot at time of request
                $table->boolean('service_due_warning')->default(false)->index();
                $table->integer('service_due_km')->nullable();

                // Reconciliation & Fuel Station Receipt (after fueling)
                $table->string('receipt_photo', 500)->nullable();
                $table->integer('receipt_odometer_km')->nullable();
                $table->decimal('actual_liters', 10, 2)->nullable();
                $table->decimal('actual_amount', 15, 2)->nullable();
                $table->string('fuel_station_name', 255)->nullable();
                $table->string('fuel_station_tin', 50)->nullable();
                $table->timestamp('receipt_uploaded_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->unsignedBigInteger('closed_by')->nullable()->index();

                $table->timestamps();
                $table->softDeletes();

                $table->foreign('fixed_asset_unit_id')->references('id')->on('fixed_asset_units')->onDelete('cascade');
                $table->foreign('driver_employee_id')->references('id')->on('employees')->onDelete('set null');
                $table->foreign('requested_by_user_id')->references('id')->on('users')->onDelete('set null');
                if (Schema::hasTable('driver_bookings')) {
                    $table->foreign('driver_booking_id')->references('id')->on('driver_bookings')->onDelete('set null');
                }
                if (Schema::hasTable('projects')) {
                    $table->foreign('project_id')->references('id')->on('projects')->onDelete('set null');
                }
                if (Schema::hasTable('expense_requests')) {
                    $table->foreign('expense_request_id')->references('id')->on('expense_requests')->onDelete('set null');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_fuel_requests');
    }
};
