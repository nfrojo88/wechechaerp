<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\FinancialAuditTrail;
use App\Models\User;
use App\Services\Finance\OpeningBalanceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Exception;

class OpeningBalanceLockedVoucherTest extends TestCase
{
    protected OpeningBalanceService $openingService;
    protected User $cfo;
    protected User $clerk;
    protected ChartOfAccount $cashAcc;
    protected ChartOfAccount $capAcc;
    protected ChartOfAccount $resAcc;
    protected ChartOfAccount $retAcc;

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

        $this->cfo   = User::create(['name' => 'Chief Financial Officer', 'email' => 'cfo@wechecha.com', 'is_admin' => true]);
        $this->cfo->role = 'cfo';
        $this->cfo->save();

        $this->clerk = User::create(['name' => 'Junior Entry Clerk', 'email' => 'clerk@wechecha.com', 'is_admin' => false]);
        $this->clerk->role = 'clerk';
        $this->clerk->save();

        $this->cashAcc = ChartOfAccount::create(['code' => '1011', 'name' => 'CBE Bank', 'type' => 'asset', 'normal_side' => 'debit']);
        $this->capAcc  = ChartOfAccount::create(['code' => '3100', 'name' => 'Paid-Up Capital', 'type' => 'equity', 'normal_side' => 'credit']);
        $this->resAcc  = ChartOfAccount::create(['code' => '3150', 'name' => 'Legal Reserve', 'type' => 'equity', 'normal_side' => 'credit']);
        $this->retAcc  = ChartOfAccount::create(['code' => '3152', 'name' => 'Retained Earnings (Prior Years)', 'type' => 'equity', 'normal_side' => 'credit']);

        $this->openingService = new OpeningBalanceService();
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
        $requiredTables = ['users', 'chart_of_accounts', 'journal_entries', 'journal_entry_lines', 'financial_audit_trails'];
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
            $table->string('role')->nullable();
            $table->boolean('is_admin')->default(false);
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

    public function test_opening_balance_audit_classifies_seed_data_under_needs_confirmation()
    {
        // Simulate seed data balances
        ChartOfAccount::create(['code' => '1010', 'name' => 'Petty Cash', 'type' => 'asset', 'opening_balance' => -742800.00]);
        ChartOfAccount::create(['code' => '1300', 'name' => 'WHT Receivable', 'type' => 'asset', 'opening_balance' => -36000.00]);

        $audit = $this->openingService->auditExistingBalances();

        $this->assertNotEmpty($audit['needs_confirmation']);
        $codes = $audit['needs_confirmation']->pluck('code')->toArray();
        $this->assertContains('1010', $codes);
        $this->assertContains('1300', $codes);
        $this->assertEquals('Needs Confirmation', $audit['needs_confirmation']->firstWhere('code', '1010')['audit_status']);
    }

    public function test_post_opening_journal_enforces_balance_and_locks_voucher()
    {
        // 1. Unbalanced opening balance attempt must fail (no plug allowed!)
        $unbalancedLines = [
            ['account_id' => $this->cashAcc->id, 'side' => 'debit', 'amount' => 5000000.00],
            ['account_id' => $this->capAcc->id,  'side' => 'credit', 'amount' => 4000000.00], // 1,000,000 difference
        ];

        try {
            $this->openingService->postOpeningJournal($unbalancedLines, $this->cfo, $this->cfo, '2026-07-08');
            $this->fail("Unbalanced opening journal must throw exception.");
        } catch (Exception $e) {
            $this->assertStringContainsString('Opening Trial Balance does not balance', $e->getMessage());
            $this->assertStringContainsString('No balancing plug account is permitted', $e->getMessage());
        }

        // 2. Balanced opening balance: Debit Cash 5M, Credit Capital 4M, Credit Legal Reserve 500K, Credit Retained Earnings 500K
        $balancedLines = [
            ['account_id' => $this->cashAcc->id, 'side' => 'debit',  'amount' => 5000000.00, 'description' => 'Audited CBE closing balance'],
            ['account_id' => $this->capAcc->id,  'side' => 'credit', 'amount' => 4000000.00, 'description' => 'Statutory Paid-Up Capital'],
            ['account_id' => $this->resAcc->id,  'side' => 'credit', 'amount' => 500000.00,  'description' => 'Statutory Legal Reserve (5%)'],
            ['account_id' => $this->retAcc->id,  'side' => 'credit', 'amount' => 500000.00,  'description' => 'Audited Retained Earnings'],
        ];

        $journal = $this->openingService->postOpeningJournal($balancedLines, $this->cfo, $this->cfo, '2026-07-08');

        $this->assertEquals('JE-OPENING-FY2026', $journal->entry_no);
        $this->assertEquals('2026-07-08', $journal->entry_date->toDateString());
        $this->assertTrue((bool)$journal->is_locked);
        $this->assertEquals($this->cfo->id, $journal->created_by);
        $this->assertEquals($this->cfo->id, $journal->approved_by);

        // Verify account opening balances updated correctly
        $this->assertEquals(5000000.00, $this->cashAcc->fresh()->opening_balance);
        $this->assertEquals(4000000.00, $this->capAcc->fresh()->opening_balance);
    }

    public function test_reopening_opening_journal_requires_cfo_role_and_justification()
    {
        $balancedLines = [
            ['account_id' => $this->cashAcc->id, 'side' => 'debit',  'amount' => 1000000.00],
            ['account_id' => $this->capAcc->id,  'side' => 'credit', 'amount' => 1000000.00],
        ];

        $journal = $this->openingService->postOpeningJournal($balancedLines, $this->cfo, $this->cfo, '2026-07-08');
        $this->assertTrue((bool)$journal->is_locked);

        // 1. Unauthorized user (clerk) rejected
        try {
            $this->openingService->reopenOpeningJournal($this->clerk, 'Unauthorized attempt');
            $this->fail("Non-CFO/Admin cannot reopen opening balance.");
        } catch (Exception $e) {
            $this->assertStringContainsString('Unauthorized. Only the CFO or Global Admin can reopen', $e->getMessage());
        }

        // 2. CFO with insufficient justification rejected
        try {
            $this->openingService->reopenOpeningJournal($this->cfo, 'short');
            $this->fail("Short justification must be rejected.");
        } catch (Exception $e) {
            $this->assertStringContainsString('A detailed accounting justification', $e->getMessage());
        }

        // 3. Authorized CFO with full justification succeeds
        $reopened = $this->openingService->reopenOpeningJournal(
            $this->cfo,
            'External audit adjustment received for prior year accrued retainage'
        );

        $this->assertFalse((bool)$reopened->is_locked);
        $this->assertEquals($this->cfo->id, $reopened->reopened_by);
        $this->assertNotNull($reopened->reopened_at);
        $this->assertEquals('External audit adjustment received for prior year accrued retainage', $reopened->reopen_reason);
    }
}
