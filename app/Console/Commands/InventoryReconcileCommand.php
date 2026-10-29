<?php

namespace App\Console\Commands;

use App\Models\FixedAsset;
use App\Models\FixedAssetUnit;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Store;
use App\Services\InventoryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InventoryReconcileCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inventory:reconcile
                            {--store= : Filter by Store ID}
                            {--product= : Filter by Product ID}
                            {--repair : Interactively repair discrepancies}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify and reconcile Store Inventory against Movement Ledger and Fixed Asset Unit records.';

    /**
     * Execute the console command.
     */
    public function handle(InventoryService $inventoryService): int
    {
        $storeId   = $this->option('store');
        $productId = $this->option('product');
        $isRepair  = (bool) $this->option('repair');

        $this->info("=====================================================================");
        $this->info(" INVENTORY RECONCILIATION & CONSISTENCY AUDIT");
        $this->info(" Mode: " . ($isRepair ? "INTERACTIVE REPAIR (--repair)" : "DRY RUN (Report Only)"));
        $this->info("=====================================================================");

        $query = Inventory::with(['store', 'product']);

        if ($storeId) {
            $query->where('store_id', $storeId);
        }
        if ($productId) {
            $query->where('product_id', $productId);
        }

        $inventories = $query->get();

        if ($inventories->isEmpty()) {
            $this->warn("No inventory records found for the given criteria.");
            return Command::SUCCESS;
        }

        $report = [];
        $totalChecked = 0;
        $consistentCount = 0;
        $ledgerDriftCount = 0;
        $unitMismatchCount = 0;

        foreach ($inventories as $inv) {
            $totalChecked++;
            $product = $inv->product;
            $store = $inv->store;

            $onHand   = (float) $inv->quantity_on_hand;
            $reserved = (float) $inv->quantity_reserved;
            $avail    = max(0.0, $onHand - $reserved);

            // Calculate ledger sum
            $ledgerSum = (float) InventoryMovement::where('inventory_id', $inv->id)->sum('quantity');
            $ledgerDiff = round($onHand - $ledgerSum, 3);

            // Check if product is Fixed Asset
            $cat = strtolower(trim($product?->category ?? ''));
            $sku = strtoupper(trim($product?->sku ?? ''));
            $isFixedAsset = str_contains($cat, 'fixed') || str_contains($cat, 'asset') || str_starts_with($sku, 'FA-');

            $activeUnitsCount = null;
            $unitDiff = null;

            if ($isFixedAsset && $product) {
                $prefix = str_starts_with($sku, 'FA-') ? substr($sku, 3) : null;
                $fixedAsset = FixedAsset::where('store_id', $inv->store_id)
                    ->where(function ($q) use ($product, $prefix) {
                        if ($prefix) {
                            $q->where('code_prefix', $prefix);
                        }
                        $q->orWhere('name', $product->name);
                    })->first();

                if ($fixedAsset) {
                    $activeUnitsCount = FixedAssetUnit::where('fixed_asset_id', $fixedAsset->id)
                        ->whereIn('status', [FixedAssetUnit::STATUS_IN_STORE, FixedAssetUnit::STATUS_ASSIGNED])
                        ->count();
                    $unitDiff = (int) round($onHand - $activeUnitsCount);
                }
            }

            // Determine status
            $hasLedgerDrift = abs($ledgerDiff) >= 0.001;
            $hasUnitMismatch = $isFixedAsset && $unitDiff !== null && $unitDiff !== 0;

            if ($hasLedgerDrift) {
                $ledgerDriftCount++;
            }
            if ($hasUnitMismatch) {
                $unitMismatchCount++;
            }

            if (!$hasLedgerDrift && !$hasUnitMismatch) {
                $consistentCount++;
                $status = 'OK';
            } elseif ($hasLedgerDrift && $hasUnitMismatch) {
                $status = 'DRIFT & MISMATCH';
            } elseif ($hasLedgerDrift) {
                $status = 'LEDGER DRIFT';
            } else {
                $status = 'UNIT MISMATCH';
            }

            $report[] = [
                'inventory'         => $inv,
                'store_name'        => $store?->name ?? "Store #{$inv->store_id}",
                'sku'               => $product?->sku ?? '—',
                'product_name'      => $product?->name ?? "Product #{$inv->product_id}",
                'is_fa'             => $isFixedAsset ? 'Yes' : 'No',
                'on_hand'           => number_format($onHand, 3),
                'reserved'          => number_format($reserved, 3),
                'ledger_sum'        => number_format($ledgerSum, 3),
                'ledger_diff'       => ($ledgerDiff > 0 ? '+' : '') . number_format($ledgerDiff, 3),
                'active_units'      => $activeUnitsCount !== null ? (string) $activeUnitsCount : 'N/A',
                'unit_diff'         => $unitDiff !== null ? (($unitDiff > 0 ? '+' : '') . $unitDiff) : 'N/A',
                'status'            => $status,
                'has_ledger_drift'  => $hasLedgerDrift,
                'has_unit_mismatch' => $hasUnitMismatch,
                'raw_ledger_diff'   => $ledgerDiff,
                'raw_ledger_sum'    => $ledgerSum,
                'raw_on_hand'       => $onHand,
                'raw_active_units'  => $activeUnitsCount,
            ];
        }

        // Render table
        $headers = [
            'Store', 'SKU', 'Product', 'FA?', 'On-Hand', 'Reserved', 'Ledger Sum', 'Ledger Diff', 'FA Units', 'Unit Diff', 'Status'
        ];

        $tableRows = array_map(function ($row) {
            return [
                $row['store_name'],
                $row['sku'],
                mb_strimwidth($row['product_name'], 0, 22, '...'),
                $row['is_fa'],
                $row['on_hand'],
                $row['reserved'],
                $row['ledger_sum'],
                $row['ledger_diff'],
                $row['active_units'],
                $row['unit_diff'],
                $row['status'],
            ];
        }, $report);

        $this->table($headers, $tableRows);

        $this->info("\n--- Audit Summary ---");
        $this->line("Total items audited:     <comment>{$totalChecked}</comment>");
        $this->line("Consistent items:        <info>{$consistentCount}</info>");
        $this->line("Ledger drift items:      " . ($ledgerDriftCount > 0 ? "<error>{$ledgerDriftCount}</error>" : "<info>0</info>"));
        $this->line("Fixed Asset mismatches:  " . ($unitMismatchCount > 0 ? "<error>{$unitMismatchCount}</error>" : "<info>0</info>"));

        if (!$isRepair) {
            if ($ledgerDriftCount > 0 || $unitMismatchCount > 0) {
                $this->warn("\nDiscrepancies found! To interactively resolve them, run:");
                $this->line("  <comment>php artisan inventory:reconcile --repair</comment>");
            } else {
                $this->info("\nAll inventory balances, movement ledger sums, and fixed asset counts are 100% consistent!");
            }
            return Command::SUCCESS;
        }

        // ─── INTERACTIVE REPAIR MODE ─────────────────────────────────────────
        if ($ledgerDriftCount === 0 && $unitMismatchCount === 0) {
            $this->info("\nNo discrepancies found to repair. Database is 100% consistent!");
            return Command::SUCCESS;
        }

        $this->warn("\n[!] INTERACTIVE REPAIR NOTICE:");
        $this->line("Existing ledger rows and units are NEVER deleted.");
        $this->line("Reconciliations create explicit audit movement entries (type: reconciliation).");

        if (!$this->confirm('Are you sure you want to proceed with interactive repair?', false)) {
            $this->info("Repair cancelled by user.");
            return Command::SUCCESS;
        }

        $repairedCount = 0;

        foreach ($report as $item) {
            if (!$item['has_ledger_drift'] && !$item['has_unit_mismatch']) {
                continue;
            }

            $inv         = $item['inventory'];
            $storeName   = $item['store_name'];
            $productName = $item['product_name'];
            $onHand      = $item['raw_on_hand'];
            $ledgerSum   = $item['raw_ledger_sum'];
            $ledgerDiff  = $item['raw_ledger_diff'];

            $this->info("\n-------------------------------------------------------------");
            $this->comment("Item: [{$storeName}] {$productName} (SKU: {$item['sku']})");

            // 1. Resolve Ledger Drift
            if ($item['has_ledger_drift']) {
                $this->warn("  * Ledger Drift: Inventory On-Hand is {$onHand}, but Movement Ledger sum is {$ledgerSum} (Diff: {$item['ledger_diff']})");

                $choice = $this->choice(
                    "Which quantity is correct for this inventory balance?",
                    [
                        "1" => "Keep Inventory On-Hand ({$onHand}) — add balancing ledger entry (+{$ledgerDiff})",
                        "2" => "Adjust Inventory to Ledger Sum ({$ledgerSum}) — adjust on-hand by (-{$ledgerDiff})",
                        "3" => "Enter a custom physical count",
                        "4" => "Skip this item",
                    ],
                    "1"
                );

                if ($choice === "1") {
                    // Create balancing ledger movement to match physical on-hand
                    DB::transaction(function () use ($inv, $ledgerDiff, $ledgerSum, $onHand) {
                        InventoryMovement::create([
                            'inventory_id'   => $inv->id,
                            'type'           => 'reconciliation',
                            'quantity'       => $ledgerDiff,
                            'balance_before' => $ledgerSum,
                            'balance_after'  => $onHand,
                            'performed_by'   => auth()->id() ?? 1,
                            'remarks'        => "Audit reconciliation: balancing ledger row to match verified stock balance ({$onHand})",
                        ]);
                    });
                    $this->info("  -> Balanced ledger sum to {$onHand} via reconciliation movement.");
                    $repairedCount++;
                } elseif ($choice === "2") {
                    // Adjust inventory to match ledger sum
                    try {
                        $inventoryService->applyMovement(
                            storeId: $inv->store_id,
                            productId: $inv->product_id,
                            quantityDelta: -$ledgerDiff,
                            type: 'reconciliation',
                            remarks: "Audit reconciliation: adjusted inventory on-hand to match ledger sum ({$ledgerSum})",
                            performedBy: auth()->id() ?? 1
                        );
                        $this->info("  -> Inventory adjusted to match ledger sum ({$ledgerSum}).");
                        $repairedCount++;
                        $onHand = $ledgerSum;
                    } catch (\Throwable $e) {
                        $this->error("  -> Failed to adjust inventory: " . $e->getMessage());
                    }
                } elseif ($choice === "3") {
                    $customQty = (float) $this->ask("Enter verified physical stock count:");
                    $delta = round($customQty - $onHand, 3);
                    try {
                        $inventoryService->applyMovement(
                            storeId: $inv->store_id,
                            productId: $inv->product_id,
                            quantityDelta: $delta,
                            type: 'reconciliation',
                            remarks: "Audit reconciliation: set verified physical stock count to {$customQty}",
                            performedBy: auth()->id() ?? 1
                        );
                        $this->info("  -> Inventory set to verified count {$customQty}.");
                        $repairedCount++;
                        $onHand = $customQty;
                    } catch (\Throwable $e) {
                        $this->error("  -> Failed to adjust inventory: " . $e->getMessage());
                    }
                } else {
                    $this->line("  -> Skipped ledger drift reconciliation.");
                }
            }

            // 2. Resolve Fixed Asset Unit Mismatch
            if ($item['has_unit_mismatch']) {
                $activeUnits = $item['raw_active_units'];
                $product = $inv->product;
                $prefix = str_starts_with($product?->sku ?? '', 'FA-') ? substr($product->sku, 3) : null;
                $fixedAsset = FixedAsset::where('store_id', $inv->store_id)
                    ->where(function ($q) use ($product, $prefix) {
                        if ($prefix) {
                            $q->where('code_prefix', $prefix);
                        }
                        $q->orWhere('name', $product->name);
                    })->first();

                if ($fixedAsset) {
                    $this->warn("  * Fixed Asset Unit Mismatch: Inventory has {$onHand}, but Active Units count is {$activeUnits}");

                    $faChoice = $this->choice(
                        "Which count is correct for Fixed Asset '{$fixedAsset->name}'?",
                        [
                            "1" => "Keep Inventory balance ({$onHand}) — synchronize unit records (generate/dispose)",
                            "2" => "Keep Active Unit count ({$activeUnits}) — adjust inventory balance to match units",
                            "3" => "Skip unit synchronization",
                        ],
                        "1"
                    );

                    if ($faChoice === "1") {
                        // Inventory is correct: synchronize units to match inventory
                        $diff = (int) round($onHand - $activeUnits);
                        if ($diff > 0) {
                            // Positive: generate missing units
                            $maxSeq = (int) $fixedAsset->units()->max('sequence_number') ?: 0;
                            $assetPrefix = strtoupper(trim($fixedAsset->code_prefix ?: 'AST'));
                            for ($i = 0; $i < $diff; $i++) {
                                $maxSeq++;
                                $code = "{$assetPrefix}-{$maxSeq}";
                                while (FixedAssetUnit::where('unit_code', $code)->exists()) {
                                    $maxSeq++;
                                    $code = "{$assetPrefix}-{$maxSeq}";
                                }
                                FixedAssetUnit::create([
                                    'fixed_asset_id'   => $fixedAsset->id,
                                    'unit_code'        => $code,
                                    'sequence_number'  => $maxSeq,
                                    'status'           => FixedAssetUnit::STATUS_IN_STORE,
                                    'condition'        => 'good',
                                    'notes'            => 'Details incomplete — automatically generated during inventory reconciliation.',
                                    'created_by'       => auth()->id() ?? 1,
                                ]);
                            }
                            $this->info("  -> Generated {$diff} missing unit(s) flagged 'Details incomplete'.");
                        } elseif ($diff < 0) {
                            // Negative: dispose unassigned units (never delete)
                            $toDisposeCount = abs($diff);
                            $unitsToDispose = $fixedAsset->units()
                                ->where('status', FixedAssetUnit::STATUS_IN_STORE)
                                ->take($toDisposeCount)
                                ->get();

                            foreach ($unitsToDispose as $u) {
                                $u->update([
                                    'status' => FixedAssetUnit::STATUS_DISPOSED,
                                    'notes'  => $u->notes ? $u->notes . "\n[Reconciliation] Marked as disposed" : "[Reconciliation] Marked as disposed",
                                ]);
                            }
                            $this->info("  -> Marked {$unitsToDispose->count()} unassigned unit(s) as disposed.");
                        }

                        $fixedAsset->total_quantity = max(0, (int) round($onHand));
                        $fixedAsset->saveQuietly();
                        $repairedCount++;

                    } elseif ($faChoice === "2") {
                        // Units count is correct: adjust inventory to match units
                        $delta = (float) ($activeUnits - $onHand);
                        try {
                            $inventoryService->applyMovement(
                                storeId: $inv->store_id,
                                productId: $inv->product_id,
                                quantityDelta: $delta,
                                type: 'reconciliation',
                                remarks: "Audit reconciliation: matched inventory balance to active unit records count ({$activeUnits})",
                                performedBy: auth()->id() ?? 1
                            );
                            $fixedAsset->total_quantity = $activeUnits;
                            $fixedAsset->saveQuietly();
                            $this->info("  -> Inventory balance adjusted to match {$activeUnits} active units.");
                            $repairedCount++;
                        } catch (\Throwable $e) {
                            $this->error("  -> Failed to adjust inventory: " . $e->getMessage());
                        }
                    } else {
                        $this->line("  -> Skipped unit synchronization.");
                    }
                }
            }
        }

        $this->info("\n=====================================================================");
        $this->info(" RECONCILIATION COMPLETE: {$repairedCount} discrepancy issue(s) repaired.");
        $this->info("=====================================================================");

        return Command::SUCCESS;
    }
}
