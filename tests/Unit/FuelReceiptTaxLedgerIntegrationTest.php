<?php

namespace Tests\Unit;

use App\Http\Controllers\Finance\FinanceTaxReportController;
use App\Models\Employee;
use App\Models\ExpenseHubVatDecision;
use App\Models\ExpenseRequest;
use App\Models\FixedAsset;
use App\Models\FixedAssetUnit;
use App\Models\Project;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\User;
use App\Models\VehicleFuelRequest;
use App\Services\VehicleFuelService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FuelReceiptTaxLedgerIntegrationTest extends TestCase
{
    protected VehicleFuelService $fuelService;
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
        Schema::dropIfExists('employees');
        Schema::dropIfExists('projects');
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
            $table->string('phone')->nullable();
            $table->string('password')->nullable();
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

        Schema::create('expense_requests', function ($table) {
            $table->id();
            $table->string('request_number')->unique();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('purchase_request_id')->nullable();
            $table->unsignedBigInteger('tax_settlement_id')->nullable();
            $table->string('category');
            $table->decimal('amount', 15, 2);
            $table->decimal('gross_amount', 15, 2)->nullable();
            $table->decimal('net_amount', 15, 2)->nullable();
            $table->string('vat_type', 30)->default('none');
            $table->decimal('vat_rate', 5, 2)->default(15.00);
            $table->decimal('vat_amount', 15, 2)->default(0);
            $table->boolean('has_withholding')->default(false);
            $table->decimal('withholding_rate', 5, 2)->default(3.00);
            $table->decimal('withholding_amount', 15, 2)->default(0);
            $table->string('withholding_receipt', 500)->nullable();
            $table->string('withholding_receipt_number', 100)->nullable();
            $table->boolean('vat_settled')->default(false);
            $table->unsignedBigInteger('vat_settlement_id')->nullable();
            $table->boolean('withholding_settled')->default(false);
            $table->unsignedBigInteger('withholding_settlement_id')->nullable();
            $table->string('attachment')->nullable();
            $table->string('status');
            $table->string('payment_reference')->nullable();
            $table->text('payment_notes')->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('paid_by')->nullable();
            $table->timestamp('paid_at')->nullable();
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

        Schema::create('receipts', function ($table) {
            $table->id();
            $table->string('receipt_number', 50)->unique();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->string('vendor_name')->nullable();
            $table->string('vendor_tin', 30)->nullable();
            $table->boolean('tin_valid')->default(false);
            $table->string('buyer_tin', 30)->nullable();
            $table->string('fs_no', 50)->nullable();
            $table->string('fs_no_raw', 50)->nullable();
            $table->boolean('fs_no_valid')->default(false);
            $table->string('mrc_no', 50)->nullable();
            $table->date('receipt_date')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('vat_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->string('currency', 10)->default('ETB');
            $table->string('category', 60)->nullable();
            $table->text('description')->nullable();
            $table->string('file_path', 500)->nullable();
            $table->string('file_type', 30)->nullable();
            $table->string('status', 30)->default('approved');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('purchasable_type')->nullable();
            $table->unsignedBigInteger('purchasable_id')->nullable();
            $table->timestamps();
        });

        Schema::create('receipt_items', function ($table) {
            $table->id();
            $table->unsignedBigInteger('receipt_id');
            $table->text('item_description')->nullable();
            $table->string('vat_category', 10)->default('G');
            $table->string('calendar_type', 10)->default('E');
            $table->integer('purchase_type')->default(3);
            $table->string('uom', 20)->default('9');
            $table->decimal('qty', 10, 2)->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('total_value', 15, 2)->default(0);
            $table->decimal('vat_amount', 15, 2)->default(0);
            $table->decimal('value_after_vat', 15, 2)->default(0);
            $table->boolean('is_flagged')->default(false);
            $table->json('flag_reasons')->nullable();
            $table->timestamps();
        });

        Schema::create('expense_hub_vat_decisions', function ($table) {
            $table->id();
            $table->string('source_type', 60);
            $table->unsignedBigInteger('source_id');
            $table->string('vat_status', 30);
            $table->boolean('has_withholding')->default(false);
            $table->decimal('withholding_rate', 5, 2)->default(3.00);
            $table->decimal('withholding_amount', 18, 2)->default(0.00);
            $table->string('withholding_receipt', 500)->nullable();
            $table->string('withholding_receipt_number', 100)->nullable();
            $table->decimal('net_payable_amount', 18, 2)->nullable();
            $table->decimal('vat_rate', 5, 2)->default(15.00);
            $table->boolean('is_inclusive')->default(true);
            $table->decimal('base_amount', 18, 2)->default(0.00);
            $table->decimal('vat_amount', 18, 2)->default(0.00);
            $table->decimal('total_amount', 18, 2)->default(0.00);
            $table->string('no_vat_reason_code', 50)->nullable();
            $table->string('no_vat_reason_note', 500)->nullable();
            $table->string('vat_category', 10)->nullable();
            $table->string('calendar_type', 10)->default('E');
            $table->integer('purchase_type')->default(3);
            $table->string('custom_seller_tin', 30)->nullable();
            $table->string('custom_fs_no', 50)->nullable();
            $table->string('custom_mrc_no', 50)->nullable();
            $table->string('custom_item_description', 255)->nullable();
            $table->string('uom', 20)->default('9');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->boolean('is_locked')->default(false);
            $table->timestamp('locked_at')->nullable();
            $table->unsignedBigInteger('locked_by')->nullable();
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });

        $this->adminUser = User::create([
            'name'     => 'Finance Head Admin',
            'email'    => 'finhead@wechecha.com',
            'password' => bcrypt('password'),
        ]);

        Auth::login($this->adminUser);
        $this->fuelService = app(VehicleFuelService::class);
    }

    /**
     * Test 1: Uploading fuel station receipt with FS #, MRC #, Station TIN, and Reason for No VAT
     * correctly updates fuel request, expense request, and registers in Tax Deductions Ledger & Expense Hub.
     */
    public function test_fuel_receipt_upload_records_tax_slip_with_reason_for_no_vat(): void
    {
        $asset = FixedAsset::create(['name' => 'Toyota Land Cruiser', 'category' => 'Vehicles']);
        $unit  = FixedAssetUnit::create([
            'fixed_asset_id' => $asset->id,
            'unit_code'      => 'VH-LC-01',
            'plate_number'   => '3-54321-AA',
        ]);

        $exp = ExpenseRequest::create([
            'request_number' => 'EXP-FUEL-TEST-001',
            'user_id'        => $this->adminUser->id,
            'category'       => 'Fuel',
            'amount'         => 3500.00,
            'gross_amount'   => 3500.00,
            'status'         => ExpenseRequest::STATUS_PAID,
            'paid_by'        => $this->adminUser->id,
            'paid_at'        => now(),
        ]);

        $fuelReq = VehicleFuelRequest::create([
            'request_no'          => 'FR-20261022-001',
            'fixed_asset_unit_id' => $unit->id,
            'liters_requested'    => 35.0,
            'estimated_amount'    => 3500.00,
            'current_odometer_km' => 20000,
            'expense_request_id'  => $exp->id,
            'status'              => VehicleFuelRequest::STATUS_PAID,
        ]);

        // Upload fuel station receipt with tax fields
        $this->fuelService->attachReceiptAndClose($fuelReq, [
            'receipt_photo'       => 'fuel_receipts/sample_slip.jpg',
            'actual_liters'       => 35.5,
            'actual_amount'       => 3550.00,
            'fuel_station_name'   => 'TotalEnergies Bole Station',
            'fuel_station_tin'    => '0012998877',
            'fs_no'               => 'FS00098765',
            'mrc_no'              => 'MRC-TOTAL-99',
            'tax_type'            => 'no_vat',
            'no_vat_reason_code'  => 'exempt_item',
            'no_vat_reason_note'  => 'Retail fuel pump purchase (legally VAT-exempt proclamation)',
        ]);

        $fuelReq->refresh();
        $this->assertEquals(VehicleFuelRequest::STATUS_CLOSED, $fuelReq->status);
        $this->assertEquals('FS00098765', $fuelReq->fs_no);
        $this->assertEquals('MRC-TOTAL-99', $fuelReq->mrc_no);
        $this->assertEquals('0012998877', $fuelReq->fuel_station_tin);
        $this->assertEquals('no_vat', $fuelReq->tax_type);
        $this->assertEquals('exempt_item', $fuelReq->no_vat_reason_code);

        // Verify ExpenseRequest update
        $exp->refresh();
        $this->assertEquals(3550.00, (float)$exp->amount);
        $this->assertEquals(0.00, (float)$exp->vat_amount);
        $this->assertEquals('none', $exp->vat_type);
        $this->assertStringContainsString('FS00098765', $exp->payment_reference);

        // Verify ExpenseHubVatDecision creation
        $decision = ExpenseHubVatDecision::where('source_type', 'expense_request')
            ->where('source_id', $exp->id)
            ->first();
        $this->assertNotNull($decision, 'ExpenseHubVatDecision must be created');
        $this->assertEquals(ExpenseHubVatDecision::STATUS_NO_VAT, $decision->vat_status);
        $this->assertEquals('exempt_item', $decision->no_vat_reason_code);
        $this->assertEquals(6, $decision->purchase_type);
        $this->assertEquals('FS00098765', $decision->custom_fs_no);
        $this->assertEquals('MRC-TOTAL-99', $decision->custom_mrc_no);
        $this->assertEquals('0012998877', $decision->custom_seller_tin);
        $this->assertEquals(3550.00, (float)$decision->total_amount);
        $this->assertEquals(0.00, (float)$decision->vat_amount);

        // Verify Tax Deductions Ledger: Receipt & ReceiptItem creation
        $receipt = Receipt::where('purchasable_type', ExpenseRequest::class)
            ->where('purchasable_id', $exp->id)
            ->first();
        $this->assertNotNull($receipt, 'Receipt record must be saved in Tax Ledger');
        $this->assertEquals('FS00098765', $receipt->fs_no);
        $this->assertEquals('MRC-TOTAL-99', $receipt->mrc_no);
        $this->assertEquals('0012998877', $receipt->vendor_tin);
        $this->assertEquals(3550.00, (float)$receipt->total_amount);
        $this->assertEquals(0.00, (float)$receipt->vat_amount);

        $receiptItem = ReceiptItem::where('receipt_id', $receipt->id)->first();
        $this->assertNotNull($receiptItem, 'ReceiptItem record must be saved in Tax Ledger');
        $this->assertEquals(6, $receiptItem->purchase_type, 'Purchase type must be ERCA Line 6 (PUR_EX)');
        $this->assertEquals('E', $receiptItem->vat_category, 'VAT category must be E (Exempt)');
        $this->assertEquals(0.00, (float)$receiptItem->vat_amount);
    }

    /**
     * Test 2: FinanceTaxReportController storeReceipt with category = fuel & tax_type = no_vat
     * saves with 0 VAT, Reason for No VAT = exempt_item, and creates ExpenseHubVatDecision.
     */
    public function test_store_receipt_for_fuel_saves_with_reason_for_no_vat_and_zero_vat(): void
    {
        $controller = app(FinanceTaxReportController::class);

        $request = new Request([
            'receipt_date'       => now()->toDateString(),
            'fs_no'              => 'FS00112233',
            'mrc_no'             => 'MRC-NOC-44',
            'vendor_name'        => 'NOC Ethiopia Service Station',
            'vendor_tin'         => '0099887766',
            'total_amount'       => 5000.00,
            'tax_type'           => 'no_vat',
            'category'           => 'fuel',
            'no_vat_reason_code' => 'exempt_item',
            'no_vat_reason_note' => 'Legally VAT-Exempt retail fuel pump purchase under Proclamation',
            'description'        => 'Diesel fuel for generator/vehicles',
        ]);

        $controller->storeReceipt($request);

        // Verify Receipt (fs_no is normalized by QcService e.g. 00112233, fs_no_raw is FS00112233)
        $receipt = Receipt::where('fs_no', '00112233')->orWhere('fs_no_raw', 'FS00112233')->first();
        $this->assertNotNull($receipt, 'Receipt must exist in database');
        $this->assertEquals('0099887766', $receipt->vendor_tin);
        $this->assertEquals('MRC-NOC-44', $receipt->mrc_no);
        $this->assertEquals(5000.00, (float)$receipt->total_amount);
        $this->assertEquals(0.00, (float)$receipt->vat_amount, 'VAT amount must be 0 for retail fuel');
        $this->assertEquals(5000.00, (float)$receipt->subtotal);

        // Verify ReceiptItem Line 6 (PUR_EX)
        $receiptItem = ReceiptItem::where('receipt_id', $receipt->id)->first();
        $this->assertNotNull($receiptItem);
        $this->assertEquals(6, $receiptItem->purchase_type, 'Must be Line 6 Tax Exempt');
        $this->assertEquals('E', $receiptItem->vat_category);
        $this->assertEquals(0.00, (float)$receiptItem->vat_amount);

        // Verify ExpenseRequest
        $exp = ExpenseRequest::where('request_number', 'like', 'EXP-TAX-%')->latest('id')->first();
        $this->assertNotNull($exp);
        $this->assertEquals('none', $exp->vat_type);
        $this->assertEquals(0.00, (float)$exp->vat_amount);
        $this->assertStringContainsString('00112233', $exp->payment_reference);

        // Verify ExpenseHubVatDecision
        $decision = ExpenseHubVatDecision::where('source_type', 'expense_request')
            ->where('source_id', $exp->id)
            ->first();
        $this->assertNotNull($decision);
        $this->assertEquals(ExpenseHubVatDecision::STATUS_NO_VAT, $decision->vat_status);
        $this->assertEquals('exempt_item', $decision->no_vat_reason_code);
        $this->assertEquals(6, $decision->purchase_type);
        $this->assertEquals('00112233', $decision->custom_fs_no);
        $this->assertEquals('MRC-NOC-44', $decision->custom_mrc_no);
        $this->assertEquals('0099887766', $decision->custom_seller_tin);
    }
}
