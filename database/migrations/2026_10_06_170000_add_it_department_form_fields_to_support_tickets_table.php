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
        Schema::table('support_tickets', function (Blueprint $table) {
            // 1. Submitter Information
            if (!Schema::hasColumn('support_tickets', 'submitter_name')) {
                $table->string('submitter_name')->nullable()->after('user_id');
            }
            if (!Schema::hasColumn('support_tickets', 'employee_code')) {
                $table->string('employee_code')->nullable()->after('submitter_name');
            }
            if (!Schema::hasColumn('support_tickets', 'department')) {
                $table->string('department')->nullable()->after('employee_code');
            }
            if (!Schema::hasColumn('support_tickets', 'contact_email')) {
                $table->string('contact_email')->nullable()->after('department');
            }
            if (!Schema::hasColumn('support_tickets', 'contact_phone')) {
                $table->string('contact_phone')->nullable()->after('contact_email');
            }
            if (!Schema::hasColumn('support_tickets', 'submitted_date')) {
                $table->date('submitted_date')->nullable()->after('contact_phone');
            }

            // 2. Type of Submission
            if (!Schema::hasColumn('support_tickets', 'submission_type')) {
                $table->string('submission_type')->default('problem')->after('submitted_date'); // problem, suggestion, both
            }

            // 3. Problem Report details
            if (!Schema::hasColumn('support_tickets', 'affected_system')) {
                $table->string('affected_system')->nullable()->after('category');
            }
            if (!Schema::hasColumn('support_tickets', 'location')) {
                $table->string('location')->nullable()->after('affected_system');
            }
            if (!Schema::hasColumn('support_tickets', 'incident_started_at')) {
                $table->string('incident_started_at')->nullable()->after('location');
            }
            if (!Schema::hasColumn('support_tickets', 'frequency')) {
                $table->string('frequency')->nullable()->after('incident_started_at'); // Once, Sometimes, Always
            }
            if (!Schema::hasColumn('support_tickets', 'steps_to_reproduce')) {
                $table->text('steps_to_reproduce')->nullable()->after('description');
            }
            if (!Schema::hasColumn('support_tickets', 'error_message')) {
                $table->text('error_message')->nullable()->after('steps_to_reproduce');
            }
            if (!Schema::hasColumn('support_tickets', 'impact_urgency')) {
                $table->string('impact_urgency')->nullable()->after('priority'); // critical, high, medium, low
            }
            if (!Schema::hasColumn('support_tickets', 'already_tried')) {
                $table->text('already_tried')->nullable()->after('error_message');
            }

            // 4. Suggestion / Improvement Idea
            if (!Schema::hasColumn('support_tickets', 'suggestion_title')) {
                $table->string('suggestion_title')->nullable()->after('already_tried');
            }
            if (!Schema::hasColumn('support_tickets', 'suggestion_area')) {
                $table->string('suggestion_area')->nullable()->after('suggestion_title'); // Tools & software, Process, Security, Training, Hardware, Support service, Other
            }
            if (!Schema::hasColumn('support_tickets', 'current_situation')) {
                $table->text('current_situation')->nullable()->after('suggestion_area');
            }
            if (!Schema::hasColumn('support_tickets', 'suggested_change')) {
                $table->text('suggested_change')->nullable()->after('current_situation');
            }
            if (!Schema::hasColumn('support_tickets', 'expected_benefits')) {
                $table->text('expected_benefits')->nullable()->after('suggested_change'); // JSON or comma-separated
            }
            if (!Schema::hasColumn('support_tickets', 'expected_benefits_other')) {
                $table->string('expected_benefits_other')->nullable()->after('expected_benefits');
            }

            // 5. Attachments
            if (!Schema::hasColumn('support_tickets', 'attachment_path')) {
                $table->string('attachment_path')->nullable()->after('expected_benefits_other');
            }
            if (!Schema::hasColumn('support_tickets', 'attachment_name')) {
                $table->string('attachment_name')->nullable()->after('attachment_path');
            }
            if (!Schema::hasColumn('support_tickets', 'attachments_notes')) {
                $table->text('attachments_notes')->nullable()->after('attachment_name');
            }

            // 6. For IT Department / Admin Use Only
            if (!Schema::hasColumn('support_tickets', 'date_received')) {
                $table->date('date_received')->nullable()->after('attachments_notes');
            }
            if (!Schema::hasColumn('support_tickets', 'received_by_id')) {
                $table->foreignId('received_by_id')->nullable()->constrained('users')->nullOnDelete()->after('date_received');
            }
            if (!Schema::hasColumn('support_tickets', 'target_resolution_date')) {
                $table->date('target_resolution_date')->nullable()->after('assigned_to');
            }
            if (!Schema::hasColumn('support_tickets', 'root_cause')) {
                $table->text('root_cause')->nullable()->after('target_resolution_date');
            }
            if (!Schema::hasColumn('support_tickets', 'actions_taken')) {
                $table->text('actions_taken')->nullable()->after('root_cause');
            }
            if (!Schema::hasColumn('support_tickets', 'resolution_decision')) {
                $table->text('resolution_decision')->nullable()->after('actions_taken');
            }
            if (!Schema::hasColumn('support_tickets', 'date_closed')) {
                $table->date('date_closed')->nullable()->after('resolution_decision');
            }
            if (!Schema::hasColumn('support_tickets', 'user_confirmed_resolved')) {
                $table->string('user_confirmed_resolved')->default('pending')->after('date_closed'); // yes, no, pending
            }

            // Escalation & SMS Notifications
            if (!Schema::hasColumn('support_tickets', 'notified_gm')) {
                $table->boolean('notified_gm')->default(false)->after('user_confirmed_resolved');
            }
            if (!Schema::hasColumn('support_tickets', 'notified_admin')) {
                $table->boolean('notified_admin')->default(false)->after('notified_gm');
            }
            if (!Schema::hasColumn('support_tickets', 'sms_alert_sent')) {
                $table->boolean('sms_alert_sent')->default(false)->after('notified_admin');
            }
            if (!Schema::hasColumn('support_tickets', 'sms_alert_log')) {
                $table->text('sms_alert_log')->nullable()->after('sms_alert_sent');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $cols = [
                'submitter_name', 'employee_code', 'department', 'contact_email', 'contact_phone', 'submitted_date',
                'submission_type', 'affected_system', 'location', 'incident_started_at', 'frequency',
                'steps_to_reproduce', 'error_message', 'impact_urgency', 'already_tried',
                'suggestion_title', 'suggestion_area', 'current_situation', 'suggested_change',
                'expected_benefits', 'expected_benefits_other', 'attachment_path', 'attachment_name', 'attachments_notes',
                'date_received', 'received_by_id', 'target_resolution_date', 'root_cause', 'actions_taken',
                'resolution_decision', 'date_closed', 'user_confirmed_resolved',
                'notified_gm', 'notified_admin', 'sms_alert_sent', 'sms_alert_log'
            ];
            foreach ($cols as $c) {
                if (Schema::hasColumn('support_tickets', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
