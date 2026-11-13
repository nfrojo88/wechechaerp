<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\User;
use App\Services\Finance\TaxRemittanceService;
use App\Services\Finance\JournalEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TaxConfigAndRemittanceTest extends TestCase
{
    protected TaxRemittanceService $taxService;
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

        $this->user = User::create(['name' => 'Tax Officer', 'email' => 'tax@wechecha.com']);
        $this->taxService = new TaxRemittanceService();
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
        $requiredTables = ['users', 'chart_of_accounts', 'journal_entries', 'journal_entry_lines', 'financial_periods', 'financial_audit_trails', 'tax_rates'];
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
        Schema::dropIfExists('tax_rates');
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

        Schema::create('tax_rates', function ($table) {
            $table->id();
            $table->string('tax_code', 50)->unique();
            $table->string('name', 100);
            $table->string('category', 50);
            $table->decimal('standard_rate_percent', 5, 2);
            $table->decimal('non_tin_rate_percent', 5, 2)->default(30.00);
            $table->decimal('threshold_amount', 18, 2)->default(0.00);
            $table->string('applies_to', 20)->default('goods');
            $table->unsignedBigInteger('receivable_account_id')->nullable();
            $table->unsignedBigInteger('payable_account_id')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function test_vat_remittance_offsets_output_with_input_and_wires_net_cash_when_output_exceeds_input()
    {
        $cbeAcc = ChartOfAccount::create(['code' => '1011', 'name' => 'CBE Main Checking', 'type' => 'asset', 'normal_side' => 'debit']);
        $inputVatAcc = ChartOfAccount::create(['code' => '1230', 'name' => 'Input VAT Receivable', 'type' => 'asset', 'normal_side' => 'debit']);
        $outputVatAcc = ChartOfAccount::create(['code' => '2300', 'name' => 'Output VAT Payable', 'type' => 'liability', 'normal_side' => 'credit']);

        // Suppose: Output VAT = 150,000 ETB, Input VAT = 60,000 ETB
        // Net Cash Wire to ERCA = 90,000 ETB
        $res = $this->taxService->postVatRemittance(150000.00, 60000.00, $cbeAcc->id, $this->user->id, '2026-10-31', '2026-10');

        $this->assertEquals(90000.00, $res['net_cash_remitted']);
        $this->assertEquals(0.00, $res['vat_carryforward']);
        $this->assertEquals(60000.00, $res['input_vat_claimed']);

        // Assert Journal Lines: DR 2300 150,000; CR 1230 60,000; CR 1011 90,000
        $je = $res['journal_entry'];
        $this->assertEquals('posted', $je->status);
        $lines = $je->lines;
        $this->assertCount(3, $lines);

        $debitLine = $lines->firstWhere('side', 'debit');
        $this->assertEquals($outputVatAcc->id, $debitLine->account_id);
        $this->assertEquals(150000.00, $debitLine->amount);

        $inputCredit = $lines->where('side', 'credit')->firstWhere('account_id', $inputVatAcc->id);
        $this->assertEquals(60000.00, $inputCredit->amount);

        $bankCredit = $lines->where('side', 'credit')->firstWhere('account_id', $cbeAcc->id);
        $this->assertEquals(90000.00, $bankCredit->amount);
    }

    public function test_vat_remittance_carries_forward_credit_when_input_exceeds_output_with_zero_bank_disbursement()
    {
        $cbeAcc = ChartOfAccount::create(['code' => '1011', 'name' => 'CBE Main Checking', 'type' => 'asset', 'normal_side' => 'debit']);
        $inputVatAcc = ChartOfAccount::create(['code' => '1230', 'name' => 'Input VAT Receivable', 'type' => 'asset', 'normal_side' => 'debit']);
        $outputVatAcc = ChartOfAccount::create(['code' => '2300', 'name' => 'Output VAT Payable', 'type' => 'liability', 'normal_side' => 'credit']);

        // Suppose: Output VAT = 50,000 ETB, Input VAT = 85,000 ETB (Heavy equipment/fuel purchases)
        // Offset = 50,000 ETB. Net Cash = 0.00. Carryforward = 35,000 ETB.
        $res = $this->taxService->postVatRemittance(50000.00, 85000.00, $cbeAcc->id, $this->user->id, '2026-10-31', '2026-10');

        $this->assertEquals(0.00, $res['net_cash_remitted']);
        $this->assertEquals(35000.00, $res['vat_carryforward']);
        $this->assertEquals(50000.00, $res['input_vat_claimed']);

        // Assert 2 balanced lines: DR 2300 50,000; CR 1230 50,000 (No bank line)
        $lines = $res['journal_entry']->lines;
        $this->assertCount(2, $lines);
        $this->assertEquals(50000.00, $lines->firstWhere('side', 'debit')->amount);
        $this->assertEquals(50000.00, $lines->firstWhere('side', 'credit')->amount);
        $this->assertEquals($inputVatAcc->id, $lines->firstWhere('side', 'credit')->account_id);
    }
}
