<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'products';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'sku',
        'unit',
        'standard_length',
        'category',
        'max_stock',
        'reorder_level',
        'carton_size',
        'unit_price',
        'selling_price',
        'standard_width',
        'sub_category',
        'equipment_condition',
        'assigned_to',
        'current_location',
        'asset_status',
        'baseline_date',
        'purchase_threshold',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'standard_length' => 'decimal:2',
        'max_stock' => 'decimal:2',
        'reorder_level' => 'integer',
        'carton_size' => 'integer',
        'unit_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'standard_width' => 'decimal:3',
        'baseline_date' => 'date',
        'purchase_threshold' => 'decimal:2',
        'deleted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [];

    /**
     * Scope a query to only include consumable products.
     */
    public function scopeConsumable(Builder $query): Builder
    {
        return $query->where('category', 'Consumable');
    }

    /**
     * Scope a query to only include fixed assets.
     */
    public function scopeFixedAsset(Builder $query): Builder
    {
        return $query->where('category', 'Fixed Asset');
    }

    /**
     * Scope a query to only include available products.
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('asset_status', 'Available');
    }

    /**
     * Scope a query to only include products below reorder level.
     */
    public function scopeBelowReorderLevel(Builder $query): Builder
    {
        return $query->whereRaw('max_stock <= reorder_level');
    }

    /**
     * Get the total value of the product (stock * unit price).
     */
    public function getTotalValueAttribute()
    {
        return $this->max_stock * $this->unit_price;
    }

    /**
     * Check if product is below reorder level.
     */
    public function isBelowReorderLevel()
    {
        return $this->max_stock <= $this->reorder_level;
    }

    /**
     * Check if product is a consumable.
     */
    public function isConsumable()
    {
        return $this->category === 'Consumable';
    }

    /**
     * Check if product is a fixed asset.
     */
    public function isFixedAsset()
    {
        return $this->category === 'Fixed Asset';
    }

    /**
     * Get formatted unit price.
     */
    public function getFormattedUnitPriceAttribute()
    {
        return number_format($this->unit_price, 2);
    }

    /**
     * Get formatted selling price.
     */
    public function getFormattedSellingPriceAttribute()
    {
        return number_format($this->selling_price, 2);
    }

    /**
     * Calculate profit margin.
     */
    public function getProfitMarginAttribute()
    {
        if ($this->unit_price == 0) {
            return 0;
        }
        
        return (($this->selling_price - $this->unit_price) / $this->unit_price) * 100;
    }

    /**
     * Get stock status badge color.
     */
    public function getStockStatusAttribute()
    {
        if ($this->max_stock == 0) {
            return 'out-of-stock';
        } elseif ($this->isBelowReorderLevel()) {
            return 'low-stock';
        } else {
            return 'in-stock';
        }
    }

    /**
     * Search products by name, SKU, or category.
     */
    public function scopeSearch(Builder $query, ?string $term = null): Builder
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('sku', 'like', "%{$term}%")
              ->orWhere('category', 'like', "%{$term}%")
              ->orWhere('sub_category', 'like', "%{$term}%");
        });
    }

    /**
     * Get products by location.
     */
    public function scopeAtLocation(Builder $query, ?string $location = null): Builder
    {
        return $query->where('current_location', $location);
    }

    /**
     * Get the inventory entries for this product.
     */
    public function inventory()
    {
        return $this->hasMany(Inventory::class, 'product_id');
    }

    /**
     * Get the market price history entries for this product.
     */
    public function materialPrices()
    {
        return $this->hasMany(MaterialPrice::class, 'product_id')->orderBy('effective_date', 'desc');
    }

    /**
     * Get the latest market price record for this product.
     */
    public function latestMarketPrice()
    {
        return $this->hasOne(MaterialPrice::class, 'product_id')->latestOfMany('effective_date');
    }
}
