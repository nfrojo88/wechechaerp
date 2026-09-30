<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeviceAttendanceLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_sn',
        'device_user_id',
        'punch_time',
        'status',
        'verify_mode',
        'full_name',
    ];

    protected $casts = [
        'punch_time' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'device_user_id', 'device_user_id')
            ->withoutGlobalScope(\App\Scopes\NotDeadFileScope::class);
    }

    public function zkDevice()
    {
        return $this->belongsTo(ZkDevice::class, 'device_sn', 'serial_number');
    }
}
