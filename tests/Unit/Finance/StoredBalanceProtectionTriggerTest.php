<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;
use App\Models\ChartOfAccount;
use App\Models\BankAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\FinancialPeriod;
use App\Models\FinancialAuditTrail;
use App\Models\User;
use App\Services\Finance\JournalEngine;
use App\Services\Finance\FinanceMetricsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\QueryException;
use RuntimeException;

class StoredBalanceProtectionTriggerTest extends TestCase
{
    protected JournalEngine $journalEngine;
    protected FinanceMetricsService $metricsService;
    protected User $user;
    protected ChartOfAccount $bankCoa;
    protected ChartOfAccount $expenseCoa;
    protected BankAccount $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            DB::statement('SET @gl_posting_in_progress = 0;');
        }

        $this->ensureSchema();
        $this->cleanTestData();

        $this->user = User::create([
            'name'  => 'Finance Manager',
            'email' => 'finance.mgr@wechecha.com',
            'role'  => 'finance_manager',
        ]);

        $this->bankCoa = ChartOfAccount::create([
            'code'        => '1020',
            'name'        => 'CBE Main Checking Account',
            'type'        => 'asset',
            'normal_side' => 'debit',
        ]);

        $this->expenseCoa = ChartOfAccount::create([
            'code'        => '6400',
            'name'        => 'Site Electricity & Utilities',
            'type'        => 'expense',
            'normal_side' => 'debit',
        ]);

        $this->bankAccount = BankAccount::create([
            'account_name'   => 'CBE Operational',
            'account_number' => '1000123456789',
            'bank_name'      => 'Commercial Bank of Ethiopia',
            'coa_id'         => $this->bankCoa->id,
            'current_balance'=> 0.00,
            'is_active'      => true,
        ]);

        FinancialPeriod::create([
            'name'       => 'August 2026',
            'code'       => 'FP-2026-08',
            'start_date' => '2026-08-01',
            'end_date'   => '2026-08-31',
            'is_locked'  => false,
        ]);

        $this->journalEngine = new JournalEngine();
        $this->metricsService = new FinanceMetricsService();
    }

    protected function tearDown(): void
    {
        $this->cleanTestData();
        parent::tearDown();
    }

    protected function cleanTestData(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            DB::statement('SET @gl_posting_in_progress = 1;');
            DB::statement('TRUNCATE TABLE journal_entry_lines;');
            DB::statement('TRUNCATE TABLE journal_entries;');
            DB::statement('TRUNCATE TABLE bank_accounts;');
            DB::statement('TRUNCATE TABLE chart_of_accounts;');
            DB::statement('TRUNCATE TABLE financial_periods;');
            DB::statement('TRUNCATE TABLE financial_audit_trails;');
            DB::statement('TRUNCATE TABLE users;');
            DB::statement('SET @gl_posting_in_progress = 0;');
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }
    }

    protected function ensureSchema(): void
    {
        if (Schema::hasTable('journal_entries')) {
            return;
        }
        $requiredTables = ['users', 'chart_of_accounts', 'bank_accounts', 'journal_entries', 'journal_entry_lines', 'financial_periods', 'financial_audit_trails'];
        $allExist = true;
        foreach ($requiredTables as $t) {
            if (!Schema::hasTable($t)) {
                $allExist = false;
                break;
            }
        }

        if ($allExist) {
            $triggerExists = DB::select("SHOW TRIGGERS LIKE 'trg_coa_block_direct_balance_update'");
            if (!empty($triggerExists) && Schema::hasColumn('journal_entries', 'is_reversal')) {
                return;
            }
        }

        // Clean tables
        Schema::dropIfExists('financial_audit_trails');
        Schema::dropIfExists('financial_periods');
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('bank_accounts');
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
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('bank_accounts', function ($table) {
            $table->id();
            $table->string('account_name');
            $table->string('account_number')->unique();
            $table->string('bank_name');
            $table->unsignedBigInteger('coa_id')->unique();
            $table->decimal('current_balance', 18, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
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
            $table->timestamps();
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
            $table->boolean('is_seed')->default(false);
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('journal_entry_lines', function ($table) {
            $table->id();
            $table->unsignedBigInteger('journal_entry_id');
            $table->unsignedBigInteger('account_id');
            $table->string('side', 10);
            $table->decimal('amount', 18, 2);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('original_account_id')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('cost_center_id')->nullable();
            $table->unsignedBigInteger('site_id')->nullable();
            $table->string('party_type', 50)->nullable();
            $table->unsignedBigInteger('party_id')->nullable();
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

        // Re-attach triggers in test DB
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared("DROP TRIGGER IF EXISTS trg_coa_block_direct_balance_update;");
            DB::unprepared("
                CREATE TRIGGER trg_coa_block_direct_balance_update
                BEFORE UPDATE ON chart_of_accounts
                FOR EACH ROW
                BEGIN
                    IF NOT (OLD.current_balance <=> NEW.current_balance) THEN
                        IF @gl_posting_in_progress IS NULL OR @gl_posting_in_progress <> 1 THEN
                            SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'Direct update of chart_of_accounts.current_balance is blocked. Balances derive only from posted journals.';
                        END IF;
                    END IF;
                END;
            ");

            DB::unprepared("DROP TRIGGER IF EXISTS trg_bank_account_block_direct_balance_update;");
            DB::unprepared("
                CREATE TRIGGER trg_bank_account_block_direct_balance_update
                BEFORE UPDATE ON bank_accounts
                FOR EACH ROW
                BEGIN
                    IF NOT (OLD.current_balance <=> NEW.current_balance) THEN
                        IF @gl_posting_in_progress IS NULL OR @gl_posting_in_progress <> 1 THEN
                            SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'Direct update of bank_accounts.current_balance is blocked. Balances derive only from posted journals.';
                        END IF;
                    END IF;
                END;
            ");
        }
    }

    public function test_eloquent_instance_update_on_coa_current_balance_throws_runtime_exception()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Direct update of chart_of_accounts.current_balance is strictly prohibited");

        $this->bankCoa->update(['current_balance' => 5000.00]);
    }

    public function test_eloquent_instance_update_on_bank_account_current_balance_throws_runtime_exception()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Direct update of bank_accounts.current_balance is strictly prohibited");

        $this->bankAccount->update(['current_balance' => 5000.00]);
    }

    public function test_db_table_update_on_coa_current_balance_blocked_by_database_trigger()
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Database triggers require MySQL.');
        }

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage("Direct update of chart_of_accounts.current_balance is blocked");

        DB::table('chart_of_accounts')
            ->where('id', $this->bankCoa->id)
            ->update(['current_balance' => 7777.00]);
    }

    public function test_raw_sql_update_on_coa_current_balance_blocked_by_database_trigger()
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Database triggers require MySQL.');
        }

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage("Direct update of chart_of_accounts.current_balance is blocked");

        DB::statement("UPDATE chart_of_accounts SET current_balance = 8888.00 WHERE id = {$this->bankCoa->id}");
    }

    public function test_eloquent_mass_update_on_coa_current_balance_blocked_by_database_trigger()
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Database triggers require MySQL.');
        }

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage("Direct update of chart_of_accounts.current_balance is blocked");

        ChartOfAccount::where('id', $this->bankCoa->id)->update(['current_balance' => 9999.00]);
    }

    public function test_db_table_update_on_bank_account_current_balance_blocked_by_database_trigger()
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Database triggers require MySQL.');
        }

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage("Direct update of bank_accounts.current_balance is blocked");

        DB::table('bank_accounts')
            ->where('id', $this->bankAccount->id)
            ->update(['current_balance' => 4444.00]);
    }

    public function test_raw_sql_update_on_bank_account_current_balance_blocked_by_database_trigger()
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Database triggers require MySQL.');
        }

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage("Direct update of bank_accounts.current_balance is blocked");

        DB::statement("UPDATE bank_accounts SET current_balance = 3333.00 WHERE id = {$this->bankAccount->id}");
    }

    public function test_authorized_posting_via_journal_engine_updates_both_coa_and_bank_account_balances()
    {
        // Deposit 100,000 ETB into CBE Bank
        $je = $this->journalEngine->createEntry(
            referenceType: 'capital_deposit',
            referenceId: 101,
            description: 'Capital deposit into CBE bank account',
            lines: [
                ['account_id' => $this->bankCoa->id, 'side' => 'debit', 'amount' => 100000.00, 'description' => 'Bank debit'],
                ['account_id' => $this->expenseCoa->id, 'side' => 'credit', 'amount' => 100000.00, 'description' => 'Contra credit'],
            ],
            createdBy: $this->user->id,
            approvedBy: $this->user->id,
            entryDate: '2026-08-15'
        );

        $this->assertEquals('posted', $je->status);

        // Verify ChartOfAccount current_balance AND ledger_balance
        $this->bankCoa->refresh();
        $this->assertEquals(100000.00, (float) $this->bankCoa->current_balance);
        $this->assertEquals(100000.00, (float) $this->bankCoa->ledger_balance);

        // Verify linked BankAccount current_balance AND ledger_balance
        $this->bankAccount->refresh();
        $this->assertEquals(100000.00, (float) $this->bankAccount->current_balance);
        $this->assertEquals(100000.00, (float) $this->bankAccount->ledger_balance);

        // Post a disbursement of 25,000 ETB
        $disbursementJe = $this->journalEngine->createEntry(
            referenceType: 'utility_payment',
            referenceId: 102,
            description: 'Utility payment from CBE bank account',
            lines: [
                ['account_id' => $this->expenseCoa->id, 'side' => 'debit', 'amount' => 25000.00, 'description' => 'Expense debit'],
                ['account_id' => $this->bankCoa->id, 'side' => 'credit', 'amount' => 25000.00, 'description' => 'Bank credit'],
            ],
            createdBy: $this->user->id,
            approvedBy: $this->user->id,
            entryDate: '2026-08-20'
        );

        $this->bankCoa->refresh();
        $this->bankAccount->refresh();
        $this->assertEquals(75000.00, (float) $this->bankCoa->current_balance);
        $this->assertEquals(75000.00, (float) $this->bankCoa->ledger_balance);
        $this->assertEquals(75000.00, (float) $this->bankAccount->current_balance);
        $this->assertEquals(75000.00, (float) $this->bankAccount->ledger_balance);
    }

    public function test_daily_reconciliation_verifies_bank_accounts_match_ledger_balance()
    {
        // Initially 0.00, perfectly reconciled
        $reconciliation = $this->metricsService->reconcileBankAccountsVsLedger();
        $this->assertTrue($reconciliation['reconciled']);
        $this->assertEquals(0, $reconciliation['discrepancies']);
        $this->assertCount(1, $reconciliation['report']);
        $this->assertEquals(0.00, $reconciliation['report'][0]['variance']);
        $this->assertTrue($reconciliation['report'][0]['is_reconciled']);

        // Post a transaction via JournalEngine
        $this->journalEngine->createEntry(
            referenceType: 'deposit',
            referenceId: 103,
            description: 'Deposit',
            lines: [
                ['account_id' => $this->bankCoa->id, 'side' => 'debit', 'amount' => 50000.00, 'description' => 'Bank debit'],
                ['account_id' => $this->expenseCoa->id, 'side' => 'credit', 'amount' => 50000.00, 'description' => 'Credit'],
            ],
            createdBy: $this->user->id,
            approvedBy: $this->user->id,
            entryDate: '2026-08-25'
        );

        $reconciliation = $this->metricsService->reconcileBankAccountsVsLedger();
        $this->assertTrue($reconciliation['reconciled']);
        $this->assertEquals(0, $reconciliation['discrepancies']);
        $this->assertEquals(50000.00, $reconciliation['report'][0]['bank_stored_balance']);
        $this->assertEquals(50000.00, $reconciliation['report'][0]['coa_ledger_balance']);
        $this->assertEquals(0.00, $reconciliation['report'][0]['variance']);
    }

    public function test_gl_posting_in_progress_resets_to_zero_when_posting_fails()
    {
        // 1. Initial state: @gl_posting_in_progress is 0
        $flag = DB::select("SELECT @gl_posting_in_progress as flag")[0]->flag ?? 0;
        $this->assertEquals(0, (int)$flag);

        // 2. Attach an event listener on ChartOfAccount that simulates a database failure on save
        $failingAccount = ChartOfAccount::create([
            'code'            => 'FAIL999',
            'name'            => 'Failing Account Test',
            'type'            => 'expense',
            'normal_side'     => 'debit',
            'opening_balance' => 0.00,
            'current_balance' => 0.00,
        ]);

        ChartOfAccount::saving(function ($account) {
            if ($account->code === 'FAIL999') {
                throw new \RuntimeException("Simulated fatal database failure during line posting");
            }
        });

        // 3. Create a draft journal entry that includes this failing account
        $draftJe = JournalEntry::create([
            'entry_no'       => 'JE-FAIL-RESET-001',
            'entry_date'     => '2026-08-28',
            'status'         => 'draft',
            'created_by'     => $this->user->id,
        ]);
        JournalEntryLine::create([
            'journal_entry_id' => $draftJe->id,
            'account_id'       => $failingAccount->id,
            'side'             => 'debit',
            'amount'           => 12500.00,
            'description'      => 'Debit to failing account',
        ]);
        JournalEntryLine::create([
            'journal_entry_id' => $draftJe->id,
            'account_id'       => $this->bankCoa->id,
            'side'             => 'credit',
            'amount'           => 12500.00,
            'description'      => 'Credit to bank account',
        ]);

        $exceptionCaught = false;
        try {
            $this->journalEngine->postEntry($draftJe->id, $this->user->id);
        } catch (\Throwable $e) {
            $exceptionCaught = true;
            $this->assertStringContainsString("Simulated fatal database failure", $e->getMessage());
        }

        $this->assertTrue($exceptionCaught, "An exception was expected during posting failure.");

        // 4. Assert that @gl_posting_in_progress reset to 0 in MySQL session variable
        $flagAfter = DB::select("SELECT @gl_posting_in_progress as flag")[0]->flag ?? 0;
        $this->assertEquals(0, (int)$flagAfter, "@gl_posting_in_progress must reset to 0 in finally block.");
        $this->assertFalse((bool)(app()->bound('gl_posting_in_progress') ? app('gl_posting_in_progress') : false), "Container flag must also be false.");

        // 5. Assert that direct updates to stored balances are STILL blocked by the database trigger
        $this->expectException(RuntimeException::class);
        $this->bankCoa->current_balance += 5000;
        $this->bankCoa->save();
    }

    public function test_multiple_bank_accounts_cannot_link_to_same_coa_id_at_model_level()
    {
        $this->assertEquals($this->bankCoa->id, $this->bankAccount->coa_id);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("already linked to another bank account");

        BankAccount::create([
            'account_name'   => 'Duplicate Bank Account',
            'account_number' => 'ETB-DUPLICATE-001',
            'bank_name'      => 'Commercial Bank of Ethiopia',
            'coa_id'         => $this->bankCoa->id,
            'current_balance'=> 0.00,
            'is_active'      => true,
        ]);
    }

    public function test_multiple_bank_accounts_cannot_link_to_same_coa_id_at_database_level()
    {
        $this->assertEquals($this->bankCoa->id, $this->bankAccount->coa_id);

        $this->expectException(QueryException::class);

        DB::table('bank_accounts')->insert([
            'account_name'   => 'Direct DB Duplicate',
            'account_number' => 'ETB-DB-DUP-001',
            'bank_name'      => 'Commercial Bank of Ethiopia',
            'coa_id'         => $this->bankCoa->id,
            'current_balance'=> 0.00,
            'is_active'      => 1,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }
}
