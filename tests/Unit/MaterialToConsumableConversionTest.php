<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Product;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\FixedAsset;
use App\Models\FixedAssetUnit;
use App\Models\FixedAssetAssignment;
use App\Services\MaterialToConsumableService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MaterialToConsumableConversionTest extends TestCase
{
    protected MaterialToConsumableService $service;

    protected function setUp(): void
    {
        parent::setUp();

        DB::statement('PRAGMA foreign_keys = OFF;');

        Schema::dropIfExists('fixed_asset_assignments');
        Schema::dropIfExists('fixed_asset_units');
        Schema::dropIfExists('fixed_assets');
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory');
        Schema::dropIfExists('products');
        Schema::dropIfExists('stores');

        Schema::create('stores', function ($table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('products', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('sku')->unique();
            $table->string('unit')->default('pcs');
            $table->string('category')->default('Fixed Asset');
            $table->string('sub_category')->nullable();
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('selling_price', 15, 2)->default(0);
            $table->decimal('max_stock', 15, 2)->default(100);
            $table->integer('reorder_level')->default(20);
            $table->string('equipment_condition')->default('Good');
            $table->string('assigned_to')->default('Unassigned');
            $table->string('asset_status')->default('Available');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('inventory', function ($table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->default(1);
            $table->unsignedBigInteger('product_id');
            $table->decimal('quantity_on_hand', 15, 3)->default(0);
            $table->decimal('quantity_reserved', 15, 3)->default(0);
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->decimal('min_stock', 15, 3)->default(0);
            $table->timestamp('last_movement_at')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_movements', function ($table) {
            $table->id();
            $table->unsignedBigInteger('inventory_id');
            $table->string('type', 20);
            $table->decimal('quantity', 15, 3);
            $table->decimal('balance_before', 15, 3)->default(0);
            $table->decimal('balance_after', 15, 3)->default(0);
            $table->timestamps();
        });

        Schema::create('fixed_assets', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('category')->default('Equipment');
            $table->string('code_prefix')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('store_id')->default(1);
            $table->integer('total_quantity')->default(0);
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->text('description')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('fixed_asset_units', function ($table) {
            $table->id();
            $table->unsignedBigInteger('fixed_asset_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('unit_code')->nullable();
            $table->string('status')->default('in_store');
            $table->timestamps();
        });

        Schema::create('fixed_asset_assignments', function ($table) {
            $table->id();
            $table->unsignedBigInteger('fixed_asset_unit_id');
            $table->string('action')->default('assigned');
            $table->timestamps();
        });

        DB::table('stores')->insert(['id' => 1, 'name' => 'Main Site Store']);

        $this->service = app(MaterialToConsumableService::class);
    }

    public function test_converts_fixed_asset_material_to_consumable_and_preserves_inventory()
    {
        // 1. Create product currently classified as Fixed Asset
        $product = Product::create([
            'name'       => 'HDPE Pipe 90mm PN16',
            'sku'        => 'FA-HDP4',
            'category'   => 'Fixed Asset',
            'unit'       => 'm',
            'unit_price' => 450.00,
        ]);

        // 2. Set up store inventory with stock
        $invId = DB::table('inventory')->insertGetId([
            'store_id'          => 1,
            'product_id'        => $product->id,
            'quantity_on_hand'  => 120.000,
            'quantity_reserved' => 10.000,
            'unit_cost'         => 450.00,
        ]);

        // 3. Set up linked FixedAsset and units
        $faId = DB::table('fixed_assets')->insertGetId([
            'name'           => 'HDPE Pipe 90mm PN16',
            'code_prefix'    => 'HDP4',
            'product_id'     => $product->id,
            'total_quantity' => 120,
        ]);

        $unitId = DB::table('fixed_asset_units')->insertGetId([
            'fixed_asset_id' => $faId,
            'product_id'     => $product->id,
            'unit_code'      => 'HDP4-1',
        ]);

        DB::table('fixed_asset_assignments')->insert([
            'fixed_asset_unit_id' => $unitId,
            'action'              => 'assigned',
        ]);

        // 4. Run conversion service
        $result = $this->service->convertSkusToConsumable(['FA-HDP4']);

        $this->assertEquals(1, $result['converted_count']);
        $this->assertEquals(1, $result['fixed_assets_removed']);
        $this->assertEquals(1, $result['units_removed']);

        // 5. Verify Product is now Consumable
        $product->refresh();
        $this->assertEquals('Consumable', $product->category);
        $this->assertEquals('Available', $product->asset_status);
        $this->assertEquals('Unassigned', $product->assigned_to);

        // 6. Verify Store Inventory is preserved and reserved is cleared
        $inv = DB::table('inventory')->where('id', $invId)->first();
        $this->assertEquals(120.000, (float)$inv->quantity_on_hand, 'Inventory quantity on hand must be preserved!');
        $this->assertEquals(0.000, (float)$inv->quantity_reserved, 'Reserved quantity must be reset to 0 for consumable stock!');

        // 7. Verify Fixed Asset records and units are removed
        $this->assertEquals(0, DB::table('fixed_assets')->where('id', $faId)->count());
        $this->assertEquals(0, DB::table('fixed_asset_units')->where('id', $unitId)->count());
        $this->assertEquals(0, DB::table('fixed_asset_assignments')->where('fixed_asset_unit_id', $unitId)->count());
    }

    public function test_handles_space_variants_in_skus()
    {
        // Test "FA-B RUS" variant matching
        $product = Product::create([
            'name'     => 'Carbon Brush 150mm',
            'sku'      => 'FA-B RUS',
            'category' => 'Fixed Asset',
        ]);

        $result = $this->service->convertSkusToConsumable(['FA-B RUS']);
        $this->assertEquals(1, $result['converted_count']);

        $product->refresh();
        $this->assertEquals('Consumable', $product->category);
    }

    public function test_bulk_conversion_of_error_doc_items()
    {
        $skus = ['FA-ELBO', 'FA-COVE', 'FA-CUP2', 'FA-DATA', 'MAT-0577', 'FA-WCWI'];

        foreach ($skus as $idx => $sku) {
            Product::create([
                'name'     => 'Material ' . $sku,
                'sku'      => $sku,
                'category' => 'Fixed Asset',
            ]);
            DB::table('inventory')->insert([
                'store_id'          => 1,
                'product_id'        => $idx + 1,
                'quantity_on_hand'  => 25.000,
                'quantity_reserved' => 5.000,
            ]);
        }

        $result = $this->service->convertSkusToConsumable($skus);

        $this->assertEquals(count($skus), $result['converted_count']);

        foreach ($skus as $sku) {
            $p = Product::where('sku', $sku)->first();
            $this->assertNotNull($p);
            $this->assertEquals('Consumable', $p->category);
        }

        // All inventory on hand must still exist
        $totalStock = DB::table('inventory')->sum('quantity_on_hand');
        $this->assertEquals(25.000 * count($skus), (float)$totalStock);

        // Reserved must be reset to 0
        $totalReserved = DB::table('inventory')->sum('quantity_reserved');
        $this->assertEquals(0.000, (float)$totalReserved);
    }
}
