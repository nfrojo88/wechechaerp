<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;
use App\Models\ChartOfAccount;
use App\Models\ConstructionContract;
use App\Models\ContractBudgetRevision;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\User;
use App\Models\FinancialPeriod;
use App\Services\Finance\JournalEngine;
use App\Services\Finance\ConstructionWipPostingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ConstructionContractMasterAndWipTest extends TestCase
{
    protected ConstructionWipPostingService $wipService;
    protected JournalEngine $journalEngine;
    protected User $user;
    protected ConstructionContract $contract;
    protected ChartOfAccount $wipAcc;
    protected ChartOfAccount $storeAcc;
    protected ChartOfAccount $laborLiabAcc;
    protected ChartOfAccount $subAcc;
    protected ChartOfAccount $equipAcc;

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
        $this->seedData();
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
            'construction_contracts', 'contract_budget_revisions'
        ];

        $allExist = true;
        foreach ($requiredTables as $t) {
            if (!Schema::hasTable($t)) {
                $allExist = false;
                break;
            }
        }

        if ($allExist && Schema::hasColumn('financial_periods', 'status') && Schema::hasColumn('journal_entry_lines', 'contract_id')) {
            return;
        }

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
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('contract_id')->nullable();
            $table->unsignedBigInteger('cost_center_id')->nullable();
            $table->unsignedBigInteger('site_id')->nullable();
            $table->string('party_type', 50)->nullable();
            $table->unsignedBigInteger('party_id')->nullable();
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
            $table->date('defect_liability_end_date')->nullable();
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
    }

    protected function seedData(): void
    {
        FinancialPeriod::create([
            'name'        => 'August 2026',
            'code'        => 'FP-2026-08',
            'start_date'  => '2026-08-01',
            'end_date'    => '2026-08-31',
            'fiscal_year' => '2026',
            'status'      => 'open',
            'is_locked'   => false,
        ]);

        $this->user = User::create(['name' => 'Eng. Dawit', 'email' => 'dawit@wechecha.com']);

        $this->wipAcc        = ChartOfAccount::create(['code' => '1260', 'name' => 'Construction Work-in-Progress (WIP)', 'type' => 'asset', 'normal_side' => 'debit']);
        $this->storeAcc      = ChartOfAccount::create(['code' => '1250', 'name' => 'Inventory on Hand - Stores', 'type' => 'asset', 'normal_side' => 'debit']);
        $this->laborLiabAcc  = ChartOfAccount::create(['code' => '2251', 'name' => 'Accrued Salaries & Wages', 'type' => 'liability', 'normal_side' => 'credit']);
        $this->subAcc        = ChartOfAccount::create(['code' => '2255', 'name' => 'Accrued Subcontractor Costs', 'type' => 'liability', 'normal_side' => 'credit']);
        $this->equipAcc      = ChartOfAccount::create(['code' => '1455', 'name' => 'Accum Depr - Heavy Machinery', 'type' => 'asset', 'normal_side' => 'credit']);

        $this->contract = ConstructionContract::create([
            'contract_number'              => 'CNT-2026-BOLE',
            'title'                        => 'Bole Road Expansion Project',
            'client_id'                    => 10,
            'project_id'                   => 1,
            'cost_center_id'               => 101,
            'contract_value_exc_vat'       => 10000000.00,
            'vat_rate_percent'             => 15.00,
            'retention_rate_percent'       => 5.00,
            'retention_cap_amount'         => 500000.00,
            'advance_payment_amount'       => 2000000.00,
            'advance_recovery_rate_percent'=> 20.00,
            'vat_base_type'                => null, // Strict no default
            'vat_base_status'              => 'to_be_verified',
            'original_budget_cost'         => 8500000.00,
            'current_budget_cost'          => 8500000.00,
            'estimate_to_complete'         => 8500000.00,
            'status'                       => 'active',
            'start_date'                   => '2026-08-01',
            'end_date'                     => '2027-07-31',
            'created_by'                   => $this->user->id,
        ]);

        $this->wipService    = new ConstructionWipPostingService();
        $this->journalEngine = new JournalEngine();
    }

    public function test_contract_master_creation_and_onerous_status_detection()
    {
        $this->assertFalse($this->contract->isOnerous());
        $this->assertEquals(0.00, $this->contract->forecast_loss);
        $this->assertNull($this->contract->vat_base_type, 'vat_base_type must have no default');

        // Mutate BAC to exceed contract value (Onerous)
        $this->contract->update(['current_budget_cost' => 11500000.00]);
        $this->assertTrue($this->contract->isOnerous());
        $this->assertEquals(1500000.00, $this->contract->forecast_loss);
    }

    public function test_budget_revision_logging_and_approval_workflow()
    {
        // 1. Submit unapproved budget revision
        $rev = $this->wipService->recordBudgetRevision(
            $this->contract->id,
            'cost_budget_etc',
            11500000.00,
            10000000.00,
            'Site soil condition variation requires additional piling works',
            $this->user->id,
            false
        );

        $this->assertEquals('REV-001', $rev->revision_no);
        $this->assertFalse($rev->is_approved);
        $this->assertEquals(8500000.00, $this->contract->fresh()->current_budget_cost, 'Contract BAC must not change prior to approval');

        // 2. Approve budget revision
        $rev->update([
            'is_approved' => true,
            'approved_by' => $this->user->id,
            'approved_at' => now(),
        ]);
        $this->contract->update(['current_budget_cost' => $rev->revised_budget_cost]);

        $this->assertEquals(11500000.00, $this->contract->fresh()->current_budget_cost);
        $this->assertTrue($this->contract->fresh()->isOnerous());
    }

    public function test_siv_material_issue_posts_to_wip_with_contract_and_project_dimensions()
    {
        $je = $this->wipService->postMaterialIssueToWip(
            contractId: $this->contract->id,
            projectId: 1,
            amount: 250000.00,
            sivNumber: 'SIV-2026-008',
            costCenterId: 101,
            isInstalled: true,
            userId: $this->user->id,
            entryDate: '2026-08-15'
        );

        $this->assertEquals('posted', $je->status);
        $lines = $je->lines;
        $this->assertCount(2, $lines);

        $debitLine = $lines->firstWhere('side', 'debit');
        $this->assertEquals($this->wipAcc->id, $debitLine->account_id);
        $this->assertEquals(250000.00, $debitLine->amount);
        $this->assertEquals($this->contract->id, $debitLine->contract_id);
        $this->assertEquals(1, $debitLine->project_id);
        $this->assertEquals(101, $debitLine->cost_center_id);

        $creditLine = $lines->firstWhere('side', 'credit');
        $this->assertEquals($this->storeAcc->id, $creditLine->account_id);
        $this->assertEquals(250000.00, $creditLine->amount);
    }

    public function test_direct_labor_and_subcontract_and_equipment_costs_post_to_wip()
    {
        // 1. Direct Labor
        $jeLabor = $this->wipService->postDirectLaborToWip(
            $this->contract->id,
            1,
            80000.00,
            'PAY-SITE-202608',
            101,
            $this->user->id,
            '2026-08-20'
        );
        $this->assertEquals('posted', $jeLabor->status);
        $wipLine1 = $jeLabor->lines->firstWhere('side', 'debit');
        $this->assertEquals($this->contract->id, $wipLine1->contract_id);
        $this->assertEquals(80000.00, $wipLine1->amount);

        // 2. Subcontractor Accrual
        $jeSub = $this->wipService->postSubcontractCostToWip(
            $this->contract->id,
            1,
            55, // Subcontractor ID
            120000.00,
            'SUB-CLAIM-04',
            101,
            $this->user->id,
            '2026-08-25'
        );
        $this->assertEquals('posted', $jeSub->status);
        $wipLine2 = $jeSub->lines->firstWhere('side', 'debit');
        $this->assertEquals($this->contract->id, $wipLine2->contract_id);
        $this->assertEquals('subcontractor', $wipLine2->party_type);
        $this->assertEquals(55, $wipLine2->party_id);
        $this->assertEquals(120000.00, $wipLine2->amount);

        // 3. Equipment Plant Usage
        $jeEquip = $this->wipService->postEquipmentCostToWip(
            $this->contract->id,
            1,
            $this->equipAcc->id,
            35000.00,
            'EQUIP-LOG-AUG26',
            101,
            $this->user->id,
            '2026-08-28'
        );
        $this->assertEquals('posted', $jeEquip->status);
        $wipLine3 = $jeEquip->lines->firstWhere('side', 'debit');
        $this->assertEquals($this->contract->id, $wipLine3->contract_id);
        $this->assertEquals(35000.00, $wipLine3->amount);

        // Assert Total WIP Accumulated
        $totalWip = $this->wipAcc->fresh()->ledger_balance;
        $this->assertEquals(235000.00, $totalWip, 'Total WIP must equal sum of direct labor (80k) + subcontract (120k) + equipment (35k)');
    }

    public function test_project_with_single_contract_auto_resolves_when_contract_id_is_null()
    {
        // Project 1 has only $this->contract
        $resolvedId = $this->wipService->resolveContractId(projectId: 1, contractId: null);
        $this->assertEquals($this->contract->id, $resolvedId);

        // Posting material issue with contractId=null succeeds via auto-resolution
        $je = $this->wipService->postMaterialIssueToWip(
            contractId: null,
            projectId: 1,
            amount: 50000.00,
            sivNumber: 'SIV-AUTO-01',
            costCenterId: 101,
            isInstalled: true,
            userId: $this->user->id,
            entryDate: '2026-08-16'
        );
        $this->assertEquals('posted', $je->status);
        $wipLine = $je->lines->firstWhere('side', 'debit');
        $this->assertEquals($this->contract->id, $wipLine->contract_id);
    }

    public function test_project_with_multiple_contracts_throws_ambiguous_contract_exception_when_contract_id_is_null()
    {
        // Create second active contract on project 1
        $contract2 = ConstructionContract::create([
            'contract_number'              => 'CNT-2026-BOLE-LOT2',
            'title'                        => 'Bole Road Expansion Lot 2',
            'client_id'                    => 10,
            'project_id'                   => 1,
            'cost_center_id'               => 101,
            'contract_value_exc_vat'       => 5000000.00,
            'status'                       => 'active',
            'start_date'                   => '2026-08-01',
            'end_date'                     => '2027-07-31',
            'created_by'                   => $this->user->id,
        ]);

        $this->expectException(\App\Exceptions\Finance\AmbiguousContractException::class);
        $this->expectExceptionMessage("is linked to multiple active contracts");

        // Should throw AmbiguousContractException
        $this->wipService->postMaterialIssueToWip(
            contractId: null,
            projectId: 1,
            amount: 50000.00,
            sivNumber: 'SIV-AMBIGUOUS-01',
            costCenterId: 101,
            isInstalled: true,
            userId: $this->user->id,
            entryDate: '2026-08-17'
        );
    }
}
