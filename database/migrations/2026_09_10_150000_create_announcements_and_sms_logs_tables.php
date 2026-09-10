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
        // 1. Announcements Table
        if (!Schema::hasTable('announcements')) {
            Schema::create('announcements', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->text('message');
                $table->string('target_type', 30)->default('all'); // all, department, project, selected
                $table->json('target_criteria')->nullable(); // stored criteria e.g. ["departments": [...], "project_ids": [...]]
                $table->boolean('send_sms')->default(true);
                $table->boolean('is_published')->default(true); // in-app notification banner
                $table->timestamp('expires_at')->nullable();
                $table->unsignedInteger('total_recipients')->default(0);
                $table->unsignedInteger('sms_sent_count')->default(0);
                $table->unsignedInteger('sms_failed_count')->default(0);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        // 2. Announcement SMS Delivery Logs Table
        if (!Schema::hasTable('announcement_sms_logs')) {
            Schema::create('announcement_sms_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('announcement_id');
                $table->unsignedBigInteger('employee_id')->nullable();
                $table->string('recipient_name')->nullable();
                $table->string('phone_number', 40);
                $table->string('status', 20)->default('pending'); // sent, failed, skipped
                $table->text('error_message')->nullable();
                $table->text('response_payload')->nullable();
                $table->timestamps();

                $table->foreign('announcement_id')->references('id')->on('announcements')->cascadeOnDelete();
                $table->foreign('employee_id')->references('id')->on('employees')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('announcement_sms_logs');
        Schema::dropIfExists('announcements');
    }
};
