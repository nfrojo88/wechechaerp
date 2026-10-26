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
use App\Services\ProcurementTransitionNotifier;
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
                    return ['success' => false, 'error' => 'Simulated gateway timeout'];
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
        Schema::dropIfExists('project_user');
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

        Schema::create('project_user', function ($table) {
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('user_id');
            $table->primary(['project_id', 'user_id']);
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
            $table->string('priority')->default('normal');
            $table->string('status')->default('submitted');
            $table->date('required_date')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('planning_approval_status')->default('pending');
            $table->text('planning_rejection_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('material_request_items', function ($table) {
            $table->id();
            $table->unsignedBigInteger('material_request_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->decimal('quantity_requested', 15, 3)->default(1);
            $table->text('notes')->nullable();
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
            $table->text('rejection_reason')->nullable();
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
            $table->unsignedBigInteger('purchase_request_id')->nullable();
            $table->unsignedBigInteger('material_request_id')->nullable();
            $table->string('recipient_phone', 30);
            $table->string('recipient_role', 60);
            $table->text('message');
            $table->string('status');
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
        });
    }

    protected function createUserWithPhone(string $name, string $email, ?string $phone): User
    {
        $user = User::create([
            'name'      => $name,
            'email'     => $email,
            'phone'     => $phone,
            'is_active' => true,
        ]);

        Employee::create([
            'user_id'            => $user->id,
            'full_name'          => $name,
            'email'              => $email,
            'phone'              => $phone,
            'is_approved_by_gm'  => true,
            'gm_approval_status' => 'approved',
        ]);

        return $user;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ACCEPTANCE TESTS (Section 7: 1-14)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Acceptance Test 1:
     * Site Engineer creates an emergency MR -> Planning Manager gets an SMS
     * with [URGENT-EMERGENCY] within seconds.
     */
    public function test_01_site_engineer_creates_emergency_mr_sends_sms_to_planning_manager_with_urgent_emergency_prefix(): void
    {
        $planning = $this->createUserWithPhone('Planning Head', 'planning@wechecha.com', '+251911111111');
        $planning->assignRole(Role::firstOrCreate(['name' => 'planning']));

        $siteEng = $this->createUserWithPhone('Eng. Daniel', 'daniel@wechecha.com', '+251922222222');
        $siteEng->assignRole(Role::firstOrCreate(['name' => 'site_engineer']));

        $project = Project::create(['name' => 'Bole Tower Site']);

        $mr = MaterialRequest::create([
            'project_id'       => $project->id,
            'reference_number' => 'MR-EMERGENCY-2026-001',
            'created_by'       => $siteEng->id,
            'source'           => 'Emergency',
            'priority'         => 'emergency',
            'status'           => 'submitted',
        ]);

        $notifier = app(ProcurementTransitionNotifier::class);
        $notifier->notify($mr, 'submitted', null, $siteEng);

        $this->assertCount(1, $this->sentMessages);
        $this->assertEquals('+251911111111', $this->sentMessages[0]['to']);
        $this->assertStringContainsString('[URGENT-EMERGENCY]', $this->sentMessages[0]['message']);
        $this->assertStringContainsString('MR #MR-EMERGENCY-2026-001', $this->sentMessages[0]['message']);
        $this->assertStringContainsString('Action needed:', $this->sentMessages[0]['message']);
    }

    /**
     * Acceptance Test 2:
     * Planning approves -> only the project's Coordinator gets an SMS.
     */
    public function test_02_planning_approves_only_project_coordinator_gets_sms(): void
    {
        $coordProject = $this->createUserWithPhone('Project Coord. Yohannes', 'coord_proj@wechecha.com', '+251933333331');
        $coordOther   = $this->createUserWithPhone('Other Coord. Helen', 'coord_other@wechecha.com', '+251933333332');
        $coordRole    = Role::firstOrCreate(['name' => 'coordinator']);
        $coordProject->assignRole($coordRole);
        $coordOther->assignRole($coordRole);

        $project = Project::create(['name' => 'CMC Site']);
        // Assign only Yohannes to this project
        $project->users()->attach($coordProject->id);

        $mr = MaterialRequest::create([
            'project_id'       => $project->id,
            'reference_number' => 'MR-2026-0002',
            'status'           => 'submitted',
        ]);

        $this->sentMessages = [];
        Cache::flush();

        $notifier = app(ProcurementTransitionNotifier::class);
        $notifier->notify($mr, 'planning_approved', 'submitted');

        // MUST be exactly 1 SMS, and MUST go only to the project's assigned coordinator!
        $this->assertCount(1, $this->sentMessages);
        $this->assertEquals('+251933333331', $this->sentMessages[0]['to']);
        $this->assertNotEquals('+251933333332', $this->sentMessages[0]['to']);
    }

    /**
     * Acceptance Test 3:
     * Planning rejects -> only the Site Engineer gets an SMS with the reason.
     */
    public function test_03_planning_rejects_only_site_engineer_gets_sms_with_reason(): void
    {
        $siteEng = $this->createUserWithPhone('Eng. Solomon', 'solomon@wechecha.com', '+251922222223');
        $siteEng->assignRole(Role::firstOrCreate(['name' => 'site_engineer']));

        $mr = MaterialRequest::create([
            'reference_number'          => 'MR-2026-0003',
            'created_by'                => $siteEng->id,
            'status'                    => 'submitted',
            'planning_rejection_reason' => 'Quantity exceeds site excavation plan limits',
        ]);

        $this->sentMessages = [];
        Cache::flush();

        $notifier = app(ProcurementTransitionNotifier::class);
        $notifier->notify($mr, 'rejected', 'submitted', null, [
            'reason' => 'Quantity exceeds site excavation plan limits',
        ]);

        $this->assertCount(1, $this->sentMessages);
        $this->assertEquals('+251922222223', $this->sentMessages[0]['to']);
        $this->assertStringContainsString('Quantity exceeds site excavation plan limits', $this->sentMessages[0]['message']);
        $this->assertStringContainsString('Rejected', $this->sentMessages[0]['message']);
    }

    /**
     * Acceptance Test 4:
     * Planning "petty cash" -> Site Engineer gets an SMS.
     */
    public function test_04_planning_petty_cash_site_engineer_gets_sms(): void
    {
        $siteEng = $this->createUserWithPhone('Eng. Daniel', 'daniel4@wechecha.com', '+251922222224');
        $siteEng->assignRole(Role::firstOrCreate(['name' => 'site_engineer']));

        $mr = MaterialRequest::create([
            'reference_number' => 'MR-2026-0004',
            'created_by'       => $siteEng->id,
            'status'           => 'submitted',
        ]);

        $this->sentMessages = [];
        Cache::flush();

        $notifier = app(ProcurementTransitionNotifier::class);
        $notifier->notify($mr, 'approved_for_petty_cash', 'submitted');

        $this->assertCount(1, $this->sentMessages);
        $this->assertEquals('+251922222224', $this->sentMessages[0]['to']);
        $this->assertStringContainsString('Petty Cash', $this->sentMessages[0]['message']);
    }

    /**
     * Acceptance Test 5:
     * Coordinator sends to store -> only that target store's Store Manager gets an SMS.
     */
    public function test_05_coordinator_sends_to_store_only_target_store_manager_gets_sms(): void
    {
        $targetMgr = $this->createUserWithPhone('Manager Target Store', 'target_mgr@wechecha.com', '+251944444441');
        $otherMgr  = $this->createUserWithPhone('Manager Other Store', 'other_mgr@wechecha.com', '+251944444442');
        $smRole    = Role::firstOrCreate(['name' => 'store_manager']);
        $targetMgr->assignRole($smRole);
        $otherMgr->assignRole($smRole);

        $targetStore = Store::create(['name' => 'Target Store', 'manager_id' => $targetMgr->id]);
        $otherStore  = Store::create(['name' => 'Other Store', 'manager_id' => $otherMgr->id]);

        $mr = MaterialRequest::create([
            'reference_number'     => 'MR-2026-0005',
            'destination_store_id' => $targetStore->id,
            'status'               => 'planning_approved',
        ]);

        $this->sentMessages = [];
        Cache::flush();

        $notifier = app(ProcurementTransitionNotifier::class);
        $notifier->notify($mr, 'sent_to_store_manager', 'planning_approved');

        // MUST be sent only to Target Store's manager
        $this->assertCount(1, $this->sentMessages);
        $this->assertEquals('+251944444441', $this->sentMessages[0]['to']);
    }

    /**
     * Acceptance Test 6:
     * Store Manager rejects -> Planning Manager gets an SMS.
     */
    public function test_06_store_manager_rejects_planning_manager_gets_sms(): void
    {
        $planning = $this->createUserWithPhone('Planning Lead', 'planning6@wechecha.com', '+251911111111');
        $planning->assignRole(Role::firstOrCreate(['name' => 'planning']));

        $siteEng = $this->createUserWithPhone('Eng. Requester', 'req6@wechecha.com', '+251922222226');
        $siteEng->assignRole(Role::firstOrCreate(['name' => 'site_engineer']));

        $mr = MaterialRequest::create([
            'reference_number' => 'MR-2026-0006',
            'created_by'       => $siteEng->id,
            'status'           => 'sent_to_store_manager',
        ]);

        $this->sentMessages = [];
        Cache::flush();

        $notifier = app(ProcurementTransitionNotifier::class);
        $notifier->notify($mr, 'store_rejected', 'sent_to_store_manager', null, [
            'reason' => 'Defective batch specification in warehouse',
        ]);

        // Planning gets the action SMS
        $phones = array_column($this->sentMessages, 'to');
        $this->assertContains('+251911111111', $phones);
    }

    /**
     * Acceptance Test 7:
     * Store Manager "send to purchase" -> Procurement Manager gets an SMS.
     */
    public function test_07_store_manager_send_to_purchase_procurement_manager_gets_sms(): void
    {
        $pm = $this->createUserWithPhone('Procurement Mgr', 'pm7@wechecha.com', '+251955555555');
        $pm->assignRole(Role::firstOrCreate(['name' => 'purchase_manager']));

        $mr = MaterialRequest::create([
            'reference_number' => 'MR-2026-0007',
            'status'           => 'sent_to_store_manager',
        ]);

        $this->sentMessages = [];
        Cache::flush();

        $notifier = app(ProcurementTransitionNotifier::class);
        $notifier->notify($mr, 'sent_to_pr', 'sent_to_store_manager');

        $this->assertCount(1, $this->sentMessages);
        $this->assertEquals('+251955555555', $this->sentMessages[0]['to']);
    }

    /**
     * Acceptance Test 8:
     * Store Manager "create transfer" -> General Service gets an SMS.
     */
    public function test_08_store_manager_create_transfer_general_service_gets_sms(): void
    {
        $gs = $this->createUserWithPhone('General Service Fleet', 'gs8@wechecha.com', '+251966666666');
        $gs->assignRole(Role::firstOrCreate(['name' => 'general_service']));

        $mr = MaterialRequest::create([
            'reference_number' => 'MR-2026-0008',
            'status'           => 'sent_to_store_manager',
        ]);

        $this->sentMessages = [];
        Cache::flush();

        $notifier = app(ProcurementTransitionNotifier::class);
        $notifier->notify($mr, 'transferred', 'sent_to_store_manager');

        $phones = array_column($this->sentMessages, 'to');
        $this->assertContains('+251966666666', $phones);
    }

    /**
     * Acceptance Test 9:
     * Procurement Manager sends to team -> Procurement Team gets an SMS.
     */
    public function test_09_procurement_manager_sends_to_team_procurement_team_gets_sms(): void
    {
        $purchaser = $this->createUserWithPhone('Procurement Officer', 'buyer9@wechecha.com', '+251977777777');
        $purchaser->assignRole(Role::firstOrCreate(['name' => 'purchase']));

        $pr = PurchaseRequest::create([
            'pr_no'  => 'PR-2026-0009',
            'status' => PurchaseRequest::STATUS_PENDING_PROC_MANAGER,
        ]);

        $this->sentMessages = [];
        Cache::flush();

        $notifier = app(ProcurementTransitionNotifier::class);
        $notifier->notify($pr, PurchaseRequest::STATUS_PENDING_PROC_TEAM, PurchaseRequest::STATUS_PENDING_PROC_MANAGER);

        $this->assertCount(1, $this->sentMessages);
        $this->assertEquals('+251977777777', $this->sentMessages[0]['to']);
    }

    /**
     * Acceptance Test 10:
     * Procurement Manager sends back -> Store Manager gets an SMS.
     */
    public function test_10_procurement_manager_sends_back_store_manager_gets_sms(): void
    {
        $storeMgr = $this->createUserWithPhone('Store Mgr 10', 'sm10@wechecha.com', '+251944444440');
        $storeMgr->assignRole(Role::firstOrCreate(['name' => 'store_manager']));

        $store = Store::create(['name' => 'Main Warehouse', 'manager_id' => $storeMgr->id]);

        $pr = PurchaseRequest::create([
            'pr_no'    => 'PR-2026-0010',
            'store_id' => $store->id,
            'status'   => PurchaseRequest::STATUS_PENDING_PROC_MANAGER,
        ]);

        $this->sentMessages = [];
        Cache::flush();

        $notifier = app(ProcurementTransitionNotifier::class);
        $notifier->notify($pr, 'pm_returned_to_store', PurchaseRequest::STATUS_PENDING_PROC_MANAGER, null, [
            'reason' => 'Item specification is incomplete, re-check store stock',
        ]);

        $this->assertCount(1, $this->sentMessages);
        $this->assertEquals('+251944444440', $this->sentMessages[0]['to']);
    }

    /**
     * Acceptance Test 11:
     * Calling the same transition twice within 60s sends only ONE SMS per recipient.
     */
    public function test_11_calling_same_transition_twice_within_60s_sends_only_one_sms(): void
    {
        $planning = $this->createUserWithPhone('Planning Lead', 'planning11@wechecha.com', '+251911111111');
        $planning->assignRole(Role::firstOrCreate(['name' => 'planning']));

        $mr = MaterialRequest::create([
            'reference_number' => 'MR-2026-0011',
            'status'           => 'submitted',
        ]);

        $this->sentMessages = [];
        Cache::flush();

        $notifier = app(ProcurementTransitionNotifier::class);

        // First attempt
        $notifier->notify($mr, 'submitted');
        $this->assertCount(1, $this->sentMessages);

        // Second duplicate attempt (e.g. fast double-click or replay)
        $notifier->notify($mr, 'submitted');
        // Deduplication guard ensures count is still exactly 1
        $this->assertCount(1, $this->sentMessages);
    }

    /**
     * Acceptance Test 12:
     * If the SMS gateway is down, the user action still succeeds and the failure is logged.
     */
    public function test_12_gateway_failure_does_not_block_user_action_and_failure_is_logged(): void
    {
        $planning = $this->createUserWithPhone('Planning Lead', 'planning12@wechecha.com', '+251911111112');
        $planning->assignRole(Role::firstOrCreate(['name' => 'planning']));

        $mr = MaterialRequest::create([
            'reference_number' => 'MR-2026-0012',
            'status'           => 'submitted',
        ]);

        $this->sentMessages = [];
        Cache::flush();
        NotificationLog::truncate();
        DB::table('procurement_sms_logs')->truncate();

        // Simulate gateway timeout / error
        $this->mockGatewayFailure = true;

        $notifier = app(ProcurementTransitionNotifier::class);
        // This MUST execute and never throw an exception!
        $notifier->notify($mr, 'submitted');

        // Check error logging
        $failedLog = NotificationLog::where('request_id', $mr->id)->first();
        $this->assertNotNull($failedLog);
        $this->assertEquals('failed', $failedLog->status);
        $this->assertStringContainsString('Simulated gateway timeout', $failedLog->error);

        $procLog = DB::table('procurement_sms_logs')->where('material_request_id', $mr->id)->first();
        $this->assertNotNull($procLog);
        $this->assertEquals('failed', $procLog->status);
    }

    /**
     * Acceptance Test 13:
     * If an assigned person has no phone, it is logged as "skipped" and the action still succeeds.
     */
    public function test_13_assigned_person_without_phone_is_logged_as_skipped_and_action_succeeds(): void
    {
        // Assigned Cashier has NO phone number configured
        $cashierNoPhone = $this->createUserWithPhone('Cashier No Phone', 'cashier_no_phone@wechecha.com', null);
        $cashierNoPhone->assignRole(Role::firstOrCreate(['name' => 'finance']));

        $pr = PurchaseRequest::create([
            'pr_no'                     => 'PR-2026-0013',
            'status'                    => PurchaseRequest::STATUS_PENDING_PAYMENT,
            'assigned_finance_staff_id' => $cashierNoPhone->id,
        ]);

        $this->sentMessages = [];
        Cache::flush();
        NotificationLog::truncate();
        DB::table('procurement_sms_logs')->truncate();

        $notifier = app(ProcurementTransitionNotifier::class);
        // Must succeed without throwing
        $notifier->notify($pr, 'finance_assigned_payment', null, null, [
            'assigned_user_id' => $cashierNoPhone->id,
        ]);

        // Zero SMS sent because phone was absent
        $this->assertCount(0, $this->sentMessages);

        // Verification: It is logged as "skipped"
        $skippedLog = NotificationLog::where('request_id', $pr->id)->first();
        $this->assertNotNull($skippedLog);
        $this->assertEquals('skipped', $skippedLog->status);
        $this->assertStringContainsString('Skipped: Recipient has no phone number', $skippedLog->error);

        $procLog = DB::table('procurement_sms_logs')->where('purchase_request_id', $pr->id)->first();
        $this->assertNotNull($procLog);
        $this->assertEquals('skipped', $procLog->status);
    }

    /**
     * Acceptance Test 14:
     * Every status in the audit table (rows 1-35) has a mapped owner.
     * Add a test that fails if any status has no mapping.
     */
    public function test_14_every_status_in_audit_table_has_mapped_owner(): void
    {
        $auditStatuses = ProcurementStatusOwnerService::getAuditStatuses();
        $statusMap     = ProcurementStatusOwnerService::getStatusMap();

        // 1. Audit Table Rows 1 to 35
        $this->assertCount(35, $auditStatuses, "Audit table must have exactly 35 lifecycle rows.");

        foreach ($auditStatuses as $rowNum => $row) {
            $mapKey = $row['map_key'];
            $this->assertArrayHasKey($mapKey, $statusMap, "Audit row #{$rowNum} [{$mapKey}] has no mapped owner definition in getStatusMap().");
            $this->assertNotEmpty($statusMap[$mapKey]['owner_role'], "Audit row #{$rowNum} [{$mapKey}] has an empty owner_role.");
        }

        // 2. All PurchaseRequest state machine constants must be mapped
        foreach (PurchaseRequest::allStatuses() as $prStatus) {
            if ($prStatus === PurchaseRequest::STATUS_DRAFT) {
                continue; // Drafts do not dispatch SMS
            }
            $mapKey = "pr:{$prStatus}";
            $this->assertArrayHasKey($mapKey, $statusMap, "PurchaseRequest status constant [{$prStatus}] is missing from getStatusMap().");
            $this->assertNotEmpty($statusMap[$mapKey]['owner_role'], "PurchaseRequest status [{$prStatus}] has an empty owner_role.");
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SUPPLEMENTARY INTEGRATION TESTS
    // ─────────────────────────────────────────────────────────────────────────

    public function test_pr_gm_approval_routes_to_finance_head(): void
    {
        $financeHead = $this->createUserWithPhone('CFO Dawit', 'cfo@wechecha.com', '+251944444444');
        $financeHead->assignRole(Role::firstOrCreate(['name' => 'finance_head']));

        $pr = PurchaseRequest::create([
            'pr_no'             => 'PR-20261021-0001',
            'direct_buy_amount' => 85000.00,
            'status'            => PurchaseRequest::STATUS_PENDING_PAYMENT,
        ]);
        $pr->estimated_total_cost = 85000.00;

        $this->sentMessages = [];
        Cache::flush();

        app(ProcurementTransitionNotifier::class)->notify($pr, 'gm_approved_cash', PurchaseRequest::STATUS_PENDING_GM, null, [
            'amount' => 85000.00,
        ]);

        $this->assertCount(1, $this->sentMessages);
        $this->assertEquals('+251944444444', $this->sentMessages[0]['to']);
        $this->assertStringContainsString('85,000.00 ETB', $this->sentMessages[0]['message']);
    }

    public function test_direct_cashier_assignment_routes_sms_to_assigned_staff(): void
    {
        $cashier = $this->createUserWithPhone('Cashier Beth', 'beth@wechecha.com', '+251966666666');
        $cashier->assignRole(Role::firstOrCreate(['name' => 'finance']));

        $pr = PurchaseRequest::create([
            'pr_no'                     => 'PR-20261021-0002',
            'status'                    => PurchaseRequest::STATUS_PENDING_PAYMENT,
            'direct_buy_amount'         => 12000.00,
            'assigned_finance_staff_id' => $cashier->id,
        ]);

        $this->sentMessages = [];
        Cache::flush();

        app(ProcurementTransitionNotifier::class)->notify($pr, 'finance_assigned_payment', null, null, [
            'assigned_user_id' => $cashier->id,
            'amount'           => 12000.00,
        ]);

        $this->assertCount(1, $this->sentMessages);
        $this->assertEquals('+251966666666', $this->sentMessages[0]['to']);
    }

    public function test_driver_booking_routes_sms_to_assigned_driver(): void
    {
        $driverEmp = Employee::create([
            'full_name'          => 'Driver Tariku',
            'role_title'         => 'Truck Driver',
            'phone'              => '+251977777777',
            'is_approved_by_gm'  => true,
            'gm_approval_status' => 'approved',
        ]);

        $pr = PurchaseRequest::create([
            'pr_no'  => 'PR-20261021-0003',
            'status' => 'pending_driver_booking',
        ]);

        $this->sentMessages = [];
        Cache::flush();

        app(ProcurementTransitionNotifier::class)->notify($pr, 'driver_booked', 'pending_driver_booking', null, [
            'driver_employee_id' => $driverEmp->id,
        ]);

        $this->assertCount(1, $this->sentMessages);
        $this->assertEquals('+251977777777', $this->sentMessages[0]['to']);
    }

    public function test_fallback_to_global_admin_when_no_owner_found(): void
    {
        $adminUser = $this->createUserWithPhone('Global Admin', 'admin@wechecha.com', '+251999999999');
        $adminUser->assignRole(Role::firstOrCreate(['name' => 'global_admin']));

        // Stage requiring role with NO assigned users
        $pr = PurchaseRequest::create([
            'pr_no'  => 'PR-20261021-0005',
            'status' => PurchaseRequest::STATUS_PENDING_MARKETING,
        ]);

        $this->sentMessages = [];
        Cache::flush();

        app(ProcurementTransitionNotifier::class)->notify($pr, PurchaseRequest::STATUS_PENDING_MARKETING);

        $this->assertCount(1, $this->sentMessages);
        $this->assertEquals('+251999999999', $this->sentMessages[0]['to']);
    }

    public function test_action_taken_on_emergency_mr_removes_it_from_emergency_queue(): void
    {
        $mr = MaterialRequest::create([
            'reference_number'         => 'MR-EMERGENCY-001',
            'status'                   => 'pending_planning',
            'planning_approval_status' => 'pending',
            'source'                   => 'Emergency',
        ]);
        $mr->items()->create(['quantity_requested' => 5]);

        $pr = PurchaseRequest::create([
            'pr_no'               => 'PR-EMERGENCY-001',
            'material_request_id' => $mr->id,
            'status'              => PurchaseRequest::STATUS_PENDING_PLANNING,
            'current_owner_role'  => 'planning',
        ]);

        $getPlanningQueue = fn() => MaterialRequest::whereHas('items')
            ->where('planning_approval_status', 'pending')
            ->whereNotIn('status', ['planning_approved', 'sent_to_store_manager', 'sent_to_pr', 'transferred', 'approved', 'rejected', 'cancelled', 'completed'])
            ->get();

        $this->assertCount(1, $getPlanningQueue());

        $mr->update([
            'planning_approval_status' => 'approved',
            'status'                   => 'planning_approved',
        ]);

        $this->assertCount(0, $getPlanningQueue());
    }
}
