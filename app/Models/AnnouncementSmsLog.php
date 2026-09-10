<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnnouncementSmsLog extends Model
{
    use HasFactory;

    protected $table = 'announcement_sms_logs';

    protected $fillable = [
        'announcement_id',
        'employee_id',
        'recipient_name',
        'phone_number',
        'status',
        'error_message',
        'response_payload',
    ];

    public function announcement()
    {
        return $this->belongsTo(Announcement::class, 'announcement_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
