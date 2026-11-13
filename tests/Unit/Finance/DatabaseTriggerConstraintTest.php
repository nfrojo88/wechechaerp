<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\QueryException;

class DatabaseTriggerConstraintTest extends TestCase
{
    protected User $user;
    protected ChartOfAccount $cashAccount;
    protected ChartOfAccount $apAccount;

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
        $this->cleanTestData();

        $this->user = User::create([
            'name'  => 'Test Auditor',
            'email' => 'auditor@wechecha.com',
            'role'  => 'auditor',
        ]);

        $this->cashAccount = ChartOfAccount::create([
            'code'        => '1011',
            'name'        => 'CBE Cash Account',
            'type'        => 'asset',
            'normal_side' => 'debit',
        ]);

        $this->apAccount = ChartOfAccount::create([
            'code'        => '2001',
            'name'        => 'Accounts Payable',
            'type'        => 'liability',
            'normal_side' => 'credit',
        ]);
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
            DB::statement('TRUNCATE TABLE journal_entry_lines;');
            DB::statement('TRUNCATE TABLE journal_entries;');
            DB::statement('TRUNCATE TABLE chart_of_accounts;');
            DB::statement('TRUNCATE TABLE users;');
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        } else {
            if (Schema::hasTable('journal_entry_lines')) {
                DB::table('journal_entry_lines')->delete();
            }
            if (Schema::hasTable('journal_entries')) {
                DB::table('journal_entries')->delete();
            }
            if (Schema::hasTable('chart_of_accounts')) {
                DB::table('chart_of_accounts')->delete();
            }
            if (Schema::hasTable('users')) {
                DB::table('users')->delete();
            }
        }
    }

    protected function ensureSchema(): void
    {
        $requiredTables = ['users', 'chart_of_accounts', 'journal_entries', 'journal_entry_lines', 'financial_periods', 'financial_audit_trails'];
        $allExist = true;
        foreach ($requiredTables as $t) {
            if (!Schema::hasTable($t)) {
                $allExist = false;
                break;
            }
        }

        if ($allExist) {
            $triggerExists = DB::select("SHOW TRIGGERS LIKE 'trg_journal_entries_prevent_unbalanced_post'");
            if (!empty($triggerExists)) {
                return;
            }
            $migration = require base_path('database/migrations/2026_11_09_140000_create_finance_database_constraints_and_audit_columns.php');
            $migration->up();
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

        // Instantiate and run the trigger migration
        $migration = require base_path('database/migrations/2026_11_09_140000_create_finance_database_constraints_and_audit_columns.php');
        $migration->up();
    }

    public function test_database_trigger_blocks_posting_unbalanced_journal_entry_via_direct_sql()
    {
        // 1. Insert draft entry via raw DB query (bypassing PHP JournalEngine)
        $entryId = DB::table('journal_entries')->insertGetId([
            'entry_no'   => 'JE-RAW-UNBALANCED-01',
            'entry_date' => '2026-11-09',
            'created_by' => $this->user->id,
            'status'     => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert unbalanced lines directly: Debit 1000, Credit 600
        DB::table('journal_entry_lines')->insert([
            [
                'journal_entry_id' => $entryId,
                'account_id'       => $this->cashAccount->id,
                'side'             => 'debit',
                'amount'           => 1000.00,
                'created_at'       => now(),
                'updated_at'       => now(),
            ],
            [
                'journal_entry_id' => $entryId,
                'account_id'       => $this->apAccount->id,
                'side'             => 'credit',
                'amount'           => 600.00,
                'created_at'       => now(),
                'updated_at'       => now(),
            ],
        ]);

        // 2. Direct raw SQL UPDATE to status = 'posted' must be rejected BY THE DATABASE ENGINE TRIGGER
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('DB Constraint Violation: Cannot post unbalanced journal entry');

        DB::statement("UPDATE journal_entries SET status = 'posted' WHERE id = ?", [$entryId]);
    }

    public function test_database_trigger_blocks_posting_one_legged_journal_entry_via_direct_sql()
    {
        $entryId = DB::table('journal_entries')->insertGetId([
            'entry_no'   => 'JE-RAW-ONE-LEGGED-01',
            'entry_date' => '2026-11-09',
            'created_by' => $this->user->id,
            'status'     => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Only 1 line inserted
        DB::table('journal_entry_lines')->insert([
            'journal_entry_id' => $entryId,
            'account_id'       => $this->cashAccount->id,
            'side'             => 'debit',
            'amount'           => 5000.00,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('DB Constraint Violation: Cannot post unbalanced journal entry');

        DB::statement("UPDATE journal_entries SET status = 'posted' WHERE id = ?", [$entryId]);
    }

    public function test_database_trigger_blocks_modifying_lines_of_posted_journal_entry_via_direct_sql()
    {
        $entryId = DB::table('journal_entries')->insertGetId([
            'entry_no'   => 'JE-RAW-BALANCED-01',
            'entry_date' => '2026-11-09',
            'created_by' => $this->user->id,
            'status'     => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $line1Id = DB::table('journal_entry_lines')->insertGetId([
            'journal_entry_id' => $entryId,
            'account_id'       => $this->cashAccount->id,
            'side'             => 'debit',
            'amount'           => 1500.00,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        DB::table('journal_entry_lines')->insert([
            'journal_entry_id' => $entryId,
            'account_id'       => $this->apAccount->id,
            'side'             => 'credit',
            'amount'           => 1500.00,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        // Post the balanced journal directly via SQL - this succeeds
        DB::statement("UPDATE journal_entries SET status = 'posted' WHERE id = ?", [$entryId]);

        // Now attempt raw SQL UPDATE on the posted line - DB trigger must abort!
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('DB Constraint Violation: Lines of a posted journal entry are immutable and cannot be updated');

        DB::statement("UPDATE journal_entry_lines SET amount = 9999.00 WHERE id = ?", [$line1Id]);
    }

    public function test_database_trigger_blocks_deleting_lines_of_posted_journal_entry_via_direct_sql()
    {
        $entryId = DB::table('journal_entries')->insertGetId([
            'entry_no'   => 'JE-RAW-BALANCED-02',
            'entry_date' => '2026-11-09',
            'created_by' => $this->user->id,
            'status'     => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $lineId = DB::table('journal_entry_lines')->insertGetId([
            'journal_entry_id' => $entryId,
            'account_id'       => $this->cashAccount->id,
            'side'             => 'debit',
            'amount'           => 2000.00,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        DB::table('journal_entry_lines')->insert([
            'journal_entry_id' => $entryId,
            'account_id'       => $this->apAccount->id,
            'side'             => 'credit',
            'amount'           => 2000.00,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        DB::statement("UPDATE journal_entries SET status = 'posted' WHERE id = ?", [$entryId]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('DB Constraint Violation: Lines of a posted journal entry are immutable and cannot be deleted');

        DB::statement("DELETE FROM journal_entry_lines WHERE id = ?", [$lineId]);
    }

    public function test_database_trigger_allows_posting_when_debits_strictly_equal_credits()
    {
        $entryId = DB::table('journal_entries')->insertGetId([
            'entry_no'   => 'JE-RAW-BALANCED-03',
            'entry_date' => '2026-11-09',
            'created_by' => $this->user->id,
            'status'     => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('journal_entry_lines')->insert([
            [
                'journal_entry_id' => $entryId,
                'account_id'       => $this->cashAccount->id,
                'side'             => 'debit',
                'amount'           => 4500.00,
                'created_at'       => now(),
                'updated_at'       => now(),
            ],
            [
                'journal_entry_id' => $entryId,
                'account_id'       => $this->apAccount->id,
                'side'             => 'credit',
                'amount'           => 4500.00,
                'created_at'       => now(),
                'updated_at'       => now(),
            ],
        ]);

        // This direct SQL statement must execute successfully without throwing QueryException
        DB::statement("UPDATE journal_entries SET status = 'posted' WHERE id = ?", [$entryId]);

        $status = DB::table('journal_entries')->where('id', $entryId)->value('status');
        $this->assertEquals('posted', $status);
    }

    public function test_database_trigger_blocks_direct_insert_of_posted_header()
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('DB Constraint Violation: Cannot insert journal entry directly as posted');

        DB::table('journal_entries')->insert([
            'entry_no'   => 'JE-DIRECT-POSTED-FAIL',
            'entry_date' => '2026-11-09',
            'created_by' => $this->user->id,
            'status'     => 'posted', // Direct posted insert without drafting and lines
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_database_trigger_blocks_updating_posted_journal_entry_header()
    {
        $entryId = DB::table('journal_entries')->insertGetId([
            'entry_no'   => 'JE-HEADER-IMMUTABLE-01',
            'entry_date' => '2026-11-09',
            'created_by' => $this->user->id,
            'status'     => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('journal_entry_lines')->insert([
            [
                'journal_entry_id' => $entryId,
                'account_id'       => $this->cashAccount->id,
                'side'             => 'debit',
                'amount'           => 1000.00,
                'created_at'       => now(),
                'updated_at'       => now(),
            ],
            [
                'journal_entry_id' => $entryId,
                'account_id'       => $this->apAccount->id,
                'side'             => 'credit',
                'amount'           => 1000.00,
                'created_at'       => now(),
                'updated_at'       => now(),
            ],
        ]);

        DB::statement("UPDATE journal_entries SET status = 'posted' WHERE id = ?", [$entryId]);

        // Attempting to revert status to 'draft' or mutate header date must be blocked by trigger
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('DB Constraint Violation: Posted journal entries cannot be modified');

        DB::statement("UPDATE journal_entries SET status = 'draft' WHERE id = ?", [$entryId]);
    }

    public function test_database_trigger_blocks_deleting_posted_journal_entry_header()
    {
        $entryId = DB::table('journal_entries')->insertGetId([
            'entry_no'   => 'JE-HEADER-IMMUTABLE-02',
            'entry_date' => '2026-11-09',
            'created_by' => $this->user->id,
            'status'     => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('journal_entry_lines')->insert([
            [
                'journal_entry_id' => $entryId,
                'account_id'       => $this->cashAccount->id,
                'side'             => 'debit',
                'amount'           => 3000.00,
                'created_at'       => now(),
                'updated_at'       => now(),
            ],
            [
                'journal_entry_id' => $entryId,
                'account_id'       => $this->apAccount->id,
                'side'             => 'credit',
                'amount'           => 3000.00,
                'created_at'       => now(),
                'updated_at'       => now(),
            ],
        ]);

        DB::statement("UPDATE journal_entries SET status = 'posted' WHERE id = ?", [$entryId]);

        // Deleting the posted header must be blocked by database trigger
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('DB Constraint Violation: Posted journal entries are immutable and cannot be deleted');

        DB::statement("DELETE FROM journal_entries WHERE id = ?", [$entryId]);
    }
}
