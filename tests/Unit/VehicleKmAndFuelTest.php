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
use App\Services\ProcurementSmsService;
use App\Services\VehicleFuelService;
use App\Services\VehicleKmService;
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
            $table->integer('current_odometer_km');
            $table->string('fuel_type', 30)->default('diesel');
            $table->decimal('liters_requested', 10, 2);
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
}
