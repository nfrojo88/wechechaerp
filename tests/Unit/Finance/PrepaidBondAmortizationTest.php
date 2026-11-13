<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\PrepaidBondAmortizationSchedule;
use App\Models\FinancialPeriod;
use App\Models\User;
use App\Services\Finance\PrepaidAmortizationService;
use App\Services\Finance\JournalEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PrepaidBondAmortizationTest extends TestCase
{
    protected PrepaidAmortizationService $amortizationService;
    protected JournalEngine $journalEngine;
    protected User $user;
    protected ChartOfAccount $prepaidAccount;
    protected ChartOfAccount $expenseAccount;

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
            'name'  => 'Finance Officer',
            'email' => 'finance.officer@wechecha.com',
            'role'  => 'finance_officer',
        ]);

        $this->prepaidAccount = ChartOfAccount::create([
            'code' => '1280', 'name' => 'Prepaid Leases & Performance Bonds', 'type' => 'asset', 'normal_side' => 'debit'
        ]);
        $this->expenseAccount = ChartOfAccount::create([
            'code' => '6651', 'name' => 'Advance & Performance Bond Expense', 'type' => 'expense', 'normal_side' => 'debit'
        ]);

        FinancialPeriod::create([
            'name'       => 'August 2026',
            'code'       => 'FP-2026-08',
            'start_date' => '2026-08-01',
            'end_date'   => '2026-08-31',
            'is_locked'  => false,
        ]);

        $this->journalEngine = new JournalEngine();
        $this->amortizationService = new PrepaidAmortizationService($this->journalEngine);
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
        $requiredTables = ['users', 'chart_of_accounts', 'financial_periods', 'journal_entries', 'journal_entry_lines', 'financial_audit_trails', 'prepaid_bond_amortization_schedules'];
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

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF;');
        } else {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        }

        Schema::dropIfExists('financial_audit_trails');
        Schema::dropIfExists('financial_periods');
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('prepaid_bond_amortization_schedules');
        Schema::dropIfExists('chart_of_accounts');
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

        Schema::create('prepaid_bond_amortization_schedules', function ($table) {
            $table->id();
            $table->string('schedule_no', 50)->unique();
            $table->string('bond_reference', 100);
            $table->string('name', 255);
            $table->unsignedBigInteger('prepayment_account_id');
            $table->unsignedBigInteger('expense_account_id');
            $table->decimal('total_fee_amount', 18, 2);
            $table->decimal('amortized_amount', 18, 2)->default(0.00);
            $table->decimal('monthly_amortization_amount', 18, 2);
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('validity_months')->default(12);
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('cost_center_id')->nullable();
            $table->unsignedBigInteger('site_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function test_bond_fee_amortization_posts_balanced_journal_and_tags_project()
    {
        $schedule = PrepaidBondAmortizationSchedule::create([
            'schedule_no'                 => 'SCH-PB-2026-001',
            'bond_reference'              => 'PB-CBE-2026-001',
            'name'                        => 'Performance Bond - Bole Project Road Package 2',
            'total_fee_amount'            => 12000.00, // 12,000 ETB total fee
            'amortized_amount'            => 0.00,
            'start_date'                  => '2026-08-01',
            'end_date'                    => '2027-07-31',
            'validity_months'             => 12,
            'monthly_amortization_amount' => 1000.00, // 1,000 / month
            'prepayment_account_id'       => $this->prepaidAccount->id,
            'expense_account_id'          => $this->expenseAccount->id,
            'project_id'                  => 201,
            'cost_center_id'              => 8,
            'site_id'                     => 4,
        ]);

        $je = $this->amortizationService->amortizeMonthlyBondFee(
            $schedule,
            '2026-08-31',
            $this->user->id
        );

        $this->assertNotNull($je);
        $this->assertEquals('posted', $je->status);
        $this->assertCount(2, $je->lines);

        $debitLine = $je->lines->where('side', 'debit')->first();
        $creditLine = $je->lines->where('side', 'credit')->first();

        // DR 6651 Bond Expense 1,000.00
        $this->assertEquals($this->expenseAccount->id, $debitLine->account_id);
        $this->assertEquals(1000.00, (float) $debitLine->amount);
        $this->assertEquals(201, $debitLine->project_id);
        $this->assertEquals(8, $debitLine->cost_center_id);
        $this->assertEquals(4, $debitLine->site_id);

        // CR 1280 Prepaid Bonds 1,000.00
        $this->assertEquals($this->prepaidAccount->id, $creditLine->account_id);
        $this->assertEquals(1000.00, (float) $creditLine->amount);
        $this->assertEquals(201, $creditLine->project_id);

        $schedule->refresh();
        $this->assertEquals(1000.00, (float) $schedule->amortized_amount);
        $this->assertEquals(11000.00, (float) $schedule->remaining_fee_amount);
    }

    public function test_bond_fee_amortization_is_idempotent_and_caps_at_total_fee()
    {
        $schedule = PrepaidBondAmortizationSchedule::create([
            'schedule_no'                 => 'SCH-PB-2026-002',
            'bond_reference'              => 'PB-AWASH-2026-002',
            'name'                        => 'Advance Guarantee - Awash Bank',
            'total_fee_amount'            => 5000.00,
            'amortized_amount'            => 4600.00, // Only 400.00 remains
            'start_date'                  => '2026-01-01',
            'end_date'                    => '2026-12-31',
            'validity_months'             => 12,
            'monthly_amortization_amount' => 500.00, // Would exceed if not capped
            'prepayment_account_id'       => $this->prepaidAccount->id,
            'expense_account_id'          => $this->expenseAccount->id,
            'project_id'                  => 202,
        ]);

        $je = $this->amortizationService->amortizeMonthlyBondFee(
            $schedule,
            '2026-08-31',
            $this->user->id
        );

        $this->assertNotNull($je);
        // Assert capped at 400.00
        $this->assertEquals(400.00, (float) $je->lines->where('side', 'debit')->first()->amount);

        $schedule->refresh();
        $this->assertEquals(5000.00, (float) $schedule->amortized_amount);
        $this->assertEquals(0.00, (float) $schedule->remaining_fee_amount);

        // Further attempt must return null
        $nextJe = $this->amortizationService->amortizeMonthlyBondFee(
            $schedule,
            '2026-09-30',
            $this->user->id
        );
        $this->assertNull($nextJe);
    }
}
