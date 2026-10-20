<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrWorkflowLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'purchase_request_id', 'from_stage', 'to_stage',
        'action', 'actor_role', 'notes', 'actor_id', 'created_at',
        // Support legacy / controller alias parameter names:
        'from_status', 'to_status', 'performed_by', 'performed_by_role', 'performed_at',
    ];

    protected $casts = ['created_at' => 'datetime'];

    public function setFromStatusAttribute($value)
    {
        $this->attributes['from_stage'] = $value;
    }

    public function setToStatusAttribute($value)
    {
        $this->attributes['to_stage'] = $value;
    }

    public function setPerformedByAttribute($value)
    {
        $this->attributes['actor_id'] = $value;
    }

    public function setPerformedByRoleAttribute($value)
    {
        $this->attributes['actor_role'] = $value;
    }

    public function setPerformedAtAttribute($value)
    {
        $this->attributes['created_at'] = $value;
    }

    public function purchaseRequest() { return $this->belongsTo(PurchaseRequest::class); }
    public function actor()           { return $this->belongsTo(User::class, 'actor_id'); }
}
