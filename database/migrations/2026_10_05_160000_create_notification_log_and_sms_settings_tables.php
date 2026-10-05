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
        // 1. Notification Log Table (stores every SMS handoff attempt & result)
        if (!Schema::hasTable('notification_log')) {
            Schema::create('notification_log', function (Blueprint $table) {
                $table->id();
                $table->string('request_type', 40)->default('purchase_request')->index(); // material_request, purchase_request, transfer
                $table->unsignedBigInteger('request_id')->nullable()->index();
                $table->string('action', 80)->index(); // handoff action key
                $table->unsignedBigInteger('sender_user_id')->nullable();
                $table->unsignedBigInteger('recipient_user_id')->nullable();
                $table->unsignedBigInteger('recipient_employee_id')->nullable();
                $table->string('role', 60)->index();
                $table->string('phone', 35)->index();
                $table->text('message');
                $table->string('status', 20)->default('sent')->index(); // sent, failed
                $table->text('error')->nullable();
                $table->unsignedTinyInteger('retries')->default(0);
                $table->timestamps();

                $table->foreign('sender_user_id')->references('id')->on('users')->nullOnDelete();
                $table->foreign('recipient_user_id')->references('id')->on('users')->nullOnDelete();
                $table->foreign('recipient_employee_id')->references('id')->on('employees')->nullOnDelete();

                $table->index(['request_type', 'request_id']);
            });
        }

        // 2. Procurement SMS Settings Table (admin toggles & editable templates)
        if (!Schema::hasTable('procurement_sms_settings')) {
            Schema::create('procurement_sms_settings', function (Blueprint $table) {
                $table->id();
                $table->string('handoff_key', 80)->unique();
                $table->string('name', 120);
                $table->string('sender_role', 60);
                $table->string('target_role', 60);
                $table->boolean('is_enabled')->default(true);
                $table->text('template');
                $table->string('description', 255)->nullable();
                $table->timestamps();
            });
        }

        // 3. Driver Bookings delivery tracking
        if (Schema::hasTable('driver_bookings') && !Schema::hasColumn('driver_bookings', 'delivered_at')) {
            Schema::table('driver_bookings', function (Blueprint $table) {
                $table->timestamp('delivered_at')->nullable()->after('scheduled_at');
                $table->text('delivery_notes')->nullable()->after('delivered_at');
            });
        }

        // 4. Purchase Requests 3-Way Match tracking
        if (Schema::hasTable('purchase_requests') && !Schema::hasColumn('purchase_requests', 'three_way_matched_at')) {
            Schema::table('purchase_requests', function (Blueprint $table) {
                $table->timestamp('three_way_matched_at')->nullable()->after('approved_at');
                $table->unsignedBigInteger('three_way_matched_by')->nullable()->after('three_way_matched_at');
                $table->foreign('three_way_matched_by')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_log');
        Schema::dropIfExists('procurement_sms_settings');

        if (Schema::hasTable('driver_bookings') && Schema::hasColumn('driver_bookings', 'delivered_at')) {
            Schema::table('driver_bookings', function (Blueprint $table) {
                $table->dropColumn(['delivered_at', 'delivery_notes']);
            });
        }

        if (Schema::hasTable('purchase_requests') && Schema::hasColumn('purchase_requests', 'three_way_matched_at')) {
            Schema::table('purchase_requests', function (Blueprint $table) {
                $table->dropForeign(['three_way_matched_by']);
                $table->dropColumn(['three_way_matched_at', 'three_way_matched_by']);
            });
        }
    }
};
