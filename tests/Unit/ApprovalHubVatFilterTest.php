<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\ExpenseRequest;
use App\Models\PurchaseRequest;
use App\Models\ProcurementPayment;
use App\Models\Project;
use App\Models\ChartOfAccount;
use App\Models\BankAccount;
use App\Models\Store;
use App\Http\Controllers\ApprovalHubController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ApprovalHubVatFilterTest extends TestCase
{
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            DB::statement('PRAGMA foreign_keys = OFF;');
        } catch (\Throwable $e) {}

        $this->setupSchema();

        $this->adminUser = User::create([
            'name'  => 'Admin Finance',
            'email' => 'admin_fin_' . uniqid() . '@example.com',
        ]);
        Auth::login($this->adminUser);
    }

    protected function setupSchema(): void
    {
        Schema::dropIfExists('petty_cash_replenishments');
        Schema::dropIfExists('expense_hub_vat_decisions');
        Schema::dropIfExists('procurement_payments');
        Schema::dropIfExists('purchase_requests');
        Schema::dropIfExists('expense_requests');
        Schema::dropIfExists('receipts');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('chart_of_accounts');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('stores');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('users');

        Schema::create('petty_cash_replenishments', function ($table) {
            $table->id();
            $table->string('request_no')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('department')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('employees', function ($table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->string('full_name')->nullable();
            $table->string('department')->nullable();
            $table->string('role_title')->nullable();
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
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('chart_of_accounts', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('bank_accounts', function ($table) {
            $table->id();
            $table->string('bank_name');
            $table->string('account_number')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('stores', function ($table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('receipts', function ($table) {
            $table->id();
            $table->string('purchasable_type')->nullable();
            $table->unsignedBigInteger('purchasable_id')->nullable();
            $table->decimal('vat_amount', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('expense_requests', function ($table) {
            $table->id();
            $table->string('request_number')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('purchase_request_id')->nullable();
            $table->string('category')->default('Service');
            $table->string('other_reason')->nullable();
            $table->text('description')->nullable();
            $table->decimal('amount', 18, 2)->default(0);
            $table->decimal('gross_amount', 18, 2)->nullable();
            $table->string('vat_type')->nullable()->default('none');
            $table->decimal('vat_rate', 5, 2)->default(15.00);
            $table->decimal('vat_amount', 18, 2)->default(0);
            $table->decimal('withholding_amount', 18, 2)->default(0);
            $table->decimal('net_amount', 18, 2)->nullable();
            $table->string('status')->default('paid');
            $table->timestamp('paid_at')->nullable();
            $table->string('attachment')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('account_name')->nullable();
            $table->string('payment_reference')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('purchase_requests', function ($table) {
            $table->id();
            $table->string('pr_no')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->text('justification')->nullable();
            $table->string('status')->default(PurchaseRequest::STATUS_COMPLETED);
            $table->decimal('direct_buy_amount', 18, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('procurement_payments', function ($table) {
            $table->id();
            $table->unsignedBigInteger('purchase_request_id');
            $table->string('method')->default('bank_transfer');
            $table->decimal('amount', 18, 2)->default(0);
            $table->decimal('gross_amount', 18, 2)->nullable();
            $table->string('vat_type')->default('none');
            $table->decimal('vat_rate', 5, 2)->default(15.00);
            $table->decimal('vat_amount', 18, 2)->default(0);
            $table->decimal('withholding_amount', 18, 2)->default(0);
            $table->decimal('net_amount', 18, 2)->nullable();
            $table->string('status')->default('paid');
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('assigned_finance_staff_id')->nullable();
            $table->unsignedBigInteger('paid_by')->nullable();
            $table->timestamps();
        });
    }

    public function test_vat_filter_separates_vat_and_non_vat_expenses()
    {
        // 1. Create an Expense Request WITH VAT
        $withVatExp = ExpenseRequest::create([
            'request_number' => 'REQ-VAT-001',
            'user_id'        => $this->adminUser->id,
            'category'       => 'Service',
            'description'    => 'Consulting with VAT',
            'amount'         => 11500.00,
            'gross_amount'   => 11500.00,
            'vat_type'       => 'inclusive',
            'vat_amount'     => 1500.00,
            'net_amount'     => 11500.00,
            'status'         => 'paid',
        ]);

        // 2. Create an Expense Request WITHOUT VAT
        $withoutVatExp = ExpenseRequest::create([
            'request_number' => 'REQ-NOVAT-002',
            'user_id'        => $this->adminUser->id,
            'category'       => 'Transport',
            'description'    => 'Taxi fare without VAT',
            'amount'         => 500.00,
            'gross_amount'   => 500.00,
            'vat_type'       => 'none',
            'vat_amount'     => 0.00,
            'net_amount'     => 500.00,
            'status'         => 'paid',
        ]);

        // 3. Create a Purchase Request WITH VAT on payment
        $withVatPr = PurchaseRequest::create([
            'pr_no'          => 'PR-VAT-003',
            'status'         => PurchaseRequest::STATUS_COMPLETED,
            'requested_by'   => $this->adminUser->id,
            'direct_buy_amount' => 23000.00,
        ]);
        ProcurementPayment::create([
            'purchase_request_id' => $withVatPr->id,
            'amount'              => 23000.00,
            'gross_amount'        => 23000.00,
            'vat_type'            => 'vat_b',
            'vat_amount'          => 3000.00,
            'net_amount'          => 23000.00,
            'status'              => 'paid',
        ]);

        $controller = new ApprovalHubController();

        // A. Filter by 'with_vat' -> should return REQ-VAT-001 and PR-VAT-003
        $reqWithVat = Request::create('/expenses', 'GET', ['vat' => 'with_vat']);
        app()->instance('request', $reqWithVat);
        $viewWithVat = $controller->index($reqWithVat);
        $paginated = $viewWithVat->getData()['paginatedItems'];
        $ids = collect($paginated->items())->pluck('id_formatted')->toArray();

        $this->assertContains('REQ-VAT-001', $ids);
        $this->assertContains('PR-VAT-003', $ids);
        $this->assertNotContains('REQ-NOVAT-002', $ids);

        // B. Filter by 'without_vat' -> should return REQ-NOVAT-002 only
        $reqNoVat = Request::create('/expenses', 'GET', ['vat' => 'without_vat']);
        $viewNoVat = $controller->index($reqNoVat);
        $paginatedNoVat = $viewNoVat->getData()['paginatedItems'];
        $idsNoVat = collect($paginatedNoVat->items())->pluck('id_formatted')->toArray();

        $this->assertContains('REQ-NOVAT-002', $idsNoVat);
        $this->assertNotContains('REQ-VAT-001', $idsNoVat);
        $this->assertNotContains('PR-VAT-003', $idsNoVat);

        // C. Filter by 'all' -> should return all 3 items
        $reqAll = Request::create('/expenses', 'GET', ['vat' => 'all']);
        $viewAll = $controller->index($reqAll);
        $paginatedAll = $viewAll->getData()['paginatedItems'];
        $idsAll = collect($paginatedAll->items())->pluck('id_formatted')->toArray();

        $this->assertContains('REQ-VAT-001', $idsAll);
        $this->assertContains('PR-VAT-003', $idsAll);
        $this->assertContains('REQ-NOVAT-002', $idsAll);

        // D. Verify item properties for VAT
        $itemsCollection = collect($paginatedAll->items());
        $itemVat1 = $itemsCollection->firstWhere('id_formatted', 'REQ-VAT-001');
        $this->assertTrue($itemVat1->has_vat);
        $this->assertEquals(1500.00, $itemVat1->vat_amount);

        $itemVat2 = $itemsCollection->firstWhere('id_formatted', 'PR-VAT-003');
        $this->assertTrue($itemVat2->has_vat);
        $this->assertEquals(3000.00, $itemVat2->vat_amount);

        $itemNoVat = $itemsCollection->firstWhere('id_formatted', 'REQ-NOVAT-002');
        $this->assertFalse($itemNoVat->has_vat);
        $this->assertEquals(0, $itemNoVat->vat_amount);

        // E. Verify Blade View rendering includes the VAT filter dropdown
        $renderedHtml = $viewWithVat->render();
        $this->assertStringContainsString('name="vat"', $renderedHtml);
        $this->assertStringContainsString('value="with_vat" selected', $renderedHtml);
        $this->assertStringContainsString('VAT Option (ቫት)', $renderedHtml);
        $this->assertStringContainsString('fa-receipt', $renderedHtml);
    }
}
