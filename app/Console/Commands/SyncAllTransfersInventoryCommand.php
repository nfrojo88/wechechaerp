<?php

namespace App\Console\Commands;

use App\Http\Controllers\StoreManagerController;
use App\Models\Transfer;
use Illuminate\Console\Command;

class SyncAllTransfersInventoryCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inventory:sync-transfers {--transfer-id= : Sync a specific transfer ID only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reconcile and ensure all transfers deduct from origin store inventory and add to destination store inventory';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $specificId = $this->option('transfer-id');

        $query = Transfer::with(['items.product', 'fromStore', 'toStore'])
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->whereNotNull('from_store_id')
            ->whereNotNull('to_store_id')
            ->whereColumn('from_store_id', '!=', 'to_store_id');

        if ($specificId) {
            $query->where('id', $specificId);
        }

        $transfers = $query->orderBy('id')->get();

        if ($transfers->isEmpty()) {
            $this->info('No eligible transfers found to reconcile.');
            return Command::SUCCESS;
        }

        $this->info("Checking and reconciling {$transfers->count()} transfer(s)...");

        $totalDeducted = 0;
        $totalAdded = 0;

        foreach ($transfers as $transfer) {
            $res = StoreManagerController::syncTransferInventory($transfer);
            $totalDeducted += $res['deducted'];
            $totalAdded += $res['added'];

            $fromName = $transfer->fromStore->name ?? "Store #{$transfer->from_store_id}";
            $toName   = $transfer->toStore->name ?? "Store #{$transfer->to_store_id}";

            $this->line("Transfer <comment>#{$transfer->transfer_no}</comment> [{$fromName} -> {$toName}]: Deducted {$res['deducted']} item(s), Added {$res['added']} item(s).");
        }

        $this->newLine();
        $this->info("Reconciliation complete! Total items deducted from origin stores: {$totalDeducted}, Total items added to destination stores: {$totalAdded}.");

        return Command::SUCCESS;
    }
}
