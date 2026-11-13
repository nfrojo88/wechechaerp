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
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\ProcurementPayment;
use App\Models\ExpenseHubVatDecision;
use App\Models\PettyCashMaterialPurchase;
use App\Services\ProcurementLifecycleService;
use App\Services\ExpenseHubQueryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;

class PhysicalSlipReceiptUploadTest extends TestCase
{
    protected Store $store;
    protected User $user;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        DB::statement('PRAGMA foreign_keys = OFF;');

        Schema::dropIfExists('pr_workflow_logs');
        Schema::dropIfExists('expense_hub_vat_decisions');
        Schema::dropIfExists('procurement_payments');
        Schema::dropIfExists('proforma_invoices');
        Schema::dropIfExists('credit_store_ledgers');
        Schema::dropIfExists('petty_cash_material_purchase_items');
        Schema::dropIfExists('petty_cash_material_purchases');
        Schema::dropIfExists('delivery_receipt_items');
        Schema::dropIfExists('delivery_receipts');
        Schema::dropIfExists('slip_sequences');
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory');
        Schema::dropIfExists('purchase_request_items');
        Schema::dropIfExists('purchase_requests');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('products');
        Schema::dropIfExists('stores');
        Schema::dropIfExists('users');

        Schema::create('pr_workflow_logs', function ($table) {
            $table->id();
            $table->foreignId('purchase_request_id');
            $table->string('from_stage')->nullable();
            $table->string('to_stage')->nullable();
            $table->string('action')->nullable();
            $table->string('actor_role')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('actor_id')->nullable();
            $table->timestamps();
        });

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
            $table->string('type')->default('site');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('products', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('unit')->default('pcs');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('purchase_orders', function ($table) {
            $table->id();
            $table->foreignId('project_id')->nullable();
            $table->foreignId('purchase_request_id')->nullable();
            $table->string('reference_number')->nullable();
            $table->string('supplier_name')->nullable();
            $table->foreignId('supplier_id')->nullable();
            $table->string('status')->default('delivered');
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->date('issued_date')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_requests', function ($table) {
            $table->id();
            $table->string('pr_no')->nullable();
            $table->foreignId('store_id')->nullable();
            $table->foreignId('project_id')->nullable();
            $table->string('status')->default('pending_store_review');
            $table->string('current_owner_role')->nullable();
            $table->string('supplier_name')->nullable();
            $table->foreignId('requested_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('purchase_request_items', function ($table) {
            $table->id();
            $table->foreignId('purchase_request_id');
            $table->foreignId('product_id')->nullable();
            $table->decimal('quantity', 12, 3)->default(0);
            $table->decimal('received_quantity', 12, 3)->default(0);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->string('unit')->default('pcs');
            $table->timestamps();
        });

        Schema::create('credit_store_ledgers', function ($table) {
            $table->id();
            $table->foreignId('purchase_request_id')->nullable();
            $table->string('supplier_name')->nullable();
            $table->foreignId('supplier_id')->nullable();
            $table->timestamps();
        });

        Schema::create('proforma_invoices', function ($table) {
            $table->id();
            $table->foreignId('purchase_request_id')->nullable();
            $table->boolean('gm_selected')->default(false);
            $table->foreignId('supplier_id')->nullable();
            $table->timestamps();
        });

        Schema::create('procurement_payments', function ($table) {
            $table->id();
            $table->foreignId('purchase_request_id');
            $table->decimal('amount', 14, 2)->default(0);
            $table->decimal('gross_amount', 14, 2)->default(0);
            $table->decimal('vat_amount', 14, 2)->default(0);
            $table->string('status')->default('paid');
            $table->string('receipt_path')->nullable();
            $table->string('withholding_receipt')->nullable();
            $table->string('withholding_receipt_number')->nullable();
            $table->decimal('net_amount', 14, 2)->default(0);
            $table->foreignId('paid_by')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('slip_sequences', function ($table) {
            $table->id();
            $table->foreignId('store_id');
            $table->enum('slip_type', ['receive', 'send']);
            $table->string('book_number')->nullable();
            $table->unsignedInteger('book_start_no');
            $table->unsignedInteger('book_end_no');
            $table->unsignedInteger('current_slip_no');
            $table->unsignedInteger('used_count')->default(0);
            $table->string('prefix')->nullable();
            $table->enum('status', ['active', 'inactive', 'exhausted'])->default('active');
            $table->string('label')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('delivery_receipts', function ($table) {
            $table->id();
            $table->string('dr_no');
            $table->string('slip_no')->nullable();
            $table->enum('slip_type', ['receive', 'send'])->default('receive');
            $table->string('source_type')->default('procurement');
            $table->enum('sequence_status', ['valid', 'gap', 'pending'])->default('valid');
            $table->foreignId('purchase_order_id')->nullable();
            $table->foreignId('purchase_request_id')->nullable();
            $table->foreignId('store_id')->nullable();
            $table->foreignId('to_store_id')->nullable();
            $table->date('received_date')->nullable();
            $table->date('receipt_date')->nullable();
            $table->foreignId('received_by')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->string('supplier_name')->nullable();
            $table->string('reference_no')->nullable();
            $table->string('status')->default('verified');
            $table->boolean('is_void')->default(false);
            $table->text('notes')->nullable();
            $table->string('slip_receipt_path')->nullable();
            $table->boolean('is_physical_store_only')->default(false);
            $table->text('physical_slip_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('delivery_receipt_items', function ($table) {
            $table->id();
            $table->foreignId('delivery_receipt_id');
            $table->foreignId('product_id')->nullable();
            $table->decimal('quantity_received', 12, 3)->default(0);
            $table->decimal('accepted_quantity', 12, 3)->default(0);
            $table->decimal('rejected_quantity', 12, 3)->default(0);
            $table->string('unit')->default('pcs');
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory', function ($table) {
            $table->id();
            $table->foreignId('store_id');
            $table->foreignId('product_id');
            $table->decimal('quantity_on_hand', 14, 3)->default(0);
            $table->decimal('quantity_reserved', 14, 3)->default(0);
            $table->decimal('unit_cost', 14, 2)->default(0);
            $table->decimal('min_stock', 14, 3)->default(0);
            $table->timestamp('last_movement_at')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_movements', function ($table) {
            $table->id();
            $table->foreignId('inventory_id');
            $table->string('type');
            $table->decimal('quantity', 14, 3);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('performed_by')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('petty_cash_material_purchases', function ($table) {
            $table->id();
            $table->string('purchase_no');
            $table->foreignId('store_id');
            $table->foreignId('chart_of_account_id')->nullable();
            $table->foreignId('purchased_by')->nullable();
            $table->date('purchase_date')->nullable();
            $table->string('supplier_name')->nullable();
            $table->string('supplier_tin')->nullable();
            $table->string('fs_no')->nullable();
            $table->string('receipt_no')->nullable();
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->string('attachment_path')->nullable();
            $table->foreignId('material_request_id')->nullable();
            $table->foreignId('delivery_receipt_id')->nullable();
            $table->boolean('is_physical_store_only')->default(false);
            $table->string('status')->default('completed');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('petty_cash_material_purchase_items', function ($table) {
            $table->id();
            $table->foreignId('purchase_id');
            $table->foreignId('product_id')->nullable();
            $table->decimal('quantity', 14, 3)->default(0);
            $table->string('unit')->default('pcs');
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('total_price', 14, 2)->default(0);
            $table->string('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('expense_hub_vat_decisions', function ($table) {
            $table->id();
            $table->string('source_type');
            $table->unsignedBigInteger('source_id');
            $table->string('vat_status');
            $table->boolean('has_withholding')->default(false);
            $table->decimal('withholding_rate', 5, 2)->default(0);
            $table->decimal('withholding_amount', 14, 2)->default(0);
            $table->string('withholding_receipt')->nullable();
            $table->string('withholding_receipt_number')->nullable();
            $table->decimal('net_payable_amount', 14, 2)->default(0);
            $table->decimal('vat_rate', 5, 2)->default(15);
            $table->boolean('is_inclusive')->default(false);
            $table->decimal('base_amount', 14, 2)->default(0);
            $table->decimal('vat_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->string('no_vat_reason_code')->nullable();
            $table->string('no_vat_reason_note')->nullable();
            $table->boolean('is_verified_without_receipt')->default(false);
            $table->string('receipt_path')->nullable();
            $table->integer('ocr_confidence_score')->default(0);
            $table->text('raw_ocr_payload')->nullable();
            $table->string('vat_category')->nullable();
            $table->string('purc_type')->nullable();
            $table->string('calendar_type')->default('GC');
            $table->integer('purchase_type')->nullable();
            $table->string('custom_seller_name')->nullable();
            $table->string('custom_seller_tin')->nullable();
            $table->string('custom_fs_no')->nullable();
            $table->string('custom_mrc_no')->nullable();
            $table->string('custom_item_description')->nullable();
            $table->date('custom_receipt_date')->nullable();
            $table->string('uom')->nullable();
            $table->decimal('quantity', 14, 2)->default(0);
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->boolean('is_locked')->default(false);
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable();
            $table->foreignId('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });

        $this->user = User::create([
            'name'  => 'Store Keeper John',
            'email' => 'keeper@wechecha.com',
        ]);
        Auth::login($this->user);

        $this->store = Store::create([
            'name' => 'Main Site Warehouse',
            'code' => 'STORE-MAIN',
        ]);

        $this->product = Product::create([
            'name' => 'Cement Grade 42.5',
            'code' => 'CEM-001',
            'unit' => 'bags',
        ]);

        SlipSequence::create([
            'store_id'        => $this->store->id,
            'slip_type'       => 'receive',
            'book_number'     => 'BK-01',
            'book_start_no'   => 1,
            'book_end_no'     => 100,
            'current_slip_no' => 1,
            'prefix'          => 'REC-',
            'status'          => 'active',
            'label'           => 'Receiving (GRN)',
        ]);
    }

    public function test_delivery_receipt_model_physical_store_fields_and_scopes()
    {
        $drPhysical = DeliveryReceipt::create([
            'dr_no'                  => 'REC-0001',
            'slip_no'                => 'REC-0001',
            'slip_type'              => 'receive',
            'store_id'               => $this->store->id,
            'slip_receipt_path'      => 'slips/receipt_scan_01.jpg',
            'is_physical_store_only' => true,
            'physical_slip_notes'    => 'Internal Physical Store Slip Only',
        ]);

        $drVat = DeliveryReceipt::create([
            'dr_no'                  => 'REC-0002',
            'slip_no'                => 'REC-0002',
            'slip_type'              => 'receive',
            'store_id'               => $this->store->id,
            'slip_receipt_path'      => 'slips/official_tax_invoice.pdf',
            'is_physical_store_only' => false,
        ]);

        $this->assertTrue($drPhysical->is_physical_store_only);
        $this->assertFalse($drVat->is_physical_store_only);
        $this->assertEquals('slips/receipt_scan_01.jpg', $drPhysical->slip_receipt_path);
        $this->assertNotNull($drPhysical->slip_receipt_url);

        // Scopes test
        $vatEligible = DeliveryReceipt::vatEligible()->pluck('dr_no')->toArray();
        $this->assertContains('REC-0002', $vatEligible);
        $this->assertNotContains('REC-0001', $vatEligible);

        $storeOnly = DeliveryReceipt::storeOnly()->pluck('dr_no')->toArray();
        $this->assertContains('REC-0001', $storeOnly);
        $this->assertNotContains('REC-0002', $storeOnly);
    }

    public function test_procurement_lifecycle_store_intake_with_physical_store_slip_and_file()
    {
        $pr = PurchaseRequest::create([
            'pr_no'        => 'PR-2026-001',
            'store_id'     => $this->store->id,
            'status'       => PurchaseRequest::STATUS_PENDING_STORE_REVIEW,
            'requested_by' => $this->user->id,
        ]);

        $item = PurchaseRequestItem::create([
            'purchase_request_id' => $pr->id,
            'product_id'          => $this->product->id,
            'quantity'            => 50,
            'received_quantity'   => 0,
            'unit_price'          => 450,
            'unit'                => 'bags',
        ]);

        $payment = ProcurementPayment::create([
            'purchase_request_id' => $pr->id,
            'amount'              => 22500,
            'gross_amount'        => 22500,
            'vat_amount'          => 2934.78,
            'status'              => 'paid',
        ]);

        $service = app(ProcurementLifecycleService::class);

        $result = $service->storeIntake(
            pr: $pr,
            storeId: $this->store->id,
            slipNo: 'REC-0001',
            receivedDate: '2026-11-04',
            receivedItems: [
                $item->id => ['quantity' => 50, 'accepted_quantity' => 50]
            ],
            notes: 'Physical intake at site',
            slipReceiptPath: 'slips/sample_receipt.pdf',
            isPhysicalStoreOnly: true
        );

        $this->assertTrue($result['is_full']);

        // Check DeliveryReceipt
        $receipt = DeliveryReceipt::where('dr_no', $result['slip_no'])->first();
        $this->assertNotNull($receipt);
        $this->assertTrue($receipt->is_physical_store_only);
        $this->assertEquals('slips/sample_receipt.pdf', $receipt->slip_receipt_path);

        // Check Inventory was updated
        $inv = Inventory::where('store_id', $this->store->id)->where('product_id', $this->product->id)->first();
        $this->assertEquals(50, (float)$inv->quantity_on_hand);

        // Check ExpenseHubVatDecision was auto-synced to STATUS_NO_VAT with physical_store_slip reason
        $vatDecision = ExpenseHubVatDecision::where('source_type', 'procurement_payment')
            ->where('source_id', $payment->id)
            ->first();

        $this->assertNotNull($vatDecision);
        $this->assertEquals(ExpenseHubVatDecision::STATUS_NO_VAT, $vatDecision->vat_status);
        $this->assertEquals('physical_store_slip', $vatDecision->no_vat_reason_code);
    }

    public function test_procurement_store_intake_fails_and_rejects_without_slip_upload()
    {
        $pr = PurchaseRequest::create([
            'pr_no'        => 'PR-2026-NOSLIP',
            'store_id'     => $this->store->id,
            'status'       => PurchaseRequest::STATUS_PENDING_STORE_REVIEW,
            'requested_by' => $this->user->id,
        ]);

        $item = PurchaseRequestItem::create([
            'purchase_request_id' => $pr->id,
            'product_id'          => $this->product->id,
            'quantity'            => 10,
            'received_quantity'   => 0,
            'unit_price'          => 100,
            'unit'                => 'pcs',
        ]);

        $service = app(ProcurementLifecycleService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Physical receiving slip upload is strictly required');

        $service->storeIntake(
            pr: $pr,
            storeId: $this->store->id,
            slipNo: 'REC-0002',
            receivedDate: '2026-11-04',
            receivedItems: [
                $item->id => ['quantity' => 10, 'accepted_quantity' => 10]
            ],
            notes: 'Attempt intake without slip receipt',
            slipReceiptPath: null
        );
    }

    public function test_procurement_store_intake_rejects_empty_slip_upload()
    {
        $pr = PurchaseRequest::create([
            'pr_no'        => 'PR-2026-EMPTYSLIP',
            'store_id'     => $this->store->id,
            'status'       => PurchaseRequest::STATUS_PENDING_STORE_REVIEW,
            'requested_by' => $this->user->id,
        ]);

        $item = PurchaseRequestItem::create([
            'purchase_request_id' => $pr->id,
            'product_id'          => $this->product->id,
            'quantity'            => 5,
            'received_quantity'   => 0,
            'unit_price'          => 50,
            'unit'                => 'pcs',
        ]);

        $service = app(ProcurementLifecycleService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Physical receiving slip upload is strictly required');

        $service->storeIntake(
            pr: $pr,
            storeId: $this->store->id,
            slipNo: 'REC-0003',
            receivedDate: '2026-11-04',
            receivedItems: [
                $item->id => ['quantity' => 5, 'accepted_quantity' => 5]
            ],
            notes: 'Attempt intake with empty slip string',
            slipReceiptPath: ''
        );
    }

    public function test_petty_cash_material_purchase_with_physical_store_slip()
    {
        $pc = PettyCashMaterialPurchase::create([
            'purchase_no'            => 'PCMP-20261104-0001',
            'store_id'               => $this->store->id,
            'total_amount'           => 1200,
            'is_physical_store_only' => true,
            'status'                 => 'completed',
        ]);

        $this->assertTrue($pc->is_physical_store_only);
    }

    public function test_expense_hub_query_service_excludes_physical_store_slips_from_vat()
    {
        // 1. PR with physical store intake
        $pr = PurchaseRequest::create([
            'pr_no'        => 'PR-2026-PHYS',
            'store_id'     => $this->store->id,
            'status'       => PurchaseRequest::STATUS_INTAKE_COMPLETE,
            'requested_by' => $this->user->id,
        ]);

        DeliveryReceipt::create([
            'dr_no'                  => 'REC-PHYS-01',
            'slip_no'                => 'REC-PHYS-01',
            'purchase_request_id'    => $pr->id,
            'store_id'               => $this->store->id,
            'is_physical_store_only' => true,
        ]);

        $payment = ProcurementPayment::create([
            'purchase_request_id' => $pr->id,
            'amount'              => 10000,
            'gross_amount'        => 10000,
            'vat_amount'          => 1304.35,
            'status'              => 'paid',
        ]);

        // 2. Petty Cash with physical store only
        $pc = PettyCashMaterialPurchase::create([
            'purchase_no'            => 'PCMP-PHYS-01',
            'store_id'               => $this->store->id,
            'total_amount'           => 500,
            'is_physical_store_only' => true,
            'status'                 => 'approved',
        ]);

        $service = app(ExpenseHubQueryService::class);
        $expenses = $service->getExpenses([], null);

        // Verify PR payment defaults to NO_VAT because of physical store only slip
        $procItem = $expenses->firstWhere('source_type', 'procurement_payment');
        $this->assertNotNull($procItem);
        $this->assertEquals(ExpenseHubVatDecision::STATUS_NO_VAT, $procItem->default_status);
        $this->assertEquals('physical_store_slip', $procItem->default_reason_code);

        // Verify Petty Cash defaults to NO_VAT because of physical store only
        $pettyItem = $expenses->firstWhere('source_type', 'petty_cash_purchase');
        $this->assertNotNull($pettyItem);
        $this->assertEquals(ExpenseHubVatDecision::STATUS_NO_VAT, $pettyItem->default_status);
        $this->assertEquals('physical_store_slip', $pettyItem->default_reason_code);
    }
}
