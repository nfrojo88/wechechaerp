<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Inventory;
use App\Models\FixedAsset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class MaterialToConsumableService
{
    /**
     * Convert an array of material SKUs from Fixed Asset to Consumable.
     * Sets product category to 'Consumable', purges serialized fixed_assets & units,
     * and preserves all inventory on-hand balances.
     *
     * @param array $skus
     * @return array
     */
    public function convertSkusToConsumable(array $skus): array
    {
        $parsedSkus = $this->expandSkuVariants($skus);

        if (empty($parsedSkus)) {
            return [
                'converted_count'      => 0,
                'converted_products'   => [],
                'fixed_assets_removed' => 0,
                'units_removed'        => 0,
                'not_found'            => [],
            ];
        }

        $convertedProducts = [];
        $notFound = [];
        $totalFaRemoved = 0;
        $totalUnitsRemoved = 0;

        foreach ($parsedSkus as $group) {
            $primarySku = $group['primary'];
            $variants = $group['variants'];
            $variantsLower = array_map('strtolower', $variants);

            $converted = false;

            // 1. Find Product(s) matching any variant
            $productIds = [];
            if (Schema::hasTable('products')) {
                try {
                    $prods = Product::withTrashed()
                        ->where(function ($q) use ($variants, $variantsLower) {
                            $q->whereIn('sku', $variants)
                              ->orWhereIn(DB::raw('LOWER(TRIM(sku))'), $variantsLower);
                        })
                        ->get();

                    foreach ($prods as $p) {
                        $productIds[] = $p->id;
                    }
                } catch (\Throwable $e) {}

                // Also check direct table query in case model global scopes hide soft-deleted or renamed rows
                try {
                    $rawIds = DB::table('products')
                        ->where(function ($q) use ($variants, $variantsLower) {
                            $q->whereIn('sku', $variants)
                              ->orWhereIn(DB::raw('LOWER(TRIM(sku))'), $variantsLower);
                        })
                        ->pluck('id')
                        ->toArray();
                    $productIds = array_unique(array_merge($productIds, $rawIds));
                } catch (\Throwable $e) {}
            }

            // 2. Find any FixedAsset(s) matching code_prefix, sku, name, or product_id
            $faIds = [];
            if (Schema::hasTable('fixed_assets')) {
                $faQuery = DB::table('fixed_assets');
                $hasCond = false;

                // Match by code_prefix
                $prefixes = [];
                foreach ($variants as $v) {
                    $prefixes[] = $v;
                    $prefixes[] = strtolower($v);
                    if (str_starts_with(strtoupper($v), 'FA-')) {
                        $p = substr($v, 3);
                        $prefixes[] = $p;
                        $prefixes[] = strtolower($p);
                        $prefixes[] = str_replace([' ', '-', '_'], '', $p);
                    }
                    if (str_starts_with(strtoupper($v), 'FE-')) {
                        $p = substr($v, 3);
                        $prefixes[] = $p;
                        $prefixes[] = strtolower($p);
                    }
                }
                $prefixes = array_unique(array_filter($prefixes));

                if (Schema::hasColumn('fixed_assets', 'code_prefix') && !empty($prefixes)) {
                    $faQuery->where(function ($q) use ($prefixes) {
                        $q->whereIn('code_prefix', $prefixes)
                          ->orWhereIn(DB::raw('LOWER(code_prefix)'), array_map('strtolower', $prefixes));
                    });
                    $hasCond = true;
                }

                if (Schema::hasColumn('fixed_assets', 'sku')) {
                    $faQuery->orWhereIn('sku', $variants);
                    $hasCond = true;
                }

                if (!empty($productIds) && Schema::hasColumn('fixed_assets', 'product_id')) {
                    $faQuery->orWhereIn('product_id', $productIds);
                    $hasCond = true;
                }

                if ($hasCond) {
                    $faIds = $faQuery->pluck('id')->toArray();
                }
            }

            // 3. Perform conversion if product or fixed asset found
            if (!empty($productIds) || !empty($faIds)) {
                DB::transaction(function () use (
                    $primarySku, $productIds, $faIds, &$converted,
                    &$convertedProducts, &$totalFaRemoved, &$totalUnitsRemoved
                ) {
                    // A. Update Product(s) to Consumable
                    if (!empty($productIds)) {
                        foreach ($productIds as $pId) {
                            $prod = Product::withTrashed()->find($pId);
                            if ($prod) {
                                if ($prod->trashed()) {
                                    $prod->restore();
                                }
                                $prod->category = 'Consumable';
                                $prod->asset_status = 'Available';
                                $prod->equipment_condition = 'Good';
                                $prod->assigned_to = 'Unassigned';
                                $prod->save();

                                $convertedProducts[] = [
                                    'id'   => $prod->id,
                                    'sku'  => $prod->sku,
                                    'name' => $prod->name,
                                ];
                            } else {
                                DB::table('products')->where('id', $pId)->update([
                                    'category'     => 'Consumable',
                                    'asset_status' => 'Available',
                                    'assigned_to'  => 'Unassigned',
                                    'deleted_at'   => null,
                                ]);
                                $convertedProducts[] = [
                                    'id'   => $pId,
                                    'sku'  => $primarySku,
                                    'name' => 'Converted Product #' . $pId,
                                ];
                            }

                            // Reset reserved quantity on inventory so entire stock is available as consumable
                            if (Schema::hasTable('inventory')) {
                                DB::table('inventory')->where('product_id', $pId)->update([
                                    'quantity_reserved' => 0,
                                ]);
                            }
                            if (Schema::hasTable('inventories')) {
                                DB::table('inventories')->where('product_id', $pId)->update([
                                    'quantity_reserved' => 0,
                                ]);
                            }
                        }
                    }

                    // B. Clean up Fixed Asset records and serial unit codes
                    if (!empty($faIds)) {
                        $unitIds = [];
                        if (Schema::hasTable('fixed_asset_units') && Schema::hasColumn('fixed_asset_units', 'fixed_asset_id')) {
                            $unitIds = DB::table('fixed_asset_units')->whereIn('fixed_asset_id', $faIds)->pluck('id')->toArray();
                        }

                        if (!empty($unitIds)) {
                            $safeDelete = function (string $table, string $column, array $ids) {
                                if (Schema::hasTable($table) && Schema::hasColumn($table, $column) && !empty($ids)) {
                                    try {
                                        DB::table($table)->whereIn($column, $ids)->delete();
                                    } catch (\Throwable $e) {}
                                }
                            };

                            $safeDelete('vehicle_fuel_requests', 'fixed_asset_unit_id', $unitIds);
                            $safeDelete('vehicle_km_logs', 'fixed_asset_unit_id', $unitIds);
                            $safeDelete('vehicle_reminders', 'fixed_asset_unit_id', $unitIds);
                            $safeDelete('maintenance_requests', 'fixed_asset_unit_id', $unitIds);
                            $safeDelete('fixed_asset_maintenance_logs', 'fixed_asset_unit_id', $unitIds);
                            $safeDelete('fixed_asset_assignments', 'fixed_asset_unit_id', $unitIds);
                            $safeDelete('equipment_fixed_asset_units', 'fixed_asset_unit_id', $unitIds);
                            $safeDelete('fixed_asset_units', 'id', $unitIds);

                            $totalUnitsRemoved += count($unitIds);
                        }

                        // Delete Fixed Asset parent rows
                        try {
                            DB::table('fixed_assets')->whereIn('id', $faIds)->delete();
                        } catch (\Throwable $e) {
                            if (Schema::hasColumn('fixed_assets', 'deleted_at')) {
                                DB::table('fixed_assets')->whereIn('id', $faIds)->update(['deleted_at' => now()]);
                            }
                        }

                        $totalFaRemoved += count($faIds);
                    }

                    $converted = true;
                    Log::info("MaterialToConsumableService: Successfully converted '{$primarySku}' to Consumable.");
                });
            }

            if (!$converted) {
                $notFound[] = $primarySku;
            }
        }

        return [
            'converted_count'      => count($convertedProducts),
            'converted_products'   => $convertedProducts,
            'fixed_assets_removed' => $totalFaRemoved,
            'units_removed'        => $totalUnitsRemoved,
            'not_found'            => $notFound,
        ];
    }

    /**
     * Expand SKU strings into possible database variants (e.g., spaces vs hyphens, FE- vs FA-).
     */
    protected function expandSkuVariants(array $skus): array
    {
        $result = [];

        foreach ($skus as $raw) {
            $raw = trim((string)$raw);
            if (empty($raw) || strtoupper($raw) === 'DELETE') {
                continue;
            }

            // Strip numeric list prefix e.g. "1\tFA-BUST" -> "FA-BUST"
            if (preg_match('/^[0-9]+[\.\t\s]+(.+)$/', $raw, $m)) {
                $raw = trim($m[1]);
            }

            $primary = $raw;
            $variants = [$primary];

            // Uppercase & lowercase
            $variants[] = strtoupper($primary);
            $variants[] = strtolower($primary);

            // Handle space variations e.g. "FA-B RUS" <-> "FA-BRUS", "FA-B-RUS"
            if (str_contains($primary, ' ')) {
                $variants[] = str_replace(' ', '', $primary);
                $variants[] = str_replace(' ', '-', $primary);
                $variants[] = str_replace(' ', '_', $primary);
            }

            // Handle "FE-GEN2" <-> "FA-GEN2"
            if (str_starts_with(strtoupper($primary), 'FE-')) {
                $variants[] = 'FA-' . substr($primary, 3);
            }

            // Handle "FA-" prefix removal for code_prefix
            if (str_starts_with(strtoupper($primary), 'FA-')) {
                $variants[] = substr($primary, 3);
            }

            $variants = array_values(array_unique(array_filter($variants)));

            $result[] = [
                'primary'  => $primary,
                'variants' => $variants,
            ];
        }

        return $result;
    }
}
