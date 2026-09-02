<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateManpowerDailyReportsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('manpower_daily_reports')) {
            Schema::create('manpower_daily_reports', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained()->cascadeOnDelete();
                $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();
                $table->date('report_date');

                // Workforce categories
                $table->unsignedInteger('skilled_workers')->default(0);
                $table->unsignedInteger('unskilled_workers')->default(0);
                $table->unsignedInteger('supervisors')->default(0);
                $table->unsignedInteger('engineers')->default(0);
                $table->unsignedInteger('operators')->default(0);
                $table->unsignedInteger('daily_laborers')->default(0);
                $table->unsignedInteger('subcontractor_workers')->default(0);

                // Totals (computed)
                $table->unsignedInteger('total_present')->default(0);
                $table->unsignedInteger('total_absent')->default(0);

                // Work area / activity
                $table->string('work_area')->nullable();
                $table->text('planned_activities')->nullable();
                $table->text('completed_activities')->nullable();
                $table->text('challenges')->nullable();
                $table->text('notes')->nullable();

                // Approval
                $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('review_notes')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->unique(['project_id', 'report_date', 'submitted_by'], 'unique_daily_manpower_report');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('manpower_daily_reports');
    }
}
