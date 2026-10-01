<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Delete or archive material/product with SKU 'fa-black'.
     */
    public function up(): void
    {
        if (Schema::hasTable('products')) {
            $matchingProducts = DB::table('products')
                ->where(function ($q) {
                    $q->whereRaw('LOWER(sku) = ?', ['fa-black'])
                      ->orWhere('sku', 'fa-black')
                      ->orWhere('sku', 'FA-BLACK')
                      ->orWhere('sku', 'like', '%fa-black%');
                })
                ->get();

            foreach ($matchingProducts as $prod) {
                // Clean up inventory records if table exists
                if (Schema::hasTable('inventories')) {
                    DB::table('inventories')->where('product_id', $prod->id)->delete();
                }

                // Attempt permanent delete; fallback to soft-delete if foreign key constraint exists
                try {
                    DB::table('products')->where('id', $prod->id)->delete();
                } catch (\Throwable $e) {
                    if (Schema::hasColumn('products', 'deleted_at')) {
                        DB::table('products')->where('id', $prod->id)->update(['deleted_at' => now()]);
                    }
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Deletion cannot be reversed without original data
    }
};
