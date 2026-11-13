<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;
use App\Models\ChartOfAccount;
use App\Models\FinancialPeriod;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\User;
use App\Services\Finance\TrialBalanceIntegrityService;
use App\Services\Finance\JournalEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Exception;

class TrialBalanceIntegrityTest extends TestCase
{
    protected TrialBalanceIntegrityService $tbService;
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
            'name'  => 'Senior Finance Lead',
            'email' => 'finance@wechecha.com',
            'role'  => 'finance_manager'
        ]);

        $this->tbService     = new TrialBalanceIntegrityService();
        $this->journalEngine = new JournalEngine();
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

    public function test_trial_balance_debits_equal_credits_when_properly_posted()
    {
        // 1. Setup COA for all 5 account types
        $cashAcc = ChartOfAccount::create(['code' => '1011', 'name' => 'CBE Main Bank', 'type' => 'asset', 'normal_side' => 'debit']);
        $apAcc   = ChartOfAccount::create(['code' => '2001', 'name' => 'Trade Payables', 'type' => 'liability', 'normal_side' => 'credit']);
        $eqAcc   = ChartOfAccount::create(['code' => '3100', 'name' => 'Paid-Up Capital', 'type' => 'equity', 'normal_side' => 'credit']);
        $revAcc  = ChartOfAccount::create(['code' => '4200', 'name' => 'Equipment Leasing', 'type' => 'revenue', 'normal_side' => 'credit']);
        $matAcc  = ChartOfAccount::create(['code' => '5100', 'name' => 'Material Expense', 'type' => 'expense', 'normal_side' => 'debit']);

        // 2. Post balanced transaction 1: Capital injection (Debit Cash 500,000, Credit Capital 500,000)
        $this->journalEngine->createEntry(
            'capital_deposit',
            null,
            'Initial Capital Injection',
            [
                ['account_id' => $cashAcc->id, 'side' => 'debit', 'amount' => 500000.00],
                ['account_id' => $eqAcc->id, 'side' => 'credit', 'amount' => 500000.00],
            ],
            $this->user->id,
            $this->user->id,
            '2026-10-01'
        );

        // 3. Post balanced transaction 2: Material purchase on credit (Debit Material 150,000, Credit AP 150,000)
        $this->journalEngine->createEntry(
            'material_purchase',
            null,
            'Rebar steel delivery on credit',
            [
                ['account_id' => $matAcc->id, 'side' => 'debit', 'amount' => 150000.00],
                ['account_id' => $apAcc->id, 'side' => 'credit', 'amount' => 150000.00],
            ],
            $this->user->id,
            $this->user->id,
            '2026-10-05'
        );

        // 4. Post balanced transaction 3: Equipment rental revenue received in bank (Debit Cash 80,000, Credit Revenue 80,000)
        $this->journalEngine->createEntry(
            'leasing_income',
            null,
            'Excavator rental payment received',
            [
                ['account_id' => $cashAcc->id, 'side' => 'debit', 'amount' => 80000.00],
                ['account_id' => $revAcc->id, 'side' => 'credit', 'amount' => 80000.00],
            ],
            $this->user->id,
            $this->user->id,
            '2026-10-10'
        );

        // 5. Run Trial Balance
        $tb = $this->tbService->getTrialBalance('2026-10-31');

        $this->assertTrue($tb['is_balanced'], "Trial Balance must be strictly balanced.");
        $this->assertEquals(0.00, $tb['difference'], "Discrepancy must be exactly 0.00 ETB.");
        $this->assertEquals(730000.00, $tb['total_debit']);
        $this->assertEquals(730000.00, $tb['total_credit']);
    }

    public function test_integrity_check_detects_unbalanced_entries_and_blocks_period_close()
    {
        $cashAcc = ChartOfAccount::create(['code' => '1011', 'name' => 'CBE Bank', 'type' => 'asset', 'normal_side' => 'debit']);
        $expAcc  = ChartOfAccount::create(['code' => '6000', 'name' => 'Office Expense', 'type' => 'expense', 'normal_side' => 'debit']);

        // Create a period
        $period = FinancialPeriod::create([
            'name'       => 'October 2026',
            'code'       => 'FP-2026-10',
            'start_date' => '2026-10-01',
            'end_date'   => '2026-10-31',
        ]);

        // Deliberately simulate an unbalanced legacy entry (bypassing JournalEngine)
        $badEntry = JournalEntry::create([
            'entry_no'   => 'JE-LEGACY-BAD-01',
            'entry_date' => '2026-10-15',
            'description'=> 'Bad legacy unbalanced entry',
            'status'     => 'posted',
            'created_by' => $this->user->id,
        ]);

        // Only one line inserted (Debit 25,000 without corresponding credit)
        JournalEntryLine::create([
            'journal_entry_id' => $badEntry->id,
            'account_id'       => $expAcc->id,
            'side'             => 'debit',
            'amount'           => 25000.00,
        ]);

        $integrity = $this->tbService->checkIntegrity('2026-10-31', '2026-10-01');

        $this->assertFalse($integrity['is_balanced'], "Integrity check must flag imbalance.");
        $this->assertFalse($integrity['can_close_period'], "Period close must be blocked.");
        $this->assertCount(1, $integrity['unbalanced_entries'], "Must isolate the specific bad entry.");
        $this->assertEquals('JE-LEGACY-BAD-01', $integrity['unbalanced_entries']->first()->entry_no);

        // Attempting to close period must throw exception
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/Period close blocked by Accounting Integrity Guard/');
        $this->tbService->enforcePeriodCloseIntegrity($period);
    }
}
