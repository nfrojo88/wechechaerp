<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Ensures any negative quantity on hand or reserved in store inventory is clamped to zero.
     */
    public function up(): void
    {
        if (Schema::hasTable('inventory')) {
            try {
                DB::table('inventory')
                    ->where('quantity_on_hand', '<', 0)
                    ->update([
                        'quantity_on_hand' => 0,
                        'last_movement_at' => now(),
                    ]);

                DB::table('inventory')
                    ->where('quantity_reserved', '<', 0)
                    ->update([
                        'quantity_reserved' => 0,
                    ]);
            } catch (\Throwable $e) {}
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op: do not revert clamped non-negative inventory balances
    }
};
