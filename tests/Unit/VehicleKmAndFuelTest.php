<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\ExpenseRequest;
use App\Models\FixedAsset;
use App\Models\FixedAssetUnit;
use App\Models\User;
use App\Models\VehicleFuelRequest;
use App\Models\VehicleKmLog;
use App\Models\VehicleReminder;
use App\Models\VehicleReminderHistory;
use App\Http\Controllers\VehicleFuelRequestController;
use App\Services\ProcurementSmsService;
use App\Services\VehicleFuelService;
use App\Services\VehicleKmService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VehicleKmAndFuelTest extends TestCase
{
    protected VehicleKmService $kmService;
    protected VehicleFuelService $fuelService;

    protected function setUp(): void
    {
        parent::setUp();

        DB::statement('PRAGMA foreign_keys = OFF;');

        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('vehicle_fuel_requests');
        Schema::dropIfExists('vehicle_km_logs');
        Schema::dropIfExists('vehicle_reminder_history');
        Schema::dropIfExists('vehicle_reminders');
        Schema::dropIfExists('expense_requests');
        Schema::dropIfExists('fixed_asset_units');
        Schema::dropIfExists('fixed_assets');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('users');
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
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('employees', function ($table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('full_name');
            $table->string('department')->nullable();
            $table->string('role_title')->nullable();
            $table->string('phone')->nullable();
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

        Schema::create('fixed_assets', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('category')->default('Vehicles');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('fixed_asset_units', function ($table) {
            $table->id();
            $table->unsignedBigInteger('fixed_asset_id');
            $table->string('unit_code')->unique();
            $table->string('plate_number')->nullable();
            $table->unsignedBigInteger('assigned_to_employee_id')->nullable();
            $table->string('status')->default('assigned');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('vehicle_reminders', function ($table) {
            $table->id();
            $table->unsignedBigInteger('fixed_asset_unit_id');
            $table->unsignedBigInteger('fixed_asset_id');
            $table->string('reminder_type', 50);
            $table->string('status', 30)->default('active');
            $table->integer('current_odometer_km')->nullable();
            $table->integer('last_service_km')->nullable();
            $table->integer('service_interval_km')->nullable();
            $table->integer('next_service_km')->nullable();
            $table->integer('reminder_threshold_km')->default(500);
            $table->date('bolo_expiry_date')->nullable();
            $table->date('insurance_expiry_date')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('vehicle_reminder_history', function ($table) {
            $table->id();
            $table->unsignedBigInteger('vehicle_reminder_id');
            $table->unsignedBigInteger('fixed_asset_unit_id');
            $table->string('reminder_type', 50);
            $table->string('action', 50);
            $table->json('snapshot')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('performed_by')->nullable();
            $table->timestamp('performed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('vehicle_km_logs', function ($table) {
            $table->id();
            $table->unsignedBigInteger('fixed_asset_unit_id');
            $table->unsignedBigInteger('driver_employee_id')->nullable();
            $table->unsignedBigInteger('logged_by_user_id')->nullable();
            $table->integer('odometer_km');
            $table->date('km_date');
            $table->string('odometer_photo', 500)->nullable();
            $table->string('source', 50)->default('direct_log');
            $table->integer('km_difference')->nullable();
            $table->boolean('is_large_jump')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('expense_requests', function ($table) {
            $table->id();
            $table->string('request_number')->unique();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->string('category');
            $table->string('service_type')->nullable();
            $table->string('other_reason')->nullable();
            $table->decimal('amount', 15, 2);
            $table->decimal('gross_amount', 15, 2)->nullable();
            $table->decimal('net_amount', 15, 2)->nullable();
            $table->string('vat_type', 30)->default('none');
            $table->decimal('vat_amount', 15, 2)->default(0);
            $table->boolean('is_bank_payment')->default(false);
            $table->text('description')->nullable();
            $table->string('attachment')->nullable();
            $table->string('status');
            $table->string('payment_reference')->nullable();
            $table->text('payment_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('vehicle_fuel_requests', function ($table) {
            $table->id();
            $table->string('request_no', 50)->unique();
            $table->unsignedBigInteger('fixed_asset_unit_id');
            $table->unsignedBigInteger('driver_employee_id')->nullable();
            $table->unsignedBigInteger('requested_by_user_id')->nullable();
            $table->unsignedBigInteger('driver_booking_id')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('expense_request_id')->nullable();
            $table->integer('current_odometer_km')->nullable();
            $table->boolean('is_km_skipped')->default(false);
            $table->string('fuel_type', 30)->default('diesel');
            $table->decimal('liters_requested', 10, 2);
            $table->decimal('price_per_liter', 10, 2)->nullable();
            $table->decimal('estimated_amount', 15, 2);
            $table->text('reason')->nullable();
            $table->string('status', 50)->default('pending_finance');
            $table->boolean('service_due_warning')->default(false);
            $table->integer('service_due_km')->nullable();
            $table->string('receipt_photo', 500)->nullable();
            $table->integer('receipt_odometer_km')->nullable();
            $table->decimal('actual_liters', 10, 2)->nullable();
            $table->decimal('actual_amount', 15, 2)->nullable();
            $table->string('fuel_station_name', 255)->nullable();
            $table->string('fuel_station_tin', 50)->nullable();
            $table->string('fs_no', 50)->nullable();
            $table->string('mrc_no', 50)->nullable();
            $table->string('tax_type', 30)->default('no_vat');
            $table->string('no_vat_reason_code', 50)->nullable();
            $table->string('no_vat_reason_note', 255)->nullable();
            $table->timestamp('receipt_uploaded_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
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

        $this->kmService = app(VehicleKmService::class);
        $this->fuelService = app(VehicleFuelService::class);
    }

    /**
     * Test 1: KM cannot go down.
     */
    public function test_km_cannot_go_down(): void
    {
        $asset = FixedAsset::create(['name' => 'Toyota Hilux Pickup', 'category' => 'Vehicles']);
        $unit  = FixedAssetUnit::create([
            'fixed_asset_id' => $asset->id,
            'unit_code'      => 'VH-001',
            'plate_number'   => 'AA-3-12345',
        ]);

        // First initial log at 50,000 KM
        $this->kmService->recordKm(
            unitId: $unit->id,
            newOdometerKm: 50000,
            kmDate: '2026-10-20'
        );

        $this->assertEquals(50000, $this->kmService->getLastOdometer($unit->id));

        // Attempting to log 49,000 KM must throw ValidationException
        $this->expectException(ValidationException::class);
        $this->kmService->recordKm(
            unitId: $unit->id,
            newOdometerKm: 49000,
            kmDate: '2026-10-21'
        );
    }

    /**
     * Test 2: Reminder updates after a KM log.
     */
    public function test_reminder_updates_after_km_log(): void
    {
        $asset = FixedAsset::create(['name' => 'Isuzu NPR Truck', 'category' => 'Vehicles']);
        $unit  = FixedAssetUnit::create([
            'fixed_asset_id' => $asset->id,
            'unit_code'      => 'VH-002',
            'plate_number'   => 'ET-3-88888',
        ]);

        // Create service reminder: next service at 60,000 KM with threshold 500 KM
        $reminder = VehicleReminder::create([
            'fixed_asset_unit_id'   => $unit->id,
            'fixed_asset_id'        => $asset->id,
            'reminder_type'         => VehicleReminder::TYPE_SERVICE_KM,
            'status'                => 'active',
            'current_odometer_km'   => 55000,
            'last_service_km'       => 50000,
            'service_interval_km'   => 10000,
            'next_service_km'       => 60000,
            'reminder_threshold_km' => 500,
        ]);

        // 1. Log KM at 58,000 -> Remaining = 2,000 KM (Healthy / Active)
        $this->kmService->recordKm(unitId: $unit->id, newOdometerKm: 58000, kmDate: '2026-10-20');
        $reminder->refresh();
        $this->assertEquals(58000, $reminder->current_odometer_km);
        $this->assertEquals(VehicleReminder::STATUS_ACTIVE, $reminder->status);

        // 2. Log KM at 59,600 -> Remaining = 400 KM <= 500 KM (Due Soon)
        $this->kmService->recordKm(unitId: $unit->id, newOdometerKm: 59600, kmDate: '2026-10-21');
        $reminder->refresh();
        $this->assertEquals(59600, $reminder->current_odometer_km);
        $this->assertEquals(VehicleReminder::STATUS_DUE_SOON, $reminder->status);

        // 3. Log KM at 60,100 -> Remaining = -100 KM (Overdue)
        $this->kmService->recordKm(unitId: $unit->id, newOdometerKm: 60100, kmDate: '2026-10-21');
        $reminder->refresh();
        $this->assertEquals(60100, $reminder->current_odometer_km);
        $this->assertEquals(VehicleReminder::STATUS_OVERDUE, $reminder->status);

        // 4. Verify history log was created
        $this->assertTrue(VehicleReminderHistory::where('fixed_asset_unit_id', $unit->id)->exists());
    }

    /**
     * Test 3: Expense request is created and routed directly to the Finance Head.
     */
    public function test_expense_request_created_and_routed_to_finance_head(): void
    {
        $user = User::create(['name' => 'Driver Abebe', 'email' => 'driver@wechecha.com']);
        $driverEmp = Employee::create([
            'user_id'    => $user->id,
            'full_name'  => 'Abebe Kebede',
            'department' => 'Driver Department (General Service)',
            'role_title' => 'Company Driver',
        ]);

        $asset = FixedAsset::create(['name' => 'Toyota Land Cruiser', 'category' => 'Vehicles']);
        $unit  = FixedAssetUnit::create([
            'fixed_asset_id'          => $asset->id,
            'unit_code'               => 'VH-003',
            'plate_number'            => 'AA-2-99999',
            'assigned_to_employee_id' => $driverEmp->id,
        ]);

        // Submit fuel request
        $fuelReq = $this->fuelService->createFuelRequest([
            'fixed_asset_unit_id'  => $unit->id,
            'current_odometer_km'  => 35000,
            'fuel_type'            => 'diesel',
            'liters_requested'     => 60,
            'estimated_amount'     => 6600.00,
            'reason'               => 'Material transport to site',
            'driver_employee_id'   => $driverEmp->id,
            'requested_by_user_id' => $user->id,
        ]);

        // Verify fuel request creation
        $this->assertNotNull($fuelReq->id);
        $this->assertEquals('diesel', $fuelReq->fuel_type);
        $this->assertEquals(60, $fuelReq->liters_requested);
        $this->assertEquals(6600.00, $fuelReq->estimated_amount);

        // Verify KM log was created automatically
        $kmLog = VehicleKmLog::where('fixed_asset_unit_id', $unit->id)->first();
        $this->assertNotNull($kmLog);
        $this->assertEquals(35000, $kmLog->odometer_km);
        $this->assertEquals(VehicleKmLog::SOURCE_FUEL_REQUEST, $kmLog->source);

        // Verify ExpenseRequest was auto-generated and routed directly to Finance Head
        $exp = $fuelReq->expenseRequest;
        $this->assertNotNull($exp);
        $this->assertEquals(ExpenseRequest::CATEGORY_TRANSPORT, $exp->category);
        $this->assertEquals(6600.00, $exp->amount);
        $this->assertEquals(ExpenseRequest::STATUS_APPROVED_ASSIGNED, $exp->status);
        $this->assertStringContainsString('Vehicle Fuel', $exp->service_type);
        $this->assertStringContainsString($fuelReq->request_no, $exp->description);
    }

    /**
     * Test 4: Attaching fuel station receipt closes request and syncs post-fueling KM.
     */
    public function test_attaching_receipt_closes_request(): void
    {
        $user = User::create(['name' => 'Driver Tesfaye', 'email' => 'driver2@wechecha.com']);
        $asset = FixedAsset::create(['name' => 'Nissan Patrol', 'category' => 'Vehicles']);
        $unit  = FixedAssetUnit::create([
            'fixed_asset_id' => $asset->id,
            'unit_code'      => 'VH-004',
            'plate_number'   => 'ET-2-55555',
        ]);

        $fuelReq = $this->fuelService->createFuelRequest([
            'fixed_asset_unit_id'  => $unit->id,
            'current_odometer_km'  => 40000,
            'fuel_type'            => 'benzine',
            'liters_requested'     => 40,
            'estimated_amount'     => 4400.00,
            'reason'               => 'Supervision trip',
            'requested_by_user_id' => $user->id,
        ]);

        $this->assertEquals(VehicleFuelRequest::STATUS_PENDING_FINANCE, $fuelReq->status);

        // Attach receipt
        $closed = $this->fuelService->attachReceiptAndClose($fuelReq, [
            'receipt_photo'       => 'uploads/receipts/sample_fuel_receipt.jpg',
            'receipt_odometer_km' => 40150,
            'actual_liters'       => 42.5,
            'actual_amount'       => 4675.00,
            'fuel_station_name'   => 'TotalEnergies Bole Medhanialem',
            'fuel_station_tin'    => '0045678912',
        ]);

        $this->assertEquals(VehicleFuelRequest::STATUS_CLOSED, $closed->status);
        $this->assertNotNull($closed->closed_at);
        $this->assertEquals('TotalEnergies Bole Medhanialem', $closed->fuel_station_name);
        $this->assertEquals('0045678912', $closed->fuel_station_tin);

        // Check mileage calculation (150 KM / 42.5 L = 3.53 KM/L)
        $this->assertEquals(150, $closed->km_traveled);
        $this->assertEquals(3.53, $closed->km_per_liter);

        // Verify updated KM log at 40,150 KM was recorded
        $this->assertEquals(40150, $this->kmService->getLastOdometer($unit->id));
    }

    public function test_general_admin_can_request_fuel_with_total_money_and_price_per_liter_skipping_km()
    {
        $admin = User::create([
            'name'  => 'General Admin User',
            'email' => 'admin_fuel_' . uniqid() . '@wechecha.com',
        ]);

        $asset = FixedAsset::create(['name' => 'Toyota Hilux Double Cab', 'category' => 'Vehicles']);
        $unit  = FixedAssetUnit::create([
            'fixed_asset_id' => $asset->id,
            'unit_code'      => 'VH-ADM-001',
            'plate_number'   => 'ET-3-88899',
        ]);

        // Prior odometer reading was 50,000 KM
        VehicleKmLog::create([
            'fixed_asset_unit_id' => $unit->id,
            'odometer_km'         => 50000,
            'km_date'             => '2026-10-01',
            'source'              => 'initial',
        ]);

        $initialLogsCount = VehicleKmLog::where('fixed_asset_unit_id', $unit->id)->count();

        // Admin submits request: Total money = 5,500 ETB, Price per liter = 110.00 ETB.
        // Expected fulfill liters: 5,500 / 110 = 50.00 Liters. KM is skipped!
        $totalMoney    = 5500.00;
        $pricePerLiter = 110.00;

        $fuelReq = $this->fuelService->createFuelRequest([
            'fixed_asset_unit_id'  => $unit->id,
            'skip_km'              => true,
            'price_per_liter'      => $pricePerLiter,
            'fuel_type'            => 'diesel',
            'estimated_amount'     => $totalMoney,
            'reason'               => 'Administrative project supervision fuel allocation',
            'requested_by_user_id' => $admin->id,
        ]);

        $this->assertNotNull($fuelReq);
        $this->assertTrue((bool)$fuelReq->is_km_skipped, 'Request must flag is_km_skipped = true.');
        $this->assertEquals(110.00, (float)$fuelReq->price_per_liter);
        $this->assertEquals(5500.00, (float)$fuelReq->estimated_amount);
        $this->assertEquals(50.00, (float)$fuelReq->liters_requested, 'Fulfill liters must be calculated as total money / price per liter (5500/110 = 50L).');
        $this->assertEquals(VehicleFuelRequest::STATUS_PENDING_FINANCE, $fuelReq->status);

        // Verify that no new KM log was added (KM was skipped!)
        $afterLogsCount = VehicleKmLog::where('fixed_asset_unit_id', $unit->id)->count();
        $this->assertEquals($initialLogsCount, $afterLogsCount, 'No new KM log should be created when KM is skipped.');
        $this->assertEquals(50000, $this->kmService->getLastOdometer($unit->id), 'Vehicle last odometer reading should remain intact at 50,000 KM.');

        // Verify linked ExpenseRequest was automatically generated for Finance Head
        $this->assertNotNull($fuelReq->expense_request_id);
        $expense = ExpenseRequest::find($fuelReq->expense_request_id);
        $this->assertNotNull($expense);
        $this->assertEquals(5500.00, (float)$expense->amount);
        $this->assertEquals(5500.00, (float)$expense->net_amount);
        $this->assertStringContainsString('50 L diesel', $expense->description);
        $this->assertStringContainsString('110.00 ETB/L', $expense->description);
        $this->assertStringContainsString('KM Skipped', $expense->description);
        $this->assertEquals(ExpenseRequest::STATUS_APPROVED_ASSIGNED, $expense->status);
    }

    public function test_controller_store_allows_general_admin_to_skip_km_and_denies_regular_user()
    {
        $roleAdmin = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::create([
            'name'  => 'Admin Chief',
            'email' => 'admin_chief_' . uniqid() . '@wechecha.com',
        ]);
        $admin->assignRole($roleAdmin);

        $asset = FixedAsset::create(['name' => 'Isuzu NPR Truck', 'category' => 'Vehicles']);
        $unit  = FixedAssetUnit::create([
            'fixed_asset_id' => $asset->id,
            'unit_code'      => 'VH-TRK-001',
            'plate_number'   => 'ET-3-99999',
        ]);

        $controller = new VehicleFuelRequestController($this->fuelService, $this->kmService);

        // 1. General Admin submits request with skip_km = 1, estimated_amount = 6000, price_per_liter = 120 (NO odometer KM)
        Auth::login($admin);
        $reqAdmin = Request::create(route('vehicle-fuel-requests.store'), 'POST', [
            'fixed_asset_unit_id' => $unit->id,
            'skip_km'             => '1',
            'estimated_amount'    => 6000.00,
            'price_per_liter'     => 120.00,
            'fuel_type'           => 'diesel',
            'reason'              => 'Emergency logistics dispatch',
        ]);
        app()->instance('request', $reqAdmin);

        $response = $controller->store($reqAdmin);
        $this->assertEquals(302, $response->getStatusCode());

        $created = VehicleFuelRequest::where('fixed_asset_unit_id', $unit->id)->latest('id')->first();
        $this->assertNotNull($created);
        $this->assertTrue((bool)$created->is_km_skipped);
        $this->assertEquals(50.00, (float)$created->liters_requested, '6000 ETB / 120 ETB/L must calculate 50 Liters.');
        $this->assertEquals(6000.00, (float)$created->estimated_amount);
        $this->assertEquals(120.00, (float)$created->price_per_liter);

        // 2. Regular user (Driver with no admin role) attempts to submit without odometer KM
        $driverUser = User::create([
            'name'  => 'Standard Driver',
            'email' => 'driver_' . uniqid() . '@wechecha.com',
        ]);
        $driverEmp = Employee::create([
            'user_id'   => $driverUser->id,
            'full_name' => 'Standard Driver',
        ]);
        $unit->update(['assigned_to_employee_id' => $driverEmp->id]);

        Auth::login($driverUser);

        $reqDriver = Request::create(route('vehicle-fuel-requests.store'), 'POST', [
            'fixed_asset_unit_id' => $unit->id,
            'skip_km'             => '1', // Even if driver sends skip_km=1, controller must ignore it because driver is not general admin!
            'estimated_amount'    => 2000.00,
            'fuel_type'           => 'diesel',
            'liters_requested'    => 20.0,
            'reason'              => 'Trip to site',
        ]);
        app()->instance('request', $reqDriver);

        $this->expectException(ValidationException::class);
        $controller->store($reqDriver);
    }
}
