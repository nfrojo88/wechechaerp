<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Main vehicle reminders table
        Schema::create('vehicle_reminders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fixed_asset_unit_id')->index();
            $table->unsignedBigInteger('fixed_asset_id')->index();
            $table->string('reminder_type', 50)->index(); // bolo|service_km|third_party_insurance|insurance
            $table->string('status', 30)->default('active')->index(); // active|expired|due_soon|renewed|serviced

            // Bolo
            $table->date('bolo_last_date')->nullable();
            $table->date('bolo_expiry_date')->nullable()->index();

            // Service by KM
            $table->integer('current_odometer_km')->nullable();
            $table->integer('last_service_km')->nullable();
            $table->integer('service_interval_km')->nullable();
            $table->integer('next_service_km')->nullable();
            $table->date('last_service_date')->nullable();
            $table->integer('reminder_threshold_km')->nullable()->default(500);

            // Insurance (both types)
            $table->string('insurance_company', 255)->nullable();
            $table->string('policy_number', 100)->nullable();
            $table->date('insurance_start_date')->nullable();
            $table->date('insurance_expiry_date')->nullable()->index();
            $table->decimal('premium_amount', 15, 2)->nullable();
            $table->string('coverage_type', 100)->nullable();

            // Shared
            $table->string('attachment', 500)->nullable();
            $table->text('notes')->nullable();
            $table->json('alert_days_before')->nullable();

            // Audit
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('fixed_asset_unit_id')->references('id')->on('fixed_asset_units')->onDelete('cascade');
            $table->foreign('fixed_asset_id')->references('id')->on('fixed_assets')->onDelete('cascade');
        });

        // History table
        Schema::create('vehicle_reminder_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vehicle_reminder_id')->index();
            $table->unsignedBigInteger('fixed_asset_unit_id')->index();
            $table->string('reminder_type', 50);
            $table->string('action', 50)->default('renewed');
            $table->json('snapshot')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('performed_by')->nullable();
            $table->timestamp('performed_at')->useCurrent();
            $table->timestamps();
            $table->foreign('vehicle_reminder_id')->references('id')->on('vehicle_reminders')->onDelete('cascade');
        });

        // Notification log
        Schema::create('vehicle_reminder_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vehicle_reminder_id')->index();
            $table->string('channel', 30)->default('in_app');
            $table->string('alert_type', 50)->nullable();
            $table->boolean('sent')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->text('message')->nullable();
            $table->timestamps();
            $table->foreign('vehicle_reminder_id')->references('id')->on('vehicle_reminders')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_reminder_notifications');
        Schema::dropIfExists('vehicle_reminder_history');
        Schema::dropIfExists('vehicle_reminders');
    }
};
