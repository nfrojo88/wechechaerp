<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Store;
use App\Models\Product;
use App\Models\Inventory;
use App\Http\Controllers\InventoryController;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;

class InventoryAlphabeticalAndSkuTest extends TestCase
{
    protected User $user;
    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            DB::statement('PRAGMA foreign_keys = OFF;');
        } catch (\Throwable $e) {}

        Schema::dropIfExists('inventory');
        Schema::dropIfExists('material_prices');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('products');
        Schema::dropIfExists('stores');
        Schema::dropIfExists('users');

        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('stores', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('Site Store');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('products', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->string('code')->nullable();
            $table->string('category')->nullable();
            $table->string('unit')->default('pcs');
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('material_prices', function ($table) {
            $table->id();
            $table->foreignId('product_id')->nullable();
            $table->date('effective_date')->nullable();
            $table->decimal('price', 15, 2)->default(0);
        });

        Schema::create('purchase_order_items', function ($table) {
            $table->id();
            $table->foreignId('product_id')->nullable();
            $table->decimal('unit_price', 15, 2)->default(0);
        });

        Schema::create('inventory', function ($table) {
            $table->id();
            $table->foreignId('store_id');
            $table->foreignId('product_id');
            $table->decimal('quantity_on_hand', 15, 3)->default(0);
            $table->decimal('quantity_reserved', 15, 3)->default(0);
            $table->decimal('quantity_available', 15, 3)->default(0);
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->decimal('min_stock', 15, 3)->default(0);
            $table->timestamp('last_movement_at')->nullable();
            $table->timestamps();
        });

        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->user = User::create([
            'name'     => 'Store Manager',
            'email'    => 'sm_' . uniqid() . '@wechecha.com',
            'password' => bcrypt('password'),
        ]);
        $this->user->assignRole($role);
        Auth::login($this->user);

        $this->store = Store::create([
            'name'      => 'Main Store',
            'is_active' => true,
        ]);
    }

    public function test_inventory_is_sorted_alphabetically_by_product_name_by_default()
    {
        // Create products deliberately out of alphabetical order
        $pZ = Product::create(['name' => 'Z Metal', 'sku' => 'MAT-0008', 'category' => 'Consumable']);
        $pA = Product::create(['name' => 'Angle Iron', 'sku' => 'MAT-0789', 'category' => 'Consumable']);
        $pB = Product::create(['name' => 'Bonda', 'sku' => 'MAT-0276', 'category' => 'Consumable']);
        $pN = Product::create(['name' => 'Nail #6', 'sku' => 'MAT-0001', 'category' => 'Consumable']);

        Inventory::create(['store_id' => $this->store->id, 'product_id' => $pZ->id, 'quantity_on_hand' => 10]);
        Inventory::create(['store_id' => $this->store->id, 'product_id' => $pA->id, 'quantity_on_hand' => 20]);
        Inventory::create(['store_id' => $this->store->id, 'product_id' => $pB->id, 'quantity_on_hand' => 15]);
        Inventory::create(['store_id' => $this->store->id, 'product_id' => $pN->id, 'quantity_on_hand' => 30]);

        $controller = app(InventoryController::class);
        $request = new Request();

        $view = $controller->index($request);
        $inventoryItems = $view->getData()['inventory'];

        $this->assertCount(4, $inventoryItems);

        // Verify strictly alphabetical order (A -> Z)
        $names = collect($inventoryItems->items())->map(fn($inv) => $inv->product->name)->toArray();
        $this->assertEquals(['Angle Iron', 'Bonda', 'Nail #6', 'Z Metal'], $names);

        // Verify product SKU codes are accurately accessible on each item
        $skus = collect($inventoryItems->items())->map(fn($inv) => $inv->product->sku)->toArray();
        $this->assertEquals(['MAT-0789', 'MAT-0276', 'MAT-0001', 'MAT-0008'], $skus);
    }

    public function test_inventory_search_filters_by_sku_code()
    {
        $p1 = Product::create(['name' => 'Paint Water', 'sku' => 'MAT-0094', 'category' => 'Consumable']);
        $p2 = Product::create(['name' => 'Soap Holder', 'sku' => 'MAT-0279', 'category' => 'Consumable']);

        Inventory::create(['store_id' => $this->store->id, 'product_id' => $p1->id, 'quantity_on_hand' => 5]);
        Inventory::create(['store_id' => $this->store->id, 'product_id' => $p2->id, 'quantity_on_hand' => 8]);

        $controller = app(InventoryController::class);
        $request = new Request(['search' => 'MAT-0279']);

        $view = $controller->index($request);
        $inventoryItems = $view->getData()['inventory'];

        $this->assertCount(1, $inventoryItems);
        $this->assertEquals('Soap Holder', $inventoryItems->first()->product->name);
        $this->assertEquals('MAT-0279', $inventoryItems->first()->product->sku);
    }

    public function test_store_manager_inventory_all_handles_null_product_gracefully()
    {
        $p1 = Product::create(['name' => 'Valid Material', 'sku' => 'MAT-9999', 'category' => 'Consumable']);
        Inventory::create(['store_id' => $this->store->id, 'product_id' => $p1->id, 'quantity_on_hand' => 10]);

        // Create an inventory item referencing a non-existent or soft-deleted product
        Inventory::create(['store_id' => $this->store->id, 'product_id' => 99999, 'quantity_on_hand' => 5]);

        $controller = app(\App\Http\Controllers\StoreManagerController::class);
        $request = new Request();

        // Must not throw "Attempt to read property 'sku' on null"
        $view = $controller->allInventory($request);
        $this->assertNotNull($view);
    }
}
