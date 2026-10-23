<?php

namespace Tests\Unit;

use App\Models\ExpenseRequest;
use App\Models\PettyCashMaterialPurchase;
use App\Models\Project;
use App\Models\Store;
use App\Models\User;
use App\Services\EthiopianDate;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MonthMovementsExpensesTest extends TestCase
{
    protected User $user;
    protected Project $project;
    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            DB::statement('PRAGMA foreign_keys = OFF;');
        } catch (\Throwable $e) {}

        $this->setupSchema();

        $this->user = User::create([
            'name'     => 'Finance Manager',
            'email'    => 'fin_mgr_' . uniqid() . '@wechecha.com',
            'password' => bcrypt('password'),
        ]);

        $this->project = Project::create([
            'name'       => 'HQ Alpha',
            'code'       => 'HQA',
            'status'     => 'active',
            'created_by' => $this->user->id,
        ]);

        $this->store = Store::create([
            'name'       => 'Central Store',
            'code'       => 'STR-01',
            'project_id' => $this->project->id,
            'is_active'  => true,
        ]);
    }

    protected function setupSchema(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_requests');
        Schema::dropIfExists('petty_cash_material_purchase_items');
        Schema::dropIfExists('petty_cash_material_purchases');
        Schema::dropIfExists('chart_of_accounts');
        Schema::dropIfExists('products');
        Schema::dropIfExists('stores');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('users');
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('receipts');
        Schema::dropIfExists('receipt_items');

        Schema::create('chart_of_accounts', function ($table) {
            $table->id();
            $table->string('code')->nullable();
            $table->string('name');
            $table->string('type')->default('expense');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('products', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('sku')->nullable();
            $table->timestamps();
        });

        Schema::create('petty_cash_material_purchase_items', function ($table) {
            $table->id();
            $table->foreignId('purchase_id');
            $table->foreignId('product_id')->nullable();
            $table->decimal('quantity', 14, 3)->default(1);
            $table->string('unit', 30)->default('pcs');
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->decimal('total_price', 18, 2)->default(0);
            $table->string('remarks', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('projects', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('status')->default('active');
            $table->foreignId('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('stores', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable();
            $table->foreignId('project_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('expense_requests', function ($table) {
            $table->id();
            $table->string('request_number')->unique();
            $table->foreignId('user_id')->nullable();
            $table->foreignId('project_id')->nullable();
            $table->string('category')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('gross_amount', 15, 2)->nullable();
            $table->string('vat_type')->default('none');
            $table->decimal('vat_rate', 5, 2)->default(15);
            $table->decimal('vat_amount', 15, 2)->default(0);
            $table->boolean('has_withholding')->default(false);
            $table->decimal('withholding_rate', 5, 2)->default(3);
            $table->decimal('withholding_amount', 15, 2)->default(0);
            $table->decimal('net_amount', 15, 2)->default(0);
            $table->string('payment_reference')->nullable();
            $table->foreignId('paid_by')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('status')->default('Paid');
            $table->boolean('vat_settled')->default(false);
            $table->boolean('withholding_settled')->default(false);
            $table->text('description')->nullable();
            $table->string('attachment')->nullable();
            $table->string('attachment_url')->nullable();
            $table->string('account_name')->nullable();
            $table->foreignId('bank_account_id')->nullable();
            $table->foreignId('chart_of_account_id')->nullable();
            $table->string('payment_method')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('petty_cash_material_purchases', function ($table) {
            $table->id();
            $table->string('purchase_no', 50)->unique();
            $table->foreignId('store_id')->nullable();
            $table->foreignId('chart_of_account_id')->nullable();
            $table->foreignId('purchased_by')->nullable();
            $table->date('purchase_date')->nullable();
            $table->string('supplier_name', 255)->nullable();
            $table->string('supplier_tin', 20)->nullable();
            $table->string('fs_no', 20)->nullable();
            $table->string('receipt_no', 100)->nullable();
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('status', 30)->default('completed');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('expenses', function ($table) {
            $table->id();
            $table->foreignId('project_id')->nullable();
            $table->string('category')->default('other');
            $table->string('description');
            $table->decimal('amount', 18, 2)->default(0);
            $table->date('expense_date')->nullable();
            $table->string('status')->default('approved');
            $table->foreignId('created_by')->nullable();
            $table->foreignId('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->string('receipt_path')->nullable();
            $table->timestamps();
        });
    }

    public function test_month_movements_captures_expenses_paid_in_month_even_if_created_earlier(): void
    {
        // Month 1 2019 EC: 2026-09-11 to 2026-10-10
        // Expense created on Sept 1, 2026 (prior month), but PAID on Sept 20, 2026
        $exp = ExpenseRequest::create([
            'request_number'    => 'EXP-PAID-001',
            'user_id'           => $this->user->id,
            'project_id'        => $this->project->id,
            'category'          => 'Office Supplies',
            'amount'            => 1500.00,
            'gross_amount'      => 1500.00,
            'vat_type'          => 'none',
            'vat_rate'          => 0,
            'vat_amount'        => 0,
            'status'            => ExpenseRequest::STATUS_PAID,
            'paid_by'           => $this->user->id,
            'paid_at'           => Carbon::create(2026, 9, 20, 14, 30, 0),
            'created_at'        => Carbon::create(2026, 9, 1, 10, 0, 0),
            'updated_at'        => Carbon::create(2026, 9, 20, 14, 30, 0),
        ]);

        $controller = new \App\Http\Controllers\Finance\FinanceTaxReportController();
        $request = new Request(['ethiopian_year' => 2019, 'ethiopian_month' => 1]);
        $response = $controller->monthMovements($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertTrue($data['success'], $data['message'] ?? '');

        $voucherNumbers = array_column($data['expenses'], 'request_number');
        $this->assertContains('EXP-PAID-001', $voucherNumbers, 'Expense paid in Month 1 must be captured in Month 1 even if created earlier.');
    }

    public function test_month_movements_captures_petty_cash_material_purchases(): void
    {
        $pc = PettyCashMaterialPurchase::create([
            'purchase_no'   => 'PCMP-20260918-001',
            'store_id'      => $this->store->id,
            'purchased_by'  => $this->user->id,
            'purchase_date' => '2026-09-18', // Month 1 2019 EC
            'supplier_name' => 'Addis Building Materials',
            'receipt_no'    => 'REC-9988',
            'fs_no'         => 'FS-12345678',
            'total_amount'  => 3250.00,
            'notes'         => 'Nails and binding wire spot purchase',
            'status'        => 'completed',
        ]);

        $controller = new \App\Http\Controllers\Finance\FinanceTaxReportController();
        $request = new Request(['ethiopian_year' => 2019, 'ethiopian_month' => 1]);
        $response = $controller->monthMovements($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertTrue($data['success'], $data['message'] ?? '');

        $voucherNumbers = array_column($data['expenses'], 'request_number');
        $this->assertContains('PCMP-20260918-001', $voucherNumbers, 'Petty cash material purchase must be included in Purchases, Petty Cash & Paid Expense Vouchers.');

        $found = null;
        foreach ($data['expenses'] as $item) {
            if ($item['request_number'] === 'PCMP-20260918-001') {
                $found = $item;
                break;
            }
        }
        $this->assertNotNull($found);
        $this->assertEquals('petty_cash', $found['source_type']);
        $this->assertEquals(3250.00, (float)$found['base_amount']);
        $this->assertEquals(3250.00, (float)$found['net_disbursed']);
        $this->assertEquals('Addis Building Materials', $found['payee']);
    }

    public function test_month_movements_excludes_rejected_expenses(): void
    {
        $rejected = ExpenseRequest::create([
            'request_number'    => 'EXP-REJ-01',
            'user_id'           => $this->user->id,
            'category'          => 'Office Supplies',
            'amount'            => 999.00,
            'gross_amount'      => 999.00,
            'vat_type'          => 'none',
            'status'            => ExpenseRequest::STATUS_REJECTED,
            'created_at'        => Carbon::create(2026, 9, 15, 10, 0, 0),
        ]);

        $controller = new \App\Http\Controllers\Finance\FinanceTaxReportController();
        $request = new Request(['ethiopian_year' => 2019, 'ethiopian_month' => 1]);
        $response = $controller->monthMovements($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertTrue($data['success'], $data['message'] ?? '');

        $voucherNumbers = array_column($data['expenses'], 'request_number');
        $this->assertNotContains('EXP-REJ-01', $voucherNumbers, 'Rejected expense requests must never be included.');
    }

    public function test_month_movements_direct_project_expense(): void
    {
        $direct = \App\Models\Expense::create([
            'project_id'   => $this->project->id,
            'category'     => 'equipment',
            'description'  => 'Excavator rental on-site spot payment',
            'amount'       => 4500.00,
            'expense_date' => '2026-09-25', // Month 1 2019 EC
            'status'       => 'approved',
            'created_by'   => $this->user->id,
        ]);

        $controller = new \App\Http\Controllers\Finance\FinanceTaxReportController();
        $request = new Request(['ethiopian_year' => 2019, 'ethiopian_month' => 1]);
        $response = $controller->monthMovements($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertTrue($data['success']);

        $found = null;
        foreach ($data['expenses'] as $item) {
            if ($item['request_number'] === 'EXP-DIR-' . $direct->id) {
                $found = $item;
                break;
            }
        }
        $this->assertNotNull($found, 'Direct expense should be captured.');
        $this->assertEquals('direct_expense', $found['source_type']);
        $this->assertEquals(4500.00, (float)$found['base_amount']);
    }

    public function test_month_movements_financial_summary_totals(): void
    {
        // 1. Expense request with 15% VAT and 3% WHT
        // Gross = 11500 (Base = 10000, VAT = 1500, WHT = 300, Net = 11200)
        ExpenseRequest::create([
            'request_number'            => 'EXP-SUM-01',
            'user_id'                   => $this->user->id,
            'project_id'                => $this->project->id,
            'category'                  => 'Material Purchase',
            'amount'                    => 11500.00,
            'gross_amount'              => 11500.00,
            'vat_type'                  => 'exclusive',
            'vat_rate'                  => 15.00,
            'vat_amount'                => 1500.00,
            'has_withholding'           => true,
            'withholding_rate'          => 3.00,
            'withholding_amount'        => 300.00,
            'net_amount'                => 11200.00,
            'status'                    => ExpenseRequest::STATUS_PAID,
            'paid_by'                   => $this->user->id,
            'paid_at'                   => Carbon::create(2026, 9, 15, 12, 0, 0),
        ]);

        // 2. Petty cash material purchase of 2000
        PettyCashMaterialPurchase::create([
            'purchase_no'   => 'PCMP-SUM-01',
            'store_id'      => $this->store->id,
            'purchased_by'  => $this->user->id,
            'purchase_date' => '2026-09-16',
            'supplier_name' => 'Hardware Store',
            'receipt_no'    => 'R-1',
            'total_amount'  => 2000.00,
            'status'        => 'completed',
        ]);

        $controller = new \App\Http\Controllers\Finance\FinanceTaxReportController();
        $request = new Request(['ethiopian_year' => 2019, 'ethiopian_month' => 1]);
        $response = $controller->monthMovements($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertTrue($data['success']);

        // Base = 11500 (from Exp) + 2000 (from PC) = 13500
        $this->assertEquals(13500.00, (float)$data['summary']['purchases_base']);
        // Input VAT = 1500
        $this->assertEquals(1500.00, (float)$data['summary']['input_vat']);
        // Withholding = 345 (3% of 11500 base)
        $this->assertEquals(345.00, (float)$data['summary']['withholding_tax']);
        // Net Disbursed = (11500 + 1500 - 345) + 2000 = 14655
        $this->assertEquals(14655.00, (float)$data['summary']['net_disbursed']);

        // Assert VAT vs TAS purchases separation
        $this->assertEquals(1, $data['summary']['vat_purchases_count']);
        $this->assertEquals(11500.00, (float)$data['summary']['vat_purchases_base']);
        $this->assertEquals(1500.00, (float)$data['summary']['vat_purchases_vat']);

        $this->assertEquals(1, $data['summary']['tas_purchases_count']);
        $this->assertEquals(2000.00, (float)$data['summary']['tas_purchases_base']);
        $this->assertEquals(0.0, (float)$data['summary']['tas_purchases_vat']);

        $this->assertCount(1, $data['vat_purchases']);
        $this->assertEquals('EXP-SUM-01', $data['vat_purchases'][0]['request_number']);
        $this->assertTrue($data['vat_purchases'][0]['is_vat_purchase']);
        $this->assertFalse($data['vat_purchases'][0]['is_tas_purchase']);

        $this->assertCount(1, $data['tas_purchases']);
        $this->assertEquals('PCMP-SUM-01', $data['tas_purchases'][0]['request_number']);
        $this->assertFalse($data['tas_purchases'][0]['is_vat_purchase']);
        $this->assertTrue($data['tas_purchases'][0]['is_tas_purchase']);
    }

    public function test_movements_page_handles_vehicle_fuel_requests_with_only_request_no_column()
    {
        Schema::dropIfExists('vehicle_fuel_requests');
        Schema::create('vehicle_fuel_requests', function ($table) {
            $table->id();
            $table->string('request_no')->nullable();
            $table->unsignedBigInteger('expense_request_id')->nullable();
            $table->string('fuel_station_tin')->nullable();
            $table->string('fs_no')->nullable();
            $table->string('mrc_no')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Ensure vehicle_fuel_requests strictly has no request_number column initially
        $this->assertFalse(Schema::hasColumn('vehicle_fuel_requests', 'request_number'));

        DB::table('vehicle_fuel_requests')->insert([
            'request_no'       => 'REQ-20261008-QHKA',
            'fuel_station_tin' => '0012345678',
            'fs_no'            => '00088899',
            'mrc_no'           => 'MRC-999',
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        ExpenseRequest::create([
            'request_number'    => 'REQ-20261008-QHKA',
            'category'          => 'fuel',
            'payee'             => 'Total Station',
            'amount'            => 1250.00,
            'gross_amount'      => 1250.00,
            'net_amount'        => 1250.00,
            'vat_amount'        => 0.00,
            'withholding_amount'=> 0.00,
            'status'            => 'paid',
            'paid_at'           => '2026-10-15 10:00:00', // Month 2 in Ethiopian Year 2019
        ]);

        $controller = new \App\Http\Controllers\Finance\FinanceTaxReportController();
        $request = new Request(['ethiopian_year' => 2019, 'ethiopian_month' => 2]);
        $response = $controller->movementsPage($request);

        $this->assertInstanceOf(\Illuminate\View\View::class, $response);
        $viewData = $response->getData();
        $this->assertArrayHasKey('movementsData', $viewData);
        $this->assertNotEmpty($viewData['movementsData']['tas_purchases']);
        $this->assertEquals('0012345678', $viewData['movementsData']['tas_purchases'][0]['seller_tin']);
        $this->assertEquals('00088899', $viewData['movementsData']['tas_purchases'][0]['fs_no']);
    }
}
