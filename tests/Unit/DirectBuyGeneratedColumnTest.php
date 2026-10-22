<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\Project;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\User;
use App\Services\ProcurementLifecycleService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DirectBuyGeneratedColumnTest extends TestCase
{
    protected User $user;
    protected Project $project;
    protected Product $product;
    protected PurchaseRequest $pr;
    protected PurchaseRequestItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        DB::statement('PRAGMA foreign_keys = OFF;');

        Schema::dropIfExists('purchase_request_items');
        Schema::dropIfExists('purchase_requests');
        Schema::dropIfExists('products');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('pr_workflow_logs');
        Schema::dropIfExists('users');

        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->default('password');
            $table->timestamps();
        });

        Schema::create('projects', function ($table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('products', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('item_code')->nullable();
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('purchase_requests', function ($table) {
            $table->id();
            $table->string('pr_no', 50)->unique();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('requested_by');
            $table->string('status', 50)->default('pending_proc_team');
            $table->string('sourcing_method', 30)->nullable();
            $table->decimal('direct_buy_amount', 14, 2)->default(0);
            $table->unsignedBigInteger('direct_buy_added_by')->nullable();
            $table->text('procurement_team_notes')->nullable();
            $table->string('current_owner_role', 50)->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_request_items', function ($table) {
            $table->id();
            $table->unsignedBigInteger('purchase_request_id');
            $table->unsignedBigInteger('product_id');
            $table->decimal('quantity', 15, 3)->default(1);
            $table->string('unit', 20)->default('pcs');
            $table->decimal('estimated_unit_cost', 15, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('pr_workflow_logs', function ($table) {
            $table->id();
            $table->unsignedBigInteger('purchase_request_id');
            $table->string('from_stage')->nullable();
            $table->string('to_stage')->nullable();
            $table->string('action')->nullable();
            $table->string('actor_role')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamps();
        });

        $this->user = User::create([
            'name' => 'Procurement Officer',
            'email' => 'buyer@test.com',
        ]);
        Auth::login($this->user);

        $this->project = Project::create(['name' => 'Project Alpha']);
        $this->product = Product::create(['name' => 'Steel Rebar 12mm', 'unit_price' => 2500]);

        $this->pr = PurchaseRequest::create([
            'pr_no'        => 'PR-2026-TEST',
            'project_id'   => $this->project->id,
            'requested_by' => $this->user->id,
            'status'       => PurchaseRequest::STATUS_PENDING_PROC_TEAM,
        ]);

        $this->item = PurchaseRequestItem::create([
            'purchase_request_id' => $this->pr->id,
            'product_id'          => $this->product->id,
            'quantity'            => 8.000,
            'unit'                => 'pcs',
        ]);
    }

    public function test_submit_direct_buy_updates_unit_cost_without_writing_to_generated_column(): void
    {
        $service = app(ProcurementLifecycleService::class);

        // Submit direct buy with unit price 2608.70 for 8 items, with VAT
        $service->submitDirectBuy(
            $this->pr,
            0.0,
            'Direct buy test notes',
            [$this->item->id => 2608.70],
            true
        );

        $this->item->refresh();
        $this->pr->refresh();

        // Unit cost updated
        $this->assertEquals(2608.70, (float)$this->item->estimated_unit_cost);

        // Accessor calculates 8 * 2608.70 = 20869.60
        $this->assertEquals(20869.60, (float)$this->item->estimated_total);

        // PR direct buy amount with 15% VAT = 20869.60 * 1.15 = 24000.04
        $this->assertEquals(24000.04, (float)$this->pr->direct_buy_amount);

        // PR status advanced
        $this->assertEquals(PurchaseRequest::STATUS_PENDING_MARKETING, $this->pr->status);
    }

    public function test_purchase_request_item_strips_estimated_total_on_save(): void
    {
        $this->item->estimated_total = 999999.99;
        $this->item->estimated_unit_cost = 100.00;
        $this->item->save();

        // The save hook strips estimated_total so MySQL never sees it in the UPDATE statement
        $this->assertEquals(800.00, (float)$this->item->fresh()->estimated_total);
    }
}
