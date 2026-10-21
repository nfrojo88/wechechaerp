<?php

namespace Tests\Unit;

use App\Contracts\SmsProviderInterface;
use App\Models\DriverBooking;
use App\Models\Employee;
use App\Models\MaterialRequest;
use App\Models\NotificationLog;
use App\Models\ProcurementSmsSetting;
use App\Models\Project;
use App\Models\PurchaseRequest;
use App\Models\Store;
use App\Models\User;
use App\Observers\MaterialRequestObserver;
use App\Observers\PurchaseRequestObserver;
use App\Services\ProcurementStatusOwnerService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProcurementStatusSmsTest extends TestCase
{
    protected array $sentMessages = [];
    protected bool $mockGatewayFailure = false;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            DB::statement('PRAGMA foreign_keys = OFF;');
        } catch (\Throwable $e) {}

        Cache::flush();
        $this->sentMessages = [];
        $this->mockGatewayFailure = false;

        $fakeProvider = new class($this) implements SmsProviderInterface {
            public function __construct(protected ProcurementStatusSmsTest $test) {}

            public function send(string $to, string $message): array
            {
                if ($this->test->shouldFailGateway()) {
                    return ['success' => false, 'error' => 'Simulated afro-message gateway timeout'];
                }

                $this->test->recordMessage($to, $message);
                return ['success' => true, 'message_id' => 'MSG-' . rand(1000, 9999)];
            }
        };

        $this->app->instance(SmsProviderInterface::class, $fakeProvider);

        $this->setupTestSchema();

        PurchaseRequest::observe(PurchaseRequestObserver::class);
        MaterialRequest::observe(MaterialRequestObserver::class);
    }

    public function recordMessage(string $to, string $message): void
    {
        $this->sentMessages[] = ['to' => $to, 'message' => $message];
    }

    public function shouldFailGateway(): bool
    {
        return $this->mockGatewayFailure;
    }

    protected function setupTestSchema(): void
    {
        Schema::dropIfExists('procurement_payments');
        Schema::dropIfExists('procurement_sms_logs');
        Schema::dropIfExists('notification_log');
        Schema::dropIfExists('procurement_sms_settings');
        Schema::dropIfExists('driver_bookings');
        Schema::dropIfExists('purchase_request_items');
        Schema::dropIfExists('purchase_requests');
        Schema::dropIfExists('material_request_items');
        Schema::dropIfExists('material_requests');
        Schema::dropIfExists('stores');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('users');

        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('password')->nullable();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('access_blocked_at')->nullable();
            $table->string('access_block_reason')->nullable();
            $table->unsignedBigInteger('access_unblocked_by')->nullable();
            $table->timestamp('access_unblocked_at')->nullable();
            $table->string('access_unblock_reason')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('employees', function ($table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->string('full_name');
            $table->string('department')->nullable();
            $table->string('role_title')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('status')->default('active');
            $table->boolean('is_dead_file')->default(false);
            $table->boolean('is_approved_by_gm')->default(true);
            $table->string('gm_approval_status')->default('approved');
            $table->timestamp('gm_approved_at')->nullable();
            $table->unsignedBigInteger('gm_approved_by')->nullable();
            $table->text('gm_rejection_reason')->nullable();
            $table->timestamp('gm_rejected_at')->nullable();
            $table->unsignedBigInteger('gm_rejected_by')->nullable();
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
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('material_requests', function ($table) {
            $table->id();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('destination_store_id')->nullable();
            $table->string('reference_number')->nullable();
            $table->string('source')->nullable();
            $table->string('status')->default('submitted');
            $table->date('required_date')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('planning_approval_status')->default('pending');
            $table->timestamps();
        });

        Schema::create('purchase_requests', function ($table) {
            $table->id();
            $table->string('pr_no')->unique();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->unsignedBigInteger('material_request_id')->nullable();
            $table->string('priority')->default('normal');
            $table->string('type')->default('normal');
            $table->string('status')->default('pending_planning_approval');
            $table->string('current_owner_role')->nullable();
            $table->unsignedBigInteger('assigned_finance_staff_id')->nullable();
            $table->decimal('estimated_total_cost', 15, 2)->nullable();
            $table->decimal('direct_buy_amount', 15, 2)->nullable();
            $table->text('justification')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('procurement_payments', function ($table) {
            $table->id();
            $table->unsignedBigInteger('purchase_request_id');
            $table->unsignedBigInteger('assigned_finance_staff_id')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('method', 50)->default('cash');
            $table->string('status', 50)->default('pending_payment');
            $table->timestamps();
        });

        Schema::create('driver_bookings', function ($table) {
            $table->id();
            $table->unsignedBigInteger('purchase_request_id');
            $table->unsignedBigInteger('driver_employee_id');
            $table->string('status')->default('scheduled');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });

        Schema::create('procurement_sms_settings', function ($table) {
            $table->id();
            $table->string('handoff_key')->unique();
            $table->string('name');
            $table->string('sender_role');
            $table->string('target_role');
            $table->boolean('is_enabled')->default(true);
            $table->text('template');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('notification_log', function ($table) {
            $table->id();
            $table->string('request_type');
            $table->unsignedBigInteger('request_id')->nullable();
            $table->string('action');
            $table->unsignedBigInteger('sender_user_id')->nullable();
            $table->unsignedBigInteger('recipient_user_id')->nullable();
            $table->unsignedBigInteger('recipient_employee_id')->nullable();
            $table->string('role');
            $table->string('phone');
            $table->text('message');
            $table->string('status');
            $table->text('error')->nullable();
            $table->integer('retries')->default(0);
            $table->timestamps();
        });

        Schema::create('procurement_sms_logs', function ($table) {
            $table->id();
            $table->unsignedBigInteger('purchase_request_id')->default(0);
            $table->string('recipient_phone', 30);
            $table->string('recipient_role', 60);
            $table->text('message');
            $table->string('status');
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
        });
    }

    protected function createUserWithPhone(string $name, string $email, string $phone): User
    {
        $user = User::create([
            'name'      => $name,
            'email'     => $email,
            'is_active' => true,
        ]);
        $user->phone = $phone;
        $user->save();

        Employee::create([
            'user_id'             => $user->id,
            'full_name'           => $name,
            'email'               => $email,
            'phone'               => $phone,
            'is_approved_by_gm'   => true,
            'gm_approval_status'  => 'approved',
        ]);

        return $user;
    }

    /**
     * Test 1: Submitting Material Request immediately sends SMS to Planning team.
     */
    public function test_mr_submission_sends_sms_to_planning_team(): void
    {
        $planningUser = $this->createUserWithPhone('Planning Lead', 'planning@wechecha.com', '+251911111111');
        $role = Role::create(['name' => 'planning']);
        $planningUser->assignRole($role);

        $siteEng = $this->createUserWithPhone('Eng. Daniel', 'daniel@wechecha.com', '+251922222222');
        $project = Project::create(['name' => 'Bole Site']);

        $service = app(ProcurementStatusOwnerService::class);

        $mr = MaterialRequest::create([
            'project_id'       => $project->id,
            'reference_number' => 'MR-20261021-0001',
            'created_by'       => $siteEng->id,
            'status'           => 'submitted',
        ]);

        $service->dispatchStatusChangeSms($mr, 'submitted', null, $siteEng);

        $this->assertCount(1, $this->sentMessages);
        $this->assertEquals('+251911111111', $this->sentMessages[0]['to']);
        $this->assertStringContainsString('MR #MR-20261021-0001', $this->sentMessages[0]['message']);
        $this->assertStringContainsString('Action needed:', $this->sentMessages[0]['message']);
    }

    /**
     * Test 2: Planning approval transitions to Coordinator and sends SMS.
     */
    public function test_mr_planning_approval_routes_to_coordinator(): void
    {
        $coordUser = $this->createUserWithPhone('Coord. Yohannes', 'coord@wechecha.com', '+251933333333');
        $role = Role::create(['name' => 'coordinator']);
        $coordUser->assignRole($role);

        $mr = MaterialRequest::create([
            'reference_number' => 'MR-20261021-0002',
            'status'           => 'submitted',
        ]);

        $this->sentMessages = [];
        Cache::flush();

        $service = app(ProcurementStatusOwnerService::class);
        $service->dispatchStatusChangeSms($mr, 'planning_approved', 'submitted');

        $this->assertCount(1, $this->sentMessages);
        $this->assertEquals('+251933333333', $this->sentMessages[0]['to']);
        $this->assertStringContainsString('Approved by Planning', $this->sentMessages[0]['message']);
    }

    /**
     * Test 3: GM approval sends SMS to Finance Head with amount in ETB.
     */
    public function test_pr_gm_approval_routes_to_finance_head(): void
    {
        $financeHead = $this->createUserWithPhone('CFO Dawit', 'cfo@wechecha.com', '+251944444444');
        $role = Role::create(['name' => 'finance_head']);
        $financeHead->assignRole($role);

        $pr = PurchaseRequest::create([
            'pr_no'             => 'PR-20261021-0001',
            'direct_buy_amount' => 85000.00,
            'status'            => PurchaseRequest::STATUS_PENDING_PAYMENT,
        ]);
        $pr->estimated_total_cost = 85000.00;

        $this->sentMessages = [];
        Cache::flush();

        $service = app(ProcurementStatusOwnerService::class);
        $service->dispatchStatusChangeSms($pr, PurchaseRequest::STATUS_PENDING_PAYMENT, PurchaseRequest::STATUS_PENDING_GM, null, ['amount' => 85000.00]);

        $this->assertCount(1, $this->sentMessages);
        $this->assertEquals('+251944444444', $this->sentMessages[0]['to']);
        $this->assertStringContainsString('GM approved', $this->sentMessages[0]['message']);
        $this->assertStringContainsString('85,000.00 ETB', $this->sentMessages[0]['message']);
    }

    /**
     * Test 4: Direct assignment takes top priority (Direct Cashier gets SMS).
     */
    public function test_direct_cashier_assignment_routes_sms_to_assigned_staff(): void
    {
        $generalAccountant = $this->createUserWithPhone('Accountant A', 'acc_a@wechecha.com', '+251955555555');
        $role = Role::create(['name' => 'finance']);
        $generalAccountant->assignRole($role);

        $assignedCashier = $this->createUserWithPhone('Cashier Beth', 'beth@wechecha.com', '+251966666666');
        $assignedCashier->assignRole($role);

        $pr = PurchaseRequest::create([
            'pr_no'                     => 'PR-20261021-0002',
            'status'                    => PurchaseRequest::STATUS_PENDING_PAYMENT,
            'direct_buy_amount'         => 12000.00,
        ]);
        $pr->assigned_finance_staff_id = $assignedCashier->id;
        $pr->estimated_total_cost = 12000.00;

        $this->sentMessages = [];
        Cache::flush();

        $service = app(ProcurementStatusOwnerService::class);
        $service->dispatchStatusChangeSms($pr, PurchaseRequest::STATUS_PENDING_PAYMENT, null, null, [
            'assigned_user_id' => $assignedCashier->id,
            'amount'           => 12000.00,
        ]);

        $this->assertCount(1, $this->sentMessages);
        $this->assertEquals('+251966666666', $this->sentMessages[0]['to']);
        $this->assertStringContainsString('12,000.00 ETB', $this->sentMessages[0]['message']);
    }

    /**
     * Test 5: Booking a driver sends direct SMS to the booked driver's phone.
     */
    public function test_driver_booking_routes_sms_to_assigned_driver(): void
    {
        $driverEmp = Employee::create([
            'full_name'           => 'Driver Tariku',
            'role_title'          => 'Truck Driver',
            'phone'               => '+251977777777',
            'is_approved_by_gm'   => true,
            'gm_approval_status'  => 'approved',
        ]);

        $pr = PurchaseRequest::create([
            'pr_no'  => 'PR-20261021-0003',
            'status' => 'pending_driver_booking',
        ]);

        $this->sentMessages = [];
        Cache::flush();
        NotificationLog::truncate();

        $service = app(ProcurementStatusOwnerService::class);
        $service->dispatchStatusChangeSms(
            $pr,
            'driver_booked',
            'pending_driver_booking',
            null,
            ['driver_employee_id' => $driverEmp->id]
        );

        $this->assertTrue(NotificationLog::where('request_type', 'purchase_request')->exists());
        $this->assertCount(1, $this->sentMessages);
        $this->assertEquals('+251977777777', $this->sentMessages[0]['to']);
    }

    /**
     * Test 6: Gateway failure does not crash or block the status change.
     */
    public function test_gateway_failure_does_not_block_status_change(): void
    {
        $financeHead = $this->createUserWithPhone('CFO Dawit', 'cfo2@wechecha.com', '+251944444444');
        $role = Role::firstOrCreate(['name' => 'finance_head']);
        $financeHead->assignRole($role);

        $pr = PurchaseRequest::create([
            'pr_no'  => 'PR-20261021-0004',
            'status' => PurchaseRequest::STATUS_PENDING_PAYMENT,
        ]);

        // Clear previous logs and deduplication cache from creation observer
        NotificationLog::truncate();
        DB::table('procurement_sms_logs')->truncate();
        Cache::flush();

        // Trigger gateway error
        $this->mockGatewayFailure = true;

        $service = app(ProcurementStatusOwnerService::class);
        // This must execute without throwing any exception!
        $service->dispatchStatusChangeSms($pr, PurchaseRequest::STATUS_PENDING_PAYMENT);

        // Verification: Error is logged in notification_log and procurement_sms_logs
        $failedLog = NotificationLog::where('request_id', $pr->id)->first();
        $this->assertNotNull($failedLog);
        $this->assertEquals('failed', $failedLog->status);
        $this->assertStringContainsString('Simulated afro-message gateway timeout', $failedLog->error);

        $procLog = DB::table('procurement_sms_logs')->where('purchase_request_id', $pr->id)->first();
        $this->assertNotNull($procLog);
        $this->assertEquals('failed', $procLog->status);
    }

    /**
     * Test 7: Fallback to Global Admin works when no role owner is found.
     */
    public function test_fallback_to_global_admin_when_no_owner_found(): void
    {
        $adminUser = $this->createUserWithPhone('Global Admin', 'admin@wechecha.com', '+251999999999');
        $adminRole = Role::create(['name' => 'global_admin']);
        $adminUser->assignRole($adminRole);

        // Stage requiring 'market_research', but no user has this role
        $pr = PurchaseRequest::create([
            'pr_no'  => 'PR-20261021-0005',
            'status' => PurchaseRequest::STATUS_PENDING_MARKETING,
        ]);

        $service = app(ProcurementStatusOwnerService::class);
        $service->dispatchStatusChangeSms($pr, PurchaseRequest::STATUS_PENDING_MARKETING);

        // Must fall back to Global Admin
        $this->assertCount(1, $this->sentMessages);
        $this->assertEquals('+251999999999', $this->sentMessages[0]['to']);
    }

    /**
     * Test 8: Deduplication guard prevents duplicate SMS within 60s.
     */
    public function test_no_duplicate_sms_sent_for_same_record_and_status(): void
    {
        $planningUser = $this->createUserWithPhone('Planning Lead', 'planning3@wechecha.com', '+251911111111');
        $role = Role::firstOrCreate(['name' => 'planning']);
        $planningUser->assignRole($role);

        $mr = MaterialRequest::create([
            'reference_number' => 'MR-20261021-0006',
            'status'           => 'submitted',
        ]);

        $service = app(ProcurementStatusOwnerService::class);

        // First trigger
        $service->dispatchStatusChangeSms($mr, 'submitted');
        $this->assertCount(1, $this->sentMessages);

        // Second duplicate trigger (e.g. page refresh / double click)
        $service->dispatchStatusChangeSms($mr, 'submitted');
        // Must still be 1! Duplicate is suppressed.
        $this->assertCount(1, $this->sentMessages);
    }

    /**
     * Test 9: Rejection sends an FYI SMS copy to the original requester.
     */
    public function test_fyi_message_sent_to_requester_on_rejection(): void
    {
        $requester = $this->createUserWithPhone('Eng. Alazar', 'alazar@wechecha.com', '+251988888888');
        $gmUser = $this->createUserWithPhone('General Manager', 'gm@wechecha.com', '+251900000000');
        $pmUser = $this->createUserWithPhone('Purchase Mgr', 'pm@wechecha.com', '+251912345678');
        $pmRole = Role::firstOrCreate(['name' => 'purchase_manager']);
        $pmUser->assignRole($pmRole);

        $pr = PurchaseRequest::create([
            'pr_no'        => 'PR-20261021-0007',
            'requested_by' => $requester->id,
            'status'       => PurchaseRequest::STATUS_REJECTED,
        ]);

        $service = app(ProcurementStatusOwnerService::class);
        $service->dispatchStatusChangeSms($pr, PurchaseRequest::STATUS_REJECTED, PurchaseRequest::STATUS_PENDING_GM, $gmUser);

        // Requester receives FYI notification
        $phonesSent = array_column($this->sentMessages, 'to');
        $this->assertContains('+251988888888', $phonesSent);
    }
}
