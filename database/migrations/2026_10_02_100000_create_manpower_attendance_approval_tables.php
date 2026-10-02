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
        // 1. Worker Master (Casual / Daily / Gang Workers)
        if (!Schema::hasTable('workers')) {
            Schema::create('workers', function (Blueprint $table) {
                $table->id();
                $table->string('worker_code', 50)->unique()->nullable();
                $table->string('name');
                $table->string('phone', 50)->nullable()->index();
                $table->string('national_id', 50)->nullable()->index();
                $table->string('trade', 100)->nullable()->index(); // Mason, Carpenter, Electrician, Laborer, etc.
                $table->decimal('daily_rate', 12, 2)->default(0);
                $table->string('status', 20)->default('active')->index(); // active, inactive
                $table->string('photo_url', 255)->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        // 2. Attendance Raw Punches (Dedicated buffer for machine punches)
        if (!Schema::hasTable('attendance_raw_punches')) {
            Schema::create('attendance_raw_punches', function (Blueprint $table) {
                $table->id();
                $table->string('device_user_id', 50)->index();
                $table->dateTime('punch_time')->index();
                $table->string('device_id', 100)->nullable()->index();
                $table->unsignedBigInteger('site_id')->nullable()->index(); // refers to projects.id
                $table->string('punch_type', 20)->default('check_in'); // check_in, check_out, general
                $table->dateTime('synced_to_manpower_at')->nullable();
                $table->timestamps();

                $table->index(['device_user_id', 'punch_time'], 'raw_punch_user_time_idx');
            });
        }

        // 3. Daily Manpower Sheets
        if (!Schema::hasTable('daily_manpower_sheets')) {
            Schema::create('daily_manpower_sheets', function (Blueprint $table) {
                $table->id();
                $table->string('sheet_number', 50)->unique();
                $table->unsignedBigInteger('project_id')->index();
                $table->date('date')->index();
                $table->unsignedBigInteger('site_engineer_id')->index();
                $table->string('trade', 100)->nullable();
                $table->string('gang_subcontractor', 150)->nullable();
                
                // Status Engine:
                // Draft, Submitted, Planning Approved, Coordinator Approved, HR Approved, In Weekly Batch, GM Approved, Paid, Rejected
                $table->string('status', 50)->default('Draft')->index();
                
                // Current Stage in chain: site_engineer, planning_manager, coordinator, hr, hr_officer, gm, finance, completed
                $table->string('current_stage', 50)->default('site_engineer')->index();
                
                // Aggregates
                $table->unsignedInteger('total_headcount')->default(0);
                $table->decimal('total_regular_hours', 10, 2)->default(0);
                $table->decimal('total_overtime_hours', 10, 2)->default(0);
                $table->decimal('total_amount', 14, 2)->default(0);
                $table->decimal('total_adjusted_amount', 14, 2)->nullable();
                
                // Rejection context
                $table->string('rejected_by_stage', 50)->nullable();
                $table->string('rejection_reason', 150)->nullable();
                $table->text('rejection_comment')->nullable();
                $table->unsignedBigInteger('rejected_by_user_id')->nullable();
                
                // Batch assignment
                $table->unsignedBigInteger('weekly_batch_id')->nullable()->index();

                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['project_id', 'date'], 'sheet_proj_date_idx');
            });
        }

        // 4. Daily Manpower Lines
        if (!Schema::hasTable('daily_manpower_lines')) {
            Schema::create('daily_manpower_lines', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sheet_id')->index();
                $table->unsignedBigInteger('worker_id')->index();
                
                $table->time('check_in')->nullable();
                $table->time('check_out')->nullable();
                $table->decimal('regular_hours', 6, 2)->default(8.0);
                $table->decimal('overtime_hours', 6, 2)->default(0);
                $table->decimal('daily_rate', 12, 2)->default(0);
                $table->decimal('amount', 12, 2)->default(0);
                $table->decimal('adjusted_amount', 12, 2)->nullable();
                
                // Comparison status vs machine punch: present (rostered + punched), absent (rostered, no punch), unrostered (punched, not on roster)
                $table->string('attendance_status', 30)->default('present');
                $table->string('source', 30)->default('manual'); // machine, manual
                $table->string('device_user_id', 50)->nullable();
                $table->text('remark')->nullable();
                
                $table->timestamps();

                $table->index(['sheet_id', 'worker_id'], 'line_sheet_worker_idx');
            });
        }

        // 5. Manpower Approval Logs (Audit Trail)
        if (!Schema::hasTable('manpower_approval_logs')) {
            Schema::create('manpower_approval_logs', function (Blueprint $table) {
                $table->id();
                $table->string('record_type', 50)->default('daily_sheet')->index(); // daily_sheet, weekly_batch
                $table->unsignedBigInteger('record_id')->index();
                $table->string('stage', 50)->index(); // site_engineer, planning_manager, coordinator, hr, hr_officer, gm, finance
                $table->string('action', 50)->index(); // submitted, approved, rejected, resubmitted, batched, paid, held
                $table->unsignedBigInteger('user_id')->index();
                
                $table->string('rejection_reason_code', 100)->nullable();
                $table->text('comment')->nullable();
                $table->decimal('amount_before', 14, 2)->nullable();
                $table->decimal('amount_after', 14, 2)->nullable();
                $table->json('line_adjustments')->nullable(); // Snapshot of partial amounts if any
                
                $table->timestamps();

                $table->index(['record_type', 'record_id'], 'appr_log_rec_idx');
            });
        }

        // 6. Weekly Manpower Batches (HR Officer -> GM -> Finance)
        if (!Schema::hasTable('weekly_manpower_batches')) {
            Schema::create('weekly_manpower_batches', function (Blueprint $table) {
                $table->id();
                $table->string('batch_number', 50)->unique();
                $table->unsignedBigInteger('project_id')->index();
                $table->date('week_start')->index();
                $table->date('week_end')->index();
                
                // Status: Draft, Submitted_GM, GM_Approved, Paid, Rejected, Held
                $table->string('status', 50)->default('Draft')->index();
                $table->string('current_stage', 50)->default('hr_officer')->index(); // hr_officer, gm, finance, completed
                
                // Totals
                $table->unsignedInteger('total_workers_count')->default(0);
                $table->decimal('total_days_worked', 10, 2)->default(0);
                $table->decimal('total_gross_amount', 14, 2)->default(0);
                $table->decimal('total_deductions', 14, 2)->default(0);
                $table->decimal('total_advances', 14, 2)->default(0);
                $table->decimal('total_net_payable', 14, 2)->default(0);
                
                // User tracking
                $table->unsignedBigInteger('prepared_by_hr_id')->nullable();
                $table->unsignedBigInteger('approved_by_gm_id')->nullable();
                $table->dateTime('gm_approved_at')->nullable();
                $table->text('gm_notes')->nullable();
                
                // Payment execution
                $table->unsignedBigInteger('paid_by_finance_id')->nullable();
                $table->date('payment_date')->nullable();
                $table->string('payment_method', 50)->nullable(); // Bank Transfer, Cash, Check, Telebirr, CBE Birr
                $table->string('payment_reference', 100)->nullable();
                $table->string('payment_attachment', 255)->nullable();
                $table->text('payment_notes')->nullable();
                
                // Rejection / Hold context
                $table->string('rejection_reason', 150)->nullable();
                $table->text('rejection_comment')->nullable();
                $table->string('hold_reason', 255)->nullable();

                $table->timestamps();

                $table->index(['project_id', 'week_start', 'week_end'], 'batch_proj_week_idx');
            });
        }

        // 7. Weekly Batch Items (Summary per worker within the weekly batch)
        if (!Schema::hasTable('weekly_batch_items')) {
            Schema::create('weekly_batch_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('batch_id')->index();
                $table->unsignedBigInteger('worker_id')->index();
                
                $table->decimal('days_worked', 6, 2)->default(0);
                $table->decimal('total_regular_hours', 8, 2)->default(0);
                $table->decimal('total_overtime_hours', 8, 2)->default(0);
                $table->decimal('gross_amount', 14, 2)->default(0);
                $table->decimal('deductions', 14, 2)->default(0);
                $table->decimal('advances', 14, 2)->default(0);
                $table->decimal('net_payable', 14, 2)->default(0);
                $table->json('daily_sheet_ids')->nullable(); // list of sheet IDs included
                $table->text('notes')->nullable();

                $table->timestamps();

                $table->index(['batch_id', 'worker_id'], 'batch_item_worker_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weekly_batch_items');
        Schema::dropIfExists('weekly_manpower_batches');
        Schema::dropIfExists('manpower_approval_logs');
        Schema::dropIfExists('daily_manpower_lines');
        Schema::dropIfExists('daily_manpower_sheets');
        Schema::dropIfExists('attendance_raw_punches');
        Schema::dropIfExists('workers');
    }
};
