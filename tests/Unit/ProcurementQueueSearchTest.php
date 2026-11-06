<?php

namespace Tests\Unit;

use App\Http\Controllers\ProcurementLifecycleController;
use App\Models\MaterialRequest;
use App\Models\MaterialRequestItem;
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

class ProcurementQueueSearchTest extends TestCase
{
    protected User $user;
    protected Project $project;
    protected Store $store;
    protected Product $productCement;
    protected Product $productHelmet;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF;');
        }

        Schema::dropIfExists('delivery_receipts');
        Schema::dropIfExists('purchase_request_items');
        Schema::dropIfExists('material_request_items');
        Schema::dropIfExists('purchase_requests');
        Schema::dropIfExists('material_requests');
        Schema::dropIfExists('products');
        Schema::dropIfExists('stores');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('roles');
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
            $table->softDeletes();
        });

        Schema::create('products', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->string('unit')->default('pcs');
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('material_requests', function ($table) {
            $table->id();
            $table->string('reference_number')->unique();
            $table->foreignId('project_id')->nullable();
            $table->foreignId('destination_store_id')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->string('status')->default('pending');
            $table->string('planning_approval_status')->default('pending');
            $table->date('required_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('material_request_items', function ($table) {
            $table->id();
            $table->foreignId('material_request_id');
            $table->foreignId('product_id');
            $table->decimal('quantity_requested', 15, 3)->default(1);
            $table->decimal('quantity_fulfilled', 15, 3)->default(0);
            $table->string('status')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('delivery_receipts', function ($table) {
            $table->id();
            $table->foreignId('purchase_request_id')->nullable();
            $table->string('dr_no')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('purchase_requests', function ($table) {
            $table->id();
            $table->string('pr_no')->unique();
            $table->foreignId('project_id')->nullable();
            $table->foreignId('store_id')->nullable();
            $table->foreignId('requested_by')->nullable();
            $table->foreignId('material_request_id')->nullable();
            $table->string('status')->default('submitted');
            $table->string('current_owner_role')->nullable();
            $table->string('priority')->default('normal');
            $table->string('type')->default('normal');
            $table->date('required_date')->nullable();
            $table->text('justification')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('purchase_request_items', function ($table) {
            $table->id();
            $table->foreignId('purchase_request_id');
            $table->foreignId('product_id');
            $table->decimal('quantity', 15, 3)->default(1);
            $table->decimal('purchased_quantity', 15, 3)->default(0);
            $table->decimal('received_quantity', 15, 3)->default(0);
            $table->string('unit')->default('pcs');
            $table->text('specifications')->nullable();
            $table->decimal('estimated_unit_cost', 15, 2)->default(0);
            $table->timestamps();
        });

        $this->user = User::create([
            'name' => 'Admin User',
            'email' => 'admin@wechecha.com',
            'is_admin' => true,
        ]);

        $adminRoleId = DB::table('roles')->insertGetId([
            'name' => 'admin',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('model_has_roles')->insert([
            'role_id' => $adminRoleId,
            'model_type' => User::class,
            'model_id' => $this->user->id,
        ]);

        $this->project = Project::create([
            'name' => 'Bole Tower Project',
            'code' => 'BTP-01',
            'status' => 'active',
        ]);

        $this->store = Store::create([
            'name' => 'Central Depot',
            'code' => 'CD-01',
            'is_active' => true,
        ]);

        $this->productCement = Product::create([
            'name' => 'Portland Cement 50kg',
            'sku' => 'CEM-50KG-001',
            'unit' => 'bag',
        ]);

        $this->productHelmet = Product::create([
            'name' => 'Safety Helmet Yellow',
            'sku' => 'HELM-YEL-002',
            'unit' => 'pcs',
        ]);

        Auth::login($this->user);
    }

    public function test_my_queue_filters_prs_by_product_name()
    {
        $pr1 = PurchaseRequest::create([
            'pr_no' => 'PR-2026-001',
            'project_id' => $this->project->id,
            'store_id' => $this->store->id,
            'requested_by' => $this->user->id,
            'status' => 'submitted',
        ]);
        PurchaseRequestItem::create([
            'purchase_request_id' => $pr1->id,
            'product_id' => $this->productCement->id,
            'quantity' => 20,
            'unit' => 'bag',
        ]);

        $pr2 = PurchaseRequest::create([
            'pr_no' => 'PR-2026-002',
            'project_id' => $this->project->id,
            'store_id' => $this->store->id,
            'requested_by' => $this->user->id,
            'status' => 'submitted',
        ]);
        PurchaseRequestItem::create([
            'purchase_request_id' => $pr2->id,
            'product_id' => $this->productHelmet->id,
            'quantity' => 10,
            'unit' => 'pcs',
        ]);

        $controller = new ProcurementLifecycleController();

        // Search for "Cement"
        $requestCement = Request::create('/procurement/my-queue', 'GET', [
            'search' => 'Cement',
            'tab' => 'all',
        ]);
        $viewCement = $controller->myQueue($requestCement);
        $allPrsCement = $viewCement->getData()['allPrs'];

        $this->assertTrue($allPrsCement->contains('id', $pr1->id));
        $this->assertFalse($allPrsCement->contains('id', $pr2->id));

        // Search for "Helmet"
        $requestHelmet = Request::create('/procurement/my-queue', 'GET', [
            'search' => 'Helmet',
            'tab' => 'all',
        ]);
        $viewHelmet = $controller->myQueue($requestHelmet);
        $allPrsHelmet = $viewHelmet->getData()['allPrs'];

        $this->assertFalse($allPrsHelmet->contains('id', $pr1->id));
        $this->assertTrue($allPrsHelmet->contains('id', $pr2->id));
    }

    public function test_my_queue_filters_prs_by_product_sku()
    {
        $pr1 = PurchaseRequest::create([
            'pr_no' => 'PR-2026-010',
            'project_id' => $this->project->id,
            'requested_by' => $this->user->id,
            'status' => 'submitted',
        ]);
        PurchaseRequestItem::create([
            'purchase_request_id' => $pr1->id,
            'product_id' => $this->productCement->id,
            'quantity' => 5,
        ]);

        $pr2 = PurchaseRequest::create([
            'pr_no' => 'PR-2026-020',
            'project_id' => $this->project->id,
            'requested_by' => $this->user->id,
            'status' => 'submitted',
        ]);
        PurchaseRequestItem::create([
            'purchase_request_id' => $pr2->id,
            'product_id' => $this->productHelmet->id,
            'quantity' => 15,
        ]);

        $controller = new ProcurementLifecycleController();

        $request = Request::create('/procurement/my-queue', 'GET', [
            'search' => 'CEM-50KG',
            'tab' => 'all',
        ]);
        $view = $controller->myQueue($request);
        $allPrs = $view->getData()['allPrs'];

        $this->assertTrue($allPrs->contains('id', $pr1->id));
        $this->assertFalse($allPrs->contains('id', $pr2->id));
    }

    public function test_my_queue_filters_by_pr_no_with_or_without_hash()
    {
        $pr = PurchaseRequest::create([
            'pr_no' => 'PR-2026-099',
            'project_id' => $this->project->id,
            'requested_by' => $this->user->id,
            'status' => 'submitted',
        ]);
        PurchaseRequestItem::create([
            'purchase_request_id' => $pr->id,
            'product_id' => $this->productCement->id,
            'quantity' => 1,
        ]);

        $controller = new ProcurementLifecycleController();

        // Search with hash "#PR-2026-099"
        $requestHash = Request::create('/procurement/my-queue', 'GET', [
            'search' => '#PR-2026-099',
            'tab' => 'all',
        ]);
        $viewHash = $controller->myQueue($requestHash);
        $allPrsHash = $viewHash->getData()['allPrs'];

        $this->assertTrue($allPrsHash->contains('id', $pr->id));
    }

    public function test_my_queue_filters_mrs_by_product_name_in_created_tab()
    {
        $mr1 = MaterialRequest::create([
            'reference_number' => 'MR-2026-001',
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'status' => 'pending',
            'planning_approval_status' => 'pending',
        ]);
        MaterialRequestItem::create([
            'material_request_id' => $mr1->id,
            'product_id' => $this->productCement->id,
            'quantity_requested' => 10,
        ]);

        $mr2 = MaterialRequest::create([
            'reference_number' => 'MR-2026-002',
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'status' => 'pending',
            'planning_approval_status' => 'pending',
        ]);
        MaterialRequestItem::create([
            'material_request_id' => $mr2->id,
            'product_id' => $this->productHelmet->id,
            'quantity_requested' => 5,
        ]);

        $controller = new ProcurementLifecycleController();

        $request = Request::create('/procurement/my-queue', 'GET', [
            'search' => 'Cement',
            'tab' => 'my_created',
        ]);
        $view = $controller->myQueue($request);
        $createdMrs = $view->getData()['myCreatedMrs'];

        $this->assertTrue($createdMrs->contains('id', $mr1->id));
        $this->assertFalse($createdMrs->contains('id', $mr2->id));
    }

    public function test_my_queue_filters_pending_queue()
    {
        $pr1 = PurchaseRequest::create([
            'pr_no' => 'PR-2026-PEND-1',
            'project_id' => $this->project->id,
            'requested_by' => $this->user->id,
            'status' => 'submitted',
        ]);
        PurchaseRequestItem::create([
            'purchase_request_id' => $pr1->id,
            'product_id' => $this->productCement->id,
            'quantity' => 10,
        ]);

        $pr2 = PurchaseRequest::create([
            'pr_no' => 'PR-2026-PEND-2',
            'project_id' => $this->project->id,
            'requested_by' => $this->user->id,
            'status' => 'submitted',
        ]);
        PurchaseRequestItem::create([
            'purchase_request_id' => $pr2->id,
            'product_id' => $this->productHelmet->id,
            'quantity' => 5,
        ]);

        $controller = new ProcurementLifecycleController();

        $request = Request::create('/procurement/my-queue', 'GET', [
            'search' => 'Cement',
            'tab' => 'pending',
        ]);
        $view = $controller->myQueue($request);
        $myPrs = $view->getData()['myPrs'];

        $this->assertTrue($myPrs->contains('id', $pr1->id));
        $this->assertFalse($myPrs->contains('id', $pr2->id));
    }

    public function test_my_queue_filters_completed_history()
    {
        $prComp1 = PurchaseRequest::create([
            'pr_no' => 'PR-2026-COMP-1',
            'project_id' => $this->project->id,
            'requested_by' => $this->user->id,
            'status' => PurchaseRequest::STATUS_INTAKE_COMPLETE,
        ]);
        PurchaseRequestItem::create([
            'purchase_request_id' => $prComp1->id,
            'product_id' => $this->productCement->id,
            'quantity' => 50,
        ]);

        $prComp2 = PurchaseRequest::create([
            'pr_no' => 'PR-2026-COMP-2',
            'project_id' => $this->project->id,
            'requested_by' => $this->user->id,
            'status' => PurchaseRequest::STATUS_INTAKE_COMPLETE,
        ]);
        PurchaseRequestItem::create([
            'purchase_request_id' => $prComp2->id,
            'product_id' => $this->productHelmet->id,
            'quantity' => 20,
        ]);

        $controller = new ProcurementLifecycleController();

        $request = Request::create('/procurement/my-queue', 'GET', [
            'search' => 'Helmet',
            'tab' => 'completed',
        ]);
        $view = $controller->myQueue($request);
        $completedPrs = $view->getData()['completedPrs'];

        $this->assertFalse($completedPrs->contains('id', $prComp1->id));
        $this->assertTrue($completedPrs->contains('id', $prComp2->id));
    }

    public function test_my_queue_empty_search_returns_all_records()
    {
        $pr1 = PurchaseRequest::create([
            'pr_no' => 'PR-2026-ALL-1',
            'project_id' => $this->project->id,
            'requested_by' => $this->user->id,
            'status' => 'submitted',
        ]);
        PurchaseRequestItem::create([
            'purchase_request_id' => $pr1->id,
            'product_id' => $this->productCement->id,
            'quantity' => 5,
        ]);

        $pr2 = PurchaseRequest::create([
            'pr_no' => 'PR-2026-ALL-2',
            'project_id' => $this->project->id,
            'requested_by' => $this->user->id,
            'status' => 'submitted',
        ]);
        PurchaseRequestItem::create([
            'purchase_request_id' => $pr2->id,
            'product_id' => $this->productHelmet->id,
            'quantity' => 5,
        ]);

        $controller = new ProcurementLifecycleController();

        $request = Request::create('/procurement/my-queue', 'GET', [
            'search' => '',
            'tab' => 'all',
        ]);
        $view = $controller->myQueue($request);
        $allPrs = $view->getData()['allPrs'];

        $this->assertTrue($allPrs->contains('id', $pr1->id));
        $this->assertTrue($allPrs->contains('id', $pr2->id));
    }
}
