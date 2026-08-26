<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

class FixedAsset extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'fixed_assets';

    protected $fillable = [
        'name',
        'category',
        'code_prefix',
        'total_quantity',
        'unit_cost',
        'purchase_date',
        'supplier',
        'store_id',
        'description',
        'created_by',
    ];

    protected $casts = [
        'total_quantity' => 'integer',
        'unit_cost'      => 'decimal:2',
        'purchase_date'  => 'date',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
        'deleted_at'     => 'datetime',
    ];

    protected static function booted()
    {
        static::saved(function (FixedAsset $fixedAsset) {
            $fixedAsset->syncWithCatalogAndInventory();
        });

        static::deleted(function (FixedAsset $fixedAsset) {
            try {
                $prefix = strtoupper(trim($fixedAsset->code_prefix ?: 'AST'));
                $sku = 'FA-' . $prefix;
                $product = Product::where('sku', $sku)->orWhere(function($q) use ($fixedAsset) {
                    $q->where('name', $fixedAsset->name)->where('category', 'Fixed Asset');
                })->first();

                if ($product) {
                    Inventory::where('product_id', $product->id)->delete();
                    $product->delete();
                }
            } catch (\Throwable $e) {
                Log::warning("FixedAsset delete sync failed: " . $e->getMessage());
            }
        });
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function units()
    {
        return $this->hasMany(FixedAssetUnit::class, 'fixed_asset_id')->orderBy('sequence_number');
    }

    public function availableUnits()
    {
        return $this->hasMany(FixedAssetUnit::class, 'fixed_asset_id')
            ->where('status', FixedAssetUnit::STATUS_IN_STORE)
            ->orderBy('sequence_number');
    }

    public function assignedUnits()
    {
        return $this->hasMany(FixedAssetUnit::class, 'fixed_asset_id')
            ->where('status', FixedAssetUnit::STATUS_ASSIGNED)
            ->orderBy('sequence_number');
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ─── Accessors & Helpers ──────────────────────────────────────────────────

    public function getUnitsCountAttribute(): int
    {
        return $this->units()->count();
    }

    public function getAvailableCountAttribute(): int
    {
        return $this->availableUnits()->count();
    }

    public function getAssignedCountAttribute(): int
    {
        return $this->assignedUnits()->count();
    }

    public function getTotalValueAttribute(): float
    {
        return (float) ($this->total_quantity * $this->unit_cost);
    }

    public function getCategoryIconAttribute(): string
    {
        return match(strtolower(trim($this->category))) {
            'computer & it', 'computer', 'it', 'electronics' => 'fa-laptop',
            'vehicle', 'vehicles', 'automotive'               => 'fa-truck-pickup',
            'heavy machinery', 'machinery', 'heavy equipment' => 'fa-truck-monster',
            'furniture', 'furniture & fixture'                => 'fa-chair',
            'tools', 'tools & equipment'                      => 'fa-screwdriver-wrench',
            default                                           => 'fa-boxes-stacked',
        };
    }

    /**
     * Check if more units can be added based on strict quantity limit.
     */
    public function canAddUnit(): bool
    {
        return $this->units()->count() < $this->total_quantity;
    }

    /**
     * Generate unit code with clean prefix format (e.g. COMP-1).
     */
    public function generateUnitCode(int $sequenceNumber): string
    {
        $prefix = strtoupper(trim($this->code_prefix ?: 'AST'));
        return "{$prefix}-{$sequenceNumber}";
    }

    /**
     * Auto-generate missing units to match the total_quantity.
     */
    public function generateUnitsToMatchQuantity(array $defaultAttributes = []): int
    {
        $currentCount = $this->units()->count();
        $targetQty = $this->total_quantity;
        $created = 0;

        if ($currentCount >= $targetQty) {
            return 0;
        }

        // Find max sequence number used so far
        $maxSeq = (int) $this->units()->max('sequence_number');

        for ($i = $currentCount + 1; $i <= $targetQty; $i++) {
            $maxSeq++;
            $unitCode = $this->generateUnitCode($maxSeq);

            // Avoid collisions if previously deleted or customized
            while (FixedAssetUnit::where('unit_code', $unitCode)->exists()) {
                $maxSeq++;
                $unitCode = $this->generateUnitCode($maxSeq);
            }

            FixedAssetUnit::create(array_merge([
                'fixed_asset_id'  => $this->id,
                'unit_code'       => $unitCode,
                'sequence_number' => $maxSeq,
                'status'          => FixedAssetUnit::STATUS_IN_STORE,
                'condition'       => 'good',
                'purchase_price'  => $this->unit_cost,
                'current_location'=> $this->store->name ?? 'Main Store',
                'created_by'      => auth()->id(),
            ], $defaultAttributes));

            $created++;
        }

        return $created;
    }

    /**
     * Synchronize this Fixed Asset with Material Catalog (products) and Store Inventory (inventory).
     */
    public function syncWithCatalogAndInventory(): ?Product
    {
        try {
            $prefix = strtoupper(trim($this->code_prefix ?: 'AST'));
            $sku = 'FA-' . $prefix;

            // 1. Sync Material Catalog (Product)
            $product = Product::withTrashed()->where('sku', $sku)
                ->orWhere(function($q) {
                    $q->where('name', $this->name)->where('category', 'Fixed Asset');
                })->first();

            $storeName = $this->store ? $this->store->name : 'Main Store';
            $inStoreCount = $this->units()->where('status', FixedAssetUnit::STATUS_IN_STORE)->count();
            $assignedCount = $this->units()->where('status', FixedAssetUnit::STATUS_ASSIGNED)->count();
            $totalCount = max((int) $this->total_quantity, $inStoreCount + $assignedCount);

            $productData = [
                'name'                => $this->name,
                'sku'                 => $sku,
                'category'            => 'Fixed Asset',
                'sub_category'        => $this->category ?? 'Equipment',
                'unit'                => 'Pcs',
                'unit_price'          => $this->unit_cost ?? 0.00,
                'selling_price'       => $this->unit_cost ?? 0.00,
                'max_stock'           => $totalCount,
                'reorder_level'       => 1,
                'equipment_condition' => 'Good',
                'assigned_to'         => $assignedCount > 0 ? ($assignedCount . ' Assigned to Staff') : 'Unassigned',
                'current_location'    => $storeName,
                'asset_status'        => $inStoreCount > 0 ? 'Available' : 'Assigned',
            ];

            if ($product) {
                if ($product->trashed()) {
                    $product->restore();
                }
                $product->update($productData);
            } else {
                $product = Product::create($productData);
            }

            // 2. Sync Store Inventory (Inventory)
            $storeId = $this->store_id;
            if (!$storeId) {
                $defaultStore = Store::where('is_active', true)->first();
                $storeId = $defaultStore ? $defaultStore->id : 1;
            }

            if ($storeId && $product) {
                Inventory::updateOrCreate(
                    [
                        'store_id'   => $storeId,
                        'product_id' => $product->id,
                    ],
                    [
                        'quantity_on_hand'  => $inStoreCount + $assignedCount,
                        'quantity_reserved' => $assignedCount,
                        'unit_cost'         => $this->unit_cost ?? 0.00,
                        'min_stock'         => 1,
                        'last_movement_at'  => now(),
                    ]
                );
            }

            return $product;
        } catch (\Throwable $e) {
            Log::warning("Failed to sync FixedAsset #{$this->id} ({$this->name}) to Catalog/Inventory: " . $e->getMessage());
            return null;
        }
    }
}
