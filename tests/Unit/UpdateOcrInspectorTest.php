<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\ExpenseRequest;
use App\Models\ExpenseHubVatDecision;
use App\Models\Project;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UpdateOcrInspectorTest extends TestCase
{
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:wA39j/rL2o0E52Hh0mQ1UvJ1mZ2v6m6jN6k9k4X8e4U=']);

        try {
            DB::statement('PRAGMA foreign_keys = OFF;');
        } catch (\Throwable $e) {}

        $this->setupSchema();

        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->adminUser = User::create([
            'name'      => 'Tax Admin Tester',
            'email'     => 'tax_tester_' . uniqid() . '@wechecha.com',
            'is_active' => true,
        ]);
        $this->adminUser->assignRole($role);
        Auth::login($this->adminUser);

        SystemSetting::set('company_tin', '38480010');
        SystemSetting::set('purchaser_tin', '38480010');
    }

    protected function setupSchema(): void
    {
        Schema::dropIfExists('vehicle_fuel_requests');
        Schema::dropIfExists('expense_hub_vat_audit_logs');
        Schema::dropIfExists('expense_hub_vat_decisions');
        Schema::dropIfExists('receipt_items');
        Schema::dropIfExists('receipts');
        Schema::dropIfExists('expense_requests');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('users');
        Schema::dropIfExists('system_settings');

        Schema::create('vehicle_fuel_requests', function ($table) {
            $table->id();
            $table->string('request_no')->nullable();
            $table->string('request_number')->nullable();
            $table->unsignedBigInteger('expense_request_id')->nullable();
            $table->string('fuel_station_tin')->nullable();
            $table->string('fs_no')->nullable();
            $table->string('mrc_no')->nullable();
            $table->decimal('total_price', 18, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('system_settings', function ($table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string');
            $table->string('group')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('projects', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('expense_requests', function ($table) {
            $table->id();
            $table->string('request_number')->nullable();
            $table->foreignId('project_id')->nullable();
            $table->foreignId('user_id')->nullable();
            $table->string('category')->nullable();
            $table->string('payee')->nullable();
            $table->string('account_name')->nullable();
            $table->string('vat_type')->nullable();
            $table->decimal('vat_rate', 5, 2)->default(15.00);
            $table->text('description')->nullable();
            $table->decimal('amount', 18, 2);
            $table->decimal('gross_amount', 18, 2)->nullable();
            $table->decimal('net_amount', 18, 2)->nullable();
            $table->decimal('vat_amount', 18, 2)->default(0);
            $table->decimal('withholding_amount', 18, 2)->default(0);
            $table->string('payment_reference')->nullable();
            $table->string('attachment')->nullable();
            $table->string('status')->default('paid');
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('receipts', function ($table) {
            $table->id();
            $table->string('receipt_number')->unique();
            $table->foreignId('uploaded_by')->nullable();
            $table->foreignId('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('purchasable_type')->nullable();
            $table->unsignedBigInteger('purchasable_id')->nullable();
            $table->foreignId('project_id')->nullable();
            $table->string('vendor_name')->nullable();
            $table->string('vendor_tin')->nullable();
            $table->boolean('tin_valid')->default(false);
            $table->string('buyer_tin')->nullable();
            $table->string('fs_no')->nullable();
            $table->string('fs_no_raw')->nullable();
            $table->boolean('fs_no_valid')->default(false);
            $table->string('mrc_no')->nullable();
            $table->date('receipt_date')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('vat_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->string('currency', 10)->default('ETB');
            $table->string('category')->default('materials');
            $table->text('description')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_type', 20)->default('image');
            $table->json('parsed_data')->nullable();
            $table->timestamps();
        });

        Schema::create('receipt_items', function ($table) {
            $table->id();
            $table->foreignId('receipt_id');
            $table->text('item_description')->nullable();
            $table->string('vat_category', 10)->default('G');
            $table->string('calendar_type', 10)->default('G');
            $table->integer('purchase_type')->default(3);
            $table->string('uom', 50)->default('9');
            $table->decimal('qty', 15, 2)->default(1.00);
            $table->decimal('unit_price', 15, 2)->default(0.00);
            $table->decimal('total_value', 15, 2)->default(0.00);
            $table->decimal('vat_amount', 15, 2)->default(0.00);
            $table->decimal('value_after_vat', 15, 2)->default(0.00);
            $table->boolean('is_flagged')->default(false);
            $table->timestamps();
        });

        Schema::create('expense_hub_vat_decisions', function ($table) {
            $table->id();
            $table->string('source_type', 60);
            $table->unsignedBigInteger('source_id');
            $table->string('decision')->default('vat_applicable');
            $table->string('vat_status', 30)->default('pending_decision');
            $table->boolean('has_withholding')->default(false);
            $table->decimal('withholding_rate', 5, 2)->default(3.00);
            $table->decimal('withholding_amount', 18, 2)->default(0.00);
            $table->decimal('base_amount', 18, 2)->default(0.00);
            $table->decimal('vat_amount', 18, 2)->default(0.00);
            $table->decimal('total_amount', 18, 2)->default(0.00);
            $table->string('no_vat_reason_code', 50)->nullable();
            $table->string('vat_category', 5)->default('G');
            $table->string('purc_type', 20)->default('PUR_GD');
            $table->string('custom_seller_tin', 30)->nullable();
            $table->string('custom_fs_no', 50)->nullable();
            $table->string('custom_mrc_no', 50)->nullable();
            $table->timestamps();
            $table->unique(['source_type', 'source_id']);
        });
    }

    public function test_standard_vat_receipt_inspector_save_persists_latest_data()
    {
        $project = Project::create([
            'name' => 'Bole Site Project',
            'status' => 'active',
        ]);

        $receipt = Receipt::create([
            'vendor_name'    => 'Old Vendor PLC',
            'vendor_tin'     => '1111111111',
            'fs_no'          => '11111111',
            'fs_no_raw'      => '11111111',
            'mrc_no'         => 'MRC-OLD-01',
            'receipt_date'   => '2026-03-01',
            'subtotal'       => 1000.00,
            'vat_amount'     => 150.00,
            'total_amount'   => 1150.00,
            'category'       => 'materials',
            'parsed_data'    => [
                'receipt_no' => '11111111',
                'seller_tin' => '1111111111',
                'mrc'        => 'MRC-OLD-01',
                'purc_type'  => '3',
            ],
        ]);

        $item = ReceiptItem::create([
            'receipt_id'       => $receipt->id,
            'item_description' => 'Old Material Description',
            'vat_category'     => 'G',
            'purchase_type'    => 3,
            'qty'              => 1,
            'unit_price'       => 1000.00,
            'total_value'      => 1000.00,
            'vat_amount'       => 150.00,
            'value_after_vat'  => 1150.00,
        ]);

        $response = $this->postJson(route('finance.tax-deductions.update-ocr-item'), [
            'id'               => $item->id,
            'receipt_id'       => $receipt->id,
            'seller_name'      => 'TotalEnergies Marketing Ethiopia',
            'supplier_tin'     => '0012345678',
            'fs_no'            => '00088899',
            'mrc_no'           => 'MRC-NEW-99',
            'receipt_date'     => '2026-03-10',
            'item_description' => 'Updated Fuel & Lubricants',
            'project_id'       => $project->id,
            'category'         => 'fuel',
            'vat_category'     => 'G',
            'purchase_type'    => '1',
            'total_value'      => 2000.00,
            'vat_amount'       => 300.00,
            'value_after_vat'  => 2300.00,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $receipt->refresh();
        $item->refresh();

        $this->assertEquals('TotalEnergies Marketing Ethiopia', $receipt->vendor_name);
        $this->assertEquals('0012345678', $receipt->vendor_tin);
        $this->assertEquals('00088899', $receipt->fs_no);
        $this->assertEquals('MRC-NEW-99', $receipt->mrc_no);
        $this->assertEquals($project->id, $receipt->project_id);
        $this->assertEquals(2000.00, (float)$receipt->subtotal);
        $this->assertEquals(300.00, (float)$receipt->vat_amount);
        $this->assertEquals(2300.00, (float)$receipt->total_amount);

        // Check parsed_data JSON was also updated
        $this->assertIsArray($receipt->parsed_data);
        $this->assertEquals('00088899', $receipt->parsed_data['receipt_no'] ?? null);
        $this->assertEquals('0012345678', $receipt->parsed_data['seller_tin'] ?? null);
        $this->assertEquals('MRC-NEW-99', $receipt->parsed_data['mrc'] ?? null);

        // Check item updated
        $this->assertEquals('Updated Fuel & Lubricants', $item->item_description);
        $this->assertEquals(2000.00, (float)$item->total_value);
        $this->assertEquals(300.00, (float)$item->vat_amount);
        $this->assertEquals(2300.00, (float)$item->value_after_vat);
    }

    public function test_zero_vat_tas_receipt_inspector_save_does_not_fail_validation()
    {
        $receipt = Receipt::create([
            'vendor_name'    => 'Oil Libya Petrol Station',
            'vendor_tin'     => '0023456789',
            'fs_no'          => '00054321',
            'receipt_date'   => '2026-03-05',
            'subtotal'       => 850.00,
            'vat_amount'     => 0.00,
            'total_amount'   => 850.00,
            'category'       => 'fuel',
            'parsed_data'    => ['tax_type' => 'no_vat'],
        ]);

        $item = ReceiptItem::create([
            'receipt_id'       => $receipt->id,
            'item_description' => 'Diesel Fuel 25L',
            'vat_category'     => 'E',
            'purchase_type'    => 3,
            'qty'              => 1,
            'unit_price'       => 850.00,
            'total_value'      => 850.00,
            'vat_amount'       => 0.00,
            'value_after_vat'  => 850.00,
        ]);

        $response = $this->postJson(route('finance.tax-deductions.update-ocr-item'), [
            'id'               => $item->id,
            'receipt_id'       => $receipt->id,
            'seller_name'      => 'Oil Libya Station Revised',
            'supplier_tin'     => '0023456789',
            'fs_no'            => '00054322',
            'mrc_no'           => 'MRC-TAS-77',
            'receipt_date'     => '2026-03-06',
            'item_description' => 'Diesel Fuel 30L',
            'vat_category'     => 'E',
            'purchase_type'    => 'PUR_GD',
            'total_value'      => 1020.00,
            'vat_amount'       => 0.00,
            'value_after_vat'  => 1020.00,
        ]);

        $response->assertStatus(200);
        $receipt->refresh();
        $item->refresh();

        $this->assertEquals('Oil Libya Station Revised', $receipt->vendor_name);
        $this->assertEquals('00054322', $receipt->fs_no);
        $this->assertEquals('MRC-TAS-77', $receipt->mrc_no);
        $this->assertEquals(1020.00, (float)$receipt->subtotal);
        $this->assertEquals(0.00, (float)$receipt->vat_amount);
        $this->assertEquals(1020.00, (float)$receipt->total_amount);
        $this->assertEquals('E', $item->vat_category);
        $this->assertEquals(0.00, (float)$item->vat_amount);
    }

    public function test_standalone_receipt_creates_item_and_preserves_totals()
    {
        $receipt = Receipt::create([
            'vendor_name'    => 'Standalone Hardware Store',
            'vendor_tin'     => '0098765432',
            'fs_no'          => '00077777',
            'receipt_date'   => '2026-03-08',
            'subtotal'       => 500.00,
            'vat_amount'     => 75.00,
            'total_amount'   => 575.00,
        ]);

        $this->assertEquals(0, $receipt->items()->count());

        $response = $this->postJson(route('finance.tax-deductions.update-ocr-item'), [
            'id'               => $receipt->id, // standalone virtual id in UI
            'receipt_id'       => $receipt->id,
            'seller_name'      => 'Hardware Store Updated',
            'supplier_tin'     => '0098765432',
            'fs_no'            => '00077778',
            'receipt_date'     => '2026-03-09',
            'item_description' => 'Nails & Cement',
            'vat_category'     => 'G',
            'purchase_type'    => 1,
            'total_value'      => 600.00,
            'vat_amount'       => 90.00,
            'value_after_vat'  => 690.00,
        ]);

        $response->assertStatus(200);
        $receipt->refresh();

        $this->assertEquals(1, $receipt->items()->count());
        $this->assertEquals('Hardware Store Updated', $receipt->vendor_name);
        $this->assertEquals(600.00, (float)$receipt->subtotal);
        $this->assertEquals(90.00, (float)$receipt->vat_amount);
        $this->assertEquals(690.00, (float)$receipt->total_amount);
    }

    public function test_linked_expense_request_and_vat_decision_are_synchronized()
    {
        $exp = ExpenseRequest::create([
            'request_number'    => 'EXP-FUEL-2026-0999',
            'amount'            => 400.00,
            'vat_amount'        => 0.00,
            'gross_amount'      => 400.00,
            'net_amount'        => 400.00,
            'payment_reference' => 'FS: 00011111',
            'payee'             => 'Old Payee',
            'category'          => 'fuel',
            'status'            => 'paid',
        ]);

        $receipt = Receipt::create([
            'purchasable_type' => ExpenseRequest::class,
            'purchasable_id'   => $exp->id,
            'receipt_number'   => $exp->request_number,
            'vendor_name'      => 'Old Payee',
            'vendor_tin'       => '0011111111',
            'fs_no'            => '00011111',
            'receipt_date'     => '2026-03-02',
            'subtotal'         => 400.00,
            'vat_amount'       => 0.00,
            'total_amount'     => 400.00,
        ]);

        $vatDec = ExpenseHubVatDecision::create([
            'source_type'      => 'expense_request',
            'source_id'        => $exp->id,
            'decision'         => 'no_vat',
            'vat_status'       => 'no_vat',
            'custom_fs_no'     => '00011111',
            'custom_seller_tin'=> '0011111111',
            'custom_mrc_no'    => 'MRC-OLD',
            'purc_type'        => 'PUR_GD',
            'vat_amount'       => 0.00,
        ]);

        $response = $this->postJson(route('finance.tax-deductions.update-ocr-item'), [
            'id'               => $receipt->id,
            'receipt_id'       => $receipt->id,
            'seller_name'      => 'Total Station Finfinne',
            'supplier_tin'     => '0099999999',
            'fs_no'            => '00099999',
            'mrc_no'           => 'MRC-NEW-88',
            'receipt_date'     => '2026-03-03',
            'item_description' => 'Vehicle Benzene Fuel Refill',
            'vat_category'     => 'E',
            'purchase_type'    => 'PUR_GD',
            'total_value'      => 550.00,
            'vat_amount'       => 0.00,
            'value_after_vat'  => 550.00,
        ]);

        $response->assertStatus(200);

        $exp->refresh();
        $vatDec->refresh();

        $this->assertEquals(550.00, (float)$exp->amount);
        $this->assertEquals(550.00, (float)$exp->gross_amount);
        $this->assertEquals('Total Station Finfinne', $exp->payee);
        $this->assertStringContainsString('00099999', $exp->payment_reference);

        $this->assertEquals('00099999', $vatDec->custom_fs_no);
        $this->assertEquals('0099999999', $vatDec->custom_seller_tin);
        $this->assertEquals('MRC-NEW-88', $vatDec->custom_mrc_no);
    }

    public function test_tas_purchase_seller_tin_fill_and_fuel_request_sync()
    {
        $exp = ExpenseRequest::create([
            'request_number'    => 'EXP-FUEL-2026-0276',
            'amount'            => 1250.00,
            'vat_amount'        => 0.00,
            'gross_amount'      => 1250.00,
            'net_amount'        => 1250.00,
            'payee'             => 'NOC Fuel Station',
            'category'          => 'fuel',
            'status'            => 'paid',
            'payment_reference' => 'Fuel refill voucher',
        ]);

        $fuelReq = \App\Models\VehicleFuelRequest::create([
            'request_number'     => 'EXP-FUEL-2026-0276',
            'expense_request_id' => $exp->id,
            'fuel_station_tin'   => null,
            'fs_no'              => null,
            'total_price'        => 1250.00,
        ]);

        // Inspector update from TAS tab: user fills missing Seller TIN
        $response = $this->postJson(route('finance.tax-deductions.update-ocr-item'), [
            'receipt_id'       => $exp->request_number,
            'seller_name'      => 'NOC Fuel Station Gurd Shola',
            'supplier_tin'     => '0012345678',
            'fs_no'            => '00055443',
            'mrc_no'           => 'MRC-NOC-99',
            'receipt_date'     => '2026-03-05',
            'item_description' => 'Vehicle Benzene Fuel Refill',
            'vat_category'     => 'E',
            'purchase_type'    => 'PUR_GD',
            'total_value'      => 1250.00,
            'vat_amount'       => 0.00,
            'value_after_vat'  => 1250.00,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'item'    => [
                'vendor_tin'  => '0012345678',
                'vendor_name' => 'NOC Fuel Station Gurd Shola',
                'fs_no'       => '00055443',
            ]
        ]);

        $fuelReq->refresh();
        $this->assertEquals('0012345678', $fuelReq->fuel_station_tin);
        $this->assertEquals('00055443', $fuelReq->fs_no);
        $this->assertEquals('MRC-NOC-99', $fuelReq->mrc_no);

        $receipt = Receipt::where('purchasable_id', $exp->id)->first();
        $this->assertNotNull($receipt);
        $this->assertEquals('0012345678', $receipt->vendor_tin);
        $this->assertTrue((bool)$receipt->tin_valid);
    }

    public function test_tas_purchase_opened_with_exp_prefix_and_no_receipt_saves_and_creates_records()
    {
        $exp = ExpenseRequest::create([
            'request_number'    => 'EXP-FUEL-2026-0276',
            'amount'            => 1500.00,
            'vat_amount'        => 0.00,
            'gross_amount'      => 1500.00,
            'net_amount'        => 1500.00,
            'payee'             => 'Total Station',
            'category'          => 'fuel',
            'status'            => 'paid',
        ]);

        $this->assertNull(Receipt::where('purchasable_id', $exp->id)->first());

        // Inspector opened from TAS purchases tab passing EXP-ID and explicit expense_request_id
        $response = $this->postJson(route('finance.tax-deductions.update-ocr-item'), [
            'receipt_id'         => 'EXP-' . $exp->id,
            'expense_request_id' => $exp->id,
            'request_number'     => $exp->request_number,
            'seller_name'        => 'TotalEnergies Marketing Ethiopia',
            'supplier_tin'       => '0099887766',
            'fs_no'              => '00044556',
            'mrc_no'             => 'MRC-TEST-99',
            'receipt_date'       => '2026-03-12',
            'item_description'   => 'Vehicle Benzene Fuel Refill',
            'vat_category'       => 'E',
            'purchase_type'      => 'PUR_GD',
            'total_value'        => 1500.00,
            'vat_amount'         => 0.00,
            'value_after_vat'    => 1500.00,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'item'    => [
                'expense_request_id' => $exp->id,
                'vendor_tin'         => '0099887766',
                'vendor_name'        => 'TotalEnergies Marketing Ethiopia',
                'fs_no'              => '00044556',
            ]
        ]);

        $receipt = Receipt::where('purchasable_id', $exp->id)->first();
        $this->assertNotNull($receipt);
        $this->assertEquals('TotalEnergies Marketing Ethiopia', $receipt->vendor_name);
        $this->assertEquals('0099887766', $receipt->vendor_tin);
        $this->assertEquals('00044556', $receipt->fs_no);

        $vatDec = ExpenseHubVatDecision::where('source_type', 'expense_request')->where('source_id', $exp->id)->first();
        $this->assertNotNull($vatDec);
        $this->assertEquals('0099887766', $vatDec->custom_seller_tin);
        $this->assertEquals('00044556', $vatDec->custom_fs_no);
        $this->assertEquals('MRC-TEST-99', $vatDec->custom_mrc_no);

        $exp->refresh();
        $this->assertStringContainsString('FS: 00044556', $exp->payment_reference);
        $this->assertStringContainsString('TIN: 0099887766', $exp->payment_reference);
    }

    public function test_tas_purchase_opened_with_only_exp_dash_id_without_explicit_expense_request_id_saves()
    {
        $exp = ExpenseRequest::create([
            'request_number'    => 'EXP-FUEL-2026-0555',
            'amount'            => 800.00,
            'vat_amount'        => 0.00,
            'gross_amount'      => 800.00,
            'net_amount'        => 800.00,
            'payee'             => 'Kobil Station',
            'category'          => 'fuel',
            'status'            => 'paid',
        ]);

        // Inspector opened passing only receipt_id = 'EXP-555' (where 555 is exp->id)
        $response = $this->postJson(route('finance.tax-deductions.update-ocr-item'), [
            'receipt_id'       => 'EXP-' . $exp->id,
            'seller_name'      => 'Kobil Station Bole',
            'supplier_tin'     => '0055443322',
            'fs_no'            => '00012121',
            'mrc_no'           => 'MRC-KOB-01',
            'receipt_date'     => '2026-03-14',
            'item_description' => 'Diesel Fuel Refill',
            'vat_category'     => 'E',
            'purchase_type'    => 'PUR_GD',
            'total_value'      => 800.00,
            'vat_amount'       => 0.00,
            'value_after_vat'  => 800.00,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'item'    => [
                'vendor_tin'  => '0055443322',
                'vendor_name' => 'Kobil Station Bole',
                'fs_no'       => '00012121',
            ]
        ]);

        $receipt = Receipt::where('purchasable_id', $exp->id)->first();
        $this->assertNotNull($receipt);
        $this->assertEquals('0055443322', $receipt->vendor_tin);
        $this->assertEquals('00012121', $receipt->fs_no);
    }

    public function test_inspector_saved_changes_persist_on_month_movements_page_reload()
    {
        $exp = ExpenseRequest::create([
            'request_number'    => 'EXP-FUEL-2026-0888',
            'amount'            => 1000.00,
            'gross_amount'      => 1000.00,
            'net_amount'        => 1000.00,
            'vat_amount'        => 130.43,
            'vat_type'          => 'inclusive',
            'vat_rate'          => 15.00,
            'payee'             => 'Old Employee Requester',
            'account_name'      => 'Old Employee Account',
            'category'          => 'fuel',
            'status'            => 'paid',
            'created_at'        => '2026-10-15 10:00:00',
            'paid_at'           => '2026-10-15 10:00:00', // Month 2 of Ethiopian Year 2019
        ]);

        // User edits in the Inspector modal and saves
        $response = $this->postJson(route('finance.tax-deductions.update-ocr-item'), [
            'receipt_id'       => 'EXP-' . $exp->id,
            'seller_name'      => 'TotalEnergies Marketing Ethiopia',
            'supplier_tin'     => '0012345678',
            'fs_no'            => '00055667',
            'mrc_no'           => 'MRC-REV-01',
            'receipt_date'     => '2026-10-18', // YYYY-MM-DD from <input type="date">
            'item_description' => 'Vehicle Benzene Refill',
            'vat_category'     => 'E',
            'purchase_type'    => 'PUR_GD',
            'total_value'      => 1000.00,
            'vat_amount'       => 0.00,
            'value_after_vat'  => 1000.00,
        ]);

        $response->assertStatus(200);

        // Verify receipt_date was NOT corrupted to year 2034
        $receipt = Receipt::where('purchasable_id', $exp->id)->first();
        $this->assertNotNull($receipt);
        $this->assertEquals('2026-10-18', \Carbon\Carbon::parse($receipt->receipt_date)->format('Y-m-d'));
        $this->assertEquals('TotalEnergies Marketing Ethiopia', $receipt->vendor_name);
        $this->assertEquals('0012345678', $receipt->vendor_tin);
        $this->assertEquals('00055667', $receipt->fs_no);

        // Now simulate page reload: load movementsPage for Ethiopian year 2019, month 2
        $controller = new \App\Http\Controllers\Finance\FinanceTaxReportController();
        $view = $controller->movementsPage(new \Illuminate\Http\Request([
            'ethiopian_year'  => 2019,
            'ethiopian_month' => 2,
        ]));

        $this->assertInstanceOf(\Illuminate\View\View::class, $view);
        $movementsData = $view->getData()['movementsData'];

        // Assert TAS Purchases reflects all updated values on reload
        $tasItems = collect($movementsData['tas_purchases']);
        $match = $tasItems->firstWhere('request_number', 'EXP-FUEL-2026-0888');

        $this->assertNotNull($match, 'Updated TAS purchase must be present on page reload');
        $this->assertEquals('TotalEnergies Marketing Ethiopia', $match['payee'], 'Payee must reflect updated seller name');
        $this->assertEquals('0012345678', $match['seller_tin'], 'Seller TIN must reflect updated value');
        $this->assertEquals('00055667', $match['fs_no'], 'FS # must reflect updated value');
        $this->assertEquals('MRC-REV-01', $match['mrc_no'], 'MRC # must reflect updated value');
        $this->assertEquals(0.00, (float)$match['vat_amount'], 'VAT amount must remain 0.00 (TAS 0% VAT)');
        $this->assertEquals(1000.00, (float)$match['base_amount'], 'Base amount must be preserved');
        $this->assertEquals('Oct 18, 2026', $match['date_gc'], 'Date must reflect updated Gregorian receipt date');
    }

    public function test_scanned_ocr_receipt_inspector_adjust_persists_on_month_movements_page_reload()
    {
        $project = Project::create([
            'name' => 'HQ Renovation',
            'status' => 'active',
        ]);

        $receipt = Receipt::create([
            'receipt_number' => 'OCR-20261015-001',
            'vendor_name'    => 'Old Scanned Merchant',
            'vendor_tin'     => '1234567890',
            'fs_no'          => '11223344',
            'mrc_no'         => 'MRC-OLD-55',
            'receipt_date'   => '2026-10-15', // Ethiopian month 2, 2019
            'subtotal'       => 2000.00,
            'vat_amount'     => 300.00,
            'total_amount'   => 2300.00,
            'category'       => 'materials',
            'parsed_data'    => [
                'tax_type'   => 'vat_standard',
                'receipt_no' => '11223344',
                'seller_tin' => '1234567890',
                'seller_name'=> 'Old Scanned Merchant',
            ],
        ]);

        $item = ReceiptItem::create([
            'receipt_id'       => $receipt->id,
            'item_description' => 'Old Extracted Items',
            'vat_category'     => 'G',
            'purchase_type'    => 3,
            'purc_type'        => 'PUR_GD',
            'qty'              => 1,
            'unit_price'       => 2000.00,
            'total_value'      => 2000.00,
            'vat_amount'       => 300.00,
            'value_after_vat'  => 2300.00,
        ]);

        // User adjusts slip details in "Fiscal Declaration & System Adjustment" modal
        $response = $this->postJson(route('finance.tax-deductions.update-ocr-item'), [
            'id'               => $item->id,
            'receipt_id'       => $receipt->id,
            'seller_name'      => 'Bole Building Supplies PLC',
            'supplier_tin'     => '0098765432',
            'fs_no'            => '00889900',
            'mrc_no'           => 'MRC-BOLE-01',
            'receipt_date'     => '2026-10-16', // Ethiopian month 2, 2019
            'item_description' => 'Reinforced Concrete Steel & Cement',
            'project_id'       => $project->id,
            'category'         => 'materials',
            'vat_category'     => 'G',
            'purchase_type'    => '1',
            'total_value'      => 4000.00,
            'vat_amount'       => 600.00,
            'value_after_vat'  => 4600.00,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'item'    => [
                'vendor_name' => 'Bole Building Supplies PLC',
                'vendor_tin'  => '0098765432',
                'fs_no'       => '00889900',
                'mrc_no'      => 'MRC-BOLE-01',
            ]
        ]);

        // Verify database records updated
        $receipt->refresh();
        $item->refresh();

        $this->assertEquals('Bole Building Supplies PLC', $receipt->vendor_name);
        $this->assertEquals('0098765432', $receipt->vendor_tin);
        $this->assertEquals('00889900', $receipt->fs_no);
        $this->assertEquals('MRC-BOLE-01', $receipt->mrc_no);
        $this->assertEquals('2026-10-16', \Carbon\Carbon::parse($receipt->receipt_date)->format('Y-m-d'));
        $this->assertEquals(4000.00, (float)$receipt->subtotal);
        $this->assertEquals(600.00, (float)$receipt->vat_amount);
        $this->assertEquals(4600.00, (float)$receipt->total_amount);

        // Now reload movementsPage for Ethiopian Month 2, 2019 with tab=ocr
        $controller = new \App\Http\Controllers\Finance\FinanceTaxReportController();
        $view = $controller->movementsPage(new \Illuminate\Http\Request([
            'ethiopian_year'  => 2019,
            'ethiopian_month' => 2,
            'tab'             => 'ocr',
        ]));

        $this->assertInstanceOf(\Illuminate\View\View::class, $view);
        $this->assertEquals('ocr', $view->getData()['activeTab']);

        $movementsData = $view->getData()['movementsData'];
        $allOcr = collect($movementsData['all_ocr_items'] ?? []);
        $ocrItems = collect($movementsData['ocr_items'] ?? []);

        // Assert item exists in both all_ocr_items and ocr_items with latest values
        $matchedAll = $allOcr->firstWhere('id', $item->id);
        $this->assertNotNull($matchedAll, 'Adjusted OCR item must persist in all_ocr_items upon reload');
        $this->assertEquals('Bole Building Supplies PLC', $matchedAll['vendor_name']);
        $this->assertEquals('0098765432', $matchedAll['vendor_tin']);
        $this->assertEquals('00889900', $matchedAll['fs_no']);
        $this->assertEquals('MRC-BOLE-01', $matchedAll['mrc_no']);
        $this->assertEquals(4000.00, (float)$matchedAll['base_value']);
        $this->assertEquals(600.00, (float)$matchedAll['vat_amount']);
        $this->assertEquals(4600.00, (float)$matchedAll['value_after_vat']);

        $matchedVat = $ocrItems->firstWhere('id', $item->id);
        $this->assertNotNull($matchedVat, 'Adjusted OCR item must persist in ocr_items upon reload');
        $this->assertEquals('Bole Building Supplies PLC', $matchedVat['vendor_name']);
        $this->assertEquals('0098765432', $matchedVat['vendor_tin']);
        $this->assertEquals('00889900', $matchedVat['fs_no']);
    }

    public function test_scanned_ocr_receipt_converted_to_zero_vat_persists_in_all_ocr_items_on_reload()
    {
        $receipt = Receipt::create([
            'receipt_number' => 'OCR-20261017-002',
            'vendor_name'    => 'Fuel Station Central',
            'vendor_tin'     => '0033221100',
            'fs_no'          => '00778899',
            'receipt_date'   => '2026-10-17', // Ethiopian month 2, 2019
            'subtotal'       => 1500.00,
            'vat_amount'     => 225.00,
            'total_amount'   => 1725.00,
            'category'       => 'fuel',
            'parsed_data'    => ['tax_type' => 'vat_standard'],
        ]);

        $item = ReceiptItem::create([
            'receipt_id'       => $receipt->id,
            'item_description' => 'Diesel Fuel Refill',
            'vat_category'     => 'G',
            'purchase_type'    => 3,
            'qty'              => 1,
            'unit_price'       => 1500.00,
            'total_value'      => 1500.00,
            'vat_amount'       => 225.00,
            'value_after_vat'  => 1725.00,
        ]);

        // User changes VAT to 0 (converting to 0% VAT TAS fuel receipt)
        $response = $this->postJson(route('finance.tax-deductions.update-ocr-item'), [
            'id'               => $item->id,
            'receipt_id'       => $receipt->id,
            'seller_name'      => 'Fuel Station Central Revised',
            'supplier_tin'     => '0033221100',
            'fs_no'            => '00778899',
            'mrc_no'           => 'MRC-FUEL-01',
            'receipt_date'     => '2026-10-17',
            'item_description' => 'Diesel Fuel 0% VAT Fuel Receipt',
            'category'         => 'fuel',
            'vat_category'     => 'E',
            'purchase_type'    => 'PUR_GD',
            'total_value'      => 1500.00,
            'vat_amount'       => 0.00,
            'value_after_vat'  => 1500.00,
        ]);

        $response->assertStatus(200);

        $receipt->refresh();
        $this->assertEquals('no_vat', $receipt->tax_type, 'Receipt tax_type must be set to no_vat when VAT is 0');
        $this->assertEquals(0.00, (float)$receipt->vat_amount);
        $this->assertEquals(1500.00, (float)$receipt->total_amount);

        // Reload movements page: verify it is NOT dropped from all_ocr_items
        $controller = new \App\Http\Controllers\Finance\FinanceTaxReportController();
        $view = $controller->movementsPage(new \Illuminate\Http\Request([
            'ethiopian_year'  => 2019,
            'ethiopian_month' => 2,
            'tab'             => 'ocr',
        ]));

        $movementsData = $view->getData()['movementsData'];
        $allOcr = collect($movementsData['all_ocr_items'] ?? []);

        $found = $allOcr->firstWhere('id', $item->id);
        $this->assertNotNull($found, 'Zero-VAT converted OCR receipt must NOT disappear from all_ocr_items on reload');
        $this->assertEquals(0.00, (float)$found['vat_amount']);
        $this->assertEquals('Fuel Station Central Revised', $found['vendor_name']);
        $this->assertTrue((bool)$found['is_no_vat']);
    }

    public function test_movements_page_respects_tab_parameter()
    {
        $controller = new \App\Http\Controllers\Finance\FinanceTaxReportController();

        $viewOcr = $controller->movementsPage(new \Illuminate\Http\Request([
            'ethiopian_year'  => 2019,
            'ethiopian_month' => 2,
            'tab'             => 'ocr',
        ]));
        $this->assertEquals('ocr', $viewOcr->getData()['activeTab']);

        $viewTas = $controller->movementsPage(new \Illuminate\Http\Request([
            'ethiopian_year'  => 2019,
            'ethiopian_month' => 2,
            'tab'             => 'tas',
        ]));
        $this->assertEquals('tas', $viewTas->getData()['activeTab']);

        $viewDefault = $controller->movementsPage(new \Illuminate\Http\Request([
            'ethiopian_year'  => 2019,
            'ethiopian_month' => 2,
        ]));
        $this->assertEquals('income', $viewDefault->getData()['activeTab']);
    }

    public function test_save_does_not_fail_when_tax_type_column_missing_from_receipts_table()
    {
        $this->assertFalse(Schema::hasColumn('receipts', 'tax_type'), 'Precondition: receipts table must NOT have tax_type column');

        $receipt = Receipt::create([
            'receipt_number' => 'RCP-TEST-NO-COL-001',
            'subtotal'       => 400.00,
            'vat_amount'     => 60.00,
            'total_amount'   => 460.00,
            'vendor_name'    => 'Initial Merchant',
        ]);

        $item = ReceiptItem::create([
            'receipt_id'       => $receipt->id,
            'item_description' => 'Initial Description',
            'vat_category'     => 'G',
            'purchase_type'    => 3,
            'qty'              => 1,
            'unit_price'       => 400.00,
            'total_value'      => 400.00,
            'vat_amount'       => 60.00,
            'value_after_vat'  => 460.00,
        ]);

        // Exact payload user submitted: id=22, total_value=478.26, vat_amount=71.74, value_after_vat=550
        $response = $this->postJson(route('finance.tax-deductions.update-ocr-item'), [
            'id'               => $item->id,
            'receipt_id'       => $receipt->id,
            'seller_name'      => 'Updated Merchant PLC',
            'supplier_tin'     => '0012345678',
            'fs_no'            => '00088899',
            'mrc_no'           => 'MRC-TEST-88',
            'receipt_date'     => '2026-10-23',
            'item_description' => 'Updated Item Description',
            'total_value'      => 478.26,
            'vat_amount'       => 71.74,
            'value_after_vat'  => 550.00,
            'vat_category'     => 'G',
            'purchase_type'    => '1',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $receipt->refresh();
        $this->assertEquals(478.26, (float)$receipt->subtotal);
        $this->assertEquals(71.74, (float)$receipt->vat_amount);
        $this->assertEquals(550.00, (float)$receipt->total_amount);
        $this->assertEquals('Updated Merchant PLC', $receipt->vendor_name);
        $this->assertEquals('vat_standard', $receipt->tax_type);
    }
}
