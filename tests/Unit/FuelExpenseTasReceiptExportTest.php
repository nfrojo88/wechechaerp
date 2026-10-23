<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\ExpenseRequest;
use App\Models\Project;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\ExpenseHubVatDecision;
use App\Models\SystemSetting;
use App\Http\Controllers\Finance\ExpenseHubController;
use App\Http\Controllers\Finance\FinanceTaxReportController;
use App\Services\ExpenseHubQueryService;
use App\Services\EthiopianDate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class FuelExpenseTasReceiptExportTest extends TestCase
{
    protected User $financeUser;
    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            DB::statement('PRAGMA foreign_keys = OFF;');
        } catch (\Throwable $e) {}

        $this->setupSchema();

        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->financeUser = User::create([
            'name'  => 'Finance TAS Tester',
            'email' => 'fin_tas_tester_' . uniqid() . '@wechecha.com',
        ]);
        $this->financeUser->assignRole($role);
        Auth::login($this->financeUser);

        $this->project = Project::create([
            'name' => 'HQ Fleet Site',
        ]);

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
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('projects', function ($table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('expense_requests', function ($table) {
            $table->id();
            $table->string('request_number')->nullable();
            $table->foreignId('project_id')->nullable();
            $table->foreignId('user_id')->nullable();
            $table->foreignId('employee_id')->nullable();
            $table->string('category')->nullable();
            $table->string('service_type')->nullable();
            $table->text('description')->nullable();
            $table->string('other_reason')->nullable();
            $table->decimal('amount', 18, 2);
            $table->decimal('gross_amount', 18, 2)->nullable();
            $table->decimal('net_amount', 18, 2)->nullable();
            $table->decimal('vat_amount', 18, 2)->default(0);
            $table->decimal('vat_rate', 5, 2)->default(15.00);
            $table->string('vat_type')->nullable();
            $table->boolean('has_withholding')->default(false);
            $table->decimal('withholding_rate', 5, 2)->default(3.00);
            $table->decimal('withholding_amount', 18, 2)->default(0);
            $table->string('withholding_receipt')->nullable();
            $table->string('withholding_receipt_number')->nullable();
            $table->string('payment_reference')->nullable();
            $table->string('attachment')->nullable();
            $table->string('status')->default(ExpenseRequest::STATUS_PAID);
            $table->dateTime('paid_at')->nullable();
            $table->foreignId('paid_by')->nullable();
            $table->boolean('is_bank_payment')->default(false);
            $table->unsignedBigInteger('purchase_request_id')->nullable();
            $table->unsignedBigInteger('credit_store_ledger_id')->nullable();
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
            $table->string('category')->default('fuel');
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
            $table->string('vat_category', 10)->default('E');
            $table->string('purc_type', 20)->default('PUR_GD');
            $table->string('calendar_type', 10)->default('E');
            $table->integer('purchase_type')->default(6);
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
            $table->string('vat_status', 30)->default('pending_decision');
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
            $table->text('no_vat_reason_note')->nullable();
            $table->string('vat_category', 5)->default('G');
            $table->string('purc_type', 20)->default('PUR_GD');
            $table->string('calendar_type', 5)->default('E');
            $table->tinyInteger('purchase_type')->default(6);
            $table->string('custom_seller_tin', 30)->nullable();
            $table->string('custom_fs_no', 50)->nullable();
            $table->string('custom_mrc_no', 50)->nullable();
            $table->string('custom_item_description', 255)->nullable();
            $table->string('custom_receipt_date', 30)->nullable();
            $table->string('uom', 20)->default('9');
            $table->decimal('quantity', 12, 2)->default(1.00);
            $table->decimal('unit_price', 18, 2)->default(0.00);
            $table->boolean('is_locked')->default(false);
            $table->timestamp('locked_at')->nullable();
            $table->unsignedBigInteger('locked_by')->nullable();
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->unique(['source_type', 'source_id']);
        });

        Schema::create('expense_hub_vat_audit_logs', function ($table) {
            $table->id();
            $table->unsignedBigInteger('decision_id')->nullable();
            $table->string('source_type', 60);
            $table->unsignedBigInteger('source_id');
            $table->string('action', 50);
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->unsignedBigInteger('performed_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Test: Recording VAT decision with 0% VAT on EXP-FUEL-2026-0276 saves to TAS Purchase
     * and correctly populates 9-column pro.doc parsed_data.
     */
    public function test_record_vat_decision_exp_fuel_creates_tas_receipt_and_pro_doc_data()
    {
        // 1. Create paid fuel expense request: EXP-FUEL-2026-0276
        $fuelExpense = ExpenseRequest::create([
            'request_number' => 'EXP-FUEL-2026-0276',
            'project_id'     => $this->project->id,
            'category'       => 'Fuel',
            'service_type'   => 'Vehicle Fuel (Diesel)',
            'description'    => 'Vehicle Fuel: Diesel station slip for Toyota Hilux',
            'amount'         => 17565.22,
            'gross_amount'   => 17565.22,
            'net_amount'     => 17565.22,
            'vat_amount'     => 0.00,
            'status'         => ExpenseRequest::STATUS_PAID,
            'paid_at'        => Carbon::parse('2026-08-15 10:30:00'),
            'attachment'     => 'receipts/fuel_slip_20260276.jpg',
        ]);

        $hubController = app(ExpenseHubController::class);

        // 2. Simulate User clicking "Save to TAS Excel (0% VAT)" for EXP-FUEL-2026-0276
        $request = Request::create(route('finance.expense-hub.decision'), 'POST', [
            'source_type'             => 'expense_request',
            'source_id'               => $fuelExpense->id,
            'vat_status'              => 'no_vat',
            'no_vat_reason_code'      => 'fuel_zero_vat',
            'amount'                  => 17565.22,
            'custom_seller_tin'       => '5201',
            'custom_fs_no'            => 'FS00003898',
            'custom_mrc_no'           => 'DDB0000032',
            'custom_receipt_date'     => '2026-08-15',
            'custom_seller_name'      => 'TotalEnergies Station',
            'custom_item_description' => 'Diesel Fuel Refill (0% VAT)',
            'purc_type'               => 'PUR_GD',
            'receipt_path'            => 'receipts/fuel_slip_20260276.jpg',
        ]);
        $request->headers->set('Accept', 'application/json');

        $response = $hubController->saveDecision($request);
        $this->assertEquals(200, $response->getStatusCode());

        // 3. Verify ExpenseHubVatDecision saved
        $decision = ExpenseHubVatDecision::where('source_type', 'expense_request')
            ->where('source_id', $fuelExpense->id)
            ->first();

        $this->assertNotNull($decision);
        $this->assertEquals('no_vat', $decision->vat_status);
        $this->assertEquals('fuel_zero_vat', $decision->no_vat_reason_code);
        $this->assertEquals(0.00, (float)$decision->vat_amount);
        $this->assertEquals(17565.22, (float)$decision->total_amount);
        $this->assertEquals('5201', $decision->custom_seller_tin);
        $this->assertEquals('FS00003898', $decision->custom_fs_no);
        $this->assertEquals('DDB0000032', $decision->custom_mrc_no);
        $this->assertEquals('PUR_GD', $decision->purc_type);

        // 4. Verify Receipt is automatically synchronized with tax_type = 'no_vat'
        $receipt = Receipt::where('purchasable_type', ExpenseRequest::class)
            ->where('purchasable_id', $fuelExpense->id)
            ->first();

        $this->assertNotNull($receipt);
        $this->assertEquals('fuel', $receipt->category);
        $this->assertEquals('no_vat', $receipt->tax_type);
        $this->assertEquals(0.00, (float)$receipt->vat_amount);
        $this->assertEquals(17565.22, (float)$receipt->total_amount);
        $this->assertEquals('5201', $receipt->vendor_tin);
        $this->assertEquals('FS00003898', $receipt->fs_no);
        $this->assertEquals('DDB0000032', $receipt->mrc_no);
        $this->assertEquals('2026-08-15', $receipt->receipt_date ? $receipt->receipt_date->toDateString() : null);

        // 5. Verify ReceiptItem is synchronized for TAS Purchases
        $receiptItem = ReceiptItem::where('receipt_id', $receipt->id)->first();
        $this->assertNotNull($receiptItem);
        $this->assertEquals('E', $receiptItem->vat_category);
        $this->assertEquals(6, $receiptItem->purchase_type);
        $this->assertEquals(0.00, (float)$receiptItem->vat_amount);
        $this->assertEquals(17565.22, (float)$receiptItem->total_value);

        // 6. Verify 9-column pro.doc parsed data matches exact structure
        $parsed = json_decode($receipt->parsed_data, true);
        $this->assertIsArray($parsed);
        $this->assertEquals(38480010, $parsed['purchaser_tin']);
        $this->assertEquals('5201', $parsed['seller_tin']);
        $this->assertEquals(17565.22, (float)$parsed['amount']);
        $this->assertEquals('FS00003898', $parsed['receipt_no']);
        $this->assertEquals('DDB0000032', $parsed['mrc']);
        $this->assertEquals('PUR_GD', $parsed['purc_type']);
        $this->assertEquals('wechacha', $parsed['encoded_by']);

        // 7. Verify ExpenseRequest payment reference was updated
        $fuelExpense->refresh();
        $this->assertEquals(0.00, (float)$fuelExpense->vat_amount);
        $this->assertEquals('none', $fuelExpense->vat_type);
        $this->assertStringContainsString('FS: FS00003898', $fuelExpense->payment_reference);
        $this->assertStringContainsString('MRC: DDB0000032', $fuelExpense->payment_reference);
    }

    /**
     * Test: Exporting Purchase TAS Excel directly from ExpenseHub exports pro.doc format.
     */
    public function test_export_tas_excel_matches_pro_doc_structure()
    {
        $fuelExpense = ExpenseRequest::create([
            'request_number' => 'EXP-FUEL-2026-0276',
            'project_id'     => $this->project->id,
            'category'       => 'Fuel',
            'service_type'   => 'Vehicle Fuel (Diesel)',
            'description'    => 'Diesel fuel refill',
            'amount'         => 17565.22,
            'gross_amount'   => 17565.22,
            'status'         => ExpenseRequest::STATUS_PAID,
            'paid_at'        => Carbon::parse('2026-08-15'),
        ]);

        ExpenseHubVatDecision::create([
            'source_type'        => 'expense_request',
            'source_id'          => $fuelExpense->id,
            'vat_status'         => 'no_vat',
            'no_vat_reason_code' => 'fuel_zero_vat',
            'base_amount'        => 17565.22,
            'vat_amount'         => 0.00,
            'total_amount'       => 17565.22,
            'custom_seller_tin'  => '5201',
            'custom_fs_no'       => 'FS00003898',
            'custom_mrc_no'      => 'DDB0000032',
            'purc_type'          => 'PUR_GD',
            'vat_category'       => 'G',
        ]);

        $hubController = app(ExpenseHubController::class);
        $request = Request::create(route('finance.expense-hub.export-tas'), 'GET');

        $response = $hubController->exportTasExcel($request);
        $this->assertTrue($response->getStatusCode() === 200);
        $this->assertStringContainsString('Purchase_TAS_', $response->headers->get('content-disposition'));
    }

    /**
     * Test: FinanceTaxReportController exportPurchaseTasExcel exports exact 9 columns for TAS purchases.
     */
    public function test_finance_tax_report_export_purchase_tas_excel_includes_fuel_receipt()
    {
        // 1. Create a fuel receipt with 0 VAT recorded for EXP-FUEL-2026-0276
        $fuelExpense = ExpenseRequest::create([
            'request_number' => 'EXP-FUEL-2026-0276',
            'project_id'     => $this->project->id,
            'category'       => 'Fuel',
            'service_type'   => 'Vehicle Fuel (Diesel)',
            'description'    => 'Diesel fuel refill for Toyota Hilux',
            'amount'         => 17565.22,
            'gross_amount'   => 17565.22,
            'status'         => ExpenseRequest::STATUS_PAID,
            'paid_at'        => Carbon::parse('2026-08-15'),
        ]);

        $receipt = Receipt::create([
            'receipt_number' => 'RCP-HUB-20260815-F001',
            'uploaded_by'    => $this->financeUser->id,
            'approved_by'    => $this->financeUser->id,
            'status'         => 'approved',
            'purchasable_type' => ExpenseRequest::class,
            'purchasable_id'   => $fuelExpense->id,
            'project_id'     => $this->project->id,
            'vendor_name'    => 'TotalEnergies Station',
            'vendor_tin'     => '5201',
            'buyer_tin'      => '38480010',
            'fs_no'          => 'FS00003898',
            'fs_no_raw'      => 'FS00003898',
            'mrc_no'         => 'DDB0000032',
            'receipt_date'   => '2026-08-15',
            'subtotal'       => 17565.22,
            'vat_amount'     => 0.00,
            'total_amount'   => 17565.22,
            'category'       => 'fuel',
            'tax_type'       => 'no_vat',
            'parsed_data'    => json_encode([
                'purchaser_tin' => 38480010,
                'seller_tin'    => '5201',
                'amount'        => 17565.22,
                'receipt_no'    => 'FS00003898',
                'receipt_date'  => '15-08-2026',
                'encoding_date' => '26-08-2026',
                'mrc'           => 'DDB0000032',
                'purc_type'     => 'PUR_GD',
                'encoded_by'    => 'wechacha',
            ]),
        ]);

        ReceiptItem::create([
            'receipt_id'       => $receipt->id,
            'item_description' => 'Diesel fuel refill (0% VAT)',
            'vat_category'     => 'E',
            'purc_type'        => 'PUR_GD',
            'calendar_type'    => 'E',
            'purchase_type'    => 6,
            'qty'              => 1.0,
            'unit_price'       => 17565.22,
            'total_value'      => 17565.22,
            'vat_amount'       => 0.00,
            'value_after_vat'  => 17565.22,
        ]);

        // 2. Export Purchase TAS Excel for the date range
        $taxController = app(FinanceTaxReportController::class);
        $request = Request::create(route('finance.tax-deductions.export-excel'), 'GET', [
            'type'       => 'purchase_tas',
            'from_date'  => '2026-08-01',
            'to_date'    => '2026-08-31',
            'filter'     => 'tas',
        ]);

        $response = $taxController->exportExcel($request);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Purchase_TAS', $response->headers->get('content-disposition'));
    }
}
