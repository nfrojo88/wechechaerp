<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_no', 20)->unique();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->unsignedBigInteger('fixed_asset_unit_id')->nullable();
            $table->unsignedBigInteger('employee_asset_id')->nullable();
            $table->string('asset_name');
            $table->string('asset_code')->nullable();
            $table->enum('issue_type', ['breakdown', 'damage', 'service_due', 'malfunction', 'needs_repair', 'other'])->default('other');
            $table->text('description');
            $table->enum('urgency', ['low', 'normal', 'urgent', 'critical'])->default('normal');
            $table->enum('status', ['pending', 'in_progress', 'resolved', 'closed'])->default('pending');
            $table->text('admin_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('reported_by_user_id')->constrained('users')->onDelete('cascade');
            $table->unsignedBigInteger('assigned_to_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('fixed_asset_unit_id')->references('id')->on('fixed_asset_units')->onDelete('set null');
            $table->foreign('assigned_to_user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_requests');
    }
};
