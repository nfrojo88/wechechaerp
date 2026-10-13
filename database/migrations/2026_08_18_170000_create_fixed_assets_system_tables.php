<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop any partially created tables from prior failed run
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('fixed_asset_assignments');
        Schema::dropIfExists('fixed_asset_units');
        Schema::dropIfExists('fixed_assets');
        Schema::enableForeignKeyConstraints();

        // 1. Parent Fixed Assets Table (Inventory Definition)
        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);                     // e.g. "Computer", "Sinotruck", "Executive Desk"
            $table->string('category', 100);                 // e.g. "Computer & IT", "Vehicle", "Heavy Machinery", "Furniture", "Tools", "Other"
            $table->string('code_prefix', 20);               // e.g. "COMP", "TRUCK", "DESK"
            $table->unsignedInteger('total_quantity')->default(1); // Strict quantity limit
            $table->decimal('unit_cost', 15, 2)->default(0.00);
            $table->date('purchase_date')->nullable();
            $table->string('supplier', 255)->nullable();
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category', 'name']);
        });

        // 2. Individual Coded Fixed Asset Units
        Schema::create('fixed_asset_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixed_asset_id')->constrained('fixed_assets')->cascadeOnDelete();
            $table->string('unit_code', 50)->unique();       // e.g. "COMP-1", "COMP-2", "TRUCK-1"
            $table->unsignedInteger('sequence_number');      // e.g. 1, 2, 3...
            $table->enum('status', ['in_store', 'assigned', 'maintenance', 'disposed'])->default('in_store')->index();
            $table->string('condition', 50)->default('good'); // new, good, fair, needs_repair, damaged

            // Category-specific details
            $table->string('brand', 100)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('serial_number', 100)->nullable(); // Computers, Electronics, Machinery
            $table->string('plate_number', 50)->nullable();   // Vehicles, Heavy Equipment
            $table->string('chassis_number', 100)->nullable(); // Vehicles
            $table->string('engine_number', 100)->nullable();  // Vehicles, Machinery
            $table->unsignedSmallInteger('year')->nullable();
            $table->text('specifications')->nullable();        // RAM, CPU, Storage, Capacity, etc.
            $table->json('custom_attributes')->nullable();

            // Assignment & Location tracking
            $table->foreignId('assigned_to_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->dateTime('assigned_date')->nullable();
            $table->string('current_location', 255)->nullable();
            $table->decimal('purchase_price', 15, 2)->nullable();
            $table->date('warranty_expiry')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['fixed_asset_id', 'status']);
            $table->index(['assigned_to_employee_id']);
        });

        // 3. Fixed Asset Assignment History & Audit Trail
        Schema::create('fixed_asset_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixed_asset_unit_id')->constrained('fixed_asset_units')->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('action', 50)->default('assigned'); // assigned, returned, transferred, maintenance, disposed
            $table->dateTime('assigned_date');
            $table->dateTime('returned_date')->nullable();
            $table->string('condition_on_assignment', 50)->default('good');
            $table->string('condition_on_return', 50)->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['fixed_asset_unit_id', 'employee_id']);
        });

        // 4. Data Migration: Migrate existing Fixed Asset products matching actual inventory stock on hand
        $existingFixedAssetProducts = DB::table('products')->where('category', 'Fixed Asset')->get();
        $usedPrefixes = [];
        $usedUnitCodes = [];

        foreach ($existingFixedAssetProducts as $prod) {
            // Get actual stock on hand and available quantity from inventory table
            $totalOnHand = (int) round(DB::table('inventory')->where('product_id', $prod->id)->sum('quantity_on_hand'));
            $totalAvailable = (int) round(DB::table('inventory')->where('product_id', $prod->id)->selectRaw('SUM(quantity_on_hand - COALESCE(quantity_reserved, 0)) as avail')->value('avail') ?? 0);
            $qty = $totalAvailable > 0 ? $totalAvailable : ($totalOnHand > 0 ? $totalOnHand : 0);

            // If no stock exists in inventory, only create if product exists with 0 units or skip
            if ($qty <= 0 && $totalOnHand <= 0) {
                continue;
            }

            // Get real effective unit cost from inventory / material_prices / product
            $invCost = (float) DB::table('inventory')
                ->where('product_id', $prod->id)
                ->where('unit_cost', '>', 0)
                ->value('unit_cost');

            $matPrice = (float) DB::table('material_prices')
                ->where('product_id', $prod->id)
                ->orderByDesc('effective_date')
                ->value('price');

            $prodCost = (float) ($prod->current_cost ?? $prod->standard_cost ?? $prod->unit_price ?? $prod->selling_price ?? 0);
            $unitCost = $invCost > 0 ? $invCost : ($matPrice > 0 ? $matPrice : $prodCost);

            // Get primary store
            $storeId = DB::table('inventory')->where('product_id', $prod->id)->where('quantity_on_hand', '>', 0)->value('store_id');
            
            // Derive clean base prefix (letters only, max 4 chars)
            $cleanName = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $prod->name));
            $basePrefix = substr($cleanName, 0, 4) ?: 'AST';

            // Ensure unique prefix across fixed_assets
            $prefix = $basePrefix;
            $counter = 1;
            while (isset($usedPrefixes[$prefix])) {
                $counter++;
                $prefix = substr($basePrefix, 0, 3) . $counter;
            }
            $usedPrefixes[$prefix] = true;

            $prodCategory = $prod->sub_category ?? $prod->category ?? 'Computer & IT';

            $fixedAssetId = DB::table('fixed_assets')->insertGetId([
                'name'           => $prod->name,
                'category'       => $prodCategory,
                'code_prefix'    => $prefix,
                'total_quantity' => $qty,
                'unit_cost'      => $unitCost,
                'store_id'       => $storeId,
                'description'    => 'Migrated from Inventory Product SKU: ' . ($prod->sku ?? $prod->code ?? ''),
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            // Auto-generate unit records matching actual on-hand quantity exactly
            for ($i = 1; $i <= $qty; $i++) {
                $seq = $i;
                $unitCode = "{$prefix}-{$seq}";
                while (isset($usedUnitCodes[$unitCode])) {
                    $seq++;
                    $unitCode = "{$prefix}-{$seq}";
                }
                $usedUnitCodes[$unitCode] = true;

                DB::table('fixed_asset_units')->insert([
                    'fixed_asset_id'  => $fixedAssetId,
                    'unit_code'       => $unitCode,
                    'sequence_number' => $seq,
                    'status'          => 'in_store',
                    'condition'       => $prod->equipment_condition ?: 'good',
                    'current_location'=> $prod->current_location ?: 'Main Store',
                    'purchase_price'  => $unitCost,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fixed_asset_assignments');
        Schema::dropIfExists('fixed_asset_units');
        Schema::dropIfExists('fixed_assets');
    }
};
