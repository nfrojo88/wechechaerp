<?php

use App\Models\Transfer;
use App\Http\Controllers\StoreManagerController;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Ensures all existing active transfers deduct from origin store inventory and add to destination store inventory.
     */
    public function up(): void
    {
        if (!Schema::hasTable('transfers') || !Schema::hasTable('inventory')) {
            return;
        }

        try {
            $transfers = Transfer::with(['items.product', 'fromStore', 'toStore'])
                ->whereNotIn('status', ['rejected', 'cancelled'])
                ->whereNotNull('from_store_id')
                ->whereNotNull('to_store_id')
                ->whereColumn('from_store_id', '!=', 'to_store_id')
                ->get();

            foreach ($transfers as $transfer) {
                try {
                    StoreManagerController::syncTransferInventory($transfer);
                } catch (\Throwable $e) {}
            }
        } catch (\Throwable $e) {}
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op to preserve inventory consistency
    }
};
