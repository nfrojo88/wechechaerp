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
use App\Services\SlipSequenceService;
use App\Exceptions\SlipSequenceException;
use App\Http\Controllers\SlipSequenceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VoidPhysicalSlipOptionTest extends TestCase
{
    protected SlipSequenceService $service;
    protected Store $store;
    protected User $user;
    protected SlipSequence $sequence;

    protected function setUp(): void
    {
        parent::setUp();

        DB::statement('PRAGMA foreign_keys = OFF;');

        Schema::dropIfExists('delivery_receipts');
        Schema::dropIfExists('slip_sequences');
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory');
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
            $table->string('slip_receipt_path')->nullable();
            $table->boolean('is_physical_store_only')->default(false);
            $table->text('physical_slip_notes')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'slip_type', 'slip_no'], 'idx_store_slip_unique');
        });

        DB::statement('PRAGMA foreign_keys = ON;');

        $this->service = new SlipSequenceService();

        $this->user = User::create([
            'id'    => 1,
            'name'  => 'Test Store Keeper',
            'email' => 'storekeeper@example.com',
        ]);

        $this->store = Store::create([
            'id'        => 1,
            'name'      => 'Central Main Store',
            'code'      => 'CMS-01',
            'is_active' => true,
        ]);

        $this->sequence = SlipSequence::create([
            'store_id'        => $this->store->id,
            'slip_type'       => 'receive',
            'label'           => 'Main Book 2026',
            'prefix'          => 'REC-',
            'book_start_no'   => 100,
            'book_end_no'     => 150,
            'current_slip_no' => 100,
            'used_count'      => 0,
            'status'          => 'active',
        ]);
    }

    public function test_parse_slip_numbers_input_handles_various_formats(): void
    {
        // Single numeric string
        $this->assertEquals([105], $this->service->parseSlipNumbersInput('105'));

        // Comma separated
        $this->assertEquals([105, 106, 107], $this->service->parseSlipNumbersInput('105, 106, 107'));

        // Range
        $this->assertEquals([105, 106, 107, 108], $this->service->parseSlipNumbersInput('105-108'));

        // Formatted with prefix
        $this->assertEquals([105], $this->service->parseSlipNumbersInput('REC-105'));

        // Range with prefix
        $this->assertEquals([105, 106, 107], $this->service->parseSlipNumbersInput('REC-105 - REC-107'));

        // Array
        $this->assertEquals([105, 106], $this->service->parseSlipNumbersInput(['105', 106]));

        // Empty
        $this->assertEquals([], $this->service->parseSlipNumbersInput(''));
    }

    public function test_voiding_spoiled_slip_advances_sequence_without_inventory_movement(): void
    {
        $movementsBefore = InventoryMovement::count();

        // Slip 100 is spoiled on paper
        $result = $this->service->voidMissingSlips(
            storeId: $this->store->id,
            slipType: 'receive',
            numbers: [100],
            reason: 'Physical paper spoiled while storekeeper wrote receipt',
            userId: $this->user->id
        );

        $this->assertEquals(1, $result['voided_count']);
        $this->assertEquals('REC-00101', $result['next_expected']);
        $this->assertEquals($movementsBefore, InventoryMovement::count());

        // Verify receipt created with is_void = true
        $this->assertDatabaseHas('delivery_receipts', [
            'store_id'    => $this->store->id,
            'slip_no'     => 'REC-00100',
            'is_void'     => 1,
            'source_type' => 'void',
            'void_reason' => 'Physical paper spoiled while storekeeper wrote receipt',
        ]);

        // Sequence current_slip_no now points to 101
        $this->sequence->refresh();
        $this->assertEquals(101, $this->sequence->current_slip_no);
        $this->assertEquals(101, $this->sequence->nextFreeNumber());
    }

    public function test_voiding_range_of_damaged_slips(): void
    {
        // Slips 100, 101, 102 got damaged by rain/water in the warehouse
        $result = $this->service->voidMissingSlips(
            storeId: $this->store->id,
            slipType: 'receive',
            numbers: [100, 101, 102],
            reason: 'Water damaged slips 100 to 102',
            userId: $this->user->id
        );

        $this->assertEquals(3, $result['voided_count']);
        $this->assertEquals('REC-00103', $result['next_expected']);

        $this->sequence->refresh();
        $this->assertEquals(103, $this->sequence->current_slip_no);
        $this->assertEquals(103, $this->sequence->nextFreeNumber());
    }

    public function test_cannot_void_already_consumed_valid_slip(): void
    {
        // First consume slip 100 validly
        DeliveryReceipt::create([
            'dr_no'         => 'REC-00100',
            'slip_no'       => 'REC-00100',
            'slip_type'     => 'receive',
            'source_type'   => 'procurement',
            'store_id'      => $this->store->id,
            'received_date' => now()->toDateString(),
            'status'        => 'verified',
            'is_void'       => false,
        ]);

        $this->sequence->updateQuietly(['current_slip_no' => 101, 'used_count' => 1]);

        // Trying to void slip 100 must fail because it is actively used
        $this->expectException(SlipSequenceException::class);
        $this->expectExceptionMessage('already used by an active Procurement transaction');

        $this->service->voidMissingSlips(
            storeId: $this->store->id,
            slipType: 'receive',
            numbers: [100],
            reason: 'Try to void already used slip'
        );
    }

    public function test_slip_sequence_controller_void_missing_api(): void
    {
        $controller = new SlipSequenceController();

        $request = Request::create('/api/slip-sequences/void-missing', 'POST', [
            'store_id'          => $this->store->id,
            'slip_type'         => 'receive',
            'slip_number_input' => '100, 101',
            'reason'            => 'Leaves torn from pad',
        ]);
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');

        $response = $controller->voidMissing($request);
        $responseData = json_decode($response->getContent(), true);

        $this->assertTrue($responseData['success']);
        $this->assertEquals(2, $responseData['voided_count']);
        $this->assertEquals('REC-00102', $responseData['next_expected']);
    }
}
