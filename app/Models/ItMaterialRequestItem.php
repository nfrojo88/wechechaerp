<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItMaterialRequestItem extends Model
{
    protected $table = 'it_material_request_items';

    protected $fillable = [
        'support_ticket_id',
        'item_name',
        'unit',
        'quantity',
        'purpose',
        'urgency_level',
        'store_dispatch_qty',
        'store_dispatch_status',
        'store_notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    /**
     * Store dispatch status badge
     */
    public function getDispatchBadgeAttribute(): string
    {
        return match($this->store_dispatch_status) {
            'available'   => '<span class="badge bg-success">Available / Dispatched</span>',
            'partial'     => '<span class="badge bg-warning text-dark">Partially Available</span>',
            'unavailable' => '<span class="badge bg-danger">Unavailable</span>',
            default       => '<span class="badge bg-secondary">Pending Store Check</span>',
        };
    }
}
