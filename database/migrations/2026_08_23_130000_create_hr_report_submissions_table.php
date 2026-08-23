<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('hr_report_submissions')) {
            Schema::create('hr_report_submissions', function (Blueprint $table) {
                $table->id();
                $table->string('report_type')->default('Attendance Report'); // Attendance, Turnover, Cost Analysis, Leave, Employee Cost
                $table->date('from_date')->nullable();
                $table->date('to_date')->nullable();
                $table->integer('total_employees')->default(0);
                $table->decimal('avg_attendance_rate', 5, 2)->default(0.00);
                $table->integer('total_working_days')->default(0);
                $table->text('notes')->nullable();
                $table->json('summary_data')->nullable();
                $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status')->default('submitted'); // submitted, reviewed, acknowledged
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('gm_remarks')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('hr_report_submissions');
    }
};
