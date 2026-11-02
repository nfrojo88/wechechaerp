<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\CreditStoreLedger;
use App\Models\DeliveryReceipt;
use App\Models\Expense;
use App\Models\ExpenseRequest;
use App\Models\PettyCashMaterialPurchase;
use App\Models\ProcurementPayment;
use App\Models\Project;
use App\Models\PurchaseRequest;
use App\Models\Store;
use App\Models\User;
use App\Services\ProjectAssociatedCostService;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class ProjectAssociatedCostTest extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;
    protected Project $guardedProject;
    protected Project $unguardedProject;
    protected ProjectAssociatedCostService $costService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::firstOrCreate(
            ['email' => 'associated_cost_admin@wechecha.com'],
            ['name' => 'Associated Cost Admin', 'password' => bcrypt('password')]
        );

        try {
            $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
            $this->adminUser->assignRole($role);
        } catch (\Throwable $e) {}

        // 1. Guarded project (GM allocated budget)
        $this->guardedProject = Project::create([
            'name'             => 'Guarded Test Project ' . uniqid(),
            'code'             => 'GP-' . strtoupper(uniqid()),
            'status'           => 'active',
            'budget_allocated' => 500000.00,
            'budget_consumed'  => 12000.00,
            'created_by'       => $this->adminUser->id,
        ]);

        // 2. Unguarded project (No budget allocated)
        $this->unguardedProject = Project::create([
            'name'             => 'Unguarded Test Project ' . uniqid(),
            'code'             => 'UGP-' . strtoupper(uniqid()),
            'status'           => 'planning',
            'budget_allocated' => 0.00,
            'budget_consumed'  => 0.00,
            'created_by'       => $this->adminUser->id,
        ]);

        $this->costService = app(ProjectAssociatedCostService::class);
    }

    /**
     * Test 1: An unguarded-project Expense appears in Associated Costs.
     */
    public function test_unguarded_project_expense_appears_in_associated_costs(): void
    {
        $expense = Expense::create([
            'project_id'         => $this->unguardedProject->id,
            'category'           => 'material',
            'description'        => 'Emergency Sand on Unguarded Site',
            'amount'             => 4500.00,
            'expense_date'       => now()->toDateString(),
            'status'             => 'approved',
            'budget_status'      => 'unguarded',
            'budget_consumed_at' => null,
            'created_by'         => $this->adminUser->id,
        ]);

        $paginated = $this->costService->getPaginated($this->unguardedProject);
        $summary = $this->costService->getSummary($this->unguardedProject);

        $this->assertTrue(collect($paginated->items())->contains('reference_no', 'EXP-' . $expense->id));
        $this->assertEquals(4500.00, $summary['total_associated_cost']);
    }

    /**
     * Test 2: Save-time fact preservation: A later GM budget increase does NOT make old expenses vanish.
     */
    public function test_unguarded_expense_remains_in_associated_costs_after_later_gm_budget_increase(): void
    {
        // 1. Create unguarded project expense
        $expense = Expense::create([
            'project_id'         => $this->unguardedProject->id,
            'category'           => 'material',
            'description'        => 'Historical Timber on Unguarded Site',
            'amount'             => 8200.00,
            'expense_date'       => now()->toDateString(),
            'status'             => 'approved',
            'budget_status'      => 'unguarded',
            'budget_consumed_at' => null,
            'created_by'         => $this->adminUser->id,
        ]);

        $this->assertTrue(
            collect($this->costService->getPaginated($this->unguardedProject)->items())->contains('reference_no', 'EXP-' . $expense->id)
        );

        // 2. GM later allocates budget to this project
        $this->unguardedProject->update([
            'budget_allocated' => 750000.00,
            'status'           => 'active',
        ]);

        // 3. Confirm expense still remains in Associated Costs because save-time fact has budget_consumed_at = NULL
        $paginatedAfter = $this->costService->getPaginated($this->unguardedProject);
        $this->assertTrue(
            collect($paginatedAfter->items())->contains('reference_no', 'EXP-' . $expense->id),
            'Old unguarded expense must not vanish after a subsequent GM budget allocation'
        );
    }

    /**
     * Test 3: An expense counted in budget_consumed must NOT appear in Associated Costs (no double counting).
     */
    public function test_expense_counted_in_budget_consumed_must_not_appear(): void
    {
        // An expense created on guarded project with budget_consumed_at set
        $budgetedExpense = Expense::create([
            'project_id'         => $this->guardedProject->id,
            'category'           => 'overhead',
            'description'        => 'Office Printing Consumed in Budget',
            'amount'             => 3000.00,
            'expense_date'       => now()->toDateString(),
            'status'             => 'approved',
            'budget_status'      => 'safe',
            'budget_consumed_at' => now(),
            'created_by'         => $this->adminUser->id,
        ]);

        $paginated = $this->costService->getPaginated($this->guardedProject);

        $this->assertFalse(collect($paginated->items())->contains('reference_no', 'EXP-' . $budgetedExpense->id));
    }

    /**
     * Test 4: Credit PR stage-by-stage lifecycle test:
     * Confirms that a Credit PR is excluded at each pending/draft/rejected stage,
     * and only enters Associated Costs when goods are actually received on site (intake_complete / verified DR).
     */
    public function test_credit_pr_stage_by_stage_lifecycle_and_goods_receipt_gate(): void
    {
        // 1. All pending, draft, and rejected stages where goods have NOT been received
        $unreceivedStages = [
            'draft',
            'pending_planning',
            'pending_planning_approval',
            'pending_hr_approval',
            'pending_store_review',
            'pending_procurement_manager',
            'pending_procurement_team',
            'pending_marketing_review',
            'pending_proforma_selection',
            'pending_gm_decision',
            'pending_finance',
            'pending_payment',
            'pending_receipt_upload',
            'pending_receipt_verification',
            'pending_driver_booking',
            'rejected',
            'cancelled',
        ];

        $creditPr = PurchaseRequest::create([
            'project_id'   => $this->guardedProject->id,
            'requested_by' => $this->adminUser->id,
            'title'        => 'Stage Lifecycle PR for Cement',
            'status'       => 'draft',
        ]);

        $ledger = CreditStoreLedger::create([
            'purchase_request_id' => $creditPr->id,
            'pr_no'               => 'PR-' . $creditPr->id,
            'project_id'          => $this->guardedProject->id,
            'supplier_name'       => 'Dangote Cement Credit Depot',
            'credit_amount'       => 150000.00,
            'status'              => 'outstanding',
            'authorized_at'       => now(),
            'created_by'          => $this->adminUser->id,
        ]);

        // Verify credit PR is excluded at every unreceived stage
        foreach ($unreceivedStages as $stage) {
            $creditPr->update(['status' => $stage]);
            $items = collect($this->costService->getPaginated($this->guardedProject)->items());
            $this->assertFalse(
                $items->contains('reference_no', 'PR-' . $creditPr->id),
                "Credit PR at stage '{$stage}' must NOT enter Associated Costs before physical goods receipt."
            );
        }

        // 2. Stage: intake_complete (Goods physically received on site by Store Keeper)
        $creditPr->update(['status' => 'intake_complete']);
        $itemsIntake = collect($this->costService->getPaginated($this->guardedProject)->items());
        $this->assertTrue(
            $itemsIntake->contains('reference_no', 'PR-' . $creditPr->id),
            "Credit PR at stage 'intake_complete' MUST enter Associated Costs as goods are received on site."
        );

        // 3. Stage: completed (Full procurement workflow finalized)
        $creditPr->update(['status' => 'completed']);
        $itemsCompleted = collect($this->costService->getPaginated($this->guardedProject)->items());
        $this->assertTrue(
            $itemsCompleted->contains('reference_no', 'PR-' . $creditPr->id),
            "Credit PR at stage 'completed' MUST remain in Associated Costs."
        );

        // 4. In-flight stage (e.g. pending_receipt_verification) BUT verified DeliveryReceipt exists on site
        $creditPr->update(['status' => 'pending_receipt_verification']);
        
        $store = Store::firstOrCreate(
            ['project_id' => $this->guardedProject->id],
            ['name' => 'Main Site Store', 'code' => 'MSS-' . uniqid()]
        );

        DeliveryReceipt::create([
            'dr_no'               => 'DR-' . uniqid(),
            'purchase_request_id' => $creditPr->id,
            'store_id'            => $store->id,
            'received_by'         => $this->adminUser->id,
            'received_date'       => now()->toDateString(),
            'status'              => 'verified',
        ]);

        $itemsWithDr = collect($this->costService->getPaginated($this->guardedProject)->items());
        $this->assertTrue(
            $itemsWithDr->contains('reference_no', 'PR-' . $creditPr->id),
            "Credit PR with verified DeliveryReceipt MUST enter Associated Costs."
        );
    }

    /**
     * Test 5: Double counting prevention: Foreign keys and model constants.
     */
    public function test_foreign_key_and_constant_exclusions_prevent_double_counting(): void
    {
        // 1. Credit settlement ExpenseRequest (linked via credit_store_ledger_id foreign key)
        $creditSettlementFk = ExpenseRequest::create([
            'request_number'            => 'EXP-CR-FK-01',
            'user_id'                   => $this->adminUser->id,
            'project_id'                => $this->guardedProject->id,
            'category'                  => 'Material (Credit Settlement)',
            'other_reason'              => ExpenseRequest::REASON_CREDIT_SETTLEMENT,
            'description'               => 'Settling credit balance for supplier',
            'amount'                    => 25000.00,
            'credit_store_ledger_id'    => 1234, // Foreign key set
            'status'                    => 'Paid',
            'paid_at'                   => now(),
        ]);

        // 2. PR payment ExpenseRequest (linked via purchase_request_id foreign key)
        $prPaymentFk = ExpenseRequest::create([
            'request_number'            => 'EXP-PR-FK-02',
            'user_id'                   => $this->adminUser->id,
            'project_id'                => $this->guardedProject->id,
            'category'                  => 'Material',
            'description'               => 'Procurement PR payment voucher',
            'amount'                    => 35000.00,
            'purchase_request_id'       => 5678, // Foreign key set
            'status'                    => 'Paid',
            'paid_at'                   => now(),
        ]);

        // 3. Petty cash float top-up ExpenseRequest (linked via petty_cash_owner_id / constant)
        $floatTopUpFk = ExpenseRequest::create([
            'request_number'            => 'EXP-PCR-FK-03',
            'user_id'                   => $this->adminUser->id,
            'project_id'                => $this->guardedProject->id,
            'category'                  => 'Other',
            'other_reason'              => ExpenseRequest::REASON_PETTY_REPLENISHMENT,
            'description'               => 'Site Petty Cash Replenishment float top up',
            'amount'                    => 40000.00,
            'petty_cash_owner_id'       => $this->adminUser->id, // Foreign key set
            'status'                    => 'Paid',
            'paid_at'                   => now(),
        ]);

        // 4. Genuine operational cash voucher (no module FKs)
        $operationalVoucher = ExpenseRequest::create([
            'request_number'            => 'EXP-OPS-01',
            'user_id'                   => $this->adminUser->id,
            'project_id'                => $this->guardedProject->id,
            'category'                  => ExpenseRequest::CATEGORY_TRANSPORT,
            'description'               => 'Excavator lowbed trailer transport to site',
            'amount'                    => 12000.00,
            'credit_store_ledger_id'    => null,
            'purchase_request_id'       => null,
            'petty_cash_owner_id'       => null,
            'status'                    => 'Paid',
            'paid_at'                   => now(),
        ]);

        $paginated = $this->costService->getPaginated($this->guardedProject);
        $items = collect($paginated->items());

        $this->assertFalse($items->contains('reference_no', 'EXP-CR-FK-01'), 'Credit settlement FK must be excluded');
        $this->assertFalse($items->contains('reference_no', 'EXP-PR-FK-02'), 'PR payment FK must be excluded');
        $this->assertFalse($items->contains('reference_no', 'EXP-PCR-FK-03'), 'Petty cash top-up FK must be excluded');
        $this->assertTrue($items->contains('reference_no', 'EXP-OPS-01'), 'Independent operational voucher must appear');
    }

    /**
     * Test 6: Direct Expense exclusions: PR markers and Credit Settlement markers in expenses table.
     */
    public function test_direct_expense_markers_are_excluded(): void
    {
        // 1. Mirror expense created for PR
        $prExpenseMirror = Expense::create([
            'project_id'         => $this->guardedProject->id,
            'category'           => 'material',
            'description'        => Expense::EXCLUDED_PR_MARKER . ' #1042',
            'amount'             => 48000.00,
            'expense_date'       => now()->toDateString(),
            'status'             => 'approved',
            'budget_status'      => 'unguarded',
            'created_by'         => $this->adminUser->id,
        ]);

        // 2. Mirror expense created for credit settlement
        $creditExpenseMirror = Expense::create([
            'project_id'         => $this->guardedProject->id,
            'category'           => 'material',
            'description'        => Expense::EXCLUDED_CREDIT_MARKER . '1042 (Supplier)',
            'amount'             => 32000.00,
            'expense_date'       => now()->toDateString(),
            'status'             => 'approved',
            'budget_status'      => 'unguarded',
            'created_by'         => $this->adminUser->id,
        ]);

        $paginated = $this->costService->getPaginated($this->guardedProject);
        $items = collect($paginated->items());

        $this->assertFalse($items->contains('reference_no', 'EXP-' . $prExpenseMirror->id));
        $this->assertFalse($items->contains('reference_no', 'EXP-' . $creditExpenseMirror->id));
    }

    /**
     * Test 7: Cross-project isolation: Project B expenses never appear under Project A.
     */
    public function test_cross_project_isolation(): void
    {
        $projectB = Project::create([
            'name'             => 'Project B ' . uniqid(),
            'code'             => 'PB-' . strtoupper(uniqid()),
            'status'           => 'active',
            'budget_allocated' => 100000.00,
            'budget_consumed'  => 0.00,
            'created_by'       => $this->adminUser->id,
        ]);

        $expenseProjectB = ExpenseRequest::create([
            'request_number' => 'EXP-PROJ-B-01',
            'user_id'        => $this->adminUser->id,
            'project_id'     => $projectB->id,
            'category'       => 'Logistics',
            'description'    => 'Truck transport for Project B',
            'amount'         => 18000.00,
            'status'         => 'Paid',
            'paid_at'        => now(),
        ]);

        $paginated = $this->costService->getPaginated($this->guardedProject);
        $this->assertFalse(collect($paginated->items())->contains('reference_no', 'EXP-PROJ-B-01'));
    }
}
