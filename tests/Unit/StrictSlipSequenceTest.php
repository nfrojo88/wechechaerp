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
use Illuminate\Support\Facades\Artisan;

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
            $table->softDeletes();
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
            $table->softDeletes();
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
            $table->string('challan_no', 100)->nullable();
            $table->string('vehicle_no', 100)->nullable();
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
            $table->string('physical_slip_no')->nullable();
            $table->string('receiving_slip_no')->nullable();
            $table->string('outgoing_slip_file')->nullable();
            $table->string('receiving_slip_file')->nullable();
            $table->text('dispatch_notes')->nullable();
            $table->text('receiving_notes')->nullable();
            $table->text('reason')->nullable();
            $table->string('vehicle_plate_no')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->unsignedBigInteger('dispatched_by')->nullable();
            $table->unsignedBigInteger('received_by')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $this->service = app(SlipSequenceService::class);

        $this->user = User::create([
            'name' => 'Store Keeper Dave',
            'email' => 'dave@store.et',
        ]);

        $this->store = Store::create([
            'name' => 'Chafe Primary School G+4 Building',
            'code' => 'CHAFE-01',
            'is_active' => true,
        ]);
    }

    /**
     * Helper to set up the Chafe Primary School G+4 Building scenario:
     * Store: Chafe Primary School G+4 Building, book 2401-2450 (capacity 50).
     * Already registered:
     * - Transfers: 2401, 2409, 2410, 2417, 2420
     * - Petty cash: 2422, 2430
     */
    protected function setupChafeScenario(): SlipSequence
    {
        $sequence = SlipSequence::create([
            'store_id'        => $this->store->id,
            'slip_type'       => 'receive',
            'label'           => 'Receiving (GRN) Book 2401-2450',
            'prefix'          => '',
            'book_start_no'   => 2401,
            'book_end_no'     => 2450,
            'current_slip_no' => 2401, // Stored stale guess
            'used_count'      => 0,    // Stored stale guess
            'status'          => 'active',
        ]);

        // Register 5 Transfers: 2401, 2409, 2410, 2417, 2420
        $transfers = [2401, 2409, 2410, 2417, 2420];
        foreach ($transfers as $trNum) {
            DeliveryReceipt::create([
                'dr_no'         => (string)$trNum,
                'slip_no'       => (string)$trNum,
                'slip_type'     => 'receive',
                'source_type'   => 'transfer',
                'store_id'      => $this->store->id,
                'received_date' => now()->toDateString(),
                'status'        => 'verified',
                'is_void'       => false,
            ]);
        }

        // Register 2 Petty Cash: 2422, 2430
        $pettyCash = [2422, 2430];
        foreach ($pettyCash as $pcNum) {
            DeliveryReceipt::create([
                'dr_no'         => (string)$pcNum,
                'slip_no'       => (string)$pcNum,
                'slip_type'     => 'receive',
                'source_type'   => 'petty_cash',
                'store_id'      => $this->store->id,
                'received_date' => now()->toDateString(),
                'status'        => 'verified',
                'is_void'       => false,
            ]);
        }

        return $sequence;
    }

    /**
     * Test 1: With the data above, nextFreeNumber() returns 2402,
     * header shows 43 remaining and Next #2402.
     */
    public function test_chafe_primary_school_scenario_next_free_number_returns_2402(): void
    {
        $sequence = $this->setupChafeScenario();

        // 1. nextFreeNumber() dynamically returns 2402 (lowest free number in 2401-2450)
        $this->assertEquals(2402, $sequence->nextFreeNumber());
        $this->assertEquals(2402, $sequence->getNextSlipNumber());

        // 2. Capacity is 50
        $this->assertEquals(50, $sequence->getCapacity());

        // 3. Assigned slips detail finds the 7 records
        $assigned = $sequence->getAssignedSlipsDetail();
        $this->assertCount(7, $assigned);
        $this->assertEquals(5, $assigned->where('source_type', 'transfer')->count());
        $this->assertEquals(2, $assigned->where('source_type', 'petty_cash')->count());
        $this->assertEquals(0, $assigned->whereIn('source_type', ['procurement', 'delivery_receipt'])->count());

        // 4. Remaining count = capacity - used - void = 50 - 7 - 0 = 43
        $this->assertEquals(43, $sequence->getRemainingSlips($assigned));
        $this->assertEquals(14.0, $sequence->getPercentageUsed($assigned));

        // 5. Leaf map status: 2402 is 'next' (blue), available leaves are 'available' (gray)
        $map = $sequence->getBookRangeMap($assigned);
        $mapByNum = collect($map)->keyBy('number');

        $this->assertEquals('transfer', $mapByNum[2401]['status']);
        $this->assertEquals('next', $mapByNum[2402]['status']);
        $this->assertEquals('available', $mapByNum[2403]['status']);
        $this->assertEquals('available', $mapByNum[2408]['status']);
        $this->assertEquals('transfer', $mapByNum[2409]['status']);
        $this->assertEquals('transfer', $mapByNum[2410]['status']);
        $this->assertEquals('available', $mapByNum[2411]['status']);
        $this->assertEquals('transfer', $mapByNum[2417]['status']);
        $this->assertEquals('available', $mapByNum[2418]['status']);
        $this->assertEquals('transfer', $mapByNum[2420]['status']);
        $this->assertEquals('available', $mapByNum[2421]['status']);
        $this->assertEquals('petty_cash', $mapByNum[2422]['status']);
        $this->assertEquals('available', $mapByNum[2423]['status']);
        $this->assertEquals('petty_cash', $mapByNum[2430]['status']);
        $this->assertEquals('available', $mapByNum[2431]['status']);

        // Check that none of the available leaves are marked as yellow 'unrecorded' gaps
        $unrecordedGaps = collect($map)->where('status', 'unrecorded');
        $this->assertCount(0, $unrecordedGaps);
    }

    /**
     * Test 2: Sequential consumption auto-skips registered numbers:
     * - Consumes 2402..2408
     * - Next returns 2411 (skips 2409 and 2410)
     * - Consumes 2411..2416
     * - Next returns 2418 (skips 2417)
     * - Consumes 2418, 2419
     * - Next returns 2421 (skips 2420)
     * - Consumes 2421
     * - Next returns 2423 (skips 2422)
     * - Consumes 2423..2429
     * - Next returns 2431 (skips 2430)
     */
    public function test_consuming_in_order_skips_registered_numbers_correctly(): void
    {
        $sequence = $this->setupChafeScenario();

        // 1. Consume 2402 to 2408
        for ($n = 2402; $n <= 2408; $n++) {
            $res = $this->service->consumeNext(
                storeId: $this->store->id,
                slipType: 'receive',
                sourceType: 'procurement'
            );
            $this->assertEquals((int)$n, (int)$res['numeric_no']);

            // Record DeliveryReceipt
            DeliveryReceipt::create([
                'dr_no'         => $res['slip_no'],
                'slip_no'       => $res['slip_no'],
                'slip_type'     => 'receive',
                'source_type'   => 'procurement',
                'store_id'      => $this->store->id,
                'received_date' => now()->toDateString(),
                'status'        => 'received',
                'is_void'       => false,
            ]);
        }

        // After using 2408, 2409 and 2410 are already used -> Next MUST jump to 2411
        $expected = $this->service->getNextExpected($this->store->id, 'receive');
        $this->assertEquals(2411, $expected['current_slip_no']);

        $res2411 = $this->service->consumeNext(
            storeId: $this->store->id,
            slipType: 'receive',
            sourceType: 'procurement'
        );
        $this->assertEquals(2411, (int)$res2411['numeric_no']);
        DeliveryReceipt::create([
            'dr_no' => $res2411['slip_no'], 'slip_no' => $res2411['slip_no'],
            'slip_type' => 'receive', 'source_type' => 'procurement',
            'store_id' => $this->store->id, 'received_date' => now()->toDateString(),
            'status' => 'received', 'is_void' => false,
        ]);

        // 2. Consume 2412 to 2416
        for ($n = 2412; $n <= 2416; $n++) {
            $res = $this->service->consumeNext($this->store->id, 'receive');
            $this->assertEquals((int)$n, (int)$res['numeric_no']);
            DeliveryReceipt::create([
                'dr_no' => $res['slip_no'], 'slip_no' => $res['slip_no'],
                'slip_type' => 'receive', 'source_type' => 'procurement',
                'store_id' => $this->store->id, 'received_date' => now()->toDateString(),
                'status' => 'received', 'is_void' => false,
            ]);
        }

        // After 2416, 2417 is already used -> Next MUST jump to 2418
        $res2418 = $this->service->consumeNext($this->store->id, 'receive');
        $this->assertEquals(2418, (int)$res2418['numeric_no']);
        DeliveryReceipt::create([
            'dr_no' => $res2418['slip_no'], 'slip_no' => $res2418['slip_no'],
            'slip_type' => 'receive', 'source_type' => 'procurement',
            'store_id' => $this->store->id, 'received_date' => now()->toDateString(),
            'status' => 'received', 'is_void' => false,
        ]);

        // Consume 2419
        $res2419 = $this->service->consumeNext($this->store->id, 'receive');
        $this->assertEquals(2419, (int)$res2419['numeric_no']);
        DeliveryReceipt::create([
            'dr_no' => $res2419['slip_no'], 'slip_no' => $res2419['slip_no'],
            'slip_type' => 'receive', 'source_type' => 'procurement',
            'store_id' => $this->store->id, 'received_date' => now()->toDateString(),
            'status' => 'received', 'is_void' => false,
        ]);

        // After 2419, 2420 is used -> Next jumps to 2421
        $res2421 = $this->service->consumeNext($this->store->id, 'receive');
        $this->assertEquals(2421, (int)$res2421['numeric_no']);
        DeliveryReceipt::create([
            'dr_no' => $res2421['slip_no'], 'slip_no' => $res2421['slip_no'],
            'slip_type' => 'receive', 'source_type' => 'procurement',
            'store_id' => $this->store->id, 'received_date' => now()->toDateString(),
            'status' => 'received', 'is_void' => false,
        ]);

        // After 2421, 2422 (petty cash) is used -> Next jumps to 2423
        $res2423 = $this->service->consumeNext($this->store->id, 'receive');
        $this->assertEquals(2423, (int)$res2423['numeric_no']);
        DeliveryReceipt::create([
            'dr_no' => $res2423['slip_no'], 'slip_no' => $res2423['slip_no'],
            'slip_type' => 'receive', 'source_type' => 'procurement',
            'store_id' => $this->store->id, 'received_date' => now()->toDateString(),
            'status' => 'received', 'is_void' => false,
        ]);

        // Consume 2424 to 2429
        for ($n = 2424; $n <= 2429; $n++) {
            $res = $this->service->consumeNext($this->store->id, 'receive');
            $this->assertEquals((int)$n, (int)$res['numeric_no']);
            DeliveryReceipt::create([
                'dr_no' => $res['slip_no'], 'slip_no' => $res['slip_no'],
                'slip_type' => 'receive', 'source_type' => 'procurement',
                'store_id' => $this->store->id, 'received_date' => now()->toDateString(),
                'status' => 'received', 'is_void' => false,
            ]);
        }

        // After 2429, 2430 (petty cash) is used -> Next jumps to 2431
        $res2431 = $this->service->consumeNext($this->store->id, 'receive');
        $this->assertEquals(2431, (int)$res2431['numeric_no']);
    }

    /**
     * Test 3: Entering 2405 while next is 2402 is rejected.
     * Entering 2409 (already registered) is rejected as duplicate.
     * Entering out of book range is rejected.
     */
    public function test_entering_out_of_turn_rejected_and_duplicate_rejected(): void
    {
        $sequence = $this->setupChafeScenario();

        // Next is 2402. Entering 2405: must reject with "Next slip is 2402. Use it or void it first."
        try {
            $this->service->consumeNext(
                storeId: $this->store->id,
                slipType: 'receive',
                inputSlipNo: '2405'
            );
            $this->fail('Expected SlipSequenceException when entering 2405 while next is 2402');
        } catch (SlipSequenceException $e) {
            $this->assertStringContainsString('Next slip is 2402. Use it or void it first.', $e->getMessage());
        }

        // Entering already registered 2409: must reject as duplicate
        try {
            $this->service->consumeNext(
                storeId: $this->store->id,
                slipType: 'receive',
                inputSlipNo: '2409'
            );
            $this->fail('Expected SlipSequenceException when entering duplicate slip 2409');
        } catch (SlipSequenceException $e) {
            $this->assertStringContainsString('Duplicate Entry', $e->getMessage());
        }

        // Entering already registered 2401: must reject as duplicate
        try {
            $this->service->consumeNext(
                storeId: $this->store->id,
                slipType: 'receive',
                inputSlipNo: '2401'
            );
            $this->fail('Expected SlipSequenceException when entering duplicate slip 2401');
        } catch (SlipSequenceException $e) {
            $this->assertStringContainsString('Duplicate Entry', $e->getMessage());
        }

        // Out of range: below book_start_no (2400)
        try {
            $this->service->consumeNext(
                storeId: $this->store->id,
                slipType: 'receive',
                inputSlipNo: '2400'
            );
            $this->fail('Expected SlipSequenceException for slip below book range');
        } catch (SlipSequenceException $e) {
            $this->assertStringContainsString('outside the active sequence book range', $e->getMessage());
        }

        // Out of range: above book_end_no (2451)
        try {
            $this->service->consumeNext(
                storeId: $this->store->id,
                slipType: 'receive',
                inputSlipNo: '2451'
            );
            $this->fail('Expected SlipSequenceException for slip above book range');
        } catch (SlipSequenceException $e) {
            $this->assertStringContainsString('outside the active sequence book range', $e->getMessage());
        }
    }

    /**
     * Test 4: Voiding 2402 moves next to 2403 and creates no stock movement.
     */
    public function test_voiding_2402_moves_next_to_2403_and_creates_no_stock_movement(): void
    {
        $sequence = $this->setupChafeScenario();

        $movementsBefore = InventoryMovement::count();

        // Void next free slip (2402)
        $voidResult = $this->service->voidMissingSlips(
            storeId: $this->store->id,
            slipType: 'receive',
            numbers: [2402],
            reason: 'Damaged leaf on store pad',
            userId: $this->user->id
        );

        // 1. Stock movements unchanged (no stock movement)
        $this->assertEquals($movementsBefore, InventoryMovement::count());

        // 2. Void delivery receipt created
        $this->assertDatabaseHas('delivery_receipts', [
            'store_id'    => $this->store->id,
            'slip_no'     => '02402',
            'is_void'     => 1,
            'source_type' => 'void',
            'void_reason' => 'Damaged leaf on store pad',
        ]);

        // 3. Next slip dynamically advances to 2403
        $sequence->refresh();
        $this->assertEquals(2403, $sequence->nextFreeNumber());
        $this->assertEquals(2403, $sequence->getNextSlipNumber());

        // 4. Remaining count decreases by 1 (43 -> 42)
        $this->assertEquals(42, $sequence->getRemainingSlips());
    }

    /**
     * Test 5: Petty cash, transfer, and PR intake all call the same service and skip used numbers.
     */
    public function test_petty_cash_transfer_and_pr_intake_all_call_same_service_and_skip_used_numbers(): void
    {
        $sequence = $this->setupChafeScenario();

        // 1. PR intake consumes next (2402)
        $resPr = $this->service->consumeNext(
            storeId: $this->store->id,
            slipType: 'receive',
            sourceType: 'procurement'
        );
        $this->assertEquals(2402, (int)$resPr['numeric_no']);
        DeliveryReceipt::create([
            'dr_no' => $resPr['slip_no'], 'slip_no' => $resPr['slip_no'],
            'slip_type' => 'receive', 'source_type' => 'procurement',
            'store_id' => $this->store->id, 'received_date' => now()->toDateString(),
            'status' => 'received', 'is_void' => false,
        ]);

        // 2. Petty cash consumes next (2403)
        $resPetty = $this->service->consumeNext(
            storeId: $this->store->id,
            slipType: 'receive',
            sourceType: 'petty_cash'
        );
        $this->assertEquals(2403, (int)$resPetty['numeric_no']);
        DeliveryReceipt::create([
            'dr_no' => $resPetty['slip_no'], 'slip_no' => $resPetty['slip_no'],
            'slip_type' => 'receive', 'source_type' => 'petty_cash',
            'store_id' => $this->store->id, 'received_date' => now()->toDateString(),
            'status' => 'received', 'is_void' => false,
        ]);

        // 3. Transfer receive consumes next (2404)
        $resTransfer = $this->service->consumeNext(
            storeId: $this->store->id,
            slipType: 'receive',
            sourceType: 'transfer_in'
        );
        $this->assertEquals(2404, (int)$resTransfer['numeric_no']);
        DeliveryReceipt::create([
            'dr_no' => $resTransfer['slip_no'], 'slip_no' => $resTransfer['slip_no'],
            'slip_type' => 'receive', 'source_type' => 'transfer_in',
            'store_id' => $this->store->id, 'received_date' => now()->toDateString(),
            'status' => 'received', 'is_void' => false,
        ]);

        // Next is now 2405
        $next = $this->service->getNextExpected($this->store->id, 'receive');
        $this->assertEquals(2405, $next['current_slip_no']);
    }

    /**
     * Test 6: slips:recalculate Artisan command (with --dry-run option)
     */
    public function test_slips_recalculate_artisan_command_and_dry_run(): void
    {
        $sequence = $this->setupChafeScenario();

        // Sequence currently has stale guesses in database: current_slip_no = 2401, used_count = 0
        $this->assertEquals(2401, $sequence->current_slip_no);
        $this->assertEquals(0, $sequence->used_count);

        // Run with --dry-run
        Artisan::call('slips:recalculate', ['--dry-run' => true]);
        $outputDry = Artisan::output();
        $this->assertStringContainsString('DRY-RUN', $outputDry);
        $this->assertStringContainsString('2401', $outputDry);
        $this->assertStringContainsString('2402', $outputDry);

        // Verify database is UNTOUCHED in dry-run
        $sequence->refresh();
        $this->assertEquals(2401, $sequence->current_slip_no);
        $this->assertEquals(0, $sequence->used_count);

        // Run without --dry-run to apply recalculation
        Artisan::call('slips:recalculate');
        $outputReal = Artisan::output();
        $this->assertStringContainsString('RECALCULATION COMPLETE', $outputReal);

        // Verify database is updated to exact values
        $sequence->refresh();
        $this->assertEquals(2402, $sequence->current_slip_no);
        $this->assertEquals(7, $sequence->used_count);
        $this->assertEquals('active', $sequence->status);
    }

    /**
     * Test 7: Recalculate method on model computes values and updates safely.
     */
    public function test_model_recalculate_method_returns_and_saves_correct_values(): void
    {
        $sequence = $this->setupChafeScenario();

        $result = $sequence->recalculate(save: true);

        $this->assertTrue($result['changed']);
        $this->assertEquals(2401, $result['old_current']);
        $this->assertEquals(2402, $result['new_current']);
        $this->assertEquals(0, $result['old_used']);
        $this->assertEquals(7, $result['new_used']);

        $sequence->refresh();
        $this->assertEquals(2402, $sequence->current_slip_no);
        $this->assertEquals(7, $sequence->used_count);
    }

    /**
     * Test 8: Multiple inactive sequences can coexist peacefully without unique constraint crashes.
     */
    public function test_can_deactivate_sequence_when_another_inactive_sequence_already_exists(): void
    {
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
        $controller->deactivate($seq2);

        $seq2->refresh();
        $this->assertEquals('inactive', $seq2->status);
        $this->assertEquals('inactive', $seq1->status);

        $inactiveCount = SlipSequence::where('store_id', $this->store->id)
            ->where('slip_type', 'receive')
            ->where('status', 'inactive')
            ->count();
        $this->assertEquals(2, $inactiveCount);
    }
}
