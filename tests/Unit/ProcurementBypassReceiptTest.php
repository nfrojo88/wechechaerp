<?php

namespace Tests\Unit;

use App\Http\Controllers\PurchaseRequestController;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Models\PrWorkflowLog;
use App\Services\ProcurementLifecycleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProcurementBypassReceiptTest extends TestCase
{
    protected ProcurementLifecycleService $lifecycle;

    protected function setUp(): void
    {
        parent::setUp();

        DB::statement('PRAGMA foreign_keys = OFF;');

        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('purchase_requests');
        Schema::dropIfExists('users');
        Schema::dropIfExists('pr_workflow_logs');
        Schema::dropIfExists('procurement_sms_logs');
        Schema::dropIfExists('procurement_payments');
        Schema::dropIfExists('chart_of_accounts');
        Schema::dropIfExists('expenses');

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

        Schema::create('purchase_requests', function ($table) {
            $table->id();
            $table->string('pr_no')->unique();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->string('sourcing_method')->nullable();
            $table->decimal('direct_buy_amount', 15, 2)->nullable();
            $table->string('status')->default('draft');
            $table->string('current_owner_role')->nullable();
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

        Schema::create('procurement_payments', function ($table) {
            $table->id();
            $table->unsignedBigInteger('purchase_request_id');
            $table->unsignedBigInteger('coa_account_id')->nullable();
            $table->decimal('amount', 15, 2);
            $table->unsignedBigInteger('assigned_finance_staff_id')->nullable();
            $table->string('status')->default('assigned');
            $table->unsignedBigInteger('paid_by')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('chart_of_accounts', function ($table) {
            $table->id();
            $table->string('code')->nullable();
            $table->string('name');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('expenses', function ($table) {
            $table->id();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->string('description')->nullable();
            $table->string('category')->default('material');
            $table->decimal('amount', 15, 2)->default(0);
            $table->date('expense_date')->nullable();
            $table->string('status')->default('approved');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        $this->lifecycle = app(ProcurementLifecycleService::class);
    }

    public function test_skip_receipt_to_store_advances_pr_to_store_material_intake()
    {
        $storeKeeperRole = DB::table('roles')->insertGetId(['name' => 'store_keeper', 'guard_name' => 'web']);
        $storeKeeperUser = User::create(['name' => 'Store Keeper', 'email' => 'sk@example.com']);
        DB::table('model_has_roles')->insert([
            'role_id' => $storeKeeperRole,
            'model_type' => User::class,
            'model_id' => $storeKeeperUser->id,
        ]);

        $pr = PurchaseRequest::create([
            'pr_no' => 'PR-TEST-SKIP',
            'status' => PurchaseRequest::STATUS_PENDING_RECEIPT_UPLOAD,
            'current_owner_role' => 'purchase',
        ]);

        $this->lifecycle->skipReceiptToStore($pr, 'User jumped directly past receipt upload');

        $pr->refresh();
        $this->assertEquals(PurchaseRequest::STATUS_PENDING_STORE_REVIEW, $pr->status);
        $this->assertEquals('store_keeper', $pr->current_owner_role);

        $log = PrWorkflowLog::where('purchase_request_id', $pr->id)->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertEquals('skip_receipt_jump_to_store', $log->action);
        $this->assertEquals(PurchaseRequest::STATUS_PENDING_STORE_REVIEW, $log->to_stage);
    }

    public function test_finance_staff_pay_advances_directly_to_store_review_bypassing_receipt_upload()
    {
        $storeKeeperRole = DB::table('roles')->insertGetId(['name' => 'store_keeper', 'guard_name' => 'web']);
        $storeKeeperUser = User::create(['name' => 'Store Keeper 2', 'email' => 'sk2@example.com']);
        DB::table('model_has_roles')->insert([
            'role_id' => $storeKeeperRole,
            'model_type' => User::class,
            'model_id' => $storeKeeperUser->id,
        ]);

        $cashierRole = DB::table('roles')->insertGetId(['name' => 'finance', 'guard_name' => 'web']);
        $cashierUser = User::create(['name' => 'Cashier User', 'email' => 'cashier@example.com']);
        DB::table('model_has_roles')->insert([
            'role_id' => $cashierRole,
            'model_type' => User::class,
            'model_id' => $cashierUser->id,
        ]);

        Auth::login($cashierUser);

        $pr = PurchaseRequest::create([
            'pr_no' => 'PR-TEST-PAY',
            'status' => PurchaseRequest::STATUS_PENDING_PAYMENT,
            'current_owner_role' => 'finance',
        ]);

        DB::table('procurement_payments')->insert([
            'purchase_request_id' => $pr->id,
            'amount' => 5000.00,
            'assigned_finance_staff_id' => $cashierUser->id,
            'status' => 'assigned',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->lifecycle->financeStaffPay($pr, 'Bank TXN #123456');

        $pr->refresh();
        $this->assertEquals(PurchaseRequest::STATUS_PENDING_STORE_REVIEW, $pr->status);
        $this->assertEquals('store_keeper', $pr->current_owner_role);

        $log = PrWorkflowLog::where('purchase_request_id', $pr->id)->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertEquals('finance_staff_paid_direct_to_store', $log->action);
        $this->assertEquals(PurchaseRequest::STATUS_PENDING_STORE_REVIEW, $log->to_stage);
    }

    public function test_controller_skip_receipt_to_store_action_redirects_with_success()
    {
        $purchaserRole = DB::table('roles')->insertGetId(['name' => 'purchaser', 'guard_name' => 'web']);
        $purchaserUser = User::create(['name' => 'Purchaser User', 'email' => 'purchaser@example.com']);
        DB::table('model_has_roles')->insert([
            'role_id' => $purchaserRole,
            'model_type' => User::class,
            'model_id' => $purchaserUser->id,
        ]);

        $storeKeeperRole = DB::table('roles')->insertGetId(['name' => 'store_keeper', 'guard_name' => 'web']);
        $storeKeeperUser = User::create(['name' => 'Store Keeper 3', 'email' => 'sk3@example.com']);
        DB::table('model_has_roles')->insert([
            'role_id' => $storeKeeperRole,
            'model_type' => User::class,
            'model_id' => $storeKeeperUser->id,
        ]);

        Auth::login($purchaserUser);

        $pr = PurchaseRequest::create([
            'pr_no' => 'PR-TEST-ACTION',
            'status' => PurchaseRequest::STATUS_PENDING_RECEIPT_UPLOAD,
            'current_owner_role' => 'purchase',
        ]);

        $controller = app(PurchaseRequestController::class);
        $request = Request::create(route('purchase-requests.skip-receipt-to-store', $pr), 'POST', [
            'notes' => 'Bypassing receipt upload via controller',
        ]);

        $response = $controller->skipReceiptToStore($request, $pr);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringContainsString('purchase-requests/' . $pr->id, $response->headers->get('Location'));

        $pr->refresh();
        $this->assertEquals(PurchaseRequest::STATUS_PENDING_STORE_REVIEW, $pr->status);
        $this->assertEquals('store_keeper', $pr->current_owner_role);
    }
}
