<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;

class ProductPurgeService
{
    /**
     * Completely and permanently purge a single material / product by SKU,
     * including all child inventory, movements, fixed assets, units, assignments,
     * transactions, and references.
     *
     * @param string $sku
     * @return bool True if a product or fixed asset was found and purged, false if not found.
     */
    public function purgeBySku(string $sku): bool
    {
        $result = $this->purgeSkus([$sku]);
        return ($result['deleted_count'] > 0);
    }

    /**
     * Completely and permanently purge multiple material / product SKUs in bulk.
     *
     * @param array $skus
     * @return array Summary of purged records: ['deleted_count' => int, 'deleted_skus' => array, 'failed_skus' => array]
     */
    public function purgeSkus(array $skus): array
    {
        $cleanSkus = [];
        foreach ($skus as $s) {
            $s = trim((string)$s);
            if (!empty($s) && strtoupper($s) !== 'DELETE') {
                $cleanSkus[] = $s;
            }
        }
        $cleanSkus = array_values(array_unique($cleanSkus));

        if (empty($cleanSkus)) {
            return [
                'deleted_count' => 0,
                'deleted_skus'  => [],
                'failed_skus'   => [],
            ];
        }

        $deletedSkus = [];
        $failedSkus = [];

        foreach ($cleanSkus as $sku) {
            try {
                $purged = $this->purgeSingleSkuInternal($sku);
                if ($purged) {
                    $deletedSkus[] = $sku;
                } else {
                    $failedSkus[] = "SKU '{$sku}' not found";
                }
            } catch (\Throwable $e) {
                Log::error("Failed to completely delete material SKU '{$sku}': " . $e->getMessage(), [
                    'exception' => $e
                ]);
                $failedSkus[] = "SKU '{$sku}' failed ({$e->getMessage()})";
            }
        }

        return [
            'deleted_count' => count($deletedSkus),
            'deleted_skus'  => $deletedSkus,
            'failed_skus'   => $failedSkus,
        ];
    }

    /**
     * Internal worker to purge a single SKU and all its cascades within a transaction.
     */
    protected function purgeSingleSkuInternal(string $sku): bool
    {
        $sku = trim($sku);
        if (empty($sku)) return false;

        $skuUpper = strtoupper($sku);
        $skuLower = strtolower($sku);

        // 1. Resolve matching products (including soft-deleted)
        $productIds = [];
        if (Schema::hasTable('products')) {
            try {
                $matchingProducts = Product::withTrashed()
                    ->where('sku', $sku)
                    ->orWhere('sku', $skuUpper)
                    ->orWhere(DB::raw('LOWER(TRIM(sku))'), $skuLower)
                    ->get();
                $productIds = $matchingProducts->pluck('id')->toArray();
            } catch (\Throwable $e) {}

            // Also check raw query in case model global scopes, soft delete settings, or mutators hide it
            try {
                $rawIds = DB::table('products')
                    ->where('sku', $sku)
                    ->orWhere('sku', $skuUpper)
                    ->orWhere(DB::raw('LOWER(TRIM(sku))'), $skuLower)
                    ->orWhere('sku', 'like', $sku . '_DELETED_%')
                    ->pluck('id')
                    ->toArray();
                $productIds = array_unique(array_merge($productIds, $rawIds));
            } catch (\Throwable $e) {}
        }

        // 2. Resolve matching Fixed Assets (code_prefix, sku, description, or linked product_id)
        $faIds = [];
        if (Schema::hasTable('fixed_assets')) {
            $prefixWithoutFa = str_starts_with($skuUpper, 'FA-') ? substr($skuUpper, 3) : null;
            $faQuery = DB::table('fixed_assets');
            $hasCondition = false;

            if (Schema::hasColumn('fixed_assets', 'code_prefix')) {
                $faQuery->where(function ($q) use ($sku, $skuUpper, $skuLower, $prefixWithoutFa) {
                    $q->where('code_prefix', $sku)
                      ->orWhere('code_prefix', $skuUpper)
                      ->orWhere('code_prefix', $skuLower);
                    if ($prefixWithoutFa) {
                        $q->orWhere('code_prefix', $prefixWithoutFa)
                          ->orWhere('code_prefix', strtolower($prefixWithoutFa));
                    }
                });
                $hasCondition = true;
            }

            if (Schema::hasColumn('fixed_assets', 'sku')) {
                $faQuery->orWhere(function ($q) use ($sku, $skuUpper) {
                    $q->where('sku', $sku)->orWhere('sku', $skuUpper);
                });
                $hasCondition = true;
            }

            if (Schema::hasColumn('fixed_assets', 'description')) {
                $faQuery->orWhere('description', 'like', "%SKU: {$sku}%")
                        ->orWhere('description', 'like', "%SKU: {$skuUpper}%");
                $hasCondition = true;
            }

            if (Schema::hasColumn('fixed_assets', 'name')) {
                $faQuery->orWhere('name', 'like', "%{$sku}%");
                $hasCondition = true;
            }

            if (!empty($productIds) && Schema::hasColumn('fixed_assets', 'product_id')) {
                $faQuery->orWhereIn('product_id', $productIds);
                $hasCondition = true;
            }

            if ($hasCondition) {
                $faIds = $faQuery->pluck('id')->toArray();
            }
        }

        if (empty($productIds) && empty($faIds)) {
            return false;
        }

        // 3. Perform atomic cascaded purge
        DB::transaction(function () use ($productIds, $faIds, $sku, $skuUpper, $skuLower) {
            $safeDeleteByColumn = function (string $table, string $column, array $ids) {
                if (empty($ids)) return;
                try {
                    if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                        DB::table($table)->whereIn($column, $ids)->delete();
                    }
                } catch (\Throwable $e) {
                    Log::warning("ProductPurgeService: could not delete from {$table}.{$column}: " . $e->getMessage());
                }
            };

            $safeNullifyByColumn = function (string $table, string $column, array $ids) {
                if (empty($ids)) return;
                try {
                    if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                        DB::table($table)->whereIn($column, $ids)->update([$column => null]);
                    }
                } catch (\Throwable $e) {
                    Log::warning("ProductPurgeService: could not nullify {$table}.{$column}: " . $e->getMessage());
                }
            };

            // ── Purge Fixed Assets and child units/assignments ─────────────────
            $unitIds = [];
            if (!empty($faIds)) {
                if (Schema::hasTable('fixed_asset_units') && Schema::hasColumn('fixed_asset_units', 'fixed_asset_id')) {
                    $unitIds = DB::table('fixed_asset_units')->whereIn('fixed_asset_id', $faIds)->pluck('id')->toArray();
                }
            }

            if (!empty($productIds) && Schema::hasTable('fixed_asset_units') && Schema::hasColumn('fixed_asset_units', 'product_id')) {
                $prodUnitIds = DB::table('fixed_asset_units')->whereIn('product_id', $productIds)->pluck('id')->toArray();
                $unitIds = array_unique(array_merge($unitIds, $prodUnitIds));
            }

            if (!empty($unitIds)) {
                // Delete vehicle requests, logs, and reminders
                $safeDeleteByColumn('vehicle_fuel_requests', 'fixed_asset_unit_id', $unitIds);
                $safeDeleteByColumn('vehicle_km_logs', 'fixed_asset_unit_id', $unitIds);
                $safeDeleteByColumn('vehicle_reminders', 'fixed_asset_unit_id', $unitIds);
                $safeDeleteByColumn('maintenance_requests', 'fixed_asset_unit_id', $unitIds);
                $safeDeleteByColumn('fixed_asset_maintenance_logs', 'fixed_asset_unit_id', $unitIds);
                $safeDeleteByColumn('fixed_asset_assignments', 'fixed_asset_unit_id', $unitIds);
                $safeDeleteByColumn('equipment_fixed_asset_units', 'fixed_asset_unit_id', $unitIds);
            }

            if (!empty($faIds)) {
                $safeDeleteByColumn('fixed_asset_assignments', 'fixed_asset_id', $faIds);
                $safeDeleteByColumn('vehicle_reminders', 'fixed_asset_id', $faIds);
                $safeDeleteByColumn('fixed_asset_maintenance_logs', 'fixed_asset_id', $faIds);
                $safeDeleteByColumn('fixed_asset_depreciations', 'fixed_asset_id', $faIds);
                $safeDeleteByColumn('fixed_asset_documents', 'fixed_asset_id', $faIds);
                $safeDeleteByColumn('fixed_asset_units', 'fixed_asset_id', $faIds);
                $safeDeleteByColumn('fixed_assets', 'id', $faIds);
            }

            if (!empty($unitIds)) {
                $safeDeleteByColumn('fixed_asset_units', 'id', $unitIds);
            }

            // ── Purge Product records and direct relations ─────────────────────
            if (!empty($productIds)) {
                // 1. Inventory and movements
                $invIds = [];
                if (Schema::hasTable('inventory') && Schema::hasColumn('inventory', 'product_id')) {
                    $invIds = array_merge($invIds, DB::table('inventory')->whereIn('product_id', $productIds)->pluck('id')->toArray());
                }
                if (Schema::hasTable('inventories') && Schema::hasColumn('inventories', 'product_id')) {
                    $invIds = array_merge($invIds, DB::table('inventories')->whereIn('product_id', $productIds)->pluck('id')->toArray());
                }
                $invIds = array_unique($invIds);

                $safeDeleteByColumn('inventory_movements', 'inventory_id', $invIds);
                $safeDeleteByColumn('inventory', 'product_id', $productIds);
                $safeDeleteByColumn('inventories', 'product_id', $productIds);

                // 2. Rebar dia products
                $safeDeleteByColumn('rebar_dia_products', 'product_id', $productIds);

                // 3. Material prices
                $safeDeleteByColumn('material_prices', 'product_id', $productIds);

                // 4. Material damage reports
                $safeDeleteByColumn('material_damage_reports', 'product_id', $productIds);

                // 5. Employee assets
                $safeDeleteByColumn('employee_assets', 'product_id', $productIds);

                // 6. Petty cash purchases
                $safeDeleteByColumn('petty_cash_material_purchase_items', 'product_id', $productIds);
                $safeDeleteByColumn('petty_cash_material_purchases', 'product_id', $productIds);

                // 7. Delivery receipts
                $safeDeleteByColumn('delivery_receipt_items', 'product_id', $productIds);
                $safeDeleteByColumn('delivery_receipts', 'product_id', $productIds);

                // 8. Transfers
                $safeDeleteByColumn('transfer_items', 'product_id', $productIds);
                $safeDeleteByColumn('transfers', 'product_id', $productIds);

                // 9. Purchase orders & requests
                $safeDeleteByColumn('purchase_order_items', 'product_id', $productIds);
                $safeDeleteByColumn('purchase_request_items', 'product_id', $productIds);
                $safeDeleteByColumn('material_request_items', 'product_id', $productIds);

                // 10. Material usage, optimization, waste, plans
                $safeDeleteByColumn('material_usage_items', 'product_id', $productIds);
                $safeDeleteByColumn('cut_optimization_items', 'product_id', $productIds);
                $safeDeleteByColumn('waste_items', 'product_id', $productIds);
                $safeDeleteByColumn('material_plan_items', 'product_id', $productIds);

                // 11. Market research
                $safeDeleteByColumn('market_research_items', 'product_id', $productIds);
                $safeDeleteByColumn('market_research', 'product_id', $productIds);

                // 12. Tool transactions
                $safeDeleteByColumn('tool_transactions', 'equipment_id', $productIds);
                $safeDeleteByColumn('tool_transactions', 'product_id', $productIds);

                // 13. Nullify references in composite documents
                $safeNullifyByColumn('boq_items', 'product_id', $productIds);
                $safeNullifyByColumn('takeoff_items', 'product_id', $productIds);
                $safeNullifyByColumn('office_material_request_items', 'product_id', $productIds);
                $safeNullifyByColumn('office_material_requests', 'product_id', $productIds);
                $safeNullifyByColumn('equipment_masters', 'product_id', $productIds);
                $safeNullifyByColumn('equipment_fixed_asset_units', 'product_id', $productIds);

                // 14. Force-delete Product Eloquent model instances
                try {
                    Product::withTrashed()->whereIn('id', $productIds)->forceDelete();
                } catch (\Throwable $e) {}

                // Direct table purge by IDs
                $safeDeleteByColumn('products', 'id', $productIds);
            }

            // 15. Direct table purge by SKU just in case
            try {
                if (Schema::hasTable('products') && Schema::hasColumn('products', 'sku')) {
                    DB::table('products')
                        ->where('sku', $sku)
                        ->orWhere('sku', $skuUpper)
                        ->orWhere(DB::raw('LOWER(TRIM(sku))'), $skuLower)
                        ->orWhere('sku', 'like', $sku . '_DELETED_%')
                        ->delete();
                }
            } catch (\Throwable $e) {}

            $actorId = Auth::id() ?: 1;
            Log::info("ProductPurgeService: Material SKU '{$sku}' permanently deleted (Actor ID: {$actorId})");
        });

        return true;
    }
}
