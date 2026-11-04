<?php

namespace Tests\Unit;

use App\Http\Controllers\DashboardController;
use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\Expense;
use App\Models\ExpenseRequest;
use App\Models\FixedAsset;
use App\Models\IncomeRecord;
use App\Models\Inventory;
use App\Models\Letter;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProcurementPayment;
use App\Models\Project;
use App\Models\PurchaseRequest;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GlobalAdminDashboardTest extends TestCase
{
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        DB::statement('PRAGMA foreign_keys = OFF;');
        Schema::dropIfExists('letter_recipients');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('procurement_payments');
        Schema::dropIfExists('purchase_requests');
        Schema::dropIfExists('expense_requests');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('income_records');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('chart_of_accounts');
        Schema::dropIfExists('letters');
        Schema::dropIfExists('fixed_assets');
        Schema::dropIfExists('inventory');
        Schema::dropIfExists('products');
        Schema::dropIfExists('stores');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('users');

        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->boolean('is_admin')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('employees', function ($table) {
            $table->id();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('name')->nullable();
            $table->string('status')->default('active');
            $table->boolean('is_dead_file')->default(false);
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
            $table->decimal('contract_value', 15, 2)->default(0);
            $table->decimal('budget_allocated', 15, 2)->default(0);
            $table->string('status')->default('active');
            $table->unsignedBigInteger('created_by')->nullable();
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

        Schema::create('products', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->string('code')->nullable();
            $table->string('category')->nullable();
            $table->string('sub_category')->nullable();
            $table->string('unit')->default('pcs');
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('selling_price', 15, 2)->default(0);
            $table->decimal('reorder_level', 15, 2)->default(5);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('inventory', function ($table) {
            $table->id();
            $table->foreignId('store_id');
            $table->foreignId('product_id');
            $table->decimal('quantity_on_hand', 15, 3)->default(0);
            $table->decimal('quantity_reserved', 15, 3)->default(0);
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->decimal('min_stock', 15, 3)->default(0);
            $table->timestamps();
        });

        Schema::create('fixed_assets', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('code_prefix')->nullable();
            $table->integer('total_quantity')->default(1);
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->date('purchase_date')->nullable();
            $table->string('supplier')->nullable();
            $table->foreignId('store_id')->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('letters', function ($table) {
            $table->id();
            $table->string('letter_number')->nullable();
            $table->string('type')->default('incoming');
            $table->string('subject')->nullable();
            $table->string('status')->default('draft');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('letter_recipients', function ($table) {
            $table->id();
            $table->foreignId('letter_id');
            $table->foreignId('to_user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('chart_of_accounts', function ($table) {
            $table->id();
            $table->string('code');
            $table->string('name');
            $table->string('type')->default('asset');
            $table->string('subtype')->nullable();
            $table->decimal('current_balance', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('bank_accounts', function ($table) {
            $table->id();
            $table->string('account_name');
            $table->string('account_number');
            $table->string('bank_name');
            $table->string('branch')->nullable();
            $table->decimal('current_balance', 15, 2)->default(0);
            $table->foreignId('coa_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('payments', function ($table) {
            $table->id();
            $table->foreignId('project_id')->nullable();
            $table->string('reference_number')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->date('payment_date');
            $table->string('payment_type')->default('progress');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('income_records', function ($table) {
            $table->id();
            $table->string('income_no')->nullable();
            $table->foreignId('project_id')->nullable();
            $table->date('income_date');
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('expenses', function ($table) {
            $table->id();
            $table->foreignId('project_id')->nullable();
            $table->string('title')->nullable();
            $table->string('category')->default('Operational');
            $table->decimal('amount', 15, 2)->default(0);
            $table->date('expense_date');
            $table->timestamps();
        });

        Schema::create('purchase_requests', function ($table) {
            $table->id();
            $table->string('pr_no')->nullable();
            $table->foreignId('project_id')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->string('status')->default('draft');
            $table->decimal('direct_buy_amount', 15, 2)->nullable();
            $table->string('current_owner_role')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('expense_requests', function ($table) {
            $table->id();
            $table->string('request_number');
            $table->foreignId('project_id')->nullable();
            $table->foreignId('purchase_request_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('category')->default('Operational');
            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('gross_amount', 15, 2)->default(0);
            $table->string('vat_type')->default('none');
            $table->decimal('vat_rate', 5, 2)->default(15.00);
            $table->decimal('vat_amount', 15, 2)->default(0);
            $table->boolean('has_withholding')->default(false);
            $table->decimal('withholding_amount', 15, 2)->default(0);
            $table->string('withholding_receipt')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('procurement_payments', function ($table) {
            $table->id();
            $table->foreignId('purchase_request_id')->nullable();
            $table->string('method')->default('cash');
            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('gross_amount', 15, 2)->default(0);
            $table->string('vat_type')->default('none');
            $table->decimal('vat_rate', 5, 2)->default(15.00);
            $table->decimal('vat_amount', 15, 2)->default(0);
            $table->boolean('has_withholding')->default(false);
            $table->decimal('withholding_amount', 15, 2)->default(0);
            $table->string('withholding_receipt')->nullable();
            $table->decimal('net_amount', 15, 2)->default(0);
            $table->string('status')->default('pending_payment');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Setup global_admin role and user
        Role::firstOrCreate(['name' => 'global_admin', 'guard_name' => 'web']);
        $this->adminUser = User::firstOrCreate(
            ['email' => 'exec_admin@wechecha.com'],
            ['name' => 'Executive Admin', 'password' => bcrypt('secret123')]
        );
        $this->adminUser->assignRole('global_admin');
        Auth::login($this->adminUser);
    }

    public function test_admin_dashboard_shows_real_numbers_across_all_history_period()
    {
        $pastDate = Carbon::now()->subMonths(3)->toDateString();
        $project = Project::create([
            'code'             => 'PRJ-EXEC-01',
            'name'             => 'Executive Test Project',
            'contract_value'   => 5000000,
            'budget_allocated' => 3000000,
            'status'           => 'active',
            'created_by'       => $this->adminUser->id,
        ]);

        Payment::create([
            'project_id'       => $project->id,
            'reference_number' => 'PAY-PAST-001',
            'amount'           => 150000.00,
            'payment_date'     => $pastDate,
            'payment_type'     => 'progress',
            'created_by'       => $this->adminUser->id,
        ]);

        Expense::create([
            'project_id'   => $project->id,
            'title'        => 'Past Site Fuel',
            'amount'       => 20000.00,
            'expense_date' => $pastDate,
            'category'     => 'Fuel & Energy',
        ]);

        ExpenseRequest::create([
            'request_number' => 'EXP-PAST-001',
            'project_id'     => $project->id,
            'user_id'        => $this->adminUser->id,
            'amount'         => 35000.00,
            'status'         => ExpenseRequest::STATUS_PAID,
            'category'       => 'Site Operations',
            'paid_at'        => Carbon::parse($pastDate),
        ]);

        $pr = PurchaseRequest::create([
            'pr_no'        => 'PR-EXEC-001',
            'project_id'   => $project->id,
            'requested_by' => $this->adminUser->id,
            'status'       => PurchaseRequest::STATUS_COMPLETED,
        ]);

        ProcurementPayment::create([
            'purchase_request_id' => $pr->id,
            'amount'              => 80000.00,
            'status'              => 'paid',
            'paid_at'             => Carbon::parse($pastDate),
            'method'              => 'bank_transfer',
        ]);

        $controller = new DashboardController();

        // Request dashboard with period = 'all'
        request()->merge(['period' => 'all']);
        $response = $controller->admin();

        $data = $response->getData();
        $kpi = $data['kpi'];

        // Under 'all', monthly_income and monthly_expenses should reflect the historical numbers
        $this->assertGreaterThanOrEqual(150000.00, $kpi['monthly_income'], 'Historical income must not be zeroed out under period=all');
        $this->assertGreaterThanOrEqual(135000.00, $kpi['monthly_expenses'], 'Historical expenses including procurement must not be zeroed out under period=all');
        $this->assertGreaterThanOrEqual(150000.00, $kpi['all_time_income']);
        $this->assertGreaterThanOrEqual(135000.00, $kpi['all_time_expenses']);
    }

    public function test_fixed_assets_valuation_computes_real_total_quantity_times_unit_cost()
    {
        FixedAsset::create([
            'name'           => 'Excavator Cat 320',
            'category'       => 'Machinery',
            'code_prefix'    => 'CAT',
            'total_quantity' => 2,
            'unit_cost'      => 4500000.00,
            'created_by'     => $this->adminUser->id,
        ]);

        $controller = new DashboardController();
        request()->merge(['period' => 'current_month']);
        $response = $controller->admin();
        $data = $response->getData();
        $kpi = $data['kpi'];

        $this->assertGreaterThanOrEqual(9000000.00, $kpi['fixed_assets_value']);
        $this->assertGreaterThanOrEqual(1, $kpi['fixed_assets_count']);
        $this->assertGreaterThanOrEqual(2, $kpi['fixed_assets_units']);
    }

    public function test_low_stock_items_returns_sku_and_unit_without_sql_errors()
    {
        $store = Store::create(['name' => 'Central Depot', 'code' => 'CD-01', 'is_active' => true]);
        $prod = Product::create([
            'name'          => 'Cement PPC 42.5',
            'sku'           => 'MAT-CEM-01',
            'code'          => 'MAT-CEM-01',
            'unit'          => 'bags',
            'reorder_level' => 10,
        ]);

        Inventory::create([
            'store_id'         => $store->id,
            'product_id'       => $prod->id,
            'quantity_on_hand' => 4,
            'unit_cost'        => 850.00,
        ]);

        $controller = new DashboardController();
        $response = $controller->admin();
        $data = $response->getData();

        $this->assertNotEmpty($data['lowStockItems']);
        $lowItem = collect($data['lowStockItems'])->firstWhere('product_name', 'Cement PPC 42.5');
        $this->assertNotNull($lowItem);
        $this->assertEquals('MAT-CEM-01', $lowItem->item_code);
        $this->assertEquals('bags', $lowItem->unit_of_measure);
    }

    public function test_cash_and_bank_includes_both_coa_and_standalone_bank_accounts()
    {
        // 1. COA Cash Account
        ChartOfAccount::create([
            'code'            => '10101',
            'name'            => 'Petty Cash Safe',
            'type'            => 'asset',
            'subtype'         => 'Cash and Bank',
            'current_balance' => 45000.00,
            'is_active'       => true,
        ]);

        // 2. Standalone Bank Account (e.g. Awash Bank)
        BankAccount::create([
            'account_name'    => 'Main Operations Awash',
            'account_number'  => 'AW-0192837465',
            'bank_name'       => 'Awash Bank',
            'current_balance' => 350000.00,
            'is_active'       => true,
        ]);

        $controller = new DashboardController();
        $response = $controller->admin();
        $data = $response->getData();
        $kpi = $data['kpi'];

        $this->assertGreaterThanOrEqual(395000.00, $kpi['cash_bank_balance']);
        $this->assertTrue(collect($data['cashAndBankCoas'])->contains('code', '10101'));
        $this->assertTrue(collect($data['cashAndBankCoas'])->contains('code', 'AW-0192837465'));
    }

    public function test_letters_count_and_display_checks_letter_number()
    {
        Letter::create([
            'letter_number' => 'LTR/WECH/2026/088',
            'subject'       => 'Notice to Commence Substructure',
            'type'          => 'outgoing',
            'status'        => 'sent',
            'created_by'    => $this->adminUser->id,
        ]);

        Letter::create([
            'letter_number' => null, // Draft unregistered
            'subject'       => 'Internal Procurement Memo Draft',
            'type'          => 'incoming',
            'status'        => 'draft',
            'created_by'    => $this->adminUser->id,
        ]);

        $controller = new DashboardController();
        $response = $controller->admin();
        $data = $response->getData();
        $kpi = $data['kpi'];

        $this->assertGreaterThanOrEqual(1, $kpi['draft_letters']);
        $this->assertGreaterThanOrEqual(1, $kpi['outgoing_letters']);

        $recentLetters = collect($data['recentLetters']);
        $registered = $recentLetters->firstWhere('subject', 'Notice to Commence Substructure');
        $this->assertNotNull($registered);
        $this->assertEquals('LTR/WECH/2026/088', $registered->letter_number);
    }

    public function test_tax_compliance_report_incorporates_procurement_payments()
    {
        $project = Project::create([
            'name'       => 'HQ Test',
            'created_by' => $this->adminUser->id,
        ]);

        $pr = PurchaseRequest::create([
            'pr_no'        => 'PR-TAX-001',
            'project_id'   => $project->id,
            'requested_by' => $this->adminUser->id,
            'status'       => PurchaseRequest::STATUS_COMPLETED,
        ]);

        ProcurementPayment::create([
            'purchase_request_id' => $pr->id,
            'amount'              => 200000.00,
            'gross_amount'        => 200000.00,
            'vat_type'            => 'inclusive',
            'vat_amount'          => 26086.96,
            'has_withholding'     => true,
            'withholding_amount'  => 4000.00,
            'net_amount'          => 196000.00,
            'withholding_receipt' => 'uploads/tax/wht_pr_tax.pdf',
            'status'              => 'paid',
            'paid_at'             => now(),
            'method'              => 'bank_transfer',
        ]);

        $controller = new DashboardController();
        $taxData = $controller->getTaxComplianceReportData();

        $this->assertGreaterThanOrEqual(1, $taxData['total_records']);
        $this->assertGreaterThanOrEqual(200000.00, $taxData['total_gross_base']);
        $this->assertGreaterThanOrEqual(26086.96, $taxData['total_vat_amount']);
        $this->assertGreaterThanOrEqual(4000.00, $taxData['total_withholding_amount']);
        $this->assertGreaterThanOrEqual(1, $taxData['slips_attached_count']);
    }

    public function test_tax_compliance_report_groups_by_ethiopian_calendar_correctly()
    {
        $project = Project::firstOrCreate(
            ['name' => 'HQ Tax Ethiopian Project'],
            ['created_by' => $this->adminUser->id]
        );

        // Date in Meskerem 2019 (e.g. 2026-09-25)
        $meskeremDate = Carbon::parse('2026-09-25 10:00:00');
        // Date in Tikimt 2019 (e.g. 2026-10-25)
        $tikimtDate = Carbon::parse('2026-10-25 14:00:00');

        $exp1 = ExpenseRequest::create([
            'request_number'      => 'EXP-ETH-001',
            'user_id'             => $this->adminUser->id,
            'project_id'          => $project->id,
            'category'            => 'office_supplies',
            'amount'              => 50000.00,
            'gross_amount'        => 50000.00,
            'vat_type'            => 'exclusive',
            'vat_rate'            => 15.00,
            'vat_amount'          => 7500.00,
            'has_withholding'     => true,
            'withholding_amount'  => 1000.00,
            'withholding_receipt' => 'uploads/tax/slip_001.pdf',
            'status'              => ExpenseRequest::STATUS_PAID,
            'paid_at'             => $meskeremDate,
            'created_at'          => $meskeremDate,
        ]);

        ExpenseRequest::create([
            'request_number'      => 'EXP-ETH-002',
            'user_id'             => $this->adminUser->id,
            'project_id'          => $project->id,
            'category'            => 'equipment',
            'amount'              => 80000.00,
            'gross_amount'        => 80000.00,
            'vat_type'            => 'exclusive',
            'vat_rate'            => 15.00,
            'vat_amount'          => 12000.00,
            'has_withholding'     => true,
            'withholding_amount'  => 1600.00,
            'withholding_receipt' => null,
            'status'              => ExpenseRequest::STATUS_PAID,
            'paid_at'             => $tikimtDate,
            'created_at'          => $tikimtDate,
        ]);

        $controller = new DashboardController();
        $taxData = $controller->getTaxComplianceReportData();

        // 1. By default, calendar is Ethiopian
        $this->assertEquals('ethiopian', $taxData['calendar']);
        $this->assertNotEmpty($taxData['monthly_report']);
        $this->assertNotEmpty($taxData['monthly_report_ec']);
        $this->assertNotEmpty($taxData['monthly_report_gc']);

        // 2. Check Ethiopian month grouping
        $ecMonths = collect($taxData['monthly_report_ec']);
        $meskeremReport = $ecMonths->first(fn($m) => ($m['eth_month'] ?? null) === 1);
        $tikimtReport = $ecMonths->first(fn($m) => ($m['eth_month'] ?? null) === 2);

        $this->assertNotNull($meskeremReport, 'Meskerem report must exist');
        $this->assertEquals('መስከረም', $meskeremReport['month_am']);
        $this->assertEquals('Meskerem', $meskeremReport['month_en']);
        $this->assertStringContainsString('መስከረም', $meskeremReport['month_label']);
        $this->assertStringContainsString('ዓ.ም.', $meskeremReport['eth_period_label']);
        $this->assertEquals(1, $meskeremReport['slips_count']);
        $this->assertEquals(7500.00, $meskeremReport['vat_amount']);
        $this->assertEquals(1000.00, $meskeremReport['wht_amount']);

        $this->assertNotNull($tikimtReport, 'Tikimt report must exist');
        $this->assertEquals('ጥቅምት', $tikimtReport['month_am']);
        $this->assertEquals('Tikimt', $tikimtReport['month_en']);
        $this->assertStringContainsString('ጥቅምት', $tikimtReport['month_label']);
        $this->assertEquals(12000.00, $tikimtReport['vat_amount']);
        $this->assertEquals(1600.00, $tikimtReport['wht_amount']);

        // 3. Test Gregorian calendar toggle parameter
        $gcTaxData = $controller->getTaxComplianceReportData('gregorian');
        $this->assertEquals('gregorian', $gcTaxData['calendar']);
        $this->assertEquals($taxData['monthly_report_gc'], $gcTaxData['monthly_report']);
    }
}
