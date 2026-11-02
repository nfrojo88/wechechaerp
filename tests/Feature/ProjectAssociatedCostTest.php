<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\CreditStoreLedger;
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
            'project_id'   => $this->unguardedProject->id,
            'category'     => 'material',
            'description'  => 'Emergency Sand on Unguarded Site',
            'amount'       => 4500.00,
            'expense_date' => now()->toDateString(),
            'status'       => 'approved',
            'created_by'   => $this->adminUser->id,
        ]);

        $paginated = $this->costService->getPaginated($this->unguardedProject);
        $summary = $this->costService->getSummary($this->unguardedProject);

        $this->assertTrue(collect($paginated->items())->contains('reference_no', 'EXP-' . $expense->id));
        $this->assertEquals(4500.00, $summary['total_associated_cost']);
    }

    /**
     * Test 2: An expense counted in budget_consumed must NOT appear in Associated Costs (no double counting).
     */
    public function test_expense_counted_in_budget_consumed_must_not_appear(): void
    {
        // An expense created on guarded project with budget_status = 'safe'
        $budgetedExpense = Expense::create([
            'project_id'    => $this->guardedProject->id,
            'category'      => 'overhead',
            'description'   => 'Office Printing Consumed in Budget',
            'amount'        => 3000.00,
            'expense_date'  => now()->toDateString(),
            'status'        => 'approved',
            'budget_status' => 'safe', // Recorded through BudgetGuard
            'created_by'    => $this->adminUser->id,
        ]);

        $paginated = $this->costService->getPaginated($this->guardedProject);

        $this->assertFalse(collect($paginated->items())->contains('reference_no', 'EXP-' . $budgetedExpense->id));
    }

    /**
     * Test 3: A credit purchase that is pending does NOT appear in Associated Costs.
     */
    public function test_credit_purchase_that_is_pending_does_not_appear(): void
    {
        // A PR still pending planning review
        $pendingPr = PurchaseRequest::create([
            'project_id'   => $this->guardedProject->id,
            'requested_by' => $this->adminUser->id,
            'title'        => 'Pending PR for Bricks',
            'status'       => 'pending_planning',
        ]);

        // A Credit Store Ledger entry linked to the pending PR with no authorized date
        $pendingCredit = CreditStoreLedger::create([
            'purchase_request_id' => $pendingPr->id,
            'pr_no'               => 'PR-' . $pendingPr->id,
            'project_id'          => $this->guardedProject->id,
            'supplier_name'       => 'Pending Supplier Ltd',
            'credit_amount'       => 85000.00,
            'status'              => 'outstanding',
            'authorized_at'       => null,
            'created_by'          => $this->adminUser->id,
        ]);

        $paginated = $this->costService->getPaginated($this->guardedProject);

        $this->assertFalse(collect($paginated->items())->contains('reference_no', 'PR-' . $pendingPr->id));
    }

    /**
     * Test 4: A realized credit purchase where goods are received appears in Associated Costs.
     */
    public function test_credit_purchase_with_received_goods_appears(): void
    {
        $receivedPr = PurchaseRequest::create([
            'project_id'   => $this->guardedProject->id,
            'requested_by' => $this->adminUser->id,
            'title'        => 'Received Concrete Blocks on Credit',
            'status'       => 'intake_complete', // Store intake confirmed
        ]);

        $validCredit = CreditStoreLedger::create([
            'purchase_request_id' => $receivedPr->id,
            'pr_no'               => 'PR-' . $receivedPr->id,
            'project_id'          => $this->guardedProject->id,
            'supplier_name'       => 'Reliable Block Suppliers',
            'credit_amount'       => 62000.00,
            'status'              => 'outstanding',
            'authorized_at'       => now(),
            'created_by'          => $this->adminUser->id,
        ]);

        $paginated = $this->costService->getPaginated($this->guardedProject);
        $summary = $this->costService->getSummary($this->guardedProject);

        $this->assertTrue(collect($paginated->items())->contains('reference_no', 'PR-' . $receivedPr->id));
        $this->assertGreaterThanOrEqual(62000.00, $summary['total_associated_cost']);
        $this->assertGreaterThanOrEqual(62000.00, $summary['total_material_cost']);
    }

    /**
     * Test 5: Double counting prevention: Credit settlements and float top-ups are excluded.
     */
    public function test_credit_settlement_and_float_top_up_are_excluded(): void
    {
        // 1. Credit settlement ExpenseRequest
        $creditSettlementReq = ExpenseRequest::create([
            'request_number'            => 'EXP-CR-TEST-99',
            'user_id'                   => $this->adminUser->id,
            'project_id'                => $this->guardedProject->id,
            'category'                  => 'Material (Credit Settlement)',
            'other_reason'              => 'Credit Store Purchase Settlement',
            'description'               => 'Settling credit balance for supplier',
            'amount'                    => 20000.00,
            'credit_store_ledger_id'    => 999, // Linked to ledger
            'status'                    => 'Paid',
            'paid_at'                   => now(),
        ]);

        // 2. Petty cash float top-up ExpenseRequest
        $floatTopUpReq = ExpenseRequest::create([
            'request_number'            => 'EXP-PCR-TEST-88',
            'user_id'                   => $this->adminUser->id,
            'project_id'                => $this->guardedProject->id,
            'category'                  => 'Other',
            'other_reason'              => 'Petty Cash Replenishment',
            'description'               => 'Site Petty Cash Replenishment float top up',
            'amount'                    => 50000.00,
            'status'                    => 'Paid',
            'paid_at'                   => now(),
        ]);

        // 3. Normal site expense (e.g. municipal site permit)
        $normalExpenseReq = ExpenseRequest::create([
            'request_number'            => 'EXP-PERMIT-01',
            'user_id'                   => $this->adminUser->id,
            'project_id'                => $this->guardedProject->id,
            'category'                  => 'Site Permits',
            'description'               => 'Municipal Construction Site Permit Fee',
            'amount'                    => 7500.00,
            'status'                    => 'Paid',
            'paid_at'                   => now(),
        ]);

        $paginated = $this->costService->getPaginated($this->guardedProject);
        $items = collect($paginated->items());

        $this->assertFalse($items->contains('reference_no', 'EXP-CR-TEST-99'), 'Credit settlement must be excluded to prevent double counting');
        $this->assertFalse($items->contains('reference_no', 'EXP-PCR-TEST-88'), 'Float top-up must be excluded to prevent double counting');
        $this->assertTrue($items->contains('reference_no', 'EXP-PERMIT-01'), 'Normal site expense must appear');
    }

    /**
     * Test 6: Cross-project isolation: Project B expenses never appear under Project A.
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
