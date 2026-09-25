<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class SiteDeploymentRequest extends Model
{
    protected $table = 'site_deployment_requests';

    protected $fillable = [
        'requested_by',
        'requested_by_role',
        'employee_id',
        'project_id',
        'site_name',
        'duration_type',
        'start_date',
        'end_date',
        'session_type',
        'morning_in',
        'morning_out',
        'afternoon_in',
        'afternoon_out',
        'hours_worked',
        'task_notes',
        'status',
        'hr_reviewed_by',
        'hr_reviewed_at',
        'hr_notes',
    ];

    protected $casts = [
        'start_date'     => 'date',
        'end_date'       => 'date',
        'hours_worked'   => 'decimal:2',
        'hr_reviewed_at' => 'datetime',
    ];

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function hrReviewedBy()
    {
        return $this->belongsTo(User::class, 'hr_reviewed_by');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function siteProject()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Ensure the database table exists safely in any environment without CLI requirement.
     */
    public static function ensureTableExists(): void
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
}
