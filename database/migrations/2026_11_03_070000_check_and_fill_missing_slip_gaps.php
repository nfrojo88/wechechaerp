<?php

use Illuminate\Database\Migrations\Migration;
use App\Services\SlipSequenceService;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('slip_sequences') && Schema::hasTable('delivery_receipts')) {
            try {
                $service = app(SlipSequenceService::class);
                $service->checkAndFillMissingSlips();
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
        // No reversal needed for auto-filled gap leaves
    }
};
