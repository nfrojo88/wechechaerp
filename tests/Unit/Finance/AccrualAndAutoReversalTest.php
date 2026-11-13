<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\AccrualEntry;
use App\Models\FinancialPeriod;
use App\Models\User;
use App\Services\Finance\AccrualEngineService;
use App\Services\Finance\PayrollGlPostingService;
use App\Services\Finance\JournalEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Exception;

class AccrualAndAutoReversalTest extends TestCase
{
    protected AccrualEngineService $accrualService;
    protected PayrollGlPostingService $payrollService;
    protected JournalEngine $journalEngine;
    protected User $user;
    protected FinancialPeriod $currentPeriod;
    protected FinancialPeriod $nextPeriod;

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
            'email' => 'senior.accountant@wechecha.com',
            'role'  => 'senior_accountant',
        ]);

        // Seed accounts
        ChartOfAccount::create(['code' => '1260', 'name' => 'Work-In-Progress (WIP)', 'type' => 'asset', 'normal_side' => 'debit']);
        ChartOfAccount::create(['code' => '2255', 'name' => 'Accrued Subcontractor Costs', 'type' => 'liability', 'normal_side' => 'credit']);
        ChartOfAccount::create(['code' => '2050', 'name' => 'Legacy Other Payables', 'type' => 'liability', 'normal_side' => 'credit']);
        ChartOfAccount::create(['code' => '6100', 'name' => 'Basic Salary & Wages Expense', 'type' => 'expense', 'normal_side' => 'debit']);
        ChartOfAccount::create(['code' => '6300', 'name' => 'Employer Pension Contribution', 'type' => 'expense', 'normal_side' => 'debit']);
        ChartOfAccount::create(['code' => '2210', 'name' => 'PAYE Withholding Tax Payable', 'type' => 'liability', 'normal_side' => 'credit']);
        ChartOfAccount::create(['code' => '2220', 'name' => 'Pension Fund Contribution Payable', 'type' => 'liability', 'normal_side' => 'credit']);
        ChartOfAccount::create(['code' => '2006', 'name' => 'Net Salaries Payable', 'type' => 'liability', 'normal_side' => 'credit']);
        ChartOfAccount::create(['code' => '2251', 'name' => 'Accrued Salaries & Wages', 'type' => 'liability', 'normal_side' => 'credit']);
        ChartOfAccount::create(['code' => '6400', 'name' => 'Electricity & Water Utility Expense', 'type' => 'expense', 'normal_side' => 'debit']);
        ChartOfAccount::create(['code' => '2252', 'name' => 'Accrued Utilities', 'type' => 'liability', 'normal_side' => 'credit']);

        $this->currentPeriod = FinancialPeriod::create([
            'name'       => 'August 2026',
            'code'       => 'FP-2026-08',
            'start_date' => '2026-08-01',
            'end_date'   => '2026-08-31',
            'is_locked'  => false,
        ]);

        $this->nextPeriod = FinancialPeriod::create([
            'name'       => 'September 2026',
            'code'       => 'FP-2026-09',
            'start_date' => '2026-09-01',
            'end_date'   => '2026-09-30',
            'is_locked'  => false,
        ]);

        DB::table('pension_agency_configs')->insert([
            'agency_code'           => 'POESSA',
            'agency_name'           => 'Private Organisations Employees Social Security Agency',
            'employee_rate_percent' => 7.00,
            'employer_rate_percent' => 11.00,
            'sector'                => 'private',
            'is_active'             => true,
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);

        $this->journalEngine = new JournalEngine();
        $this->accrualService = new AccrualEngineService($this->journalEngine);
        $this->payrollService = new PayrollGlPostingService($this->journalEngine);
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
        $requiredTables = ['users', 'chart_of_accounts', 'financial_periods', 'journal_entries', 'journal_entry_lines', 'financial_audit_trails', 'accrual_entries', 'payroll_tax_brackets', 'pension_agency_configs'];
        $allExist = true;
        foreach ($requiredTables as $t) {
            if (!Schema::hasTable($t)) {
                $allExist = false;
                break;
            }
        }
        if ($allExist && Schema::hasColumn('journal_entries', 'is_reversal')) {
            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF;');
        } else {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        }

        Schema::dropIfExists('financial_audit_trails');
        Schema::dropIfExists('accrual_entries');
        Schema::dropIfExists('financial_periods');
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('chart_of_accounts');
        Schema::dropIfExists('payroll_tax_brackets');
        Schema::dropIfExists('pension_agency_configs');
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
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('financial_periods', function ($table) {
            $table->id();
            $table->string('name', 50);
            $table->string('code', 20)->unique();
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_locked')->default(false);
            $table->timestamp('locked_at')->nullable();
            $table->unsignedBigInteger('locked_by')->nullable();
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

        Schema::create('accrual_entries', function ($table) {
            $table->id();
            $table->unsignedBigInteger('financial_period_id');
            $table->string('accrual_type', 50);
            $table->unsignedBigInteger('journal_entry_id');
            $table->decimal('amount', 18, 2);
            $table->unsignedBigInteger('expense_account_id');
            $table->unsignedBigInteger('accrual_account_id');
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('cost_center_id')->nullable();
            $table->unsignedBigInteger('site_id')->nullable();
            $table->boolean('is_reversed')->default(false);
            $table->unsignedBigInteger('reversal_journal_entry_id')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->unsignedBigInteger('reversed_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('payroll_tax_brackets', function ($table) {
            $table->id();
            $table->decimal('min_income', 18, 2);
            $table->decimal('max_income', 18, 2)->nullable();
            $table->decimal('tax_rate_percent', 5, 2);
            $table->decimal('deduction_amount', 18, 2);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status', 30)->default('to_be_verified');
            $table->timestamps();
        });

        Schema::create('pension_agency_configs', function ($table) {
            $table->id();
            $table->string('agency_code', 20)->unique();
            $table->string('agency_name', 150);
            $table->decimal('employee_rate_percent', 5, 2)->default(7.00);
            $table->decimal('employer_rate_percent', 5, 2)->default(11.00);
            $table->string('status', 30)->default('to_be_verified');
            $table->timestamps();
        });
    }

    public function test_subcontractor_accrual_uses_dedicated_2255_and_never_2050()
    {
        $accrual = $this->accrualService->postAccrual(
            $this->currentPeriod,
            'subcontractor_unbilled',
            45000.00,
            $this->user->id,
            projectId: 301,
            costCenterId: 10,
            siteId: 5,
            notes: 'Unbilled excavation works package 4'
        );

        $this->assertNotNull($accrual);
        $je = $accrual->journalEntry;
        $this->assertNotNull($je);
        $this->assertEquals('posted', $je->status);

        $debitLine = $je->lines->where('side', 'debit')->first();
        $creditLine = $je->lines->where('side', 'credit')->first();

        // DR 1260 WIP (by project)
        $this->assertEquals('1260', $debitLine->account->code);
        $this->assertEquals(45000.00, (float) $debitLine->amount);
        $this->assertEquals(301, $debitLine->project_id);

        // CR 2255 Accrued Subcontractor Costs (strictly 2255, NOT 2050)
        $this->assertEquals('2255', $creditLine->account->code);
        $this->assertEquals(45000.00, (float) $creditLine->amount);
        $this->assertNotEquals('2050', $creditLine->account->code);

        // Confirm 2050 account has zero posted activity
        $legacyUsage = JournalEntryLine::whereHas('account', function($q) {
            $q->where('code', '2050');
        })->count();
        $this->assertEquals(0, $legacyUsage);
    }

    public function test_salary_accrual_allowed_only_for_unapproved_payroll_and_auto_reverses_when_payroll_posts()
    {
        // 1. Post unapproved salary accrual
        $accrual = $this->accrualService->postAccrual(
            $this->currentPeriod,
            'payroll_unapproved',
            100000.00,
            $this->user->id,
            notes: 'Estimated August payroll prior to timesheet approval'
        );

        $this->assertNotNull($accrual);
        $originalJe = $accrual->journalEntry;
        $this->assertNotNull($originalJe);
        $this->assertFalse($accrual->is_reversed);

        // Lines: DR 6100 / CR 2251
        $this->assertEquals('6100', $originalJe->lines->where('side', 'debit')->first()->account->code);
        $this->assertEquals('2251', $originalJe->lines->where('side', 'credit')->first()->account->code);

        // 2. Now approved payroll is posted via PayrollGlPostingService
        $approvedJe = $this->payrollService->postPayroll(
            'August 2026',
            100000.00,
            15000.00,
            7000.00,
            11000.00,
            'POESSA',
            $this->user->id,
            '2026-08-31'
        );

        $this->assertNotNull($approvedJe);

        // 3. Confirm that unapproved salary accrual was AUTO-REVERSED upon approved payroll posting!
        $accrual->refresh();
        $this->assertTrue($accrual->is_reversed);
        $this->assertNotNull($accrual->reversal_journal_entry_id);

        $reversalJe = JournalEntry::find($accrual->reversal_journal_entry_id);
        $this->assertNotNull($reversalJe);
        $this->assertTrue($reversalJe->is_reversal);
        $this->assertEquals($originalJe->id, $reversalJe->reversal_of_id);

        $originalJe->refresh();
        $this->assertEquals($reversalJe->id, $originalJe->reversed_by_id);

        // Reversal lines clear the 2251 liability: DR 2251 / CR 6100
        $revDebit = $reversalJe->lines->where('side', 'debit')->first();
        $revCredit = $reversalJe->lines->where('side', 'credit')->first();
        $this->assertEquals('2251', $revDebit->account->code);
        $this->assertEquals(100000.00, (float) $revDebit->amount);
        $this->assertEquals('6100', $revCredit->account->code);
        $this->assertEquals(100000.00, (float) $revCredit->amount);

        // 4. If payroll is already posted, attempting another unapproved salary accrual returns null
        $secondAccrual = $this->accrualService->postAccrual(
            $this->currentPeriod,
            'payroll_unapproved',
            50000.00,
            $this->user->id
        );
        $this->assertNull($secondAccrual);
    }

    public function test_auto_reversals_on_day_one_of_subsequent_open_period()
    {
        // Create an unbilled subcontractor accrual and a utilities accrual in August 2026
        $subAccrual = $this->accrualService->postAccrual(
            $this->currentPeriod,
            'subcontractor_unbilled',
            25000.00,
            $this->user->id,
            projectId: 302
        );

        $utilAccrual = $this->accrualService->postAccrual(
            $this->currentPeriod,
            'utilities',
            8000.00,
            $this->user->id,
            costCenterId: 12
        );

        $this->assertNotNull($subAccrual);
        $this->assertNotNull($utilAccrual);

        // Execute auto-reversals into September 2026 (subsequent period)
        $reversals = $this->accrualService->reversePeriodAccruals(
            $this->currentPeriod,
            $this->nextPeriod,
            $this->user->id
        );

        $this->assertCount(2, $reversals);

        foreach ($reversals as $revJe) {
            $this->assertEquals('2026-09-01', \Illuminate\Support\Carbon::parse($revJe->entry_date)->format('Y-m-d')); // Day 1 of September
            $this->assertTrue($revJe->is_reversal);
            $this->assertNotNull($revJe->reversal_of_id);
            $this->assertEquals('posted', $revJe->status);
        }

        // Subcontractor reversal clears 2255
        $subAccrual->refresh();
        $this->assertTrue($subAccrual->is_reversed);
        $subRevJe = JournalEntry::find($subAccrual->reversal_journal_entry_id);
        $this->assertEquals('2255', $subRevJe->lines->where('side', 'debit')->first()->account->code);
        $this->assertEquals(25000.00, (float) $subRevJe->lines->where('side', 'debit')->first()->amount);

        // Idempotency: Running reversal again returns empty array
        $secondRun = $this->accrualService->reversePeriodAccruals(
            $this->currentPeriod,
            $this->nextPeriod,
            $this->user->id
        );
        $this->assertCount(0, $secondRun);
    }

    public function test_auto_reversal_fails_if_subsequent_period_is_locked()
    {
        $this->nextPeriod->update(['is_locked' => true]);

        $this->accrualService->postAccrual(
            $this->currentPeriod,
            'utilities',
            5000.00,
            $this->user->id
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Cannot post auto-reversal to locked subsequent period");

        $this->accrualService->reversePeriodAccruals(
            $this->currentPeriod,
            $this->nextPeriod,
            $this->user->id
        );
    }
}
