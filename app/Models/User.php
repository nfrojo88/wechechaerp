<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'store_id',
        'is_active',
        'access_blocked_at',
        'access_block_reason',
        'access_unblocked_by',
        'access_unblocked_at',
        'access_unblock_reason',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at'   => 'datetime',
        'is_active'           => 'boolean',
        'access_blocked_at'   => 'datetime',
        'access_unblocked_at' => 'datetime',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function projects()
    {
        return $this->belongsToMany(Project::class, 'project_user');
    }

    public function takeoffEditRequests()
    {
        return $this->hasMany(TakeoffEditRequest::class, 'user_id');
    }

    public function employee()
    {
        return $this->hasOne(Employee::class)->withoutGlobalScope(\App\Scopes\NotDeadFileScope::class);
    }

    /**
     * Scope query to strictly active users who are not in Dead File.
     */
    public function scopeActiveUsers($query)
    {
        return $query->where(function ($q) {
            $q->where('is_active', true)->orWhereNull('is_active');
        })->whereDoesntHave('employee', function ($eq) {
            $eq->withoutGlobalScope(\App\Scopes\NotDeadFileScope::class)
               ->where(function ($deadQ) {
                   $deadQ->where('is_dead_file', true)
                         ->orWhere('status', 'dead_file');
               });
        });
    }

    public function assignedAccounts()
    {
        return $this->hasMany(ChartOfAccount::class, 'assigned_to');
    }

    public function assignedPettyCashAccounts()
    {
        return $this->hasMany(ChartOfAccount::class, 'assigned_to')
            ->where(function($q) {
                $q->where('code', '1110')
                  ->orWhere('code', 'like', '1110%')
                  ->orWhere('name', 'like', '%petty cash%')
                  ->orWhere('subtype', 'cash');
            });
    }

    public function getPettyCashBalanceAttribute(): float
    {
        return (float) $this->assignedPettyCashAccounts()->sum('current_balance');
    }

    public function isInDeadFile(): bool
    {
        if ($this->employee && ($this->employee->is_dead_file || $this->employee->status === 'dead_file')) {
            return true;
        }

        if (!empty($this->email)) {
            return Employee::inDeadFile()->where('email', trim($this->email))->exists();
        }

        return false;
    }

    /**
     * Get the currently active role name for this user (supports session-based active role switching).
     */
    public function getActiveRole(): ?string
    {
        $active = session('active_role');
        if ($active && ($this->hasRole($active) || $this->hasAnyRole(['admin', 'global_admin']))) {
            return $active;
        }

        return $this->roles->first()?->name;
    }

    /**
     * Human-friendly formatted active role label.
     */
    public function getActiveRoleLabel(): string
    {
        $role = $this->getActiveRole();
        if (!$role) {
            return 'No Role Assigned';
        }

        return ucwords(str_replace(['_', '-'], ' ', $role));
    }

    /**
     * Get array of all assigned role names.
     */
    public function getAllRoleNames(): array
    {
        return $this->roles->pluck('name')->toArray();
    }

    /**
     * Check if user has multiple roles or admin switching capabilities.
     */
    public function hasMultipleRoles(): bool
    {
        return $this->roles->count() > 1 || $this->hasAnyRole(['admin', 'global_admin']);
    }

    /**
     * Check if user has Global Admin or Super Admin privileges.
     */
    public function isGlobalAdmin(): bool
    {
        $roleNames = $this->roles->pluck('name')->map(fn($r) => strtolower(str_replace([' ', '-'], '_', trim($r))))->toArray();
        return in_array('global_admin', $roleNames) 
            || in_array('admin', $roleNames) 
            || in_array('super_admin', $roleNames)
            || (bool)($this->is_admin ?? false);
    }

    public function accessUnblockedByUser()
    {
        return $this->belongsTo(User::class, 'access_unblocked_by');
    }

    /**
     * Check if user login / API access is suspended.
     */
    public function isAccessBlocked(): bool
    {
        return $this->access_blocked_at !== null;
    }

    /**
     * Block user access due to consecutive attendance absences.
     */
    public function blockAccess(string $reason, int $missedStreakDays = 5): void
    {
        // Strict Rule: Never auto-block Admin or Global Admin accounts
        if ($this->isGlobalAdmin() || $this->hasAnyRole(['admin', 'global_admin', 'super_admin'])) {
            return;
        }

        $this->update([
            'access_blocked_at'   => now(),
            'access_block_reason' => $reason,
        ]);

        try {
            \App\Models\UserAccessAudit::create([
                'user_id'            => $this->id,
                'employee_id'        => $this->employee?->id,
                'action'             => 'blocked',
                'performed_by'       => null, // System automated
                'reason'             => $reason,
                'missed_streak_days' => $missedStreakDays,
                'ip_address'         => request()->ip() ?? '127.0.0.1',
            ]);
        } catch (\Throwable $e) {}

        try {
            \App\Models\ActivityLog::log(
                'suspended',
                "User [{$this->name}] access suspended due to {$missedStreakDays} consecutive absence days without attendance.",
                'HR Attendance Security',
                $this
            );
        } catch (\Throwable $e) {}
    }

    /**
     * Restore user access with mandatory HR/Admin reason.
     */
    public function unblockAccess(User $unblockedBy, string $reason): void
    {
        $this->update([
            'access_blocked_at'     => null,
            'access_unblocked_by'   => $unblockedBy->id,
            'access_unblocked_at'   => now(),
            'access_unblock_reason' => $reason,
        ]);

        try {
            \App\Models\UserAccessAudit::create([
                'user_id'            => $this->id,
                'employee_id'        => $this->employee?->id,
                'action'             => 'unblocked',
                'performed_by'       => $unblockedBy->id,
                'reason'             => $reason,
                'missed_streak_days' => 0,
                'ip_address'         => request()->ip() ?? '127.0.0.1',
            ]);
        } catch (\Throwable $e) {}

        try {
            \App\Models\ActivityLog::log(
                'restored',
                "User [{$this->name}] access restored by [{$unblockedBy->name}]. Reason: {$reason}",
                'HR Attendance Security',
                $this
            );
        } catch (\Throwable $e) {}
    }
}
