<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAuditFindingsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('audit_findings')) {
            Schema::create('audit_findings', function (Blueprint $table) {
                $table->id();
                $table->string('finding_number', 50)->unique();
                $table->string('source_type', 50); // inventory, stock_movement, daily_usage, transfer, fulfillment, purchase_request
                $table->unsignedBigInteger('source_id')->nullable();
                $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
                $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
                $table->string('title', 255);
                $table->text('observation');
                $table->string('severity', 20)->default('medium'); // low, medium, high, critical
                $table->string('status', 20)->default('open'); // open, responded, resolved, escalated
                $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->date('due_date')->nullable();
                $table->string('attachment_path', 255)->nullable();
                $table->text('response_notes')->nullable();
                $table->string('response_attachment', 255)->nullable();
                $table->foreignId('responded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('responded_at')->nullable();
                $table->text('resolution_notes')->nullable();
                $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('resolved_at')->nullable();
                $table->boolean('is_escalated')->default(false);
                $table->timestamp('escalated_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('audit_findings');
    }
}
