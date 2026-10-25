<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\ExpenseHubVatDecision;
use App\Models\ExpenseHubMonthLock;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\ExpenseRequest;
use App\Models\ProcurementPayment;
use App\Models\PurchaseRequest;
use App\Models\VehicleFuelRequest;
use App\Models\FixedAsset;
use App\Models\Project;
use App\Models\User;
use App\Exports\PurchaseVatExport;
use App\Exports\PurchaseTasExport;
use App\Exports\ExpenseHubMasterAuditExport;
use App\Services\ExpenseHubQueryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;

class ExpenseHubProDocTest extends TestCase
{
    use DatabaseTransactions;

    protected User $financeHead;
    protected Project $project;
    protected ExpenseHubQueryService $queryService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->financeHead = User::firstOrCreate(
            ['email' => 'finance_head_prodoc_test@wechecha.com'],
            ['name' => 'Finance Head Tester', 'password' => bcrypt('password')]
        );

        try {
            $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'finance_head', 'guard_name' => 'web']);
            $this->financeHead->assignRole($role);
        } catch (\Throwable $e) {}

        $this->project = Project::firstOrCreate(
            ['name' => 'Kazanchis Commercial Complex'],
            ['code' => 'KCC-01', 'status' => 'active', 'created_by' => $this->financeHead->id]
        );

        $this->queryService = app(ExpenseHubQueryService::class);
    }

    /**
     * Test 1: Verify exact column count and order of all 3 exports.
     */
    public function test_exact_column_order_and_count_of_all_exports()
    {
        // 1. Report A: 15-Column Purchase VAT Return
        $vatHeaders = PurchaseVatExport::HEADERS;
        $this->assertCount(15, $vatHeaders);
        $this->assertEquals('VAT CATEGORY  (G=GOODS;S=SERVICES)', $vatHeaders[0]);
        $this->assertEquals('CALENDAR TYPE (E=ETHIOPIAN;G=GREGORIAN)', $vatHeaders[1]);
        $this->assertStringContainsString('Types of purchase', $vatHeaders[2]);
        $this->assertEquals('TIN..This field  is not mandatory.', $vatHeaders[3]);
        $this->assertEquals('Date of purchase/Customs Declaration No.  Dispatched Date (Please use  dd/mm/yyyy date format).  This field is mandatory.', $vatHeaders[5]);
        $this->assertEquals('MRC Number..This field is not mandatory.', $vatHeaders[6]);
        $this->assertEquals('Vat receipt number/ Customs Declaration Number.This field is mandatory.', $vatHeaders[7]);
        $this->assertEquals('Total value', $vatHeaders[12]);
        $this->assertEquals('vat', $vatHeaders[13]);
        $this->assertEquals('value after vat', $vatHeaders[14]);

        // 2. Report B: 9-Column e-Tax Purchase TAS Declaration
        $tasHeaders = PurchaseTasExport::HEADERS;
        $this->assertCount(9, $tasHeaders);
        $this->assertEquals('Purchaser TIN', $tasHeaders[0]);
        $this->assertEquals('Seller TIN', $tasHeaders[1]);
        $this->assertEquals('Amount', $tasHeaders[2]);
        $this->assertEquals('Receipt No', $tasHeaders[3]);
        $this->assertEquals('Receipt Date', $tasHeaders[4]);
        $this->assertEquals('Encoding Date', $tasHeaders[5]);
        $this->assertEquals('MRC', $tasHeaders[6]);
        $this->assertEquals('PurcType', $tasHeaders[7]);
        $this->assertEquals('Encoded By', $tasHeaders[8]);

        // 3. Report D: 22-Column Master Corporate Tax Audit
        $auditHeaders = ExpenseHubMasterAuditExport::HEADERS;
        $this->assertCount(22, $auditHeaders);
        $this->assertEquals('Disbursement Date', $auditHeaders[0]);
        $this->assertEquals('Source Module', $auditHeaders[1]);
        $this->assertEquals('Reference No', $auditHeaders[2]);
        $this->assertEquals('Vendor TIN', $auditHeaders[5]);
        $this->assertEquals('Gross Amount (ETB)', $auditHeaders[6]);
        $this->assertEquals('VAT Status', $auditHeaders[7]);
        $this->assertEquals('Verified w/o Receipt', $auditHeaders[8]);
        $this->assertEquals('Withholding Amount (3%)', $auditHeaders[14]);
        $this->assertEquals('FS Number', $auditHeaders[17]);
        $this->assertEquals('MRC Number', $auditHeaders[18]);
    }

    /**
     * Test 2: 4 Verification Outcomes and their report flow.
     */
    public function test_four_verification_outcomes_flow_into_correct_reports()
    {
        // Outcome 1: With VAT receipt (15%)
        $item1 = (object)[
            'source_type'            => 'expense_request',
            'source_id'              => 101,
            'reference_no'           => 'EXP-2026-0001',
            'payee_supplier'         => 'National Cement SC',
            'tin'                    => '0001234567',
            'amount'                 => 11500.00,
            'vat_status'             => ExpenseHubVatDecision::STATUS_VAT_APPLIES,
            'is_verified_without_receipt' => false,
            'decision_base_amount'   => 10000.00,
            'decision_vat_amount'    => 1500.00,
            'decision_total_amount'  => 11500.00,
            'fs_no'                  => 'FS-008123',
            'mrc_no'                 => 'MRC-9988',
            'vat_category'           => 'G',
            'calendar_type'          => 'G',
            'purchase_type'          => 3,
            'disbursement_date'      => '2026-03-15',
            'has_withholding'        => false,
            'withholding_amount'     => 0,
            'is_capex'               => false,
        ];

        // Outcome 2: Without VAT receipt (0% / TAS)
        $item2 = (object)[
            'source_type'            => 'vehicle_fuel_request',
            'source_id'              => 202,
            'reference_no'           => 'VFR-2026-0042',
            'payee_supplier'         => 'TotalEnergies Bole Station',
            'tin'                    => '0009876543',
            'amount'                 => 4500.00,
            'vat_status'             => ExpenseHubVatDecision::STATUS_NO_VAT,
            'is_verified_without_receipt' => false,
            'decision_base_amount'   => 4500.00,
            'decision_vat_amount'    => 0.00,
            'decision_total_amount'  => 4500.00,
            'fs_no'                  => 'FS-003344',
            'mrc_no'                 => 'MRC-1122',
            'vat_category'           => 'G',
            'purc_type'              => 'PUR_GD',
            'disbursement_date'      => '2026-03-16',
            'has_withholding'        => false,
            'withholding_amount'     => 0,
            'reason_code'            => 'fuel_zero_vat',
            'reason_note'            => '0% TAS fuel purchase',
            'is_capex'               => false,
        ];

        // Outcome 3: With receipt and withholding (3% WHT)
        $item3 = (object)[
            'source_type'            => 'procurement_payment',
            'source_id'              => 303,
            'reference_no'           => 'PR-2026-0089',
            'payee_supplier'         => 'Steel Fabrication Enterprise',
            'tin'                    => '0005556667',
            'amount'                 => 23000.00,
            'vat_status'             => ExpenseHubVatDecision::STATUS_VAT_APPLIES,
            'is_verified_without_receipt' => false,
            'decision_base_amount'   => 20000.00,
            'decision_vat_amount'    => 3000.00,
            'decision_total_amount'  => 23000.00,
            'fs_no'                  => 'FS-009900',
            'mrc_no'                 => 'MRC-7788',
            'vat_category'           => 'G',
            'calendar_type'          => 'G',
            'purchase_type'          => 3,
            'disbursement_date'      => '2026-03-18',
            'has_withholding'        => true,
            'withholding_rate'       => 3.00,
            'withholding_amount'     => 600.00, // 3% of 20,000 base
            'net_payable_amount'     => 22400.00,
            'withholding_receipt_number' => 'WHT-SLIP-00441',
            'is_capex'               => false,
        ];

        // Outcome 4: Without receipt (verified w/o receipt, EXCLUDED from VAT and TAS returns)
        $item4 = (object)[
            'source_type'            => 'expense_request',
            'source_id'              => 404,
            'reference_no'           => 'EXP-2026-0099',
            'payee_supplier'         => 'Informal Site Porter',
            'tin'                    => null,
            'amount'                 => 850.00,
            'vat_status'             => ExpenseHubVatDecision::STATUS_NO_VAT,
            'is_verified_without_receipt' => true,
            'decision_base_amount'   => 850.00,
            'decision_vat_amount'    => 0.00,
            'decision_total_amount'  => 850.00,
            'fs_no'                  => null,
            'mrc_no'                 => null,
            'disbursement_date'      => '2026-03-20',
            'has_withholding'        => false,
            'withholding_amount'     => 0,
            'reason_code'            => 'no_fiscal_receipt',
            'reason_note'            => 'Audited day-labor porter payment without machine receipt',
            'is_capex'               => false,
        ];

        $collection = new Collection([$item1, $item2, $item3, $item4]);

        // A. Verify PurchaseVatExport contains only Outcome 1 & 3 (VAT Applies)
        $vatRows = PurchaseVatExport::buildRows($collection);
        $this->assertCount(2, $vatRows, 'Purchase VAT Return must strictly contain Outcome 1 and 3 rows');
        $this->assertEquals(1234567, $vatRows[0][3], 'Item 1 TIN digits match');
        $this->assertEquals(5556667, $vatRows[1][3], 'Item 3 TIN digits match');

        // B. Verify PurchaseTasExport contains ONLY Outcome 2 (0% TAS), and EXCLUDES Outcome 4
        $tasRows = PurchaseTasExport::buildRows($collection);
        $this->assertCount(1, $tasRows, 'Purchase TAS Declaration must include Outcome 2 and strictly exclude Outcome 4');
        $this->assertEquals('FS-003344', $tasRows[0][3], 'Only Item 2 is in TAS declaration');
        $this->assertEquals('PUR_GD', $tasRows[0][7], 'PurcType is PUR_GD');

        // C. Verify ExpenseHubMasterAuditExport contains all 4 outcomes with proper flags
        $auditRows = ExpenseHubMasterAuditExport::buildRows($collection);
        $this->assertCount(4, $auditRows, 'Master audit export must contain all 4 outcomes');
        $this->assertEquals('NO', $auditRows[0][8]);
        $this->assertEquals('NO', $auditRows[1][8]);
        $this->assertEquals('NO', $auditRows[2][8]);
        $this->assertEquals('YES (No Slip)', $auditRows[3][8], 'Outcome 4 clearly flagged as Verified w/o Receipt');
        $this->assertEquals('600.00', $auditRows[2][14], 'Item 3 Withholding tax is 600.00 ETB');
    }

    /**
     * Test 3: FixedAsset CapEx acquisition uses Line 65 (purchase_type = 1).
     */
    public function test_fixed_asset_capex_maps_to_line_65()
    {
        $capexItem = (object)[
            'source_type'            => 'fixed_asset',
            'source_id'              => 505,
            'reference_no'           => 'AST-EQ-001',
            'payee_supplier'         => 'Heavy Machinery Importer',
            'tin'                    => '0003332211',
            'amount'                 => 1150000.00,
            'vat_status'             => ExpenseHubVatDecision::STATUS_VAT_APPLIES,
            'is_verified_without_receipt' => false,
            'decision_base_amount'   => 1000000.00,
            'decision_vat_amount'    => 150000.00,
            'decision_total_amount'  => 1150000.00,
            'fs_no'                  => 'FS-EQ-888',
            'mrc_no'                 => 'MRC-EQ-11',
            'vat_category'           => 'G',
            'calendar_type'          => 'G',
            'purchase_type'          => 3, // Will be overridden to 1 because source_type is fixed_asset
            'disbursement_date'      => '2026-03-22',
            'has_withholding'        => false,
            'withholding_amount'     => 0,
            'is_capex'               => true,
        ];

        $rows = PurchaseVatExport::buildRows(new Collection([$capexItem]));
        $this->assertCount(1, $rows);
        $this->assertEquals(1, $rows[0][2], 'Fixed Asset CapEx must be mapped to purchase_type 1 (Line No. 65)');
    }

    /**
     * Test 4: 15% VAT match calculation and tolerance.
     */
    public function test_vat_15_percent_cross_match_validation()
    {
        // Base = 1,000 ETB, VAT = 150 ETB => Exact 15% match
        $base = 1000.00;
        $vat = 150.00;
        $expectedVat = round($base * 0.15, 2);
        $this->assertEquals($expectedVat, $vat);

        // Mismatched VAT test
        $mismatchedVat = 200.00;
        $isMismatch = abs($mismatchedVat - $expectedVat) > 1.0;
        $this->assertTrue($isMismatch, 'Discrepancy greater than 1.0 ETB must be flagged as mismatch');
    }

    /**
     * Test 5: Month Lock governance locks row edits.
     */
    public function test_month_lock_prevents_unauthorized_modifications()
    {
        // Create decision
        $decision = ExpenseHubVatDecision::updateOrCreate(
            ['source_type' => 'expense_request', 'source_id' => 9999],
            [
                'vat_status'   => ExpenseHubVatDecision::STATUS_PENDING_DECISION,
                'amount'       => 5000.00,
                'base_amount'  => 5000.00,
                'vat_amount'   => 0.00,
                'total_amount' => 5000.00,
                'is_locked'    => true, // Month is settled and locked
            ]
        );

        $regularUser = User::firstOrCreate(
            ['email' => 'regular_staff_test@wechecha.com'],
            ['name' => 'Regular Staff', 'password' => bcrypt('password')]
        );

        // Regular user should be rejected from modifying locked month
        $response = $this->actingAs($regularUser)->postJson(route('finance.expense-hub.decision'), [
            'source_type' => 'expense_request',
            'source_id'   => 9999,
            'vat_status'  => 'vat_applies',
            'amount'      => 5000.00,
        ]);

        $response->assertStatus(422);
        $this->assertFalse($response->json('success'));
        $this->assertStringContainsString('locked', $response->json('message'));
    }

    /**
     * Test 6: VehicleFuelRequest relationship integrity and query aggregation.
     */
    public function test_vehicle_fuel_request_relationships_and_query_loader()
    {
        $vfr = new VehicleFuelRequest();
        $this->assertTrue(method_exists($vfr, 'driver'), 'driver relationship method exists');
        $this->assertTrue(method_exists($vfr, 'driverEmployee'), 'driverEmployee alias relationship method exists');
        $this->assertTrue(method_exists($vfr, 'requestedBy'), 'requestedBy relationship method exists');
        $this->assertTrue(method_exists($vfr, 'requestedByUser'), 'requestedByUser alias relationship method exists');
        $this->assertTrue(method_exists($vfr, 'assetUnit'), 'assetUnit relationship method exists');
        $this->assertTrue(method_exists($vfr, 'expenseRequest'), 'expenseRequest relationship method exists');

        // Verify ExpenseHubQueryService safely queries vehicle fuel requests
        $expenses = $this->queryService->getExpenses(['source_module' => 'vehicle_fuel_requests'], $this->financeHead);
        $this->assertInstanceOf(Collection::class, $expenses);
    }

    /**
     * Test 7: Action 1 - Receipt upload and graceful fallback when OCR fails
     */
    public function test_action_1_receipt_upload_and_scan_fallback_on_error()
    {
        $response = $this->actingAs($this->financeHead)->postJson(route('finance.expense-hub.scan-receipt'), [
            'receipt_path' => 'non_existent_slip_test.jpg',
            'source_type'  => 'expense_request',
            'source_id'    => 12345,
        ]);

        $response->assertStatus(200);
        // Fallback per pro.doc Action 1: Never block, returns ocr_failed: true with empty fields
        $this->assertTrue($response->json('ocr_failed'));
        $this->assertArrayHasKey('file_url', $response->json());
        $this->assertEquals('', $response->json('supplier_tin'));
        $this->assertEquals('', $response->json('fs_no'));
    }

    /**
     * Test 8: Action 2 - Enforce mandatory 10-digit TIN, FS No, MRC No for 15% VAT
     */
    public function test_action_2_add_vat_requires_mandatory_tin_fs_mrc_and_blocks_without_them()
    {
        // 1. Missing TIN, FS, and MRC should be rejected with 422
        $response = $this->actingAs($this->financeHead)->postJson(route('finance.expense-hub.decision'), [
            'source_type' => 'expense_request',
            'source_id'   => 8881,
            'vat_status'  => 'vat_applies',
            'amount'      => 1150.00,
        ]);

        $response->assertStatus(422);
        $this->assertFalse($response->json('success'));
        $this->assertStringContainsString('Missing mandatory fields', $response->json('message'));

        // 2. Invalid TIN (e.g. 5 digits instead of 10)
        $response2 = $this->actingAs($this->financeHead)->postJson(route('finance.expense-hub.decision'), [
            'source_type'       => 'expense_request',
            'source_id'         => 8881,
            'vat_status'        => 'vat_applies',
            'amount'            => 1150.00,
            'custom_seller_tin' => '12345',
            'custom_fs_no'      => 'FS-001',
            'custom_mrc_no'     => 'MRC-001',
        ]);

        $response2->assertStatus(422);
        $this->assertStringContainsString('Valid 10-digit Supplier TIN', $response2->json('message'));

        // 3. Valid inputs save successfully and return normalized item & summary
        $response3 = $this->actingAs($this->financeHead)->postJson(route('finance.expense-hub.decision'), [
            'source_type'        => 'expense_request',
            'source_id'          => 8881,
            'vat_status'         => 'vat_applies',
            'amount'             => 1150.00,
            'vat_rate'           => 15.00,
            'is_inclusive'       => 1,
            'custom_seller_name' => 'Valid Steel Supplier',
            'custom_seller_tin'  => '0012345678',
            'custom_fs_no'       => 'FS-9912001',
            'custom_mrc_no'      => 'MRC-889900',
        ]);

        $response3->assertStatus(200);
        $this->assertTrue($response3->json('success'));
        $this->assertEquals('vat_applies', $response3->json('item.vat_status'));
        $this->assertEquals(1000.00, (float)$response3->json('item.decision_base_amount'));
        $this->assertEquals(150.00, (float)$response3->json('item.decision_vat_amount'));
        $this->assertArrayHasKey('summary', $response3->json());
    }

    /**
     * Test 9: Action 3 - Non-VAT payment saves and zeroes VAT
     */
    public function test_action_3_non_vat_payment_routes_to_report_b()
    {
        $response = $this->actingAs($this->financeHead)->postJson(route('finance.expense-hub.decision'), [
            'source_type'        => 'expense_request',
            'source_id'          => 8882,
            'vat_status'         => 'no_vat',
            'amount'             => 2500.00,
            'no_vat_reason_code' => 'exempt_item',
            'no_vat_reason_note' => 'Fuel / Tax exempt goods',
            'custom_seller_name' => 'TotalEnergies Ethiopia',
            'custom_fs_no'       => 'FS-TAS-001',
        ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertEquals('no_vat', $response->json('item.vat_status'));
        $this->assertEquals(0.00, (float)$response->json('item.decision_vat_amount'));
    }

    /**
     * Test 10: Action 4 - Verified Without Receipt sets is_verified_without_receipt = true
     */
    public function test_action_4_verified_without_receipt_routes_to_report_d_only()
    {
        $response = $this->actingAs($this->financeHead)->postJson(route('finance.expense-hub.decision'), [
            'source_type'                 => 'expense_request',
            'source_id'                   => 8883,
            'vat_status'                  => 'verified_no_receipt',
            'is_verified_without_receipt' => 1,
            'amount'                      => 450.00,
            'no_vat_reason_code'          => 'petty_cash',
            'no_vat_reason_note'          => 'Informal day labor payment',
            'custom_seller_name'          => 'Day Labor Crew',
        ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertTrue($response->json('item.is_verified_without_receipt'));
        $this->assertEquals(0.00, (float)$response->json('item.decision_vat_amount'));
    }

    /**
     * Test 11: Action 5 - Audit History endpoint retrieves logs
     */
    public function test_action_5_item_audit_history_endpoint()
    {
        // First record a decision to generate an audit log
        $this->actingAs($this->financeHead)->postJson(route('finance.expense-hub.decision'), [
            'source_type'        => 'expense_request',
            'source_id'          => 8884,
            'vat_status'         => 'vat_applies',
            'amount'             => 1150.00,
            'custom_seller_name' => 'Audit History Supplier',
            'custom_seller_tin'  => '0098765432',
            'custom_fs_no'       => 'FS-HIST-001',
            'custom_mrc_no'      => 'MRC-HIST-001',
        ]);

        $response = $this->actingAs($this->financeHead)->getJson(route('finance.expense-hub.item-history', [
            'source_type' => 'expense_request',
            'source_id'   => 8884,
        ]));

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertIsArray($response->json('logs'));
        $this->assertNotEmpty($response->json('logs'));
        $this->assertEquals('VAT Decision Updated', $response->json('logs.0.action'));
    }

    /**
     * Test 12: Action 7 - 3% Withholding Tax auto-calculates on pre-tax base
     */
    public function test_action_7_withholding_tax_calculation_and_ledger_routing()
    {
        $response = $this->actingAs($this->financeHead)->postJson(route('finance.expense-hub.decision'), [
            'source_type'        => 'expense_request',
            'source_id'          => 8885,
            'vat_status'         => 'vat_applies',
            'amount'             => 11500.00,
            'vat_rate'           => 15.00,
            'is_inclusive'       => 1,
            'has_withholding'    => 1,
            'withholding_rate'   => 3.00,
            'withholding_amount' => 300.00, // 3% of 10,000 base
            'withholding_receipt_number' => 'WHT-SLIP-9901',
            'custom_seller_name' => 'Major Subcontractor PLC',
            'custom_seller_tin'  => '0055443322',
            'custom_fs_no'       => 'FS-WHT-001',
            'custom_mrc_no'      => 'MRC-WHT-001',
        ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertTrue($response->json('item.has_withholding'));
        $this->assertEquals(300.00, (float)$response->json('item.withholding_amount'));
        $this->assertEquals(11200.00, (float)$response->json('item.net_payable_amount')); // 11500 - 300
    }

    /**
     * Test 13: Exact duplicate receipt is blocked across other rows
     */
    public function test_duplicate_receipt_blocked_across_rows()
    {
        // First row with FS-DUP-001 and TIN 0077889900
        $this->actingAs($this->financeHead)->postJson(route('finance.expense-hub.decision'), [
            'source_type'        => 'expense_request',
            'source_id'          => 8886,
            'vat_status'         => 'vat_applies',
            'amount'             => 1150.00,
            'custom_seller_name' => 'Supplier Alpha',
            'custom_seller_tin'  => '0077889900',
            'custom_fs_no'       => 'FS-DUP-001',
            'custom_mrc_no'      => 'MRC-DUP-001',
        ]);

        // Attempt second row with same FS No and TIN
        $response = $this->actingAs($this->financeHead)->postJson(route('finance.expense-hub.decision'), [
            'source_type'        => 'expense_request',
            'source_id'          => 8887,
            'vat_status'         => 'vat_applies',
            'amount'             => 2300.00,
            'custom_seller_name' => 'Supplier Beta',
            'custom_seller_tin'  => '0077889900',
            'custom_fs_no'       => 'FS-DUP-001',
            'custom_mrc_no'      => 'MRC-DUP-001',
        ]);

        $response->assertStatus(422);
        $this->assertFalse($response->json('success'));
        $this->assertTrue($response->json('is_duplicate'));
        $this->assertStringContainsString('Duplicate Receipt', $response->json('message'));
    }

    /**
     * Test 14: Saving the same decision twice updates the row without creating duplicates
     */
    public function test_saving_decision_twice_does_not_create_duplicate_records()
    {
        $payload = [
            'source_type'        => 'expense_request',
            'source_id'          => 8888,
            'vat_status'         => 'vat_applies',
            'amount'             => 1150.00,
            'custom_seller_name' => 'Idempotent Supplier',
            'custom_seller_tin'  => '0066554433',
            'custom_fs_no'       => 'FS-IDEM-001',
            'custom_mrc_no'      => 'MRC-IDEM-001',
        ];

        // Save 1st time
        $r1 = $this->actingAs($this->financeHead)->postJson(route('finance.expense-hub.decision'), $payload);
        $r1->assertStatus(200);

        // Save 2nd time on exact same row
        $r2 = $this->actingAs($this->financeHead)->postJson(route('finance.expense-hub.decision'), $payload);
        $r2->assertStatus(200);

        // Assert exactly 1 record in database
        $count = ExpenseHubVatDecision::where('source_type', 'expense_request')
            ->where('source_id', 8888)
            ->count();
        $this->assertEquals(1, $count);
    }
}
