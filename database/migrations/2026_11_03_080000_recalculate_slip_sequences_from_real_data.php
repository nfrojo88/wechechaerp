<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\SlipSequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('delivery_receipts')) {
            try {
                // Remove any synthetic auto-filled gap leaves that were created during gap synchronization
                DB::table('delivery_receipts')
                    ->where('source_type', 'void')
                    ->where(function ($q) {
                        $q->where('void_reason', 'like', 'Auto-filled missing slip gap%')
                          ->orWhere('notes', 'like', 'Auto-filled missing gap%');
                    })
                    ->delete();
            } catch (\Throwable $e) {}
        }

        if (Schema::hasTable('slip_sequences')) {
            try {
                $sequences = SlipSequence::all();
                foreach ($sequences as $sequence) {
                    $sequence->recalculate(save: true);
                }
            } catch (\Throwable $e) {
                // Non-blocking in case tables are unpopulated
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reversal needed for recalculation
    }
};
