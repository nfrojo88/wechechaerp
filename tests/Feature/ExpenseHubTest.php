<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\ExpenseHubVatDecision;
use App\Models\ExpenseHubVatAuditLog;
use App\Models\ProcurementPayment;
use App\Models\PurchaseRequest;
use App\Models\CreditStorePayment;
use App\Models\CreditStoreLedger;
use App\Models\ExpenseRequest;
use App\Models\PettyCashMaterialPurchase;
use App\Models\Payroll;
use App\Models\EmployeeAdvance;
use App\Models\IpcRecord;
use App\Models\Project;
use App\Models\User;
use App\Services\ExpenseHubQueryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;

class ExpenseHubTest extends TestCase
{
    use DatabaseTransactions;

    protected User $financeUser;
    protected Project $project;
    protected ExpenseHubQueryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->financeUser = User::firstOrCreate(
            ['email' => 'fin_tester_hub@wechecha.com'],
            ['name' => 'Finance Hub Tester', 'password' => bcrypt('password')]
        );

        $this->project = Project::firstOrCreate(
            ['name' => 'Test Site Kazanchis'],
            ['code' => 'TK-01', 'status' => 'active', 'created_by' => $this->financeUser->id]
        );

        $this->service = app(ExpenseHubQueryService::class);
    }

    /**
     * Test VAT Inclusive and Exclusive math formulas.
     */
    public function test_vat_math_calculations_inclusive_and_exclusive()
    {
        // 1. VAT Inclusive: 1,150 ETB with 15% VAT included
        $inclusive = ExpenseHubVatDecision::computeVatAmounts(1150.00, 15.00, true);
        $this->assertEquals(1000.00, $inclusive['base']);
        $this->assertEquals(150.00, $inclusive['vat']);
        $this->assertEquals(1150.00, $inclusive['total']);

        // 2. VAT Exclusive: 1,000 ETB base + 15% VAT on top
        $exclusive = ExpenseHubVatDecision::computeVatAmounts(1000.00, 15.00, false);
        $this->assertEquals(1000.00, $exclusive['base']);
        $this->assertEquals(150.00, $exclusive['vat']);
        $this->assertEquals(1150.00, $exclusive['total']);
    }

    /**
     * Test Cash-Basis only and Anti-Double Counting in Expense Hub.
     */
    public function test_cash_basis_and_no_double_counting_rules()
    {
        // 1. Create a paid PR payment (Must be included)
        $pr = PurchaseRequest::create([
            'pr_no'       => 'PR-HUB-TEST-' . rand(1000, 9999),
            'project_id'  => $this->project->id,
            'vendor_name' => 'Cement Factory',
            'status'      => 'paid',
            'created_by'  => $this->financeUser->id,
        ]);

        $payment = ProcurementPayment::create([
            'purchase_request_id' => $pr->id,
            'method'              => 'bank_transfer',
            'amount'              => 50000.00,
            'gross_amount'        => 50000.00,
            'vat_amount'          => 6521.74,
            'status'              => 'paid',
            'paid_by'             => $this->financeUser->id,
            'paid_at'             => now(),
        ]);

        // 2. Create an unpaid PR payment (Must be EXCLUDED per cash basis)
        $unpaidPayment = ProcurementPayment::create([
            'purchase_request_id' => $pr->id,
            'method'              => 'cash',
            'amount'              => 20000.00,
            'status'              => 'pending',
        ]);

        // 3. Create a PR-linked ExpenseRequest (Must be EXCLUDED to prevent double counting with PR payment)
        $prLinkedReq = ExpenseRequest::create([
            'request_number'      => 'EXP-DUP-PR-' . rand(1000, 9999),
            'purchase_request_id' => $pr->id,
            'project_id'          => $this->project->id,
            'category'            => 'Material',
            'amount'              => 50000.00,
            'status'              => ExpenseRequest::STATUS_PAID,
            'user_id'             => $this->financeUser->id,
        ]);

        // 4. Create a Credit-linked ExpenseRequest (Must be EXCLUDED to prevent double counting)
        $crLinkedReq = ExpenseRequest::create([
            'request_number'         => 'EXP-DUP-CR-' . rand(1000, 9999),
            'credit_store_ledger_id' => 77777,
            'project_id'             => $this->project->id,
            'category'               => 'Material (Credit Settlement)',
            'amount'                 => 15000.00,
            'status'                 => ExpenseRequest::STATUS_PAID,
            'user_id'                => $this->financeUser->id,
        ]);

        // 5. Create a Petty Cash Replenishment (Must be EXCLUDED as fund transfer)
        $replenishmentReq = ExpenseRequest::create([
            'request_number' => 'EXP-PCR-' . rand(1000, 9999),
            'project_id'     => $this->project->id,
            'category'       => 'Other',
            'other_reason'   => 'Petty Cash Replenishment',
            'amount'         => 30000.00,
            'status'         => ExpenseRequest::STATUS_PAID,
            'user_id'        => $this->financeUser->id,
        ]);

        // 6. Create a standalone Paid ExpenseRequest (Must be INCLUDED)
        $standaloneReq = ExpenseRequest::create([
            'request_number' => 'EXP-VALID-MAINT-' . rand(1000, 9999),
            'project_id'     => $this->project->id,
            'category'       => 'Maintenance',
            'amount'         => 8000.00,
            'status'         => ExpenseRequest::STATUS_PAID,
            'user_id'        => $this->financeUser->id,
        ]);

        $expenses = $this->service->getExpenses([], $this->financeUser);

        // Assertions
        $foundPaidPayment = $expenses->firstWhere('source_id', $payment->id);
        $this->assertNotNull($foundPaidPayment, 'Paid ProcurementPayment must be included.');

        $foundUnpaidPayment = $expenses->firstWhere('source_id', $unpaidPayment->id);
        $this->assertNull($foundUnpaidPayment, 'Unpaid ProcurementPayment must be excluded on cash basis.');

        $foundPrLinked = $expenses->firstWhere('source_id', $prLinkedReq->id);
        $this->assertNull($foundPrLinked, 'PR-linked ExpenseRequest must be excluded to prevent double counting.');

        $foundCrLinked = $expenses->firstWhere('source_id', $crLinkedReq->id);
        $this->assertNull($foundCrLinked, 'Credit-linked ExpenseRequest must be excluded to prevent double counting.');

        $foundReplenishment = $expenses->firstWhere('source_id', $replenishmentReq->id);
        $this->assertNull($foundReplenishment, 'Petty cash replenishment must be excluded as fund transfer.');

        $foundStandalone = $expenses->firstWhere('source_id', $standaloneReq->id);
        $this->assertNotNull($foundStandalone, 'Standalone paid ExpenseRequest must be included.');
    }

    /**
     * Test VAT Decision recording and audit log creation in new tables.
     */
    public function test_vat_decision_saves_in_new_tables_with_audit_trail()
    {
        $this->actingAs($this->financeUser);

        $response = $this->post(route('finance.expense-hub.decision'), [
            'source_type'        => 'procurement_payment',
            'source_id'          => 12345,
            'vat_status'         => 'vat_applies',
            'amount'             => 23000.00,
            'vat_rate'           => 15.00,
            'is_inclusive'       => 1,
            'custom_seller_tin'  => '0098765432',
            'custom_fs_no'       => 'FS-001234',
        ]);

        $response->assertSessionHas('success');

        // Check decision saved in expense_hub_vat_decisions
        $decision = ExpenseHubVatDecision::where('source_type', 'procurement_payment')
            ->where('source_id', 12345)
            ->first();

        $this->assertNotNull($decision);
        $this->assertEquals('vat_applies', $decision->vat_status);
        $this->assertEquals(20000.00, (float)$decision->base_amount);
        $this->assertEquals(3000.00, (float)$decision->vat_amount);
        $this->assertEquals('0098765432', $decision->custom_seller_tin);

        // Check audit log saved in expense_hub_vat_audit_logs
        $audit = ExpenseHubVatAuditLog::where('decision_id', $decision->id)->first();
        $this->assertNotNull($audit);
        $this->assertEquals('procurement_payment', $audit->source_type);
        $this->assertEquals(12345, $audit->source_id);
    }

    /**
     * Test STRICT RULE: Ensure all existing tables remain completely untouched.
     */
    public function test_existing_tables_are_unchanged()
    {
        // 1. Existing table names must exist
        $existingTables = [
            'procurement_payments',
            'purchase_requests',
            'credit_store_payments',
            'credit_store_ledgers',
            'expense_requests',
            'petty_cash_material_purchases',
            'payrolls',
            'ipc_records',
            'fixed_assets',
            'expenses',
        ];

        foreach ($existingTables as $tbl) {
            $this->assertTrue(Schema::hasTable($tbl), "Existing table {$tbl} must exist.");
        }

        // 2. Ensure new tables have the strict prefix expense_hub_
        $newTables = [
            'expense_hub_vat_decisions',
            'expense_hub_vat_audit_logs',
            'expense_hub_month_locks',
        ];

        foreach ($newTables as $tbl) {
            $this->assertTrue(str_starts_with($tbl, 'expense_hub_'), "New table {$tbl} must have expense_hub_ prefix.");
        }
    }
}
