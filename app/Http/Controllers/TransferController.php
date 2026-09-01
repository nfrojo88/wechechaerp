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

class TransferController extends Controller
{
    public function index()
    {
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
            'items.*.unit'        => 'required|string|max:20',
        ]);

        DB::transaction(function () use ($request) {
            $no = 'TR-' . date('Ymd') . '-' . str_pad(Transfer::count() + 1, 4, '0', STR_PAD_LEFT);

            $transfer = Transfer::create([
                'transfer_no'  => $no,
                'from_store_id'=> $request->from_store_id,
                'to_store_id'  => $request->to_store_id,
                'requested_by' => Auth::id(),
                'required_date'=> $request->required_date,
                'reason'       => $request->reason,
                'status'       => 'draft',
            ]);

            foreach ($request->items as $item) {
                $transfer->items()->create([
                    'product_id'          => $item['product_id'],
                    'requested_quantity'  => $item['quantity'],
                    'unit'                => $item['unit'],
                ]);
            }
        });

        return redirect()->route('transfers.index')->with('success', 'Transfer request created.');
    }

    public function show(Transfer $transfer)
    {
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

        return view('transfers.show', compact('transfer', 'drivers'));
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

                $smsService = app(\App\Services\SmsEthiopiaService::class);
                $smsService->sendMessage($driver->phone, $smsMessage);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Transfer Driver SMS error: ' . $e->getMessage());
            }
        }

        return back()->with('success', 'Transfer dispatched and SMS notification sent to driver successfully!');
    }

    public function complete(Transfer $transfer)
    {
        $transfer->update([
            'status'      => 'completed',
            'received_by' => Auth::id(),
            'received_at' => now(),
        ]);
        return back()->with('success', 'Transfer completed.');
    }

    public function reject(Request $request, Transfer $transfer)
    {
        $request->validate(['rejection_reason' => 'required|string']);
        $transfer->update([
            'status'           => 'rejected',
            'rejection_reason' => $request->rejection_reason,
        ]);
        return back()->with('success', 'Transfer rejected.');
    }
}
