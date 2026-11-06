<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates purged_materials table to permanently prevent deleted materials from resurrecting.
     */
    public function up(): void
    {
        if (!Schema::hasTable('purged_materials')) {
            Schema::create('purged_materials', function (Blueprint $table) {
                $table->id();
                $table->string('sku')->index();
                $table->string('name')->nullable();
                $table->unsignedBigInteger('purged_by')->nullable();
                $table->timestamp('purged_at')->nullable();
                $table->timestamps();
            });
        }

        // Clean up any previously soft-deleted or legacy _DELETED_ products
        if (Schema::hasTable('products')) {
            try {
                $trashedProducts = DB::table('products')
                    ->whereNotNull('deleted_at')
                    ->orWhere('sku', 'like', '%_DELETED_%')
                    ->get();

                $purgeService = app(\App\Services\ProductPurgeService::class);
                foreach ($trashedProducts as $tp) {
                    if (!empty($tp->sku)) {
                        $purgeService->purgeBySku($tp->sku);
                    }
                }
            } catch (\Throwable $e) {
                // Ignore during migration replay
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purged_materials');
    }
};
