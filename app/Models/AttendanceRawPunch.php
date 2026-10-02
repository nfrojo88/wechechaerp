<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceRawPunch extends Model
{
    use HasFactory;

    protected $table = 'attendance_raw_punches';

    protected $fillable = [
        'device_user_id',
        'punch_time',
        'device_id',
        'site_id',
        'punch_type',
        'synced_to_manpower_at',
    ];

    protected $casts = [
        'punch_time' => 'datetime',
        'synced_to_manpower_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'site_id');
    }
}
