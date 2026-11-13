<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\User;
use App\Services\Finance\PayrollGlPostingService;
use App\Services\Finance\JournalEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PayrollGlPostingTest extends TestCase
{
    protected PayrollGlPostingService $payrollService;
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

        DB::table('pension_agency_configs')->insert([
            'agency_code'           => 'POESSA',
            'agency_name'           => 'Private Organizations Employees Social Security Agency',
            'sector'                => 'private',
            'employee_rate_percent' => 7.00,
            'employer_rate_percent' => 11.00,
            'total_rate_percent'    => 18.00,
            'is_active'             => true,
            'is_default_target'     => true,
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);

        DB::table('payroll_tax_brackets')->insert([
            ['bracket_name' => 'Bracket 1 (0%)',   'min_income' => 0.00,    'max_income' => 600.00,   'tax_rate_percent' => 0.00,  'deduction_amount' => 0.00,    'status' => 'to_be_verified', 'effective_from' => '2026-07-08', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['bracket_name' => 'Bracket 2 (10%)',  'min_income' => 601.00,  'max_income' => 1650.00,  'tax_rate_percent' => 10.00, 'deduction_amount' => 60.00,   'status' => 'to_be_verified', 'effective_from' => '2026-07-08', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['bracket_name' => 'Bracket 3 (15%)',  'min_income' => 1651.00, 'max_income' => 3200.00,  'tax_rate_percent' => 15.00, 'deduction_amount' => 142.50,  'status' => 'to_be_verified', 'effective_from' => '2026-07-08', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['bracket_name' => 'Bracket 4 (20%)',  'min_income' => 3201.00, 'max_income' => 5250.00,  'tax_rate_percent' => 20.00, 'deduction_amount' => 302.50,  'status' => 'to_be_verified', 'effective_from' => '2026-07-08', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['bracket_name' => 'Bracket 5 (25%)',  'min_income' => 5251.00, 'max_income' => 7800.00,  'tax_rate_percent' => 25.00, 'deduction_amount' => 565.00,  'status' => 'to_be_verified', 'effective_from' => '2026-07-08', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['bracket_name' => 'Bracket 6 (30%)',  'min_income' => 7801.00, 'max_income' => 10900.00, 'tax_rate_percent' => 30.00, 'deduction_amount' => 955.00,  'status' => 'to_be_verified', 'effective_from' => '2026-07-08', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['bracket_name' => 'Bracket 7 (35%)',  'min_income' => 10901.00,'max_income' => null,     'tax_rate_percent' => 35.00, 'deduction_amount' => 1500.00, 'status' => 'to_be_verified', 'effective_from' => '2026-07-08', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->user = User::create(['name' => 'HR Payroll', 'email' => 'payroll@wechecha.com']);
        $this->payrollService = new PayrollGlPostingService();
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
        $requiredTables = ['users', 'chart_of_accounts', 'journal_entries', 'journal_entry_lines', 'financial_periods', 'financial_audit_trails', 'payroll_tax_brackets', 'pension_agency_configs'];
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
        Schema::dropIfExists('payroll_tax_brackets');
        Schema::dropIfExists('pension_agency_configs');
        Schema::dropIfExists('users');

        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('role')->default('payroll_officer');
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
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('cost_center_id')->nullable();
            $table->unsignedBigInteger('site_id')->nullable();
            $table->string('party_type', 50)->nullable();
            $table->unsignedBigInteger('party_id')->nullable();
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

        Schema::create('payroll_tax_brackets', function ($table) {
            $table->id();
            $table->string('bracket_name', 50);
            $table->decimal('min_income', 18, 2);
            $table->decimal('max_income', 18, 2)->nullable();
            $table->decimal('tax_rate_percent', 5, 2);
            $table->decimal('deduction_amount', 18, 2)->default(0.00);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status', 30)->default('to_be_verified');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('payroll_tax_brackets')->insert([
            ['bracket_name' => 'Bracket 1 (0%)',   'min_income' => 0.00,    'max_income' => 600.00,   'tax_rate_percent' => 0.00,  'deduction_amount' => 0.00,    'status' => 'to_be_verified', 'effective_from' => '2026-07-08', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['bracket_name' => 'Bracket 2 (10%)',  'min_income' => 601.00,  'max_income' => 1650.00,  'tax_rate_percent' => 10.00, 'deduction_amount' => 60.00,   'status' => 'to_be_verified', 'effective_from' => '2026-07-08', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['bracket_name' => 'Bracket 3 (15%)',  'min_income' => 1651.00, 'max_income' => 3200.00,  'tax_rate_percent' => 15.00, 'deduction_amount' => 142.50,  'status' => 'to_be_verified', 'effective_from' => '2026-07-08', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['bracket_name' => 'Bracket 4 (20%)',  'min_income' => 3201.00, 'max_income' => 5250.00,  'tax_rate_percent' => 20.00, 'deduction_amount' => 302.50,  'status' => 'to_be_verified', 'effective_from' => '2026-07-08', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['bracket_name' => 'Bracket 5 (25%)',  'min_income' => 5251.00, 'max_income' => 7800.00,  'tax_rate_percent' => 25.00, 'deduction_amount' => 565.00,  'status' => 'to_be_verified', 'effective_from' => '2026-07-08', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['bracket_name' => 'Bracket 6 (30%)',  'min_income' => 7801.00, 'max_income' => 10900.00, 'tax_rate_percent' => 30.00, 'deduction_amount' => 955.00,  'status' => 'to_be_verified', 'effective_from' => '2026-07-08', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['bracket_name' => 'Bracket 7 (35%)',  'min_income' => 10901.00,'max_income' => null,     'tax_rate_percent' => 35.00, 'deduction_amount' => 1500.00, 'status' => 'to_be_verified', 'effective_from' => '2026-07-08', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        Schema::create('pension_agency_configs', function ($table) {
            $table->id();
            $table->string('agency_code', 50)->unique();
            $table->string('agency_name', 150);
            $table->string('sector', 20)->default('private');
            $table->decimal('employee_rate_percent', 5, 2)->default(7.00);
            $table->decimal('employer_rate_percent', 5, 2)->default(11.00);
            $table->decimal('total_rate_percent', 5, 2)->default(18.00);
            $table->string('bank_account_number', 50)->nullable();
            $table->string('bank_name', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default_target')->default(true);
            $table->timestamps();
        });
    }

    public function test_payroll_posting_generates_balanced_journal_with_gross_pension_paye_and_net_salaries()
    {
        $grossAcc     = ChartOfAccount::create(['code' => '6100', 'name' => 'Salary & Wage Expense', 'type' => 'expense', 'normal_side' => 'debit']);
        $erPensionAcc = ChartOfAccount::create(['code' => '6300', 'name' => 'Pension Fund Contribution (11%)', 'type' => 'expense', 'normal_side' => 'debit']);
        $payeAcc      = ChartOfAccount::create(['code' => '2210', 'name' => 'PAYE Payable', 'type' => 'liability', 'normal_side' => 'credit']);
        $pensionLiab  = ChartOfAccount::create(['code' => '2220', 'name' => 'Pension Fund Contribution Payable', 'type' => 'liability', 'normal_side' => 'credit']);
        $netPayAcc    = ChartOfAccount::create(['code' => '2006', 'name' => 'Net Salaries Payable', 'type' => 'liability', 'normal_side' => 'credit']);

        // Suppose October Payroll:
        // Gross Salaries = ETB 500,000
        // Employer Pension (11%) = ETB 55,000
        // Employee Pension (7%) = ETB 35,000
        // Total Pension (18%) = ETB 90,000
        // Employee PAYE Withholding = ETB 65,000
        // Net Pay = 500,000 - 35,000 - 65,000 = ETB 400,000
        $je = $this->payrollService->postPayroll(
            '2026-10',
            500000.00,
            65000.00,
            35000.00,
            55000.00,
            'POESSA',
            $this->user->id,
            '2026-10-31'
        );

        $this->assertEquals('posted', $je->status);
        $lines = $je->lines;
        $this->assertCount(5, $lines);

        // Debits: Gross Wages (500,000) + Employer Pension (55,000) = 555,000
        $grossLine = $lines->where('side', 'debit')->firstWhere('account_id', $grossAcc->id);
        $this->assertEquals(500000.00, $grossLine->amount);

        $erPensionLine = $lines->where('side', 'debit')->firstWhere('account_id', $erPensionAcc->id);
        $this->assertEquals(55000.00, $erPensionLine->amount);

        // Credits: PAYE (65,000) + Total Pension (90,000) + Net Pay (400,000) = 555,000
        $payeLine = $lines->where('side', 'credit')->firstWhere('account_id', $payeAcc->id);
        $this->assertEquals(65000.00, $payeLine->amount);

        $pensionLine = $lines->where('side', 'credit')->firstWhere('account_id', $pensionLiab->id);
        $this->assertEquals(90000.00, $pensionLine->amount);

        $netPayLine = $lines->where('side', 'credit')->firstWhere('account_id', $netPayAcc->id);
        $this->assertEquals(400000.00, $netPayLine->amount);

        // Assert Ledger Balances
        $this->assertEquals(500000.00, $grossAcc->fresh()->ledger_balance);
        $this->assertEquals(55000.00, $erPensionAcc->fresh()->ledger_balance);
        $this->assertEquals(65000.00, $payeAcc->fresh()->ledger_balance);
        $this->assertEquals(90000.00, $pensionLiab->fresh()->ledger_balance);
        $this->assertEquals(400000.00, $netPayAcc->fresh()->ledger_balance);
    }

    public function test_payroll_remittances_clear_liabilities_to_zero_via_bank()
    {
        $cbeAcc      = ChartOfAccount::create(['code' => '1011', 'name' => 'CBE Main Checking', 'type' => 'asset', 'normal_side' => 'debit']);
        $payeAcc     = ChartOfAccount::create(['code' => '2210', 'name' => 'PAYE Payable', 'type' => 'liability', 'normal_side' => 'credit']);
        $pensionLiab = ChartOfAccount::create(['code' => '2220', 'name' => 'Pension Fund Contribution Payable', 'type' => 'liability', 'normal_side' => 'credit']);
        $netPayAcc   = ChartOfAccount::create(['code' => '2006', 'name' => 'Net Salaries Payable', 'type' => 'liability', 'normal_side' => 'credit']);

        // 1. Remit PAYE (65,000)
        $this->payrollService->postPayeRemittance(65000.00, $cbeAcc->id, $this->user->id, '2026-11-05', 'ERCA-PAYE-OCT26');
        $this->assertEquals(-65000.00, $payeAcc->fresh()->ledger_balance); // Debit clearing prior credit

        // 2. Remit Pension to POESSA (90,000)
        $this->payrollService->postPensionRemittance(90000.00, $cbeAcc->id, 'POESSA', $this->user->id, '2026-11-05', 'POESSA-OCT26');
        $this->assertEquals(-90000.00, $pensionLiab->fresh()->ledger_balance);

        // 3. Disburse Net Pay to Employees via Bank (400,000)
        $this->payrollService->postNetSalaryDisbursement(400000.00, $cbeAcc->id, $this->user->id, '2026-11-01', 'BULK-SAL-OCT26');
        $this->assertEquals(-400000.00, $netPayAcc->fresh()->ledger_balance);
    }

    public function test_bracket_calculation_and_posting_for_salary_500_bracket1_zero_tax()
    {
        // Salary ETB 500.00 falls in Bracket 1 (0 - 600 ETB, 0% tax rate)
        $computedPaye = $this->payrollService->computePaye(500.00);
        $this->assertEquals(0.00, $computedPaye, 'Salary <= 600 ETB must yield 0 PAYE tax');

        $grossAcc     = ChartOfAccount::create(['code' => '6100', 'name' => 'Salary & Wage Expense', 'type' => 'expense', 'normal_side' => 'debit']);
        $erPensionAcc = ChartOfAccount::create(['code' => '6300', 'name' => 'Pension Fund Contribution (11%)', 'type' => 'expense', 'normal_side' => 'debit']);
        $payeAcc      = ChartOfAccount::create(['code' => '2210', 'name' => 'PAYE Payable', 'type' => 'liability', 'normal_side' => 'credit']);
        $pensionLiab  = ChartOfAccount::create(['code' => '2220', 'name' => 'Pension Fund Contribution Payable', 'type' => 'liability', 'normal_side' => 'credit']);
        $netPayAcc    = ChartOfAccount::create(['code' => '2006', 'name' => 'Net Salaries Payable', 'type' => 'liability', 'normal_side' => 'credit']);

        $je = $this->payrollService->postPayroll('2026-08-LOW', 500.00, null, null, null, 'POESSA', $this->user->id, '2026-08-31');
        $this->assertEquals('posted', $je->status);

        // Lines: Gross (500), ER Pen (55), PAYE (0), Pension Liab (35 + 55 = 90), Net Pay (465)
        $lines = $je->lines;
        $this->assertEquals(500.00, $lines->firstWhere('account_id', $grossAcc->id)->amount);
        $this->assertEquals(55.00,  $lines->firstWhere('account_id', $erPensionAcc->id)->amount);
        $this->assertEquals(0.00,   $lines->firstWhere('account_id', $payeAcc->id)->amount);
        $this->assertEquals(90.00,  $lines->firstWhere('account_id', $pensionLiab->id)->amount);
        $this->assertEquals(465.00, $lines->firstWhere('account_id', $netPayAcc->id)->amount);
    }

    public function test_bracket_calculation_and_posting_for_salary_5000_bracket4_exact_deduction()
    {
        // Salary ETB 5,000.00 falls in Bracket 4 (3,201 - 5,250 ETB, 20% tax rate, 302.50 deduction)
        // Formula: (5,000 * 20%) - 302.50 = 1,000.00 - 302.50 = ETB 697.50
        $computedPaye = $this->payrollService->computePaye(5000.00);
        $this->assertEquals(697.50, $computedPaye, 'Salary 5,000 ETB must yield exactly 697.50 PAYE');

        $grossAcc     = ChartOfAccount::create(['code' => '6100', 'name' => 'Salary & Wage Expense', 'type' => 'expense', 'normal_side' => 'debit']);
        $erPensionAcc = ChartOfAccount::create(['code' => '6300', 'name' => 'Pension Fund Contribution (11%)', 'type' => 'expense', 'normal_side' => 'debit']);
        $payeAcc      = ChartOfAccount::create(['code' => '2210', 'name' => 'PAYE Payable', 'type' => 'liability', 'normal_side' => 'credit']);
        $pensionLiab  = ChartOfAccount::create(['code' => '2220', 'name' => 'Pension Fund Contribution Payable', 'type' => 'liability', 'normal_side' => 'credit']);
        $netPayAcc    = ChartOfAccount::create(['code' => '2006', 'name' => 'Net Salaries Payable', 'type' => 'liability', 'normal_side' => 'credit']);

        $je = $this->payrollService->postPayroll('2026-08-MID', 5000.00, null, null, null, 'POESSA', $this->user->id, '2026-08-31');
        $this->assertEquals('posted', $je->status);

        // Lines: Gross (5000), ER Pen (550), PAYE (697.50), Pension Liab (350 + 550 = 900), Net Pay (3952.50)
        $lines = $je->lines;
        $this->assertEquals(5000.00, $lines->firstWhere('account_id', $grossAcc->id)->amount);
        $this->assertEquals(550.00,  $lines->firstWhere('account_id', $erPensionAcc->id)->amount);
        $this->assertEquals(697.50,  $lines->firstWhere('account_id', $payeAcc->id)->amount);
        $this->assertEquals(900.00,  $lines->firstWhere('account_id', $pensionLiab->id)->amount);
        $this->assertEquals(3952.50, $lines->firstWhere('account_id', $netPayAcc->id)->amount);
    }

    public function test_bracket_calculation_and_posting_for_salary_12000_bracket7_top_rate()
    {
        // Salary ETB 12,000.00 falls in Bracket 7 (> 10,900 ETB, 35% tax rate, 1,500.00 deduction)
        // Formula: (12,000 * 35%) - 1,500.00 = 4,200.00 - 1,500.00 = ETB 2,700.00
        $computedPaye = $this->payrollService->computePaye(12000.00);
        $this->assertEquals(2700.00, $computedPaye, 'Salary 12,000 ETB must yield exactly 2,700.00 PAYE');

        $grossAcc     = ChartOfAccount::create(['code' => '6100', 'name' => 'Salary & Wage Expense', 'type' => 'expense', 'normal_side' => 'debit']);
        $erPensionAcc = ChartOfAccount::create(['code' => '6300', 'name' => 'Pension Fund Contribution (11%)', 'type' => 'expense', 'normal_side' => 'debit']);
        $payeAcc      = ChartOfAccount::create(['code' => '2210', 'name' => 'PAYE Payable', 'type' => 'liability', 'normal_side' => 'credit']);
        $pensionLiab  = ChartOfAccount::create(['code' => '2220', 'name' => 'Pension Fund Contribution Payable', 'type' => 'liability', 'normal_side' => 'credit']);
        $netPayAcc    = ChartOfAccount::create(['code' => '2006', 'name' => 'Net Salaries Payable', 'type' => 'liability', 'normal_side' => 'credit']);

        $je = $this->payrollService->postPayroll('2026-08-HIGH', 12000.00, null, null, null, 'POESSA', $this->user->id, '2026-08-31');
        $this->assertEquals('posted', $je->status);

        // Lines: Gross (12000), ER Pen (1320), PAYE (2700), Pension Liab (840 + 1320 = 2160), Net Pay (8460)
        $lines = $je->lines;
        $this->assertEquals(12000.00, $lines->firstWhere('account_id', $grossAcc->id)->amount);
        $this->assertEquals(1320.00,  $lines->firstWhere('account_id', $erPensionAcc->id)->amount);
        $this->assertEquals(2700.00,  $lines->firstWhere('account_id', $payeAcc->id)->amount);
        $this->assertEquals(2160.00,  $lines->firstWhere('account_id', $pensionLiab->id)->amount);
        $this->assertEquals(8460.00,  $lines->firstWhere('account_id', $netPayAcc->id)->amount);
    }
}
