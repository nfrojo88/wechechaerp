<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\User;
use App\Services\Finance\AbnormalBalanceService;
use App\Services\Finance\JournalEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AbnormalBalanceDetectionTest extends TestCase
{
    protected AbnormalBalanceService $abnormalService;
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
            'name'  => 'Audit Officer',
            'email' => 'auditor@wechecha.com',
            'role'  => 'internal_auditor'
        ]);

        $this->abnormalService = new AbnormalBalanceService();
        $this->journalEngine   = new JournalEngine();
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
        $requiredTables = [
            'users', 'chart_of_accounts', 'journal_entries',
            'journal_entry_lines', 'financial_periods', 'financial_audit_trails'
        ];

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

    public function test_abnormal_balance_engine_flags_inverted_petty_cash_and_wht_receivable()
    {
        // 1. Create accounts simulating the legacy negative seed balances
        // 1010 Petty Cash seeded with -742,800
        $pettyAcc = ChartOfAccount::create([
            'code'            => '1010',
            'name'            => 'Petty Cash',
            'type'            => 'asset',
            'normal_side'     => 'debit',
            'opening_balance' => -742800.00,
        ]);

        // 1300 WHT Receivable seeded with -36,000
        $whtAcc = ChartOfAccount::create([
            'code'            => '1300',
            'name'            => 'Withholding Tax Receivable',
            'type'            => 'asset',
            'normal_side'     => 'debit',
            'opening_balance' => -36000.00,
        ]);

        // 1011 CBE Bank with normal positive debit balance
        $cbeAcc = ChartOfAccount::create([
            'code'            => '1011',
            'name'            => 'CBE Main Checking',
            'type'            => 'asset',
            'normal_side'     => 'debit',
            'opening_balance' => 1354000.00,
        ]);

        // 2001 AP with normal credit balance
        $apAcc = ChartOfAccount::create([
            'code'            => '2001',
            'name'            => 'Trade Payables',
            'type'            => 'liability',
            'normal_side'     => 'credit',
            'opening_balance' => 1087039.34,
        ]);

        // 1012 Bank of Abyssinia with credit balance (Overdraft facility)
        $abyssiniaAcc = ChartOfAccount::create([
            'code'            => '1012',
            'name'            => 'Bank of Abyssinia Overdraft',
            'type'            => 'asset',
            'subtype'         => 'cash_and_bank',
            'normal_side'     => 'debit',
            'opening_balance' => -2678645.00,
        ]);

        // Rule 5 (Correction): Only bank facility accounts (1011-1013) allow credit side without abnormal flag.
        // Account 1010 Petty Cash MUST be flagged when credit because physical cash cannot be negative.
        // Account 1300 Withholding Tax Receivable with negative/credit balance MUST be flagged as abnormal.
        $abnormal = $this->abnormalService->getAbnormalAccounts();

        $flaggedCodes = $abnormal->pluck('code')->toArray();
        $this->assertContains('1010', $flaggedCodes, "Rule 5 Correction: Account 1010 Petty Cash MUST be flagged when credit.");
        $this->assertNotContains('1011', $flaggedCodes);
        $this->assertNotContains('1012', $flaggedCodes, "Rule 5 Correction: Bank facility accounts (1011-1013) allow credit side without abnormal flag.");
        $this->assertContains('1300', $flaggedCodes, "Account 1300 with credit balance must be flagged as abnormal.");

        $pettyFinding = $abnormal->firstWhere('code', '1010');
        $this->assertEquals('debit', $pettyFinding['normal_side']);
        $this->assertEquals('credit', $pettyFinding['actual_side']);
        $this->assertEquals(742800.00, $pettyFinding['abnormal_amount']);
        $this->assertEquals('high', $pettyFinding['risk_level']);
        $this->assertStringContainsString('Petty Cash carries an abnormal credit balance', $pettyFinding['explanation']);

        $whtFinding = $abnormal->firstWhere('code', '1300');
        $this->assertEquals('debit', $whtFinding['normal_side']);
        $this->assertEquals('credit', $whtFinding['actual_side']);
        $this->assertEquals(36000.00, $whtFinding['abnormal_amount']);
        $this->assertEquals('high', $whtFinding['risk_level']);
    }

    public function test_abnormal_balance_engine_flags_abnormal_debit_balance_on_liability()
    {
        // 2001 AP with abnormal net debit balance
        $apAcc = ChartOfAccount::create([
            'code'            => '2001',
            'name'            => 'Trade Payables',
            'type'            => 'liability',
            'normal_side'     => 'credit',
            'opening_balance' => -1087039.34, // Inverted sign => net debit
        ]);

        $abnormal = $this->abnormalService->getAbnormalAccounts();
        $flaggedCodes = $abnormal->pluck('code')->toArray();
        $this->assertContains('2001', $flaggedCodes, "Liability with net debit balance must be flagged as abnormal.");
    }

    public function test_regularized_petty_cash_and_wht_clear_abnormal_flags()
    {
        // When 1010 is properly represented as an imprest advance debit float
        $pettyAcc = ChartOfAccount::create([
            'code'            => '1010',
            'name'            => 'Petty Cash',
            'type'            => 'asset',
            'normal_side'     => 'debit',
            'opening_balance' => 150000.00, // Positive debit float
        ]);

        // When 1300 is properly represented as tax credit asset
        $whtAcc = ChartOfAccount::create([
            'code'            => '1300',
            'name'            => 'Withholding Tax Receivable',
            'type'            => 'asset',
            'normal_side'     => 'debit',
            'opening_balance' => 36000.00, // Positive debit asset
        ]);

        $abnormal = $this->abnormalService->getAbnormalAccounts();
        $this->assertEmpty($abnormal, "Regularized positive debit balances must produce 0 abnormal flags.");
    }

    public function test_account_1012_credit_not_flagged()
    {
        // 1012 Bank of Abyssinia facility carrying negative balance (credit overdraft of ETB 2,678,645.00)
        $abyssiniaAcc = ChartOfAccount::create([
            'code'            => '1012',
            'name'            => 'Bank of Abyssinia Overdraft',
            'type'            => 'asset',
            'subtype'         => 'cash_and_bank',
            'normal_side'     => 'debit',
            'opening_balance' => -2678645.00,
        ]);

        $abnormal = $this->abnormalService->getAbnormalAccounts();
        $flaggedCodes = $abnormal->pluck('code')->toArray();

        $this->assertNotContains('1012', $flaggedCodes, "Account 1012 Bank of Abyssinia credit balance must NOT be flagged as abnormal (it is a valid commercial overdraft).");
    }
}
