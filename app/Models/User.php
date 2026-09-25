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
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
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
        return $this->hasOne(Employee::class);
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
}
