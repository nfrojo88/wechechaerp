<?php

namespace Tests\Unit;

use App\Models\MaterialRequest;
use App\Models\MaterialRequestItem;
use App\Models\PrWorkflowLog;
use App\Models\Product;
use App\Models\Project;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminMergeSeparatePurchaseRequestTest extends TestCase
{
    protected User $generalAdmin;
    protected User $regularUser;
    protected Project $project;
    protected Store $store;
    protected Product $productA;
    protected Product $productB;

    protected function setUp(): void
    {
        parent::setUp();

        DB::statement('PRAGMA foreign_keys = OFF;');
        Schema::dropIfExists('purchase_request_items');
        Schema::dropIfExists('material_request_items');
        Schema::dropIfExists('pr_workflow_logs');
        Schema::dropIfExists('purchase_requests');
        Schema::dropIfExists('material_requests');
        Schema::dropIfExists('products');
        Schema::dropIfExists('stores');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('users');

        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->boolean('is_admin')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('projects', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('stores', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('unit')->default('pcs');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('purchase_requests', function ($table) {
            $table->id();
            $table->string('pr_no')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->unsignedBigInteger('material_request_id')->nullable();
            $table->string('priority')->default('normal');
            $table->string('type')->default('normal');
            $table->date('required_date')->nullable();
            $table->text('justification')->nullable();
            $table->string('status')->default('draft');
            $table->unsignedBigInteger('merged_into_pr_id')->nullable();
            $table->text('merge_notes')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('current_owner_role')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('purchase_request_items', function ($table) {
            $table->id();
            $table->unsignedBigInteger('purchase_request_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->decimal('quantity', 15, 3)->default(1);
            $table->decimal('purchased_quantity', 15, 3)->default(0);
            $table->decimal('received_quantity', 15, 3)->default(0);
            $table->string('unit')->nullable();
            $table->text('specifications')->nullable();
            $table->decimal('estimated_unit_cost', 15, 2)->default(0);
            $table->string('payment_method')->nullable();
            $table->timestamps();
        });

        Schema::create('material_requests', function ($table) {
            $table->id();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('destination_store_id')->nullable();
            $table->string('reference_number')->nullable();
            $table->string('source')->default('Emergency');
            $table->string('status')->default('draft');
            $table->unsignedBigInteger('merged_into_mr_id')->nullable();
            $table->text('merge_notes')->nullable();
            $table->date('required_date')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('material_request_items', function ($table) {
            $table->id();
            $table->unsignedBigInteger('material_request_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->decimal('quantity_requested', 15, 3)->default(1);
            $table->decimal('quantity_fulfilled', 15, 3)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('procurement_payments', function ($table) {
            $table->id();
            $table->unsignedBigInteger('purchase_request_id');
            $table->unsignedBigInteger('assigned_finance_staff_id')->nullable();
            $table->unsignedBigInteger('coa_account_id')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('status')->default('pending');
            $table->boolean('has_withholding')->default(false);
            $table->decimal('withholding_rate', 5, 2)->default(2.00);
            $table->decimal('withholding_amount', 15, 2)->default(0);
            $table->string('withholding_receipt')->nullable();
            $table->string('withholding_receipt_number')->nullable();
            $table->timestamps();
        });

        Schema::create('procurement_receipts', function ($table) {
            $table->id();
            $table->unsignedBigInteger('purchase_request_id');
            $table->string('file_path')->nullable();
            $table->string('verification_status')->default('pending');
            $table->string('withholding_receipt_path')->nullable();
            $table->string('withholding_receipt_number')->nullable();
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

        $this->generalAdmin = User::create([
            'name'     => 'General Administrator',
            'email'    => 'admin@construction.com',
            'is_admin' => true,
        ]);

        $this->regularUser = User::create([
            'name'     => 'Regular Employee',
            'email'    => 'user@construction.com',
            'is_admin' => false,
        ]);

        $this->project = Project::create(['name' => 'HQ Construction Project']);
        $this->store = Store::create(['name' => 'Main Warehouse Store']);
        $this->productA = Product::create(['name' => 'Portland Cement 50kg', 'unit' => 'bag']);
        $this->productB = Product::create(['name' => 'Reinforcement Rebar 12mm', 'unit' => 'pcs']);
    }

    protected function tearDown(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF;');
        Schema::dropIfExists('purchase_request_items');
        Schema::dropIfExists('material_request_items');
        Schema::dropIfExists('pr_workflow_logs');
        Schema::dropIfExists('purchase_requests');
        Schema::dropIfExists('material_requests');
        Schema::dropIfExists('products');
        Schema::dropIfExists('stores');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('users');
        parent::tearDown();
    }

    /**
     * Test non-general admin is forbidden from merging PRs.
     */
    public function test_non_general_admin_cannot_merge_pr(): void
    {
        Auth::login($this->regularUser);

        $pr1 = PurchaseRequest::create([
            'pr_no'      => 'PR-20261103-0001',
            'project_id' => $this->project->id,
            'status'     => PurchaseRequest::STATUS_DRAFT,
        ]);

        $pr2 = PurchaseRequest::create([
            'pr_no'      => 'PR-20261103-0002',
            'project_id' => $this->project->id,
            'status'     => PurchaseRequest::STATUS_DRAFT,
        ]);

        $controller = app(\App\Http\Controllers\PurchaseRequestController::class);
        $req = Request::create('/purchase-requests/' . $pr1->id . '/admin-merge', 'POST', [
            'source_pr_ids' => [$pr2->id],
        ]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $controller->adminMergePr($req, $pr1);
    }

    /**
     * Test non-general admin is forbidden from separating PRs.
     */
    public function test_non_general_admin_cannot_separate_pr(): void
    {
        Auth::login($this->regularUser);

        $pr = PurchaseRequest::create([
            'pr_no'      => 'PR-20261103-0003',
            'project_id' => $this->project->id,
            'status'     => PurchaseRequest::STATUS_DRAFT,
        ]);

        $item = PurchaseRequestItem::create([
            'purchase_request_id' => $pr->id,
            'product_id'          => $this->productA->id,
            'quantity'            => 10,
            'unit'                => 'bag',
        ]);

        $controller = app(\App\Http\Controllers\PurchaseRequestController::class);
        $req = Request::create('/purchase-requests/' . $pr->id . '/admin-separate', 'POST', [
            'items' => [
                $item->id => ['selected' => 1, 'quantity' => 5],
            ],
        ]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $controller->adminSeparatePr($req, $pr);
    }

    /**
     * Test General Admin can merge purchase requests with item consolidation.
     */
    public function test_general_admin_can_merge_purchase_requests_with_consolidation(): void
    {
        Auth::login($this->generalAdmin);

        $targetPr = PurchaseRequest::create([
            'pr_no'      => 'PR-20261103-0010',
            'project_id' => $this->project->id,
            'status'     => PurchaseRequest::STATUS_PENDING_PLANNING,
        ]);
        PurchaseRequestItem::create([
            'purchase_request_id' => $targetPr->id,
            'product_id'          => $this->productA->id,
            'quantity'            => 20,
            'unit'                => 'bag',
        ]);

        $sourcePr = PurchaseRequest::create([
            'pr_no'      => 'PR-20261103-0011',
            'project_id' => $this->project->id,
            'status'     => PurchaseRequest::STATUS_PENDING_PLANNING,
        ]);
        // Duplicate productA to be consolidated
        PurchaseRequestItem::create([
            'purchase_request_id' => $sourcePr->id,
            'product_id'          => $this->productA->id,
            'quantity'            => 15,
            'unit'                => 'bag',
        ]);
        // Unique productB to be transferred
        PurchaseRequestItem::create([
            'purchase_request_id' => $sourcePr->id,
            'product_id'          => $this->productB->id,
            'quantity'            => 50,
            'unit'                => 'pcs',
        ]);

        $controller = app(\App\Http\Controllers\PurchaseRequestController::class);
        $req = Request::create('/purchase-requests/' . $targetPr->id . '/admin-merge', 'POST', [
            'source_pr_ids'          => [$sourcePr->id],
            'consolidate_duplicates' => 1,
            'merge_notes'            => 'Combining site foundation cement & rebar',
        ]);

        $res = $controller->adminMergePr($req, $targetPr);
        $this->assertTrue($res->isRedirect());

        // Target PR verification
        $targetPr->refresh();
        $targetItems = $targetPr->items()->get();
        $this->assertCount(2, $targetItems);

        $cementItem = $targetItems->firstWhere('product_id', $this->productA->id);
        $this->assertNotNull($cementItem);
        $this->assertEquals(35.0, (float)$cementItem->quantity); // 20 + 15

        $rebarItem = $targetItems->firstWhere('product_id', $this->productB->id);
        $this->assertNotNull($rebarItem);
        $this->assertEquals(50.0, (float)$rebarItem->quantity);

        // Source PR verification
        $sourcePr->refresh();
        $this->assertEquals(PurchaseRequest::STATUS_CANCELLED, $sourcePr->status);
        $this->assertEquals($targetPr->id, $sourcePr->merged_into_pr_id);

        // PrWorkflowLog audit
        $this->assertTrue(PrWorkflowLog::where('purchase_request_id', $sourcePr->id)->where('action', 'admin_pr_merged')->exists());
        $this->assertTrue(PrWorkflowLog::where('purchase_request_id', $targetPr->id)->where('action', 'admin_pr_merged_sources')->exists());
    }

    /**
     * Test General Admin can separate PR items (partial qty and full item).
     */
    public function test_general_admin_can_separate_purchase_request_items(): void
    {
        Auth::login($this->generalAdmin);

        $origPr = PurchaseRequest::create([
            'pr_no'      => 'PR-20261103-0020',
            'project_id' => $this->project->id,
            'status'     => PurchaseRequest::STATUS_PENDING_MARKETING,
        ]);
        $item1 = PurchaseRequestItem::create([
            'purchase_request_id' => $origPr->id,
            'product_id'          => $this->productA->id,
            'quantity'            => 100,
            'unit'                => 'bag',
        ]);
        $item2 = PurchaseRequestItem::create([
            'purchase_request_id' => $origPr->id,
            'product_id'          => $this->productB->id,
            'quantity'            => 30,
            'unit'                => 'pcs',
        ]);

        // Separate: 40 bags of cement (partial) and all 30 rebar (full)
        $controller = app(\App\Http\Controllers\PurchaseRequestController::class);
        $req = Request::create('/purchase-requests/' . $origPr->id . '/admin-separate', 'POST', [
            'items' => [
                $item1->id => ['selected' => 1, 'quantity' => 40],
                $item2->id => ['selected' => 1, 'quantity' => 30],
            ],
            'separate_notes' => 'Splitting emergency portion',
            'new_priority'   => 'urgent',
        ]);

        $res = $controller->adminSeparatePr($req, $origPr);
        $this->assertTrue($res->isRedirect());

        // Original PR verification
        $origPr->refresh();
        $item1->refresh();
        $this->assertEquals(60.0, (float)$item1->quantity); // 100 - 40

        // New PR verification
        $newPr = PurchaseRequest::where('id', '!=', $origPr->id)->latest('id')->first();
        $this->assertNotNull($newPr);
        $this->assertEquals('urgent', $newPr->priority);
        $this->assertEquals(PurchaseRequest::STATUS_PENDING_MARKETING, $newPr->status);

        $newItems = $newPr->items;
        $this->assertCount(2, $newItems);

        $newCement = $newItems->firstWhere('product_id', $this->productA->id);
        $this->assertEquals(40.0, (float)$newCement->quantity);

        $newRebar = $newItems->firstWhere('product_id', $this->productB->id);
        $this->assertEquals(30.0, (float)$newRebar->quantity);
    }

    /**
     * Test General Admin can merge material requests.
     */
    public function test_general_admin_can_merge_material_requests(): void
    {
        Auth::login($this->generalAdmin);

        $targetMr = MaterialRequest::create([
            'reference_number'     => 'MR-20261103-0100',
            'project_id'           => $this->project->id,
            'destination_store_id' => $this->store->id,
            'status'               => 'draft',
            'required_date'        => now()->addDays(3),
        ]);
        MaterialRequestItem::create([
            'material_request_id' => $targetMr->id,
            'product_id'          => $this->productA->id,
            'quantity_requested'  => 10,
        ]);

        $srcMr = MaterialRequest::create([
            'reference_number'     => 'MR-20261103-0101',
            'project_id'           => $this->project->id,
            'destination_store_id' => $this->store->id,
            'status'               => 'draft',
            'required_date'        => now()->addDays(3),
        ]);
        MaterialRequestItem::create([
            'material_request_id' => $srcMr->id,
            'product_id'          => $this->productA->id,
            'quantity_requested'  => 25,
        ]);

        $controller = app(\App\Http\Controllers\MaterialRequestController::class);
        $req = Request::create('/material-requests/' . $targetMr->id . '/admin-merge', 'POST', [
            'source_mr_ids'          => [$srcMr->id],
            'consolidate_duplicates' => 1,
            'merge_notes'            => 'Combining site requisitions',
        ]);

        $res = $controller->adminMergeMr($req, $targetMr);
        $this->assertTrue($res->isRedirect());

        $targetMr->refresh();
        $this->assertEquals(35.0, (float)$targetMr->items()->first()->quantity_requested);

        $srcMr->refresh();
        $this->assertEquals('cancelled', $srcMr->status);
        $this->assertEquals($targetMr->id, $srcMr->merged_into_mr_id);
    }

    /**
     * Test General Admin can separate material requests.
     */
    public function test_general_admin_can_separate_material_requests(): void
    {
        Auth::login($this->generalAdmin);

        $origMr = MaterialRequest::create([
            'reference_number'     => 'MR-20261103-0200',
            'project_id'           => $this->project->id,
            'destination_store_id' => $this->store->id,
            'status'               => 'draft',
            'required_date'        => now()->addDays(5),
        ]);
        $mItem = MaterialRequestItem::create([
            'material_request_id' => $origMr->id,
            'product_id'          => $this->productA->id,
            'quantity_requested'  => 80,
        ]);

        $controller = app(\App\Http\Controllers\MaterialRequestController::class);
        $req = Request::create('/material-requests/' . $origMr->id . '/admin-separate', 'POST', [
            'items' => [
                $mItem->id => ['selected' => 1, 'quantity' => 30],
            ],
            'separate_notes' => 'Split batch for immediate delivery',
        ]);

        $res = $controller->adminSeparateMr($req, $origMr);
        $this->assertTrue($res->isRedirect());

        $origMr->refresh();
        $mItem->refresh();
        $this->assertEquals(50.0, (float)$mItem->quantity_requested);

        $newMr = MaterialRequest::where('id', '!=', $origMr->id)->latest('id')->first();
        $this->assertNotNull($newMr);
        $this->assertEquals(30.0, (float)$newMr->items()->first()->quantity_requested);
    }

    /**
     * Test non-general admin is forbidden from merging MRs.
     */
    public function test_non_general_admin_cannot_merge_mr(): void
    {
        Auth::login($this->regularUser);

        $mr1 = MaterialRequest::create([
            'reference_number'     => 'MR-20261103-0301',
            'project_id'           => $this->project->id,
            'destination_store_id' => $this->store->id,
            'status'               => 'draft',
            'required_date'        => now()->addDays(2),
        ]);
        $mr2 = MaterialRequest::create([
            'reference_number'     => 'MR-20261103-0302',
            'project_id'           => $this->project->id,
            'destination_store_id' => $this->store->id,
            'status'               => 'draft',
            'required_date'        => now()->addDays(2),
        ]);

        $controller = app(\App\Http\Controllers\MaterialRequestController::class);
        $req = Request::create('/material-requests/' . $mr1->id . '/admin-merge', 'POST', [
            'source_mr_ids' => [$mr2->id],
        ]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $controller->adminMergeMr($req, $mr1);
    }

    /**
     * Test non-general admin is forbidden from separating MRs.
     */
    public function test_non_general_admin_cannot_separate_mr(): void
    {
        Auth::login($this->regularUser);

        $mr = MaterialRequest::create([
            'reference_number'     => 'MR-20261103-0303',
            'project_id'           => $this->project->id,
            'destination_store_id' => $this->store->id,
            'status'               => 'draft',
            'required_date'        => now()->addDays(2),
        ]);
        $item = MaterialRequestItem::create([
            'material_request_id' => $mr->id,
            'product_id'          => $this->productA->id,
            'quantity_requested'  => 20,
        ]);

        $controller = app(\App\Http\Controllers\MaterialRequestController::class);
        $req = Request::create('/material-requests/' . $mr->id . '/admin-separate', 'POST', [
            'items' => [
                $item->id => ['selected' => 1, 'quantity' => 5],
            ],
        ]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $controller->adminSeparateMr($req, $mr);
    }

    /**
     * Test General Admin can perform bulk PR merge.
     */
    public function test_general_admin_can_bulk_merge_prs(): void
    {
        Auth::login($this->generalAdmin);

        $targetPr = PurchaseRequest::create([
            'pr_no'      => 'PR-20261103-0400',
            'project_id' => $this->project->id,
            'status'     => PurchaseRequest::STATUS_DRAFT,
        ]);
        $sourcePr = PurchaseRequest::create([
            'pr_no'      => 'PR-20261103-0401',
            'project_id' => $this->project->id,
            'status'     => PurchaseRequest::STATUS_DRAFT,
        ]);
        PurchaseRequestItem::create([
            'purchase_request_id' => $sourcePr->id,
            'product_id'          => $this->productA->id,
            'quantity'            => 12,
            'unit'                => 'bag',
        ]);

        $controller = app(\App\Http\Controllers\PurchaseRequestController::class);
        $req = Request::create('/purchase-requests/admin-bulk-merge', 'POST', [
            'target_pr_id'  => $targetPr->id,
            'source_pr_ids' => [$sourcePr->id],
        ]);

        $res = $controller->adminBulkMergePr($req);
        $this->assertTrue($res->isRedirect());

        $targetPr->refresh();
        $this->assertCount(1, $targetPr->items);
        $this->assertEquals(12.0, (float)$targetPr->items()->first()->quantity);

        $sourcePr->refresh();
        $this->assertEquals(PurchaseRequest::STATUS_CANCELLED, $sourcePr->status);
        $this->assertEquals($targetPr->id, $sourcePr->merged_into_pr_id);
    }

    /**
     * Test PR view renders without error when pending payment and payment record is null.
     */
    public function test_pr_show_view_handles_null_payment_when_pending_payment(): void
    {
        Auth::login($this->generalAdmin);

        $pr = PurchaseRequest::create([
            'pr_no'      => 'PR-20261103-0500',
            'project_id' => $this->project->id,
            'status'     => PurchaseRequest::STATUS_PENDING_PAYMENT,
        ]);
        PurchaseRequestItem::create([
            'purchase_request_id' => $pr->id,
            'product_id'          => $this->productA->id,
            'quantity'            => 10,
            'unit'                => 'bag',
        ]);
        $pr->load('items.product', 'project');

        $this->assertNull($pr->payment);

        $rendered = view('procurement.purchase-requests.show', [
            'purchaseRequest'      => $pr,
            'stockAvailability'    => [],
            'transferAvailability' => [],
            'prTransfers'          => collect(),
            'coaAccounts'          => collect(),
            'financeStaff'         => collect(),
            'drivers'              => collect(),
            'suppliers'            => collect(),
            'stores'               => collect(),
            'pricingBenchmarks'    => [
                'items'                  => [],
                'total_monthly_market'   => 0,
                'total_last_purchase'    => 0,
                'total_latest_benchmark' => 0,
                'has_monthly_data'       => false,
                'has_purchase_data'      => false,
            ],
            'isFinalIntake'        => false,
            'receiveSlipSequence'  => null,
            'nextReceiveSlipNo'    => null,
            'availableProducts'    => collect(),
            'prDeliveryReceipts'   => collect(),
            'userStoreId'          => null,
            'proformaMatrix'       => ['suppliers' => [], 'items' => [], 'grand_totals' => []],
            'mergeablePrs'         => collect(),
            'errors'               => new \Illuminate\Support\ViewErrorBag(),
        ])->render();

        $this->assertStringContainsString('PR-20261103-0500', $rendered);
        $this->assertStringContainsString('Finance Payment Assignment', $rendered);
    }
}
