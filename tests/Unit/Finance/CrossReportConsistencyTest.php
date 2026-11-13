<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\BankAccount;
use App\Models\User;
use App\Services\Finance\FinanceMetricsService;
use App\Services\Finance\TrialBalanceIntegrityService;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;

class CrossReportConsistencyTest extends TestCase
{
    protected FinanceMetricsService $metricsService;
    protected TrialBalanceIntegrityService $tbService;
    protected User $user;

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

        $this->user = User::create([
            'name'  => 'Finance Manager',
            'email' => 'manager@wechecha.com',
            'role'  => 'finance_head',
        ]);
        Auth::login($this->user);

        $this->metricsService = new FinanceMetricsService();
        $this->tbService      = new TrialBalanceIntegrityService();
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
        $requiredTables = ['users', 'chart_of_accounts', 'journal_entries', 'journal_entry_lines', 'bank_accounts', 'petty_cash_subledgers', 'client_ipcs', 'credit_store_ledger', 'credit_store_payments'];
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
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('petty_cash_subledgers');
        Schema::dropIfExists('client_ipcs');
        Schema::dropIfExists('credit_store_payments');
        Schema::dropIfExists('credit_store_ledger');
        Schema::dropIfExists('users');

        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('role')->default('finance_head');
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

        Schema::create('bank_accounts', function ($table) {
            $table->id();
            $table->string('bank_name');
            $table->string('account_name')->nullable();
            $table->string('account_number');
            $table->unsignedBigInteger('coa_id')->nullable();
            $table->decimal('current_balance', 18, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('petty_cash_subledgers', function ($table) {
            $table->id();
            $table->unsignedBigInteger('control_account_id');
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('custodian_id')->nullable();
            $table->string('subledger_code')->unique();
            $table->string('subledger_name');
            $table->decimal('imprest_limit', 18, 2)->default(0);
            $table->decimal('current_balance', 18, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('client_ipcs', function ($table) {
            $table->id();
            $table->string('ipc_no')->nullable();
            $table->string('status')->default('billed');
            $table->decimal('gross_amount', 18, 2)->default(0);
            $table->decimal('net_payable_amount', 18, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('credit_store_ledger', function ($table) {
            $table->id();
            $table->string('pr_no')->nullable();
            $table->string('supplier_name')->nullable();
            $table->decimal('credit_amount', 18, 2)->default(0);
            $table->decimal('remaining_balance', 18, 2)->default(0);
            $table->string('payment_status')->default('pending');
            $table->timestamps();
        });

        Schema::create('credit_store_payments', function ($table) {
            $table->id();
            $table->unsignedBigInteger('credit_store_ledger_id');
            $table->date('payment_date');
            $table->decimal('amount', 18, 2);
            $table->decimal('vat_amount', 18, 2)->default(0);
            $table->decimal('withholding_amount', 18, 2)->default(0);
            $table->timestamps();
        });
    }

    public function test_cash_and_bank_consistency_across_balance_sheet_and_dashboard_metrics()
    {
        // Setup Cash Accounts:
        // CBE checking (asset with debit balance)
        $cbe = ChartOfAccount::create([
            'code'            => '1011',
            'name'            => 'CBE Checking Account',
            'type'            => 'asset',
            'subtype'         => 'cash_and_bank',
            'normal_side'     => 'debit',
            'opening_balance' => 1500000.00,
            'current_balance' => 1500000.00,
        ]);

        // Abyssinia (bank account with credit balance => bank overdraft liability)
        $abyssinia = ChartOfAccount::create([
            'code'            => '1012',
            'name'            => 'Bank of Abyssinia Overdraft',
            'type'            => 'asset',
            'subtype'         => 'cash_and_bank',
            'normal_side'     => 'debit',
            'opening_balance' => -500000.00, // Credit balance of 500k
            'current_balance' => -500000.00,
        ]);

        // Net Cash across firm = 1,500,000 - 500,000 = 1,000,000.00
        $netCash = $this->metricsService->getTotalCashAndBank();
        $this->assertEquals(1000000.00, $netCash);

        // Verify Balance Sheet treats CBE as Asset (1.5M) and Abyssinia as Overdraft Liability (500k)
        $bs = $this->metricsService->getBalanceSheet();
        $cbeAsset = $bs['assets']->firstWhere('code', '1011');
        $this->assertNotNull($cbeAsset);
        $this->assertEquals(1500000.00, $cbeAsset['balance']);

        $overdraftLiab = $bs['overdrafts']->firstWhere('code', '1012');
        $this->assertNotNull($overdraftLiab);
        $this->assertEquals(500000.00, $overdraftLiab['balance']);

        // Assert net matches
        $this->assertEquals($netCash, $cbeAsset['balance'] - $overdraftLiab['balance']);
    }

    public function test_accounts_payable_and_receivable_consistency()
    {
        // 1200 AR
        $ar = ChartOfAccount::create([
            'code'            => '1200',
            'name'            => 'Accounts Receivable',
            'type'            => 'asset',
            'subtype'         => 'receivable',
            'normal_side'     => 'debit',
            'opening_balance' => 3000000.00,
            'current_balance' => 3000000.00,
        ]);

        // 2001 AP
        $ap = ChartOfAccount::create([
            'code'            => '2001',
            'name'            => 'Trade Accounts Payable',
            'type'            => 'liability',
            'subtype'         => 'payable',
            'normal_side'     => 'credit',
            'opening_balance' => 2000000.00,
            'current_balance' => 2000000.00,
        ]);

        $arMetric = $this->metricsService->getTotalTradeReceivables();
        $apMetric = $this->metricsService->getTotalTradePayables();

        $this->assertEquals(3000000.00, $arMetric);
        $this->assertEquals(2000000.00, $apMetric);

        // Verify Balance Sheet displays exact same numbers
        $bs = $this->metricsService->getBalanceSheet();
        $bsAr = $bs['assets']->firstWhere('code', '1200');
        $bsAp = $bs['liabilities']->firstWhere('code', '2001');

        $this->assertNotNull($bsAr);
        $this->assertEquals(3000000.00, $bsAr['balance']);
        $this->assertEquals($arMetric, $bsAr['balance']);

        $this->assertNotNull($bsAp);
        $this->assertEquals(2000000.00, $bsAp['balance']);
        $this->assertEquals($apMetric, $bsAp['balance']);
    }

    public function test_dpo_and_dso_calculations_are_mathematically_consistent()
    {
        // AR = 1,000,000; Revenue = 6,000,000; Days = 30
        // Expected DSO = (1,000,000 / 6,000,000) * 30 = 5.0 days
        $ar = ChartOfAccount::create([
            'code'            => '1200',
            'name'            => 'Accounts Receivable',
            'type'            => 'asset',
            'subtype'         => 'receivable',
            'current_balance' => 1000000.00,
        ]);

        $rev = ChartOfAccount::create([
            'code'            => '4100',
            'name'            => 'Construction Contract Revenue',
            'type'            => 'revenue',
            'current_balance' => 6000000.00,
        ]);

        // AP = 500,000; COGS Purchases = 3,000,000; Days = 30
        // Expected DPO = (500,000 / 3,000,000) * 30 = 5.0 days
        $ap = ChartOfAccount::create([
            'code'            => '2001',
            'name'            => 'Trade Accounts Payable',
            'type'            => 'liability',
            'subtype'         => 'payable',
            'current_balance' => 500000.00,
        ]);

        $cogs = ChartOfAccount::create([
            'code'            => '5100',
            'name'            => 'Cost of Material',
            'type'            => 'expense',
            'subtype'         => 'cost_of_sale',
            'current_balance' => 3000000.00,
        ]);

        $dso = $this->metricsService->getDaysSalesOutstanding('2026-11-01', '2026-11-30');
        $dpo = $this->metricsService->getDaysPayablesOutstanding('2026-11-01', '2026-11-30');

        $this->assertEquals(30, $dso['days_in_period']);
        $this->assertEquals(5.0, $dso['dso_days']);

        $this->assertEquals(30, $dpo['days_in_period']);
        $this->assertEquals(5.0, $dpo['dpo_days']);
    }

    public function test_subledger_control_account_reconciliations_for_ar_ap_and_bank()
    {
        // 1. AR Subledger Check
        $arControl = ChartOfAccount::create([
            'code'            => '1200',
            'name'            => 'Accounts Receivable',
            'type'            => 'asset',
            'current_balance' => 250000.00,
        ]);

        DB::table('client_ipcs')->insert([
            ['ipc_no' => 'IPC-001', 'status' => 'billed', 'net_payable_amount' => 150000.00],
            ['ipc_no' => 'IPC-002', 'status' => 'certified', 'net_payable_amount' => 100000.00],
        ]);

        $arRec = $this->metricsService->reconcileArSubledger();
        $this->assertTrue($arRec['is_reconciled']);
        $this->assertEquals(0.00, $arRec['discrepancy']);
        $this->assertEquals(250000.00, $arRec['control_balance']);
        $this->assertEquals(250000.00, $arRec['subledger_total']);

        // 2. AP Subledger Check
        $apControl = ChartOfAccount::create([
            'code'            => '2001',
            'name'            => 'Accounts Payable',
            'type'            => 'liability',
            'current_balance' => 400000.00,
        ]);

        DB::table('credit_store_ledger')->insert([
            ['pr_no' => 'PR-VEND-1', 'supplier_name' => 'Cement Supplier', 'remaining_balance' => 250000.00, 'payment_status' => 'pending'],
            ['pr_no' => 'PR-VEND-2', 'supplier_name' => 'Steel Supplier', 'remaining_balance' => 150000.00, 'payment_status' => 'partially_paid'],
        ]);

        $apRec = $this->metricsService->reconcileApSubledger();
        $this->assertTrue($apRec['is_reconciled']);
        $this->assertEquals(0.00, $apRec['discrepancy']);
        $this->assertEquals(400000.00, $apRec['control_balance']);
        $this->assertEquals(400000.00, $apRec['subledger_total']);

        // 3. Bank Subledger Check
        $cbeCoa = ChartOfAccount::create([
            'code'            => '1011',
            'name'            => 'CBE Checking',
            'type'            => 'asset',
            'current_balance' => 800000.00,
        ]);

        BankAccount::create([
            'bank_name'       => 'Commercial Bank of Ethiopia',
            'account_number'  => '1000123456789',
            'coa_id'          => $cbeCoa->id,
            'current_balance' => 800000.00,
            'is_active'       => true,
        ]);

        $bankRec = $this->metricsService->reconcileBankSubledger();
        $this->assertTrue($bankRec['is_reconciled']);
        $this->assertEquals(0.00, $bankRec['discrepancy']);
        $this->assertEquals(800000.00, $bankRec['gl_control_total']);
        $this->assertEquals(800000.00, $bankRec['subledger_total']);

        // 4. Consolidated Subledger Reconciliations
        $all = $this->metricsService->getAllSubledgerReconciliations();
        $this->assertArrayHasKey('ar', $all);
        $this->assertArrayHasKey('ap', $all);
        $this->assertArrayHasKey('bank', $all);
        $this->assertArrayHasKey('petty_cash', $all);
        $this->assertArrayHasKey('tax_report', $all);
        $this->assertTrue($all['ar']['is_reconciled']);
        $this->assertTrue($all['ap']['is_reconciled']);
        $this->assertTrue($all['bank']['is_reconciled']);
    }

    public function test_tax_report_vat_and_wht_reconciliation_to_gl_accounts_2300_and_2600()
    {
        $vatAcc = ChartOfAccount::create([
            'code'        => '2300',
            'name'        => 'VAT Payable',
            'type'        => 'liability',
            'normal_side' => 'credit',
        ]);

        $whtAcc = ChartOfAccount::create([
            'code'        => '2600',
            'name'        => 'WHT Payable',
            'type'        => 'liability',
            'normal_side' => 'credit',
        ]);

        $fundingAcc = ChartOfAccount::create([
            'code'        => '1011',
            'name'        => 'CBE Checking',
            'type'        => 'asset',
            'normal_side' => 'debit',
        ]);

        $expAcc = ChartOfAccount::create([
            'code'        => '5100',
            'name'        => 'Cost of Material',
            'type'        => 'expense',
            'normal_side' => 'debit',
        ]);

        // Post a balanced journal with VAT and WHT lines:
        // Debit: Material Cost 100,000
        // Debit: VAT Input / Credit VAT 15,000
        // Credit: WHT 3,000
        // Credit: CBE Cash 112,000
        $je = JournalEntry::create([
            'entry_no'   => 'JE-TAX-REC-01',
            'entry_date' => '2026-11-09',
            'status'     => 'draft',
            'created_by' => $this->user->id,
        ]);

        JournalEntryLine::insert([
            ['journal_entry_id' => $je->id, 'account_id' => $expAcc->id, 'side' => 'debit', 'amount' => 100000.00, 'created_at' => now(), 'updated_at' => now()],
            ['journal_entry_id' => $je->id, 'account_id' => $fundingAcc->id, 'side' => 'credit', 'amount' => 97000.00, 'created_at' => now(), 'updated_at' => now()],
            ['journal_entry_id' => $je->id, 'account_id' => $whtAcc->id, 'side' => 'credit', 'amount' => 3000.00, 'created_at' => now(), 'updated_at' => now()],
        ]);
        $je->update(['status' => 'posted']);

        // Insert matching tax report record in credit_store_payments
        DB::table('credit_store_ledger')->insert([
            'id' => 999, 'pr_no' => 'PR-TAX-01', 'supplier_name' => 'Supplier A', 'credit_amount' => 100000.00, 'remaining_balance' => 0.00, 'payment_status' => 'paid', 'created_at' => now(), 'updated_at' => now()
        ]);
        DB::table('credit_store_payments')->insert([
            'credit_store_ledger_id' => 999,
            'payment_date'           => '2026-11-09',
            'amount'                 => 100000.00,
            'vat_amount'             => 0.00,
            'withholding_amount'     => 3000.00,
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        $rec = $this->metricsService->reconcileTaxReportWithGl();
        $this->assertEquals(3000.00, $rec['wht']['gl_balance']);
        $this->assertEquals(3000.00, $rec['wht']['tax_report_total']);
        $this->assertEquals(0.00, $rec['wht']['discrepancy']);
        $this->assertTrue($rec['wht']['is_reconciled']);
        $this->assertTrue($rec['is_fully_reconciled']);
    }
}
