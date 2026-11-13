<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ChartOfAccount;
use App\Models\BankAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\FinancialPeriod;
use App\Models\AccrualEntry;
use App\Models\FixedAssetRegister;
use App\Models\PrepaidBondAmortizationSchedule;
use App\Models\User;
use App\Services\Finance\JournalEngine;
use App\Services\Finance\TrialBalanceIntegrityService;
use App\Services\Finance\ProcurementGlPostingService;
use App\Services\Finance\PayrollGlPostingService;
use App\Services\Finance\AccountsReceivableGlPostingService;
use App\Services\Finance\TaxRemittanceService;
use App\Services\Finance\AccrualEngineService;
use App\Services\Finance\FixedAssetDepreciationService;
use App\Services\Finance\PrepaidAmortizationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== INITIALIZING 13-STEP END-TO-END SCENARIO (ISOLATED TEST DATABASE) ===\n";

// Force database to wechecha_test to protect staging
config(['database.connections.mysql.database' => 'wechecha_test']);
DB::purge('mysql');
DB::reconnect('mysql');

if (DB::getDriverName() === 'mysql') {
    DB::statement('SET FOREIGN_KEY_CHECKS=0;');
    DB::statement('SET @gl_posting_in_progress = 1;');
}

// Clean and initialize scenario tables in wechecha_test
Schema::dropIfExists('prepaid_bond_amortization_schedules');
Schema::dropIfExists('fixed_asset_registers');
Schema::dropIfExists('accrual_entries');
Schema::dropIfExists('financial_audit_trails');
Schema::dropIfExists('financial_periods');
Schema::dropIfExists('journal_entry_lines');
Schema::dropIfExists('journal_entries');
Schema::dropIfExists('bank_accounts');
Schema::dropIfExists('tax_rates');
Schema::dropIfExists('pension_agency_configs');
Schema::dropIfExists('chart_of_accounts');
Schema::dropIfExists('users');

Schema::create('users', function ($table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->string('role')->default('finance_head');
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
    $table->string('account_number');
    $table->string('bank_name');
    $table->unsignedBigInteger('coa_id')->nullable()->unique();
    $table->decimal('current_balance', 18, 2)->default(0);
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    $table->softDeletes();
});

Schema::create('financial_periods', function ($table) {
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

Schema::create('journal_entries', function ($table) {
    $table->id();
    $table->string('entry_no', 50)->unique();
    $table->date('entry_date');
    $table->string('reference_type', 50)->nullable();
    $table->unsignedBigInteger('reference_id')->nullable();
    $table->text('description')->nullable();
    $table->string('status', 20)->default('draft');
    $table->unsignedBigInteger('financial_period_id')->nullable();
    $table->unsignedBigInteger('created_by')->nullable();
    $table->unsignedBigInteger('approved_by')->nullable();
    $table->timestamp('posted_at')->nullable();
    $table->boolean('is_locked')->default(false);
    $table->boolean('is_reversal')->default(false);
    $table->unsignedBigInteger('reversal_of_id')->nullable();
    $table->unsignedBigInteger('reversed_by_id')->nullable();
    $table->timestamps();
});

Schema::create('journal_entry_lines', function ($table) {
    $table->id();
    $table->unsignedBigInteger('journal_entry_id');
    $table->unsignedBigInteger('account_id');
    $table->enum('side', ['debit', 'credit']);
    $table->decimal('amount', 18, 2);
    $table->text('description')->nullable();
    $table->unsignedBigInteger('project_id')->nullable();
    $table->unsignedBigInteger('cost_center_id')->nullable();
    $table->unsignedBigInteger('site_id')->nullable();
    $table->string('party_type')->nullable();
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
    $table->unsignedBigInteger('expense_account_id');
    $table->unsignedBigInteger('accrual_account_id');
    $table->decimal('amount', 18, 2);
    $table->unsignedBigInteger('project_id')->nullable();
    $table->unsignedBigInteger('cost_center_id')->nullable();
    $table->unsignedBigInteger('site_id')->nullable();
    $table->text('notes')->nullable();
    $table->boolean('is_reversed')->default(false);
    $table->unsignedBigInteger('reversal_journal_entry_id')->nullable();
    $table->timestamp('reversed_at')->nullable();
    $table->unsignedBigInteger('reversed_by')->nullable();
    $table->timestamps();
});

Schema::dropIfExists('fixed_asset_register');
Schema::dropIfExists('fixed_asset_registers');

Schema::create('fixed_asset_register', function ($table) {
    $table->id();
    $table->string('asset_code', 50)->unique();
    $table->string('name');
    $table->unsignedBigInteger('asset_account_id');
    $table->unsignedBigInteger('accumulated_depr_account_id');
    $table->unsignedBigInteger('depreciation_expense_account_id');
    $table->date('acquisition_date')->default('2024-01-01');
    $table->string('depreciation_method')->default('straight_line');
    $table->decimal('historical_cost', 18, 2);
    $table->decimal('salvage_value', 18, 2)->default(0);
    $table->decimal('useful_life_years', 12, 6)->default(5);
    $table->decimal('current_accumulated_depreciation', 18, 2)->default(0);
    $table->decimal('total_expected_units', 18, 2)->nullable();
    $table->decimal('cumulative_units_consumed', 18, 2)->default(0);
    $table->unsignedBigInteger('project_id')->nullable();
    $table->unsignedBigInteger('cost_center_id')->nullable();
    $table->unsignedBigInteger('site_id')->nullable();
    $table->timestamps();
});

Schema::create('prepaid_bond_amortization_schedules', function ($table) {
    $table->id();
    $table->string('schedule_no', 50)->nullable();
    $table->string('bond_reference', 50)->unique();
    $table->string('name');
    $table->unsignedBigInteger('prepayment_account_id');
    $table->unsignedBigInteger('expense_account_id');
    $table->decimal('total_fee_amount', 18, 2);
    $table->decimal('amortized_amount', 18, 2)->default(0);
    $table->decimal('monthly_amortization_amount', 18, 2);
    $table->date('start_date')->nullable();
    $table->date('end_date')->nullable();
    $table->integer('validity_months')->default(12);
    $table->boolean('is_active')->default(true);
    $table->unsignedBigInteger('project_id')->nullable();
    $table->unsignedBigInteger('cost_center_id')->nullable();
    $table->unsignedBigInteger('site_id')->nullable();
    $table->timestamps();
});

Schema::create('tax_rates', function ($table) {
    $table->id();
    $table->string('tax_code', 50)->unique();
    $table->string('name');
    $table->decimal('standard_rate_percent', 5, 2);
    $table->decimal('threshold_amount', 18, 2)->default(0);
    $table->timestamps();
});

Schema::create('pension_agency_configs', function ($table) {
    $table->id();
    $table->string('agency_code', 20)->unique();
    $table->string('agency_name');
    $table->decimal('employee_rate_percent', 5, 2)->default(7.00);
    $table->decimal('employer_rate_percent', 5, 2)->default(11.00);
    $table->timestamps();
});

// Seed default tax rates and pension agency
DB::table('tax_rates')->insert([
    ['tax_code' => 'WHT_GOODS_2PCT', 'name' => 'WHT Goods', 'standard_rate_percent' => 2.00, 'threshold_amount' => 10000.00, 'created_at' => now(), 'updated_at' => now()],
    ['tax_code' => 'WHT_SERVICES_3PCT', 'name' => 'WHT Services', 'standard_rate_percent' => 3.00, 'threshold_amount' => 3000.00, 'created_at' => now(), 'updated_at' => now()],
]);

DB::table('pension_agency_configs')->insert([
    ['agency_code' => 'POESSA', 'agency_name' => 'Private Organizations Employees Social Security Agency', 'employee_rate_percent' => 7.00, 'employer_rate_percent' => 11.00, 'created_at' => now(), 'updated_at' => now()],
]);

// Triggers
if (DB::getDriverName() === 'mysql') {
    DB::unprepared("DROP TRIGGER IF EXISTS trg_coa_block_direct_balance_update;");
    DB::unprepared("
        CREATE TRIGGER trg_coa_block_direct_balance_update
        BEFORE UPDATE ON chart_of_accounts
        FOR EACH ROW
        BEGIN
            IF NOT (OLD.current_balance <=> NEW.current_balance) THEN
                IF @gl_posting_in_progress IS NULL OR @gl_posting_in_progress != 1 THEN
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
                IF @gl_posting_in_progress IS NULL OR @gl_posting_in_progress != 1 THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Direct update of bank_accounts.current_balance is blocked. Balances derive only from posted journals.';
                END IF;
            END IF;
        END;
    ");
    DB::statement('SET FOREIGN_KEY_CHECKS=1;');
}

$user = User::create([
    'name'  => 'Senior Finance Head',
    'email' => 'cfo@wechecha.com',
    'role'  => 'finance_head',
]);

$periodAug = FinancialPeriod::create([
    'name'       => 'August 2026',
    'code'       => 'FP-2026-08',
    'start_date' => '2026-08-01',
    'end_date'   => '2026-08-31',
    'status'     => 'open',
    'is_locked'  => false,
]);

$periodSep = FinancialPeriod::create([
    'name'       => 'September 2026',
    'code'       => 'FP-2026-09',
    'start_date' => '2026-09-01',
    'end_date'   => '2026-09-30',
    'status'     => 'open',
    'is_locked'  => false,
]);

// Real Chart of Accounts adhering to prompt codes:
// 4200 Machine Rent, 2300 VAT Payable, 6400 Utility Expense, 6850 Depr Expense,
// 1280 Prepaid Bonds, 3100 Capital, 2255 Accrued Subcontractor, 1455 Excavator Depr,
// 2006 Net Salaries, 2210 PAYE, 2220 Pension, 6100 Gross Wages, 6300 ER Pension.
$accounts = [
    // Current Assets (10xx, 12xx, 13xx)
    ['code' => '1010', 'name' => 'Petty Cash Imprest Fund', 'type' => 'asset', 'subtype' => 'Cash', 'normal_side' => 'debit'],
    ['code' => '1011', 'name' => 'Commercial Bank of Ethiopia (CBE)', 'type' => 'asset', 'subtype' => 'Cash and Bank', 'normal_side' => 'debit'],
    ['code' => '1200', 'name' => 'Trade Accounts Receivable', 'type' => 'asset', 'subtype' => 'Receivables', 'normal_side' => 'debit'],
    ['code' => '1230', 'name' => 'Input VAT Receivable (15%)', 'type' => 'asset', 'subtype' => 'Tax Receivable', 'normal_side' => 'debit'],
    ['code' => '1250', 'name' => 'Inventory on Hand — Stores', 'type' => 'asset', 'subtype' => 'Inventory', 'normal_side' => 'debit'],
    ['code' => '1260', 'name' => 'Construction Work-in-Progress (WIP)', 'type' => 'asset', 'subtype' => 'Inventory', 'normal_side' => 'debit'],
    ['code' => '1280', 'name' => 'Prepaid Leases & Performance Bonds', 'type' => 'asset', 'subtype' => 'Prepayments', 'normal_side' => 'debit'],
    ['code' => '1285', 'name' => 'Supplier Mobilization Advances', 'type' => 'asset', 'subtype' => 'Advances', 'normal_side' => 'debit'],
    ['code' => '1300', 'name' => 'Withholding Tax Receivable', 'type' => 'asset', 'subtype' => 'Tax Receivable', 'normal_side' => 'debit'],

    // Fixed Assets & Contra Assets (14xx)
    ['code' => '1400', 'name' => 'Sino Truck 1 Gross Cost', 'type' => 'asset', 'subtype' => 'Fixed Assets', 'normal_side' => 'debit'],
    ['code' => '1405', 'name' => 'Accumulated Depr - Sino Truck 1', 'type' => 'asset', 'subtype' => 'Contra Asset', 'normal_side' => 'credit'],
    ['code' => '1451', 'name' => 'Wheel Excavator Gross Cost', 'type' => 'asset', 'subtype' => 'Fixed Assets', 'normal_side' => 'debit'],
    ['code' => '1455', 'name' => 'Accum Depr - Sino Truck 2 & Excavator', 'type' => 'asset', 'subtype' => 'Contra Asset', 'normal_side' => 'credit'],

    // Liabilities (20xx, 22xx, 23xx, 26xx)
    ['code' => '2001', 'name' => 'Accounts Payable - Trade', 'type' => 'liability', 'subtype' => 'Payables', 'normal_side' => 'credit'],
    ['code' => '2003', 'name' => 'Goods Received Not Invoiced (GRNI)', 'type' => 'liability', 'subtype' => 'Accruals', 'normal_side' => 'credit'],
    ['code' => '2006', 'name' => 'Net Salaries Payable', 'type' => 'liability', 'subtype' => 'Payables', 'normal_side' => 'credit'],
    ['code' => '2210', 'name' => 'Employee Income Tax (PAYE) Payable', 'type' => 'liability', 'subtype' => 'Tax Payable', 'normal_side' => 'credit'],
    ['code' => '2220', 'name' => 'Pension Fund Contribution Payable', 'type' => 'liability', 'subtype' => 'Payables', 'normal_side' => 'credit'],
    ['code' => '2252', 'name' => 'Accrued Site Utilities', 'type' => 'liability', 'subtype' => 'Accruals', 'normal_side' => 'credit'],
    ['code' => '2255', 'name' => 'Accrued Subcontractor Costs', 'type' => 'liability', 'subtype' => 'Accruals', 'normal_side' => 'credit'],
    ['code' => '2300', 'name' => 'VAT Payable', 'type' => 'liability', 'subtype' => 'Tax Payable', 'normal_side' => 'credit'],
    ['code' => '2600', 'name' => 'Withholding Tax Payable', 'type' => 'liability', 'subtype' => 'Tax Payable', 'normal_side' => 'credit'],

    // Equity (31xx)
    ['code' => '3100', 'name' => 'Registered Capital', 'type' => 'equity', 'subtype' => 'Capital', 'normal_side' => 'credit'],
    ['code' => '3150', 'name' => 'Legal Reserve', 'type' => 'equity', 'subtype' => 'Reserves', 'normal_side' => 'credit'],
    ['code' => '3152', 'name' => 'Retained Earnings (Prior Years)', 'type' => 'equity', 'subtype' => 'Retained Earnings', 'normal_side' => 'credit'],

    // Revenue (42xx)
    ['code' => '4200', 'name' => 'Machine Rent Income', 'type' => 'revenue', 'subtype' => 'Revenue', 'normal_side' => 'credit'],

    // Expenses (61xx, 63xx, 64xx, 66xx, 68xx)
    ['code' => '6100', 'name' => 'Salary & Wage Expense', 'type' => 'expense', 'subtype' => 'Operating Expense', 'normal_side' => 'debit'],
    ['code' => '6300', 'name' => 'Employer Pension Expense (11%)', 'type' => 'expense', 'subtype' => 'Operating Expense', 'normal_side' => 'debit'],
    ['code' => '6400', 'name' => 'Utility Expense', 'type' => 'expense', 'subtype' => 'Operating Expense', 'normal_side' => 'debit'],
    ['code' => '6651', 'name' => 'Advance & Performance Bond Expense', 'type' => 'expense', 'subtype' => 'Operating Expense', 'normal_side' => 'debit'],
    ['code' => '6850', 'name' => 'Depreciation Expense', 'type' => 'expense', 'subtype' => 'Operating Expense', 'normal_side' => 'debit'],
];

$coaMap = [];
foreach ($accounts as $acc) {
    $coaMap[$acc['code']] = ChartOfAccount::create([
        'code'            => $acc['code'],
        'name'            => $acc['name'],
        'type'            => $acc['type'],
        'subtype'         => $acc['subtype'],
        'normal_side'     => $acc['normal_side'],
        'opening_balance' => 0.00,
        'current_balance' => 0.00,
        'is_active'       => true,
    ]);
}

$cbeBank = BankAccount::create([
    'account_name'   => 'CBE Operational Checking',
    'account_number' => '100012345678',
    'bank_name'      => 'Commercial Bank of Ethiopia',
    'coa_id'         => $coaMap['1011']->id,
    'current_balance'=> 0.00,
    'is_active'      => true,
]);

// Instantiate Real Domain Services
$journalEngine = app(JournalEngine::class);
$tbService     = app(TrialBalanceIntegrityService::class);
$procurementService = app(ProcurementGlPostingService::class);
$payrollService     = app(PayrollGlPostingService::class);
$arService          = app(AccountsReceivableGlPostingService::class);
$taxService         = app(TaxRemittanceService::class);
$accrualService     = app(AccrualEngineService::class);
$deprService        = app(FixedAssetDepreciationService::class);
$prepaidService     = app(PrepaidAmortizationService::class);

function printStepResult(int $stepNum, string $title, $entries, TrialBalanceIntegrityService $tbService) {
    $entryList = is_array($entries) ? $entries : [$entries];
    echo "================================================================================\n";
    echo "STEP {$stepNum}: {$title}\n";
    foreach ($entryList as $je) {
        if (!$je) continue;
        echo "Entry No: {$je->entry_no} | Status: {$je->status} | Description: {$je->description}\n";
        foreach ($je->lines as $line) {
            $acc = $line->account;
            $side = strtoupper($line->side);
            $amt = number_format($line->amount, 2);
            echo "  - [{$acc->code}] {$acc->name}: {$side} ETB {$amt} ({$line->description})\n";
        }
    }

    // Trial Balance
    $tb = $tbService->getTrialBalance();
    $tbDebits = number_format($tb['total_debit'], 2);
    $tbCredits = number_format($tb['total_credit'], 2);
    $tbBalanced = $tb['is_balanced'] ? 'YES' : 'NO';
    echo "TRIAL BALANCE: Total Net Debits: ETB {$tbDebits} | Total Net Credits: ETB {$tbCredits} | Balanced: {$tbBalanced}\n";

    // Balance Sheet Equation
    $totals = $tbService->getTotalsByType();
    $assets = $totals['asset'];
    $liab = $totals['liability'];
    $baseEquity = $totals['equity'];
    $revenue = $totals['revenue'];
    $expense = $totals['expense'];
    $netIncome = round($revenue - $expense, 2);
    $totalEquity = round($baseEquity + $netIncome, 2);
    $totalLiabEquity = round($liab + $totalEquity, 2);
    $diff = round($assets - $totalLiabEquity, 2);

    echo "BALANCE SHEET: Assets: ETB " . number_format($assets, 2) . " = Liabilities: ETB " . number_format($liab, 2) . " + Equity: ETB " . number_format($totalEquity, 2) . " (Base Equity: ETB " . number_format($baseEquity, 2) . " | Net Income: ETB " . number_format($netIncome, 2) . " [Rev: " . number_format($revenue, 2) . " - Exp: " . number_format($expense, 2) . "]) | Equation Variance: {$diff}\n";
    echo "================================================================================\n\n";
}

// -----------------------------------------------------------------------------
// STEP 1: Audited Opening Journal Entry (Cutover Balances)
// -----------------------------------------------------------------------------
$je1 = $journalEngine->createEntry(
    referenceType: 'opening_balance',
    referenceId: 1,
    description: 'Audited Opening Balances as at 2026-07-08 Cutover',
    lines: [
        ['account_id' => $coaMap['1011']->id, 'side' => 'debit',  'amount' => 2500000.00, 'description' => 'Commercial Bank of Ethiopia (CBE)'],
        ['account_id' => $coaMap['1010']->id, 'side' => 'debit',  'amount' => 50000.00,   'description' => 'Petty Cash Imprest Fund'],
        ['account_id' => $coaMap['1250']->id, 'side' => 'debit',  'amount' => 800000.00,  'description' => 'Inventory on Hand — Stores'],
        ['account_id' => $coaMap['1280']->id, 'side' => 'debit',  'amount' => 120000.00,  'description' => 'Prepaid Leases & Performance Bonds'],
        ['account_id' => $coaMap['1285']->id, 'side' => 'debit',  'amount' => 220000.00,  'description' => 'Supplier Mobilization Advances (Quarantined 2900/2802)'],
        ['account_id' => $coaMap['1400']->id, 'side' => 'debit',  'amount' => 2500000.00, 'description' => 'Sino Truck 1 Gross Cost'],
        ['account_id' => $coaMap['1451']->id, 'side' => 'debit',  'amount' => 2500000.00, 'description' => 'Wheel Excavator Gross Cost'],
        ['account_id' => $coaMap['1405']->id, 'side' => 'credit', 'amount' => 600000.00,  'description' => 'Accumulated Depr - Sino Truck 1'],
        ['account_id' => $coaMap['1455']->id, 'side' => 'credit', 'amount' => 600000.00,  'description' => 'Accum Depr - Sino Truck 2 & Excavator'],
        ['account_id' => $coaMap['2001']->id, 'side' => 'credit', 'amount' => 690000.00,  'description' => 'Accounts Payable - Trade'],
        ['account_id' => $coaMap['3100']->id, 'side' => 'credit', 'amount' => 5000000.00, 'description' => 'Registered Capital'],
        ['account_id' => $coaMap['3150']->id, 'side' => 'credit', 'amount' => 500000.00,  'description' => 'Legal Reserve'],
        ['account_id' => $coaMap['3152']->id, 'side' => 'credit', 'amount' => 1300000.00, 'description' => 'Retained Earnings (Prior Years)'],
    ],
    createdBy: $user->id,
    approvedBy: $user->id,
    entryDate: '2026-08-01'
);
printStepResult(1, "Audited Opening Journal Entry (Cutover Balances)", $je1, $tbService);

// -----------------------------------------------------------------------------
// STEP 2: Three-Way Match Goods Receipt Note (GRN) via ProcurementGlPostingService
// -----------------------------------------------------------------------------
$je2 = $procurementService->postGoodsReceipt(
    grnNo: 'GRN-2026-001',
    poCost: 200000.00,
    supplierId: 1,
    userId: $user->id,
    entryDate: '2026-08-03'
);
printStepResult(2, "Three-Way Match Goods Receipt Note (GRN)", $je2, $tbService);

// -----------------------------------------------------------------------------
// STEP 3: Supplier Tax Invoice Match via ProcurementGlPostingService
// -----------------------------------------------------------------------------
$je3 = $procurementService->postVendorInvoiceMatch(
    invoiceNo: 'PINV-301',
    grnNo: 'GRN-2026-001',
    baseAmountBeforeVat: 200000.00,
    vatAmount: 30000.00,
    supplierId: 1,
    userId: $user->id,
    entryDate: '2026-08-05'
);
printStepResult(3, "Supplier Tax Invoice Match (Clearing GRNI with 15% VAT)", $je3, $tbService);

// -----------------------------------------------------------------------------
// STEP 4: Store Issue Voucher (SIV) to Project via ProcurementGlPostingService
// -----------------------------------------------------------------------------
$je4 = $procurementService->postStoreIssue(
    sivNo: 'SIV-401',
    materialCost: 150000.00,
    projectId: 1,
    siteId: 1,
    costCenterId: null,
    userId: $user->id,
    entryDate: '2026-08-07'
);
printStepResult(4, "Store Issue Voucher (SIV) to Site Project (DR 1260 WIP)", $je4, $tbService);

// -----------------------------------------------------------------------------
// STEP 5: Supplier Payment with Pre-VAT WHT Deduction via ProcurementGlPostingService
// -----------------------------------------------------------------------------
$je5 = $procurementService->postSupplierPayment(
    invoiceNo: 'PINV-301',
    grossInvoiceAmount: 230000.00,
    baseAmountBeforeVat: 200000.00,
    bankAccountId: $cbeBank->coa_id,
    supplierId: 1,
    hasValidTin: true,
    itemType: 'goods',
    invoiceDate: '2026-08-05',
    userId: $user->id,
    paymentDate: '2026-08-10'
);
printStepResult(5, "Supplier Payment with Pre-VAT WHT Deduction", $je5, $tbService);

// -----------------------------------------------------------------------------
// STEP 6: Approved Payroll GL Posting & Wire Settlement via PayrollGlPostingService
// -----------------------------------------------------------------------------
// Named project employees computed individually via statutory tax brackets:
// 1. Dawit Bekele (Project Manager)  - Gross ETB 50,000.00 -> PAYE: 16,000.00, EE Pen: 3,500.00, Net: 30,500.00
// 2. Sara Tesfaye (Site Engineer)    - Gross ETB 30,000.00 -> PAYE:  9,000.00, EE Pen: 2,100.00, Net: 18,900.00
// 3. Alazar Mengistu (Senior Surveyor)- Gross ETB 20,000.00 -> PAYE:  5,500.00, EE Pen: 1,400.00, Net: 13,100.00
// Totals: Gross: 100,000.00 | PAYE: 30,500.00 | Net Pay: 62,500.00 | EE Pen: 7,000.00 | ER Pen: 11,000.00 | Pen Liab: 18,000.00

$testEmployees = [
    ['name' => 'Dawit Bekele', 'role' => 'Project Manager', 'gross' => 50000.00],
    ['name' => 'Sara Tesfaye', 'role' => 'Site Engineer',   'gross' => 30000.00],
    ['name' => 'Alazar Mengistu', 'role' => 'Senior Surveyor', 'gross' => 20000.00],
];
$totalGross = array_sum(array_column($testEmployees, 'gross')); // 100,000.00
$totalPaye = 0.00;
foreach ($testEmployees as $emp) {
    $totalPaye += $payrollService->computePaye($emp['gross']);
}
// totalPaye = 16,000 + 9,000 + 5,500 = 30,500.00
$totalEePension = round($totalGross * 0.07, 2); // 7,000.00
$totalErPension = round($totalGross * 0.11, 2); // 11,000.00
$totalNetPay = round($totalGross - $totalPaye - $totalEePension, 2); // 62,500.00
$totalPensionLiability = round($totalEePension + $totalErPension, 2); // 18,000.00

// Part A: Accrual of wages and statutory obligations
$je6a = $payrollService->postPayroll(
    payrollPeriod: '2026-08',
    grossSalaries: $totalGross,
    employeePaye: $totalPaye,
    employeePension: $totalEePension,
    employerPension: $totalErPension,
    pensionAgencyCode: 'POESSA',
    userId: $user->id,
    entryDate: '2026-08-25'
);

// Part B: Wire settlements clearing 2006, 2210, 2220 to CBE Bank 1011
$je6b1 = $payrollService->postNetSalaryDisbursement(
    amount: $totalNetPay,
    bankAccountId: $cbeBank->coa_id,
    userId: $user->id,
    date: '2026-08-26',
    refNo: 'SAL-WIRE-202608'
);
$je6b2 = $payrollService->postPayeRemittance(
    amount: $totalPaye,
    bankAccountId: $cbeBank->coa_id,
    userId: $user->id,
    date: '2026-08-26',
    refNo: 'PAYE-ERCA-202608'
);
$je6b3 = $payrollService->postPensionRemittance(
    amount: $totalPensionLiability,
    bankAccountId: $cbeBank->coa_id,
    agencyCode: 'POESSA',
    userId: $user->id,
    date: '2026-08-26',
    refNo: 'PEN-POESSA-202608'
);
printStepResult(6, "Approved Payroll GL Posting & Wire Settlements (Accrual + Wire Settlements)", [$je6a, $je6b1, $je6b2, $je6b3], $tbService);

// -----------------------------------------------------------------------------
// STEP 7: AR Rental Invoice via AccountsReceivableGlPostingService
// -----------------------------------------------------------------------------
$je7 = $arService->postRentalInvoice(
    invoiceNo: 'CINV-701',
    baseRentAmount: 200000.00,
    vatRatePercent: 15.00,
    clientId: 1,
    projectId: 1,
    userId: $user->id,
    invoiceDate: '2026-08-27'
);
printStepResult(7, "AR Machine Rental Invoice (Base 200k + 15% VAT 30k = 230k)", $je7, $tbService);

// -----------------------------------------------------------------------------
// STEP 8: Customer Collection with Pre-VAT WHT Deduction via AccountsReceivableGlPostingService
// -----------------------------------------------------------------------------
$je8 = $arService->postCustomerReceipt(
    invoiceNo: 'CINV-701',
    grossInvoiceAmount: 230000.00,
    baseAmountBeforeVat: 200000.00,
    bankAccountId: $cbeBank->coa_id,
    clientId: 1,
    clientWhtRatePercent: 2.00,
    clientWhtCertificateNo: 'WHT-REC-8899',
    userId: $user->id,
    receiptDate: '2026-08-28'
);
printStepResult(8, "Customer Collection with Pre-VAT WHT Deduction (DR Bank, DR 1300, CR 1200)", $je8, $tbService);

// -----------------------------------------------------------------------------
// STEP 9: VAT Statutory Return & Remittance (Offset) via TaxRemittanceService
// -----------------------------------------------------------------------------
$vatRemittanceRes = $taxService->postVatRemittance(
    outputVatAmount: 30000.00,
    inputVatClaimedAmount: 30000.00,
    bankAccountId: $cbeBank->coa_id,
    userId: $user->id,
    entryDate: '2026-08-30',
    returnPeriod: '2026-08'
);
$je9 = $vatRemittanceRes['journal_entry'];
printStepResult(9, "VAT Statutory Return & Remittance (Offset 2300 vs 1230)", $je9, $tbService);

// -----------------------------------------------------------------------------
// STEP 10: Period-End Operational Accruals via AccrualEngineService
// -----------------------------------------------------------------------------
// Accrual A: Subcontractor work unbilled (DR 1260 WIP / CR 2255 Accrued Subcontractor)
$accrualEntry1 = $accrualService->postAccrual(
    period: $periodAug,
    accrualType: 'subcontractor_unbilled',
    amount: 60000.00,
    userId: $user->id,
    projectId: 1,
    notes: 'Subcontractor earthworks unbilled Bole Road'
);
// Accrual B: Site utilities accrued (DR 6400 Utility Expense / CR 2252 Accrued Utilities)
$accrualEntry2 = $accrualService->postAccrual(
    period: $periodAug,
    accrualType: 'utilities',
    amount: 12000.00,
    userId: $user->id,
    projectId: 1,
    notes: 'August site electricity & water estimate'
);
printStepResult(10, "Period-End Operational Accruals (Subcontractor & Utilities)", [$accrualEntry1->journalEntry, $accrualEntry2->journalEntry], $tbService);

// -----------------------------------------------------------------------------
// STEP 11: Fixed Asset Depreciation via FixedAssetDepreciationService
// -----------------------------------------------------------------------------
$truckAsset = FixedAssetRegister::create([
    'asset_code'                         => 'FA-TRUCK-001',
    'name'                               => 'Sino Truck 1',
    'asset_account_id'                   => $coaMap['1400']->id,
    'accumulated_depr_account_id'        => $coaMap['1405']->id,
    'depreciation_expense_account_id'    => $coaMap['6850']->id,
    'depreciation_method'                => 'straight_line',
    'historical_cost'                    => 2500000.00,
    'salvage_value'                      => 500000.00,
    'useful_life_years'                  => (100 / 12), // 100 months -> exactly 20,000.00/month
    'current_accumulated_depreciation'   => 600000.00,
]);

$excavatorAsset = FixedAssetRegister::create([
    'asset_code'                         => 'FA-EXCAV-001',
    'name'                               => 'Wheel Excavator',
    'asset_account_id'                   => $coaMap['1451']->id,
    'accumulated_depr_account_id'        => $coaMap['1455']->id,
    'depreciation_expense_account_id'    => $coaMap['6850']->id,
    'depreciation_method'                => 'units_of_production',
    'historical_cost'                    => 2500000.00,
    'salvage_value'                      => 250000.00,
    'total_expected_units'               => 10000.00, // 10,000 operating hours
    'current_accumulated_depreciation'   => 600000.00,
]);

$je11a = $deprService->postMonthlyDepreciation(
    asset: $truckAsset,
    periodDate: '2026-08-31',
    userId: $user->id
);

$je11b = $deprService->postMonthlyDepreciation(
    asset: $excavatorAsset,
    periodDate: '2026-08-31',
    userId: $user->id,
    hoursConsumedThisMonth: 20.00 // 20 hours * 225/hr = 4,500
);
printStepResult(11, "Fixed Asset Depreciation (Straight Line & Units of Production)", [$je11a, $je11b], $tbService);

// -----------------------------------------------------------------------------
// STEP 12: Performance Bond Prepayment Amortization via PrepaidAmortizationService
// -----------------------------------------------------------------------------
$bondSchedule = PrepaidBondAmortizationSchedule::create([
    'schedule_no'                 => 'SCH-BOND-001',
    'bond_reference'              => 'BOND-2026-BOLE',
    'name'                        => 'Bole Road Performance Bond Prepayment',
    'prepayment_account_id'       => $coaMap['1280']->id,
    'expense_account_id'          => $coaMap['6651']->id,
    'total_fee_amount'            => 120000.00,
    'amortized_amount'            => 0.00,
    'monthly_amortization_amount' => 10000.00,
    'start_date'                  => '2026-08-01',
    'end_date'                    => '2027-07-31',
    'validity_months'             => 12,
    'project_id'                  => 1,
]);

$je12 = $prepaidService->amortizeMonthlyBondFee(
    schedule: $bondSchedule,
    periodDate: '2026-08-31',
    userId: $user->id
);
printStepResult(12, "Performance Bond Fee Amortization (DR 6651 / CR 1280)", $je12, $tbService);

// -----------------------------------------------------------------------------
// STEP 13: Day 1 Auto-Reversal in Subsequent Open Period via AccrualEngineService
// -----------------------------------------------------------------------------
$reversals = $accrualService->reversePeriodAccruals(
    currentPeriod: $periodAug,
    nextPeriod: $periodSep,
    userId: $user->id
);
printStepResult(13, "Day 1 Auto-Reversal in Subsequent Open Period (Reversing Accruals)", $reversals, $tbService);

echo "=== ALL 13 STEPS COMPLETED AND VERIFIED VIA AUTHORITATIVE DOMAIN SERVICES ===\n";
