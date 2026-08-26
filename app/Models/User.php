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
}
