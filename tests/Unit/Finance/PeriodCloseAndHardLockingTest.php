<?php

namespace Tests\Unit\Finance;

use App\Models\ChartOfAccount;
use App\Models\BankAccount;
use App\Models\FinancialPeriod;
use App\Models\PeriodClosingChecklist;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\User;
use App\Models\FinancialAuditTrail;
use App\Services\Finance\JournalEngine;
use App\Services\Finance\TrialBalanceIntegrityService;
use App\Services\Finance\FinanceMetricsService;
use App\Services\Finance\AbnormalBalanceService;
use App\Services\Finance\PeriodCloseService;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Exception;
use InvalidArgumentException;

class PeriodCloseAndHardLockingTest extends TestCase
{
    protected PeriodCloseService $periodCloseService;
    protected JournalEngine $journalEngine;
    protected User $cfoUser;
    protected User $staffUser;
    protected FinancialPeriod $periodAug;
    protected FinancialPeriod $periodSep;
    protected ChartOfAccount $cashAcc;
    protected ChartOfAccount $revAcc;
    protected ChartOfAccount $expAcc;
    protected ChartOfAccount $retainedAcc;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            DB::statement('SET @gl_posting_in_progress = 1;');
            DB::statement('SET @period_lock_override = 0;');
            $this->ensureLockingTriggersExist();
        }

        if (!Schema::hasTable('financial_periods')) {
            Schema::create('financial_periods', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->string('name', 50);
                $table->string('code', 20)->unique();
                $table->date('start_date');
                $table->date('end_date');
                $table->string('fiscal_year', 20)->default('2026');
                $table->string('status', 30)->default('open');
                $table->boolean('is_locked')->default(false);
                $table->boolean('is_period_13')->default(false);
                $table->timestamp('closed_at')->nullable();
                $table->unsignedBigInteger('closed_by')->nullable();
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
        } else {
            Schema::table('financial_periods', function (\Illuminate\Database\Schema\Blueprint $table) {
                if (!Schema::hasColumn('financial_periods', 'fiscal_year')) {
                    $table->string('fiscal_year', 20)->default('2026');
                }
                if (!Schema::hasColumn('financial_periods', 'status')) {
                    $table->string('status', 30)->default('open');
                }
                if (!Schema::hasColumn('financial_periods', 'is_period_13')) {
                    $table->boolean('is_period_13')->default(false);
                }
                if (!Schema::hasColumn('financial_periods', 'closed_at')) {
                    $table->timestamp('closed_at')->nullable();
                }
                if (!Schema::hasColumn('financial_periods', 'closed_by')) {
                    $table->unsignedBigInteger('closed_by')->nullable();
                }
                if (!Schema::hasColumn('financial_periods', 'locked_at')) {
                    $table->timestamp('locked_at')->nullable();
                }
                if (!Schema::hasColumn('financial_periods', 'locked_by')) {
                    $table->unsignedBigInteger('locked_by')->nullable();
                }
                if (!Schema::hasColumn('financial_periods', 'reopened_at')) {
                    $table->timestamp('reopened_at')->nullable();
                }
                if (!Schema::hasColumn('financial_periods', 'reopened_by')) {
                    $table->unsignedBigInteger('reopened_by')->nullable();
                }
                if (!Schema::hasColumn('financial_periods', 'reopen_reason')) {
                    $table->text('reopen_reason')->nullable();
                }
                if (!Schema::hasColumn('financial_periods', 'tb_balanced_at_close')) {
                    $table->boolean('tb_balanced_at_close')->default(false);
                }
                if (!Schema::hasColumn('financial_periods', 'tb_total_debit')) {
                    $table->decimal('tb_total_debit', 18, 2)->nullable();
                }
                if (!Schema::hasColumn('financial_periods', 'tb_total_credit')) {
                    $table->decimal('tb_total_credit', 18, 2)->nullable();
                }
                if (!Schema::hasColumn('financial_periods', 'tb_difference')) {
                    $table->decimal('tb_difference', 18, 2)->default(0);
                }
                if (!Schema::hasColumn('financial_periods', 'closing_notes')) {
                    $table->text('closing_notes')->nullable();
                }
            });
        }

        if (!Schema::hasTable('period_closing_checklists')) {
            Schema::create('period_closing_checklists', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('financial_period_id');
                $table->string('item_key', 60);
                $table->string('item_name', 150);
                $table->enum('status', ['pending', 'passed', 'failed', 'waived'])->default('pending');
                $table->timestamp('verified_at')->nullable();
                $table->unsignedBigInteger('verified_by')->nullable();
                $table->json('details')->nullable();
                $table->text('waiver_reason')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('financial_audit_trails')) {
            Schema::create('financial_audit_trails', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->string('auditable_type');
                $table->unsignedBigInteger('auditable_id');
                $table->string('action', 100);
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('user_role', 50)->nullable();
                $table->text('reason')->nullable();
                $table->json('payload')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamps();
            });
        }

        DB::beginTransaction();

        // Users
        $this->cfoUser = User::forceCreate([
            'name'  => 'CFO Officer',
            'email' => 'cfo@wechecha.com',
            'role'  => 'finance_head',
        ]);

        $this->staffUser = User::forceCreate([
            'name'  => 'Site Accountant',
            'email' => 'accountant@wechecha.com',
            'role'  => 'accountant',
        ]);

        // Financial Periods
        $this->periodAug = FinancialPeriod::create([
            'name'        => 'August 2026',
            'code'        => 'FP-2026-08',
            'start_date'  => '2026-08-01',
            'end_date'    => '2026-08-31',
            'fiscal_year' => '2026',
            'status'      => 'open',
            'is_locked'   => false,
        ]);

        $this->periodSep = FinancialPeriod::create([
            'name'        => 'September 2026',
            'code'        => 'FP-2026-09',
            'start_date'  => '2026-09-01',
            'end_date'    => '2026-09-30',
            'fiscal_year' => '2026',
            'status'      => 'open',
            'is_locked'   => false,
        ]);

        // Key Accounts
        $this->cashAcc = ChartOfAccount::create([
            'code'        => '1011',
            'name'        => 'Commercial Bank of Ethiopia',
            'type'        => 'asset',
            'normal_side' => 'debit',
            'is_active'   => true,
        ]);

        $this->revAcc = ChartOfAccount::create([
            'code'        => '4200',
            'name'        => 'Machine Rent Income',
            'type'        => 'revenue',
            'normal_side' => 'credit',
            'is_active'   => true,
        ]);

        $this->expAcc = ChartOfAccount::create([
            'code'        => '6400',
            'name'        => 'Utility Expense',
            'type'        => 'expense',
            'normal_side' => 'debit',
            'is_active'   => true,
        ]);

        $this->retainedAcc = ChartOfAccount::create([
            'code'        => '3152',
            'name'        => 'Retained Earnings',
            'type'        => 'equity',
            'normal_side' => 'credit',
            'is_active'   => true,
        ]);

        $this->periodCloseService = app(PeriodCloseService::class);
        $this->journalEngine      = app(JournalEngine::class);
    }

    protected function tearDown(): void
    {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    /** @test */
    public function it_runs_period_close_checklist_and_reports_pass_on_balanced_period()
    {
        // Post a balanced journal in August
        $this->journalEngine->createEntry(
            'rental_income',
            101,
            'Rental Income August',
            [
                ['account_id' => $this->cashAcc->id, 'side' => 'debit',  'amount' => 50000.00],
                ['account_id' => $this->revAcc->id,  'side' => 'credit', 'amount' => 50000.00],
            ],
            $this->cfoUser->id,
            $this->cfoUser->id,
            '2026-08-15'
        );

        $checklistResult = $this->periodCloseService->runChecklist($this->periodAug, $this->cfoUser->id);

        $this->assertTrue($checklistResult['all_passed']);
        $this->assertEquals('passed', $checklistResult['checklist']['trial_balance_integrity']['status']);
        $this->assertEquals('passed', $checklistResult['checklist']['no_unposted_drafts']['status']);

        $persistedRows = PeriodClosingChecklist::where('financial_period_id', $this->periodAug->id)->count();
        $this->assertGreaterThanOrEqual(5, $persistedRows);
    }

    /** @test */
    public function it_successfully_locks_period_and_records_audit_trail()
    {
        $res = $this->periodCloseService->lockPeriod($this->periodAug, $this->cfoUser->id);

        $this->assertTrue($res['is_locked']);
        $this->periodAug->refresh();
        $this->assertTrue($this->periodAug->is_locked);
        $this->assertEquals('locked', $this->periodAug->status);

        $audit = FinancialAuditTrail::where('auditable_type', FinancialPeriod::class)
            ->where('auditable_id', $this->periodAug->id)
            ->where('action', 'PERIOD_LOCKED')
            ->first();

        $this->assertNotNull($audit);
        $this->assertEquals($this->cfoUser->id, $audit->user_id);
    }

    /** @test */
    public function database_trigger_blocks_inserting_journal_entries_into_locked_period()
    {
        $this->periodAug->update(['is_locked' => true, 'status' => 'locked']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->expectExceptionMessage('DB Hard Lock: Cannot create journal entry in locked financial period.');

        // Direct raw insert into locked date range
        DB::table('journal_entries')->insert([
            'entry_no'   => 'JE-TEST-LOCKED-001',
            'entry_date' => '2026-08-20', // Falls inside locked August
            'status'     => 'draft',
            'created_by' => $this->cfoUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @test */
    public function database_trigger_blocks_backdating_entries_into_prior_locked_period()
    {
        $this->periodAug->update(['is_locked' => true, 'status' => 'locked']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->expectExceptionMessage('DB Hard Lock: Cannot create journal entry in locked financial period.');

        // Attempting to backdate an entry into August while September is open
        DB::table('journal_entries')->insert([
            'entry_no'   => 'JE-BACKDATE-001',
            'entry_date' => '2026-08-10', // Prior locked period
            'status'     => 'draft',
            'created_by' => $this->cfoUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @test */
    public function database_trigger_blocks_updating_entries_in_locked_period()
    {
        // Create an entry before locking
        $jeId = DB::table('journal_entries')->insertGetId([
            'entry_no'   => 'JE-AUG-ORIG-001',
            'entry_date' => '2026-08-15',
            'status'     => 'draft',
            'created_by' => $this->cfoUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Lock August
        $this->periodAug->update(['is_locked' => true, 'status' => 'locked']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->expectExceptionMessage('DB Hard Lock: Cannot update journal entry in locked financial period.');

        // Direct raw update on entry in locked period
        DB::table('journal_entries')->where('id', $jeId)->update([
            'description' => 'Unauthorized alteration of locked period entry',
        ]);
    }

    /** @test */
    public function database_trigger_blocks_deleting_entries_in_locked_period()
    {
        // Create an entry before locking
        $jeId = DB::table('journal_entries')->insertGetId([
            'entry_no'   => 'JE-AUG-DEL-001',
            'entry_date' => '2026-08-18',
            'status'     => 'draft',
            'created_by' => $this->cfoUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Lock August
        $this->periodAug->update(['is_locked' => true, 'status' => 'locked']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->expectExceptionMessage('DB Hard Lock: Cannot delete journal entry in locked financial period.');

        // Raw SQL delete
        DB::table('journal_entries')->where('id', $jeId)->delete();
    }

    /** @test */
    public function unlocking_period_requires_cfo_role_and_written_justification()
    {
        $this->periodAug->update(['is_locked' => true, 'status' => 'locked']);

        // Unauthorized user attempts unlock
        try {
            $this->periodCloseService->unlockPeriod($this->periodAug, $this->staffUser->id, 'Need to make adjustments');
            $this->fail('Expected unauthorized exception was not thrown.');
        } catch (Exception $e) {
            $this->assertStringContainsString('Unauthorized', $e->getMessage());
        }

        // Insufficient reason length
        try {
            $this->periodCloseService->unlockPeriod($this->periodAug, $this->cfoUser->id, 'Short');
            $this->fail('Expected justification exception was not thrown.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Mandatory justification', $e->getMessage());
        }

        // Authorized CFO unlock
        $res = $this->periodCloseService->unlockPeriod(
            $this->periodAug,
            $this->cfoUser->id,
            'Authorized reopening by CFO for external audit adjustments.'
        );

        $this->assertTrue($res['success']);
        $this->periodAug->refresh();
        $this->assertFalse($this->periodAug->is_locked);
        $this->assertEquals('open', $this->periodAug->status);

        $audit = FinancialAuditTrail::where('action', 'PERIOD_UNLOCKED')->first();
        $this->assertNotNull($audit);
        $this->assertEquals($this->cfoUser->id, $audit->user_id);
    }

    /** @test */
    public function it_opens_period_13_and_posts_cfo_authorized_adjustments()
    {
        $p13 = $this->periodCloseService->openPeriod13(
            2026,
            $this->cfoUser->id,
            'Year-end external audit adjustments window for FY2026.'
        );

        $this->assertTrue($p13->is_period_13);
        $this->assertEquals('FP-2026-P13', $p13->code);

        // Lock the normal year periods
        $this->periodAug->update(['is_locked' => true, 'status' => 'locked']);

        // Post adjustment entry in Period 13
        $adjEntry = $this->periodCloseService->postPeriod13Adjustment(
            $p13,
            [
                ['account_id' => $this->expAcc->id,  'side' => 'debit',  'amount' => 5000.00, 'description' => 'Audit adjustment accrued utility'],
                ['account_id' => $this->cashAcc->id, 'side' => 'credit', 'amount' => 5000.00, 'description' => 'Audit adjustment credit bank'],
            ],
            $this->cfoUser->id,
            'Auditor finding AUD-2026-04 unrecorded utility expense adjustment.',
            'AUD-2026-04'
        );

        $this->assertNotNull($adjEntry);
        $this->assertEquals('posted', $adjEntry->status);
        $this->assertEquals('Period13Adjustment', $adjEntry->reference_type);

        $audit = FinancialAuditTrail::where('action', 'PERIOD_13_ADJUSTMENT_POSTED')->first();
        $this->assertNotNull($audit);
    }

    /** @test */
    public function year_end_close_strictly_requires_cfo_approval_and_does_not_auto_close_without_it()
    {
        // Record Revenue and Expense
        $this->journalEngine->createEntry(
            'operational',
            1,
            'Year Operations',
            [
                ['account_id' => $this->cashAcc->id, 'side' => 'debit',  'amount' => 150000.00],
                ['account_id' => $this->expAcc->id,  'side' => 'debit',  'amount' => 50000.00],
                ['account_id' => $this->revAcc->id,  'side' => 'credit', 'amount' => 200000.00],
            ],
            $this->cfoUser->id,
            $this->cfoUser->id,
            '2026-08-20'
        );

        // Scenario A: Auto-close blocked without CFO approval
        $draftResult = $this->periodCloseService->closeYearEnd(2026, $this->cfoUser->id, false, '');

        $this->assertEquals('pending_cfo_approval', $draftResult['status']);
        $this->assertTrue($draftResult['cfo_signature_required']);
        $this->assertEquals(200000.00, $draftResult['total_revenue']);
        $this->assertEquals(50000.00, $draftResult['total_expense']);
        $this->assertEquals(150000.00, $draftResult['net_operating_income']);

        // Verify NO closing journal was posted
        $closingEntryExists = JournalEntry::where('reference_type', 'YearEndClosing')->exists();
        $this->assertFalse($closingEntryExists, 'Closing journal must NOT be created without CFO approval.');

        // Scenario B: CFO Formally Approves with Signature Token
        $executedResult = $this->periodCloseService->closeYearEnd(
            2026,
            $this->cfoUser->id,
            true,
            'CFO-AUTH-SIG-2026-XYZ'
        );

        $this->assertEquals('completed', $executedResult['status']);
        $this->assertEquals(150000.00, $executedResult['retained_earnings_add']);

        $closeEntry = JournalEntry::where('reference_type', 'YearEndClosing')->first();
        $this->assertNotNull($closeEntry);
        $this->assertEquals('posted', $closeEntry->status);

        // Verify Retained Earnings was credited
        $retainedLine = $closeEntry->lines()->where('account_id', $this->retainedAcc->id)->first();
        $this->assertNotNull($retainedLine);
        $this->assertEquals('credit', $retainedLine->side);
        $this->assertEquals(150000.00, (float)$retainedLine->amount);

        // Verify Revenues and Expenses are now zeroed out
        $this->revAcc->refresh();
        $this->expAcc->refresh();
        $this->assertEquals(0.00, (float)$this->revAcc->ledger_balance);
        $this->assertEquals(0.00, (float)$this->expAcc->ledger_balance);

        // Verify Audit Trail recorded
        $audit = FinancialAuditTrail::where('action', 'YEAR_END_CLOSE_EXECUTED')->first();
        $this->assertNotNull($audit);
        $this->assertEquals($this->cfoUser->id, $audit->user_id);
    }

    /** @test */
    public function reversal_into_a_locked_period_is_blocked_by_application_and_trigger()
    {
        // 1. Post a legitimate entry in open August period
        $original = $this->journalEngine->createEntry(
            'operational',
            101,
            'Original August Transaction',
            [
                ['account_id' => $this->cashAcc->id, 'side' => 'debit',  'amount' => 50000.00],
                ['account_id' => $this->revAcc->id,  'side' => 'credit', 'amount' => 50000.00],
            ],
            $this->cfoUser->id,
            $this->cfoUser->id,
            '2026-08-10'
        );

        $this->assertEquals('posted', $original->status);

        // 2. Lock August period
        $this->periodAug->update(['is_locked' => true, 'status' => 'locked']);

        // 3. Attempt reversal backdated into the locked period (2026-08-15)
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('is locked');

        $this->journalEngine->reverseEntry(
            $original->id,
            $this->staffUser->id,
            'Attempted reversal into locked period',
            '2026-08-15'
        );
    }

    /** @test */
    public function lock_enforcement_blocks_posting_through_application_service_path()
    {
        // 1. Lock August period
        $this->periodAug->update(['is_locked' => true, 'status' => 'locked']);

        // 2. Attempt to create entry via JournalEngine application path into locked period
        $exceptionCaught = false;
        try {
            $this->journalEngine->createEntry(
                'vendor_invoice',
                999,
                'Invoice posted into locked period',
                [
                    ['account_id' => $this->expAcc->id,  'side' => 'debit',  'amount' => 25000.00],
                    ['account_id' => $this->cashAcc->id, 'side' => 'credit', 'amount' => 25000.00],
                ],
                $this->staffUser->id,
                $this->staffUser->id,
                '2026-08-14'
            );
        } catch (Exception $e) {
            $exceptionCaught = true;
            $this->assertStringContainsString('is locked', $e->getMessage());
        }

        $this->assertTrue($exceptionCaught, 'Application service must reject postings into locked periods.');

        // 3. Verify that zero entries or lines were created for that reference
        $entryExists = JournalEntry::where('reference_type', 'vendor_invoice')
            ->where('reference_id', 999)
            ->exists();
        $this->assertFalse($entryExists, 'No database records should be committed when application lock triggers.');
    }

    protected function ensureLockingTriggersExist(): void
    {
        DB::unprepared("DROP TRIGGER IF EXISTS trg_block_insert_journal_on_locked_period;");
        DB::unprepared("
            CREATE TRIGGER trg_block_insert_journal_on_locked_period
            BEFORE INSERT ON journal_entries
            FOR EACH ROW
            BEGIN
                DECLARE locked_count INT DEFAULT 0;
                DECLARE locked_period_code VARCHAR(50);

                IF @period_lock_override IS NULL OR @period_lock_override != 1 THEN
                    SELECT COUNT(*), MAX(code) INTO locked_count, locked_period_code
                    FROM financial_periods
                    WHERE is_locked = 1
                      AND NEW.entry_date BETWEEN start_date AND end_date;

                    IF locked_count > 0 THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'DB Hard Lock: Cannot create journal entry in locked financial period.';
                    END IF;
                END IF;
            END;
        ");

        DB::unprepared("DROP TRIGGER IF EXISTS trg_block_update_journal_on_locked_period;");
        DB::unprepared("
            CREATE TRIGGER trg_block_update_journal_on_locked_period
            BEFORE UPDATE ON journal_entries
            FOR EACH ROW
            BEGIN
                DECLARE locked_count INT DEFAULT 0;

                IF @period_lock_override IS NULL OR @period_lock_override != 1 THEN
                    SELECT COUNT(*) INTO locked_count
                    FROM financial_periods
                    WHERE is_locked = 1
                      AND (OLD.entry_date BETWEEN start_date AND end_date
                           OR NEW.entry_date BETWEEN start_date AND end_date);

                    IF locked_count > 0 THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'DB Hard Lock: Cannot update journal entry in locked financial period.';
                    END IF;
                END IF;
            END;
        ");

        DB::unprepared("DROP TRIGGER IF EXISTS trg_block_delete_journal_on_locked_period;");
        DB::unprepared("
            CREATE TRIGGER trg_block_delete_journal_on_locked_period
            BEFORE DELETE ON journal_entries
            FOR EACH ROW
            BEGIN
                DECLARE locked_count INT DEFAULT 0;

                IF @period_lock_override IS NULL OR @period_lock_override != 1 THEN
                    SELECT COUNT(*) INTO locked_count
                    FROM financial_periods
                    WHERE is_locked = 1
                      AND OLD.entry_date BETWEEN start_date AND end_date;

                    IF locked_count > 0 THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'DB Hard Lock: Cannot delete journal entry in locked financial period.';
                    END IF;
                END IF;
            END;
        ");

        DB::unprepared("DROP TRIGGER IF EXISTS trg_block_insert_lines_on_locked_period;");
        DB::unprepared("
            CREATE TRIGGER trg_block_insert_lines_on_locked_period
            BEFORE INSERT ON journal_entry_lines
            FOR EACH ROW
            BEGIN
                DECLARE parent_date DATE;
                DECLARE locked_count INT DEFAULT 0;

                IF @period_lock_override IS NULL OR @period_lock_override != 1 THEN
                    SELECT entry_date INTO parent_date
                    FROM journal_entries
                    WHERE id = NEW.journal_entry_id;

                    IF parent_date IS NOT NULL THEN
                        SELECT COUNT(*) INTO locked_count
                        FROM financial_periods
                        WHERE is_locked = 1
                          AND parent_date BETWEEN start_date AND end_date;

                        IF locked_count > 0 THEN
                            SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'DB Hard Lock: Cannot insert lines into locked financial period.';
                        END IF;
                    END IF;
                END IF;
            END;
        ");

        DB::unprepared("DROP TRIGGER IF EXISTS trg_block_update_lines_on_locked_period;");
        DB::unprepared("
            CREATE TRIGGER trg_block_update_lines_on_locked_period
            BEFORE UPDATE ON journal_entry_lines
            FOR EACH ROW
            BEGIN
                DECLARE parent_date DATE;
                DECLARE locked_count INT DEFAULT 0;

                IF @period_lock_override IS NULL OR @period_lock_override != 1 THEN
                    SELECT entry_date INTO parent_date
                    FROM journal_entries
                    WHERE id = OLD.journal_entry_id;

                    IF parent_date IS NOT NULL THEN
                        SELECT COUNT(*) INTO locked_count
                        FROM financial_periods
                        WHERE is_locked = 1
                          AND parent_date BETWEEN start_date AND end_date;

                        IF locked_count > 0 THEN
                            SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'DB Hard Lock: Cannot update lines in locked financial period.';
                        END IF;
                    END IF;
                END IF;
            END;
        ");

        DB::unprepared("DROP TRIGGER IF EXISTS trg_block_delete_lines_on_locked_period;");
        DB::unprepared("
            CREATE TRIGGER trg_block_delete_lines_on_locked_period
            BEFORE DELETE ON journal_entry_lines
            FOR EACH ROW
            BEGIN
                DECLARE parent_date DATE;
                DECLARE locked_count INT DEFAULT 0;

                IF @period_lock_override IS NULL OR @period_lock_override != 1 THEN
                    SELECT entry_date INTO parent_date
                    FROM journal_entries
                    WHERE id = OLD.journal_entry_id;

                    IF parent_date IS NOT NULL THEN
                        SELECT COUNT(*) INTO locked_count
                        FROM financial_periods
                        WHERE is_locked = 1
                          AND parent_date BETWEEN start_date AND end_date;

                        IF locked_count > 0 THEN
                            SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'DB Hard Lock: Cannot delete lines from locked financial period.';
                        END IF;
                    END IF;
                END IF;
            END;
        ");
    }
}
