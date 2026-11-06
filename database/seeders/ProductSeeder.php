<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use App\Models\Product;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds from products.sql dynamically avoiding duplicates.
     */
    public function run(): void
    {
        $sqlPath = base_path('products.sql');
        if (!File::exists($sqlPath)) {
            if ($this->command) {
                $this->command->error("products.sql file not found at $sqlPath");
            }
            return;
        }

        $sql = File::get($sqlPath);

        // Extract the INSERT INTO statement from products.sql
        $start = strpos($sql, 'INSERT INTO `products`');
        if ($start === false) {
            if ($this->command) {
                $this->command->info('No INSERT statement found in products.sql.');
            }
            return;
        }

        $insertQuery = substr($sql, $start);
        $end = strpos($insertQuery, 'ALTER TABLE');
        if ($end !== false) {
            $insertQuery = substr($insertQuery, 0, $end);
        }

        // Direct INSERT to a temp table
        $insertQuery = str_replace('INSERT INTO `products`', 'INSERT INTO `products_temp`', $insertQuery);

        try {
            Schema::dropIfExists('products_temp');

            // Create temporary table matching old products dump structure
            Schema::create('products_temp', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->string('sku')->nullable();
                $table->string('unit')->nullable();
                $table->decimal('standard_length', 10, 2)->nullable();
                $table->string('category')->nullable();
                $table->decimal('max_stock', 10, 2)->nullable();
                $table->integer('reorder_level')->nullable();
                $table->integer('carton_size')->nullable();
                $table->decimal('unit_price', 10, 2)->nullable();
                $table->decimal('selling_price', 10, 2)->nullable();
                $table->dateTime('deleted_at')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->decimal('standard_width', 10, 3)->nullable();
                $table->string('sub_category')->nullable();
                $table->string('equipment_condition')->nullable();
                $table->string('assigned_to')->nullable();
                $table->string('current_location')->nullable();
                $table->string('asset_status')->nullable();
                $table->date('baseline_date')->nullable();
                $table->decimal('purchase_threshold', 5, 2)->nullable();
            });

            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            DB::unprepared($insertQuery);
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');

            $dumpProducts = DB::table('products_temp')->get();
            $inserted = 0;
            $updated = 0;

            foreach ($dumpProducts as $item) {
                // If SKU is empty, skip or generate SKU from ID
                $sku = $item->sku ?: ('MAT-' . str_pad($item->id, 4, '0', STR_PAD_LEFT));

                // 1. NEVER recreate materials that have been purged
                if (Schema::hasTable('purged_materials')) {
                    $isPurged = DB::table('purged_materials')
                        ->where('sku', $sku)
                        ->orWhere(DB::raw('LOWER(TRIM(sku))'), strtolower(trim($sku)))
                        ->orWhere('sku', 'like', $sku . '%')
                        ->exists();
                    if ($isPurged) {
                        continue;
                    }
                }

                $data = [
                    'name'                => $item->name ?: 'Unnamed Product',
                    'unit'                => $item->unit ?: 'PCS',
                    'standard_length'     => $item->standard_length ?? 0.00,
                    'standard_width'      => $item->standard_width ?? 0.000,
                    'category'            => $item->category ?: 'Consumable',
                    'sub_category'        => $item->sub_category,
                    'max_stock'           => $item->max_stock ?? 100.00,
                    'reorder_level'       => $item->reorder_level ?? 20,
                    'carton_size'         => $item->carton_size,
                    'unit_price'          => $item->unit_price ?? 0.00,
                    'selling_price'       => $item->selling_price ?? 0.00,
                    'equipment_condition' => $item->equipment_condition ?: 'Good',
                    'assigned_to'         => $item->assigned_to ?: 'Unassigned',
                    'current_location'    => $item->current_location ?: 'Main Store',
                    'asset_status'        => $item->asset_status ?: 'Available',
                    'baseline_date'       => $item->baseline_date,
                    'purchase_threshold'  => $item->purchase_threshold ?? 5.00,
                ];

                // Check if product exists by SKU (including trashed)
                $existing = Product::withTrashed()->where('sku', $sku)->first();

                if ($existing) {
                    if ($existing->trashed()) {
                        // Product was deleted by user: DO NOT restore it
                        continue;
                    }
                    // Update non-duplicate product record with missing info
                    $existing->update($data);
                    $updated++;
                } else {
                    // Create new product record
                    $data['sku'] = $sku;
                    Product::create($data);
                    $inserted++;
                }
            }

            Schema::dropIfExists('products_temp');

            if ($this->command) {
                $this->command->info("Products imported from products.sql: $inserted created, $updated updated.");
            }
        } catch (\Throwable $e) {
            Schema::dropIfExists('products_temp');
            throw $e;
        }
    }
}
