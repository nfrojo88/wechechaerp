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
     * Completely and permanently purge a Product model instance from the entire system.
     *
     * @param Product $product
     * @return bool True if purged successfully.
     */
    public function purgeProduct(Product $product): bool
    {
        return $this->purgeInternal(
            productIds: [$product->id],
            sku: $product->sku,
            productName: $product->name
        );
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
            if (empty($s) || strtoupper($s) === 'DELETE') {
                continue;
            }
            // Strip numeric list prefix e.g. "1\tFA-BUST", "1. FA-BUST", "1 FA-BUST"
            if (preg_match('/^[0-9]+[\.\t\s]+(.+)$/', $s, $m)) {
                $s = trim($m[1]);
            }
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
        return $this->purgeInternal([], $sku);
    }

    /**
     * Internal worker to purge product IDs and/or SKU and all cascades within an atomic transaction.
     */
    public function purgeInternal(array $productIds = [], ?string $sku = null, ?string $productName = null): bool
    {
        $sku = trim((string)$sku);

        // Strip numeric list prefix e.g. "1\tFA-BUST", "1. FA-BUST", "1 FA-BUST"
        if (preg_match('/^[0-9]+[\.\t\s]+(.+)$/', $sku, $m)) {
            $sku = trim($m[1]);
        }

        $variants = [];
        if (!empty($sku)) {
            $variants = [$sku, strtoupper($sku), strtolower($sku)];
            if (str_contains($sku, ' ')) {
                $variants[] = str_replace(' ', '', $sku);
                $variants[] = str_replace(' ', '-', $sku);
                $variants[] = str_replace(' ', '_', $sku);
            }
            if (str_starts_with(strtoupper($sku), 'FE-')) {
                $variants[] = 'FA-' . substr(strtoupper($sku), 3);
            }
            $variants = array_values(array_unique(array_filter($variants)));
        }
        $variantsLower = array_map('strtolower', $variants);

        $skuUpper = !empty($sku) ? strtoupper($sku) : '';
        $skuLower = !empty($sku) ? strtolower($sku) : '';

        // 1. Resolve matching products (including soft-deleted)
        if (Schema::hasTable('products')) {
            if (!empty($variants)) {
                try {
                    $matchingProducts = Product::withTrashed()
                        ->where(function ($q) use ($variants, $variantsLower) {
                            $q->whereIn('sku', $variants)
                              ->orWhereIn(DB::raw('LOWER(TRIM(sku))'), $variantsLower);
                        })
                        ->get();
                    $foundIds = $matchingProducts->pluck('id')->toArray();
                    $productIds = array_merge($productIds, $foundIds);
                    if (empty($productName) && $matchingProducts->isNotEmpty()) {
                        $productName = $matchingProducts->first()->name;
                    }
                } catch (\Throwable $e) {}

                // Also check raw query in case model global scopes, soft delete settings, or mutators hide it
                try {
                    $rawIds = DB::table('products')
                        ->where(function ($q) use ($variants, $variantsLower) {
                            $q->whereIn('sku', $variants)
                              ->orWhereIn(DB::raw('LOWER(TRIM(sku))'), $variantsLower);
                            foreach ($variants as $v) {
                                $q->orWhere('sku', 'like', $v . '_DELETED_%');
                            }
                        })
                        ->pluck('id')
                        ->toArray();
                    $productIds = array_unique(array_merge($productIds, $rawIds));
                } catch (\Throwable $e) {}
            }

            // Also check by exact product name if available
            if (!empty($productName)) {
                try {
                    $nameIds = Product::withTrashed()
                        ->whereRaw('LOWER(TRIM(name)) = ?', [strtolower(trim($productName))])
                        ->pluck('id')
                        ->toArray();
                    $productIds = array_unique(array_merge($productIds, $nameIds));
                } catch (\Throwable $e) {}
            }
        }

        $productIds = array_values(array_unique(array_filter($productIds)));

        // 2. Resolve matching Fixed Assets (code_prefix, sku, description, or linked product_id)
        $faIds = [];
        if (Schema::hasTable('fixed_assets')) {
            $prefixes = [];
            foreach ($variants as $v) {
                $prefixes[] = $v;
                $prefixes[] = strtoupper($v);
                $prefixes[] = strtolower($v);
                if (str_starts_with(strtoupper($v), 'FA-')) {
                    $p = substr(strtoupper($v), 3);
                    $prefixes[] = $p;
                    $prefixes[] = strtolower($p);
                }
            }
            $prefixes = array_values(array_unique(array_filter($prefixes)));

            $faQuery = DB::table('fixed_assets');
            $hasCondition = false;

            if (Schema::hasColumn('fixed_assets', 'code_prefix') && !empty($prefixes)) {
                $faQuery->where(function ($q) use ($prefixes) {
                    $q->whereIn('code_prefix', $prefixes)
                      ->orWhereIn(DB::raw('LOWER(TRIM(code_prefix))'), array_map('strtolower', $prefixes));
                });
                $hasCondition = true;
            }

            if (Schema::hasColumn('fixed_assets', 'sku') && !empty($variants)) {
                $faQuery->orWhere(function ($q) use ($variants) {
                    $q->whereIn('sku', $variants);
                });
                $hasCondition = true;
            }

            if (Schema::hasColumn('fixed_assets', 'description') && !empty($variants)) {
                $faQuery->orWhere(function ($q) use ($variants) {
                    foreach ($variants as $v) {
                        $q->orWhere('description', 'like', "%SKU: {$v}%");
                    }
                });
                $hasCondition = true;
            }

            if (Schema::hasColumn('fixed_assets', 'name')) {
                $faQuery->orWhere(function ($q) use ($variants, $productName) {
                    foreach ($variants as $v) {
                        $q->orWhere('name', 'like', "%{$v}%");
                    }
                    if (!empty($productName)) {
                        $q->orWhereRaw('LOWER(TRIM(name)) = ?', [strtolower(trim($productName))]);
                    }
                });
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
        DB::transaction(function () use ($productIds, $faIds, $sku, $skuUpper, $skuLower, $variants, $productName) {
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

            // 16. Record in purged_materials so seeder / sync NEVER recreates it
            if (Schema::hasTable('purged_materials')) {
                try {
                    $skusToRecord = array_filter(array_merge([$sku], $variants));
                    if (empty($skusToRecord) && !empty($productName)) {
                        $skusToRecord = ['NAME:' . strtoupper(trim($productName))];
                    }
                    $toInsert = [];
                    foreach ($skusToRecord as $sk) {
                        $toInsert[] = [
                            'sku'        => $sk,
                            'name'       => $productName ?: $sk,
                            'purged_by'  => Auth::id() ?: 1,
                            'purged_at'  => now(),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                    if (!empty($toInsert)) {
                        DB::table('purged_materials')->insertOrIgnore($toInsert);
                    }
                } catch (\Throwable $e) {}
            }

            $actorId = Auth::id() ?: 1;
            Log::info("ProductPurgeService: Material SKU '{$sku}' permanently deleted (Actor ID: {$actorId})");
        });

        return true;
    }
}
