<?php

namespace Tests\Unit;

use App\Http\Controllers\ExpenseRequestController;
use App\Http\Controllers\OfficeSupplyRequestController;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\ChartOfAccount;
use App\Models\ExpenseRequest;
use App\Models\OfficeMaterialRequest;
use App\Models\PettyCashReplenishment;
use App\Models\PurchaseRequest;
use App\Models\ProcurementPayment;
use App\Models\User;
use App\Services\ProcurementLifecycleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PaymentCoaDeductionTest extends TestCase
{
    protected User $financeStaff;
    protected ChartOfAccount $cbeCoa;
    protected BankAccount $cbeBank;

    protected function setUp(): void
    {
        parent::setUp();

        DB::statement('PRAGMA foreign_keys = OFF;');

        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('bank_transactions');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('chart_of_accounts');
        Schema::dropIfExists('expense_requests');
        Schema::dropIfExists('office_material_requests');
        Schema::dropIfExists('petty_cash_replenishment_items');
        Schema::dropIfExists('petty_cash_replenishments');
        Schema::dropIfExists('procurement_payments');
        Schema::dropIfExists('purchase_requests');
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
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

        Schema::create('chart_of_accounts', function ($table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('type')->default('asset');
            $table->string('subtype')->nullable();
            $table->decimal('opening_balance', 18, 2)->default(0);
            $table->decimal('current_balance', 18, 2)->default(0);
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('bank_accounts', function ($table) {
            $table->id();
            $table->string('account_name');
            $table->string('account_number');
            $table->string('bank_name');
            $table->string('branch')->nullable();
            $table->string('account_type')->default('checking');
            $table->string('currency')->default('ETB');
            $table->decimal('current_balance', 18, 2)->default(0);
            $table->unsignedBigInteger('coa_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('bank_transactions', function ($table) {
            $table->id();
            $table->foreignId('bank_account_id');
            $table->date('transaction_date');
            $table->string('type');
            $table->decimal('amount', 18, 2);
            $table->decimal('balance_after', 18, 2)->default(0);
            $table->string('reference_no')->nullable();
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_reconciled')->default(false);
            $table->timestamps();
        });

        Schema::create('expense_requests', function ($table) {
            $table->id();
            $table->string('request_number', 50)->unique();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('letter_id')->nullable();
            $table->unsignedBigInteger('purchase_request_id')->nullable();
            $table->unsignedBigInteger('credit_store_ledger_id')->nullable();
            $table->unsignedBigInteger('petty_cash_owner_id')->nullable();
            $table->string('category', 50)->default('Service');
            $table->string('service_type', 100)->nullable();
            $table->string('other_reason')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('gross_amount', 12, 2)->default(0);
            $table->string('vat_type', 30)->default('none');
            $table->decimal('vat_rate', 5, 2)->default(15.00);
            $table->decimal('vat_amount', 12, 2)->default(0);
            $table->boolean('has_withholding')->default(false);
            $table->decimal('withholding_rate', 5, 2)->default(3.00);
            $table->decimal('withholding_amount', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2)->default(0);
            $table->boolean('is_bank_payment')->default(false);
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('account_name')->nullable();
            $table->string('status', 50)->default('Pending (HR Review)');
            $table->text('description')->nullable();
            $table->string('attachment')->nullable();
            $table->string('withholding_receipt')->nullable();
            $table->string('withholding_receipt_number')->nullable();
            $table->unsignedBigInteger('assigned_finance_staff_id')->nullable();
            $table->unsignedBigInteger('finance_staff_id')->nullable();
            $table->unsignedBigInteger('finance_head_id')->nullable();
            $table->unsignedBigInteger('paid_by')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_reference')->nullable();
            $table->text('payment_notes')->nullable();
            $table->unsignedBigInteger('bank_account_id')->nullable();
            $table->unsignedBigInteger('coa_id')->nullable();
            $table->unsignedBigInteger('chart_of_account_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('office_material_requests', function ($table) {
            $table->id();
            $table->string('request_no', 50)->unique();
            $table->unsignedBigInteger('requested_by');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->string('office_purpose')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('status', 50)->default('draft');
            $table->unsignedBigInteger('coa_id')->nullable();
            $table->unsignedBigInteger('bank_account_id')->nullable();
            $table->unsignedBigInteger('assigned_finance_staff_id')->nullable();
            $table->unsignedBigInteger('paid_by')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_reference')->nullable();
            $table->text('payment_notes')->nullable();
            $table->string('attachment')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('purchase_requests', function ($table) {
            $table->id();
            $table->string('pr_number', 50)->nullable();
            $table->string('pr_no', 50)->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->string('status', 50)->default('pending_finance');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('procurement_payments', function ($table) {
            $table->id();
            $table->unsignedBigInteger('purchase_request_id');
            $table->string('method', 30)->default('cash');
            $table->unsignedBigInteger('coa_account_id')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('gross_amount', 12, 2)->nullable();
            $table->string('vat_type', 30)->nullable();
            $table->decimal('vat_rate', 5, 2)->nullable();
            $table->decimal('vat_amount', 12, 2)->nullable();
            $table->boolean('has_withholding')->default(false);
            $table->decimal('withholding_rate', 5, 2)->nullable();
            $table->decimal('withholding_amount', 12, 2)->nullable();
            $table->string('withholding_receipt')->nullable();
            $table->string('withholding_receipt_number')->nullable();
            $table->decimal('net_amount', 12, 2)->nullable();
            $table->unsignedBigInteger('assigned_finance_staff_id')->nullable();
            $table->string('status', 30)->default('pending_payment');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('paid_by')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->timestamps();
        });

        Schema::create('journal_entries', function ($table) {
            $table->id();
            $table->string('entry_no')->unique();
            $table->date('entry_date');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('posted');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('journal_entry_lines', function ($table) {
            $table->id();
            $table->unsignedBigInteger('journal_entry_id');
            $table->unsignedBigInteger('account_id');
            $table->string('description')->nullable();
            $table->string('side');
            $table->decimal('amount', 18, 2);
            $table->timestamps();
        });

        Schema::create('petty_cash_replenishments', function ($table) {
            $table->id();
            $table->string('request_no')->unique();
            $table->unsignedBigInteger('chart_of_account_id')->nullable();
            $table->unsignedBigInteger('source_coa_id')->nullable();
            $table->decimal('requested_amount', 18, 2)->default(0);
            $table->decimal('fulfilled_amount', 18, 2)->default(0);
            $table->string('status')->default('pending_gm');
            $table->unsignedBigInteger('finance_head_id')->nullable();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->string('fulfillment_reference')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('petty_cash_replenishment_items', function ($table) {
            $table->id();
            $table->unsignedBigInteger('petty_cash_replenishment_id');
            $table->string('item_description')->nullable();
            $table->decimal('amount', 18, 2)->default(0);
            $table->timestamps();
        });

        $this->financeStaff = User::create([
            'name' => 'Finance Staff Cashier',
            'email' => 'cashier@test.com',
            'password' => bcrypt('password'),
        ]);

        $adminRole = DB::table('roles')->insertGetId(['name' => 'admin', 'guard_name' => 'web']);
        $finRole = DB::table('roles')->insertGetId(['name' => 'finance', 'guard_name' => 'web']);

        DB::table('model_has_roles')->insert([
            ['role_id' => $adminRole, 'model_type' => User::class, 'model_id' => $this->financeStaff->id],
            ['role_id' => $finRole, 'model_type' => User::class, 'model_id' => $this->financeStaff->id],
        ]);

        $this->cbeCoa = ChartOfAccount::create([
            'code' => '1020',
            'name' => 'CBE Operating Bank Account',
            'type' => 'asset',
            'current_balance' => 50000.00,
            'is_active' => true,
        ]);

        $this->cbeBank = BankAccount::create([
            'account_name' => 'Wechecha CBE Main',
            'account_number' => '1000123456789',
            'bank_name' => 'Commercial Bank of Ethiopia',
            'current_balance' => 50000.00,
            'coa_id' => $this->cbeCoa->id,
            'is_active' => true,
        ]);
    }

    /**
     * Test that Expense Request payment deducts from COA account when bank_account_id is also set.
     */
    public function test_expense_request_payment_deducts_from_coa_account_when_bank_account_is_set()
    {
        $this->actingAs($this->financeStaff);

        $exp = ExpenseRequest::create([
            'request_number' => 'EXP-2026-0001',
            'user_id' => $this->financeStaff->id,
            'category' => 'Service',
            'amount' => 5000.00,
            'gross_amount' => 5000.00,
            'net_amount' => 5000.00,
            'status' => ExpenseRequest::STATUS_ASSIGNED,
            'bank_account_id' => $this->cbeBank->id,
            'chart_of_account_id' => $this->cbeCoa->id,
            'coa_id' => $this->cbeCoa->id,
            'assigned_finance_staff_id' => $this->financeStaff->id,
            'finance_staff_id' => $this->financeStaff->id,
        ]);

        $controller = app(ExpenseRequestController::class);

        $request = Request::create(route('expense-requests.mark-paid', $exp->id), 'POST', [
            'payment_reference' => 'TXN-998877',
            'gross_amount' => 5000.00,
            'vat_type' => 'none',
            'has_withholding' => '0',
            'net_amount' => 5000.00,
            'paid_amount' => 5000.00,
        ]);

        $controller->markPaid($request, $exp);

        // Verify status is paid
        $exp->refresh();
        $this->assertEquals(ExpenseRequest::STATUS_PAID, $exp->status);

        // Verify COA current_balance is deducted
        $this->cbeCoa->refresh();
        $this->assertEquals(45000.00, (float)$this->cbeCoa->current_balance);

        // Verify Bank Account current_balance is also deducted
        $this->cbeBank->refresh();
        $this->assertEquals(45000.00, (float)$this->cbeBank->current_balance);
    }

    /**
     * Test that Expense Request payment deducts from COA when bank_account_id is null.
     */
    public function test_expense_request_payment_deducts_from_coa_without_bank_account()
    {
        $this->actingAs($this->financeStaff);

        $cashCoa = ChartOfAccount::create([
            'code' => '1010',
            'name' => 'Petty Cash On Hand',
            'type' => 'asset',
            'current_balance' => 20000.00,
            'is_active' => true,
        ]);

        $exp = ExpenseRequest::create([
            'request_number' => 'EXP-2026-0002',
            'user_id' => $this->financeStaff->id,
            'category' => 'Transport',
            'amount' => 2500.00,
            'gross_amount' => 2500.00,
            'net_amount' => 2500.00,
            'status' => ExpenseRequest::STATUS_ASSIGNED,
            'bank_account_id' => null,
            'chart_of_account_id' => $cashCoa->id,
            'coa_id' => $cashCoa->id,
            'assigned_finance_staff_id' => $this->financeStaff->id,
        ]);

        $controller = app(ExpenseRequestController::class);

        $request = Request::create(route('expense-requests.mark-paid', $exp->id), 'POST', [
            'payment_reference' => 'CSH-VOUCHER-01',
            'gross_amount' => 2500.00,
            'vat_type' => 'none',
            'net_amount' => 2500.00,
        ]);

        $controller->markPaid($request, $exp);

        $cashCoa->refresh();
        $this->assertEquals(17500.00, (float)$cashCoa->current_balance);
    }

    /**
     * Test OfficeMaterialRequest payment deducts from COA.
     */
    public function test_office_material_request_payment_deducts_from_coa()
    {
        $this->actingAs($this->financeStaff);

        $officeReq = OfficeMaterialRequest::create([
            'request_no' => 'OFF-2026-001',
            'requested_by' => $this->financeStaff->id,
            'office_purpose' => 'Printer Ink and Papers',
            'amount' => 3000.00,
            'status' => 'Assigned to Finance',
            'coa_id' => $this->cbeCoa->id,
            'bank_account_id' => $this->cbeBank->id,
            'assigned_finance_staff_id' => $this->financeStaff->id,
        ]);

        $controller = app(OfficeSupplyRequestController::class);

        $request = Request::create(route('office-requests.mark-paid', $officeReq->id), 'POST', [
            'payment_reference' => 'OFF-PAY-1122',
            'paid_amount' => 3000.00,
        ]);

        $controller->markPaid($request, $officeReq->id);

        $this->cbeCoa->refresh();
        $this->assertEquals(47000.00, (float)$this->cbeCoa->current_balance);

        $this->cbeBank->refresh();
        $this->assertEquals(47000.00, (float)$this->cbeBank->current_balance);
    }

    /**
     * Test Petty Cash Replenishment fulfillment deducts source COA and increments target petty cash COA.
     */
    public function test_petty_cash_replenishment_deducts_source_coa()
    {
        $this->actingAs($this->financeStaff);

        $pettyTargetCoa = ChartOfAccount::create([
            'code' => '1010-SITE',
            'name' => 'Site Petty Cash Box',
            'type' => 'asset',
            'current_balance' => 1000.00,
            'is_active' => true,
        ]);

        $replenishment = PettyCashReplenishment::create([
            'request_no' => 'PCR-2026-001',
            'chart_of_account_id' => $pettyTargetCoa->id,
            'requested_amount' => 10000.00,
            'status' => 'approved',
        ]);

        $exp = ExpenseRequest::create([
            'request_number' => 'EXP-PCR-2026-001',
            'user_id' => $this->financeStaff->id,
            'category' => 'Other',
            'other_reason' => 'Petty Cash Replenishment',
            'description' => "Petty Cash Replenishment #PCR-2026-001 for [1010-SITE]",
            'amount' => 10000.00,
            'gross_amount' => 10000.00,
            'net_amount' => 10000.00,
            'status' => ExpenseRequest::STATUS_ASSIGNED,
            'bank_account_id' => $this->cbeBank->id,
            'coa_id' => $this->cbeCoa->id,
            'chart_of_account_id' => $this->cbeCoa->id,
            'assigned_finance_staff_id' => $this->financeStaff->id,
        ]);

        $controller = app(ExpenseRequestController::class);

        $request = Request::create(route('expense-requests.mark-paid', $exp->id), 'POST', [
            'payment_reference' => 'REPLENISH-01',
            'gross_amount' => 10000.00,
            'net_amount' => 10000.00,
        ]);

        $controller->markPaid($request, $exp);

        // Target petty cash increased
        $pettyTargetCoa->refresh();
        $this->assertEquals(11000.00, (float)$pettyTargetCoa->current_balance);

        // Source bank COA decreased
        $this->cbeCoa->refresh();
        $this->assertEquals(40000.00, (float)$this->cbeCoa->current_balance);

        // Source bank account decreased
        $this->cbeBank->refresh();
        $this->assertEquals(40000.00, (float)$this->cbeBank->current_balance);
    }
}
