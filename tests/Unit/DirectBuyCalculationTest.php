<?php

namespace Tests\Unit;

use App\Http\Controllers\PurchaseRequestController;
use App\Models\Product;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\User;
use App\Services\ProcurementLifecycleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DirectBuyCalculationTest extends TestCase
{
    protected ProcurementLifecycleService $lifecycle;

    protected function setUp(): void
    {
        parent::setUp();

        DB::statement('PRAGMA foreign_keys = OFF;');

        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('purchase_request_items');
        Schema::dropIfExists('purchase_requests');
        Schema::dropIfExists('products');
        Schema::dropIfExists('users');
        Schema::dropIfExists('pr_workflow_logs');
        Schema::dropIfExists('procurement_sms_logs');

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
            $table->timestamps();
        });

        Schema::create('products', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('unit')->default('pcs');
            $table->timestamps();
        });

        Schema::create('purchase_requests', function ($table) {
            $table->id();
            $table->string('pr_no')->unique();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->unsignedBigInteger('direct_buy_added_by')->nullable();
            $table->string('sourcing_method')->nullable();
            $table->decimal('direct_buy_amount', 15, 2)->nullable();
            $table->decimal('estimated_total', 15, 2)->nullable();
            $table->text('procurement_team_notes')->nullable();
            $table->string('status')->default('draft');
            $table->string('current_owner_role')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('purchase_request_items', function ($table) {
            $table->id();
            $table->unsignedBigInteger('purchase_request_id');
            $table->unsignedBigInteger('product_id');
            $table->decimal('quantity', 15, 3)->default(1);
            $table->decimal('purchased_quantity', 15, 3)->nullable();
            $table->decimal('received_quantity', 15, 3)->nullable();
            $table->string('unit', 20)->default('pcs');
            $table->text('specifications')->nullable();
            $table->decimal('estimated_unit_cost', 15, 2)->nullable();
            $table->decimal('estimated_total', 15, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('pr_workflow_logs', function ($table) {
            $table->id();
            $table->unsignedBigInteger('purchase_request_id');
            $table->string('from_stage')->nullable();
            $table->string('to_stage')->nullable();
            $table->string('action');
            $table->string('actor_role')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('procurement_sms_logs', function ($table) {
            $table->id();
            $table->unsignedBigInteger('purchase_request_id')->nullable();
            $table->string('phone')->nullable();
            $table->text('message')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });

        DB::statement('PRAGMA foreign_keys = ON;');

        $this->lifecycle = app(ProcurementLifecycleService::class);
    }

    public function test_submit_direct_buy_calculates_line_totals_and_exact_sum()
    {
        $user = User::create(['name' => 'Procurement Officer', 'email' => 'purchaser@wechecha.com']);
        Auth::login($user);

        $prod1 = Product::create(['name' => 'Cement 42.5N', 'unit' => 'bags']);
        $prod2 = Product::create(['name' => 'River Sand', 'unit' => 'm3']);

        $pr = PurchaseRequest::create([
            'pr_no'            => 'PR-DIR-001',
            'status'           => PurchaseRequest::STATUS_PENDING_PROC_TEAM,
            'sourcing_method'  => 'direct_buy',
            'estimated_total'  => 99999.00, // Stale old estimate
        ]);

        $item1 = PurchaseRequestItem::create([
            'purchase_request_id' => $pr->id,
            'product_id'          => $prod1->id,
            'quantity'            => 50,
            'unit'                => 'bags',
        ]);

        $item2 = PurchaseRequestItem::create([
            'purchase_request_id' => $pr->id,
            'product_id'          => $prod2->id,
            'quantity'            => 10,
            'unit'                => 'm3',
        ]);

        // Purchaser enters unit prices: Cement @ 1000 ETB, Sand @ 500 ETB
        // Expected Cement: 50 * 1000 = 50,000 ETB
        // Expected Sand: 10 * 500 = 5,000 ETB
        // Expected Direct Buy Total: 55,000 ETB
        $itemPrices = [
            $item1->id => 1000.00,
            $item2->id => 500.00,
        ];

        $this->lifecycle->submitDirectBuy($pr, 0.0, 'Purchased from local market', $itemPrices, false);

        $pr->refresh();
        $item1->refresh();
        $item2->refresh();

        $this->assertEquals(50000.00, (float)$item1->estimated_total);
        $this->assertEquals(1000.00, (float)$item1->estimated_unit_cost);

        $this->assertEquals(5000.00, (float)$item2->estimated_total);
        $this->assertEquals(500.00, (float)$item2->estimated_unit_cost);

        $this->assertEquals(55000.00, (float)$pr->direct_buy_amount);
        $this->assertEquals(PurchaseRequest::STATUS_PENDING_MARKETING, $pr->status);
    }

    public function test_submit_direct_buy_with_vat_adds_15_percent()
    {
        $user = User::create(['name' => 'Procurement Officer', 'email' => 'purchaser2@wechecha.com']);
        Auth::login($user);

        $prod = Product::create(['name' => 'Paint 20L', 'unit' => 'can']);

        $pr = PurchaseRequest::create([
            'pr_no'            => 'PR-DIR-002',
            'status'           => PurchaseRequest::STATUS_PENDING_PROC_TEAM,
            'sourcing_method'  => 'direct_buy',
        ]);

        $item = PurchaseRequestItem::create([
            'purchase_request_id' => $pr->id,
            'product_id'          => $prod->id,
            'quantity'            => 10,
            'unit'                => 'can',
        ]);

        // 10 cans @ 2000 ETB = 20,000 ETB subtotal
        // With 15% VAT: 20,000 * 1.15 = 23,000 ETB
        $itemPrices = [$item->id => 2000.00];

        $this->lifecycle->submitDirectBuy($pr, 0.0, 'Including 15% VAT', $itemPrices, true);

        $pr->refresh();
        $this->assertEquals(23000.00, (float)$pr->direct_buy_amount);
    }

    public function test_submit_direct_buy_respects_remaining_quantity_if_partially_purchased()
    {
        $user = User::create(['name' => 'Procurement Officer', 'email' => 'purchaser3@wechecha.com']);
        Auth::login($user);

        $prod = Product::create(['name' => 'Safety Gloves', 'unit' => 'pairs']);

        $pr = PurchaseRequest::create([
            'pr_no'            => 'PR-DIR-003',
            'status'           => PurchaseRequest::STATUS_PENDING_PROC_TEAM,
            'sourcing_method'  => 'direct_buy',
        ]);

        // Requested 100 pairs, but 60 were already purchased previously
        $item = PurchaseRequestItem::create([
            'purchase_request_id' => $pr->id,
            'product_id'          => $prod->id,
            'quantity'            => 100,
            'purchased_quantity'  => 60,
            'unit'                => 'pairs',
        ]);

        // Remaining quantity to buy is 40 pairs @ 150 ETB = 6,000 ETB (NOT 100 * 150 = 15,000)
        $itemPrices = [$item->id => 150.00];

        $this->lifecycle->submitDirectBuy($pr, 0.0, 'Purchasing remaining 40 pairs', $itemPrices, false);

        $pr->refresh();
        $item->refresh();

        $this->assertEquals(15000.00, (float)$item->estimated_total);
        $this->assertEquals(6000.00, (float)$pr->direct_buy_amount);
    }

    public function test_controller_submit_direct_buy_overrides_stale_amount_with_exact_items_sum()
    {
        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'purchaser', 'guard_name' => 'web']);
        $user = User::create(['name' => 'Buyer Joe', 'email' => 'buyer_joe@wechecha.com']);
        $user->assignRole($role);
        Auth::login($user);

        $prod = Product::create(['name' => 'Plywood 18mm', 'unit' => 'sheet']);

        $pr = PurchaseRequest::create([
            'pr_no'            => 'PR-DIR-004',
            'status'           => PurchaseRequest::STATUS_PENDING_PROC_TEAM,
            'sourcing_method'  => 'direct_buy',
            'estimated_total'  => 88888.00,
        ]);

        $item = PurchaseRequestItem::create([
            'purchase_request_id' => $pr->id,
            'product_id'          => $prod->id,
            'quantity'            => 20,
            'unit'                => 'sheet',
        ]);

        $controller = app(PurchaseRequestController::class);

        // 20 sheets @ 500 ETB = 10,000 ETB. Stale amount passed from form: 88888.00
        $request = Request::create(route('purchase-requests.submit-direct-buy', $pr), 'POST', [
            'item_prices' => [$item->id => 500.00],
            'amount'      => 88888.00, // Stale amount submitted by old input
            'include_vat' => '0',
            'notes'       => 'Plywood bought at wood market',
        ]);
        app()->instance('request', $request);

        $response = $controller->submitDirectBuy($request, $pr);
        $this->assertEquals(302, $response->getStatusCode());

        $pr->refresh();
        $item->refresh();

        $this->assertEquals(10000.00, (float)$item->estimated_total);
        $this->assertEquals(500.00, (float)$item->estimated_unit_cost);
        $this->assertEquals(10000.00, (float)$pr->direct_buy_amount, 'Stale amount must be replaced with exact items calculation (10,000 ETB).');
    }

    public function test_submit_direct_buy_with_purchased_quantities_calculates_total_money_and_updates_items(): void
    {
        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'purchaser', 'guard_name' => 'web']);
        $user = User::create(['name' => 'Buyer Sara', 'email' => 'buyer_sara@wechecha.com']);
        $user->assignRole($role);
        Auth::login($user);

        $prod1 = Product::create(['name' => 'Cement 50kg', 'unit' => 'bag']);
        $prod2 = Product::create(['name' => 'Sand Coarse', 'unit' => 'm3']);

        $pr = PurchaseRequest::create([
            'pr_no'           => 'PR-DIR-005',
            'status'          => PurchaseRequest::STATUS_PENDING_PROC_TEAM,
            'sourcing_method' => 'direct_buy',
        ]);

        // Requested 100 bags, but purchaser bought 80 bags
        $item1 = PurchaseRequestItem::create([
            'purchase_request_id' => $pr->id,
            'product_id'          => $prod1->id,
            'quantity'            => 100,
            'purchased_quantity'  => null,
            'unit'                => 'bag',
        ]);

        // Requested 50 m3, purchaser bought 50 m3
        $item2 = PurchaseRequestItem::create([
            'purchase_request_id' => $pr->id,
            'product_id'          => $prod2->id,
            'quantity'            => 50,
            'purchased_quantity'  => null,
            'unit'                => 'm3',
        ]);

        $controller = app(PurchaseRequestController::class);

        // 80 bags @ 600 ETB = 48,000 ETB
        // 50 m3 @ 400 ETB = 20,000 ETB
        // Subtotal = 68,000 ETB
        // With 15% VAT = 68,000 * 1.15 = 78,200 ETB
        $request = Request::create(route('purchase-requests.submit-direct-buy', $pr), 'POST', [
            'item_prices'          => [
                $item1->id => 600.00,
                $item2->id => 400.00,
            ],
            'purchased_quantities' => [
                $item1->id => 80.00,
                $item2->id => 50.00,
            ],
            'amount'               => 0.00,
            'include_vat'          => '1',
            'notes'                => 'Procured materials with 15% VAT invoice',
        ]);
        app()->instance('request', $request);

        $response = $controller->submitDirectBuy($request, $pr);
        $this->assertEquals(302, $response->getStatusCode());

        $pr->refresh();
        $item1->refresh();
        $item2->refresh();

        // Check that purchased_quantity was saved on the items
        $this->assertEquals(80.00, (float)$item1->purchased_quantity);
        $this->assertEquals(600.00, (float)$item1->estimated_unit_cost);

        $this->assertEquals(50.00, (float)$item2->purchased_quantity);
        $this->assertEquals(400.00, (float)$item2->estimated_unit_cost);

        // Check that direct_buy_amount holds the total money including VAT (78,200 ETB)
        $this->assertEquals(78200.00, (float)$pr->direct_buy_amount);
        $this->assertEquals(PurchaseRequest::STATUS_PENDING_MARKETING, $pr->status);
    }
}
