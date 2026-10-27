<?php

namespace Tests\Unit;

use App\Http\Controllers\ProductController;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductCompleteDeleteBySkuTest extends TestCase
{
    protected ProductController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        DB::statement('PRAGMA foreign_keys = OFF;');

        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('fixed_asset_assignments');
        Schema::dropIfExists('fixed_asset_units');
        Schema::dropIfExists('fixed_assets');
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory');
        Schema::dropIfExists('material_prices');
        Schema::dropIfExists('rebar_dia_products');
        Schema::dropIfExists('products');
        Schema::dropIfExists('users');

        Schema::create('roles', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name')->default('web');
            $table->timestamps();
        });

        Schema::create('model_has_roles', function ($table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['role_id', 'model_id', 'model_type']);
        });

        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('password')->nullable();
            $table->boolean('is_admin')->default(false);
            $table->timestamp('access_blocked_at')->nullable();
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

        Schema::create('material_prices', function ($table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->decimal('price', 15, 2);
            $table->date('effective_date');
            $table->timestamps();
        });

        Schema::create('rebar_dia_products', function ($table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->integer('diameter');
            $table->timestamps();
        });

        Schema::create('fixed_assets', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('category')->default('Equipment');
            $table->string('code_prefix')->nullable();
            $table->text('description')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('fixed_asset_units', function ($table) {
            $table->id();
            $table->unsignedBigInteger('fixed_asset_id');
            $table->string('unit_code')->nullable();
            $table->timestamps();
        });

        Schema::create('fixed_asset_assignments', function ($table) {
            $table->id();
            $table->unsignedBigInteger('fixed_asset_unit_id'); // Note: Only fixed_asset_unit_id, NO fixed_asset_id!
            $table->string('action')->default('assigned');
            $table->timestamps();
        });

        $this->controller = app(ProductController::class);
    }

    protected function createGlobalAdminUser(): User
    {
        $roleId = DB::table('roles')->insertGetId(['name' => 'global_admin', 'guard_name' => 'web']);
        $user = User::create([
            'name'     => 'Global Admin',
            'email'    => 'admin@example.com',
            'is_admin' => true,
        ]);
        DB::table('model_has_roles')->insert([
            'role_id'    => $roleId,
            'model_type' => User::class,
            'model_id'   => $user->id,
        ]);
        return $user;
    }

    protected function createRegularUser(): User
    {
        $roleId = DB::table('roles')->insertGetId(['name' => 'store_keeper', 'guard_name' => 'web']);
        $user = User::create([
            'name'     => 'Store Keeper',
            'email'    => 'sk@example.com',
            'is_admin' => false,
        ]);
        DB::table('model_has_roles')->insert([
            'role_id'    => $roleId,
            'model_type' => User::class,
            'model_id'   => $user->id,
        ]);
        return $user;
    }

    public function test_non_global_admin_cannot_access_complete_delete_or_lookup()
    {
        $regularUser = $this->createRegularUser();
        Auth::login($regularUser);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        $request = Request::create(route('products.complete-delete-by-sku'), 'POST', [
            'sku'         => 'TEST-SKU-1',
            'confirm_sku' => 'DELETE',
        ]);
        $this->controller->completeDeleteBySku($request);
    }

    public function test_global_admin_can_lookup_product_by_sku()
    {
        $admin = $this->createGlobalAdminUser();
        Auth::login($admin);

        $product = Product::create([
            'name'       => 'High Tensile Rebar 16mm',
            'sku'        => 'REBAR-16MM',
            'category'   => 'Steel & Metals',
            'unit'       => 'pcs',
            'unit_price' => 750.50,
        ]);

        DB::table('inventory')->insert([
            'store_id'         => 1,
            'product_id'       => $product->id,
            'quantity_on_hand' => 120.000,
            'unit_cost'        => 750.50,
        ]);

        $request = Request::create(route('products.lookup-by-sku'), 'GET', [
            'sku' => 'REBAR-16MM',
        ]);

        $response = $this->controller->lookupBySku($request);
        $data = $response->getData(true);

        $this->assertTrue($data['found']);
        $this->assertEquals(1, $data['count']);
        $this->assertEquals('REBAR-16MM', $data['products'][0]['sku']);
        $this->assertEquals('High Tensile Rebar 16mm', $data['products'][0]['name']);
        $this->assertEquals(120, $data['products'][0]['stock_on_hand']);
    }

    public function test_global_admin_can_completely_delete_material_by_sku_and_purge_all_relations()
    {
        $admin = $this->createGlobalAdminUser();
        Auth::login($admin);

        $product = Product::create([
            'name'       => 'Galvanized Pipe 2 inch',
            'sku'        => 'PIPE-GALV-2IN',
            'category'   => 'Plumbing',
            'unit'       => 'pcs',
            'unit_price' => 1200.00,
        ]);

        $invId = DB::table('inventory')->insertGetId([
            'store_id'         => 1,
            'product_id'       => $product->id,
            'quantity_on_hand' => 50.000,
            'unit_cost'        => 1200.00,
        ]);

        DB::table('inventory_movements')->insert([
            'inventory_id' => $invId,
            'type'         => 'in',
            'quantity'     => 50.000,
            'performed_by' => $admin->id,
        ]);

        DB::table('material_prices')->insert([
            'product_id'     => $product->id,
            'price'          => 1200.00,
            'effective_date' => now()->toDateString(),
        ]);

        $request = Request::create(route('products.complete-delete-by-sku'), 'POST', [
            'sku'         => 'PIPE-GALV-2IN',
            'confirm_sku' => 'DELETE',
        ]);

        $response = $this->controller->completeDeleteBySku($request);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertNull(session('error'));

        // Assert Product is completely deleted (not even in withTrashed)
        $this->assertNull(Product::withTrashed()->where('sku', 'PIPE-GALV-2IN')->first());
        $this->assertEquals(0, DB::table('products')->where('sku', 'PIPE-GALV-2IN')->count());

        // Assert Inventory and movements are completely purged
        $this->assertEquals(0, DB::table('inventory')->where('product_id', $product->id)->count());
        $this->assertEquals(0, DB::table('inventory_movements')->where('inventory_id', $invId)->count());
        $this->assertEquals(0, DB::table('material_prices')->where('product_id', $product->id)->count());
    }

    public function test_complete_delete_rejects_without_valid_confirmation()
    {
        $admin = $this->createGlobalAdminUser();
        Auth::login($admin);

        Product::create([
            'name'       => 'Safety Helmet Yellow',
            'sku'        => 'PPE-HELM-YEL',
            'category'   => 'Safety Equipment',
            'unit'       => 'pcs',
        ]);

        $request = Request::create(route('products.complete-delete-by-sku'), 'POST', [
            'sku'         => 'PPE-HELM-YEL',
            'confirm_sku' => 'WRONG_CONFIRMATION',
        ]);

        $response = $this->controller->completeDeleteBySku($request);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertTrue(session()->has('error'));

        // Product must still exist
        $this->assertNotNull(Product::where('sku', 'PPE-HELM-YEL')->first());
    }

    public function test_complete_delete_purges_previously_soft_deleted_product()
    {
        $admin = $this->createGlobalAdminUser();
        Auth::login($admin);

        $product = Product::create([
            'name'       => 'Archived Cement Bag',
            'sku'        => 'CEM-ARCHIVED',
            'category'   => 'Cement & Concrete',
            'unit'       => 'bag',
        ]);
        $product->delete(); // soft delete

        $this->assertTrue($product->trashed());
        $this->assertNotNull(Product::withTrashed()->where('sku', 'CEM-ARCHIVED')->first());

        $request = Request::create(route('products.complete-delete-by-sku'), 'POST', [
            'sku'         => 'CEM-ARCHIVED',
            'confirm_sku' => 'CEM-ARCHIVED',
        ]);

        $response = $this->controller->completeDeleteBySku($request);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertNull(session('error'));

        // Product is permanently gone from DB
        $this->assertNull(Product::withTrashed()->where('sku', 'CEM-ARCHIVED')->first());
        $this->assertEquals(0, DB::table('products')->where('sku', 'CEM-ARCHIVED')->count());
    }

    public function test_bulk_sku_complete_delete()
    {
        $admin = $this->createGlobalAdminUser();
        Auth::login($admin);

        Product::create(['name' => 'Item 1', 'sku' => 'BULK-SKU-1']);
        Product::create(['name' => 'Item 2', 'sku' => 'BULK-SKU-2']);

        $request = Request::create(route('products.complete-delete-by-sku'), 'POST', [
            'sku'         => 'BULK-SKU-1, BULK-SKU-2',
            'confirm_sku' => 'DELETE',
        ]);

        $response = $this->controller->completeDeleteBySku($request);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertNull(session('error'));

        $this->assertNull(Product::withTrashed()->where('sku', 'BULK-SKU-1')->first());
        $this->assertNull(Product::withTrashed()->where('sku', 'BULK-SKU-2')->first());
    }

    public function test_global_admin_can_completely_delete_fixed_asset_by_sku_without_column_errors()
    {
        $admin = $this->createGlobalAdminUser();
        Auth::login($admin);

        // Scenario matching user error: Fixed asset SKU 'FA-117B' with linked fixed_assets, units, and assignments
        $product = Product::create([
            'name'     => 'Excavator Model 117B',
            'sku'      => 'FA-117B',
            'category' => 'Fixed Asset',
            'unit'     => 'pcs',
        ]);

        $faId = DB::table('fixed_assets')->insertGetId([
            'name'        => 'Excavator Model 117B',
            'category'    => 'Heavy Machinery',
            'code_prefix' => '117B',
            'description' => 'Auto-synced from Store Inventory (Product SKU: FA-117B)',
        ]);

        $unitId = DB::table('fixed_asset_units')->insertGetId([
            'fixed_asset_id' => $faId,
            'unit_code'      => '117B-1',
        ]);

        // fixed_asset_assignments table only has fixed_asset_unit_id, NOT fixed_asset_id
        DB::table('fixed_asset_assignments')->insert([
            'fixed_asset_unit_id' => $unitId,
            'action'              => 'assigned',
        ]);

        $request = Request::create(route('products.complete-delete-by-sku'), 'POST', [
            'sku'         => 'FA-117B',
            'confirm_sku' => 'DELETE',
        ]);

        $response = $this->controller->completeDeleteBySku($request);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertNull(session('error'));

        // Assert all related records are permanently deleted without any SQL column errors
        $this->assertNull(Product::withTrashed()->where('sku', 'FA-117B')->first());
        $this->assertEquals(0, DB::table('fixed_assets')->where('id', $faId)->count());
        $this->assertEquals(0, DB::table('fixed_asset_units')->where('id', $unitId)->count());
        $this->assertEquals(0, DB::table('fixed_asset_assignments')->where('fixed_asset_unit_id', $unitId)->count());
    }
}
