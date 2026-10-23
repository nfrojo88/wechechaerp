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
        Schema::dropIfExists('expense_hub_vat_audit_logs');
        Schema::dropIfExists('expense_hub_vat_decisions');
        Schema::dropIfExists('receipt_items');
        Schema::dropIfExists('receipts');
        Schema::dropIfExists('expense_requests');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('users');
        Schema::dropIfExists('system_settings');

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
            $table->string('tax_type')->nullable();
            $table->string('no_vat_reason_code')->nullable();
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
            $table->string('purc_type', 20)->default('PUR_GD');
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
            'tax_type'       => 'no_vat',
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
}
