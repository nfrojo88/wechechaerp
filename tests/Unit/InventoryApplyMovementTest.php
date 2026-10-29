<?php

namespace Tests\Unit;

use App\Http\Controllers\StoreManagerController;
use App\Models\DeliveryReceipt;
use App\Models\DeliveryReceiptItem;
use App\Models\Employee;
use App\Models\FixedAsset;
use App\Models\FixedAssetAssignment;
use App\Models\FixedAssetUnit;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\MaterialRequest;
use App\Models\MaterialRequestItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\SlipSequence;
use App\Models\Store;
use App\Models\Transfer;
use App\Models\TransferItem;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InventoryApplyMovementTest extends TestCase
{
    private InventoryService $inventoryService;
    private Store $store;
    private Product $product;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        DB::statement('PRAGMA foreign_keys = OFF;');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('slip_sequences');
        Schema::dropIfExists('transfer_items');
        Schema::dropIfExists('transfers');
        Schema::dropIfExists('material_request_items');
        Schema::dropIfExists('material_requests');
        Schema::dropIfExists('delivery_receipt_items');
        Schema::dropIfExists('delivery_receipts');
        Schema::dropIfExists('fixed_asset_assignments');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('fixed_asset_units');
        Schema::dropIfExists('fixed_assets');
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory');
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

        Schema::create('employees', function ($table) {
            $table->id();
            $table->string('full_name')->nullable();
            $table->string('employee_code')->nullable();
            $table->string('department')->nullable();
            $table->string('status')->default('active');
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
            $table->string('category')->nullable();
            $table->string('sub_category')->nullable();
            $table->string('sku')->nullable();
            $table->string('code')->nullable();
            $table->string('unit')->default('pcs');
            $table->decimal('unit_price', 15, 2)->default(100.00);
            $table->decimal('selling_price', 15, 2)->default(100.00);
            $table->unsignedInteger('max_stock')->nullable();
            $table->unsignedInteger('reorder_level')->nullable();
            $table->string('equipment_condition')->nullable();
            $table->string('assigned_to')->nullable();
            $table->string('current_location')->nullable();
            $table->string('asset_status')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
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
            $table->decimal('balance_before', 15, 3)->nullable();
            $table->decimal('balance_after', 15, 3)->nullable();
            $table->nullableMorphs('reference');
            $table->unsignedBigInteger('performed_by')->nullable();
            $table->text('remarks')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('fixed_assets', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('category');
            $table->string('code_prefix');
            $table->unsignedInteger('total_quantity')->default(1);
            $table->decimal('unit_cost', 15, 2)->default(0.00);
            $table->date('purchase_date')->nullable();
            $table->string('supplier')->nullable();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('fixed_asset_units', function ($table) {
            $table->id();
            $table->unsignedBigInteger('fixed_asset_id');
            $table->string('unit_code')->unique();
            $table->unsignedInteger('sequence_number');
            $table->string('status')->default('in_store');
            $table->string('condition')->default('good');
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('plate_number')->nullable();
            $table->string('chassis_number')->nullable();
            $table->string('engine_number')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->text('specifications')->nullable();
            $table->json('custom_attributes')->nullable();
            $table->unsignedBigInteger('assigned_to_employee_id')->nullable();
            $table->dateTime('assigned_date')->nullable();
            $table->string('current_location')->nullable();
            $table->decimal('purchase_price', 15, 2)->nullable();
            $table->date('warranty_expiry')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('fixed_asset_assignments', function ($table) {
            $table->id();
            $table->unsignedBigInteger('fixed_asset_unit_id');
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->string('action')->default('assigned');
            $table->dateTime('assigned_date')->nullable();
            $table->dateTime('returned_date')->nullable();
            $table->string('condition_on_assignment')->nullable();
            $table->string('condition_on_return')->nullable();
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->unsignedBigInteger('received_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('delivery_receipts', function ($table) {
            $table->id();
            $table->string('dr_no')->nullable();
            $table->string('slip_type')->default('receive');
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('to_store_id')->nullable();
            $table->date('received_date')->nullable();
            $table->date('receipt_date')->nullable();
            $table->string('supplier_name')->nullable();
            $table->string('reference_no')->nullable();
            $table->unsignedBigInteger('purchase_order_id')->nullable();
            $table->unsignedBigInteger('received_by')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('status')->default('draft');
            $table->boolean('is_void')->default(false);
            $table->string('sequence_status')->default('pending');
            $table->timestamps();
        });

        Schema::create('delivery_receipt_items', function ($table) {
            $table->id();
            $table->unsignedBigInteger('delivery_receipt_id');
            $table->unsignedBigInteger('product_id');
            $table->decimal('quantity_received', 15, 3)->default(0);
            $table->decimal('accepted_quantity', 15, 3)->default(0);
            $table->decimal('rejected_quantity', 15, 3)->default(0);
            $table->string('unit')->default('pcs');
            $table->timestamps();
        });

        Schema::create('material_requests', function ($table) {
            $table->id();
            $table->unsignedBigInteger('destination_store_id')->nullable();
            $table->string('reference_number')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('material_request_items', function ($table) {
            $table->id();
            $table->unsignedBigInteger('material_request_id');
            $table->unsignedBigInteger('product_id');
            $table->decimal('quantity_requested', 15, 3)->default(0);
            $table->decimal('quantity_fulfilled', 15, 3)->default(0);
            $table->timestamps();
        });

        Schema::create('transfers', function ($table) {
            $table->id();
            $table->string('transfer_no')->nullable();
            $table->string('physical_slip_no')->nullable();
            $table->string('outgoing_slip_no')->nullable();
            $table->string('receiving_slip_no')->nullable();
            $table->unsignedBigInteger('from_store_id');
            $table->unsignedBigInteger('to_store_id');
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->unsignedBigInteger('dispatched_by')->nullable();
            $table->dateTime('dispatched_at')->nullable();
            $table->unsignedBigInteger('received_by')->nullable();
            $table->dateTime('received_at')->nullable();
            $table->timestamps();
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
            $table->timestamps();
        });

        Schema::create('slip_sequences', function ($table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->string('slip_type');
            $table->string('prefix')->nullable();
            $table->integer('current_slip_no')->default(1);
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('purchase_orders', function ($table) {
            $table->id();
            $table->string('supplier_name')->nullable();
            $table->string('reference_number')->nullable();
            $table->string('status')->default('delivered');
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->date('issued_date')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        $this->inventoryService = new InventoryService();
        $this->store = Store::create(['name' => 'Store A', 'is_active' => true]);
        $this->product = Product::create([
            'name'       => 'Computer',
            'sku'        => 'FA-COMP',
            'category'   => 'Fixed Asset',
            'unit_price' => 500.00,
        ]);
        $this->user = User::create([
            'name'  => 'Test User',
            'email' => 'test@example.com',
        ]);
    }

    protected function tearDown(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF;');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('slip_sequences');
        Schema::dropIfExists('transfer_items');
        Schema::dropIfExists('transfers');
        Schema::dropIfExists('material_request_items');
        Schema::dropIfExists('material_requests');
        Schema::dropIfExists('delivery_receipt_items');
        Schema::dropIfExists('delivery_receipts');
        Schema::dropIfExists('fixed_asset_assignments');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('fixed_asset_units');
        Schema::dropIfExists('fixed_assets');
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory');
        Schema::dropIfExists('products');
        Schema::dropIfExists('stores');
        Schema::dropIfExists('users');
        parent::tearDown();
    }

    public function test_apply_movement_adds_stock_and_logs_ledger()
    {
        $res = $this->inventoryService->applyMovement(
            $this->store->id,
            $this->product->id,
            10.0,
            'in',
            remarks: 'Initial stock intake',
            performedBy: $this->user->id,
            unitCost: 500.0
        );

        $this->assertEquals(10.0, (float) $res['inventory']->quantity_on_hand);
        $this->assertEquals(0.0, (float) $res['balance_before']);
        $this->assertEquals(10.0, (float) $res['balance_after']);

        $movement = InventoryMovement::first();
        $this->assertNotNull($movement);
        $this->assertEquals(10.0, (float) $movement->quantity);
        $this->assertEquals(0.0, (float) $movement->balance_before);
        $this->assertEquals(10.0, (float) $movement->balance_after);
        $this->assertEquals('in', $movement->type);
    }

    public function test_apply_movement_deducts_stock_and_records_signed_delta()
    {
        $this->inventoryService->applyMovement(
            $this->store->id,
            $this->product->id,
            10.0,
            'in',
            performedBy: $this->user->id
        );

        $res = $this->inventoryService->applyMovement(
            $this->store->id,
            $this->product->id,
            -3.0,
            'out',
            remarks: 'Dispatched to site',
            performedBy: $this->user->id
        );

        $this->assertEquals(7.0, (float) $res['inventory']->quantity_on_hand);
        $this->assertEquals(10.0, (float) $res['balance_before']);
        $this->assertEquals(7.0, (float) $res['balance_after']);

        $movements = InventoryMovement::orderBy('id')->get();
        $this->assertCount(2, $movements);
        $second = $movements->last();
        $this->assertEquals(-3.0, (float) $second->quantity);
        $this->assertEquals(10.0, (float) $second->balance_before);
        $this->assertEquals(7.0, (float) $second->balance_after);
    }

    public function test_apply_movement_rejects_deduct_below_zero()
    {
        $this->inventoryService->applyMovement(
            $this->store->id,
            $this->product->id,
            5.0,
            'in',
            performedBy: $this->user->id
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Stock would fall below zero');

        $this->inventoryService->applyMovement(
            $this->store->id,
            $this->product->id,
            -6.0,
            'out',
            performedBy: $this->user->id
        );
    }

    public function test_apply_movement_rejects_deduct_below_reserved_stock()
    {
        // 10 on hand, reserve 4 (available = 6)
        $this->inventoryService->applyMovement(
            $this->store->id,
            $this->product->id,
            10.0,
            'in',
            performedBy: $this->user->id,
            reservedDelta: 4.0
        );

        // Attempting to deduct 7 should be rejected because only 6 are available (4 are reserved)
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('only 6 available, 4 are currently assigned/reserved to staff');

        $this->inventoryService->applyMovement(
            $this->store->id,
            $this->product->id,
            -7.0,
            'out',
            performedBy: $this->user->id
        );
    }

    public function test_apply_movement_adjusts_reservations()
    {
        $this->inventoryService->applyMovement(
            $this->store->id,
            $this->product->id,
            10.0,
            'in',
            performedBy: $this->user->id
        );

        // Assign 3 units to employees
        $res = $this->inventoryService->applyMovement(
            $this->store->id,
            $this->product->id,
            0.0,
            'fa_assign',
            remarks: 'Assigned 3 units to staff',
            performedBy: $this->user->id,
            reservedDelta: 3.0
        );

        $this->assertEquals(10.0, (float) $res['inventory']->quantity_on_hand);
        $this->assertEquals(3.0, (float) $res['inventory']->quantity_reserved);

        // Return 1 unit from staff
        $res2 = $this->inventoryService->applyMovement(
            $this->store->id,
            $this->product->id,
            0.0,
            'fa_return',
            remarks: 'Returned 1 unit to store',
            performedBy: $this->user->id,
            reservedDelta: -1.0
        );

        $this->assertEquals(10.0, (float) $res2['inventory']->quantity_on_hand);
        $this->assertEquals(2.0, (float) $res2['inventory']->quantity_reserved);
    }

    public function test_inventory_movement_is_strictly_append_only()
    {
        $res = $this->inventoryService->applyMovement(
            $this->store->id,
            $this->product->id,
            10.0,
            'in',
            performedBy: $this->user->id
        );

        $movement = $res['movement'];

        // Updating must throw RuntimeException
        try {
            $movement->update(['quantity' => 25.0]);
            $this->fail('Expected RuntimeException on movement update, but none was thrown.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('append-only: updates are strictly prohibited', $e->getMessage());
        }

        // Deleting must throw RuntimeException
        try {
            $movement->delete();
            $this->fail('Expected RuntimeException on movement delete, but none was thrown.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('append-only: deletes are strictly prohibited', $e->getMessage());
        }
    }

    public function test_fixed_asset_sync_does_not_overwrite_inventory_master()
    {
        // 1. Inventory is master with 8 units
        $this->inventoryService->applyMovement(
            $this->store->id,
            $this->product->id,
            8.0,
            'adjustment',
            remarks: 'Stock set to 8',
            performedBy: $this->user->id
        );

        $inv = Inventory::where('store_id', $this->store->id)->where('product_id', $this->product->id)->first();
        $this->assertEquals(8.0, (float) $inv->quantity_on_hand);

        // 2. FixedAsset exists with 10 units
        $asset = FixedAsset::create([
            'name'           => $this->product->name,
            'category'       => 'Computer & IT',
            'code_prefix'    => 'COMP',
            'total_quantity' => 10,
            'unit_cost'      => 500.0,
            'store_id'       => $this->store->id,
        ]);

        for ($i = 1; $i <= 10; $i++) {
            FixedAssetUnit::create([
                'fixed_asset_id'  => $asset->id,
                'unit_code'       => "COMP-{$i}",
                'sequence_number' => $i,
                'status'          => 'in_store',
            ]);
        }

        // 3. Trigger syncWithCatalogAndInventory
        $asset->syncWithCatalogAndInventory();

        // 4. Verify inventory balance was NOT reverted or overwritten to 10
        $invAfter = Inventory::where('store_id', $this->store->id)->where('product_id', $this->product->id)->first();
        $this->assertEquals(8.0, (float) $invAfter->quantity_on_hand, 'Inventory balance must remain 8 and not be overwritten by FixedAsset sync');
    }

    public function test_manual_add_increases_stock_and_creates_positive_movement()
    {
        // Start with 10 computers
        $this->inventoryService->applyMovement($this->store->id, $this->product->id, 10.0, 'in', performedBy: $this->user->id);

        // Manual Add +2
        $res = $this->inventoryService->applyMovement(
            $this->store->id,
            $this->product->id,
            2.0,
            'adjustment',
            referenceType: 'manual_adjustment',
            remarks: 'Manual stock addition (+2)',
            performedBy: $this->user->id
        );

        $this->assertEquals(12.0, (float) $res['inventory']->quantity_on_hand);
        $this->assertEquals(10.0, (float) $res['balance_before']);
        $this->assertEquals(12.0, (float) $res['balance_after']);
        $this->assertEquals(2.0, (float) $res['movement']->quantity);
    }

    public function test_manual_deduct_decreases_stock_and_creates_negative_movement()
    {
        // Start with 12 computers
        $this->inventoryService->applyMovement($this->store->id, $this->product->id, 12.0, 'in', performedBy: $this->user->id);

        // Manual Deduct -1
        $res = $this->inventoryService->applyMovement(
            $this->store->id,
            $this->product->id,
            -1.0,
            'adjustment',
            referenceType: 'manual_adjustment',
            remarks: 'Manual stock deduction (-1)',
            performedBy: $this->user->id
        );

        $this->assertEquals(11.0, (float) $res['inventory']->quantity_on_hand);
        $this->assertEquals(12.0, (float) $res['balance_before']);
        $this->assertEquals(11.0, (float) $res['balance_after']);
        $this->assertEquals(-1.0, (float) $res['movement']->quantity);
    }

    public function test_manual_set_calculates_difference_and_updates_balance()
    {
        // Start with 11 computers
        $this->inventoryService->applyMovement($this->store->id, $this->product->id, 11.0, 'in', performedBy: $this->user->id);

        // Manual Set to 8 (diff is -3)
        $current = (float) Inventory::where('store_id', $this->store->id)->where('product_id', $this->product->id)->value('quantity_on_hand');
        $target = 8.0;
        $diff = $target - $current;

        $res = $this->inventoryService->applyMovement(
            $this->store->id,
            $this->product->id,
            $diff,
            'adjustment',
            referenceType: 'manual_adjustment',
            remarks: "Manual stock count adjustment — set to {$target}",
            performedBy: $this->user->id
        );

        $this->assertEquals(8.0, (float) $res['inventory']->quantity_on_hand);
        $this->assertEquals(11.0, (float) $res['balance_before']);
        $this->assertEquals(8.0, (float) $res['balance_after']);
        $this->assertEquals(-3.0, (float) $res['movement']->quantity);
    }

    public function test_manual_deduct_more_than_available_is_rejected()
    {
        // Start with 8 on hand, 3 assigned (available = 5)
        $this->inventoryService->applyMovement($this->store->id, $this->product->id, 8.0, 'in', performedBy: $this->user->id, reservedDelta: 3.0);

        // Deduct 6 (more than available 5)
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('only 5 available, 3 are currently assigned/reserved to staff');

        $this->inventoryService->applyMovement(
            $this->store->id,
            $this->product->id,
            -6.0,
            'adjustment',
            performedBy: $this->user->id
        );
    }

    public function test_inventory_adjust_controller_sets_difference_instead_of_routing_to_stock_in()
    {
        $inv = Inventory::create([
            'store_id'         => $this->store->id,
            'product_id'       => $this->product->id,
            'quantity_on_hand' => 10.0,
            'quantity_reserved'=> 0.0,
            'unit_cost'        => 500.0,
        ]);

        $this->actingAs($this->user);

        $controller = new \App\Http\Controllers\InventoryController($this->inventoryService);

        // Submit type = 'adjustment', quantity = 8.0 (Set to 8)
        $request = \Illuminate\Http\Request::create("/inventory/{$inv->id}/adjust", 'POST', [
            'type'     => 'adjustment',
            'quantity' => 8.0,
        ]);

        $controller->adjust($request, $inv);

        $updatedInv = $inv->fresh();
        $this->assertEquals(8.0, (float) $updatedInv->quantity_on_hand, 'Adjustment must set on-hand to 8 (delta -2), NOT add 8 to make 18');

        $movement = InventoryMovement::where('inventory_id', $inv->id)->latest('id')->first();
        $this->assertNotNull($movement);
        $this->assertEquals(-2.0, (float) $movement->quantity);
        $this->assertEquals(10.0, (float) $movement->balance_before);
        $this->assertEquals(8.0, (float) $movement->balance_after);
    }

    public function test_legacy_movements_without_balance_return_null()
    {
        // Create an InventoryMovement directly via DB (like an old record before the migration)
        $inv = Inventory::create([
            'store_id'         => $this->store->id,
            'product_id'       => $this->product->id,
            'quantity_on_hand' => 10.0,
        ]);

        DB::table('inventory_movements')->insert([
            'inventory_id'   => $inv->id,
            'type'           => 'in',
            'quantity'       => 10.0,
            'balance_before' => null,
            'balance_after'  => null,
            'performed_by'   => $this->user->id,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $movement = InventoryMovement::where('inventory_id', $inv->id)->first();
        $this->assertNull($movement->balance_before);
        $this->assertNull($movement->balance_after);
    }

    public function test_fixed_asset_registration_adds_inventory_and_logs_movement()
    {
        $this->actingAs($this->user);

        $asset = FixedAsset::create([
            'name'           => $this->product->name,
            'category'       => 'Computer & IT',
            'code_prefix'    => 'COMP',
            'total_quantity' => 3,
            'unit_cost'      => 500.0,
            'store_id'       => $this->store->id,
        ]);

        for ($i = 1; $i <= 3; $i++) {
            FixedAssetUnit::create([
                'fixed_asset_id'  => $asset->id,
                'unit_code'       => "COMP-{$i}",
                'sequence_number' => $i,
                'status'          => FixedAssetUnit::STATUS_IN_STORE,
                'condition'       => 'good',
                'purchase_price'  => 500.0,
                'created_by'      => $this->user->id,
            ]);
        }

        // Registration applies movement +3
        $this->inventoryService->applyMovement(
            $this->store->id,
            $this->product->id,
            3.0,
            'fa_register',
            referenceType: FixedAsset::class,
            referenceId: $asset->id,
            remarks: "Initial registration of 3 unit(s)",
            performedBy: $this->user->id,
            unitCost: 500.0
        );

        $inv = Inventory::where('store_id', $this->store->id)->where('product_id', $this->product->id)->first();
        $this->assertEquals(3.0, (float) $inv->quantity_on_hand);
        $this->assertEquals(0.0, (float) $inv->quantity_reserved);

        $movement = InventoryMovement::where('inventory_id', $inv->id)->latest('id')->first();
        $this->assertEquals(3.0, (float) $movement->quantity);
        $this->assertEquals(0.0, (float) $movement->balance_before);
        $this->assertEquals(3.0, (float) $movement->balance_after);
        $this->assertEquals('fa_register', $movement->type);
    }

    public function test_fixed_asset_assign_to_employee_reserves_stock()
    {
        $this->actingAs($this->user);

        $asset = FixedAsset::create([
            'name'           => $this->product->name,
            'category'       => 'Computer & IT',
            'code_prefix'    => 'COMP',
            'total_quantity' => 3,
            'unit_cost'      => 500.0,
            'store_id'       => $this->store->id,
        ]);

        $unit1 = FixedAssetUnit::create([
            'fixed_asset_id'  => $asset->id,
            'unit_code'       => 'COMP-1',
            'sequence_number' => 1,
            'status'          => FixedAssetUnit::STATUS_IN_STORE,
            'condition'       => 'good',
            'purchase_price'  => 500.0,
            'created_by'      => $this->user->id,
        ]);

        // Stock in 3 units
        $this->inventoryService->applyMovement($this->store->id, $this->product->id, 3.0, 'fa_register', performedBy: $this->user->id);

        $employee = Employee::create(['full_name' => 'John Doe', 'status' => 'active']);

        // Assign unit to employee
        $unit1->assignToEmployee($employee->id, $this->user->id, 'Assigned to John');

        $inv = Inventory::where('store_id', $this->store->id)->where('product_id', $this->product->id)->first();
        $this->assertEquals(3.0, (float) $inv->quantity_on_hand);
        $this->assertEquals(1.0, (float) $inv->quantity_reserved);
        $this->assertEquals(2.0, (float) ($inv->quantity_on_hand - $inv->quantity_reserved), 'Available stock must be 2');

        $movement = InventoryMovement::where('inventory_id', $inv->id)->latest('id')->first();
        $this->assertEquals(0.0, (float) $movement->quantity);
        $this->assertEquals(3.0, (float) $movement->balance_before);
        $this->assertEquals(3.0, (float) $movement->balance_after);
        $this->assertEquals('fa_assign', $movement->type);
        $this->assertEquals(FixedAssetUnit::STATUS_ASSIGNED, $unit1->fresh()->status);
    }

    public function test_fixed_asset_return_in_good_condition_unreserves_stock()
    {
        $this->actingAs($this->user);

        $asset = FixedAsset::create([
            'name'           => $this->product->name,
            'category'       => 'Computer & IT',
            'code_prefix'    => 'COMP',
            'total_quantity' => 3,
            'unit_cost'      => 500.0,
            'store_id'       => $this->store->id,
        ]);

        $unit1 = FixedAssetUnit::create([
            'fixed_asset_id'  => $asset->id,
            'unit_code'       => 'COMP-1',
            'sequence_number' => 1,
            'status'          => FixedAssetUnit::STATUS_IN_STORE,
            'condition'       => 'good',
            'purchase_price'  => 500.0,
            'created_by'      => $this->user->id,
        ]);

        // 3 on hand, 1 assigned
        $this->inventoryService->applyMovement($this->store->id, $this->product->id, 3.0, 'fa_register', performedBy: $this->user->id);
        $employee = Employee::create(['full_name' => 'John Doe', 'status' => 'active']);
        $unit1->assignToEmployee($employee->id, $this->user->id);

        // Return in good condition
        $unit1->returnToStore($this->user->id, 'Returned in good condition', 'good');

        $inv = Inventory::where('store_id', $this->store->id)->where('product_id', $this->product->id)->first();
        $this->assertEquals(3.0, (float) $inv->quantity_on_hand);
        $this->assertEquals(0.0, (float) $inv->quantity_reserved, 'Reserved stock must be 0 after return');
        $this->assertEquals(FixedAssetUnit::STATUS_IN_STORE, $unit1->fresh()->status);

        $movement = InventoryMovement::where('inventory_id', $inv->id)->latest('id')->first();
        $this->assertEquals('fa_return', $movement->type);
    }

    public function test_fixed_asset_return_damaged_reduces_stock_and_reserved()
    {
        $this->actingAs($this->user);

        $asset = FixedAsset::create([
            'name'           => $this->product->name,
            'category'       => 'Computer & IT',
            'code_prefix'    => 'COMP',
            'total_quantity' => 3,
            'unit_cost'      => 500.0,
            'store_id'       => $this->store->id,
        ]);

        $unit1 = FixedAssetUnit::create([
            'fixed_asset_id'  => $asset->id,
            'unit_code'       => 'COMP-1',
            'sequence_number' => 1,
            'status'          => FixedAssetUnit::STATUS_IN_STORE,
            'condition'       => 'good',
            'purchase_price'  => 500.0,
            'created_by'      => $this->user->id,
        ]);

        $this->inventoryService->applyMovement($this->store->id, $this->product->id, 3.0, 'fa_register', performedBy: $this->user->id);
        $employee = Employee::create(['full_name' => 'John Doe', 'status' => 'active']);
        $unit1->assignToEmployee($employee->id, $this->user->id);

        // Return as damaged
        $unit1->returnToStore($this->user->id, 'Screen cracked', 'damaged');

        $inv = Inventory::where('store_id', $this->store->id)->where('product_id', $this->product->id)->first();
        $this->assertEquals(2.0, (float) $inv->quantity_on_hand, 'Stock on hand must drop from 3 to 2');
        $this->assertEquals(0.0, (float) $inv->quantity_reserved, 'Reserved stock must decrement to 0');
        $this->assertEquals(FixedAssetUnit::STATUS_MAINTENANCE, $unit1->fresh()->status);

        $movement = InventoryMovement::where('inventory_id', $inv->id)->latest('id')->first();
        $this->assertEquals(-1.0, (float) $movement->quantity);
        $this->assertEquals('fa_damaged', $movement->type);
    }

    public function test_positive_manual_adjustment_on_fixed_asset_generates_incomplete_units()
    {
        $this->actingAs($this->user);

        $asset = FixedAsset::create([
            'name'           => $this->product->name,
            'category'       => 'Computer & IT',
            'code_prefix'    => 'COMP',
            'total_quantity' => 2,
            'unit_cost'      => 500.0,
            'store_id'       => $this->store->id,
        ]);

        FixedAssetUnit::create([
            'fixed_asset_id'  => $asset->id,
            'unit_code'       => 'COMP-1',
            'sequence_number' => 1,
            'status'          => FixedAssetUnit::STATUS_IN_STORE,
            'created_by'      => $this->user->id,
        ]);
        FixedAssetUnit::create([
            'fixed_asset_id'  => $asset->id,
            'unit_code'       => 'COMP-2',
            'sequence_number' => 2,
            'status'          => FixedAssetUnit::STATUS_IN_STORE,
            'created_by'      => $this->user->id,
        ]);

        $this->inventoryService->applyMovement($this->store->id, $this->product->id, 2.0, 'fa_register', performedBy: $this->user->id);

        // Positive adjustment: +2 added in Store Manager
        $this->inventoryService->applyMovement(
            $this->store->id,
            $this->product->id,
            2.0,
            'adjustment',
            remarks: 'Physical stock count found 2 more units',
            performedBy: $this->user->id
        );

        $inv = Inventory::where('store_id', $this->store->id)->where('product_id', $this->product->id)->first();
        $this->assertEquals(4.0, (float) $inv->quantity_on_hand);

        // Check that 2 new units were created and flagged "Details incomplete"
        $allUnits = FixedAssetUnit::where('fixed_asset_id', $asset->id)->orderBy('sequence_number')->get();
        $this->assertCount(4, $allUnits);

        $newUnits = $allUnits->slice(2);
        foreach ($newUnits as $nu) {
            $this->assertStringContainsString('Details incomplete', $nu->notes);
            $this->assertEquals(FixedAssetUnit::STATUS_IN_STORE, $nu->status);
        }
    }

    public function test_negative_manual_adjustment_on_fixed_asset_disposes_unassigned_units_without_deleting()
    {
        $this->actingAs($this->user);

        $asset = FixedAsset::create([
            'name'           => $this->product->name,
            'category'       => 'Computer & IT',
            'code_prefix'    => 'COMP',
            'total_quantity' => 3,
            'unit_cost'      => 500.0,
            'store_id'       => $this->store->id,
        ]);

        $u1 = FixedAssetUnit::create([
            'fixed_asset_id'          => $asset->id,
            'unit_code'               => 'COMP-1',
            'sequence_number'         => 1,
            'status'                  => FixedAssetUnit::STATUS_ASSIGNED,
            'assigned_to_employee_id' => 1,
            'created_by'              => $this->user->id,
        ]);
        $u2 = FixedAssetUnit::create([
            'fixed_asset_id'  => $asset->id,
            'unit_code'       => 'COMP-2',
            'sequence_number' => 2,
            'status'          => FixedAssetUnit::STATUS_IN_STORE,
            'created_by'      => $this->user->id,
        ]);
        $u3 = FixedAssetUnit::create([
            'fixed_asset_id'  => $asset->id,
            'unit_code'       => 'COMP-3',
            'sequence_number' => 3,
            'status'          => FixedAssetUnit::STATUS_IN_STORE,
            'created_by'      => $this->user->id,
        ]);

        // Stock has 3 on hand, 1 reserved (available = 2)
        $this->inventoryService->applyMovement($this->store->id, $this->product->id, 3.0, 'fa_register', performedBy: $this->user->id, reservedDelta: 1.0);

        // Deduct 1 via manual adjustment
        $this->inventoryService->applyMovement(
            $this->store->id,
            $this->product->id,
            -1.0,
            'adjustment',
            remarks: 'Physical stock count correction (-1)',
            performedBy: $this->user->id
        );

        $inv = Inventory::where('store_id', $this->store->id)->where('product_id', $this->product->id)->first();
        $this->assertEquals(2.0, (float) $inv->quantity_on_hand);
        $this->assertEquals(1.0, (float) $inv->quantity_reserved);

        // Units count in database MUST STILL BE 3 (never deleted!)
        $this->assertEquals(3, FixedAssetUnit::where('fixed_asset_id', $asset->id)->count());

        // Assigned unit COMP-1 must remain assigned
        $this->assertEquals(FixedAssetUnit::STATUS_ASSIGNED, $u1->fresh()->status);

        // Exactly one in_store unit was marked as disposed
        $disposedUnits = FixedAssetUnit::where('fixed_asset_id', $asset->id)->where('status', FixedAssetUnit::STATUS_DISPOSED)->get();
        $this->assertCount(1, $disposedUnits);
        $this->assertStringContainsString('Marked as disposed', $disposedUnits->first()->notes);
    }

    public function test_fixed_asset_disposal_via_controller_decrements_inventory()
    {
        $this->actingAs($this->user);

        $asset = FixedAsset::create([
            'name'           => $this->product->name,
            'category'       => 'Computer & IT',
            'code_prefix'    => 'COMP',
            'total_quantity' => 2,
            'unit_cost'      => 500.0,
            'store_id'       => $this->store->id,
        ]);

        $unit = FixedAssetUnit::create([
            'fixed_asset_id'  => $asset->id,
            'unit_code'       => 'COMP-1',
            'sequence_number' => 1,
            'status'          => FixedAssetUnit::STATUS_IN_STORE,
            'created_by'      => $this->user->id,
        ]);

        $this->inventoryService->applyMovement($this->store->id, $this->product->id, 2.0, 'fa_register', performedBy: $this->user->id);

        $controller = new \App\Http\Controllers\FixedAssetController();
        $controller->destroyUnit($unit);

        // Unit record was preserved with status 'disposed'
        $this->assertEquals(FixedAssetUnit::STATUS_DISPOSED, $unit->fresh()->status);

        // Inventory on-hand decremented by 1
        $inv = Inventory::where('store_id', $this->store->id)->where('product_id', $this->product->id)->first();
        $this->assertEquals(1.0, (float) $inv->quantity_on_hand);

        $movement = InventoryMovement::where('inventory_id', $inv->id)->latest('id')->first();
        $this->assertEquals(-1.0, (float) $movement->quantity);
        $this->assertEquals('fa_disposed', $movement->type);
    }

    public function test_void_receive_slip_reverses_inventory_and_logs_reversal()
    {
        $this->actingAs($this->user);

        // Create receive slip with 10 units
        $slip = DeliveryReceipt::create([
            'dr_no'         => 'REC-001',
            'slip_type'     => 'receive',
            'store_id'      => $this->store->id,
            'received_date' => now(),
            'status'        => 'draft',
            'is_void'       => false,
        ]);
        $slip->items()->create([
            'product_id'        => $this->product->id,
            'quantity_received' => 10.0,
            'accepted_quantity' => 10.0,
            'unit'              => 'pcs',
        ]);

        // Stock in 10 units via applyMovement as storeSlip does
        $this->inventoryService->applyMovement(
            storeId: $this->store->id,
            productId: $this->product->id,
            quantityDelta: 10.0,
            type: 'slip_receive',
            referenceType: DeliveryReceipt::class,
            referenceId: $slip->id,
            remarks: 'Received via slip REC-001',
            performedBy: $this->user->id
        );

        $inv = Inventory::where('store_id', $this->store->id)->where('product_id', $this->product->id)->first();
        $this->assertEquals(10.0, (float) $inv->quantity_on_hand);

        // Void the slip
        $controller = new StoreManagerController();
        $controller->voidSlip($slip);

        $slip->refresh();
        $this->assertTrue((bool) $slip->is_void);
        $this->assertEquals('void', $slip->status);

        $inv->refresh();
        $this->assertEquals(0.0, (float) $inv->quantity_on_hand, 'Inventory must be reversed to 0 on voiding receive slip');

        $movement = InventoryMovement::where('inventory_id', $inv->id)->latest('id')->first();
        $this->assertEquals(-10.0, (float) $movement->quantity);
        $this->assertEquals(10.0, (float) $movement->balance_before);
        $this->assertEquals(0.0, (float) $movement->balance_after);
        $this->assertEquals('slip_void', $movement->type);
    }

    public function test_void_receive_slip_blocked_if_insufficient_stock()
    {
        $this->actingAs($this->user);

        $slip = DeliveryReceipt::create([
            'dr_no'         => 'REC-002',
            'slip_type'     => 'receive',
            'store_id'      => $this->store->id,
            'received_date' => now(),
            'status'        => 'draft',
            'is_void'       => false,
        ]);
        $slip->items()->create([
            'product_id'        => $this->product->id,
            'quantity_received' => 5.0,
            'accepted_quantity' => 5.0,
            'unit'              => 'pcs',
        ]);

        // 5 received, but 4 are reserved to employee (available = 1)
        $this->inventoryService->applyMovement(
            storeId: $this->store->id,
            productId: $this->product->id,
            quantityDelta: 5.0,
            type: 'slip_receive',
            referenceType: DeliveryReceipt::class,
            referenceId: $slip->id,
            performedBy: $this->user->id,
            reservedDelta: 4.0
        );

        $controller = new StoreManagerController();
        $controller->voidSlip($slip);

        $slip->refresh();
        $this->assertFalse((bool) $slip->is_void, 'Slip must NOT be voided if insufficient stock to deduct');

        $inv = Inventory::where('store_id', $this->store->id)->where('product_id', $this->product->id)->first();
        $this->assertEquals(5.0, (float) $inv->quantity_on_hand);
    }

    public function test_send_slip_deducts_origin_and_adds_destination()
    {
        $this->actingAs($this->user);

        $storeB = Store::create(['name' => 'Store B', 'is_active' => true]);

        // Store A starts with 15 units
        $this->inventoryService->applyMovement(
            storeId: $this->store->id,
            productId: $this->product->id,
            quantityDelta: 15.0,
            type: 'in',
            performedBy: $this->user->id
        );

        // Sequence for auto slip number
        SlipSequence::create([
            'store_id'        => $this->store->id,
            'slip_type'       => 'send',
            'prefix'          => 'SND-',
            'current_slip_no' => 1,
            'status'          => 'active',
        ]);

        $controller = new StoreManagerController();
        $request = Request::create('/store-manager/slips', 'POST', [
            'slip_type'   => 'send',
            'store_id'    => $this->store->id,
            'to_store_id' => $storeB->id,
            'slip_date'   => now()->toDateString(),
            'items'       => [
                [
                    'product_id' => $this->product->id,
                    'quantity'   => 5.0,
                ]
            ],
        ]);

        $controller->storeSlip($request);

        // Store A should have 10 on hand
        $invA = Inventory::where('store_id', $this->store->id)->where('product_id', $this->product->id)->first();
        $this->assertEquals(10.0, (float) $invA->quantity_on_hand);

        $movA = InventoryMovement::where('inventory_id', $invA->id)->latest('id')->first();
        $this->assertEquals(-5.0, (float) $movA->quantity);
        $this->assertEquals('slip_send', $movA->type);

        // Store B should have 5 on hand
        $invB = Inventory::where('store_id', $storeB->id)->where('product_id', $this->product->id)->first();
        $this->assertEquals(5.0, (float) $invB->quantity_on_hand);

        $movB = InventoryMovement::where('inventory_id', $invB->id)->latest('id')->first();
        $this->assertEquals(5.0, (float) $movB->quantity);
        $this->assertEquals('slip_receive', $movB->type);
    }

    public function test_void_send_slip_reverses_both_stores()
    {
        $this->actingAs($this->user);

        $storeB = Store::create(['name' => 'Store B', 'is_active' => true]);

        // Store A has 10, Store B has 5 from a send slip
        $slip = DeliveryReceipt::create([
            'dr_no'         => 'SND-001',
            'slip_type'     => 'send',
            'store_id'      => $this->store->id,
            'to_store_id'   => $storeB->id,
            'received_date' => now(),
            'status'        => 'draft',
            'is_void'       => false,
        ]);
        $slip->items()->create([
            'product_id'        => $this->product->id,
            'quantity_received' => 5.0,
            'accepted_quantity' => 5.0,
            'unit'              => 'pcs',
        ]);

        // Simulate initial balances: Store A was at 15 -> 10, Store B was at 0 -> 5
        $this->inventoryService->applyMovement(
            storeId: $this->store->id,
            productId: $this->product->id,
            quantityDelta: 10.0,
            type: 'in',
            performedBy: $this->user->id
        );
        $this->inventoryService->applyMovement(
            storeId: $storeB->id,
            productId: $this->product->id,
            quantityDelta: 5.0,
            type: 'slip_receive',
            referenceType: DeliveryReceipt::class,
            referenceId: $slip->id,
            performedBy: $this->user->id
        );

        $controller = new StoreManagerController();
        $controller->voidSlip($slip);

        $slip->refresh();
        $this->assertTrue((bool) $slip->is_void);

        // Store A should have +5 restored -> 15
        $invA = Inventory::where('store_id', $this->store->id)->where('product_id', $this->product->id)->first();
        $this->assertEquals(15.0, (float) $invA->quantity_on_hand);

        // Store B should have -5 deducted -> 0
        $invB = Inventory::where('store_id', $storeB->id)->where('product_id', $this->product->id)->first();
        $this->assertEquals(0.0, (float) $invB->quantity_on_hand);
    }

    public function test_sync_transfer_inventory_has_correct_negative_sign_on_reversal()
    {
        $this->actingAs($this->user);

        $storeB = Store::create(['name' => 'Store B', 'is_active' => true]);

        // Origin Store has 20 units initially
        $this->inventoryService->applyMovement(
            storeId: $this->store->id,
            productId: $this->product->id,
            quantityDelta: 20.0,
            type: 'in',
            performedBy: $this->user->id
        );

        $transfer = Transfer::create([
            'transfer_no'   => 'TR-001',
            'from_store_id' => $this->store->id,
            'to_store_id'   => $storeB->id,
            'status'        => 'completed',
        ]);

        $item = $transfer->items()->create([
            'product_id'         => $this->product->id,
            'requested_quantity' => 10.0,
            'approved_quantity'  => 10.0,
            'sent_quantity'      => 10.0,
            'received_quantity'  => 10.0,
            'unit'               => 'pcs',
        ]);

        // Sync initial transfer of 10
        StoreManagerController::syncTransferInventory($transfer);

        $invB = Inventory::where('store_id', $storeB->id)->where('product_id', $this->product->id)->first();
        $this->assertEquals(10.0, (float) $invB->quantity_on_hand);

        // Now suppose excess: transfer received is reduced from 10 to 8 (2 units excess to reverse)
        $item->update(['received_quantity' => 8.0]);

        StoreManagerController::syncTransferInventory($transfer);

        $invB->refresh();
        $this->assertEquals(8.0, (float) $invB->quantity_on_hand);

        // Reversal movement in destination store MUST have negative quantity (-2.0)
        $reversalMov = InventoryMovement::where('inventory_id', $invB->id)
            ->where('type', 'transfer_reversal')
            ->first();

        $this->assertNotNull($reversalMov);
        $this->assertEquals(-2.0, (float) $reversalMov->quantity, 'Reversal movement quantity MUST be negative -2, NOT positive +2');
        $this->assertEquals(10.0, (float) $reversalMov->balance_before);
        $this->assertEquals(8.0, (float) $reversalMov->balance_after);
    }

    public function test_issue_material_request_blocks_negative_stock()
    {
        $this->actingAs($this->user);

        // Store has 3 units
        $this->inventoryService->applyMovement(
            storeId: $this->store->id,
            productId: $this->product->id,
            quantityDelta: 3.0,
            type: 'in',
            performedBy: $this->user->id
        );

        // Material request asks for 5 units
        $mr = MaterialRequest::create([
            'destination_store_id' => $this->store->id,
            'reference_number'     => 'MR-001',
            'status'               => 'pending',
            'created_by'           => $this->user->id,
        ]);
        $mr->items()->create([
            'product_id'         => $this->product->id,
            'quantity_requested' => 5.0,
        ]);

        $controller = new StoreManagerController();
        $request = Request::create("/store-manager/material-requests/{$mr->id}/issue", 'POST');

        $controller->issueMaterialRequest($request, $mr);

        $mr->refresh();
        $this->assertEquals('pending', $mr->status, 'Material request must NOT be issued if stock is insufficient');

        $inv = Inventory::where('store_id', $this->store->id)->where('product_id', $this->product->id)->first();
        $this->assertEquals(3.0, (float) $inv->quantity_on_hand, 'Inventory must remain 3 without going negative');
    }

    public function test_issue_material_request_succeeds_when_stock_available()
    {
        $this->actingAs($this->user);

        // Store has 10 units
        $this->inventoryService->applyMovement(
            storeId: $this->store->id,
            productId: $this->product->id,
            quantityDelta: 10.0,
            type: 'in',
            performedBy: $this->user->id
        );

        // Material request asks for 4 units
        $mr = MaterialRequest::create([
            'destination_store_id' => $this->store->id,
            'reference_number'     => 'MR-002',
            'status'               => 'pending',
            'created_by'           => $this->user->id,
        ]);
        $mr->items()->create([
            'product_id'         => $this->product->id,
            'quantity_requested' => 4.0,
        ]);

        $controller = new StoreManagerController();
        $request = Request::create("/store-manager/material-requests/{$mr->id}/issue", 'POST');

        $controller->issueMaterialRequest($request, $mr);

        $mr->refresh();
        $this->assertEquals('issued', $mr->status);

        $inv = Inventory::where('store_id', $this->store->id)->where('product_id', $this->product->id)->first();
        $this->assertEquals(6.0, (float) $inv->quantity_on_hand);

        $mov = InventoryMovement::where('inventory_id', $inv->id)->latest('id')->first();
        $this->assertEquals(-4.0, (float) $mov->quantity);
        $this->assertEquals(10.0, (float) $mov->balance_before);
        $this->assertEquals(6.0, (float) $mov->balance_after);
        $this->assertEquals('issue', $mov->type);
    }

    public function test_reconcile_dry_run_identifies_ledger_drift_without_modifying_data()
    {
        $this->actingAs($this->user);

        // Inventory on-hand is 10, but ledger sum is only 7 (drift of +3)
        $inv = Inventory::create([
            'store_id'         => $this->store->id,
            'product_id'       => $this->product->id,
            'quantity_on_hand' => 10.0,
            'quantity_reserved'=> 0.0,
        ]);

        InventoryMovement::create([
            'inventory_id'   => $inv->id,
            'type'           => 'in',
            'quantity'       => 7.0,
            'balance_before' => 0.0,
            'balance_after'  => 7.0,
            'performed_by'   => $this->user->id,
        ]);

        $controller = new StoreManagerController();
        $request = Request::create('/store-manager/inventory/reconciliation', 'GET');
        $view = $controller->reconciliationIndex($request);

        $reportData = $view->getData()['report'];
        $this->assertNotEmpty($reportData);

        $first = $reportData[0];
        $this->assertEquals(10.0, $first['on_hand']);
        $this->assertEquals(7.0, $first['ledger_sum']);
        $this->assertEquals(3.0, $first['ledger_diff']);
        $this->assertTrue($first['has_ledger_drift']);

        // Dry run must NOT alter data
        $this->assertEquals(10.0, (float) $inv->fresh()->quantity_on_hand);
        $this->assertEquals(1, InventoryMovement::where('inventory_id', $inv->id)->count());
    }

    public function test_reconcile_detects_fixed_asset_unit_mismatch()
    {
        $this->actingAs($this->user);

        // Fixed Asset with 2 unit records
        $asset = FixedAsset::create([
            'name'           => $this->product->name,
            'category'       => 'Computer & IT',
            'code_prefix'    => 'COMP',
            'total_quantity' => 2,
            'unit_cost'      => 500.0,
            'store_id'       => $this->store->id,
        ]);
        FixedAssetUnit::create([
            'fixed_asset_id'  => $asset->id,
            'unit_code'       => 'COMP-1',
            'sequence_number' => 1,
            'status'          => FixedAssetUnit::STATUS_IN_STORE,
        ]);
        FixedAssetUnit::create([
            'fixed_asset_id'  => $asset->id,
            'unit_code'       => 'COMP-2',
            'sequence_number' => 2,
            'status'          => FixedAssetUnit::STATUS_IN_STORE,
        ]);

        // But inventory on-hand is 4
        $inv = Inventory::create([
            'store_id'         => $this->store->id,
            'product_id'       => $this->product->id,
            'quantity_on_hand' => 4.0,
            'quantity_reserved'=> 0.0,
        ]);

        $controller = new StoreManagerController();
        $request = Request::create('/store-manager/inventory/reconciliation', 'GET');
        $view = $controller->reconciliationIndex($request);

        $reportData = $view->getData()['report'];
        $first = $reportData[0];

        $this->assertTrue($first['is_fa']);
        $this->assertEquals(2, $first['active_units']);
        $this->assertEquals(4.0, $first['on_hand']);
        $this->assertEquals(2, $first['unit_diff']);
        $this->assertTrue($first['has_unit_mismatch']);
    }

    public function test_reconcile_repair_balances_ledger_drift()
    {
        $this->actingAs($this->user);

        // On hand is 10, ledger sum is 8 (diff +2)
        $inv = Inventory::create([
            'store_id'         => $this->store->id,
            'product_id'       => $this->product->id,
            'quantity_on_hand' => 10.0,
            'quantity_reserved'=> 0.0,
        ]);
        InventoryMovement::create([
            'inventory_id'   => $inv->id,
            'type'           => 'in',
            'quantity'       => 8.0,
            'balance_before' => 0.0,
            'balance_after'  => 8.0,
            'performed_by'   => $this->user->id,
        ]);

        $controller = new StoreManagerController();
        $request = Request::create('/store-manager/inventory/reconciliation/repair', 'POST', [
            'inventory_id' => $inv->id,
            'action'       => 'balance_ledger',
        ]);

        $controller->reconciliationRepair($request);

        // Balancing movement row was created
        $movements = InventoryMovement::where('inventory_id', $inv->id)->orderBy('id')->get();
        $this->assertCount(2, $movements);

        $balancingMov = $movements->last();
        $this->assertEquals('reconciliation', $balancingMov->type);
        $this->assertEquals(2.0, (float) $balancingMov->quantity);
        $this->assertEquals(8.0, (float) $balancingMov->balance_before);
        $this->assertEquals(10.0, (float) $balancingMov->balance_after);

        // Total ledger sum now equals on-hand
        $newLedgerSum = (float) InventoryMovement::where('inventory_id', $inv->id)->sum('quantity');
        $this->assertEquals(10.0, $newLedgerSum);
    }

    public function test_reconcile_repair_synchronizes_fixed_asset_units()
    {
        $this->actingAs($this->user);

        $asset = FixedAsset::create([
            'name'           => $this->product->name,
            'category'       => 'Computer & IT',
            'code_prefix'    => 'COMP',
            'total_quantity' => 2,
            'unit_cost'      => 500.0,
            'store_id'       => $this->store->id,
        ]);
        FixedAssetUnit::create([
            'fixed_asset_id'  => $asset->id,
            'unit_code'       => 'COMP-1',
            'sequence_number' => 1,
            'status'          => FixedAssetUnit::STATUS_IN_STORE,
        ]);
        FixedAssetUnit::create([
            'fixed_asset_id'  => $asset->id,
            'unit_code'       => 'COMP-2',
            'sequence_number' => 2,
            'status'          => FixedAssetUnit::STATUS_IN_STORE,
        ]);

        // Inventory is 4 on hand
        $inv = Inventory::create([
            'store_id'         => $this->store->id,
            'product_id'       => $this->product->id,
            'quantity_on_hand' => 4.0,
        ]);

        $controller = new StoreManagerController();
        $request = Request::create('/store-manager/inventory/reconciliation/repair', 'POST', [
            'inventory_id' => $inv->id,
            'action'       => 'sync_units',
        ]);

        $controller->reconciliationRepair($request);

        // 2 new units were created
        $allUnits = FixedAssetUnit::where('fixed_asset_id', $asset->id)->get();
        $this->assertCount(4, $allUnits);

        $newUnits = $allUnits->where('sequence_number', '>', 2);
        foreach ($newUnits as $nu) {
            $this->assertStringContainsString('Details incomplete', $nu->notes);
            $this->assertEquals(FixedAssetUnit::STATUS_IN_STORE, $nu->status);
        }
    }

    public function test_reconcile_repair_with_custom_count()
    {
        $this->actingAs($this->user);

        $inv = Inventory::create([
            'store_id'         => $this->store->id,
            'product_id'       => $this->product->id,
            'quantity_on_hand' => 10.0,
        ]);

        $controller = new StoreManagerController();
        $request = Request::create('/store-manager/inventory/reconciliation/repair', 'POST', [
            'inventory_id' => $inv->id,
            'action'       => 'custom_stock',
            'custom_count' => 13.0,
        ]);

        $controller->reconciliationRepair($request);

        $this->assertEquals(13.0, (float) $inv->fresh()->quantity_on_hand);

        $mov = InventoryMovement::where('inventory_id', $inv->id)->latest('id')->first();
        $this->assertEquals(3.0, (float) $mov->quantity);
        $this->assertEquals(10.0, (float) $mov->balance_before);
        $this->assertEquals(13.0, (float) $mov->balance_after);
        $this->assertEquals('reconciliation', $mov->type);
    }

    public function test_comprehensive_end_to_end_single_source_of_truth_workflow()
    {
        $this->actingAs($this->user);

        // 1. Initial intake: 10 units via applyMovement
        $this->inventoryService->applyMovement(
            storeId: $this->store->id,
            productId: $this->product->id,
            quantityDelta: 10.0,
            type: 'in',
            performedBy: $this->user->id
        );
        $inv = Inventory::where('store_id', $this->store->id)->where('product_id', $this->product->id)->first();
        $this->assertEquals(10.0, (float) $inv->quantity_on_hand);
        $this->assertEquals(0.0, (float) $inv->quantity_reserved);

        // 2. Fixed Asset registered: total_quantity is 10, units created
        $asset = FixedAsset::create([
            'name'           => $this->product->name,
            'category'       => 'Computer & IT',
            'code_prefix'    => 'COMP',
            'total_quantity' => 10,
            'store_id'       => $this->store->id,
        ]);
        for ($i = 1; $i <= 10; $i++) {
            FixedAssetUnit::create([
                'fixed_asset_id'  => $asset->id,
                'unit_code'       => "COMP-{$i}",
                'sequence_number' => $i,
                'status'          => FixedAssetUnit::STATUS_IN_STORE,
            ]);
        }

        // 3. Asset unit assigned to employee -> on-hand is 10, reserved is 1, available is 9
        $unit1 = FixedAssetUnit::where('unit_code', 'COMP-1')->first();
        $employee = Employee::create(['full_name' => 'Alice']);
        $unit1->assignToEmployee($employee->id, $this->user->id);

        $inv->refresh();
        $this->assertEquals(10.0, (float) $inv->quantity_on_hand);
        $this->assertEquals(1.0, (float) $inv->quantity_reserved);

        // 4. Protection: attempting to deduct 10 (when available is 9) must fail
        try {
            $this->inventoryService->applyMovement(
                storeId: $this->store->id,
                productId: $this->product->id,
                quantityDelta: -10.0,
                type: 'out',
                performedBy: $this->user->id
            );
            $this->fail('Expected InvalidArgumentException when deducting beyond available stock');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('only 9 available, 1 are currently assigned', $e->getMessage());
        }

        // 5. Deducting within available stock: deduct 2 -> on-hand becomes 8, reserved remains 1
        $this->inventoryService->applyMovement(
            storeId: $this->store->id,
            productId: $this->product->id,
            quantityDelta: -2.0,
            type: 'adjustment',
            performedBy: $this->user->id
        );
        $inv->refresh();
        $this->assertEquals(8.0, (float) $inv->quantity_on_hand);
        $this->assertEquals(1.0, (float) $inv->quantity_reserved);

        // 6. Cyclic sync attempt: asset sync does NOT overwrite inventory balance back to 10
        $asset->syncWithCatalogAndInventory();
        $inv->refresh();
        $this->assertEquals(8.0, (float) $inv->quantity_on_hand, 'Inventory master MUST NOT be reverted by FixedAsset sync');

        // 7. Non-destructive unit disposal on negative adjustment: units are marked disposed, never deleted
        $this->assertEquals(10, FixedAssetUnit::where('fixed_asset_id', $asset->id)->count(), 'Unit rows must never be deleted');
        $disposedCount = FixedAssetUnit::where('fixed_asset_id', $asset->id)->where('status', FixedAssetUnit::STATUS_DISPOSED)->count();
        $this->assertEquals(2, $disposedCount);

        // 8. Return damaged: unit returned damaged -> on-hand drops to 7, reserved drops to 0
        $unit1->returnToStore($this->user->id, 'Damaged during use', 'damaged');
        $inv->refresh();
        $this->assertEquals(7.0, (float) $inv->quantity_on_hand);
        $this->assertEquals(0.0, (float) $inv->quantity_reserved);
        $this->assertEquals(FixedAssetUnit::STATUS_MAINTENANCE, $unit1->fresh()->status);

        // 9. Append-only ledger verification: updating or deleting a movement throws RuntimeException
        $anyMovement = InventoryMovement::first();
        try {
            $anyMovement->update(['quantity' => 999]);
            $this->fail('Expected RuntimeException on ledger update');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
        }

        try {
            $anyMovement->delete();
            $this->fail('Expected RuntimeException on ledger delete');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
        }

        // 10. Ledger sum perfectly matches final inventory on-hand
        $totalLedgerSum = (float) InventoryMovement::where('inventory_id', $inv->id)->sum('quantity');
        $this->assertEquals(7.0, $totalLedgerSum, 'Total movement ledger sum must strictly match final inventory on-hand (7.0)');
    }
}
