<?php

namespace Tests\Unit;

use App\Http\Controllers\ExpenseRequestController;
use App\Http\Controllers\VehicleFuelRequestController;
use App\Models\ExpenseHubVatDecision;
use App\Models\ExpenseRequest;
use App\Models\FixedAsset;
use App\Models\FixedAssetUnit;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\User;
use App\Models\VehicleFuelRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FuelRequestExpDeleteTest extends TestCase
{
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        DB::statement('PRAGMA foreign_keys = OFF;');

        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('receipt_items');
        Schema::dropIfExists('receipts');
        Schema::dropIfExists('expense_hub_vat_audit_logs');
        Schema::dropIfExists('expense_hub_vat_decisions');
        Schema::dropIfExists('expense_requests');
        Schema::dropIfExists('vehicle_fuel_requests');
        Schema::dropIfExists('fixed_asset_units');
        Schema::dropIfExists('fixed_assets');
        Schema::dropIfExists('users');

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
            $table->string('password')->default('password');
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
            $table->foreignId('fixed_asset_id');
            $table->string('unit_code')->default('U-01');
            $table->string('plate_number')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('expense_requests', function ($table) {
            $table->id();
            $table->string('request_number', 50)->unique();
            $table->unsignedBigInteger('user_id');
            $table->string('category', 50)->default('Transport');
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('gross_amount', 12, 2)->default(0);
            $table->decimal('vat_amount', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2)->default(0);
            $table->string('status', 50)->default('Pending (HR Review)');
            $table->text('description')->nullable();
            $table->string('other_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('vehicle_fuel_requests', function ($table) {
            $table->id();
            $table->string('request_no', 50)->unique();
            $table->unsignedBigInteger('fixed_asset_unit_id');
            $table->unsignedBigInteger('requested_by_user_id');
            $table->unsignedBigInteger('expense_request_id')->nullable();
            $table->integer('current_odometer_km')->default(0);
            $table->boolean('is_km_skipped')->default(false);
            $table->decimal('price_per_liter', 10, 2)->nullable();
            $table->string('fuel_type', 30)->default('diesel');
            $table->decimal('liters_requested', 8, 2)->default(0);
            $table->decimal('estimated_amount', 12, 2)->default(0);
            $table->string('status', 40)->default('pending_finance');
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('expense_hub_vat_decisions', function ($table) {
            $table->id();
            $table->string('source_type');
            $table->unsignedBigInteger('source_id');
            $table->string('source_reference')->nullable();
            $table->string('vat_status')->default('vat_applies');
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->decimal('vat_amount', 18, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('expense_hub_vat_audit_logs', function ($table) {
            $table->id();
            $table->unsignedBigInteger('decision_id');
            $table->string('action');
            $table->timestamps();
        });

        Schema::create('receipts', function ($table) {
            $table->id();
            $table->string('receipt_number')->unique();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->string('file_path', 500)->nullable();
            $table->string('purchasable_type')->nullable();
            $table->unsignedBigInteger('purchasable_id')->nullable();
            $table->string('status')->default('approved');
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('vat_amount', 12, 2)->default(0);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('receipt_items', function ($table) {
            $table->id();
            $table->unsignedBigInteger('receipt_id');
            $table->string('item_description');
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->timestamps();
        });

        $this->adminUser = User::create([
            'name' => 'General Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password')
        ]);

        $role = DB::table('roles')->insertGetId([
            'name' => 'admin',
            'guard_name' => 'web'
        ]);

        DB::table('model_has_roles')->insert([
            'role_id' => $role,
            'model_type' => User::class,
            'model_id' => $this->adminUser->id
        ]);
    }

    /**
     * Test that target request EXP-FUEL-2026-0273 and all linked records are completely purged.
     */
    public function test_target_request_exp_fuel_2026_0273_is_purged_by_self_healing_and_migration()
    {
        $asset = FixedAsset::create(['name' => 'Toyota Hilux', 'category' => 'Vehicles']);
        $unit  = FixedAssetUnit::create(['fixed_asset_id' => $asset->id, 'plate_number' => 'AA-12345']);

        // 1. Seed the target EXP-FUEL-2026-0273
        $expenseRequest = ExpenseRequest::create([
            'request_number' => 'EXP-FUEL-2026-0273',
            'user_id' => $this->adminUser->id,
            'category' => 'Transport',
            'amount' => 5000,
            'gross_amount' => 5000,
            'net_amount' => 5000,
            'status' => 'Pending (HR Review)',
            'description' => 'Vehicle fuel request for AA-12345'
        ]);

        $fuelRequest = VehicleFuelRequest::create([
            'request_no' => 'VFR-2026-0273',
            'fixed_asset_unit_id' => $unit->id,
            'requested_by_user_id' => $this->adminUser->id,
            'expense_request_id' => $expenseRequest->id,
            'liters_requested' => 50,
            'estimated_amount' => 5000,
            'status' => VehicleFuelRequest::STATUS_PENDING_FINANCE,
            'reason' => 'Expense ref: EXP-FUEL-2026-0273'
        ]);

        $receipt = Receipt::create([
            'receipt_number' => 'RCP-FUEL-20261022-' . $fuelRequest->id,
            'purchasable_type' => ExpenseRequest::class,
            'purchasable_id' => $expenseRequest->id,
            'total_amount' => 5000,
            'description' => 'Station receipt for EXP-FUEL-2026-0273'
        ]);

        ReceiptItem::create([
            'receipt_id' => $receipt->id,
            'item_description' => 'Diesel fuel',
            'total_amount' => 5000
        ]);

        $decision = ExpenseHubVatDecision::create([
            'source_type' => 'expense_request',
            'source_id' => $expenseRequest->id,
            'vat_status' => 'no_vat',
            'total_amount' => 5000
        ]);

        DB::table('expense_hub_vat_audit_logs')->insert([
            'decision_id' => $decision->id,
            'action' => 'decision_created',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Verify seeded state exists
        $this->assertEquals(1, ExpenseRequest::where('request_number', 'EXP-FUEL-2026-0273')->count());
        $this->assertEquals(1, VehicleFuelRequest::where('expense_request_id', $expenseRequest->id)->count());
        $this->assertEquals(1, Receipt::where('purchasable_id', $expenseRequest->id)->count());
        $this->assertEquals(1, ReceiptItem::where('receipt_id', $receipt->id)->count());
        $this->assertEquals(1, ExpenseHubVatDecision::where('source_id', $expenseRequest->id)->count());

        // Execute migration
        $migration = require database_path('migrations/2026_10_22_080000_delete_fuel_request_exp_fuel_2026_0273.php');
        $migration->up();

        // Verify everything has been completely purged
        $this->assertEquals(0, DB::table('expense_requests')->where('request_number', 'EXP-FUEL-2026-0273')->count());
        $this->assertEquals(0, DB::table('vehicle_fuel_requests')->where('id', $fuelRequest->id)->count());
        $this->assertEquals(0, DB::table('receipts')->where('id', $receipt->id)->count());
        $this->assertEquals(0, DB::table('receipt_items')->where('receipt_id', $receipt->id)->count());
        $this->assertEquals(0, DB::table('expense_hub_vat_decisions')->where('id', $decision->id)->count());
        $this->assertEquals(0, DB::table('expense_hub_vat_audit_logs')->where('decision_id', $decision->id)->count());
    }

    /**
     * Test VehicleFuelRequestController purge helper works idempotently.
     */
    public function test_vehicle_fuel_request_controller_purge_helper()
    {
        $asset = FixedAsset::create(['name' => 'Toyota Landcruiser', 'category' => 'Vehicles']);
        $unit  = FixedAssetUnit::create(['fixed_asset_id' => $asset->id, 'plate_number' => 'AA-99999']);

        $expenseRequest = ExpenseRequest::create([
            'request_number' => 'EXP-FUEL-2026-0273',
            'user_id' => $this->adminUser->id,
            'category' => 'Transport',
            'amount' => 3000,
            'gross_amount' => 3000,
            'net_amount' => 3000,
            'status' => 'Pending (HR Review)',
        ]);

        $fuelRequest = VehicleFuelRequest::create([
            'request_no' => 'VFR-2026-0273-B',
            'fixed_asset_unit_id' => $unit->id,
            'requested_by_user_id' => $this->adminUser->id,
            'expense_request_id' => $expenseRequest->id,
            'liters_requested' => 30,
            'estimated_amount' => 3000,
            'status' => VehicleFuelRequest::STATUS_PENDING_FINANCE,
            'reason' => 'Fuel request EXP-FUEL-2026-0273'
        ]);

        VehicleFuelRequestController::purgeTargetFuelRequest('EXP-FUEL-2026-0273');

        $this->assertEquals(0, DB::table('expense_requests')->where('id', $expenseRequest->id)->count());
        $this->assertEquals(0, DB::table('vehicle_fuel_requests')->where('id', $fuelRequest->id)->count());
    }

    /**
     * Test VehicleFuelRequestController destroy method cascades deletion.
     */
    public function test_vehicle_fuel_request_destroy_action()
    {
        $this->actingAs($this->adminUser);

        $asset = FixedAsset::create(['name' => 'Toyota Prado', 'category' => 'Vehicles']);
        $unit  = FixedAssetUnit::create(['fixed_asset_id' => $asset->id, 'plate_number' => 'AA-55555']);

        $expenseRequest = ExpenseRequest::create([
            'request_number' => 'EXP-FUEL-TEST-0001',
            'user_id' => $this->adminUser->id,
            'category' => 'Transport',
            'amount' => 1200,
            'gross_amount' => 1200,
            'net_amount' => 1200,
            'status' => 'Pending (HR Review)',
        ]);

        $fuelRequest = VehicleFuelRequest::create([
            'request_no' => 'VFR-TEST-0001',
            'fixed_asset_unit_id' => $unit->id,
            'requested_by_user_id' => $this->adminUser->id,
            'expense_request_id' => $expenseRequest->id,
            'liters_requested' => 15,
            'estimated_amount' => 1200,
            'status' => VehicleFuelRequest::STATUS_PENDING_FINANCE,
        ]);

        $controller = app(VehicleFuelRequestController::class);
        $response = $controller->destroy($fuelRequest);

        $this->assertEquals(0, DB::table('vehicle_fuel_requests')->where('id', $fuelRequest->id)->count());
        $this->assertEquals(0, DB::table('expense_requests')->where('id', $expenseRequest->id)->count());
    }

    /**
     * Test ExpenseRequestController destroy method cascades deletion.
     */
    public function test_expense_request_destroy_action()
    {
        $this->actingAs($this->adminUser);

        $expenseRequest = ExpenseRequest::create([
            'request_number' => 'EXP-TEST-0002',
            'user_id' => $this->adminUser->id,
            'category' => 'Transport',
            'amount' => 800,
            'gross_amount' => 800,
            'net_amount' => 800,
            'status' => 'Pending (HR Review)',
        ]);

        $controller = app(ExpenseRequestController::class);
        $response = $controller->destroy($expenseRequest);

        $this->assertEquals(0, DB::table('expense_requests')->where('id', $expenseRequest->id)->count());
    }
}
