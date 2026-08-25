<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaterialRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'destination_store_id',
        'maintenance_request_id',
        'reference_number',
        'source',
        'status',
        'required_date',
        'notes',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'required_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'destination_store_id');
    }

    public function items()
    {
        return $this->hasMany(MaterialRequestItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function purchaseRequests()
    {
        return $this->hasMany(PurchaseRequest::class);
    }

    public function maintenanceRequest()
    {
        return $this->belongsTo(MaintenanceRequest::class, 'maintenance_request_id');
    }
}
