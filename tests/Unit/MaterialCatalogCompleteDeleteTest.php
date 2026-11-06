<?php

namespace Tests\Unit;

use App\Http\Controllers\ProductController;
use App\Models\FixedAsset;
use App\Models\FixedAssetUnit;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\ProductSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MaterialCatalogCompleteDeleteTest extends TestCase
{
    protected ProductController $controller;
    protected User $adminUser;
    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        DB::statement('PRAGMA foreign_keys = OFF;');

        Schema::dropIfExists('purged_materials');
        Schema::dropIfExists('fixed_asset_assignments');
        Schema::dropIfExists('fixed_asset_units');
        Schema::dropIfExists('fixed_assets');
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory');
        Schema::dropIfExists('stores');
        Schema::dropIfExists('products');
        Schema::dropIfExists('users');

        Schema::create('purged_materials', function ($table) {
            $table->id();
            $table->string('sku')->index();
            $table->string('name')->nullable();
            $table->unsignedBigInteger('purged_by')->nullable();
            $table->timestamp('purged_at')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->boolean('is_admin')->default(false);
            $table->timestamps();
        });

        Schema::create('stores', function ($table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('sku')->unique();
            $table->string('unit')->default('pcs');
            $table->string('category')->nullable();
            $table->string('sub_category')->nullable();
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('selling_price', 15, 2)->default(0);
            $table->decimal('max_stock', 15, 2)->default(100);
            $table->integer('reorder_level')->default(20);
            $table->string('asset_status')->default('Available');
            $table->string('equipment_condition')->nullable();
            $table->string('assigned_to')->nullable();
            $table->string('current_location')->nullable();
            $table->date('baseline_date')->nullable();
            $table->decimal('purchase_threshold', 5, 2)->default(5.00);
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
            $table->unsignedBigInteger('performed_by')->default(1);
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('fixed_assets', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('category')->default('Equipment');
            $table->string('code_prefix')->nullable();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->integer('total_quantity')->default(1);
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('fixed_asset_units', function ($table) {
            $table->id();
            $table->unsignedBigInteger('fixed_asset_id');
            $table->string('unit_code')->nullable();
            $table->string('status')->default('in_store');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->integer('sequence_number')->default(1);
            $table->timestamps();
        });

        Schema::create('fixed_asset_assignments', function ($table) {
            $table->id();
            $table->unsignedBigInteger('fixed_asset_unit_id');
            $table->string('action')->default('assigned');
            $table->timestamps();
        });

        $this->adminUser = User::create([
            'name'     => 'Store Administrator',
            'email'    => 'admin@test.com',
            'is_admin' => true,
        ]);

        $this->store = Store::create([
            'name'      => 'Main Store',
            'is_active' => true,
        ]);

        Auth::login($this->adminUser);

        $this->controller = app(ProductController::class);
    }

    /**
     * Test that deleting a material in Material Catalog completely and permanently purges it
     * from products, inventory, and fixed assets, and records it in purged_materials.
     */
    public function test_deleting_material_in_catalog_completely_purges_from_entire_system()
    {
        // 1. Create a product (Material)
        $product = Product::create([
            'name'       => 'Vibrator Machine 2HP',
            'sku'        => 'FA-VIB2HP',
            'category'   => 'Fixed Asset',
            'unit'       => 'Pcs',
            'unit_price' => 15000.00,
        ]);

        // 2. Create store inventory record
        $inv = Inventory::create([
            'store_id'         => $this->store->id,
            'product_id'       => $product->id,
            'quantity_on_hand' => 2.000,
            'unit_cost'        => 15000.00,
        ]);

        // 3. Create FixedAsset and units linked to it
        $fa = FixedAsset::create([
            'name'           => 'Vibrator Machine 2HP',
            'code_prefix'    => 'VIB2HP',
            'store_id'       => $this->store->id,
            'total_quantity' => 2,
            'unit_cost'      => 15000.00,
            'description'    => "Product SKU: FA-VIB2HP, Product ID: {$product->id}",
        ]);

        $unit = FixedAssetUnit::create([
            'fixed_asset_id'  => $fa->id,
            'unit_code'       => 'VIB2HP-1',
            'status'          => 'in_store',
            'product_id'      => $product->id,
            'sequence_number' => 1,
        ]);

        // Verify all exist before delete
        $this->assertDatabaseHas('products', ['id' => $product->id]);
        $this->assertDatabaseHas('inventory', ['product_id' => $product->id]);
        $this->assertDatabaseHas('fixed_assets', ['id' => $fa->id]);
        $this->assertDatabaseHas('fixed_asset_units', ['id' => $unit->id]);

        // 4. Delete product via ProductController@destroy (Material Catalog Delete button)
        $response = $this->controller->destroy($product);

        $this->assertTrue($response->isRedirect());

        // 5. Assert product is COMPLETELY deleted (not even in withTrashed!)
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $this->assertDatabaseMissing('products', ['sku' => 'FA-VIB2HP']);
        $this->assertNull(Product::withTrashed()->find($product->id));

        // 6. Assert store inventory is completely wiped
        $this->assertDatabaseMissing('inventory', ['product_id' => $product->id]);

        // 7. Assert fixed asset and units are completely wiped
        $this->assertDatabaseMissing('fixed_assets', ['id' => $fa->id]);
        $this->assertDatabaseMissing('fixed_asset_units', ['id' => $unit->id]);

        // 8. Assert recorded in purged_materials table
        $this->assertDatabaseHas('purged_materials', ['sku' => 'FA-VIB2HP']);
    }

    /**
     * Test that FixedAsset sync methods will NEVER resurrect or recreate a purged material.
     */
    public function test_fixed_asset_sync_never_resurrects_purged_material()
    {
        // Insert a purged material SKU
        DB::table('purged_materials')->insert([
            'sku'        => 'FA-GENTEST',
            'name'       => 'Generator 5KW Test',
            'purged_by'  => $this->adminUser->id,
            'purged_at'  => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Attempt to create a FixedAsset with that prefix/name
        $fa = new FixedAsset([
            'name'           => 'Generator 5KW Test',
            'code_prefix'    => 'GENTEST',
            'store_id'       => $this->store->id,
            'total_quantity' => 1,
            'unit_cost'      => 50000.00,
        ]);

        // Trigger syncWithCatalogAndInventory
        $syncedProduct = $fa->syncWithCatalogAndInventory();

        // Must return null and MUST NOT create the product!
        $this->assertNull($syncedProduct);
        $this->assertDatabaseMissing('products', ['sku' => 'FA-GENTEST']);
    }
}
