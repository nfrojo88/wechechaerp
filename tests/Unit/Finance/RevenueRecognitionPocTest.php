<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;
use App\Models\ChartOfAccount;
use App\Models\ConstructionContract;
use App\Models\ContractBudgetRevision;
use App\Models\FinancialPeriod;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\RevenueRecognitionRun;
use App\Models\User;
use App\Services\Finance\JournalEngine;
use App\Services\Finance\RevenueRecognitionService;
use App\Services\Finance\ConstructionWipPostingService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class RevenueRecognitionPocTest extends TestCase
{
    protected RevenueRecognitionService $revRecService;
    protected ConstructionWipPostingService $wipService;
    protected JournalEngine $journalEngine;
    protected User $user;
    protected FinancialPeriod $periodAug;
    protected FinancialPeriod $periodSep;
    protected ConstructionContract $contractA;
    protected ConstructionContract $contractB;
    protected ChartOfAccount $wipAcc;
    protected ChartOfAccount $storeAcc;
    protected ChartOfAccount $assetAcc;
    protected ChartOfAccount $revAcc;
    protected ChartOfAccount $cosAcc;
    protected ChartOfAccount $liabilityAcc;
    protected ChartOfAccount $arAcc;
    protected ChartOfAccount $advanceAcc;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            DB::statement('SET @gl_posting_in_progress = 1;');
            DB::statement('SET @period_lock_override = 0;');
        }

        $this->ensureSchema();
        DB::beginTransaction();
        $this->seedTestData();
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
        $requiredTables = [
            'users', 'chart_of_accounts', 'financial_periods',
            'journal_entries', 'journal_entry_lines',
            'construction_contracts', 'contract_budget_revisions',
            'revenue_recognition_runs'
        ];

        $allExist = true;
        foreach ($requiredTables as $t) {
            if (!Schema::hasTable($t)) {
                $allExist = false;
                break;
            }
        }

        if ($allExist && Schema::hasColumn('journal_entry_lines', 'contract_id') && Schema::hasColumn('journal_entry_lines', 'is_installed')) {
            return;
        }

        Schema::dropIfExists('revenue_recognition_runs');
        Schema::dropIfExists('contract_budget_revisions');
        Schema::dropIfExists('construction_contracts');
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('financial_periods');
        Schema::dropIfExists('chart_of_accounts');
        Schema::dropIfExists('users');

        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('role')->default('project_manager');
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
            $table->boolean('is_installed')->default(true);
            $table->boolean('exclude_from_poc')->default(false);
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('contract_id')->nullable();
            $table->unsignedBigInteger('cost_center_id')->nullable();
            $table->timestamps();
        });

        Schema::create('construction_contracts', function ($table) {
            $table->id();
            $table->string('contract_number', 50)->unique();
            $table->string('title', 200);
            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('cost_center_id')->nullable();
            $table->decimal('contract_value_exc_vat', 18, 2);
            $table->decimal('vat_rate_percent', 5, 2)->default(15.00);
            $table->decimal('retention_rate_percent', 5, 2)->default(5.00);
            $table->decimal('retention_cap_amount', 18, 2)->nullable();
            $table->decimal('advance_payment_amount', 18, 2)->default(0.00);
            $table->decimal('advance_recovery_rate_percent', 5, 2)->default(20.00);
            $table->string('vat_base_type', 50)->nullable();
            $table->string('vat_base_status', 30)->default('to_be_verified');
            $table->decimal('original_budget_cost', 18, 2)->default(0.00);
            $table->decimal('current_budget_cost', 18, 2)->default(0.00);
            $table->decimal('estimate_to_complete', 18, 2)->default(0.00);
            $table->string('status', 30)->default('active');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('contract_budget_revisions', function ($table) {
            $table->id();
            $table->unsignedBigInteger('contract_id');
            $table->string('revision_no', 30);
            $table->string('revision_type', 50);
            $table->decimal('previous_budget_cost', 18, 2);
            $table->decimal('revised_budget_cost', 18, 2);
            $table->decimal('previous_contract_value', 18, 2);
            $table->decimal('revised_contract_value', 18, 2);
            $table->text('justification');
            $table->boolean('is_approved')->default(false);
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
        });

        Schema::create('revenue_recognition_runs', function ($table) {
            $table->id();
            $table->unsignedBigInteger('contract_id');
            $table->unsignedBigInteger('financial_period_id');
            $table->date('as_of_date');
            $table->decimal('contract_price', 18, 2);
            $table->decimal('current_budget_cost', 18, 2);
            $table->decimal('qualifying_incurred_cost', 18, 2);
            $table->decimal('uninstalled_material_cost', 18, 2)->default(0.00);
            $table->decimal('poc_percentage', 8, 4);
            $table->decimal('cumulative_revenue', 18, 2);
            $table->decimal('previously_recognised_revenue', 18, 2)->default(0.00);
            $table->decimal('catch_up_revenue', 18, 2);
            $table->decimal('cumulative_billed', 18, 2)->default(0.00);
            $table->decimal('net_asset_amount', 18, 2)->default(0.00);
            $table->decimal('net_liability_amount', 18, 2)->default(0.00);
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->boolean('is_posted')->default(false);
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
        });
    }

    protected function seedTestData(): void
    {
        $this->user = User::create(['name' => 'Lead Accountant', 'email' => 'lead.acc@wechecha.com']);

        $this->periodAug = FinancialPeriod::create([
            'name' => 'August 2026', 'code' => 'FP-2026-08', 'start_date' => '2026-08-01', 'end_date' => '2026-08-31', 'status' => 'open'
        ]);

        $this->periodSep = FinancialPeriod::create([
            'name' => 'September 2026', 'code' => 'FP-2026-09', 'start_date' => '2026-09-01', 'end_date' => '2026-09-30', 'status' => 'open'
        ]);

        $this->wipAcc       = ChartOfAccount::create(['code' => '1260', 'name' => 'Construction WIP', 'type' => 'asset', 'normal_side' => 'debit']);
        $this->storeAcc     = ChartOfAccount::create(['code' => '1250', 'name' => 'Inventory Stores', 'type' => 'asset', 'normal_side' => 'debit']);
        $this->assetAcc     = ChartOfAccount::create(['code' => '1210', 'name' => 'Contract Asset (Unbilled Rev)', 'type' => 'asset', 'normal_side' => 'debit']);
        $this->revAcc       = ChartOfAccount::create(['code' => '4100', 'name' => 'Construction Revenue', 'type' => 'revenue', 'normal_side' => 'credit']);
        $this->cosAcc       = ChartOfAccount::create(['code' => '5010', 'name' => 'Construction Cost of Sales', 'type' => 'expense', 'normal_side' => 'debit']);
        $this->liabilityAcc = ChartOfAccount::create(['code' => '2280', 'name' => 'Contract Liability (Excess Billing)', 'type' => 'liability', 'normal_side' => 'credit']);
        $this->arAcc        = ChartOfAccount::create(['code' => '1200', 'name' => 'Accounts Receivable', 'type' => 'asset', 'normal_side' => 'debit']);
        $this->advanceAcc   = ChartOfAccount::create(['code' => '2110', 'name' => 'Client Advance Liability', 'type' => 'liability', 'normal_side' => 'credit']);

        // Contract A: 10M Budget, 12M Price
        $this->contractA = ConstructionContract::create([
            'contract_number'        => 'CNT-REV-A',
            'title'                  => 'Commercial Tower Project',
            'project_id'             => 101,
            'contract_value_exc_vat' => 12000000.00,
            'original_budget_cost'   => 10000000.00,
            'current_budget_cost'    => 10000000.00,
            'status'                 => 'active',
            'created_by'             => $this->user->id,
        ]);

        // Contract B: 8M Budget, 10M Price
        $this->contractB = ConstructionContract::create([
            'contract_number'        => 'CNT-REV-B',
            'title'                  => 'Bridge Infrastructure Project',
            'project_id'             => 102,
            'contract_value_exc_vat' => 10000000.00,
            'original_budget_cost'   => 8000000.00,
            'current_budget_cost'    => 8000000.00,
            'status'                 => 'active',
            'created_by'             => $this->user->id,
        ]);

        $this->journalEngine = new JournalEngine();
        $this->wipService    = new ConstructionWipPostingService($this->journalEngine);
        $this->revRecService = new RevenueRecognitionService($this->journalEngine);
    }

    /** @test */
    public function it_blocks_revenue_recognition_when_feature_flag_is_off_by_default()
    {
        // Explicitly set single authoritative feature flag to false
        Config::set('finance.modules.revenue_recognition', false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Revenue recognition GL posting is disabled by feature flag");

        // Attempt posting without force
        $this->revRecService->recognizeRevenue(
            contractId: $this->contractA->id,
            financialPeriodId: $this->periodAug->id,
            userId: $this->user->id,
            force: false
        );
    }

    /** @test */
    public function it_calculates_poc_cost_to_cost_strictly_excluding_uninstalled_materials()
    {
        // 1. Post installed materials: ETB 4,000,000.00
        $this->wipService->postMaterialIssueToWip(
            contractId: $this->contractA->id,
            projectId: 101,
            amount: 4000000.00,
            sivNumber: 'SIV-INST-01',
            isInstalled: true,
            userId: $this->user->id,
            entryDate: '2026-08-15'
        );

        // 2. Post uninstalled site stock: ETB 500,000.00 (exclude_from_poc = true)
        $this->wipService->postMaterialIssueToWip(
            contractId: $this->contractA->id,
            projectId: 101,
            amount: 500000.00,
            sivNumber: 'SIV-UNINST-02',
            isInstalled: false,
            userId: $this->user->id,
            entryDate: '2026-08-20'
        );

        // Calculate PoC as of August 31
        $poc = $this->revRecService->calculatePoc($this->contractA->id, '2026-08-31');

        $this->assertEquals(4000000.00, $poc['qualifying_incurred_cost'], 'Qualifying cost must exclude uninstalled materials');
        $this->assertEquals(500000.00, $poc['uninstalled_material_cost'], 'Uninstalled materials must be tracked separately');
        $this->assertEquals(0.4000, $poc['poc_percentage'], 'PoC must be 4.0M / 10.0M = 40.00% (NOT 45.00%)');
        $this->assertEquals(4800000.00, $poc['cumulative_revenue'], 'Revenue must be 12M * 40% = 4.8M');
    }

    /** @test */
    public function it_includes_only_approved_variations_in_contract_price()
    {
        // Unapproved variation: +2,000,000
        ContractBudgetRevision::create([
            'contract_id'             => $this->contractA->id,
            'revision_no'             => 'REV-UNAPP-01',
            'revision_type'           => 'scope_variation',
            'previous_budget_cost'    => 10000000.00,
            'revised_budget_cost'     => 11500000.00,
            'previous_contract_value' => 12000000.00,
            'revised_contract_value'  => 14000000.00,
            'justification'           => 'Pending client approval',
            'is_approved'             => false,
            'created_by'              => $this->user->id,
        ]);

        $pocUnapproved = $this->revRecService->calculatePoc($this->contractA->id, '2026-08-31');
        $this->assertEquals(12000000.00, $pocUnapproved['contract_price'], 'Unapproved variation must NOT increase contract price');

        // Approved variation: +1,500,000
        ContractBudgetRevision::create([
            'contract_id'             => $this->contractA->id,
            'revision_no'             => 'REV-APP-02',
            'revision_type'           => 'scope_variation',
            'previous_budget_cost'    => 10000000.00,
            'revised_budget_cost'     => 11200000.00,
            'previous_contract_value' => 12000000.00,
            'revised_contract_value'  => 13500000.00,
            'justification'           => 'Formally signed variation #02',
            'is_approved'             => true,
            'approved_by'             => $this->user->id,
            'approved_at'             => now(),
            'created_by'              => $this->user->id,
        ]);

        $pocApproved = $this->revRecService->calculatePoc($this->contractA->id, '2026-08-31');
        $this->assertEquals(13500000.00, $pocApproved['contract_price'], 'Approved variation must increase contract price to 13.5M');
    }

    /** @test */
    public function it_posts_cumulative_catch_up_adjustment_across_two_periods()
    {
        Config::set('finance.modules.revenue_recognition', true);

        // Period 1 (August): Incur 3,000,000 out of 10,000,000 BAC (PoC = 30%)
        $this->wipService->postMaterialIssueToWip($this->contractA->id, 101, 3000000.00, 'SIV-AUG-01', null, true, $this->user->id, '2026-08-15');
        
        $entryP1 = $this->revRecService->recognizeRevenue($this->contractA->id, $this->periodAug->id, $this->user->id);
        $this->assertEquals('posted', $entryP1->status);

        // In Period 1: Cumulative Rev = 12M * 30% = 3,600,000
        $revLineP1 = $entryP1->lines->firstWhere('account_id', $this->revAcc->id);
        $this->assertEquals(3600000.00, $revLineP1->amount);
        $assetLineP1 = $entryP1->lines->firstWhere('account_id', $this->assetAcc->id);
        $this->assertEquals(3600000.00, $assetLineP1->amount);

        // Period 2 (September): Incur additional 2,500,000 (Cumulative = 5,500,000 -> PoC = 55%)
        $this->wipService->postMaterialIssueToWip($this->contractA->id, 101, 2500000.00, 'SIV-SEP-02', null, true, $this->user->id, '2026-09-15');

        $entryP2 = $this->revRecService->recognizeRevenue($this->contractA->id, $this->periodSep->id, $this->user->id);
        $this->assertEquals('posted', $entryP2->status);

        // In Period 2: Cumulative Rev = 12M * 55% = 6,600,000. Previously recognized = 3,600,000.
        // Catch-up Revenue for Period 2 = 6,600,000 - 3,600,000 = 3,000,000.00!
        $revLineP2 = $entryP2->lines->firstWhere('account_id', $this->revAcc->id);
        $this->assertEquals(3000000.00, $revLineP2->amount, 'Period 2 revenue must be catch-up adjustment of 3.0M');
        $assetLineP2 = $entryP2->lines->firstWhere('account_id', $this->assetAcc->id);
        $this->assertEquals(3000000.00, $assetLineP2->amount);

        // Total Cumulative Revenue in Ledger across both periods = 3.6M + 3.0M = 6.6M
        $totalLedgerRevenue = $this->revAcc->fresh()->ledger_balance;
        $this->assertEquals(6600000.00, $totalLedgerRevenue);
    }

    /** @test */
    public function it_is_strictly_idempotent_per_contract_and_period()
    {
        Config::set('finance.modules.revenue_recognition', true);

        $this->wipService->postMaterialIssueToWip($this->contractA->id, 101, 2000000.00, 'SIV-IDEMP-01', null, true, $this->user->id, '2026-08-10');

        // First run
        $firstEntry = $this->revRecService->recognizeRevenue($this->contractA->id, $this->periodAug->id, $this->user->id);
        $this->assertEquals('posted', $firstEntry->status);

        $initialLineCount = JournalEntryLine::count();
        $initialBalance = $this->revAcc->fresh()->ledger_balance;

        // Second run for same contract and period
        $secondEntry = $this->revRecService->recognizeRevenue($this->contractA->id, $this->periodAug->id, $this->user->id);

        $this->assertEquals($firstEntry->id, $secondEntry->id, 'Second run must return identical JournalEntry');
        $this->assertEquals($initialLineCount, JournalEntryLine::count(), 'Zero additional journal lines may be created');
        $this->assertEquals($initialBalance, $this->revAcc->fresh()->ledger_balance, 'Ledger balance must remain completely unchanged');
    }

    /** @test */
    public function it_nets_balances_at_contract_level_across_two_contracts()
    {
        Config::set('finance.modules.revenue_recognition', true);

        // --- CONTRACT A (Underbilled Position: Recognised Revenue > Billing) ---
        // Incur 4M -> PoC = 40% -> Revenue = 4.8M
        $this->wipService->postMaterialIssueToWip($this->contractA->id, 101, 4000000.00, 'SIV-A-01', null, true, $this->user->id, '2026-08-10');
        // Record 3M billing to Contract A via JournalEngine (DR 1200 AR 3M / CR 2110 3M)
        $this->journalEngine->createEntry(
            'contract_billing',
            $this->contractA->id,
            'Billing for Contract A',
            [
                ['account_id' => $this->arAcc->id, 'side' => 'debit', 'amount' => 3000000.00, 'contract_id' => $this->contractA->id],
                ['account_id' => $this->advanceAcc->id, 'side' => 'credit', 'amount' => 3000000.00, 'contract_id' => $this->contractA->id],
            ],
            $this->user->id,
            $this->user->id,
            '2026-08-12'
        );

        $pocA = $this->revRecService->calculatePoc($this->contractA->id, '2026-08-31');
        $this->assertEquals(4800000.00, $pocA['cumulative_revenue']);
        $this->assertEquals(3000000.00, $pocA['cumulative_billed']);
        $this->assertEquals(1800000.00, $pocA['net_asset_amount'], 'Contract A must have 1.8M Net Contract Asset');
        $this->assertEquals(0.00, $pocA['net_liability_amount'], 'Contract A must have ZERO Net Contract Liability');

        // --- CONTRACT B (Overbilled Position: Billing > Recognised Revenue) ---
        // Incur 2M out of 8M BAC -> PoC = 25% -> Revenue = 10M * 25% = 2.5M
        $this->wipService->postMaterialIssueToWip($this->contractB->id, 102, 2000000.00, 'SIV-B-01', null, true, $this->user->id, '2026-08-10');
        // Record 4M advance billing to Contract B (DR 1200 AR 4M / CR 2110 4M)
        $this->journalEngine->createEntry(
            'contract_billing',
            $this->contractB->id,
            'Billing for Contract B',
            [
                ['account_id' => $this->arAcc->id, 'side' => 'debit', 'amount' => 4000000.00, 'contract_id' => $this->contractB->id],
                ['account_id' => $this->advanceAcc->id, 'side' => 'credit', 'amount' => 4000000.00, 'contract_id' => $this->contractB->id],
            ],
            $this->user->id,
            $this->user->id,
            '2026-08-12'
        );

        $pocB = $this->revRecService->calculatePoc($this->contractB->id, '2026-08-31');
        $this->assertEquals(2500000.00, $pocB['cumulative_revenue']);
        $this->assertEquals(4000000.00, $pocB['cumulative_billed']);
        $this->assertEquals(0.00, $pocB['net_asset_amount'], 'Contract B must have ZERO Net Contract Asset');
        $this->assertEquals(1500000.00, $pocB['net_liability_amount'], 'Contract B must have 1.5M Net Contract Liability');

        // Netting is strictly contract-level: Contract A remains unbilled asset, Contract B remains excess billing liability
        $this->assertNotEquals($pocA['net_asset_amount'], $pocB['net_asset_amount']);
    }
}
