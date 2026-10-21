<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\ExpenseRequest;
use App\Models\PurchaseRequest;
use App\Models\ProcurementPayment;
use App\Models\Project;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\ExpenseHubVatDecision;
use App\Models\ExpenseHubVatAuditLog;
use App\Models\IncomeRecord;
use App\Models\TaxSettlement;
use App\Models\ChartOfAccount;
use App\Models\BankAccount;
use App\Http\Controllers\Finance\ExpenseHubController;
use App\Http\Controllers\Finance\FinanceTaxReportController;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\ExpenseHubQueryService;

class ExpenseHubToTaxLedgerSyncTest extends TestCase
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
            'name'  => 'Finance Hub Tester',
            'email' => 'fin_hub_test_' . uniqid() . '@wechecha.com',
        ]);
        $this->financeUser->assignRole($role);
        Auth::login($this->financeUser);

        $this->project = Project::create([
            'name' => 'HQ Site',
        ]);
    }

    protected function setupSchema(): void
    {
        Schema::dropIfExists('expense_hub_vat_audit_logs');
        Schema::dropIfExists('expense_hub_vat_decisions');
        Schema::dropIfExists('receipt_items');
        Schema::dropIfExists('receipts');
        Schema::dropIfExists('tax_settlements');
        Schema::dropIfExists('income_records');
        Schema::dropIfExists('procurement_payments');
        Schema::dropIfExists('purchase_requests');
        Schema::dropIfExists('expense_requests');
        Schema::dropIfExists('letters');
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('chart_of_accounts');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('users');

        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('department')->nullable();
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

        Schema::create('employees', function ($table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('full_name')->nullable();
            $table->string('department')->nullable();
            $table->string('status')->default('active');
            $table->boolean('is_dead_file')->default(false);
            $table->timestamps();
            $table->softDeletes();
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
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('bank_accounts', function ($table) {
            $table->id();
            $table->string('bank_name');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('letters', function ($table) {
            $table->id();
            $table->string('reference_number')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('system_settings', function ($table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('receipts', function ($table) {
            $table->id();
            $table->string('receipt_number')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('status')->default('approved');
            $table->string('purchasable_type')->nullable();
            $table->unsignedBigInteger('purchasable_id')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->string('vendor_name')->nullable();
            $table->string('vendor_tin')->nullable();
            $table->boolean('tin_valid')->default(true);
            $table->string('buyer_tin')->nullable();
            $table->string('fs_no')->nullable();
            $table->string('fs_no_raw')->nullable();
            $table->boolean('fs_no_valid')->default(true);
            $table->string('mrc_no')->nullable();
            $table->date('receipt_date')->nullable();
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('vat_amount', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->string('currency')->default('ETB');
            $table->string('category')->nullable();
            $table->text('description')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_type')->nullable();
            $table->timestamps();
        });

        Schema::create('receipt_items', function ($table) {
            $table->id();
            $table->unsignedBigInteger('receipt_id');
            $table->string('item_description')->nullable();
            $table->string('vat_category')->default('G');
            $table->string('calendar_type')->default('E');
            $table->integer('purchase_type')->default(3);
            $table->string('uom')->default('9');
            $table->decimal('qty', 14, 2)->default(1.0);
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->decimal('total_value', 18, 2)->default(0);
            $table->decimal('vat_amount', 18, 2)->default(0);
            $table->decimal('value_after_vat', 18, 2)->default(0);
            $table->boolean('is_flagged')->default(false);
            $table->timestamps();
        });

        Schema::create('expense_hub_vat_decisions', function ($table) {
            $table->id();
            $table->string('source_type');
            $table->unsignedBigInteger('source_id');
            $table->string('vat_status');
            $table->decimal('vat_rate', 5, 2)->default(15.00);
            $table->boolean('is_inclusive')->default(true);
            $table->decimal('base_amount', 18, 2)->default(0);
            $table->decimal('vat_amount', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->boolean('has_withholding')->default(false);
            $table->decimal('withholding_rate', 5, 2)->default(3.00);
            $table->decimal('withholding_amount', 18, 2)->default(0);
            $table->string('withholding_receipt')->nullable();
            $table->string('withholding_receipt_number')->nullable();
            $table->decimal('net_payable_amount', 18, 2)->default(0);
            $table->string('no_vat_reason_code')->nullable();
            $table->string('no_vat_reason_note')->nullable();
            $table->string('vat_category')->default('G');
            $table->string('calendar_type')->default('E');
            $table->integer('purchase_type')->default(3);
            $table->string('custom_seller_tin')->nullable();
            $table->string('custom_fs_no')->nullable();
            $table->string('custom_mrc_no')->nullable();
            $table->string('custom_item_description')->nullable();
            $table->string('uom')->default('9');
            $table->decimal('quantity', 14, 2)->default(1.0);
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->boolean('is_locked')->default(false);
            $table->timestamp('locked_at')->nullable();
            $table->unsignedBigInteger('locked_by')->nullable();
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });

        Schema::create('expense_hub_vat_audit_logs', function ($table) {
            $table->id();
            $table->unsignedBigInteger('decision_id');
            $table->string('source_type');
            $table->unsignedBigInteger('source_id');
            $table->string('action');
            $table->text('old_values')->nullable();
            $table->text('new_values')->nullable();
            $table->unsignedBigInteger('performed_by');
            $table->timestamps();
        });

        Schema::create('expense_requests', function ($table) {
            $table->id();
            $table->string('request_number')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('purchase_request_id')->nullable();
            $table->string('category')->default('Service');
            $table->string('other_reason')->nullable();
            $table->text('description')->nullable();
            $table->decimal('amount', 18, 2)->default(0);
            $table->decimal('gross_amount', 18, 2)->nullable();
            $table->string('vat_type')->nullable()->default('none');
            $table->decimal('vat_rate', 5, 2)->default(15.00);
            $table->decimal('vat_amount', 18, 2)->default(0);
            $table->boolean('has_withholding')->default(false);
            $table->decimal('withholding_rate', 5, 2)->default(3.00);
            $table->decimal('withholding_amount', 18, 2)->default(0);
            $table->decimal('net_amount', 18, 2)->nullable();
            $table->string('status')->default(ExpenseRequest::STATUS_PAID);
            $table->timestamp('paid_at')->nullable();
            $table->string('attachment')->nullable();
            $table->string('payment_reference')->nullable();
            $table->string('withholding_receipt')->nullable();
            $table->string('withholding_receipt_number')->nullable();
            $table->unsignedBigInteger('tax_settlement_id')->nullable();
            $table->unsignedBigInteger('vat_settlement_id')->nullable();
            $table->unsignedBigInteger('withholding_settlement_id')->nullable();
            $table->boolean('vat_settled')->default(false);
            $table->boolean('withholding_settled')->default(false);
            $table->timestamp('tax_settled_at')->nullable();
            $table->timestamp('vat_settled_at')->nullable();
            $table->timestamp('withholding_settled_at')->nullable();
            $table->unsignedBigInteger('paid_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('purchase_requests', function ($table) {
            $table->id();
            $table->string('pr_no')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->string('vendor_name')->nullable();
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
            $table->boolean('has_withholding')->default(false);
            $table->decimal('withholding_rate', 5, 2)->default(3.00);
            $table->decimal('withholding_amount', 18, 2)->default(0);
            $table->string('withholding_receipt')->nullable();
            $table->string('withholding_receipt_number')->nullable();
            $table->decimal('net_amount', 18, 2)->nullable();
            $table->string('status')->default('paid');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('income_records', function ($table) {
            $table->id();
            $table->date('income_date')->nullable();
            $table->date('receipt_date')->nullable();
            $table->decimal('amount', 18, 2)->default(0);
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->decimal('quantity', 14, 2)->default(1.0);
            $table->boolean('vat_settled')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tax_settlements', function ($table) {
            $table->id();
            $table->string('settlement_number', 50)->unique();
            $table->string('tax_type', 30)->default('both');
            $table->timestamp('period_from')->nullable();
            $table->timestamp('period_to')->nullable();
            $table->decimal('total_base_amount', 14, 2)->default(0);
            $table->decimal('vat_amount', 14, 2)->default(0);
            $table->decimal('withholding_amount', 14, 2)->default(0);
            $table->decimal('total_tax_paid', 14, 2)->default(0);
            $table->integer('records_count')->default(0);
            $table->string('status', 30)->default('paid');
            $table->integer('ethiopian_year')->nullable();
            $table->integer('ethiopian_month')->nullable();
            $table->timestamps();
        });
    }

    public function test_verifying_vat_in_expense_hub_immediately_appears_in_tax_deductions_ledger()
    {
        // 1. Create a paid ExpenseRequest without VAT initially
        $exp = ExpenseRequest::create([
            'request_number' => 'REQ-EXP-999',
            'user_id'        => $this->financeUser->id,
            'project_id'     => $this->project->id,
            'category'       => 'Service',
            'description'    => 'Electrical maintenance service',
            'amount'         => 11500.00,
            'gross_amount'   => 11500.00,
            'vat_type'       => 'none',
            'vat_amount'     => 0.00,
            'status'         => ExpenseRequest::STATUS_PAID,
            'paid_at'        => now(),
        ]);

        // 2. Perform VAT Verification in Expense Hub (POST saveDecision with VAT Applies)
        $hubController = new ExpenseHubController(app(ExpenseHubQueryService::class));
        $decisionRequest = Request::create(route('finance.expense-hub.decision'), 'POST', [
            'source_type'             => 'expense_request',
            'source_id'               => $exp->id,
            'vat_status'              => 'vat_applies',
            'amount'                  => 11500.00,
            'vat_rate'                => 15.00,
            'is_inclusive'            => true,
            'custom_seller_tin'       => '0012345678',
            'custom_fs_no'            => '00088991',
            'custom_mrc_no'           => 'MRC-7788',
            'custom_item_description' => 'Electrical maintenance service with VAT',
            'custom_receipt_date'     => now()->toDateString(),
            'purchase_type'           => 3,
        ]);
        app()->instance('request', $decisionRequest);

        $response = $hubController->saveDecision($decisionRequest);
        $this->assertEquals(302, $response->getStatusCode());

        // 3. Verify that Receipt & ReceiptItem were created and ExpenseRequest was updated
        $exp->refresh();
        $this->assertEquals('inclusive', $exp->vat_type);
        $this->assertEquals(1500.00, (float)$exp->vat_amount);

        $receipt = Receipt::where('purchasable_type', ExpenseRequest::class)
            ->where('purchasable_id', $exp->id)
            ->first();
        $this->assertNotNull($receipt, 'Receipt record must be automatically created when VAT is verified.');
        $this->assertEquals('00088991', $receipt->fs_no);
        $this->assertEquals('0012345678', $receipt->vendor_tin);
        $this->assertEquals(1500.00, (float)$receipt->vat_amount);
        $this->assertEquals(10000.00, (float)$receipt->subtotal);

        $receiptItem = ReceiptItem::where('receipt_id', $receipt->id)->first();
        $this->assertNotNull($receiptItem, 'ReceiptItem must be created for the ERCA Line 100 declaration.');
        $this->assertEquals(1500.00, (float)$receiptItem->vat_amount);
        $this->assertEquals(10000.00, (float)$receiptItem->total_value);

        // 4. Now open "VAT & Withholding Tax Deductions Ledger" (FinanceTaxReportController@index)
        $taxController = new FinanceTaxReportController();
        $ledgerRequest = Request::create(route('finance.tax-deductions.index'), 'GET', [
            'tab' => 'vat',
        ]);
        app()->instance('request', $ledgerRequest);

        $taxView = $taxController->index($ledgerRequest);
        $data = $taxView->getData();

        // 5. Assert that the verified record appears in the ERCA Line 100 VAT table
        $ocrItems = $data['ocrItems'];
        $fsNumbers = collect($ocrItems->items())->map(fn($item) => $item->receipt?->fs_no)->toArray();
        $this->assertContains('00088991', $fsNumbers, 'The verified FS No must appear in the ERCA Line 100 table in the Tax Deductions Ledger.');

        // 6. Assert that Input VAT KPI card includes the verified VAT amount
        $inputVat = $data['inputVat'];
        $this->assertGreaterThanOrEqual(1500.00, $inputVat, 'Creditable Input VAT must include the 1500 ETB verified VAT.');
    }

    public function test_recording_withholding_tax_in_expense_hub_syncs_and_appears_in_deductions_ledger()
    {
        // 1. Create a paid ExpenseRequest (Subtotal 10,000 ETB)
        $exp = ExpenseRequest::create([
            'request_number' => 'REQ-EXP-WHT-001',
            'user_id'        => $this->financeUser->id,
            'project_id'     => $this->project->id,
            'category'       => 'Contractor Service',
            'description'    => 'Structural consultancy fees',
            'amount'         => 11500.00,
            'gross_amount'   => 11500.00,
            'vat_type'       => 'inclusive',
            'vat_amount'     => 1500.00,
            'status'         => ExpenseRequest::STATUS_PAID,
            'paid_at'        => now(),
        ]);

        // 2. Prepare fake Withholding Receipt PDF file
        $fakeFile = UploadedFile::fake()->create('wht_slip_official.pdf', 80, 'application/pdf');

        // 3. Post VAT & Withholding Decision in Expense Hub
        $hubController = new ExpenseHubController(app(ExpenseHubQueryService::class));
        $decisionRequest = Request::create(
            route('finance.expense-hub.decision'),
            'POST',
            [
                'source_type'                => 'expense_request',
                'source_id'                  => $exp->id,
                'vat_status'                 => 'vat_applies',
                'amount'                     => 11500.00,
                'vat_rate'                   => 15.00,
                'is_inclusive'               => true,
                'has_withholding'            => '1',
                'withholding_rate'           => 3.00,
                'withholding_amount'         => 300.00, // 3% of 10,000 base
                'withholding_receipt_number' => 'WHT-ERCA-9922',
                'custom_seller_tin'          => '0098765432',
                'custom_fs_no'               => '00099112',
                'custom_item_description'    => 'Structural consultancy fees with 3% WHT',
            ],
            [],
            [
                'withholding_receipt_file'   => $fakeFile,
            ]
        );
        app()->instance('request', $decisionRequest);

        $response = $hubController->saveDecision($decisionRequest);
        $this->assertEquals(302, $response->getStatusCode());

        // 4. Verify ExpenseHubVatDecision record
        $decision = ExpenseHubVatDecision::where('source_type', 'expense_request')
            ->where('source_id', $exp->id)
            ->first();
        $this->assertNotNull($decision);
        $this->assertTrue((bool)$decision->has_withholding);
        $this->assertEquals(3.00, (float)$decision->withholding_rate);
        $this->assertEquals(300.00, (float)$decision->withholding_amount);
        $this->assertEquals('WHT-ERCA-9922', $decision->withholding_receipt_number);
        $this->assertNotEmpty($decision->withholding_receipt);
        $this->assertEquals(11200.00, (float)$decision->net_payable_amount); // 11500 total - 300 withholding

        // 5. Verify source ExpenseRequest model synchronized
        $exp->refresh();
        $this->assertTrue((bool)$exp->has_withholding);
        $this->assertEquals(3.00, (float)$exp->withholding_rate);
        $this->assertEquals(300.00, (float)$exp->withholding_amount);
        $this->assertEquals('WHT-ERCA-9922', $exp->withholding_receipt_number);
        $this->assertEquals($decision->withholding_receipt, $exp->withholding_receipt);
        $this->assertEquals(11200.00, (float)$exp->net_amount);

        // 6. Access "VAT & Withholding Tax Deductions Ledger" -> tab=withholding
        $taxController = new FinanceTaxReportController();
        $whtLedgerReq = Request::create(route('finance.tax-deductions.index'), 'GET', [
            'tab'   => 'withholding',
            'cycle' => 'active',
        ]);
        app()->instance('request', $whtLedgerReq);

        $whtView = $taxController->index($whtLedgerReq);
        $whtData = $whtView->getData();

        $whtExpenses = collect($whtData['records']->items());
        $matching = $whtExpenses->firstWhere('id', $exp->id);
        $this->assertNotNull($matching, 'The expense with 3% withholding must appear in the Withholding tab of the tax deductions ledger.');
        $this->assertEquals(300.00, (float)$matching->withholding_amount);
        $this->assertEquals('WHT-ERCA-9922', $matching->withholding_receipt_number);

        // 7. Access "VAT & Withholding Tax Deductions Ledger" -> tab=slips
        $slipsLedgerReq = Request::create(route('finance.tax-deductions.index'), 'GET', [
            'tab'   => 'slips',
            'cycle' => 'active',
        ]);
        app()->instance('request', $slipsLedgerReq);

        $slipsView = $taxController->index($slipsLedgerReq);
        $slipsData = $slipsView->getData();

        $slipExpenses = collect($slipsData['records']->items());
        $slipMatch = $slipExpenses->firstWhere('id', $exp->id);
        $this->assertNotNull($slipMatch, 'The expense with uploaded withholding receipt must appear in the Withholding Slips tab.');
        $this->assertNotEmpty($slipMatch->withholding_receipt);
    }
}
