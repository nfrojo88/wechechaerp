<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\User;
use App\Services\Finance\FinanceMetricsService;
use App\Services\Finance\JournalEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BalanceSheetIntegrityTest extends TestCase
{
    protected FinanceMetricsService $metricsService;
    protected JournalEngine $journalEngine;
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
            'name'  => 'CFO Officer',
            'email' => 'cfo@wechecha.com',
            'role'  => 'cfo'
        ]);

        $this->metricsService = new FinanceMetricsService();
        $this->journalEngine  = new JournalEngine();
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
        $requiredTables = ['users', 'chart_of_accounts', 'journal_entries', 'journal_entry_lines', 'financial_periods', 'financial_audit_trails'];
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
        Schema::dropIfExists('users');

        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('role')->default('finance_manager');
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
            $table->boolean('is_control_account')->default(false);
            $table->string('subledger_type')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('journal_entries', function ($table) {
            $table->id();
            $table->string('entry_no', 50)->unique();
            $table->date('entry_date');
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft');
            $table->boolean('is_locked')->default(false);
            $table->timestamp('locked_at')->nullable();
            $table->unsignedBigInteger('locked_by')->nullable();
            $table->timestamp('reopened_at')->nullable();
            $table->unsignedBigInteger('reopened_by')->nullable();
            $table->text('reopen_reason')->nullable();
            $table->boolean('is_reversal')->default(false);
            $table->unsignedBigInteger('reversal_of_id')->nullable();
            $table->unsignedBigInteger('reversed_by_id')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('journal_entry_lines', function ($table) {
            $table->id();
            $table->unsignedBigInteger('journal_entry_id');
            $table->unsignedBigInteger('account_id');
            $table->string('side', 10);
            $table->decimal('amount', 18, 2);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('financial_periods', function ($table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 50)->unique();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('fiscal_year', 20)->default('2026');
            $table->string('status', 30)->default('open');
            $table->boolean('is_locked')->default(false);
            $table->timestamp('locked_at')->nullable();
            $table->unsignedBigInteger('locked_by')->nullable();
            $table->timestamp('reopened_at')->nullable();
            $table->unsignedBigInteger('reopened_by')->nullable();
            $table->text('reopen_reason')->nullable();
            $table->boolean('tb_balanced_at_close')->default(false);
            $table->decimal('tb_total_debit', 18, 2)->nullable();
            $table->decimal('tb_total_credit', 18, 2)->nullable();
            $table->decimal('tb_difference', 18, 2)->default(0);
            $table->text('closing_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('financial_audit_trails', function ($table) {
            $table->id();
            $table->string('auditable_type', 100);
            $table->unsignedBigInteger('auditable_id');
            $table->string('action', 50);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_role', 50)->nullable();
            $table->text('reason')->nullable();
            $table->json('payload')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    public function test_balance_sheet_pure_ledger_equation_assets_equal_liabilities_plus_equity()
    {
        // 1. Establish Accounts
        $bankAcc  = ChartOfAccount::create(['code' => '1011', 'name' => 'CBE Bank', 'type' => 'asset', 'normal_side' => 'debit']);
        $arAcc    = ChartOfAccount::create(['code' => '1200', 'name' => 'Accounts Receivable', 'type' => 'asset', 'normal_side' => 'debit']);
        $apAcc    = ChartOfAccount::create(['code' => '2001', 'name' => 'Accounts Payable', 'type' => 'liability', 'normal_side' => 'credit']);
        $capAcc   = ChartOfAccount::create(['code' => '3100', 'name' => 'Paid-Up Capital', 'type' => 'equity', 'normal_side' => 'credit']);
        $revAcc   = ChartOfAccount::create(['code' => '4200', 'name' => 'Equipment Leasing', 'type' => 'revenue', 'normal_side' => 'credit']);
        $expAcc   = ChartOfAccount::create(['code' => '5300', 'name' => 'Fuel Cost', 'type' => 'expense', 'normal_side' => 'debit']);

        // 2. Transaction 1: Capital deposit ETB 1,000,000
        $this->journalEngine->createEntry(
            'capital', null, 'Capital deposit',
            [
                ['account_id' => $bankAcc->id, 'side' => 'debit', 'amount' => 1000000.00],
                ['account_id' => $capAcc->id,  'side' => 'credit', 'amount' => 1000000.00],
            ],
            $this->user->id, $this->user->id, '2026-10-01'
        );

        // 3. Transaction 2: Revenue billed to client on credit ETB 350,000
        $this->journalEngine->createEntry(
            'revenue', null, 'Equipment lease billed',
            [
                ['account_id' => $arAcc->id,  'side' => 'debit', 'amount' => 350000.00],
                ['account_id' => $revAcc->id, 'side' => 'credit', 'amount' => 350000.00],
            ],
            $this->user->id, $this->user->id, '2026-10-10'
        );

        // 4. Transaction 3: Fuel purchased on credit ETB 100,000
        $this->journalEngine->createEntry(
            'fuel_credit', null, 'Diesel fuel invoice from TotalEnergies',
            [
                ['account_id' => $expAcc->id, 'side' => 'debit', 'amount' => 100000.00],
                ['account_id' => $apAcc->id,  'side' => 'credit', 'amount' => 100000.00],
            ],
            $this->user->id, $this->user->id, '2026-10-15'
        );

        // 5. Generate Balance Sheet as of 2026-10-31
        $bs = $this->metricsService->getBalanceSheet('2026-10-31');

        // Assets = Bank (1,000,000) + AR (350,000) = 1,350,000
        $this->assertEquals(1350000.00, $bs['total_assets']);

        // Liabilities = AP (100,000)
        $this->assertEquals(100000.00, $bs['total_liabilities']);

        // Operating Net Profit = Revenue (350,000) - Expense (100,000) = 250,000
        $this->assertEquals(250000.00, $bs['current_year_net_profit']);

        // Equity = Base Capital (1,000,000) + Net Profit (250,000) = 1,250,000
        $this->assertEquals(1250000.00, $bs['total_equity']);

        // Liabilities + Equity = 100,000 + 1,250,000 = 1,350,000 == Total Assets
        $this->assertEquals(1350000.00, $bs['total_liabilities_and_equity']);
        $this->assertTrue($bs['is_balanced'], "Balance Sheet must perfectly balance Assets == Liabilities + Equity.");
        $this->assertEquals(0.00, $bs['imbalance'], "Zero plug account allowed; imbalance must be exactly 0.00.");
    }

    public function test_balance_sheet_presents_bank_overdraft_as_liability_and_maintains_accounting_equation()
    {
        // 1. Establish Accounts
        $cbeAcc       = ChartOfAccount::create(['code' => '1011', 'name' => 'CBE Main Checking', 'type' => 'asset', 'subtype' => 'cash_and_bank', 'normal_side' => 'debit']);
        $abyssiniaAcc = ChartOfAccount::create(['code' => '1012', 'name' => 'Bank of Abyssinia-27', 'type' => 'asset', 'subtype' => 'cash_and_bank', 'normal_side' => 'debit']);
        $capAcc       = ChartOfAccount::create(['code' => '3100', 'name' => 'Paid-Up Capital', 'type' => 'equity', 'normal_side' => 'credit']);
        $vendorAcc    = ChartOfAccount::create(['code' => '2001', 'name' => 'Accounts Payable', 'type' => 'liability', 'normal_side' => 'credit']);

        // 2. Transaction 1: Capital injection into CBE 500,000
        $this->journalEngine->createEntry(
            'capital', null, 'Initial Capital Injection',
            [
                ['account_id' => $cbeAcc->id, 'side' => 'debit', 'amount' => 500000.00],
                ['account_id' => $capAcc->id, 'side' => 'credit', 'amount' => 500000.00],
            ],
            $this->user->id, $this->user->id, '2026-10-01'
        );

        // 3. Transaction 2: Disburse vendor payment of 200,000 through Abyssinia overdraft facility
        $this->journalEngine->createEntry(
            'payment', null, 'Overdraft payment to supplier',
            [
                ['account_id' => $vendorAcc->id,    'side' => 'debit', 'amount' => 200000.00],
                ['account_id' => $abyssiniaAcc->id, 'side' => 'credit', 'amount' => 200000.00],
            ],
            $this->user->id, $this->user->id, '2026-10-15'
        );

        // 4. Generate Balance Sheet as of 2026-10-31
        $bs = $this->metricsService->getBalanceSheet('2026-10-31');

        // Assets = CBE (500,000) only; Abyssinia credit balance is NOT in Assets
        $this->assertEquals(500000.00, $bs['total_assets']);
        $this->assertCount(1, $bs['assets']);
        $this->assertEquals('1011', $bs['assets']->first()['code']);

        // Overdrafts = Abyssinia (200,000) presented in Liabilities
        $this->assertEquals(200000.00, $bs['total_overdrafts']);
        $this->assertCount(1, $bs['overdrafts']);
        $this->assertEquals('1012', $bs['overdrafts']->first()['code']);
        $this->assertEquals(200000.00, $bs['overdrafts']->first()['balance']);

        // Total liabilities = Overdraft (200,000) - AP Debit (200,000) = 0.00 net liabilities
        // Equity = Capital (500,000)
        $this->assertEquals(500000.00, $bs['total_equity']);

        // Accounting Equation holds perfectly: Assets (500,000) == Liabilities (0.00) + Equity (500,000)
        $this->assertEquals(500000.00, $bs['total_liabilities_and_equity']);
        $this->assertTrue($bs['is_balanced'], "Balance Sheet must balance with bank overdraft presented as liability.");
        $this->assertEquals(0.00, $bs['imbalance']);
    }

    public function test_chart_of_account_ledger_balance_accessor_reads_authoritative_balance_from_posted_journal_lines()
    {
        $arAcc   = ChartOfAccount::create(['code' => '1200', 'name' => 'Trade Receivables', 'type' => 'asset', 'normal_side' => 'debit', 'current_balance' => 99999.00]);
        $bankAcc = ChartOfAccount::create(['code' => '1011', 'name' => 'CBE Checking', 'type' => 'asset', 'normal_side' => 'debit', 'current_balance' => 88888.00]);

        // Post a balanced journal
        $this->journalEngine->createEntry(
            'invoice', null, 'Client Invoice Billed',
            [
                ['account_id' => $arAcc->id,   'side' => 'debit', 'amount' => 450000.00],
                ['account_id' => $bankAcc->id, 'side' => 'credit', 'amount' => 450000.00],
            ],
            $this->user->id, $this->user->id, '2026-10-10'
        );

        // Assert dynamic ledger_balance ignores the outdated current_balance and reflects real posted lines
        $this->assertEquals(450000.00, $arAcc->fresh()->ledger_balance);
        $this->assertEquals(-450000.00, $bankAcc->fresh()->ledger_balance);

        // Daily dual source discrepancy check detects difference between stored and ledger balance
        $check = $this->metricsService->getDualSourceDiscrepancies();
        $this->assertTrue($check['has_discrepancies']);
        $this->assertGreaterThanOrEqual(2, $check['discrepancy_count']);
    }
}
