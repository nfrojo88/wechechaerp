<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use App\Services\FileUploadService;

class AuditFinding extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'audit_findings';

    protected $fillable = [
        'finding_number',
        'source_type',
        'source_id',
        'store_id',
        'project_id',
        'title',
        'observation',
        'severity',
        'status',
        'assigned_to',
        'created_by',
        'due_date',
        'attachment_path',
        'response_notes',
        'response_attachment',
        'responded_by',
        'responded_at',
        'resolution_notes',
        'resolved_by',
        'resolved_at',
        'is_escalated',
        'escalated_at',
    ];

    protected $casts = [
        'due_date'     => 'date',
        'responded_at' => 'datetime',
        'resolved_at'  => 'datetime',
        'escalated_at' => 'datetime',
        'is_escalated' => 'boolean',
    ];

    public const STATUS_OPEN       = 'open';
    public const STATUS_RESPONDED  = 'responded';
    public const STATUS_RESOLVED   = 'resolved';
    public const STATUS_ESCALATED  = 'escalated';

    public const SEVERITY_LOW      = 'low';
    public const SEVERITY_MEDIUM   = 'medium';
    public const SEVERITY_HIGH     = 'high';
    public const SEVERITY_CRITICAL = 'critical';

    public static function ensureSchema(): void
    {
        try {
            if (!Schema::hasTable('audit_findings')) {
                Schema::create('audit_findings', function (Blueprint $table) {
                    $table->id();
                    $table->string('finding_number', 50)->unique();
                    $table->string('source_type', 50);
                    $table->unsignedBigInteger('source_id')->nullable();
                    $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
                    $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
                    $table->string('title', 255);
                    $table->text('observation');
                    $table->string('severity', 20)->default('medium');
                    $table->string('status', 20)->default('open');
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
        } catch (\Throwable $e) {}
    }

    public static function generateFindingNumber(): string
    {
        self::ensureSchema();
        $date = date('Ymd');
        $countToday = self::whereDate('created_at', now())->count() + 1;
        return 'AF-' . $date . '-' . str_pad($countToday, 4, '0', STR_PAD_LEFT);
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function respondedBy()
    {
        return $this->belongsTo(User::class, 'responded_by');
    }

    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        if (!$this->attachment_path) {
            return null;
        }
        return FileUploadService::url($this->attachment_path);
    }

    public function getResponseAttachmentUrlAttribute(): ?string
    {
        if (!$this->response_attachment) {
            return null;
        }
        return FileUploadService::url($this->response_attachment);
    }

    public function getIsOverdueAttribute(): bool
    {
        if ($this->status === self::STATUS_RESOLVED) {
            return false;
        }
        return $this->due_date ? $this->due_date->isPast() : false;
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', [self::STATUS_OPEN, self::STATUS_RESPONDED, self::STATUS_ESCALATED]);
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', '!=', self::STATUS_RESOLVED)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString());
    }

    public function scopeResolved($query)
    {
        return $query->where('status', self::STATUS_RESOLVED);
    }
}
