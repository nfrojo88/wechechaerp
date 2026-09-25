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
        if (!Schema::hasTable('site_deployment_requests')) {
            Schema::create('site_deployment_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('requested_by_role', 60)->nullable();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
                $table->string('site_name', 255)->nullable();
                $table->string('duration_type', 30)->default('single_day');
                $table->date('start_date');
                $table->date('end_date');
                $table->string('session_type', 30)->default('full_day');
                $table->string('morning_in', 10)->nullable();
                $table->string('morning_out', 10)->nullable();
                $table->string('afternoon_in', 10)->nullable();
                $table->string('afternoon_out', 10)->nullable();
                $table->decimal('hours_worked', 5, 2)->default(8.0);
                $table->text('task_notes')->nullable();
                $table->string('status', 30)->default('pending'); // pending, approved, rejected
                $table->foreignId('hr_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('hr_reviewed_at')->nullable();
                $table->text('hr_notes')->nullable();
                $table->timestamps();

                $table->index(['status', 'start_date']);
                $table->index(['employee_id', 'start_date']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_deployment_requests');
    }
};
