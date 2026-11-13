<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\User;
use App\Models\CreditStoreLedger;
use App\Models\CreditStorePayment;
use App\Models\PurchaseRequest;
use App\Models\ExpenseRequest;
use App\Http\Controllers\Finance\CreditStoreController;
use App\Http\Controllers\ExpenseRequestController;
use App\Services\ProcurementLifecycleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;
use ReflectionClass;
use Exception;

class UpstreamPostingBugFixesTest extends TestCase
{
    protected User $user;
    protected ChartOfAccount $cbeAccount;
    protected ChartOfAccount $pettyCashAccount;
    protected ChartOfAccount $materialExpenseAccount;
    protected ChartOfAccount $whtPayableAccount;
    protected ChartOfAccount $whtReceivableAccount;

        protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF;');
        } else {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            DB::statement('SET @gl_posting_in_progress = 1;');
            DB::statement('SET @period_lock_override = 0;');
        }

        $this->ensureSchema();
        DB::beginTransaction();

        // Seed core test user
        $this->user = User::create([
            'name'  => 'Finance Officer',
            'email' => 'finance@wechecha.com',
            'role'  => 'finance_officer',
        ]);
        Auth::login($this->user);

        // Seed Chart of Accounts
        $this->cbeAccount = ChartOfAccount::create([
            'code'            => '1011',
            'name'            => 'CBE Checking',
            'type'            => 'asset',
            'subtype'         => 'cash_and_bank',
            'normal_side'     => 'debit',
            'current_balance' => 500000.00,
        ]);

        $this->pettyCashAccount = ChartOfAccount::create([
            'code'            => '1010',
            'name'            => 'Petty Cash Float',
            'type'            => 'asset',
            'subtype'         => 'cash_and_bank',
            'normal_side'     => 'debit',
            'current_balance' => 50000.00,
        ]);

        $this->materialExpenseAccount = ChartOfAccount::create([
            'code'            => '5100',
            'name'            => 'Cost of Material',
            'type'            => 'expense',
            'normal_side'     => 'debit',
            'current_balance' => 0.00,
        ]);

        $this->whtPayableAccount = ChartOfAccount::create([
            'code'            => '2600',
            'name'            => 'WHT Payable',
            'type'            => 'liability',
            'subtype'         => 'other_current_liability',
            'normal_side'     => 'credit',
            'current_balance' => 0.00,
        ]);

        $this->whtReceivableAccount = ChartOfAccount::create([
            'code'            => '1300',
            'name'            => 'Withholding Tax Receivable',
            'type'            => 'asset',
            'subtype'         => 'current_asset',
            'normal_side'     => 'debit',
            'current_balance' => 0.00,
        ]);
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    protected function ensureSchema(): void
    {
        if (Schema::hasTable('journal_entries')) {
            return;
        }
        $requiredTables = ['users', 'chart_of_accounts', 'journal_entries', 'journal_entry_lines', 'credit_store_ledgers', 'credit_store_payments', 'purchase_requests', 'expense_requests', 'procurement_payments', 'expenses', 'petty_cash_replenishments'];
        $allExist = true;
        foreach ($requiredTables as $t) {
            if (!Schema::hasTable($t)) {
                $allExist = false;
                break;
            }
        }
        if ($allExist) {
            return;
        }

        if (DB::getDriverName() === 'sqlite') { DB::statement('PRAGMA foreign_keys = OFF;'); } else { DB::statement('SET FOREIGN_KEY_CHECKS=0;'); }

        Schema::dropIfExists('financial_audit_trails');
        Schema::dropIfExists('financial_periods');
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('chart_of_accounts');
        Schema::dropIfExists('procurement_payments');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('credit_store_payments');
        Schema::dropIfExists('credit_store_ledgers');
        Schema::dropIfExists('purchase_requests');
        Schema::dropIfExists('expense_requests');
        Schema::dropIfExists('petty_cash_replenishments');
        Schema::dropIfExists('users');

        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('role')->default('finance_officer');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('chart_of_accounts', function ($table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('type');
            $table->string('subtype')->nullable();
            $table->string('normal_side')->default('debit');
            $table->decimal('opening_balance', 18, 2)->default(0);
            $table->decimal('current_balance', 18, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_seed')->default(false);
            $table->boolean('is_control_account')->default(false);
            $table->string('subledger_type')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('journal_entries', function ($table) {
            $table->id();
            $table->string('entry_no')->unique();
            $table->date('entry_date');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'posted', 'reversed'])->default('draft');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->boolean('is_locked')->default(false);
            $table->timestamp('locked_at')->nullable();
            $table->unsignedBigInteger('locked_by')->nullable();
            $table->timestamp('reopened_at')->nullable();
            $table->unsignedBigInteger('reopened_by')->nullable();
            $table->text('reopen_reason')->nullable();
            $table->boolean('is_reversal')->default(false);
            $table->boolean('is_seed')->default(false);
            $table->unsignedBigInteger('reversal_of_id')->nullable();
            $table->unsignedBigInteger('reversed_by_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('journal_entry_lines', function ($table) {
            $table->id();
            $table->unsignedBigInteger('journal_entry_id');
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('original_account_id')->nullable();
            $table->text('description')->nullable();
            $table->enum('side', ['debit', 'credit']);
            $table->decimal('amount', 18, 2);
            $table->timestamps();
        });

        Schema::create('credit_store_ledgers', function ($table) {
            $table->id();
            $table->unsignedBigInteger('purchase_request_id')->nullable();
            $table->string('pr_no')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->string('supplier_name')->nullable();
            $table->string('vendor_name')->nullable();
            $table->string('item_description')->nullable();
            $table->decimal('credit_amount', 18, 2)->default(0);
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->unsignedBigInteger('coa_account_id')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('authorized_by')->nullable();
            $table->timestamp('authorized_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('credit_store_payments', function ($table) {
            $table->id();
            $table->unsignedBigInteger('credit_store_ledger_id');
            $table->date('payment_date');
            $table->decimal('amount', 18, 2);
            $table->string('payment_method')->default('bank_transfer');
            $table->unsignedBigInteger('bank_account_id')->nullable();
            $table->unsignedBigInteger('coa_account_id')->nullable();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->string('reference_no')->nullable();
            $table->string('receipt_path')->nullable();
            $table->string('original_filename')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->decimal('gross_amount', 18, 2)->nullable();
            $table->string('vat_type')->nullable();
            $table->decimal('vat_rate', 5, 2)->nullable();
            $table->decimal('vat_amount', 18, 2)->nullable();
            $table->boolean('has_withholding')->default(false);
            $table->decimal('withholding_rate', 5, 2)->nullable();
            $table->decimal('withholding_amount', 18, 2)->nullable();
            $table->string('withholding_receipt')->nullable();
            $table->string('withholding_receipt_number')->nullable();
            $table->decimal('net_amount', 18, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_requests', function ($table) {
            $table->id();
            $table->string('pr_no')->unique();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('requester_id')->nullable();
            $table->string('status')->default('approved');
            $table->decimal('estimated_cost', 18, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('expense_requests', function ($table) {
            $table->id();
            $table->string('request_number')->unique();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('category')->nullable();
            $table->decimal('amount', 18, 2)->default(0);
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('coa_id')->nullable();
            $table->unsignedBigInteger('petty_cash_account_id')->nullable();
            $table->boolean('coa_deducted')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('procurement_payments', function ($table) {
            $table->id();
            $table->unsignedBigInteger('purchase_request_id');
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->timestamps();
        });

        Schema::create('expenses', function ($table) {
            $table->id();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->string('category')->nullable();
            $table->text('description')->nullable();
            $table->decimal('amount', 18, 2)->default(0);
            $table->dateTime('expense_date')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('petty_cash_replenishments', function ($table) {
            $table->id();
            $table->string('request_no')->nullable();
            $table->unsignedBigInteger('chart_of_account_id')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->decimal('requested_amount', 18, 2)->default(0);
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('source_coa_id')->nullable();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->decimal('fulfilled_amount', 18, 2)->nullable();
            $table->string('fulfillment_reference')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Test 1: CreditStoreController WHT on supplier payments must credit 2600 WHT Payable, not 1300.
     */
    public function test_credit_store_supplier_payment_withholding_credits_2600_wht_payable_not_1300()
    {
        $ledger = CreditStoreLedger::create([
            'pr_no'          => 'PR-2026-TEST-001',
            'supplier_name'  => 'National Cement SC',
            'credit_amount'  => 10000.00,
            'paid_amount'    => 0.00,
            'coa_account_id' => $this->materialExpenseAccount->id,
            'status'         => 'authorized',
            'created_by'     => $this->user->id,
        ]);

        $request = new Request([
            'payment_date'     => '2026-11-09',
            'amount'           => 10000.00,
            'payment_method'   => 'bank_transfer',
            'coa_account_id'   => $this->cbeAccount->id,
            'has_withholding'  => true,
            'withholding_rate' => 3.00,
            'no_receipt'       => true,
            'notes'            => 'Test supplier settlement with 3% withholding',
        ]);

        $controller = new CreditStoreController();
        $controller->recordPayment($request, $ledger);

        // Find the generated journal entry
        $payment = CreditStorePayment::where('credit_store_ledger_id', $ledger->id)->first();
        $this->assertNotNull($payment);
        $this->assertNotNull($payment->journal_entry_id);

        $journal = JournalEntry::find($payment->journal_entry_id);
        $this->assertNotNull($journal);
        $lines = JournalEntryLine::where('journal_entry_id', $journal->id)->get();

        $this->assertCount(3, $lines, "There must be 3 lines: Debit Material Cost, Credit CBE Bank, Credit 2600 WHT Payable.");

        // Line 1: Debit Material Cost for full settled gross (10,000.00)
        $debitLine = $lines->firstWhere('side', 'debit');
        $this->assertEquals($this->materialExpenseAccount->id, $debitLine->account_id);
        $this->assertEquals(10000.00, (float)$debitLine->amount);

        // Line 2: Credit Funding Bank for net cash disbursed (9,700.00)
        $bankLine = $lines->where('side', 'credit')->firstWhere('account_id', $this->cbeAccount->id);
        $this->assertNotNull($bankLine);
        $this->assertEquals(9700.00, (float)$bankLine->amount);

        // Line 3: Credit 2600 WHT Payable for withheld tax (300.00)
        $whtLine = $lines->where('side', 'credit')->firstWhere('account_id', $this->whtPayableAccount->id);
        $this->assertNotNull($whtLine, "Withholding tax MUST credit account 2600 WHT Payable.");
        $this->assertEquals(300.00, (float)$whtLine->amount);

        // Ensure 1300 Withholding Tax Receivable was NOT credited
        $wrongLine = $lines->firstWhere('account_id', $this->whtReceivableAccount->id);
        $this->assertNull($wrongLine, "Account 1300 Withholding Tax Receivable must NOT be credited on supplier payments.");

        // Assert strictly balanced: total debits == total credits
        $totalDebit = $lines->where('side', 'debit')->sum('amount');
        $totalCredit = $lines->where('side', 'credit')->sum('amount');
        $this->assertEquals($totalDebit, $totalCredit);
        $this->assertEquals(10000.00, (float)$totalDebit);
    }

    /**
     * Test 2: ProcurementLifecycleService debits expense account and credits bank account without same-account wash entries.
     */
    public function test_procurement_lifecycle_service_posts_distinct_expense_debit_and_bank_credit_without_wash()
    {
        $pr = PurchaseRequest::create([
            'pr_no'          => 'PR-2026-LIFECYCLE-001',
            'estimated_cost' => 25000.00,
            'status'         => 'approved',
        ]);

        $mockSms = $this->createMock(\App\Services\ProcurementSmsService::class);
        $mockHandoff = $this->createMock(\App\Services\ProcurementHandoffNotificationService::class);
        $service = new ProcurementLifecycleService($mockSms, $mockHandoff);
        $reflector = new ReflectionClass($service);
        $method = $reflector->getMethod('createJournalEntry');
        $method->setAccessible(true);

        // Call with Expense Account ID (5100)
        $method->invokeArgs($service, [$pr, $this->materialExpenseAccount->id, 25000.00, 'bank_transfer', []]);

        $entry = JournalEntry::where('reference_type', 'procurement_payment')
            ->where('reference_id', $pr->id)
            ->first();

        $this->assertNotNull($entry);
        $lines = JournalEntryLine::where('journal_entry_id', $entry->id)->get();

        $this->assertCount(2, $lines);

        $debitLine = $lines->firstWhere('side', 'debit');
        $creditLine = $lines->firstWhere('side', 'credit');

        // Distinct accounts (no wash entries)
        $this->assertNotEquals($debitLine->account_id, $creditLine->account_id, "Debit and Credit accounts must be distinct; same-account wash entries are prohibited.");

        // Debit is expense (5100), Credit is cash/bank (1011)
        $this->assertEquals($this->materialExpenseAccount->id, $debitLine->account_id);
        $this->assertEquals($this->cbeAccount->id, $creditLine->account_id);

        $this->assertEquals(25000.00, (float)$debitLine->amount);
        $this->assertEquals(25000.00, (float)$creditLine->amount);
    }

    /**
     * Test 3: ExpenseRequestController throws error if replenishment source account is missing,
     * and produces a balanced 2-legged entry when valid.
     */
    public function test_expense_request_controller_throws_error_if_source_account_missing_and_creates_balanced_entry_when_valid()
    {
        $controller = new ExpenseRequestController();
        $reflector = new ReflectionClass($controller);
        $method = $reflector->getMethod('handlePettyCashReplenishmentFulfillment');
        $method->setAccessible(true);

        // Case A: Missing source account throws Exception (prevents 1-legged entries)
        $invalidRequest = ExpenseRequest::create([
            'request_number'         => 'ER-TEST-NO-SOURCE-01',
            'amount'                 => 15000.00,
            'status'                 => 'approved',
            'coa_id'                 => null, // Missing source funding account
            'petty_cash_account_id'  => $this->pettyCashAccount->id,
        ]);

        $exceptionCaught = false;
        try {
            $method->invokeArgs($controller, [$invalidRequest, 15000.00, 'REF-TX-001', $this->user]);
        } catch (\Throwable $e) {
            $exceptionCaught = true;
            $this->assertStringContainsString('A distinct funding source bank or cash account is required', $e->getMessage());
        }
        $this->assertTrue($exceptionCaught, "Controller must throw exception when funding source account is missing.");

        // Case B: Valid source account produces balanced 2-legged entry
        $validRequest = ExpenseRequest::create([
            'request_number'         => 'ER-TEST-VALID-02',
            'amount'                 => 18000.00,
            'status'                 => 'approved',
            'coa_id'                 => $this->cbeAccount->id, // Source bank account
            'petty_cash_account_id'  => $this->pettyCashAccount->id,
        ]);

        $method->invokeArgs($controller, [$validRequest, 18000.00, 'REF-TX-002', $this->user]);

        $entry = JournalEntry::where('reference_type', 'petty_cash_replenishment')
            ->where('reference_id', $validRequest->id)
            ->first();

        $this->assertNotNull($entry);
        $lines = JournalEntryLine::where('journal_entry_id', $entry->id)->get();

        $this->assertCount(2, $lines, "Must have exactly 2 lines: Debit Petty Cash, Credit Funding Bank.");

        $debitLine = $lines->firstWhere('side', 'debit');
        $creditLine = $lines->firstWhere('side', 'credit');

        $this->assertEquals($this->pettyCashAccount->id, $debitLine->account_id);
        $this->assertEquals($this->cbeAccount->id, $creditLine->account_id);
        $this->assertEquals(18000.00, (float)$debitLine->amount);
        $this->assertEquals(18000.00, (float)$creditLine->amount);
    }
}
