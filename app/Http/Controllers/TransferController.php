<?php

namespace App\Http\Controllers;

use App\Models\Transfer;
use App\Models\TransferItem;
use App\Models\Store;
use App\Models\Product;
use App\Models\Inventory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TransferController extends Controller
{
    public function index()
    {
        // Auto-check and adjust any active transfers that have items not yet adjusted
        try {
            $unadjusted = Transfer::whereNotIn('status', ['rejected', 'cancelled'])
                ->whereNotNull('from_store_id')
                ->whereNotNull('to_store_id')
                ->whereColumn('from_store_id', '!=', 'to_store_id')
                ->whereHas('items')
                ->where(function ($q) {
                    $q->whereDoesntHave('inventoryMovements', fn($m) => $m->where('type', 'transfer_out'))
                      ->orWhereDoesntHave('inventoryMovements', fn($m) => $m->where('type', 'transfer_in'));
                })
                ->limit(50)
                ->get();

            foreach ($unadjusted as $tr) {
                \App\Http\Controllers\StoreManagerController::syncTransferInventory($tr);
            }
        } catch (\Throwable $e) {}

        $transfers = Transfer::with(['fromStore', 'toStore', 'requestedBy'])
            ->latest()->paginate(20);
        return view('transfers.index', compact('transfers'));
    }

    public function create()
    {
        $user = Auth::user();
        $rawUserRoles = $user ? $user->roles->pluck('name')->map(fn($r) => strtolower(str_replace([' ', '-'], '_', trim($r))))->toArray() : [];
        if (in_array('store_keeper', $rawUserRoles) && !in_array('store_manager', $rawUserRoles) && !in_array('admin', $rawUserRoles) && !in_array('global_admin', $rawUserRoles)) {
            return redirect()->route('transfers.index')
                ->with('error', 'Store Keepers cannot create inter-store transfers. Transfers must be initiated by Store Managers or Coordinators. Store Keepers handle Outgoing Dispatch and Incoming Receiving.');
        }

        $stores   = Store::where('is_active', true)->get();
        $products = Product::where('is_active', true)->orderBy('name')->get();
        return view('transfers.create', compact('stores', 'products'));
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $rawUserRoles = $user ? $user->roles->pluck('name')->map(fn($r) => strtolower(str_replace([' ', '-'], '_', trim($r))))->toArray() : [];
        if (in_array('store_keeper', $rawUserRoles) && !in_array('store_manager', $rawUserRoles) && !in_array('admin', $rawUserRoles) && !in_array('global_admin', $rawUserRoles)) {
            return redirect()->route('transfers.index')
                ->with('error', 'Store Keepers cannot create inter-store transfers. Transfers must be initiated by Store Managers or Coordinators. Store Keepers handle Outgoing Dispatch and Incoming Receiving.');
        }

        $request->validate([
            'from_store_id'       => 'required|exists:stores,id',
            'to_store_id'         => 'required|exists:stores,id|different:from_store_id',
            'required_date'       => 'nullable|date',
            'reason'              => 'nullable|string',
            'items'               => 'required|array|min:1',
            'items.*.product_id'  => 'required|exists:products,id',
            'items.*.quantity'    => 'required|numeric|min:0.001',
            'items.*.unit'        => 'nullable|string|max:20',
        ]);

        try {
            DB::transaction(function () use ($request) {
                $no = Transfer::generateUniqueNo();

                $transfer = Transfer::create([
                    'transfer_no'  => $no,
                    'from_store_id'=> $request->from_store_id,
                    'to_store_id'  => $request->to_store_id,
                    'requested_by' => Auth::id(),
                    'required_date'=> $request->required_date,
                    'reason'       => $request->reason,
                    'status'       => 'draft',
                ]);

                \App\Models\TransferItem::$skipInventorySync = true;
                try {
                    foreach ($request->items as $item) {
                        $transfer->items()->create([
                            'product_id'          => $item['product_id'],
                            'requested_quantity'  => $item['quantity'],
                            'unit'                => $item['unit'] ?? 'pcs',
                        ]);
                    }
                } finally {
                    \App\Models\TransferItem::$skipInventorySync = false;
                }

                // Immediately deduct from origin store and add to destination store inventory
                \App\Http\Controllers\StoreManagerController::syncTransferInventory($transfer);
            });
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['items' => $e->getMessage()])->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('Transfer create failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return back()->withInput()->withErrors(['items' => 'Failed to create transfer: ' . $e->getMessage()])->with('error', 'Failed to create transfer: ' . $e->getMessage());
        }

        return redirect()->route('transfers.index')->with('success', 'Transfer request created. Materials deducted from origin store and added to destination store inventory.');
    }

    public function show(Transfer $transfer)
    {
        // Auto-check and adjust inventory for this Transfer TR immediately on view
        if (!in_array($transfer->status, ['rejected', 'cancelled'])) {
            try {
                \App\Http\Controllers\StoreManagerController::syncTransferInventory($transfer);
            } catch (\Throwable $e) {}
        }

        $transfer->load(['fromStore', 'toStore', 'requestedBy', 'approvedBy', 'driver', 'items.product']);
        
        // Fetch drivers where department string or role_title contains driver
        $drivers = \App\Models\Employee::where('status', 'active')
            ->where(function($q) {
                $q->where('department', 'like', '%driver%')
                  ->orWhere('role_title', 'like', '%driver%');
            })->orderBy('full_name')->get();

        if ($drivers->isEmpty()) {
            $drivers = \App\Models\Employee::where('status', 'active')->orderBy('full_name')->get();
        }

        $products = Product::where('is_active', true)->orderBy('name')->get();

        return view('transfers.show', compact('transfer', 'drivers', 'products'));
    }

    public function approve(Request $request, Transfer $transfer)
    {
        $request->validate([
            'driver_employee_id' => 'required|exists:employees,id',
            'dispatch_notes'     => 'nullable|string',
        ]);

        $transfer->update([
            'status'             => 'approved',
            'approved_by'        => Auth::id(),
            'approved_at'        => now(),
            'driver_employee_id' => $request->driver_employee_id,
            'dispatch_notes'     => $request->dispatch_notes,
        ]);

        return back()->with('success', 'Transfer approved and driver assigned successfully.');
    }

    public function sendToDriver(Request $request, Transfer $transfer)
    {
        $user = Auth::user();
        $isAdmin = $user && $user->hasAnyRole(['admin', 'global_admin', 'store_manager', 'general_service', 'coordinator']);
        $assignedStore = $user?->store ?? \App\Models\Store::where('manager_id', $user?->id)->first();
        $userStoreId = $assignedStore?->id ?? $user?->store_id;

        if (!$isAdmin && (!$userStoreId || (int)$transfer->from_store_id !== (int)$userStoreId)) {
            abort(403, 'Strict Rule Violation: Only the origin storekeeper can dispatch this transfer.');
        }

        $transfer->update([
            'status'        => 'dispatched',
            'dispatched_at' => now(),
        ]);

        // Send SMS to Driver
        $driver = $transfer->driver;
        if ($driver && $driver->phone) {
            try {
                $fromStoreName = $transfer->fromStore->name ?? 'Main Store';
                $toStoreName   = $transfer->toStore->name ?? 'Destination Store';
                $timeSent      = now()->format('d M Y, h:i A');

                $materialsList = $transfer->items->map(function($item) {
                    return ($item->product->name ?? 'Item') . ' (' . number_format($item->requested_quantity, 2) . ' ' . $item->unit . ')';
                })->implode(', ');

                $smsMessage = "ConstructPro Dispatch: Transfer #{$transfer->transfer_no} has been assigned to you.\nFrom: {$fromStoreName}\nTo: {$toStoreName}\nTime: {$timeSent}\nItems: {$materialsList}";

                if (config('procurement_handoffs.enabled', env('PROCUREMENT_SMS_ENABLED', false))) {
                    $smsService = app(\App\Services\SmsEthiopiaService::class);
                    $smsService->sendMessage($driver->phone, $smsMessage);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Transfer Driver SMS error: ' . $e->getMessage());
            }
        }

        // Instant SMS Handoff: Logistics / Origin Store -> Driver + Destination Store Keeper
        try {
            app(\App\Services\ProcurementHandoffNotificationService::class)->triggerHandoff(
                'logistics_dispatched_driver',
                $transfer,
                [
                    'sender_user'          => Auth::user(),
                    'sender_role'          => 'general_service',
                    'target_roles'         => ['driver', 'store_keeper'],
                    'assigned_employee_id' => $transfer->driver_id,
                    'store_id'             => $transfer->to_store_id,
                    'project_id'           => $transfer->project_id,
                ]
            );
        } catch (\Throwable $e) {}

        // Deduct from origin store
        \App\Http\Controllers\StoreManagerController::syncTransferInventory($transfer);

        return back()->with('success', 'Transfer dispatched and materials deducted from origin store successfully!');
    }

    public function complete(Transfer $transfer)
    {
        $user = Auth::user();
        $isAdmin = $user && $user->hasAnyRole(['admin', 'global_admin', 'store_manager', 'general_service', 'coordinator']);
        $assignedStore = $user?->store ?? \App\Models\Store::where('manager_id', $user?->id)->first();
        $userStoreId = $assignedStore?->id ?? $user?->store_id;

        if (!$isAdmin && (!$userStoreId || (int)$transfer->to_store_id !== (int)$userStoreId)) {
            abort(403, 'Strict Rule Violation: Only the destination storekeeper can receive and complete this transfer.');
        }

        $transfer->update([
            'status'      => 'completed',
            'received_by' => Auth::id(),
            'received_at' => now(),
        ]);

        // Reconcile: deduct from origin and add to destination store
        \App\Http\Controllers\StoreManagerController::syncTransferInventory($transfer);

        return back()->with('success', 'Transfer marked completed! Materials deducted from origin store and added to destination store.');
    }

    public function reject(Request $request, Transfer $transfer)
    {
        $request->validate(['rejection_reason' => 'required|string']);

        \App\Http\Controllers\StoreManagerController::reverseTransferInventory($transfer, $request->rejection_reason);

        $transfer->update([
            'status'           => 'rejected',
            'rejection_reason' => $request->rejection_reason,
        ]);
        return back()->with('success', 'Transfer rejected and inventory movements reversed.');
    }

    public function syncInventoryManually(Request $request, Transfer $transfer)
    {
        return app(\App\Http\Controllers\StoreManagerController::class)->syncInventoryManually($request, $transfer);
    }

    public function syncAllTransfersInventory(Request $request)
    {
        return app(\App\Http\Controllers\StoreManagerController::class)->syncAllTransfersInventory($request);
    }

    /**
     * Global Admin Only: Add forgotten material to an existing transfer
     */
    public function adminAddItem(Request $request, Transfer $transfer)
    {
        $user = Auth::user();
        $rawUserRoles = $user ? $user->roles->pluck('name')->map(fn($r) => strtolower(str_replace([' ', '-', '_'], '', trim($r))))->toArray() : [];
        $isGlobalAdmin = $user && (
            (method_exists($user, 'isGlobalAdmin') && $user->isGlobalAdmin())
            || (bool)($user->is_admin ?? false)
            || in_array('globaladmin', $rawUserRoles)
            || in_array('admin', $rawUserRoles)
            || in_array('superadmin', $rawUserRoles)
            || $user->hasAnyRole(['admin', 'global_admin', 'super_admin', 'Admin', 'Global Admin', 'Super Admin'])
        );

        if (!$isGlobalAdmin) {
            abort(403, 'Unauthorized: Only Global Admin is authorized to add forgotten materials to an existing transfer.');
        }

        $request->validate([
            'product_id'         => 'required|exists:products,id',
            'quantity'           => 'required|numeric|min:0.0001',
            'unit'               => 'nullable|string|max:50',
            'admin_note'         => 'nullable|string|max:500',
            'fulfillment_mode'   => 'nullable|in:auto,custom',
            'requested_quantity' => 'nullable|numeric|min:0',
            'sent_quantity'      => 'nullable|numeric|min:0',
            'received_quantity'  => 'nullable|numeric|min:0',
            'merge_if_exists'    => 'nullable',
        ]);

        $product = Product::findOrFail($request->product_id);
        $qty = (float) $request->quantity;
        $unit = trim((string)$request->unit);
        if ($unit === '') {
            $unit = $product->unit ?: 'pcs';
        }

        $mode = $request->input('fulfillment_mode', 'auto');
        if ($mode === 'custom') {
            $requestedQty = (float) ($request->filled('requested_quantity') ? $request->requested_quantity : $qty);
            $sentQty      = (float) ($request->filled('sent_quantity') ? $request->sent_quantity : 0);
            $receivedQty  = (float) ($request->filled('received_quantity') ? $request->received_quantity : 0);
            $approvedQty  = max($requestedQty, $sentQty);
        } else {
            // Smart auto-alignment based on transfer status
            $status = strtolower($transfer->status ?? 'draft');
            switch ($status) {
                case 'completed':
                    $requestedQty = $qty;
                    $approvedQty  = $qty;
                    $sentQty      = $qty;
                    $receivedQty  = $qty;
                    break;
                case 'in_transit':
                case 'dispatched':
                    $requestedQty = $qty;
                    $approvedQty  = $qty;
                    $sentQty      = $qty;
                    $receivedQty  = 0;
                    break;
                case 'approved':
                    $requestedQty = $qty;
                    $approvedQty  = $qty;
                    $sentQty      = 0;
                    $receivedQty  = 0;
                    break;
                case 'draft':
                case 'pending_approval':
                default:
                    $requestedQty = $qty;
                    $approvedQty  = 0;
                    $sentQty      = 0;
                    $receivedQty  = 0;
                    break;
            }
        }

        DB::transaction(function () use ($transfer, $product, $requestedQty, $approvedQty, $sentQty, $receivedQty, $unit, $request, $user) {
            $mergeIfExists = $request->boolean('merge_if_exists', true);
            $existingItem = null;
            if ($mergeIfExists) {
                $existingItem = $transfer->items()->where('product_id', $product->id)->first();
            }

            if ($existingItem) {
                $existingItem->update([
                    'requested_quantity' => (float)$existingItem->requested_quantity + $requestedQty,
                    'approved_quantity'  => (float)$existingItem->approved_quantity + $approvedQty,
                    'sent_quantity'      => (float)$existingItem->sent_quantity + $sentQty,
                    'received_quantity'  => (float)$existingItem->received_quantity + $receivedQty,
                ]);
            } else {
                $transfer->items()->create([
                    'product_id'         => $product->id,
                    'requested_quantity' => $requestedQty,
                    'approved_quantity'  => $approvedQty,
                    'sent_quantity'      => $sentQty,
                    'received_quantity'  => $receivedQty,
                    'unit'               => $unit,
                ]);
            }

            // Sync transfer inventory immediately
            \App\Http\Controllers\StoreManagerController::syncTransferInventory($transfer);

            // Audit Log
            try {
                $noteTxt = $request->filled('admin_note') ? " Note: " . $request->admin_note : "";
                \App\Models\ActivityLog::log(
                    'transfer_item_added_admin',
                    "Global Admin {$user->name} added forgotten material \"{$product->name}\" ({$requestedQty} {$unit}) to Transfer #{$transfer->transfer_no} [Status: {$transfer->status}].{$noteTxt}",
                    'Material Transfers',
                    $transfer
                );
            } catch (\Throwable $e) {}
        });

        return back()->with('success', "Forgotten material \"{$product->name}\" ({$qty} {$unit}) successfully added to Transfer #{$transfer->transfer_no} by Global Admin, and store inventory synchronized.");
    }

    /**
     * Global Admin Only: Delete forgotten/mistaken material from an existing transfer
     */
    public function adminDeleteItem(Request $request, Transfer $transfer, TransferItem $item)
    {
        $user = Auth::user();
        $rawUserRoles = $user ? $user->roles->pluck('name')->map(fn($r) => strtolower(str_replace([' ', '-', '_'], '', trim($r))))->toArray() : [];
        $isGlobalAdmin = $user && (
            (method_exists($user, 'isGlobalAdmin') && $user->isGlobalAdmin())
            || (bool)($user->is_admin ?? false)
            || in_array('globaladmin', $rawUserRoles)
            || in_array('admin', $rawUserRoles)
            || in_array('superadmin', $rawUserRoles)
            || $user->hasAnyRole(['admin', 'global_admin', 'super_admin', 'Admin', 'Global Admin', 'Super Admin'])
        );

        if (!$isGlobalAdmin) {
            abort(403, 'Unauthorized: Only Global Admin is authorized to remove materials from an existing transfer.');
        }

        if ($item->transfer_id != $transfer->id) {
            return back()->with('error', 'Item does not belong to this transfer.');
        }

        $productName = $item->product->name ?? 'Material Item';
        $itemQty = (float)($item->received_quantity ?: ($item->sent_quantity ?: $item->requested_quantity));
        $itemUnit = $item->unit;

        DB::transaction(function () use ($transfer, $item, $productName, $itemQty, $itemUnit, $user) {
            // Reverse inventory movements specifically related to this item/product if any
            try {
                $inventoryService = app(\App\Services\InventoryService::class);
                
                // If origin store had transfer_out for this transfer & product, return it
                $outQty = (float) \App\Models\InventoryMovement::where('reference_type', Transfer::class)
                    ->where('reference_id', $transfer->id)
                    ->where('type', 'transfer_out')
                    ->whereHas('inventory', fn($q) => $q->withoutGlobalScopes()->where('store_id', $transfer->from_store_id)->where('product_id', $item->product_id))
                    ->sum('quantity');

                $retQty = (float) \App\Models\InventoryMovement::where('reference_type', Transfer::class)
                    ->where('reference_id', $transfer->id)
                    ->where('type', 'transfer_return')
                    ->whereHas('inventory', fn($q) => $q->withoutGlobalScopes()->where('store_id', $transfer->from_store_id)->where('product_id', $item->product_id))
                    ->sum('quantity');

                $netOut = abs($outQty) - abs($retQty);
                if ($netOut > 0.0001) {
                    $inventoryService->applyMovement(
                        storeId: $transfer->from_store_id,
                        productId: $item->product_id,
                        quantityDelta: +$netOut,
                        type: 'transfer_return',
                        referenceType: Transfer::class,
                        referenceId: $transfer->id,
                        remarks: "Transfer #{$transfer->transfer_no} item \"{$productName}\" removed by Global Admin — stock returned to origin store",
                        performedBy: $user->id ?? 1
                    );
                }

                // If destination store had transfer_in for this transfer & product, reverse it
                $inQty = (float) \App\Models\InventoryMovement::where('reference_type', Transfer::class)
                    ->where('reference_id', $transfer->id)
                    ->where('type', 'transfer_in')
                    ->whereHas('inventory', fn($q) => $q->withoutGlobalScopes()->where('store_id', $transfer->to_store_id)->where('product_id', $item->product_id))
                    ->sum('quantity');

                $revQty = (float) \App\Models\InventoryMovement::where('reference_type', Transfer::class)
                    ->where('reference_id', $transfer->id)
                    ->where('type', 'transfer_reversal')
                    ->whereHas('inventory', fn($q) => $q->withoutGlobalScopes()->where('store_id', $transfer->to_store_id)->where('product_id', $item->product_id))
                    ->sum('quantity');

                $netIn = abs($inQty) - abs($revQty);
                if ($netIn > 0.0001) {
                    $inventoryService->applyMovement(
                        storeId: $transfer->to_store_id,
                        productId: $item->product_id,
                        quantityDelta: -$netIn,
                        type: 'transfer_reversal',
                        referenceType: Transfer::class,
                        referenceId: $transfer->id,
                        remarks: "Transfer #{$transfer->transfer_no} item \"{$productName}\" removed by Global Admin — stock reversed from destination store",
                        performedBy: $user->id ?? 1
                    );
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Error reversing single transfer item inventory: ' . $e->getMessage());
            }

            // Delete item
            $item->delete();

            // Re-sync overall transfer inventory
            \App\Http\Controllers\StoreManagerController::syncTransferInventory($transfer);

            // Audit Log
            try {
                \App\Models\ActivityLog::log(
                    'transfer_item_removed_admin',
                    "Global Admin {$user->name} removed material \"{$productName}\" ({$itemQty} {$itemUnit}) from Transfer #{$transfer->transfer_no} [Status: {$transfer->status}]. Store inventory movements reversed.",
                    'Material Transfers',
                    $transfer
                );
            } catch (\Throwable $e) {}
        });

        return back()->with('success', "Material \"{$productName}\" removed from Transfer #{$transfer->transfer_no} by Global Admin and store inventory reversed.");
    }
}
