<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class VehicleReminderNotification extends Model
{
    protected $table = 'vehicle_reminder_notifications';
    protected $fillable = ['vehicle_reminder_id','channel','alert_type','sent','sent_at','message'];
    protected $casts = ['sent' => 'boolean', 'sent_at' => 'datetime'];

    public function reminder() { return $this->belongsTo(VehicleReminder::class, 'vehicle_reminder_id'); }
}
