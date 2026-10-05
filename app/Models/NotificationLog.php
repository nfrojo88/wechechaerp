<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationLog extends Model
{
    protected $table = 'notification_log';

    protected $fillable = [
        'request_type',
        'request_id',
        'action',
        'sender_user_id',
        'recipient_user_id',
        'recipient_employee_id',
        'role',
        'phone',
        'message',
        'status',
        'error',
        'retries',
    ];

    protected $casts = [
        'retries' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function recipientEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'recipient_employee_id');
    }

    /**
     * Resolve the related request model.
     */
    public function getRequestModelAttribute()
    {
        if (!$this->request_id) {
            return null;
        }

        if ($this->request_type === 'material_request') {
            return MaterialRequest::find($this->request_id);
        }

        if ($this->request_type === 'transfer') {
            return Transfer::find($this->request_id);
        }

        return PurchaseRequest::find($this->request_id);
    }
}
