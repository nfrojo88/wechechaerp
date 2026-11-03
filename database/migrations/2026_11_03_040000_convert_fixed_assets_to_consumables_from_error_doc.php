<?php

use Illuminate\Database\Migrations\Migration;
use App\Services\MaterialToConsumableService;
use App\Console\Commands\ConvertMaterialsToConsumableCommand;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Convert requested material items in error.doc from Fixed Asset to Consumable,
     * removing serialized unit codes and fixed_asset registers while keeping store inventory on hand.
     */
    public function up(): void
    {
        try {
            $service = app(MaterialToConsumableService::class);
            $service->convertSkusToConsumable(ConvertMaterialsToConsumableCommand::DEFAULT_SKUS);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Migration convert_fixed_assets_to_consumables_from_error_doc failed: " . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverting consumable classification requires manual category re-assignment
    }
};
