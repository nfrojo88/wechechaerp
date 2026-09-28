<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZkDevice extends Model
{
    use HasFactory;

    protected $table = 'zk_devices';

    protected $fillable = [
        'serial_number',
        'name',
        'model_name',
        'device_type',     // 'head_office' or 'site'
        'project_id',      // linked Project for site devices
        'location',        // e.g. "Main Reception", "Site Entrance Gate"
        'ip_address',
        'port',
        'notes',
        'last_seen_at',
        'is_active',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'is_active'    => 'boolean',
        'port'         => 'integer',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function punches()
    {
        return $this->hasMany(DeviceAttendanceLog::class, 'device_sn', 'serial_number');
    }

    public function isOnline(): bool
    {
        return $this->last_seen_at && $this->last_seen_at->diffInMinutes(now()) < 2;
    }

    public function isRecent(): bool
    {
        return $this->last_seen_at && $this->last_seen_at->diffInMinutes(now()) < 10;
    }

    public function getStatusLabelAttribute(): string
    {
        if ($this->isOnline()) {
            return 'Online';
        }
        if ($this->isRecent()) {
            return 'Recent';
        }
        if ($this->last_seen_at) {
            return 'Offline';
        }
        return 'Never Connected';
    }

    public function getStatusBadgeAttribute(): string
    {
        if ($this->isOnline()) {
            return 'bg-success';
        }
        if ($this->isRecent()) {
            return 'bg-warning text-dark';
        }
        if ($this->last_seen_at) {
            return 'bg-danger';
        }
        return 'bg-secondary';
    }

    public function getScopeNameAttribute(): string
    {
        if ($this->device_type === 'site') {
            return $this->project ? ('Site: ' . $this->project->name) : 'Site (Unassigned)';
        }
        return 'Head Office';
    }
}
