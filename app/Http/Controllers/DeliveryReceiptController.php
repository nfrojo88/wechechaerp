<?php

namespace App\Http\Controllers;

use App\Models\DeliveryReceipt;
use App\Models\ProcurementReceipt;
use App\Models\CreditStoreLedger;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Store;
use App\Services\InventoryService;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class DeliveryReceiptController extends Controller
{
    public function __construct(private InventoryService $inventoryService) {}

    public function index(Request $request)
    {
        $activeTab = $request->input('tab', 'pr_receipts');
        $search = $request->input('search');
        $statusFilter = $request->input('status', 'all');

        // 1. Vendor Purchase Receipts (PR Receipts uploaded by Procurement for Finance verification)
        $prReceiptQuery = ProcurementReceipt::with([
            'purchaseRequest.project',
            'purchaseRequest.requestedBy',
            'purchaseRequest.supplier',
            'purchaseRequest.payment.coaAccount',
            'purchaseRequest.payment.assignedStaff',
            'uploadedBy',
            'verifiedBy'
        ]);

        if ($search) {
            $prReceiptQuery->where(function($q) use ($search) {
                $q->where('original_filename', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhere('verification_notes', 'like', "%{$search}%")
                  ->orWhereHas('purchaseRequest', function($pq) use ($search) {
                      $pq->where('pr_no', 'like', "%{$search}%")
                         ->orWhere('title', 'like', "%{$search}%");
                  })
                  ->orWhereHas('uploadedBy', function($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if ($statusFilter !== 'all') {
            $prReceiptQuery->where('verification_status', $statusFilter);
        }

        $procurementReceipts = $prReceiptQuery->latest()->paginate(15, ['*'], 'pr_page')->withQueryString();

        // 2. Store Goods Delivery Receipts (GRN / Model 19)
        $drQuery = DeliveryReceipt::with(['purchaseOrder.supplier', 'purchaseOrder.project', 'store', 'receivedBy', 'items.product']);
        if ($search) {
            $drQuery->where(function($q) use ($search) {
                $q->where('dr_no', 'like', "%{$search}%")
                  ->orWhere('challan_no', 'like', "%{$search}%")
                  ->orWhere('vehicle_no', 'like', "%{$search}%")
                  ->orWhereHas('store', function($sq) use ($search) {
                      $sq->where('name', 'like', "%{$search}%");
                  });
            });
        }
        $deliveryReceipts = $drQuery->latest()->paginate(15, ['*'], 'dr_page')->withQueryString();

        // 3. Credit Store Purchases & Invoices (COA 5110)
        $creditReceiptQuery = CreditStoreLedger::with(['purchaseRequest.project', 'project', 'payments', 'coaAccount']);
        if ($search) {
            $creditReceiptQuery->where(function($q) use ($search) {
                $q->where('pr_no', 'like', "%{$search}%")
                  ->orWhere('supplier_name', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%");
            });
        }
        $creditReceipts = $creditReceiptQuery->latest()->paginate(15, ['*'], 'credit_page')->withQueryString();

        // Stat counts
        $pendingPrReceiptsCount = ProcurementReceipt::where('verification_status', 'pending')->count();
        $verifiedPrReceiptsCount = ProcurementReceipt::where('verification_status', 'verified')->count();
        $totalPrReceiptsCount = ProcurementReceipt::count();
        $totalDeliveryReceiptsCount = DeliveryReceipt::count();

        // Check if user is finance staff / finance head / admin
        $user = Auth::user();
        $roles = $user ? $user->roles->pluck('name')->map(fn($r) => strtolower(str_replace([' ', '-'], '_', trim($r))))->toArray() : [];
        $isFinance = in_array('finance', $roles) || in_array('finance_staff', $roles) || in_array('finance_head', $roles) || in_array('finance_manager', $roles) || in_array('admin', $roles) || in_array('global_admin', $roles);

        return view('procurement.delivery-receipts.index', compact(
            'procurementReceipts',
            'deliveryReceipts',
            'creditReceipts',
            'activeTab',
            'pendingPrReceiptsCount',
            'verifiedPrReceiptsCount',
            'totalPrReceiptsCount',
            'totalDeliveryReceiptsCount',
            'isFinance'
        ));
    }

    public function verifyProcurementReceipt(Request $request, ProcurementReceipt $procurementReceipt)
    {
        $request->validate([
            'verification_status' => 'required|in:verified,rejected',
            'verification_notes'  => 'nullable|string|max:500',
        ]);

        $procurementReceipt->update([
            'verification_status' => $request->verification_status,
            'verification_notes'  => $request->verification_notes,
            'verified_by'         => Auth::id(),
            'verified_at'         => now(),
        ]);

        $statusText = $request->verification_status === 'verified' ? 'verified and approved' : 'marked as rejected';
        return back()->with('success', "Vendor Purchase Receipt for PR #{$procurementReceipt->purchaseRequest?->pr_no} has been {$statusText} by Finance.");
    }

    public function verifyDeliveryReceipt(Request $request, DeliveryReceipt $deliveryReceipt)
    {
        $deliveryReceipt->update([
            'status' => 'verified',
        ]);

        return back()->with('success', "Delivery Receipt #{$deliveryReceipt->dr_no} marked as verified.");
    }

    public function create()
    {
        $pos    = PurchaseOrder::where('status', '!=', 'cancelled')->get();
        $stores = Store::where('is_active', true)->get();
        return view('procurement.delivery-receipts.create', compact('pos', 'stores'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'purchase_order_id'         => 'required|exists:purchase_orders,id',
            'store_id'                  => 'required|exists:stores,id',
            'received_date'             => 'required|date',
            'challan_no'                => 'nullable|string|max:100',
            'vehicle_no'                => 'nullable|string|max:50',
            'notes'                     => 'nullable|string',
            'items'                     => 'required|array|min:1',
            'items.*.product_id'        => 'required|exists:products,id',
            'items.*.po_item_id'        => 'nullable|exists:purchase_order_items,id',
            'items.*.quantity_received' => 'required|numeric|min:0.001',
            'items.*.accepted_quantity' => 'required|numeric|min:0',
            'items.*.unit'              => 'required|string|max:20',
        ]);

        DB::transaction(function () use ($request) {
            $no = 'DR-' . date('Ymd') . '-' . str_pad(DeliveryReceipt::count() + 1, 4, '0', STR_PAD_LEFT);

            $dr = DeliveryReceipt::create([
                'dr_no'             => $no,
                'purchase_order_id' => $request->purchase_order_id,
                'received_by'       => Auth::id(),
                'store_id'          => $request->store_id,
                'received_date'     => $request->received_date,
                'notes'             => $request->notes,
                'challan_no'        => $request->challan_no,
                'vehicle_no'        => $request->vehicle_no,
                'status'            => 'verified',
            ]);

            foreach ($request->items as $item) {
                $dr->items()->create([
                    'product_id'        => $item['product_id'],
                    'po_item_id'        => $item['po_item_id'] ?? null,
                    'quantity_received' => $item['quantity_received'],
                    'accepted_quantity' => $item['accepted_quantity'],
                    'rejected_quantity' => $item['quantity_received'] - $item['accepted_quantity'],
                    'unit'              => $item['unit'],
                    'rejection_reason'  => $item['rejection_reason'] ?? null,
                ]);

                // Update inventory via service
                if ($item['accepted_quantity'] > 0) {
                    $this->inventoryService->stockIn(
                        $request->store_id,
                        $item['product_id'],
                        $item['accepted_quantity'],
                        $item['unit_price'] ?? 0,
                        'purchase_receipt',
                        Auth::id(),
                        'delivery_receipt',
                        $dr->id
                    );
                }
            }
        });

        return redirect()->route('delivery-receipts.index')->with('success', 'Delivery Receipt recorded and inventory updated.');
    }

    public function show(DeliveryReceipt $deliveryReceipt)
    {
        $deliveryReceipt->load(['purchaseOrder.supplier', 'store', 'receivedBy', 'items.product']);
        return view('procurement.delivery-receipts.show', compact('deliveryReceipt'));
    }
}
