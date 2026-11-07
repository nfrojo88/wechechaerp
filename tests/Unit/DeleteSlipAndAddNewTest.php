<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Store;
use App\Models\SlipSequence;
use App\Models\DeliveryReceipt;
use App\Models\DeliveryReceiptItem;
use App\Models\Transfer;
use App\Services\SlipSequenceService;
use App\Http\Controllers\SlipSequenceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;

class DeleteSlipAndAddNewTest extends TestCase
{
    protected Store $store;
    protected User $user;
    protected SlipSequenceService $service;
    protected SlipSequenceController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        DB::statement('PRAGMA foreign_keys = OFF;');

        Schema::dropIfExists('delivery_receipt_items');
        Schema::dropIfExists('delivery_receipts');
        Schema::dropIfExists('transfers');
        Schema::dropIfExists('slip_sequences');
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
            $table->string('type')->default('warehouse');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
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
            $table->unsignedBigInteger('purchase_order_id')->nullable();
            $table->unsignedBigInteger('purchase_request_id')->nullable();
            $table->unsignedBigInteger('transfer_id')->nullable();
            $table->unsignedBigInteger('received_by')->nullable();
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('to_store_id')->nullable();
            $table->date('received_date')->nullable();
            $table->date('receipt_date')->nullable();
            $table->string('status')->default('verified');
            $table->string('slip_type')->default('receive');
            $table->string('source_type')->default('procurement');
            $table->boolean('is_void')->default(false);
            $table->string('reference_no')->nullable();
            $table->string('challan_no')->nullable();
            $table->string('vehicle_no')->nullable();
            $table->string('supplier_name')->nullable();
            $table->string('slip_receipt_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('delivery_receipt_items', function ($table) {
            $table->id();
            $table->unsignedBigInteger('delivery_receipt_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->decimal('quantity', 15, 3)->default(1);
            $table->timestamps();
        });

        Schema::create('transfers', function ($table) {
            $table->id();
            $table->string('transfer_no');
            $table->unsignedBigInteger('from_store_id');
            $table->unsignedBigInteger('to_store_id');
            $table->string('outgoing_slip_no')->nullable();
            $table->string('receiving_slip_no')->nullable();
            $table->string('physical_slip_no')->nullable();
            $table->string('status')->default('completed');
            $table->timestamps();
            $table->softDeletes();
        });

        $this->user = User::create([
            'name' => 'Store Keeper',
            'email' => 'keeper@wechecha.et',
        ]);
        Auth::login($this->user);

        $this->store = Store::create([
            'name' => 'Main Warehouse',
            'code' => 'MAIN-01',
            'is_active' => true,
        ]);

        $this->service = app(SlipSequenceService::class);
        $this->controller = app(SlipSequenceController::class);
    }

    public function test_can_delete_slip_and_add_new_slip_with_same_number(): void
    {
        $sequence = SlipSequence::create([
            'store_id'        => $this->store->id,
            'slip_type'       => 'receive',
            'label'           => 'GRN Book 101-150',
            'prefix'          => 'REC-',
            'book_start_no'   => 101,
            'book_end_no'     => 150,
            'current_slip_no' => 101,
            'used_count'      => 0,
            'status'          => 'active',
        ]);

        // Consume 101, 102, 103
        $slip101 = $this->service->consumeNext($this->store->id, 'receive');
        $dr101 = DeliveryReceipt::create([
            'dr_no'       => $slip101['slip_no'],
            'slip_no'     => $slip101['slip_no'],
            'slip_type'   => 'receive',
            'source_type' => 'procurement',
            'store_id'    => $this->store->id,
        ]);

        $slip102 = $this->service->consumeNext($this->store->id, 'receive');
        $dr102 = DeliveryReceipt::create([
            'dr_no'       => $slip102['slip_no'],
            'slip_no'     => $slip102['slip_no'],
            'slip_type'   => 'receive',
            'source_type' => 'procurement',
            'store_id'    => $this->store->id,
        ]);

        $slip103 = $this->service->consumeNext($this->store->id, 'receive');
        $dr103 = DeliveryReceipt::create([
            'dr_no'       => $slip103['slip_no'],
            'slip_no'     => $slip103['slip_no'],
            'slip_type'   => 'receive',
            'source_type' => 'procurement',
            'store_id'    => $this->store->id,
        ]);

        $this->assertEquals(104, (int)$sequence->fresh()->current_slip_no);

        // Delete slip #103 via destroyHistorySlip (source_type = procurement)
        $req = new Request([
            'source_type' => 'procurement',
            'document_id' => $dr103->id,
            'slip_no'     => $dr103->slip_no,
            'numeric_no'  => 103,
        ]);
        $response = $this->controller->destroyHistorySlip($req);

        // Verification: DeliveryReceipt is deleted
        $this->assertNull(DeliveryReceipt::find($dr103->id));

        // Verification: Sequence recalculates to 103
        $freshSeq = $sequence->fresh();
        $this->assertEquals(103, (int)$freshSeq->current_slip_no);

        // Verification: Next expected is 103
        $expected = $this->service->getNextExpected($this->store->id, 'receive');
        $this->assertEquals(103, $expected['current_slip_no']);
        $this->assertFalse($expected['is_full']);

        // Now ADD NEW SLIP with number 103: MUST ACCEPT!
        $newSlip103 = $this->service->consumeNext(
            storeId: $this->store->id,
            slipType: 'receive',
            inputSlipNo: '103',
            sourceType: 'manual'
        );
        $this->assertEquals(103, $newSlip103['numeric_no']);
        $this->assertEquals($sequence->formatSlipNumber(103), $newSlip103['slip_no']);

        // Next expected after adding 103 is now 104
        $freshSeqAfter = $sequence->fresh();
        $this->assertEquals(104, (int)$freshSeqAfter->current_slip_no);
    }

    public function test_deleting_slip_from_full_book_reactivates_book_and_accepts_new_slip(): void
    {
        // Small book: 101 to 103 (capacity 3)
        $sequence = SlipSequence::create([
            'store_id'        => $this->store->id,
            'slip_type'       => 'receive',
            'label'           => 'Small GRN Book 101-103',
            'prefix'          => '',
            'book_start_no'   => 101,
            'book_end_no'     => 103,
            'current_slip_no' => 101,
            'used_count'      => 0,
            'status'          => 'active',
        ]);

        // Consume all 3
        $dr1 = DeliveryReceipt::create([
            'dr_no'       => '101',
            'slip_no'     => '101',
            'slip_type'   => 'receive',
            'source_type' => 'manual',
            'store_id'    => $this->store->id,
        ]);
        $dr2 = DeliveryReceipt::create([
            'dr_no'       => '102',
            'slip_no'     => '102',
            'slip_type'   => 'receive',
            'source_type' => 'manual',
            'store_id'    => $this->store->id,
        ]);
        $dr3 = DeliveryReceipt::create([
            'dr_no'       => '103',
            'slip_no'     => '103',
            'slip_type'   => 'receive',
            'source_type' => 'petty_cash',
            'store_id'    => $this->store->id,
        ]);

        // Sequence is full
        $sequence->recalculate(save: true);
        $this->assertEquals('full', $sequence->fresh()->status);
        $this->assertEquals(104, (int)$sequence->fresh()->current_slip_no);

        // Delete slip #103 (petty_cash)
        $req = new Request([
            'source_type' => 'petty_cash',
            'document_id' => $dr3->id,
            'slip_no'     => '103',
            'numeric_no'  => 103,
        ]);
        $this->controller->destroyHistorySlip($req);

        // The book must automatically be reactivated to active
        $freshSeq = $sequence->fresh();
        $this->assertEquals('active', $freshSeq->status);
        $this->assertEquals(103, (int)$freshSeq->current_slip_no);

        // getNextExpected must report active and not full
        $expected = $this->service->getNextExpected($this->store->id, 'receive');
        $this->assertFalse($expected['is_full']);
        $this->assertEquals(103, $expected['current_slip_no']);

        // Now add new slip (auto-generated or manual 103) - MUST ACCEPT!
        $res = $this->service->consumeNext(
            storeId: $this->store->id,
            slipType: 'receive',
            inputSlipNo: null,
            sourceType: 'procurement'
        );

        $this->assertEquals(103, $res['numeric_no']);
        $this->assertTrue($res['is_full']); // now full again because 103 was the last leaf
    }

    public function test_can_delete_middle_slip_and_add_new_slip_into_freed_slot(): void
    {
        $sequence = SlipSequence::create([
            'store_id'        => $this->store->id,
            'slip_type'       => 'send',
            'label'           => 'SIN Book 501-550',
            'prefix'          => 'SIN-',
            'book_start_no'   => 501,
            'book_end_no'     => 550,
            'current_slip_no' => 501,
            'used_count'      => 0,
            'status'          => 'active',
        ]);

        // Create 501, 502, 503
        $dr501 = DeliveryReceipt::create([
            'dr_no'       => 'SIN-501',
            'slip_no'     => 'SIN-501',
            'slip_type'   => 'send',
            'source_type' => 'transfer_out',
            'store_id'    => $this->store->id,
        ]);
        $dr502 = DeliveryReceipt::create([
            'dr_no'       => 'SIN-502',
            'slip_no'     => 'SIN-502',
            'slip_type'   => 'send',
            'source_type' => 'transfer_out',
            'store_id'    => $this->store->id,
        ]);
        $dr503 = DeliveryReceipt::create([
            'dr_no'       => 'SIN-503',
            'slip_no'     => 'SIN-503',
            'slip_type'   => 'send',
            'source_type' => 'transfer_out',
            'store_id'    => $this->store->id,
        ]);

        $sequence->recalculate(save: true);
        $this->assertEquals(504, (int)$sequence->fresh()->current_slip_no);

        // Delete middle slip #502 (transfer_out)
        $req = new Request([
            'source_type' => 'transfer_out',
            'document_id' => $dr502->id,
            'slip_no'     => 'SIN-502',
            'numeric_no'  => 502,
        ]);
        $this->controller->destroyHistorySlip($req);

        // Sequence must find 502 as next free
        $freshSeq = $sequence->fresh();
        $this->assertEquals(502, (int)$freshSeq->current_slip_no);

        // Adding 502 must be accepted
        $newSlip502 = $this->service->consumeNext(
            storeId: $this->store->id,
            slipType: 'send',
            inputSlipNo: '502',
            sourceType: 'manual'
        );
        $this->assertEquals(502, $newSlip502['numeric_no']);
        $this->assertEquals($sequence->formatSlipNumber(502), $newSlip502['slip_no']);

        // Next expected after filling 502 is 504 (because 503 is already used)
        $this->assertEquals(504, (int)$sequence->fresh()->current_slip_no);
    }
}
