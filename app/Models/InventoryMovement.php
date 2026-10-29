<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_id',
        'type',
        'quantity',
        'balance_before',
        'balance_after',
        'reference_type',
        'reference_id',
        'performed_by',
        'remarks',
        'notes',
    ];

    protected $casts = [
        'quantity'       => 'decimal:3',
        'balance_before' => 'decimal:3',
        'balance_after'  => 'decimal:3',
    ];

    protected static function booted()
    {
        static::updating(function () {
            throw new \RuntimeException("InventoryMovement ledger is append-only: updates are strictly prohibited.");
        });

        static::deleting(function () {
            throw new \RuntimeException("InventoryMovement ledger is append-only: deletes are strictly prohibited.");
        });
    }

    public function inventory()
    {
        return $this->belongsTo(Inventory::class);
    }

    public function reference()
    {
        return $this->morphTo();
    }

    public function performer()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
