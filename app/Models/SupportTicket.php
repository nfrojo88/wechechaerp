<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class SupportTicket extends Model
{
    protected $fillable = [
        'ticket_no', 'user_id',
        // 1. Submitter Information
        'submitter_name', 'employee_code', 'department', 'contact_email', 'contact_phone', 'submitted_date',
        // 2. Type of Submission
        'submission_type', // problem, suggestion, both
        // 3. Problem Report
        'category', 'subject', 'affected_system', 'location', 'incident_started_at', 'frequency',
        'description', 'steps_to_reproduce', 'error_message', 'priority', 'impact_urgency', 'already_tried',
        // 4. Suggestion
        'suggestion_title', 'suggestion_area', 'current_situation', 'suggested_change',
        'expected_benefits', 'expected_benefits_other',
        // 5. Attachments
        'attachment_path', 'attachment_name', 'attachments_notes',
        // 5b. Material Request (IT)
        'has_material_request', 'mr_justification', 'mr_urgency', 'mr_project_location',
        // GM decision
        'mr_gm_status', 'mr_gm_decided_at', 'mr_gm_notes', 'mr_gm_decided_by',
        // Store Manager
        'mr_store_status', 'mr_store_dispatched_at', 'mr_store_notes', 'mr_store_managed_by',
        // Procurement link
        'mr_purchase_request_id',
        // Head Office Secretary
        'mr_secretary_received', 'mr_secretary_received_at', 'mr_secretary_received_by', 'mr_secretary_notes',
        // MR SMS flags
        'mr_sms_gm_sent', 'mr_sms_store_sent',
        // 6. IT Department / Admin Use Only
        'status', 'date_received', 'received_by_id', 'assigned_to', 'target_resolution_date',
        'root_cause', 'actions_taken', 'resolution_decision', 'date_closed', 'user_confirmed_resolved',
        'resolved_at',
        // Escalation / SMS flags
        'notified_gm', 'notified_admin', 'sms_alert_sent', 'sms_alert_log',
    ];

    protected $casts = [
        'submitted_date'              => 'date',
        'date_received'               => 'date',
        'target_resolution_date'      => 'date',
        'date_closed'                 => 'date',
        'resolved_at'                 => 'datetime',
        'expected_benefits'           => 'array',
        'notified_gm'                 => 'boolean',
        'notified_admin'              => 'boolean',
        'sms_alert_sent'              => 'boolean',
        // Material Request
        'has_material_request'        => 'boolean',
        'mr_gm_decided_at'            => 'datetime',
        'mr_store_dispatched_at'      => 'datetime',
        'mr_secretary_received'       => 'boolean',
        'mr_secretary_received_at'    => 'datetime',
        'mr_sms_gm_sent'              => 'boolean',
        'mr_sms_store_sent'           => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($ticket) {
            if (empty($ticket->ticket_no)) {
                $year = date('Y');
                $last = static::where('ticket_no', 'LIKE', "IT-{$year}-%")
                    ->orderBy('id', 'desc')
                    ->first();

                $nextNumber = 1;
                if ($last && preg_match('/IT-\d{4}-(\d+)/', $last->ticket_no, $matches)) {
                    $nextNumber = ((int)$matches[1]) + 1;
                } else {
                    $total = static::count();
                    $nextNumber = $total + 1;
                }

                $ticket->ticket_no = 'IT-' . $year . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
            }

            if (empty($ticket->submitted_date)) {
                $ticket->submitted_date = now()->toDateString();
            }

            if (empty($ticket->status) || $ticket->status === 'new') {
                $ticket->status = 'open';
            }
        });
    }

    /**
     * Self-healing schema helper ensuring columns exist even if migrations haven't run on production.
     */
    public static function ensureSchema(): void
    {
        try {
            if (!Schema::hasTable('support_tickets')) {
                return;
            }

            // Convert legacy MySQL ENUM columns to VARCHAR so all statuses and priorities are accepted without truncation
            try {
                \Illuminate\Support\Facades\DB::statement("ALTER TABLE `support_tickets` MODIFY COLUMN `status` VARCHAR(50) NOT NULL DEFAULT 'open'");
                \Illuminate\Support\Facades\DB::statement("ALTER TABLE `support_tickets` MODIFY COLUMN `priority` VARCHAR(50) NOT NULL DEFAULT 'medium'");
            } catch (\Throwable $e) {
                // Suppress if DB user has no ALTER privileges
            }

            Schema::table('support_tickets', function (Blueprint $table) {
                if (!Schema::hasColumn('support_tickets', 'submitter_name')) {
                    $table->string('submitter_name')->nullable();
                }
                if (!Schema::hasColumn('support_tickets', 'employee_code')) {
                    $table->string('employee_code')->nullable();
                }
                if (!Schema::hasColumn('support_tickets', 'department')) {
                    $table->string('department')->nullable();
                }
                if (!Schema::hasColumn('support_tickets', 'contact_email')) {
                    $table->string('contact_email')->nullable();
                }
                if (!Schema::hasColumn('support_tickets', 'contact_phone')) {
                    $table->string('contact_phone')->nullable();
                }
                if (!Schema::hasColumn('support_tickets', 'submitted_date')) {
                    $table->date('submitted_date')->nullable();
                }
                if (!Schema::hasColumn('support_tickets', 'submission_type')) {
                    $table->string('submission_type')->default('problem');
                }
                if (!Schema::hasColumn('support_tickets', 'affected_system')) {
                    $table->string('affected_system')->nullable();
                }
                if (!Schema::hasColumn('support_tickets', 'location')) {
                    $table->string('location')->nullable();
                }
                if (!Schema::hasColumn('support_tickets', 'incident_started_at')) {
                    $table->string('incident_started_at')->nullable();
                }
                if (!Schema::hasColumn('support_tickets', 'frequency')) {
                    $table->string('frequency')->nullable();
                }
                if (!Schema::hasColumn('support_tickets', 'steps_to_reproduce')) {
                    $table->text('steps_to_reproduce')->nullable();
                }
                if (!Schema::hasColumn('support_tickets', 'error_message')) {
                    $table->text('error_message')->nullable();
                }
                if (!Schema::hasColumn('support_tickets', 'impact_urgency')) {
                    $table->string('impact_urgency')->nullable();
                }
                if (!Schema::hasColumn('support_tickets', 'already_tried')) {
                    $table->text('already_tried')->nullable();
                }
                if (!Schema::hasColumn('support_tickets', 'suggestion_title')) {
                    $table->string('suggestion_title')->nullable();
                }
                if (!Schema::hasColumn('support_tickets', 'suggestion_area')) {
                    $table->string('suggestion_area')->nullable();
                }
                if (!Schema::hasColumn('support_tickets', 'current_situation')) {
                    $table->text('current_situation')->nullable();
                }
                if (!Schema::hasColumn('support_tickets', 'suggested_change')) {
                    $table->text('suggested_change')->nullable();
                }
                if (!Schema::hasColumn('support_tickets', 'expected_benefits')) {
                    $table->text('expected_benefits')->nullable();
                }
                if (!Schema::hasColumn('support_tickets', 'expected_benefits_other')) {
                    $table->string('expected_benefits_other')->nullable();
                }
                if (!Schema::hasColumn('support_tickets', 'attachment_path')) {
                    $table->string('attachment_path')->nullable();
                }
                if (!Schema::hasColumn('support_tickets', 'attachment_name')) {
                    $table->string('attachment_name')->nullable();
                }
                if (!Schema::hasColumn('support_tickets', 'attachments_notes')) {
                    $table->text('attachments_notes')->nullable();
                }
                if (!Schema::hasColumn('support_tickets', 'date_received')) {
                    $table->date('date_received')->nullable();
                }
                if (!Schema::hasColumn('support_tickets', 'received_by_id')) {
                    $table->unsignedBigInteger('received_by_id')->nullable();
                }
                if (!Schema::hasColumn('support_tickets', 'target_resolution_date')) {
                    $table->date('target_resolution_date')->nullable();
                }
                if (!Schema::hasColumn('support_tickets', 'root_cause')) {
                    $table->text('root_cause')->nullable();
                }
                if (!Schema::hasColumn('support_tickets', 'actions_taken')) {
                    $table->text('actions_taken')->nullable();
                }
                if (!Schema::hasColumn('support_tickets', 'resolution_decision')) {
                    $table->text('resolution_decision')->nullable();
                }
                if (!Schema::hasColumn('support_tickets', 'date_closed')) {
                    $table->date('date_closed')->nullable();
                }
                if (!Schema::hasColumn('support_tickets', 'user_confirmed_resolved')) {
                    $table->string('user_confirmed_resolved')->default('pending');
                }
                if (!Schema::hasColumn('support_tickets', 'notified_gm')) {
                    $table->boolean('notified_gm')->default(false);
                }
                if (!Schema::hasColumn('support_tickets', 'notified_admin')) {
                    $table->boolean('notified_admin')->default(false);
                }
                if (!Schema::hasColumn('support_tickets', 'sms_alert_sent')) {
                    $table->boolean('sms_alert_sent')->default(false);
                }
                if (!Schema::hasColumn('support_tickets', 'sms_alert_log')) {
                    $table->text('sms_alert_log')->nullable();
                }
                // Material Request fields
                if (!Schema::hasColumn('support_tickets', 'has_material_request')) {
                    $table->boolean('has_material_request')->default(false);
                }
                if (!Schema::hasColumn('support_tickets', 'mr_justification')) {
                    $table->text('mr_justification')->nullable();
                }
                if (!Schema::hasColumn('support_tickets', 'mr_urgency')) {
                    $table->string('mr_urgency', 50)->nullable();
                }
                if (!Schema::hasColumn('support_tickets', 'mr_project_location')) {
                    $table->string('mr_project_location', 255)->nullable();
                }
                if (!Schema::hasColumn('support_tickets', 'mr_gm_status')) {
                    $table->string('mr_gm_status', 50)->nullable();
                }
                if (!Schema::hasColumn('support_tickets', 'mr_gm_decided_at')) {
                    $table->timestamp('mr_gm_decided_at')->nullable();
                }
                if (!Schema::hasColumn('support_tickets', 'mr_gm_notes')) {
                    $table->text('mr_gm_notes')->nullable();
                }
                if (!Schema::hasColumn('support_tickets', 'mr_gm_decided_by')) {
                    $table->unsignedBigInteger('mr_gm_decided_by')->nullable();
                }
                if (!Schema::hasColumn('support_tickets', 'mr_store_status')) {
                    $table->string('mr_store_status', 50)->nullable();
                }
                if (!Schema::hasColumn('support_tickets', 'mr_store_dispatched_at')) {
                    $table->timestamp('mr_store_dispatched_at')->nullable();
                }
                if (!Schema::hasColumn('support_tickets', 'mr_store_notes')) {
                    $table->text('mr_store_notes')->nullable();
                }
                if (!Schema::hasColumn('support_tickets', 'mr_store_managed_by')) {
                    $table->unsignedBigInteger('mr_store_managed_by')->nullable();
                }
                if (!Schema::hasColumn('support_tickets', 'mr_purchase_request_id')) {
                    $table->unsignedBigInteger('mr_purchase_request_id')->nullable();
                }
                if (!Schema::hasColumn('support_tickets', 'mr_secretary_received')) {
                    $table->boolean('mr_secretary_received')->default(false);
                }
                if (!Schema::hasColumn('support_tickets', 'mr_secretary_received_at')) {
                    $table->timestamp('mr_secretary_received_at')->nullable();
                }
                if (!Schema::hasColumn('support_tickets', 'mr_secretary_received_by')) {
                    $table->unsignedBigInteger('mr_secretary_received_by')->nullable();
                }
                if (!Schema::hasColumn('support_tickets', 'mr_secretary_notes')) {
                    $table->text('mr_secretary_notes')->nullable();
                }
                if (!Schema::hasColumn('support_tickets', 'mr_sms_gm_sent')) {
                    $table->boolean('mr_sms_gm_sent')->default(false);
                }
                if (!Schema::hasColumn('support_tickets', 'mr_sms_store_sent')) {
                    $table->boolean('mr_sms_store_sent')->default(false);
                }
                // Create it_material_request_items if missing
                if (!Schema::hasTable('it_material_request_items')) {
                    Schema::create('it_material_request_items', function (Blueprint $t) {
                        $t->id();
                        $t->unsignedBigInteger('support_ticket_id');
                        $t->string('item_name', 255);
                        $t->string('unit', 50)->nullable();
                        $t->decimal('quantity', 10, 2)->default(1);
                        $t->string('purpose', 500)->nullable();
                        $t->string('urgency_level', 50)->nullable();
                        $t->string('store_dispatch_qty', 50)->nullable();
                        $t->string('store_dispatch_status', 50)->nullable();
                        $t->text('store_notes')->nullable();
                        $t->timestamps();
                    });
                }
            });
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("SupportTicket::ensureSchema check: " . $e->getMessage());
        }
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(TicketReply::class, 'ticket_id');
    }

    public function materialRequestItems(): HasMany
    {
        return $this->hasMany(ItMaterialRequestItem::class, 'support_ticket_id');
    }

    public function mrGmDecidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mr_gm_decided_by');
    }

    public function mrStoreManagedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mr_store_managed_by');
    }

    public function mrSecretaryReceivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mr_secretary_received_by');
    }

    public function getMrGmStatusBadgeAttribute(): string
    {
        return match($this->mr_gm_status) {
            'pending_gm'        => '<span class="badge bg-warning text-dark"><i class="fa-solid fa-clock me-1"></i>Pending GM Review</span>',
            'approved_to_store' => '<span class="badge bg-success text-white"><i class="fa-solid fa-check me-1"></i>Approved → Store Manager</span>',
            'rejected_by_gm'    => '<span class="badge bg-danger text-white"><i class="fa-solid fa-ban me-1"></i>Rejected by GM</span>',
            default             => '<span class="badge bg-secondary">Not Submitted</span>',
        };
    }

    public function getMrStoreStatusBadgeAttribute(): string
    {
        return match($this->mr_store_status) {
            'pending_store'         => '<span class="badge bg-warning text-dark"><i class="fa-solid fa-clock me-1"></i>Pending Store Action</span>',
            'dispatched'            => '<span class="badge bg-success text-white"><i class="fa-solid fa-truck me-1"></i>Fully Dispatched</span>',
            'partially_dispatched'  => '<span class="badge bg-info text-dark"><i class="fa-solid fa-box-open me-1"></i>Partially Dispatched</span>',
            'unavailable'           => '<span class="badge bg-danger text-white"><i class="fa-solid fa-triangle-exclamation me-1"></i>Unavailable – Needs Purchase</span>',
            default                 => '<span class="badge bg-secondary">—</span>',
        };
    }

    public function getStatusBadgeAttribute(): string
    {
        return match($this->status) {
            'new', 'open' => '<span class="badge bg-danger text-white"><i class="fa-solid fa-circle-exclamation me-1"></i>New / Open</span>',
            'in_progress' => '<span class="badge bg-warning text-dark"><i class="fa-solid fa-spinner me-1"></i>In Progress</span>',
            'waiting_for_user' => '<span class="badge bg-info text-dark"><i class="fa-solid fa-clock me-1"></i>Waiting for User</span>',
            'resolved'    => '<span class="badge bg-success text-white"><i class="fa-solid fa-circle-check me-1"></i>Resolved</span>',
            'closed'      => '<span class="badge bg-secondary text-white"><i class="fa-solid fa-check-double me-1"></i>Closed</span>',
            'rejected'    => '<span class="badge bg-dark text-white"><i class="fa-solid fa-ban me-1"></i>Rejected</span>',
            'planned'     => '<span class="badge bg-primary text-white"><i class="fa-solid fa-calendar-check me-1"></i>Planned</span>',
            default       => '<span class="badge bg-secondary">' . ucfirst(str_replace('_', ' ', $this->status)) . '</span>',
        };
    }

    public function getPriorityBadgeAttribute(): string
    {
        $priority = strtolower($this->priority ?? 'medium');
        return match($priority) {
            'critical' => '<span class="badge" style="background:#b91c1c;color:#ffffff;font-weight:700;"><i class="fa-solid fa-triangle-exclamation me-1"></i>🔴 Critical</span>',
            'urgent'   => '<span class="badge" style="background:#dc2626;color:#ffffff;font-weight:700;"><i class="fa-solid fa-fire me-1"></i>🔥 Urgent</span>',
            'high'     => '<span class="badge" style="background:#f97316;color:#ffffff;font-weight:600;"><i class="fa-solid fa-bolt me-1"></i>🟠 High</span>',
            'medium'   => '<span class="badge" style="background:#3b82f6;color:#ffffff;font-weight:600;"><i class="fa-solid fa-info me-1"></i>🔵 Medium</span>',
            'low'      => '<span class="badge" style="background:#10b981;color:#ffffff;font-weight:600;"><i class="fa-solid fa-circle-info me-1"></i>🟢 Low</span>',
            default    => '<span class="badge bg-secondary">' . ucfirst($priority) . '</span>',
        };
    }

    public function getSubmissionTypeBadgeAttribute(): string
    {
        return match($this->submission_type) {
            'problem'    => '<span class="badge bg-danger"><i class="fa-solid fa-bug me-1"></i>Problem / Issue</span>',
            'suggestion' => '<span class="badge bg-info text-dark"><i class="fa-solid fa-lightbulb me-1"></i>Suggestion / Idea</span>',
            'both'       => '<span class="badge bg-purple text-white" style="background:#8b5cf6;"><i class="fa-solid fa-layer-group me-1"></i>Both Problem & Idea</span>',
            default      => '<span class="badge bg-secondary">' . ucfirst($this->submission_type ?? 'Problem') . '</span>',
        };
    }

    /**
     * SLA Response Time Guide according to the form:
     * Critical: First response 1h, Target resolution 4h
     * High: First response 4h, Target resolution 1 business day
     * Medium: First response 1 business day, Target resolution 3 business days
     * Low: First response 2 business days, Target resolution 5 business days
     */
    public function getSlaDetailsAttribute(): array
    {
        $priority = strtolower($this->priority ?? 'medium');
        return match($priority) {
            'critical', 'urgent' => [
                'first_response'    => '1 Hour',
                'target_resolution' => '4 Hours',
                'description'       => 'I cannot work at all / many people affected',
                'color'             => 'danger',
            ],
            'high' => [
                'first_response'    => '4 Hours',
                'target_resolution' => '1 Business Day',
                'description'       => 'Major part of work is blocked',
                'color'             => 'warning',
            ],
            'medium' => [
                'first_response'    => '1 Business Day',
                'target_resolution' => '3 Business Days',
                'description'       => 'Can work, but with difficulty',
                'color'             => 'primary',
            ],
            'low' => [
                'first_response'    => '2 Business Days',
                'target_resolution' => '5 Business Days',
                'description'       => 'Minor inconvenience',
                'color'             => 'success',
            ],
            default => [
                'first_response'    => '1 Business Day',
                'target_resolution' => '3 Business Days',
                'description'       => 'Standard SLA',
                'color'             => 'secondary',
            ],
        };
    }
}
