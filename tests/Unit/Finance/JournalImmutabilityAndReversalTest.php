<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\FinancialAuditTrail;
use App\Models\User;
use App\Services\Finance\JournalEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Exception;

class JournalImmutabilityAndReversalTest extends TestCase
{
    protected JournalEngine $journalEngine;
    protected User $user;
    protected ChartOfAccount $cashAcc;
    protected ChartOfAccount $expAcc;

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
            'name'  => 'Senior Accountant',
            'email' => 'accountant@wechecha.com',
            'role'  => 'senior_accountant'
        ]);

        $this->cashAcc = ChartOfAccount::create([
            'code'            => '1011',
            'name'            => 'CBE Main Checking',
            'type'            => 'asset',
            'normal_side'     => 'debit',
            'current_balance' => 100000.00,
        ]);

        $this->expAcc = ChartOfAccount::create([
            'code'            => '6500',
            'name'            => 'Stationery Expense',
            'type'            => 'expense',
            'normal_side'     => 'debit',
            'current_balance' => 0.00,
        ]);

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

    public function test_posted_entries_cannot_be_cancelled_in_place()
    {
        // 1. Post a valid journal entry
        $entry = $this->journalEngine->createEntry(
            'stationery', null, 'Purchase of office paper',
            [
                ['account_id' => $this->expAcc->id,  'side' => 'debit',  'amount' => 5000.00],
                ['account_id' => $this->cashAcc->id, 'side' => 'credit', 'amount' => 5000.00],
            ],
            $this->user->id, $this->user->id, '2026-10-15'
        );

        $this->assertEquals('posted', $entry->status);

        // 2. Direct cancellation must be rejected with exception
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/Posted entries are immutable and cannot be cancelled or deleted. Use reverseEntry\(\) instead/');
        $this->journalEngine->cancelDraftEntry($entry->id, $this->user->id, 'Mistake on voucher');
    }

    public function test_reversing_entry_creates_exact_opposite_lines_and_links_audit_trail()
    {
        // Initial balances: Cash = 100,000, Expense = 0
        $entry = $this->journalEngine->createEntry(
            'stationery', null, 'Purchase of office printer toner',
            [
                ['account_id' => $this->expAcc->id,  'side' => 'debit',  'amount' => 12000.00],
                ['account_id' => $this->cashAcc->id, 'side' => 'credit', 'amount' => 12000.00],
            ],
            $this->user->id, $this->user->id, '2026-10-15'
        );

        $this->assertEquals(88000.00, $this->cashAcc->fresh()->current_balance);
        $this->assertEquals(12000.00, $this->expAcc->fresh()->current_balance);

        // Execute reversal
        $reversal = $this->journalEngine->reverseEntry(
            $entry->id,
            $this->user->id,
            'Supplier cancelled toner delivery and refunded payment'
        );

        // Verify reversal properties
        $this->assertEquals('posted', $reversal->status);
        $this->assertTrue((bool)$reversal->is_reversal);
        $this->assertEquals($entry->id, $reversal->reversal_of_id);
        $this->assertEquals($reversal->id, $entry->fresh()->reversed_by_id);

        // Verify opposite lines
        $this->assertCount(2, $reversal->lines);
        $revDebitLine  = $reversal->lines->firstWhere('side', 'debit');
        $revCreditLine = $reversal->lines->firstWhere('side', 'credit');

        $this->assertEquals($this->cashAcc->id, $revDebitLine->account_id);
        $this->assertEquals(12000.00, $revDebitLine->amount);

        $this->assertEquals($this->expAcc->id, $revCreditLine->account_id);
        $this->assertEquals(12000.00, $revCreditLine->amount);

        // Balances restored to initial state
        $this->assertEquals(100000.00, $this->cashAcc->fresh()->current_balance);
        $this->assertEquals(0.00, $this->expAcc->fresh()->current_balance);

        // Audit trail recorded
        $trail = FinancialAuditTrail::where('auditable_id', $entry->id)->where('action', 'reversed')->first();
        $this->assertNotNull($trail, "Reversal must be recorded in financial audit trail.");
    }
}
