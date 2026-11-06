<?php

namespace Tests\Unit;

use App\Http\Controllers\PurchaseRequestController;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Store;
use App\Models\User;
use App\Services\ProcurementLifecycleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SelectiveTransferStockValidationTest extends TestCase
{
    protected Store $storeOrigin;
    protected Store $storeDest;
    protected Store $storeAlternative;
    protected Product $product;
    protected PurchaseRequest $pr;
    protected PurchaseRequestItem $prItem;

    protected function setUp(): void
    {
        parent::setUp();

        DB::statement('PRAGMA foreign_keys = OFF;');
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory');
        Schema::dropIfExists('transfer_items');
        Schema::dropIfExists('transfers');
        Schema::dropIfExists('purchase_request_items');
        Schema::dropIfExists('pr_workflow_logs');
        Schema::dropIfExists('purchase_requests');
        Schema::dropIfExists('products');
        Schema::dropIfExists('stores');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('roles');
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
            $table->string('password')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('stores', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('products', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->decimal('unit_price', 15, 2)->default(100.00);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('purchase_requests', function ($table) {
            $table->id();
            $table->string('pr_no')->unique();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->date('required_date')->nullable();
            $table->string('status')->default(PurchaseRequest::STATUS_PENDING_STORE_REVIEW);
            $table->string('current_owner_role')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('purchase_request_items', function ($table) {
            $table->id();
            $table->unsignedBigInteger('purchase_request_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->decimal('quantity', 15, 3)->default(1);
            $table->string('unit')->default('pcs');
            $table->decimal('estimated_unit_cost', 15, 2)->default(0);
            $table->decimal('purchased_quantity', 15, 3)->default(0);
            $table->decimal('received_quantity', 15, 3)->default(0);
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
        });

        Schema::create('transfers', function ($table) {
            $table->id();
            $table->string('transfer_no')->unique();
            $table->unsignedBigInteger('from_store_id');
            $table->unsignedBigInteger('to_store_id');
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->date('required_date')->nullable();
            $table->text('reason')->nullable();
            $table->string('status')->default('draft');
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
            $table->timestamps();
        });

        Schema::create('inventory_movements', function ($table) {
            $table->id();
            $table->unsignedBigInteger('inventory_id');
            $table->string('type');
            $table->decimal('quantity', 15, 3);
            $table->decimal('balance_before', 15, 3);
            $table->decimal('balance_after', 15, 3);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->unsignedBigInteger('performed_by')->default(1);
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('pr_workflow_logs', function ($table) {
            $table->id();
            $table->unsignedBigInteger('purchase_request_id');
            $table->string('from_stage')->nullable();
            $table->string('to_stage')->nullable();
            $table->string('action');
            $table->string('actor_role');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        $role = \Spatie\Permission\Models\Role::create(['name' => 'store_manager', 'guard_name' => 'web']);
        $user = User::create(['name' => 'Store Manager', 'email' => 'sm@example.com']);
        $user->assignRole($role);
        Auth::login($user);

        $this->storeOrigin = Store::create(['name' => 'Site Store', 'code' => 'SS01']);
        $this->storeDest = Store::create(['name' => 'Destination Store', 'code' => 'DS01']);
        $this->storeAlternative = Store::create(['name' => 'Central Warehouse', 'code' => 'CW01']);

        $this->product = Product::create(['name' => 'Reinforcement Bar 16mm', 'sku' => 'RB-16']);

        $this->pr = PurchaseRequest::create([
            'pr_no' => 'PR-2026-00239',
            'store_id' => $this->storeDest->id,
            'status' => PurchaseRequest::STATUS_PENDING_STORE_REVIEW,
            'current_owner_role' => 'store_manager',
        ]);

        $this->prItem = PurchaseRequestItem::create([
            'purchase_request_id' => $this->pr->id,
            'product_id' => $this->product->id,
            'quantity' => 4.0,
            'unit' => 'pcs',
        ]);
    }

    public function test_selective_transfer_deducts_up_to_zero_when_stock_is_negative()
    {
        // Setup negative stock (-6.0) in origin store matching user scenario
        Inventory::create([
            'store_id' => $this->storeOrigin->id,
            'product_id' => $this->product->id,
            'quantity_on_hand' => -6.0,
            'quantity_reserved' => 0.0,
        ]);

        $controller = app(PurchaseRequestController::class);

        $request = Request::create(
            "/purchase-requests/{$this->pr->id}/selective-transfer",
            'POST',
            [
                'from_store_id' => $this->storeOrigin->id,
                'to_store_id'   => $this->storeDest->id,
                'items'         => [
                    [
                        'item_id'  => $this->prItem->id,
                        'quantity' => 4.0,
                    ],
                ],
            ]
        );

        $response = $controller->selectiveTransfer($request, $this->pr);

        $this->assertTrue($response->isRedirection());
        $this->assertEquals(1, \App\Models\Transfer::count());

        // Origin store was -6.0, now deducted up to zero (never negative!)
        $originInv = Inventory::where('store_id', $this->storeOrigin->id)->where('product_id', $this->product->id)->first();
        $this->assertEquals(0.0, (float)$originInv->quantity_on_hand);

        // Destination store received the 4 units
        $destInv = Inventory::where('store_id', $this->storeDest->id)->where('product_id', $this->product->id)->first();
        $this->assertEquals(4.0, (float)$destInv->quantity_on_hand);
    }

    public function test_selective_transfer_deducts_up_to_zero_when_stock_is_insufficient()
    {
        // Setup on-hand = 2.0 (less than requested 4.0)
        Inventory::create([
            'store_id' => $this->storeOrigin->id,
            'product_id' => $this->product->id,
            'quantity_on_hand' => 2.0,
            'quantity_reserved' => 0.0,
        ]);

        $controller = app(PurchaseRequestController::class);

        $request = Request::create(
            "/purchase-requests/{$this->pr->id}/selective-transfer",
            'POST',
            [
                'from_store_id' => $this->storeOrigin->id,
                'to_store_id'   => $this->storeDest->id,
                'items'         => [
                    [
                        'item_id'  => $this->prItem->id,
                        'quantity' => 4.0,
                    ],
                ],
            ]
        );

        $response = $controller->selectiveTransfer($request, $this->pr);

        $this->assertTrue($response->isRedirection());
        $this->assertEquals(1, \App\Models\Transfer::count());

        // Origin on-hand 2.0 deducted up to zero (0.0, never negative!)
        $originInv = Inventory::where('store_id', $this->storeOrigin->id)->where('product_id', $this->product->id)->first();
        $this->assertEquals(0.0, (float)$originInv->quantity_on_hand);

        // Destination store received 4.0
        $destInv = Inventory::where('store_id', $this->storeDest->id)->where('product_id', $this->product->id)->first();
        $this->assertEquals(4.0, (float)$destInv->quantity_on_hand);
    }

    public function test_selective_transfer_succeeds_when_stock_is_sufficient()
    {
        // Setup sufficient stock (10.0) in origin store
        Inventory::create([
            'store_id' => $this->storeOrigin->id,
            'product_id' => $this->product->id,
            'quantity_on_hand' => 10.0,
            'quantity_reserved' => 0.0,
        ]);

        $controller = app(PurchaseRequestController::class);

        $request = Request::create(
            "/purchase-requests/{$this->pr->id}/selective-transfer",
            'POST',
            [
                'from_store_id' => $this->storeOrigin->id,
                'to_store_id'   => $this->storeDest->id,
                'items'         => [
                    [
                        'item_id'  => $this->prItem->id,
                        'quantity' => 4.0,
                    ],
                ],
            ]
        );

        $response = $controller->selectiveTransfer($request, $this->pr);

        $this->assertTrue($response->isRedirection());
        $session = session()->all();
        $this->assertEquals(1, \App\Models\Transfer::count());

        $transfer = \App\Models\Transfer::first();
        $this->assertEquals($this->storeOrigin->id, $transfer->from_store_id);
        $this->assertEquals($this->storeDest->id, $transfer->to_store_id);

        // Origin store deducted by 4: 10 - 4 = 6
        $originInv = Inventory::where('store_id', $this->storeOrigin->id)->where('product_id', $this->product->id)->first();
        $this->assertEquals(6.0, (float)$originInv->quantity_on_hand);
    }

    public function test_transfer_controller_store_deducts_up_to_zero()
    {
        // Setup only 1 unit on hand
        Inventory::create([
            'store_id' => $this->storeOrigin->id,
            'product_id' => $this->product->id,
            'quantity_on_hand' => 1.0,
            'quantity_reserved' => 0.0,
        ]);

        $controller = app(\App\Http\Controllers\TransferController::class);

        $request = Request::create(
            '/transfers',
            'POST',
            [
                'from_store_id' => $this->storeOrigin->id,
                'to_store_id'   => $this->storeDest->id,
                'items'         => [
                    [
                        'product_id' => $this->product->id,
                        'quantity'   => 5.0,
                        'unit'       => 'pcs',
                    ],
                ],
            ]
        );

        $response = $controller->store($request);

        $this->assertTrue($response->isRedirection());
        $this->assertEquals(1, \App\Models\Transfer::count());

        // Origin on-hand 1.0 deducted up to zero (0.0, never negative!)
        $originInv = Inventory::where('store_id', $this->storeOrigin->id)->where('product_id', $this->product->id)->first();
        $this->assertEquals(0.0, (float)$originInv->quantity_on_hand);

        // Destination store received 5.0
        $destInv = Inventory::where('store_id', $this->storeDest->id)->where('product_id', $this->product->id)->first();
        $this->assertEquals(5.0, (float)$destInv->quantity_on_hand);
    }
}
