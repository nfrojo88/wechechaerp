<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeAsset extends Model
{
    use HasFactory;

    protected $table = 'employee_assets';

    protected $fillable = [
        'employee_id',
        'product_id',
        'quantity',
        'assigned_date',
        'returned_date',
        'status',
        'return_status',
        'store_manager_id',
        'return_notes',
        'notes',
    ];

    protected $casts = [
        'assigned_date' => 'date',
        'returned_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationship: Employee
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class)->withoutGlobalScope(\App\Scopes\NotDeadFileScope::class);
    }

    /**
     * Relationship: Product (Asset)
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Relationship: Store Manager
     */
    public function storeManager()
    {
        return $this->belongsTo(User::class, 'store_manager_id');
    }

    /**
     * Get asset name
     */
    public function getAssetNameAttribute()
    {
        return $this->product->name ?? 'Unknown Asset';
    }

    /**
     * Check if asset is active
     */
    public function isActive()
    {
        return in_array($this->status, ['assigned', 'in_use']);
    }

    /**
     * Return asset
     */
    public function returnAsset($notes = null)
    {
        $this->update([
            'status' => 'returned',
            'returned_date' => now(),
            'notes' => $notes,
        ]);

        return true;
    }

    /**
     * Mark as damaged
     */
    public function markDamaged($notes = null)
    {
        $this->update([
            'status' => 'damaged',
            'notes' => $notes,
        ]);

        return true;
    }
}
