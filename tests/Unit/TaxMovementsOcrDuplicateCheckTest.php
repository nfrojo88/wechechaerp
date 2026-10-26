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
     * - Identical FS No & Seller TIN -> only latest kept, older removed.
     * - Only FS No matches -> kept (not duplicate).
     * - Only Seller TIN matches -> kept (not duplicate).
     * - Missing/unreadable FS No or TIN -> kept, flagged as "Needs manual review".
     */
    public function test_ocr_receipts_deduplication_and_flagging_rules(): void
    {
        // October 2026 falls within Meskerem/Tikimt 2019 EC.
        // Let's create receipts in a specific date window: 2026-10-15
        $date1 = '2026-10-10'; // older
        $date2 = '2026-10-20'; // newer

        // 1. DUPLICATE PAIR: FS "00012345" and TIN "0012345678"
        // Older duplicate (should be removed)
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
            'item_description'=> 'Hardware Older',
            'total_value'     => 1000.00,
            'vat_amount'      => 150.00,
            'value_after_vat' => 1150.00,
            'created_at'      => Carbon::parse('2026-10-10 10:00:00'),
        ]);

        // Newer duplicate: FS normalized matches "FS-12345" -> "12345", TIN matches "00-1234-5678" -> "0012345678"
        // (should be KEPT)
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
            'item_description'=> 'Hardware Newer',
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
        // Duplicates removed = 1 (r1 removed)
        // Unique receipts used = 4 (r2, r3, r4, r5)
        // Needs manual review = 1 (r5)
        $summary = $result['summary'];
        $this->assertEquals(5, $summary['ocr_total_scanned'], 'Total receipts scanned must be 5');
        $this->assertEquals(1, $summary['ocr_duplicates_removed'], 'Duplicates removed must be 1');
        $this->assertEquals(4, $summary['ocr_unique_used'], 'Unique receipts used must be 4');
        $this->assertEquals(1, $summary['ocr_needs_review_count'], 'Needs manual review count must be 1');

        // Check Cleaned Matrix: exactly 4 unique rows
        $this->assertCount(4, $result['ocr_items']);
        $keptIds = collect($result['ocr_items'])->pluck('receipt_id')->all();
        $this->assertContains($r2->id, $keptIds, 'Latest receipt r2 must be kept');
        $this->assertNotContains($r1->id, $keptIds, 'Older duplicate receipt r1 must be removed');
        $this->assertContains($r3->id, $keptIds, 'Different TIN receipt r3 must be kept');
        $this->assertContains($r4->id, $keptIds, 'Different FS receipt r4 must be kept');
        $this->assertContains($r5->id, $keptIds, 'Unreadable receipt r5 must be kept in matrix');

        // Check that Flagged item in Cleaned Matrix has needs_manual_review = true
        $r5Cleaned = collect($result['ocr_items'])->firstWhere('receipt_id', $r5->id);
        $this->assertNotNull($r5Cleaned);
        $this->assertTrue($r5Cleaned['needs_manual_review']);
        $this->assertStringContainsString('Missing or unreadable FS No', $r5Cleaned['review_reason']);

        // Check "Duplicates Removed" Table structure
        $this->assertCount(1, $result['duplicates_removed']);
        $dupEntry = $result['duplicates_removed'][0];
        $this->assertEquals('FS-12345', $dupEntry['fs_no']);
        $this->assertEquals('12345', $dupEntry['norm_fs']);
        $this->assertEquals('Vendor Alpha', $dupEntry['vendor_name']);
        $this->assertEquals($r2->id, $dupEntry['kept_receipt']['receipt_id']);
        $this->assertCount(1, $dupEntry['removed_receipts']);
        $this->assertEquals($r1->id, $dupEntry['removed_receipts'][0]['receipt_id']);
        $this->assertStringContainsString('kept latest receipt', $dupEntry['reason']);

        // Check Totals:
        // Base value: r2 (1000) + r3 (500) + r4 (200) + r5 (300) = 2000.00 (r1 1000 is excluded!)
        // VAT amount: r2 (150) + r3 (75) + r4 (30) + r5 (45) = 300.00 (r1 150 is excluded!)
        $this->assertEquals(2000.00, (float)$summary['ocr_base']);
        $this->assertEquals(300.00, (float)$summary['ocr_vat']);
    }
}
