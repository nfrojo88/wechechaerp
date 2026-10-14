<?php

namespace Tests\Unit;

use App\Models\ActivityLog;
use App\Models\PrWorkflowLog;
use App\Models\Product;
use App\Models\ProformaInvoice;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PurchaseRequestDeleteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DB::statement('PRAGMA foreign_keys = OFF;');
        Schema::dropIfExists('purchase_request_items');
        Schema::dropIfExists('proforma_invoices');
        Schema::dropIfExists('pr_workflow_logs');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('purchase_requests');
        Schema::dropIfExists('products');
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

        Schema::create('products', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('unit')->default('pcs');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('purchase_requests', function ($table) {
            $table->id();
            $table->string('pr_no')->nullable();
            $table->string('status')->default('draft');
            $table->string('current_owner_role')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('material_request_id')->nullable();
            $table->unsignedBigInteger('merged_into_pr_id')->nullable();
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

        Schema::create('proforma_invoices', function ($table) {
            $table->id();
            $table->unsignedBigInteger('purchase_request_id');
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->string('proforma_number')->nullable();
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->string('file_path')->nullable();
            $table->boolean('gm_selected')->default(false);
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

        Schema::create('activity_logs', function ($table) {
            $table->id();
            $table->string('action');
            $table->text('description')->nullable();
            $table->string('module')->nullable();
            $table->nullableMorphs('subject');
            $table->json('properties')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF;');
        Schema::dropIfExists('purchase_request_items');
        Schema::dropIfExists('proforma_invoices');
        Schema::dropIfExists('pr_workflow_logs');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('purchase_requests');
        Schema::dropIfExists('products');
        Schema::dropIfExists('users');
        parent::tearDown();
    }

    /**
     * Test that PurchaseRequest::proformas() method exists and returns a HasMany relationship.
     */
    public function test_purchase_request_proformas_method_exists_and_returns_relation(): void
    {
        $pr = new PurchaseRequest();
        $this->assertTrue(method_exists($pr, 'proformas'), 'proformas() method must exist on PurchaseRequest');
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $pr->proformas());
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $pr->proformaInvoices());
    }

    /**
     * Test User::isGlobalAdmin() recognizes admin privileges correctly.
     */
    public function test_user_is_global_admin_helper(): void
    {
        $adminUser = new User(['name' => 'Admin User', 'email' => 'admin@test.com', 'is_admin' => true]);
        $this->assertTrue($adminUser->isGlobalAdmin());

        $normalUser = new User(['name' => 'Normal User', 'email' => 'user@test.com', 'is_admin' => false]);
        $this->assertFalse($normalUser->isGlobalAdmin());
    }

    /**
     * Test non-admin cannot delete purchase request.
     */
    public function test_non_global_admin_cannot_delete_purchase_request(): void
    {
        $user = User::create(['name' => 'Requester', 'email' => 'requester@test.com', 'is_admin' => false]);
        $this->actingAs($user);

        $pr = PurchaseRequest::create(['pr_no' => 'PR-2026-0001', 'status' => 'draft']);

        $response = $this->delete(route('purchase-requests.destroy', $pr));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('purchase_requests', ['id' => $pr->id]);
    }

    /**
     * Test Global Admin can delete purchase request and related items/proformas without error.
     */
    public function test_global_admin_can_delete_purchase_request_and_relations(): void
    {
        $admin = User::create(['name' => 'Global Admin', 'email' => 'admin@test.com', 'is_admin' => true]);
        $this->actingAs($admin);

        $pr = PurchaseRequest::create(['pr_no' => 'PR-2026-0002', 'status' => 'pending_proforma_selection']);

        $product = Product::create(['name' => 'Cement 50kg']);
        PurchaseRequestItem::create([
            'purchase_request_id' => $pr->id,
            'product_id'          => $product->id,
            'quantity'            => 10,
        ]);

        ProformaInvoice::create([
            'purchase_request_id' => $pr->id,
            'proforma_number'     => 'QUO-001',
            'grand_total'         => 5000.00,
        ]);

        PrWorkflowLog::create([
            'purchase_request_id' => $pr->id,
            'action'              => 'created',
            'actor_role'          => 'admin',
        ]);

        $response = $this->delete(route('purchase-requests.destroy', $pr));

        $response->assertRedirect(route('procurement.my-queue'));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('purchase_requests', ['id' => $pr->id]);
        $this->assertDatabaseMissing('purchase_request_items', ['purchase_request_id' => $pr->id]);
        $this->assertDatabaseMissing('proforma_invoices', ['purchase_request_id' => $pr->id]);
        $this->assertDatabaseMissing('pr_workflow_logs', ['purchase_request_id' => $pr->id]);
    }
}
