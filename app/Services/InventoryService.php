<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\FixedAsset;
use App\Models\FixedAssetUnit;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InventoryService
{
    /**
     * Single Source of Truth for all inventory mutations.
     * Updates inventory balance and writes the ledger row inside one database transaction.
     *
     * @param int $storeId
     * @param int $productId
     * @param float $quantityDelta  Delta for on-hand stock (+ for in, - for out, 0 for pure reservation changes)
     * @param string $type          Type of movement: in, out, transfer, adjustment, return, fa_register, fa_assign, etc.
     * @param string|null $referenceType
     * @param int|null $referenceId
     * @param string|null $remarks
     * @param int|null $performedBy
     * @param float|null $unitCost
     * @param float $reservedDelta  Delta for reserved stock (+ for assign/reserve, - for return/release)
     * @return array ['inventory' => Inventory, 'movement' => InventoryMovement, 'balance_before' => float, 'balance_after' => float]
     *
     * @throws \InvalidArgumentException
     */
    public function applyMovement(
        int $storeId,
        int $productId,
        float $quantityDelta,
        string $type,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $remarks = null,
        ?int $performedBy = null,
        ?float $unitCost = null,
        float $reservedDelta = 0.0
    ): array {
        return DB::transaction(function () use (
            $storeId, $productId, $quantityDelta, $type,
            $referenceType, $referenceId, $remarks, $performedBy,
            $unitCost, $reservedDelta
        ) {
            $inventory = Inventory::where('store_id', $storeId)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->first();

            if (!$inventory) {
                $inventory = Inventory::create([
                    'store_id'          => $storeId,
                    'product_id'        => $productId,
                    'quantity_on_hand'  => 0,
                    'quantity_reserved' => 0,
                    'unit_cost'         => $unitCost ?? 0,
                    'min_stock'         => 0,
                ]);
                $inventory = Inventory::where('id', $inventory->id)->lockForUpdate()->first();
            }

            $balanceBefore  = (float) $inventory->quantity_on_hand;
            $reservedBefore = (float) $inventory->quantity_reserved;

            $newOnHand   = round($balanceBefore + $quantityDelta, 3);
            $newReserved = round($reservedBefore + $reservedDelta, 3);

            // 1. Strict Rule: Quantity on hand cannot fall below zero
            if ($newOnHand < -0.0001) {
                throw new \InvalidArgumentException(
                    "Cannot deduct " . abs($quantityDelta) . " units. Stock would fall below zero (Current on-hand: {$balanceBefore})."
                );
            }
            $newOnHand = max(0.0, $newOnHand);

            // 2. Strict Rule: Quantity on hand cannot fall below reserved quantity (Assigned stock protection)
            if ($newOnHand < $newReserved - 0.0001) {
                $available = max(0.0, $balanceBefore - $reservedBefore);
                throw new \InvalidArgumentException(
                    "Cannot deduct " . abs($quantityDelta) . " units: only {$available} available, {$reservedBefore} are currently assigned/reserved to staff."
                );
            }
            $newReserved = max(0.0, $newReserved);

            // 3. Cost calculation on stock-in
            if ($quantityDelta > 0 && $unitCost !== null && $unitCost > 0) {
                $totalOld = $balanceBefore * (float) ($inventory->unit_cost ?? 0);
                $totalNew = $quantityDelta * $unitCost;
                $inventory->unit_cost = $newOnHand > 0 ? ($totalOld + $totalNew) / $newOnHand : $unitCost;
            } elseif ($unitCost !== null && $unitCost > 0 && ($inventory->unit_cost === null || $inventory->unit_cost <= 0)) {
                $inventory->unit_cost = $unitCost;
            }

            $inventory->quantity_on_hand  = $newOnHand;
            $inventory->quantity_reserved = $newReserved;
            $inventory->last_movement_at  = now();
            $inventory->save();

            $balanceAfter = $newOnHand;

            $movement = InventoryMovement::create([
                'inventory_id'   => $inventory->id,
                'type'           => $type,
                'quantity'       => $quantityDelta,
                'balance_before' => $balanceBefore,
                'balance_after'  => $balanceAfter,
                'reference_type' => $referenceType,
                'reference_id'   => $referenceId,
                'performed_by'   => $performedBy ?? auth()->id() ?? 1,
                'remarks'        => $remarks,
            ]);

            // Synchronize Fixed Asset units if product is a Fixed Asset
            $this->syncFixedAssetUnitsForMovement($inventory, $quantityDelta, $newOnHand, $type, $performedBy);

            return [
                'inventory'      => $inventory->fresh(),
                'movement'       => $movement,
                'balance_before' => $balanceBefore,
                'balance_after'  => $balanceAfter,
            ];
        });
    }

    /**
     * Synchronize Fixed Asset units for a movement on a Fixed Asset catalog product:
     * - Positive adjustment: creates new unit records with "Details incomplete".
     * - Negative adjustment: marks unassigned (in_store) units as 'disposed' (keeps records, never deletes).
     * - Updates FixedAsset total_quantity to match inventory.quantity_on_hand.
     */
    protected function syncFixedAssetUnitsForMovement(
        Inventory $inventory,
        float $quantityDelta,
        float $newOnHand,
        string $type,
        ?int $performedBy = null
    ): void {
        try {
            // Skip direct internal FA actions that manage their own units explicitly
            if (in_array($type, ['fa_register', 'fa_disposed', 'fa_damaged', 'fa_assign', 'fa_return'])) {
                $fixedAsset = FixedAsset::where('store_id', $inventory->store_id)
                    ->where(function ($q) use ($inventory) {
                        $product = $inventory->product ?? Product::find($inventory->product_id);
                        if ($product) {
                            $prefix = str_starts_with($product->sku ?? '', 'FA-') ? substr($product->sku, 3) : null;
                            if ($prefix) {
                                $q->where('code_prefix', $prefix);
                            }
                            $q->orWhere('name', $product->name);
                        }
                    })->first();

                if ($fixedAsset) {
                    $fixedAsset->total_quantity = max(0, (int) round($newOnHand));
                    $fixedAsset->saveQuietly();
                }
                return;
            }

            $product = $inventory->product ?? Product::find($inventory->product_id);
            if (!$product) {
                return;
            }

            $cat = strtolower(trim($product->category ?? ''));
            $sku = strtoupper(trim($product->sku ?? ''));
            $isFixedAsset = str_contains($cat, 'fixed') || str_contains($cat, 'asset') || str_starts_with($sku, 'FA-');

            if (!$isFixedAsset) {
                return;
            }

            $prefix = str_starts_with($sku, 'FA-') ? substr($sku, 3) : null;

            $fixedAsset = FixedAsset::where('store_id', $inventory->store_id)
                ->where(function ($q) use ($product, $prefix) {
                    if ($prefix) {
                        $q->where('code_prefix', $prefix);
                    }
                    $q->orWhere('name', $product->name);
                })->first();

            if (!$fixedAsset) {
                // If asset doesn't exist yet and stock is positive, create asset definition
                if ($quantityDelta > 0) {
                    $basePrefix = FixedAsset::generateCleanPrefix($product->name, $product->sku);
                    $assetPrefix = $basePrefix;
                    $c = 1;
                    while (FixedAsset::where('code_prefix', $assetPrefix)->exists()) {
                        $c++;
                        $assetPrefix = substr($basePrefix, 0, 3) . $c;
                    }

                    $fixedAsset = FixedAsset::create([
                        'name'           => $product->name,
                        'category'       => FixedAsset::resolveCategory($product->category . ' ' . ($product->sub_category ?? ''), $product->name),
                        'code_prefix'    => $assetPrefix,
                        'total_quantity' => (int) round($newOnHand),
                        'unit_cost'      => (float) ($inventory->unit_cost ?? $product->unit_price ?? 0),
                        'purchase_date'  => now(),
                        'store_id'       => $inventory->store_id,
                        'description'    => "Auto-created from Store Inventory (SKU: {$product->sku})",
                        'created_by'     => $performedBy ?? auth()->id(),
                    ]);
                } else {
                    return;
                }
            }

            $assetPrefix = strtoupper(trim($fixedAsset->code_prefix ?: 'AST'));

            if ($quantityDelta > 0) {
                // Positive adjustment: generate missing units flagged with "Details incomplete"
                $count = (int) round($quantityDelta);
                $maxSeq = (int) $fixedAsset->units()->max('sequence_number') ?: 0;
                $storeName = $inventory->store?->name ?? 'Main Store';

                for ($i = 0; $i < $count; $i++) {
                    $maxSeq++;
                    $unitCode = "{$assetPrefix}-{$maxSeq}";
                    while (FixedAssetUnit::where('unit_code', $unitCode)->exists()) {
                        $maxSeq++;
                        $unitCode = "{$assetPrefix}-{$maxSeq}";
                    }

                    FixedAssetUnit::create([
                        'fixed_asset_id'   => $fixedAsset->id,
                        'unit_code'        => $unitCode,
                        'sequence_number'  => $maxSeq,
                        'status'           => FixedAssetUnit::STATUS_IN_STORE,
                        'condition'        => 'good',
                        'current_location' => $storeName,
                        'purchase_price'   => $inventory->unit_cost,
                        'notes'            => 'Details incomplete — automatically generated from manual stock addition. Please update serial number and specifications.',
                        'created_by'       => $performedBy ?? auth()->id(),
                    ]);
                }
            } elseif ($quantityDelta < 0) {
                // Negative adjustment: pick unassigned (in_store) units and set status = 'disposed'. Keep records!
                $count = (int) round(abs($quantityDelta));
                $unitsToDispose = $fixedAsset->units()
                    ->where('status', FixedAssetUnit::STATUS_IN_STORE)
                    ->take($count)
                    ->get();

                foreach ($unitsToDispose as $u) {
                    $historyNote = "[" . now()->format('Y-m-d H:i') . "] Marked as disposed due to stock reduction.";
                    $u->update([
                        'status' => FixedAssetUnit::STATUS_DISPOSED,
                        'notes'  => $u->notes ? $u->notes . "\n" . $historyNote : $historyNote,
                    ]);
                }
            }

            $fixedAsset->total_quantity = max(0, (int) round($newOnHand));
            $fixedAsset->saveQuietly();

        } catch (\Throwable $e) {
            Log::warning("syncFixedAssetUnitsForMovement failed: " . $e->getMessage());
        }
    }

    /**
     * Move stock into a store (receipt, transfer-in, adjustment+).
     */
    public function stockIn(
        int $storeId,
        int $productId,
        float $quantity,
        float $unitCost,
        string $type,          // 'in' | 'transfer' | 'adjustment' | 'return'
        int $performedBy,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $remarks = null
    ): Inventory {
        $result = $this->applyMovement(
            $storeId,
            $productId,
            $quantity,
            $type,
            $referenceType,
            $referenceId,
            $remarks,
            $performedBy,
            $unitCost
        );

        return $result['inventory'];
    }

    /**
     * Move stock out of a store (issue, transfer-out, adjustment-).
     *
     * @throws \Exception if insufficient stock
     */
    public function stockOut(
        int $storeId,
        int $productId,
        float $quantity,
        string $type,          // 'out' | 'transfer' | 'adjustment' | 'return'
        int $performedBy,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $remarks = null
    ): Inventory {
        $result = $this->applyMovement(
            $storeId,
            $productId,
            -$quantity,
            $type,
            $referenceType,
            $referenceId,
            $remarks,
            $performedBy
        );

        return $result['inventory'];
    }

    /**
     * Transfer stock between stores.
     */
    public function transfer(
        int $fromStoreId,
        int $toStoreId,
        int $productId,
        float $quantity,
        int $performedBy,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $remarks = null
    ): array {
        return DB::transaction(function () use (
            $fromStoreId, $toStoreId, $productId, $quantity,
            $performedBy, $referenceType, $referenceId, $remarks
        ) {
            $from = $this->stockOut(
                $fromStoreId, $productId, $quantity, 'transfer',
                $performedBy, $referenceType, $referenceId, $remarks
            );

            $unitCost = $from->unit_cost ?? 0;

            $to = $this->stockIn(
                $toStoreId, $productId, $quantity, $unitCost, 'transfer',
                $performedBy, $referenceType, $referenceId, $remarks
            );

            return ['from' => $from, 'to' => $to];
        });
    }

    /**
     * Reserve stock for a pending request.
     */
    public function reserve(
        int $storeId,
        int $productId,
        float $quantity,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $remarks = null,
        ?int $performedBy = null
    ): bool {
        try {
            $this->applyMovement(
                $storeId,
                $productId,
                0.0,
                'reserve',
                $referenceType,
                $referenceId,
                $remarks ?? 'Stock reserved for pending request',
                $performedBy,
                reservedDelta: $quantity
            );
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Release a reservation (cancelled request).
     */
    public function releaseReservation(
        int $storeId,
        int $productId,
        float $quantity,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $remarks = null,
        ?int $performedBy = null
    ): void {
        try {
            $this->applyMovement(
                $storeId,
                $productId,
                0.0,
                'release',
                $referenceType,
                $referenceId,
                $remarks ?? 'Stock reservation released',
                $performedBy,
                reservedDelta: -$quantity
            );
        } catch (\Throwable $e) {
            Log::warning("Failed to release reservation: " . $e->getMessage());
        }
    }

    /**
     * Get current stock balance for a product across all stores (or a specific store).
     */
    public function getBalance(int $productId, ?int $storeId = null): array
    {
        $query = Inventory::with(['store', 'product'])
            ->where('product_id', $productId);

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        return $query->get()->map(fn ($inv) => [
            'store'             => $inv->store->name,
            'quantity_on_hand'  => $inv->quantity_on_hand,
            'quantity_reserved' => $inv->quantity_reserved,
            'quantity_available'=> $inv->quantity_available,
            'unit_cost'         => $inv->unit_cost,
            'total_value'       => $inv->total_value,
        ])->toArray();
    }
}
