<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverBooking extends Model
{
    protected $fillable = [
        'purchase_request_id', 'driver_employee_id', 'vehicle_number',
        'vehicle_description', 'scheduled_at', 'booking_notes', 'booked_by',
    ];

    protected $casts = ['scheduled_at' => 'datetime'];

    public function purchaseRequest() { return $this->belongsTo(PurchaseRequest::class); }

    /** The driver is an Employee record from the HR module */
    public function driver()          { return $this->belongsTo(Employee::class, 'driver_employee_id')->withoutGlobalScope(\App\Scopes\NotDeadFileScope::class); }

    public function bookedBy()        { return $this->belongsTo(User::class, 'booked_by'); }
}
