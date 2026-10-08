<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class VehicleReminderHistory extends Model
{
    protected $table = 'vehicle_reminder_history';
    protected $fillable = ['vehicle_reminder_id','fixed_asset_unit_id','reminder_type','action','snapshot','notes','performed_by','performed_at'];
    protected $casts = ['snapshot' => 'array', 'performed_at' => 'datetime'];

    public function reminder() { return $this->belongsTo(VehicleReminder::class, 'vehicle_reminder_id'); }
    public function performer() { return $this->belongsTo(User::class, 'performed_by'); }
}
