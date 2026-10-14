<?php

namespace Tests\Unit;

use App\Http\Controllers\StoreManagerController;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Store;
use App\Models\Transfer;
use App\Models\TransferItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TransferInventorySyncTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DB::statement('PRAGMA foreign_keys = OFF;');
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory');
        Schema::dropIfExists('transfer_items');
        Schema::dropIfExists('transfers');
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
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('sub_category')->nullable();
            $table->string('sku')->nullable();
            $table->decimal('unit_price', 15, 2)->default(100.00);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('transfers', function ($table) {
            $table->id();
            $table->string('transfer_no')->unique();
            $table->string('physical_slip_no')->nullable();
            $table->unsignedBigInteger('from_store_id');
            $table->unsignedBigInteger('to_store_id');
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->date('required_date')->nullable();
            $table->text('reason')->nullable();
            $table->string('status')->default('draft');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('received_by')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->unsignedBigInteger('driver_employee_id')->nullable();
            $table->string('vehicle_plate_no')->nullable();
            $table->text('dispatch_notes')->nullable();
            $table->unsignedBigInteger('dispatched_by')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->string('outgoing_slip_file')->nullable();
            $table->string('outgoing_slip_no')->nullable();
            $table->string('receiving_slip_file')->nullable();
            $table->string('receiving_slip_no')->nullable();
            $table->text('receiving_notes')->nullable();
            $table->unsignedBigInteger('material_request_id')->nullable();
            $table->unsignedBigInteger('merged_into_transfer_id')->nullable();
            $table->text('merge_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('transfer_items', function ($table) {
            $table->id();
            $table->unsignedBigInteger('transfer_id');
            $table->unsignedBigInteger('product_id');
            $table->decimal('requested_quantity', 15, 3)->default(0);
            $table->decimal('approved_quantity', 15, 3)->default(0);
            $table->decimal('sent_quantity', 15, 3)->default(0);
            $table->decimal('received_quantity', 15, 3)->default(0);
            $table->string('unit')->default('pcs');
            $table->json('unit_codes')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory', function ($table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('product_id');
            $table->decimal('quantity_on_hand', 15, 3)->default(0);
            $table->decimal('quantity_reserved', 15, 3)->default(0);
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->decimal('min_stock', 15, 3)->default(0);
            $table->timestamp('last_movement_at')->nullable();
            $table->timestamps();
            $table->unique(['store_id', 'product_id']);
        });

        Schema::create('inventory_movements', function ($table) {
            $table->id();
            $table->unsignedBigInteger('inventory_id');
            $table->string('type');
            $table->decimal('quantity', 15, 3);
            $table->nullableMorphs('reference');
            $table->unsignedBigInteger('performed_by')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF;');
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory');
        Schema::dropIfExists('transfer_items');
        Schema::dropIfExists('transfers');
        Schema::dropIfExists('products');
        Schema::dropIfExists('stores');
        Schema::dropIfExists('users');
        parent::tearDown();
    }

    /**
     * Test creating a transfer immediately deducts from origin store inventory and adds to destination store inventory.
     */
    public function test_transfer_creation_deducts_origin_and_adds_destination(): void
    {
        $originStore = Store::create(['name' => 'Main Warehouse']);
        $destStore   = Store::create(['name' => 'Bole Site Store']);
        $product     = Product::create(['name' => 'Cement 50kg', 'unit_price' => 750.00]);
        $user        = User::create(['name' => 'Store Manager', 'email' => 'manager@test.com']);

        // Initial origin inventory: 100 bags
        Inventory::create([
            'store_id'         => $originStore->id,
            'product_id'       => $product->id,
            'quantity_on_hand' => 100.0,
            'unit_cost'        => 750.00,
        ]);

        // Destination store starts with 0
        $this->assertEquals(0, Inventory::where('store_id', $destStore->id)->where('product_id', $product->id)->count());

        // Create transfer of 30 bags
        $transfer = Transfer::create([
            'transfer_no'   => 'TR-20261014-0001',
            'from_store_id' => $originStore->id,
            'to_store_id'   => $destStore->id,
            'requested_by'  => $user->id,
            'status'        => 'draft',
        ]);

        $item = $transfer->items()->create([
            'product_id'         => $product->id,
            'requested_quantity' => 30.0,
            'unit'               => 'bags',
        ]);

        // Verify origin store is decremented: 100 - 30 = 70
        $originInv = Inventory::where('store_id', $originStore->id)->where('product_id', $product->id)->first();
        $this->assertEquals(70.0, (float)$originInv->quantity_on_hand);

        // Verify destination store is incremented: 0 + 30 = 30
        $destInv = Inventory::where('store_id', $destStore->id)->where('product_id', $product->id)->first();
        $this->assertNotNull($destInv);
        $this->assertEquals(30.0, (float)$destInv->quantity_on_hand);

        // Verify Inventory Movements recorded
        $this->assertTrue($transfer->isDeductedFromOrigin());
        $this->assertTrue($transfer->isAddedToDestination());

        $movementOut = InventoryMovement::where('inventory_id', $originInv->id)->where('type', 'transfer_out')->first();
        $this->assertNotNull($movementOut);
        $this->assertEquals(-30.0, (float)$movementOut->quantity);

        $movementIn = InventoryMovement::where('inventory_id', $destInv->id)->where('type', 'transfer_in')->first();
        $this->assertNotNull($movementIn);
        $this->assertEquals(30.0, (float)$movementIn->quantity);
    }

    /**
     * Test idempotency: syncing transfer again does not double-deduct or double-add.
     */
    public function test_transfer_sync_is_idempotent(): void
    {
        $originStore = Store::create(['name' => 'Main Warehouse']);
        $destStore   = Store::create(['name' => 'Site A Store']);
        $product     = Product::create(['name' => 'Steel Rebar 12mm', 'unit_price' => 1200.00]);

        Inventory::create([
            'store_id'         => $originStore->id,
            'product_id'       => $product->id,
            'quantity_on_hand' => 50.0,
            'unit_cost'        => 1200.00,
        ]);

        $transfer = Transfer::create([
            'transfer_no'   => 'TR-20261014-0002',
            'from_store_id' => $originStore->id,
            'to_store_id'   => $destStore->id,
            'status'        => 'draft',
        ]);

        $transfer->items()->create([
            'product_id'         => $product->id,
            'requested_quantity' => 15.0,
            'unit'               => 'pcs',
        ]);

        // Manually run sync again
        $res = StoreManagerController::syncTransferInventory($transfer);
        $this->assertEquals(0, $res['deducted'], 'Second sync must not double-deduct');
        $this->assertEquals(0, $res['added'], 'Second sync must not double-add');

        $originInv = Inventory::where('store_id', $originStore->id)->where('product_id', $product->id)->first();
        $this->assertEquals(35.0, (float)$originInv->quantity_on_hand);

        $destInv = Inventory::where('store_id', $destStore->id)->where('product_id', $product->id)->first();
        $this->assertEquals(15.0, (float)$destInv->quantity_on_hand);
    }

    /**
     * Test rejection reverses the inventory deduction and addition.
     */
    public function test_transfer_rejection_reverses_inventory(): void
    {
        $originStore = Store::create(['name' => 'Store 1']);
        $destStore   = Store::create(['name' => 'Store 2']);
        $product     = Product::create(['name' => 'Sand', 'unit_price' => 500.00]);

        Inventory::create([
            'store_id'         => $originStore->id,
            'product_id'       => $product->id,
            'quantity_on_hand' => 40.0,
            'unit_cost'        => 500.00,
        ]);

        $transfer = Transfer::create([
            'transfer_no'   => 'TR-20261014-0003',
            'from_store_id' => $originStore->id,
            'to_store_id'   => $destStore->id,
            'status'        => 'draft',
        ]);

        $transfer->items()->create([
            'product_id'         => $product->id,
            'requested_quantity' => 10.0,
            'unit'               => 'm3',
        ]);

        // Origin now 30, Dest now 10
        $this->assertEquals(30.0, (float)Inventory::where('store_id', $originStore->id)->where('product_id', $product->id)->value('quantity_on_hand'));
        $this->assertEquals(10.0, (float)Inventory::where('store_id', $destStore->id)->where('product_id', $product->id)->value('quantity_on_hand'));

        // Reverse transfer
        StoreManagerController::reverseTransferInventory($transfer, 'Cancelled by Project Coordinator');

        // Origin restored back to 40
        $this->assertEquals(40.0, (float)Inventory::where('store_id', $originStore->id)->where('product_id', $product->id)->value('quantity_on_hand'));

        // Destination decremented back to 0
        $this->assertEquals(0.0, (float)Inventory::where('store_id', $destStore->id)->where('product_id', $product->id)->value('quantity_on_hand'));
    }
}
