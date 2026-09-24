<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

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

    /**
     * Resolve standard FixedAsset category from product name and category.
     */
    public static function resolveCategory(string $productCategory, string $productName = ''): string
    {
        $text = strtolower($productCategory . ' ' . $productName);

        if (str_contains($text, 'printer') || str_contains($text, 'scanner') || str_contains($text, 'computer') || str_contains($text, 'laptop') || str_contains($text, 'desktop') || str_contains($text, 'server') || str_contains($text, 'monitor') || str_contains($text, 'it') || str_contains($text, 'ups') || str_contains($text, 'copier')) {
            return 'Computer & IT';
        }
        if (str_contains($text, 'vehicle') || str_contains($text, 'truck') || str_contains($text, 'pickup') || str_contains($text, 'car') || str_contains($text, 'van') || str_contains($text, 'automotive')) {
            return 'Vehicle';
        }
        if (str_contains($text, 'machinery') || str_contains($text, 'excavator') || str_contains($text, 'crane') || str_contains($text, 'loader') || str_contains($text, 'roller') || str_contains($text, 'grader') || str_contains($text, 'bulldozer') || str_contains($text, 'forklift') || str_contains($text, 'mixer')) {
            return 'Heavy Machinery';
        }
        if (str_contains($text, 'chair') || str_contains($text, 'table') || str_contains($text, 'desk') || str_contains($text, 'cabinet') || str_contains($text, 'furniture') || str_contains($text, 'shelf') || str_contains($text, 'shelving') || str_contains($text, 'safe')) {
            return 'Furniture';
        }
        if (str_contains($text, 'tool') || str_contains($text, 'drill') || str_contains($text, 'grinder') || str_contains($text, 'welder') || str_contains($text, 'saw') || str_contains($text, 'pump') || str_contains($text, 'generator') || str_contains($text, 'compressor')) {
            return 'Tools & Equipment';
        }
        if (str_contains($text, 'electronic') || str_contains($text, 'camera') || str_contains($text, 'projector') || str_contains($text, 'tv') || str_contains($text, 'radio') || str_contains($text, 'appliance')) {
            return 'Electronics';
        }

        return 'Other';
    }

    /**
     * Generate clean, unique uppercase prefix for an asset.
     */
    public static function generateCleanPrefix(string $name, ?string $sku = null): string
    {
        if ($sku && str_starts_with($sku, 'FA-')) {
            $candidate = substr($sku, 3);
            if (strlen($candidate) >= 2) {
                return strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $candidate), 0, 5));
            }
        }
        $clean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $name));
        return substr($clean, 0, 4) ?: 'AST';
    }

    /**
     * Synchronize Fixed Assets from Direct Store Inventory & Material Catalog.
     * Can sync all asset-qualifying products, a specific product, or search-matched products.
     *
     * @param int|null $specificProductId
     * @param string|null $searchQuery
     * @return int Number of assets created or updated
     */
    public static function syncFromInventory(?int $specificProductId = null, ?string $searchQuery = null): int
    {
        try {
            $query = Product::withTrashed();

            if ($specificProductId) {
                $query->where('id', $specificProductId);
            } elseif ($searchQuery && strlen(trim($searchQuery)) >= 2) {
                $cleanSearch = trim($searchQuery);
                $query->where(function($q) use ($cleanSearch) {
                    $q->where('name', 'like', "%{$cleanSearch}%")
                      ->orWhere('sku', 'like', "%{$cleanSearch}%")
                      ->orWhere('category', 'like', "%{$cleanSearch}%")
                      ->orWhere('sub_category', 'like', "%{$cleanSearch}%");
                });
            } else {
                $assetCategories = [
                    'Fixed Asset', 'fixed asset', 'fixed_asset',
                    'Computer & IT', 'computer & it', 'IT Equipment', 'it equipment', 'IT', 'it',
                    'Office Equipment', 'office equipment',
                    'Equipment', 'equipment',
                    'Vehicle', 'vehicle', 'Vehicles', 'vehicles', 'Automotive', 'automotive',
                    'Heavy Machinery', 'heavy machinery', 'Machinery', 'machinery', 'Heavy Equipment',
                    'Furniture', 'furniture', 'Furniture & Fixture', 'furniture & fixture',
                    'Tools', 'tools', 'Tools & Equipment', 'tools & equipment',
                    'Electronics', 'electronics',
                    'Appliances', 'appliances', 'Appliance', 'appliance',
                    'Hardware', 'hardware',
                    'Printer', 'printer', 'Printers', 'printers',
                ];

                $assetKeywords = [
                    'printer', 'scanner', 'copier', 'photocopier',
                    'laptop', 'desktop', 'computer', 'server', 'monitor', 'screen', 'ups', 'router', 'switch',
                    'generator', 'compressor', 'pump', 'welder', 'welding', 'drill', 'grinder', 'saw',
                    'excavator', 'loader', 'roller', 'grader', 'bulldozer', 'crane', 'forklift', 'mixer', 'vibrator',
                    'truck', 'pickup', 'car', 'van', 'vehicle', 'tractor', 'trailer',
                    'chair', 'table', 'desk', 'cabinet', 'shelf', 'shelving', 'safe',
                    'camera', 'projector', 'radio', 'walkie talkie', 'television', 'tv', 'refrigerator', 'ac', 'air conditioner',
                ];

                $query->where(function($q) use ($assetCategories, $assetKeywords) {
                    $q->whereIn('category', $assetCategories)
                      ->orWhereIn('sub_category', $assetCategories)
                      ->orWhere('sku', 'like', 'FA-%')
                      ->orWhere('sku', 'like', 'AST-%');

                    foreach ($assetKeywords as $keyword) {
                        $q->orWhere('name', 'like', "%{$keyword}%");
                    }
                });
            }

            $products = $query->get();
            $syncedCount = 0;

            foreach ($products as $prod) {
                // Total on hand in inventory
                $totalOnHand = (int) round(Inventory::where('product_id', $prod->id)->sum('quantity_on_hand'));
                $totalReserved = (int) round(Inventory::where('product_id', $prod->id)->sum('quantity_reserved'));
                $stockQty = $totalOnHand > 0 ? $totalOnHand : $totalReserved;

                // Track at least 1 if product exists in catalog as an asset, or the actual inventory quantity
                $targetQty = max($stockQty, 1);

                $unitCost = (float) (
                    Inventory::where('product_id', $prod->id)->where('unit_cost', '>', 0)->value('unit_cost')
                    ?: DB::table('material_prices')->where('product_id', $prod->id)->orderByDesc('effective_date')->value('price')
                    ?: $prod->unit_price
                    ?: $prod->selling_price
                    ?: 0
                );

                $storeId = Inventory::where('product_id', $prod->id)->where('quantity_on_hand', '>', 0)->value('store_id')
                    ?: Store::where('is_active', true)->value('id');

                $category = self::resolveCategory($prod->category . ' ' . $prod->sub_category, $prod->name);

                // Find existing FixedAsset by name or SKU/ID in description
                $fixedAsset = FixedAsset::withTrashed()
                    ->where(function($q) use ($prod) {
                        $q->whereRaw('LOWER(TRIM(name)) = ?', [strtolower(trim($prod->name))])
                          ->orWhere('description', 'like', "%(Product ID: {$prod->id})%")
                          ->orWhere('description', 'like', "%SKU: " . ($prod->sku ?? '') . "%");
                    })->first();

                if ($fixedAsset) {
                    if ($fixedAsset->trashed()) {
                        $fixedAsset->restore();
                    }

                    $needsSave = false;
                    if ($targetQty > $fixedAsset->total_quantity) {
                        $fixedAsset->total_quantity = $targetQty;
                        $needsSave = true;
                    }
                    if ($unitCost > 0 && (!$fixedAsset->unit_cost || $fixedAsset->unit_cost == 0)) {
                        $fixedAsset->unit_cost = $unitCost;
                        $needsSave = true;
                    }
                    if (!$fixedAsset->store_id && $storeId) {
                        $fixedAsset->store_id = $storeId;
                        $needsSave = true;
                    }

                    if ($needsSave) {
                        $fixedAsset->saveQuietly();
                    }

                    // Generate any missing units up to total_quantity
                    $fixedAsset->generateUnitsToMatchQuantity([
                        'purchase_price'   => $unitCost,
                        'current_location' => $fixedAsset->store?->name ?? 'Main Store',
                        'condition'        => $prod->equipment_condition ?: 'good',
                    ]);

                    $syncedCount++;
                } else {
                    // Create new FixedAsset
                    $basePrefix = self::generateCleanPrefix($prod->name, $prod->sku);
                    $prefix = $basePrefix;
                    $c = 1;
                    while (FixedAsset::where('code_prefix', $prefix)->exists()) {
                        $c++;
                        $prefix = substr($basePrefix, 0, 3) . $c;
                    }

                    $fixedAsset = FixedAsset::create([
                        'name'           => $prod->name,
                        'category'       => $category,
                        'code_prefix'    => $prefix,
                        'total_quantity' => $targetQty,
                        'unit_cost'      => $unitCost,
                        'store_id'       => $storeId,
                        'supplier'       => $prod->supplier ?? null,
                        'description'    => "Auto-synced from Direct Store Inventory (Product SKU: " . ($prod->sku ?? 'N/A') . ", Product ID: {$prod->id})",
                        'created_by'     => auth()->id(),
                    ]);

                    $fixedAsset->generateUnitsToMatchQuantity([
                        'purchase_price'   => $unitCost,
                        'current_location' => $fixedAsset->store?->name ?? 'Main Store',
                        'condition'        => $prod->equipment_condition ?: 'good',
                    ]);

                    $syncedCount++;
                }
            }

            if ($syncedCount > 0) {
                Cache::forget('sidebar_fixed_asset_units_count');
            }

            return $syncedCount;
        } catch (\Throwable $e) {
            Log::warning("FixedAsset::syncFromInventory failed: " . $e->getMessage());
            return 0;
        }
    }
}
