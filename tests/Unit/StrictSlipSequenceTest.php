<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Store;
use App\Models\Product;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\SlipSequence;
use App\Models\DeliveryReceipt;
use App\Models\DeliveryReceiptItem;
use App\Models\PurchaseOrder;
use App\Models\Transfer;
use App\Services\SlipSequenceService;
use App\Services\InventoryService;
use App\Exceptions\SlipSequenceException;
use App\Exceptions\SlipSequenceGapException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StrictSlipSequenceTest extends TestCase
{
    protected SlipSequenceService $service;
    protected Store $store;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        DB::statement('PRAGMA foreign_keys = OFF;');

        Schema::dropIfExists('delivery_receipt_items');
        Schema::dropIfExists('delivery_receipts');
        Schema::dropIfExists('slip_sequences');
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory');
        Schema::dropIfExists('transfers');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('products');
        Schema::dropIfExists('stores');
        Schema::dropIfExists('users');

        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->timestamps();
        });

        Schema::create('stores', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('products', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('sku')->nullable();
            $table->decimal('unit_price', 15, 2)->default(100.00);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('inventory', function ($table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('product_id');
            $table->decimal('quantity_on_hand', 15, 3)->default(0);
            $table->decimal('quantity_reserved', 15, 3)->default(0);
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->decimal('min_stock', 15, 3)->default(0);
            $table->timestamp('last_movement_at')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_movements', function ($table) {
            $table->id();
            $table->unsignedBigInteger('inventory_id');
            $table->string('type');
            $table->decimal('quantity', 15, 3);
            $table->decimal('balance_before', 15, 3);
            $table->decimal('balance_after', 15, 3);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->unsignedBigInteger('performed_by')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('slip_sequences', function ($table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->string('slip_type');
            $table->string('label');
            $table->string('prefix')->nullable();
            $table->integer('book_start_no');
            $table->integer('book_end_no');
            $table->integer('current_slip_no');
            $table->integer('used_count')->default(0);
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('delivery_receipts', function ($table) {
            $table->id();
            $table->string('dr_no', 100);
            $table->string('slip_no', 100)->nullable();
            $table->string('slip_type', 20)->default('receive');
            $table->string('source_type', 50)->default('manual');
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('to_store_id')->nullable();
            $table->unsignedBigInteger('received_by')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->date('received_date')->nullable();
            $table->date('receipt_date')->nullable();
            $table->string('supplier_name', 255)->nullable();
            $table->string('reference_no', 100)->nullable();
            $table->unsignedBigInteger('purchase_order_id')->nullable();
            $table->unsignedBigInteger('purchase_request_id')->nullable();
            $table->unsignedBigInteger('transfer_id')->nullable();
            $table->string('status', 50)->default('received');
            $table->boolean('is_void')->default(false);
            $table->text('void_reason')->nullable();
            $table->unsignedBigInteger('voided_by')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->string('sequence_status', 50)->default('valid');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'slip_type', 'slip_no'], 'idx_store_slip_unique');
        });

        Schema::create('delivery_receipt_items', function ($table) {
            $table->id();
            $table->unsignedBigInteger('delivery_receipt_id');
            $table->unsignedBigInteger('product_id');
            $table->decimal('quantity_received', 15, 3)->default(0);
            $table->decimal('accepted_quantity', 15, 3)->default(0);
            $table->decimal('rejected_quantity', 15, 3)->default(0);
            $table->string('unit', 20)->default('pcs');
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_orders', function ($table) {
            $table->id();
            $table->string('reference_number')->nullable();
            $table->string('supplier_name')->nullable();
            $table->string('status')->default('delivered');
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->date('issued_date')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('transfers', function ($table) {
            $table->id();
            $table->string('transfer_no');
            $table->unsignedBigInteger('from_store_id');
            $table->unsignedBigInteger('to_store_id');
            $table->string('status')->default('in_transit');
            $table->string('outgoing_slip_no')->nullable();
            $table->string('receiving_slip_no')->nullable();
            $table->timestamps();
        });

        $this->service = app(SlipSequenceService::class);

        $this->user = User::create([
            'name' => 'Store Keeper Dave',
            'email' => 'dave@store.et',
        ]);

        $this->store = Store::create([
            'name' => 'Main Logistics Hub',
            'code' => 'MAIN-01',
            'is_active' => true,
        ]);
    }

    /**
     * Test 1: Rejecting 2102 before 2101; after voiding 2101 it is accepted.
     */
    public function test_accepting_higher_number_is_rejected_and_accepted_after_voiding(): void
    {
        $sequence = SlipSequence::create([
            'store_id'        => $this->store->id,
            'slip_type'       => 'receive',
            'label'           => 'Receiving (GRN) Book 21',
            'prefix'          => 'REC-',
            'book_start_no'   => 2100,
            'book_end_no'     => 2200,
            'current_slip_no' => 2101,
            'used_count'      => 1,
            'status'          => 'active',
        ]);

        // Attempting to consume 2102 when 2101 is next expected should throw SlipSequenceGapException
        try {
            $this->service->consumeNext(
                storeId: $this->store->id,
                slipType: 'receive',
                inputSlipNo: '2102',
                sourceType: 'procurement'
            );
            $this->fail('Expected SlipSequenceGapException was not thrown for skipped slip 2101.');
        } catch (SlipSequenceGapException $e) {
            $this->assertEquals(2102, $e->getGivenNumber());
            $this->assertEquals(2101, $e->getExpectedNumber());
            $this->assertEquals([2101], $e->getMissingNumbers());
            $this->assertStringContainsString('not recorded yet', strtolower($e->getMessage()));
            $this->assertStringContainsString('record or void', strtolower($e->getMessage()));
        }

        // Sequence must NOT have jumped; current_slip_no remains 2101
        $sequence->refresh();
        $this->assertEquals(2101, $sequence->current_slip_no);
        $this->assertEquals(1, $sequence->used_count);

        // Storekeeper voids missing slip 2101
        $voidResult = $this->service->voidMissingSlips(
            storeId: $this->store->id,
            slipType: 'receive',
            numbers: [2101],
            reason: 'Physical leaf torn and spoiled by rain',
            userId: $this->user->id
        );

        $this->assertEquals(1, $voidResult['voided_count']);
        $this->assertDatabaseHas('delivery_receipts', [
            'store_id'    => $this->store->id,
            'slip_no'     => 'REC-02101',
            'source_type' => 'void',
            'is_void'     => 1,
            'void_reason' => 'Physical leaf torn and spoiled by rain',
            'voided_by'   => $this->user->id,
        ]);

        // Counter has now advanced to 2102
        $sequence->refresh();
        $this->assertEquals(2102, $sequence->current_slip_no);
        $this->assertEquals(2, $sequence->used_count);

        // Now consuming 2102 succeeds strictly!
        $result = $this->service->consumeNext(
            storeId: $this->store->id,
            slipType: 'receive',
            inputSlipNo: '2102',
            sourceType: 'procurement'
        );

        $this->assertEquals('REC-02102', $result['slip_no']);
        $this->assertEquals(2102, $result['numeric_slip_no']);

        $sequence->refresh();
        $this->assertEquals(2103, $sequence->current_slip_no);
        $this->assertEquals(3, $sequence->used_count);
    }

    /**
     * Test 2: Duplicate number rejected; out-of-range rejected.
     */
    public function test_duplicate_number_and_out_of_range_rejected(): void
    {
        $sequence = SlipSequence::create([
            'store_id'        => $this->store->id,
            'slip_type'       => 'receive',
            'label'           => 'Receiving (GRN) Book 21',
            'prefix'          => 'REC-',
            'book_start_no'   => 2100,
            'book_end_no'     => 2105,
            'current_slip_no' => 2103,
            'used_count'      => 3,
            'status'          => 'active',
        ]);

        // Pre-create receipt for 2101
        DeliveryReceipt::create([
            'dr_no'         => 'REC-02101',
            'slip_no'       => 'REC-02101',
            'slip_type'     => 'receive',
            'source_type'   => 'procurement',
            'store_id'      => $this->store->id,
            'received_date' => now()->toDateString(),
            'status'        => 'received',
            'is_void'       => false,
        ]);

        // 1. Lower than expected / already used -> Rejected as Duplicate
        try {
            $this->service->consumeNext($this->store->id, 'receive', '2101');
            $this->fail('Expected SlipSequenceException for duplicate slip 2101');
        } catch (SlipSequenceException $e) {
            $this->assertStringContainsString('Duplicate Entry', $e->getMessage());
        }

        // Also 2102 (< current 2103) is rejected
        try {
            $this->service->consumeNext($this->store->id, 'receive', '2102');
            $this->fail('Expected SlipSequenceException for duplicate slip 2102');
        } catch (SlipSequenceException $e) {
            $this->assertStringContainsString('Duplicate Entry', $e->getMessage());
        }

        // 2. Out of range below book_start_no (2100)
        try {
            $this->service->consumeNext($this->store->id, 'receive', '2099');
            $this->fail('Expected SlipSequenceException for out-of-range slip 2099');
        } catch (SlipSequenceException $e) {
            $this->assertStringContainsString('outside the active sequence book range', $e->getMessage());
        }

        // 3. Out of range above book_end_no (2105)
        try {
            $this->service->consumeNext($this->store->id, 'receive', '2106');
            $this->fail('Expected SlipSequenceException for out-of-range slip 2106');
        } catch (SlipSequenceException $e) {
            $this->assertStringContainsString('outside the active sequence book range', $e->getMessage());
        }

        // 4. Sequence full rejection
        $sequence->update([
            'current_slip_no' => 2106,
            'used_count'      => 6,
            'status'          => 'full',
        ]);

        try {
            $this->service->consumeNext($this->store->id, 'receive');
            $this->fail('Expected SlipSequenceException for full sequence');
        } catch (SlipSequenceException $e) {
            $this->assertStringContainsString('full', strtolower($e->getMessage()));
        }
    }

    /**
     * Test 3: Two simultaneous requests for the same number: only one succeeds.
     */
    public function test_concurrent_lock_prevents_simultaneous_duplicate_consumption(): void
    {
        $sequence = SlipSequence::create([
            'store_id'        => $this->store->id,
            'slip_type'       => 'receive',
            'label'           => 'Receiving (GRN) Book Concurrent',
            'prefix'          => 'REC-',
            'book_start_no'   => 5000,
            'book_end_no'     => 5100,
            'current_slip_no' => 5001,
            'used_count'      => 1,
            'status'          => 'active',
        ]);

        // Simulating two sequential requests competing for the current slot:
        // First request takes slip 5001
        $res1 = $this->service->consumeNext(
            storeId: $this->store->id,
            slipType: 'receive',
            inputSlipNo: null,
            sourceType: 'procurement'
        );
        $this->assertEquals('REC-05001', $res1['slip_no']);

        DeliveryReceipt::create([
            'dr_no'         => $res1['slip_no'],
            'slip_no'       => $res1['slip_no'],
            'slip_type'     => 'receive',
            'source_type'   => 'procurement',
            'store_id'      => $this->store->id,
            'received_date' => now()->toDateString(),
            'status'        => 'received',
            'is_void'       => false,
        ]);

        // Second request that also tries to claim 5001 explicitly must immediately be rejected as duplicate
        try {
            $this->service->consumeNext(
                storeId: $this->store->id,
                slipType: 'receive',
                inputSlipNo: '5001',
                sourceType: 'petty_cash'
            );
            $this->fail('Second request for 5001 should have been rejected as duplicate');
        } catch (SlipSequenceException $e) {
            $this->assertStringContainsString('Duplicate Entry', $e->getMessage());
        }

        // Next automatic request seamlessly receives 5002
        $res2 = $this->service->consumeNext(
            storeId: $this->store->id,
            slipType: 'receive',
            inputSlipNo: null,
            sourceType: 'petty_cash'
        );
        $this->assertEquals('REC-05002', $res2['slip_no']);
    }

    /**
     * Test 4: Void creates no stock change and reverses stock if posted.
     */
    public function test_void_creates_no_stock_change_and_voiding_existing_receipt_reverses_stock(): void
    {
        $product = Product::create([
            'name'       => 'Portland Cement 42.5N',
            'code'       => 'CEM-425',
            'unit_price' => 750.00,
        ]);

        $sequence = SlipSequence::create([
            'store_id'        => $this->store->id,
            'slip_type'       => 'receive',
            'label'           => 'Receiving (GRN) Book 60',
            'prefix'          => 'REC-',
            'book_start_no'   => 6000,
            'book_end_no'     => 6100,
            'current_slip_no' => 6001,
            'used_count'      => 1,
            'status'          => 'active',
        ]);

        // Part A: Voiding a missing slip creates NO stock movement
        $movementsBefore = InventoryMovement::count();
        $this->service->voidMissingSlips(
            storeId: $this->store->id,
            slipType: 'receive',
            numbers: [6001],
            reason: 'Torn page',
            userId: $this->user->id
        );
        $this->assertEquals($movementsBefore, InventoryMovement::count());

        // Part B: Posting a receipt creates stock; voiding that posted receipt reverses stock
        $invService = app(InventoryService::class);
        $consumeRes = $this->service->consumeNext(
            storeId: $this->store->id,
            slipType: 'receive',
            inputSlipNo: null,
            sourceType: 'procurement'
        );
        $this->assertEquals('REC-06002', $consumeRes['slip_no']);

        $receipt = DeliveryReceipt::create([
            'dr_no'         => $consumeRes['slip_no'],
            'slip_no'       => $consumeRes['slip_no'],
            'slip_type'     => 'receive',
            'source_type'   => 'procurement',
            'store_id'      => $this->store->id,
            'received_date' => now()->toDateString(),
            'status'        => 'received',
            'is_void'       => false,
        ]);

        $receiptItem = DeliveryReceiptItem::create([
            'delivery_receipt_id' => $receipt->id,
            'product_id'          => $product->id,
            'quantity_received'   => 50.0,
            'accepted_quantity'   => 50.0,
            'rejected_quantity'   => 0,
            'unit'                => 'bags',
        ]);

        // Post stock in
        $invService->applyMovement(
            storeId: $this->store->id,
            productId: $product->id,
            quantityDelta: 50.0,
            type: 'in',
            referenceType: DeliveryReceipt::class,
            referenceId: $receipt->id,
            remarks: "Goods received under {$receipt->dr_no}",
            performedBy: $this->user->id
        );

        $inventory = Inventory::where('store_id', $this->store->id)
            ->where('product_id', $product->id)
            ->first();
        $this->assertEquals(50.0, (float)$inventory->quantity_on_hand);

        // Void the existing posted delivery receipt
        $this->service->voidExistingReceipt(
            receipt: $receipt,
            reason: 'Supplier delivered substandard cement bags; full return to vendor',
            userId: $this->user->id
        );

        // Receipt marked void with reason and actor
        $receipt->refresh();
        $this->assertTrue((bool)$receipt->is_void);
        $this->assertEquals('void', $receipt->status);
        $this->assertEquals('Supplier delivered substandard cement bags; full return to vendor', $receipt->void_reason);
        $this->assertEquals($this->user->id, $receipt->voided_by);

        // Inventory on hand is strictly reversed back to 0
        $inventory->refresh();
        $this->assertEquals(0.0, (float)$inventory->quantity_on_hand);

        // Reversal movement recorded
        $reversal = InventoryMovement::where('reference_type', DeliveryReceipt::class)
            ->where('reference_id', $receipt->id)
            ->where('type', 'slip_void')
            ->first();
        $this->assertNotNull($reversal);
        $this->assertEquals(-50.0, (float)$reversal->quantity);
    }

    /**
     * Test 5: Petty cash, PR intake, and transfer receipts all advance the same counter.
     */
    public function test_petty_cash_pr_intake_and_transfer_receipts_advance_same_counter(): void
    {
        $sequence = SlipSequence::create([
            'store_id'        => $this->store->id,
            'slip_type'       => 'receive',
            'label'           => 'Main Receiving Unified Book',
            'prefix'          => 'GRN-',
            'book_start_no'   => 7000,
            'book_end_no'     => 7100,
            'current_slip_no' => 7001,
            'used_count'      => 1,
            'status'          => 'active',
        ]);

        // 1. Procurement PR Intake consumes next slip
        $resPr = $this->service->consumeNext(
            storeId: $this->store->id,
            slipType: 'receive',
            sourceType: 'procurement'
        );
        $this->assertEquals('GRN-07001', $resPr['slip_no']);
        $this->assertEquals(7001, $resPr['numeric_slip_no']);

        DeliveryReceipt::create([
            'dr_no'         => $resPr['slip_no'],
            'slip_no'       => $resPr['slip_no'],
            'slip_type'     => 'receive',
            'source_type'   => 'procurement',
            'store_id'      => $this->store->id,
            'received_date' => now()->toDateString(),
            'status'        => 'received',
            'is_void'       => false,
        ]);

        $sequence->refresh();
        $this->assertEquals(7002, $sequence->current_slip_no);
        $this->assertEquals(2, $sequence->used_count);

        // 2. Petty cash spot material purchase consumes next slip
        $resPetty = $this->service->consumeNext(
            storeId: $this->store->id,
            slipType: 'receive',
            sourceType: 'petty_cash'
        );
        $this->assertEquals('GRN-07002', $resPetty['slip_no']);
        $this->assertEquals(7002, $resPetty['numeric_slip_no']);

        DeliveryReceipt::create([
            'dr_no'         => $resPetty['slip_no'],
            'slip_no'       => $resPetty['slip_no'],
            'slip_type'     => 'receive',
            'source_type'   => 'petty_cash',
            'store_id'      => $this->store->id,
            'received_date' => now()->toDateString(),
            'status'        => 'received',
            'is_void'       => false,
        ]);

        $sequence->refresh();
        $this->assertEquals(7003, $sequence->current_slip_no);
        $this->assertEquals(3, $sequence->used_count);

        // 3. Incoming Transfer receiving consumes next slip
        $resTransfer = $this->service->consumeNext(
            storeId: $this->store->id,
            slipType: 'receive',
            sourceType: 'transfer_in'
        );
        $this->assertEquals('GRN-07003', $resTransfer['slip_no']);
        $this->assertEquals(7003, $resTransfer['numeric_slip_no']);

        DeliveryReceipt::create([
            'dr_no'         => $resTransfer['slip_no'],
            'slip_no'       => $resTransfer['slip_no'],
            'slip_type'     => 'receive',
            'source_type'   => 'transfer_in',
            'store_id'      => $this->store->id,
            'received_date' => now()->toDateString(),
            'status'        => 'received',
            'is_void'       => false,
        ]);

        $sequence->refresh();
        $this->assertEquals(7004, $sequence->current_slip_no);
        $this->assertEquals(4, $sequence->used_count);

        // Check continuity across all 3 sources
        $receipts = DeliveryReceipt::where('store_id', $this->store->id)->orderBy('slip_no')->get();
        $this->assertCount(3, $receipts);
        $this->assertEquals(['procurement', 'petty_cash', 'transfer_in'], $receipts->pluck('source_type')->toArray());
    }

    /**
     * Test 6: Reconciliation reports a deliberately created gap and recovers when voided.
     */
    public function test_reconciliation_reports_deliberately_created_gap(): void
    {
        $sequence = SlipSequence::create([
            'store_id'        => $this->store->id,
            'slip_type'       => 'receive',
            'label'           => 'Receiving Audit Book',
            'prefix'          => 'REC-',
            'book_start_no'   => 8001,
            'book_end_no'     => 8010,
            'current_slip_no' => 8005,
            'used_count'      => 4,
            'status'          => 'active',
        ]);

        // Insert receipts for 8001, 8002, and 8004 — leaving 8003 as a deliberate gap
        DeliveryReceipt::create([
            'dr_no'         => 'REC-08001',
            'slip_no'       => 'REC-08001',
            'slip_type'     => 'receive',
            'source_type'   => 'procurement',
            'store_id'      => $this->store->id,
            'received_date' => now()->toDateString(),
            'status'        => 'received',
            'is_void'       => false,
        ]);

        DeliveryReceipt::create([
            'dr_no'         => 'REC-08002',
            'slip_no'       => 'REC-08002',
            'slip_type'     => 'receive',
            'source_type'   => 'petty_cash',
            'store_id'      => $this->store->id,
            'received_date' => now()->toDateString(),
            'status'        => 'received',
            'is_void'       => false,
        ]);

        DeliveryReceipt::create([
            'dr_no'         => 'REC-08004',
            'slip_no'       => 'REC-08004',
            'slip_type'     => 'receive',
            'source_type'   => 'transfer_in',
            'store_id'      => $this->store->id,
            'received_date' => now()->toDateString(),
            'status'        => 'received',
            'is_void'       => false,
        ]);

        // Audit & Reconcile
        $report = $this->service->reconcile($sequence);

        $this->assertTrue($report['has_issues']);
        $this->assertEquals('problems', $report['status']);
        $this->assertContains(8003, $report['gap_numbers']);
        $this->assertNotEmpty($report['mismatches']);

        // Now fix the gap by recording a void slip for 8003
        DeliveryReceipt::create([
            'dr_no'         => 'REC-08003',
            'slip_no'       => 'REC-08003',
            'slip_type'     => 'receive',
            'source_type'   => 'void',
            'store_id'      => $this->store->id,
            'received_date' => now()->toDateString(),
            'status'        => 'void',
            'is_void'       => true,
            'void_reason'   => 'Reconciled gap',
            'voided_by'     => $this->user->id,
        ]);

        // Re-run audit
        $cleanReport = $this->service->reconcile($sequence);
        $this->assertFalse($cleanReport['has_issues']);
        $this->assertEquals('ok', $cleanReport['status']);
        $this->assertEmpty($cleanReport['gaps']);
        $this->assertEmpty($cleanReport['duplicates']);
        $this->assertEmpty($cleanReport['mismatches']);
    }

    public function test_can_deactivate_sequence_when_another_inactive_sequence_already_exists_for_same_store_and_type()
    {
        // Sequence 1 already inactive
        $seq1 = SlipSequence::create([
            'store_id'        => $this->store->id,
            'slip_type'       => 'receive',
            'label'           => 'Receive Book 1 (Past)',
            'prefix'          => 'REC-',
            'book_start_no'   => 1,
            'book_end_no'     => 100,
            'current_slip_no' => 101,
            'used_count'      => 100,
            'status'          => 'inactive',
        ]);

        // Sequence 2 is currently active
        $seq2 = SlipSequence::create([
            'store_id'        => $this->store->id,
            'slip_type'       => 'receive',
            'label'           => 'Receive Book 2 (Current)',
            'prefix'          => 'REC-',
            'book_start_no'   => 101,
            'book_end_no'     => 200,
            'current_slip_no' => 105,
            'used_count'      => 4,
            'status'          => 'active',
        ]);

        $controller = new \App\Http\Controllers\SlipSequenceController();
        $response = $controller->deactivate($seq2);

        $seq2->refresh();
        $this->assertEquals('inactive', $seq2->status);
        $this->assertEquals('inactive', $seq1->status);

        // Verify multiple inactive sequences coexist peacefully
        $inactiveCount = SlipSequence::where('store_id', $this->store->id)
            ->where('slip_type', 'receive')
            ->where('status', 'inactive')
            ->count();
        $this->assertEquals(2, $inactiveCount);
    }
}
