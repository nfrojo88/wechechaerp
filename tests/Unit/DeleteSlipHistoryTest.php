<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Store;
use App\Models\SlipSequence;
use App\Models\DeliveryReceipt;
use App\Models\DeliveryReceiptItem;
use App\Models\Transfer;
use App\Http\Controllers\SlipSequenceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;

class DeleteSlipHistoryTest extends TestCase
{
    protected Store $store;
    protected User $user;
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
            $table->string('transfer_no')->nullable();
            $table->string('outgoing_slip_no')->nullable();
            $table->string('receiving_slip_no')->nullable();
            $table->string('physical_slip_no')->nullable();
            $table->string('outgoing_slip_file')->nullable();
            $table->string('receiving_slip_file')->nullable();
            $table->unsignedBigInteger('from_store_id')->nullable();
            $table->unsignedBigInteger('to_store_id')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->unsignedBigInteger('dispatched_by')->nullable();
            $table->unsignedBigInteger('received_by')->nullable();
            $table->unsignedBigInteger('driver_id')->nullable();
            $table->string('status')->default('approved');
            $table->text('reason')->nullable();
            $table->text('dispatch_notes')->nullable();
            $table->text('receiving_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $this->user = User::create([
            'name'     => 'Store Manager',
            'email'    => 'manager@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->store = Store::create([
            'name'      => 'Central Warehouse',
            'code'      => 'CW-01',
            'is_active' => true,
        ]);

        Auth::login($this->user);

        $this->controller = new SlipSequenceController();
    }

    /**
     * Test deleting a single slip history record that has no sequence assigned.
     */
    public function test_can_delete_single_slip_with_no_sequence()
    {
        // Create an unassigned Delivery Receipt (no slip sequence book covers 9999)
        $receipt = DeliveryReceipt::create([
            'store_id'      => $this->store->id,
            'dr_no'         => '9999',
            'slip_type'     => 'receive',
            'source_type'   => 'procurement',
            'status'        => 'verified',
            'supplier_name' => 'Ad-hoc Vendor',
        ]);

        DeliveryReceiptItem::create([
            'delivery_receipt_id' => $receipt->id,
            'quantity'            => 5,
        ]);

        // Verify it exists in global slip history without sequence
        $history = SlipSequence::getGlobalSlipHistory();
        $slipItem = $history->firstWhere('numeric_no', 9999);
        $this->assertNotNull($slipItem);
        $this->assertNull($slipItem['book_id']);
        $this->assertEquals('Manual / Other', $slipItem['book_range']);

        // Delete the slip history item
        $request = Request::create(route('store-manager.slip-history.destroy'), 'DELETE', [
            'source_type' => 'delivery_receipt',
            'document_id' => $receipt->id,
            'numeric_no'  => 9999,
            'slip_no'     => '9999',
        ]);

        $response = $this->controller->destroyHistorySlip($request);

        // Assert record is deleted from DB
        $this->assertDatabaseMissing('delivery_receipts', ['id' => $receipt->id]);
        $this->assertDatabaseMissing('delivery_receipt_items', ['delivery_receipt_id' => $receipt->id]);

        // Assert history is now empty
        $newHistory = SlipSequence::getGlobalSlipHistory();
        $this->assertNull($newHistory->firstWhere('numeric_no', 9999));
    }

    /**
     * Test bulk deleting all slips with no sequence assigned.
     */
    public function test_can_bulk_delete_unassigned_slips_with_no_sequence()
    {
        // 1. Create a configured sequence book (#1 to #50)
        $seq = SlipSequence::create([
            'store_id'        => $this->store->id,
            'slip_type'       => 'receive',
            'label'           => 'Book 1',
            'book_start_no'   => 1,
            'book_end_no'     => 50,
            'current_slip_no' => 11,
            'used_count'      => 1,
            'status'          => 'active',
        ]);

        // 2. Create a legitimate slip that belongs to the sequence (#10)
        $validReceipt = DeliveryReceipt::create([
            'store_id'      => $this->store->id,
            'dr_no'         => '00010',
            'slip_type'     => 'receive',
            'source_type'   => 'procurement',
            'status'        => 'verified',
            'supplier_name' => 'Valid Supplier',
        ]);

        // 3. Create 3 unassigned slips (no sequence covers them)
        $unassigned1 = DeliveryReceipt::create([
            'store_id'      => $this->store->id,
            'dr_no'         => '10001',
            'slip_type'     => 'receive',
            'supplier_name' => 'Unassigned 1',
        ]);
        $unassigned2 = DeliveryReceipt::create([
            'store_id'      => $this->store->id,
            'dr_no'         => '10002',
            'slip_type'     => 'receive',
            'supplier_name' => 'Unassigned 2',
        ]);
        $unassigned3 = DeliveryReceipt::create([
            'store_id'      => $this->store->id,
            'dr_no'         => '10003',
            'slip_type'     => 'receive',
            'supplier_name' => 'Unassigned 3',
        ]);

        // Call bulk delete unassigned history
        $request = Request::create(route('store-manager.slip-history.delete-unassigned'), 'POST', [
            'store_id' => $this->store->id,
        ]);

        $response = $this->controller->destroyUnassignedHistory($request);

        // Assert unassigned receipts are deleted
        $this->assertDatabaseMissing('delivery_receipts', ['id' => $unassigned1->id]);
        $this->assertDatabaseMissing('delivery_receipts', ['id' => $unassigned2->id]);
        $this->assertDatabaseMissing('delivery_receipts', ['id' => $unassigned3->id]);

        // Assert valid receipt inside the sequence is 100% PRESERVED
        $this->assertDatabaseHas('delivery_receipts', ['id' => $validReceipt->id]);
    }

    /**
     * Test deleting an empty slip sequence where no slips are added.
     */
    public function test_can_delete_empty_slip_sequence_with_no_slips()
    {
        $seq = SlipSequence::create([
            'store_id'        => $this->store->id,
            'slip_type'       => 'send',
            'label'           => 'Empty SIN Book',
            'book_start_no'   => 501,
            'book_end_no'     => 600,
            'current_slip_no' => 501,
            'used_count'      => 0,
            'status'          => 'active',
        ]);

        $request = Request::create(route('store-manager.slip-sequences.destroy', $seq), 'DELETE');

        $response = $this->controller->destroy($seq, $request);

        // Assert sequence was deleted
        $this->assertDatabaseMissing('slip_sequences', ['id' => $seq->id]);
    }

    /**
     * Test that deleting a sequence with slips requires force confirmation.
     */
    public function test_cannot_delete_sequence_with_slips_without_force()
    {
        $seq = SlipSequence::create([
            'store_id'        => $this->store->id,
            'slip_type'       => 'receive',
            'label'           => 'Active Book',
            'book_start_no'   => 1,
            'book_end_no'     => 50,
            'current_slip_no' => 2,
            'used_count'      => 1,
            'status'          => 'active',
        ]);

        // Assign a slip
        DeliveryReceipt::create([
            'store_id'    => $this->store->id,
            'dr_no'       => '1',
            'slip_type'   => 'receive',
        ]);

        // Try deleting without force
        $request = Request::create(route('store-manager.slip-sequences.destroy', $seq), 'DELETE');
        $response = $this->controller->destroy($seq, $request);

        // Assert sequence is still in database
        $this->assertDatabaseHas('slip_sequences', ['id' => $seq->id]);

        // Now delete with force
        $forceRequest = Request::create(route('store-manager.slip-sequences.destroy', $seq), 'DELETE', ['force' => 1]);
        $this->controller->destroy($seq, $forceRequest);

        // Assert sequence is deleted
        $this->assertDatabaseMissing('slip_sequences', ['id' => $seq->id]);
    }

    /**
     * Test deleting a transfer slip from slip history clears the slip without deleting the transfer.
     */
    public function test_deleting_transfer_slip_clears_slip_field_preserving_transfer()
    {
        $transfer = Transfer::create([
            'transfer_no'       => 'TR-2026-001',
            'from_store_id'     => $this->store->id,
            'outgoing_slip_no'  => '7777',
            'receiving_slip_no' => null,
            'status'            => 'approved',
        ]);

        $request = Request::create(route('store-manager.slip-history.destroy'), 'DELETE', [
            'source_type' => 'transfer',
            'document_id' => $transfer->id,
            'numeric_no'  => 7777,
            'slip_no'     => '7777',
        ]);

        $this->controller->destroyHistorySlip($request);

        // Refresh transfer
        $transfer->refresh();

        // Outgoing slip number should be cleared
        $this->assertNull($transfer->outgoing_slip_no);

        // The transfer itself still exists!
        $this->assertEquals('TR-2026-001', $transfer->transfer_no);
        $this->assertDatabaseHas('transfers', ['id' => $transfer->id]);
    }
}
