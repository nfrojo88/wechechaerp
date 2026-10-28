<?php

namespace Tests\Unit;

use App\Http\Controllers\Finance\FinanceTaxReportController;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TaxMovementsOcrDuplicateCheckTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Create in-memory sqlite tables for receipts and receipt_items
        Schema::dropIfExists('receipt_items');
        Schema::dropIfExists('receipts');
        Schema::dropIfExists('system_settings');

        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('receipts', function (Blueprint $table) {
            $table->increments('id');
            $table->string('receipt_number')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->date('receipt_date')->nullable();
            $table->string('fs_no')->nullable();
            $table->string('fs_no_raw')->nullable();
            $table->string('mrc_no')->nullable();
            $table->string('vendor_name')->nullable();
            $table->string('vendor_tin')->nullable();
            $table->boolean('tin_valid')->default(false);
            $table->boolean('fs_no_valid')->default(false);
            $table->string('buyer_tin')->nullable();
            $table->string('tax_type')->nullable();
            $table->string('category')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('vat_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->string('file_path')->nullable();
            $table->string('file_type')->nullable();
            $table->text('notes')->nullable();
            $table->text('description')->nullable();
            $table->json('parsed_data')->nullable();
            $table->nullableMorphs('purchasable');
            $table->unsignedBigInteger('project_id')->nullable();
            $table->timestamps();
        });

        Schema::create('receipt_items', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('receipt_id');
            $table->string('item_description')->nullable();
            $table->string('vat_category')->default('G');
            $table->string('calendar_type')->default('G');
            $table->integer('purchase_type')->default(3);
            $table->string('uom')->default('9');
            $table->decimal('qty', 10, 2)->default(1.0);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('total_value', 15, 2)->default(0);
            $table->decimal('vat_amount', 15, 2)->default(0);
            $table->decimal('value_after_vat', 15, 2)->default(0);
            $table->string('purc_type')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Test duplicate rule:
     * - Identical FS No & Seller TIN -> First original receipt preserved, subsequent double receipt immediately blocked!
     * - Blocked double receipts are strictly excluded from matrix totals and NEVER added to Excel.
     * - Only FS No matches -> kept (not duplicate).
     * - Only Seller TIN matches -> kept (not duplicate).
     * - Missing/unreadable FS No or TIN -> kept, flagged as "Needs manual review".
     */
    public function test_ocr_receipts_deduplication_and_flagging_rules(): void
    {
        // October 2026 falls within Meskerem/Tikimt 2019 EC.
        // Let's create receipts in a specific date window: 2026-10-15
        $date1 = '2026-10-10'; // first original
        $date2 = '2026-10-20'; // double scan

        // 1. DUPLICATE PAIR: FS "00012345" and TIN "0012345678"
        // First original receipt (MUST BE KEPT)
        $r1 = Receipt::create([
            'receipt_number' => 'REC-001',
            'receipt_date'   => $date1,
            'fs_no'          => '00012345',
            'vendor_tin'     => '0012345678',
            'vendor_name'    => 'Vendor Alpha',
            'total_amount'   => 1150.00,
            'vat_amount'     => 150.00,
            'subtotal'       => 1000.00,
            'created_at'     => Carbon::parse('2026-10-10 10:00:00'),
        ]);
        ReceiptItem::create([
            'receipt_id'      => $r1->id,
            'item_description'=> 'Hardware Original First',
            'total_value'     => 1000.00,
            'vat_amount'      => 150.00,
            'value_after_vat' => 1150.00,
            'created_at'      => Carbon::parse('2026-10-10 10:00:00'),
        ]);

        // Double scan: FS normalized matches "FS-12345" -> "12345", TIN matches "00-1234-5678" -> "0012345678"
        // Uploaded later (MUST BE IMMEDIATELY BLOCKED & EXCLUDED)
        $r2 = Receipt::create([
            'receipt_number' => 'REC-002',
            'receipt_date'   => $date2,
            'fs_no'          => 'FS-12345',
            'vendor_tin'     => '00-1234-5678',
            'vendor_name'    => 'Vendor Alpha',
            'total_amount'   => 1150.00,
            'vat_amount'     => 150.00,
            'subtotal'       => 1000.00,
            'created_at'     => Carbon::parse('2026-10-20 14:00:00'),
        ]);
        ReceiptItem::create([
            'receipt_id'      => $r2->id,
            'item_description'=> 'Hardware Double Scan',
            'total_value'     => 1000.00,
            'vat_amount'      => 150.00,
            'value_after_vat' => 1150.00,
            'created_at'      => Carbon::parse('2026-10-20 14:00:00'),
        ]);

        // 2. SAME FS No ("12345") but DIFFERENT Seller TIN ("9988776655") -> NOT DUPLICATE (kept)
        $r3 = Receipt::create([
            'receipt_number' => 'REC-003',
            'receipt_date'   => $date1,
            'fs_no'          => '12345',
            'vendor_tin'     => '9988776655',
            'vendor_name'    => 'Vendor Beta',
            'total_amount'   => 575.00,
            'vat_amount'     => 75.00,
            'subtotal'       => 500.00,
            'created_at'     => Carbon::parse('2026-10-10 11:00:00'),
        ]);
        ReceiptItem::create([
            'receipt_id'      => $r3->id,
            'item_description'=> 'Different Vendor Same FS',
            'total_value'     => 500.00,
            'vat_amount'      => 75.00,
            'value_after_vat' => 575.00,
            'created_at'      => Carbon::parse('2026-10-10 11:00:00'),
        ]);

        // 3. SAME Seller TIN ("0012345678") but DIFFERENT FS No ("99999") -> NOT DUPLICATE (kept)
        $r4 = Receipt::create([
            'receipt_number' => 'REC-004',
            'receipt_date'   => $date1,
            'fs_no'          => '99999',
            'vendor_tin'     => '0012345678',
            'vendor_name'    => 'Vendor Alpha',
            'total_amount'   => 230.00,
            'vat_amount'     => 30.00,
            'subtotal'       => 200.00,
            'created_at'     => Carbon::parse('2026-10-10 12:00:00'),
        ]);
        ReceiptItem::create([
            'receipt_id'      => $r4->id,
            'item_description'=> 'Same Vendor Different FS',
            'total_value'     => 200.00,
            'vat_amount'      => 30.00,
            'value_after_vat' => 230.00,
            'created_at'      => Carbon::parse('2026-10-10 12:00:00'),
        ]);

        // 4. MISSING / UNREADABLE FS No ("-") -> Flag, don't guess! (kept in matrix, flagged)
        $r5 = Receipt::create([
            'receipt_number' => 'REC-005',
            'receipt_date'   => $date1,
            'fs_no'          => '-',
            'vendor_tin'     => '0012345678',
            'vendor_name'    => 'Vendor Unreadable FS',
            'total_amount'   => 345.00,
            'vat_amount'     => 45.00,
            'subtotal'       => 300.00,
            'created_at'     => Carbon::parse('2026-10-10 13:00:00'),
        ]);
        ReceiptItem::create([
            'receipt_id'      => $r5->id,
            'item_description'=> 'Unreadable FS item',
            'total_value'     => 300.00,
            'vat_amount'      => 45.00,
            'value_after_vat' => 345.00,
            'created_at'      => Carbon::parse('2026-10-10 13:00:00'),
        ]);

        // Determine Ethiopian Month for October 2026
        $ethDate = \App\Services\EthiopianDate::toEthiopian(Carbon::parse('2026-10-15'));
        $controller = new FinanceTaxReportController();
        $result = $controller->calculateMonthMovementsData($ethDate['year'], $ethDate['month'], true);

        // Verification of Summary Stats:
        // Total receipts scanned = 5
        // Duplicates removed = 1 (r2 blocked)
        // Unique receipts used = 4 (r1, r3, r4, r5)
        // Needs manual review = 1 (r5)
        $summary = $result['summary'];
        $this->assertEquals(5, $summary['ocr_total_scanned'], 'Total receipts scanned must be 5');
        $this->assertEquals(1, $summary['ocr_duplicates_removed'], 'Duplicates removed must be 1');
        $this->assertEquals(4, $summary['ocr_unique_used'], 'Unique receipts used must be 4');
        $this->assertEquals(1, $summary['ocr_needs_review_count'], 'Needs manual review count must be 1');

        // Check Cleaned Matrix: exactly 4 unique rows
        $this->assertCount(4, $result['ocr_items']);
        $keptIds = collect($result['ocr_items'])->pluck('receipt_id')->all();
        $this->assertContains($r1->id, $keptIds, 'First original receipt r1 must be kept');
        $this->assertNotContains($r2->id, $keptIds, 'Subsequent double receipt r2 must be blocked');
        $this->assertContains($r3->id, $keptIds, 'Different TIN receipt r3 must be kept');
        $this->assertContains($r4->id, $keptIds, 'Different FS receipt r4 must be kept');
        $this->assertContains($r5->id, $keptIds, 'Unreadable receipt r5 must be kept in matrix');

        // Check that first kept receipt has has_duplicates_blocked = true
        $r1Cleaned = collect($result['ocr_items'])->firstWhere('receipt_id', $r1->id);
        $this->assertNotNull($r1Cleaned);
        $this->assertTrue($r1Cleaned['has_duplicates_blocked'], 'Kept receipt should indicate blocked duplicates');
        $this->assertEquals(1, $r1Cleaned['blocked_duplicates_count']);

        // Check that Flagged item in Cleaned Matrix has needs_manual_review = true
        $r5Cleaned = collect($result['ocr_items'])->firstWhere('receipt_id', $r5->id);
        $this->assertNotNull($r5Cleaned);
        $this->assertTrue($r5Cleaned['needs_manual_review']);
        $this->assertStringContainsString('Missing or unreadable FS No', $r5Cleaned['review_reason']);

        // Check "Duplicates Removed" Table structure
        $this->assertCount(1, $result['duplicates_removed']);
        $dupEntry = $result['duplicates_removed'][0];
        $this->assertEquals('00012345', $dupEntry['fs_no']);
        $this->assertEquals('12345', $dupEntry['norm_fs']);
        $this->assertEquals('Vendor Alpha', $dupEntry['vendor_name']);
        $this->assertEquals($r1->id, $dupEntry['kept_receipt']['receipt_id']);
        $this->assertCount(1, $dupEntry['removed_receipts']);
        $this->assertEquals($r2->id, $dupEntry['removed_receipts'][0]['receipt_id']);
        $this->assertStringContainsString('Double receipt immediately blocked', $dupEntry['reason']);
        $this->assertStringContainsString('excluded from Excel export', $dupEntry['reason']);

        // Check Totals:
        // Base value: r1 (1000) + r3 (500) + r4 (200) + r5 (300) = 2000.00 (r2 1000 is excluded!)
        // VAT amount: r1 (150) + r3 (75) + r4 (30) + r5 (45) = 300.00 (r2 150 is excluded!)
        $this->assertEquals(2000.00, (float)$summary['ocr_base']);
        $this->assertEquals(300.00, (float)$summary['ocr_vat']);
    }

    /**
     * Test that double receipts are strictly excluded from Excel export data:
     * - First original receipt is included.
     * - Double scan is blocked and NOT included in the Excel rows.
     */
    public function test_double_receipts_are_not_added_to_excel_export(): void
    {
        $date1 = '2026-10-10';
        $date2 = '2026-10-20';

        // Original first receipt
        $r1 = Receipt::create([
            'receipt_number' => 'REC-EXCEL-001',
            'receipt_date'   => $date1,
            'fs_no'          => 'FS-98765',
            'vendor_tin'     => '0098765432',
            'vendor_name'    => 'Excel Test Vendor',
            'total_amount'   => 1150.00,
            'vat_amount'     => 150.00,
            'subtotal'       => 1000.00,
            'created_at'     => Carbon::parse('2026-10-10 09:00:00'),
        ]);
        $item1 = ReceiptItem::create([
            'receipt_id'      => $r1->id,
            'item_description'=> 'Original Item',
            'total_value'     => 1000.00,
            'vat_amount'      => 150.00,
            'value_after_vat' => 1150.00,
            'created_at'      => Carbon::parse('2026-10-10 09:00:00'),
        ]);

        // Duplicate double scan (same FS and TIN)
        $r2 = Receipt::create([
            'receipt_number' => 'REC-EXCEL-002',
            'receipt_date'   => $date2,
            'fs_no'          => '0098765',
            'vendor_tin'     => '0098765432',
            'vendor_name'    => 'Excel Test Vendor',
            'total_amount'   => 1150.00,
            'vat_amount'     => 150.00,
            'subtotal'       => 1000.00,
            'created_at'     => Carbon::parse('2026-10-20 16:00:00'),
        ]);
        $item2 = ReceiptItem::create([
            'receipt_id'      => $r2->id,
            'item_description'=> 'Duplicate Item',
            'total_value'     => 1000.00,
            'vat_amount'      => 150.00,
            'value_after_vat' => 1150.00,
            'created_at'      => Carbon::parse('2026-10-20 16:00:00'),
        ]);

        // Load items with receipt relation as done in exportPurchaseVatExcel
        $ocrItems = ReceiptItem::with('receipt')->whereIn('receipt_id', [$r1->id, $r2->id])->get();
        $this->assertCount(2, $ocrItems);

        // Run through OCRScanner deduplicateReceiptItems
        $ocrScanner = new \App\Http\Controllers\Admin\OCRReceiptScannerController();
        $refMethod = new \ReflectionMethod($ocrScanner, 'deduplicateReceiptItems');
        $refMethod->setAccessible(true);
        $dedupedOcrScanner = $refMethod->invoke($ocrScanner, $ocrItems);

        $this->assertCount(1, $dedupedOcrScanner, 'OCR Scanner Excel export must contain only 1 item');
        $this->assertEquals($item1->id, $dedupedOcrScanner[0]->id, 'First original receipt item must be kept');

        // Test month movements for ERCA Excel export
        $ethDate = \App\Services\EthiopianDate::toEthiopian(Carbon::parse('2026-10-15'));
        $controller = new FinanceTaxReportController();
        $result = $controller->calculateMonthMovementsData($ethDate['year'], $ethDate['month'], true);

        $keptReceiptIds = collect($result['ocr_items'])->pluck('receipt_id')->all();
        $this->assertContains($r1->id, $keptReceiptIds, 'Excel data source must include original receipt');
        $this->assertNotContains($r2->id, $keptReceiptIds, 'Excel data source must NEVER include double scan');
    }

    /**
     * Test optional calendar selection (Ethiopian vs Gregorian) for Purchase export reports.
     */
    public function test_purchase_reports_export_calendar_choice_ec_and_gc(): void
    {
        $controller = new FinanceTaxReportController();
        $refResolve = new \ReflectionMethod($controller, 'resolveCalendarOption');
        $refResolve->setAccessible(true);

        $refFormat = new \ReflectionMethod($controller, 'formatExportDate');
        $refFormat->setAccessible(true);

        // 1. Verify resolveCalendarOption
        $reqEc = new \Illuminate\Http\Request(['calendar' => 'ec']);
        $this->assertEquals('E', $refResolve->invoke($controller, $reqEc));

        $reqGc = new \Illuminate\Http\Request(['calendar' => 'gc']);
        $this->assertEquals('G', $refResolve->invoke($controller, $reqGc));

        $reqTypeE = new \Illuminate\Http\Request(['calendar_type' => 'E']);
        $this->assertEquals('E', $refResolve->invoke($controller, $reqTypeE));

        $reqTypeG = new \Illuminate\Http\Request(['calendar_type' => 'G']);
        $this->assertEquals('G', $refResolve->invoke($controller, $reqTypeG));

        // 2. Verify formatExportDate bidirectional formatting
        // Gregorian date: 2026-10-28
        $gregDate = '2026-10-28';
        $formattedGcSlash = $refFormat->invoke($controller, $gregDate, null, 'G', '/');
        $this->assertEquals('28/10/2026', $formattedGcSlash);

        $formattedGcHyphen = $refFormat->invoke($controller, $gregDate, null, 'G', '-');
        $this->assertEquals('28-10-2026', $formattedGcHyphen);

        // Ethiopian conversion of 2026-10-28:
        // 2026-10-28 in Ethiopian calendar is Tikimt 18, 2019 (18/02/2019)
        $formattedEcSlash = $refFormat->invoke($controller, $gregDate, null, 'E', '/');
        $this->assertEquals('18/02/2019', $formattedEcSlash);

        $formattedEcHyphen = $refFormat->invoke($controller, $gregDate, null, 'E', '-');
        $this->assertEquals('18-02-2019', $formattedEcHyphen);

        // 3. Verify PurchaseVatExport calendar override
        $dummyVatExpense = (object)[
            'vat_status' => \App\Models\ExpenseHubVatDecision::STATUS_VAT_APPLIES,
            'tin' => '0012345678',
            'supplier_name' => 'Test Supplier',
            'mrc_no' => 'MRC-1234',
            'fs_no' => 'FS-5678',
            'vat_category' => 'G',
            'calendar_type' => 'G', // Item defaulted to G
            'purchase_type' => 3,
            'disbursement_date' => '2026-10-28',
            'created_at' => Carbon::parse('2026-10-28 10:00:00'),
            'reference_no' => 'REF-001',
            'decision_total_amount' => 1150.00,
            'decision_vat_amount' => 150.00,
            'source_type' => 'expense_hub',
            'is_capex' => false,
            'project' => null,
            'project_name' => 'General',
        ];

        // Export forced to Ethiopian (EC)
        $vatRowsEc = \App\Exports\PurchaseVatExport::buildRows(collect([$dummyVatExpense]), 'ec');
        $this->assertCount(1, $vatRowsEc);
        // Col 2: CALENDAR TYPE
        $this->assertEquals('E', $vatRowsEc[0][1], 'Purchase VAT Col 2 must be E when exporting in Ethiopian calendar');
        // Col 6: Date of Purchase
        $this->assertEquals('18/02/2019', $vatRowsEc[0][5], 'Purchase VAT Col 6 date must be formatted in Ethiopian calendar (d/m/Y)');

        // Export forced to Gregorian (GC)
        $vatRowsGc = \App\Exports\PurchaseVatExport::buildRows(collect([$dummyVatExpense]), 'gc');
        $this->assertCount(1, $vatRowsGc);
        $this->assertEquals('G', $vatRowsGc[0][1], 'Purchase VAT Col 2 must be G when exporting in Gregorian calendar');
        $this->assertEquals('28/10/2026', $vatRowsGc[0][5], 'Purchase VAT Col 6 date must be formatted in Gregorian calendar (d/m/Y)');

        // 4. Verify PurchaseTasExport calendar override
        $dummyTasExpense = (object)[
            'vat_status' => \App\Models\ExpenseHubVatDecision::STATUS_NO_VAT,
            'is_verified_without_receipt' => false,
            'tin' => '0012345678',
            'supplier_name' => 'Test Supplier',
            'mrc_no' => 'MRC-1234',
            'fs_no' => 'FS-5678',
            'disbursement_date' => '2026-10-28',
            'created_at' => Carbon::parse('2026-10-28 10:00:00'),
            'reference_no' => 'REF-001',
            'decision_total_amount' => 1000.00,
            'amount' => 1000.00,
        ];

        $tasRowsEc = \App\Exports\PurchaseTasExport::buildRows(collect([$dummyTasExpense]), 'ec');
        $this->assertCount(1, $tasRowsEc);
        // Col 5: Receipt Date (EC d-m-Y)
        $this->assertEquals('18-02-2019', $tasRowsEc[0][4], 'Purchase TAS Col 5 Receipt Date must be Ethiopian format d-m-Y');

        $tasRowsGc = \App\Exports\PurchaseTasExport::buildRows(collect([$dummyTasExpense]), 'gc');
        $this->assertCount(1, $tasRowsGc);
        $this->assertEquals('28-10-2026', $tasRowsGc[0][4], 'Purchase TAS Col 5 Receipt Date must be Gregorian format d-m-Y');
    }
}
