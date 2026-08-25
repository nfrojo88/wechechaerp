<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Http\Request;

class GeneralServiceController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * General Service Maintenance Dashboard — list all requests.
     */
    public function index(Request $request)
    {
        $query = MaintenanceRequest::with([
            'employee', 
            'reportedBy', 
            'assignedTo', 
            'fixedAssetUnit.parentAsset',
            'expenseRequests',
            'materialRequests'
        ])->withTrashed(false);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('urgency')) {
            $query->where('urgency', $request->urgency);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('request_no', 'like', "%$s%")
                  ->orWhere('asset_name', 'like', "%$s%")
                  ->orWhereHas('employee', fn($q2) => $q2->where('full_name', 'like', "%$s%"));
            });
        }

        $requests = $query->latest()->paginate(20)->withQueryString();

        $stats = [
            'pending'     => MaintenanceRequest::whereIn('status', ['pending', 'sent_to_store_manager'])->count(),
            'in_progress' => MaintenanceRequest::where('status', 'in_progress')->count(),
            'resolved'    => MaintenanceRequest::where('status', 'resolved')
                ->whereDate('resolved_at', today())->count(),
            'total'       => MaintenanceRequest::count(),
        ];

        $staff = User::whereHas('roles', fn($q) => $q->whereIn('name', [
            'global_admin', 'admin', 'store_manager', 'general_service'
        ]))->get(['id', 'name']);

        return view('general-service.maintenance.index', compact('requests', 'stats', 'staff'));
    }

    /**
     * Show a single maintenance request.
     */
    public function show(MaintenanceRequest $maintenanceRequest)
    {
        $maintenanceRequest->load([
            'employee', 
            'reportedBy', 
            'assignedTo', 
            'fixedAssetUnit.parentAsset',
            'expenseRequests.user',
            'expenseRequests.paidBy',
            'expenseRequests.financeStaff',
            'materialRequests.items.product',
            'materialRequests.store',
            'materialRequests.creator',
            'materialRequests.purchaseRequests.receipt.uploadedBy',
            'materialRequests.purchaseRequests.payment'
        ]);

        $staff = User::whereHas('roles', fn($q) => $q->whereIn('name', [
            'global_admin', 'admin', 'store_manager', 'general_service'
        ]))->get(['id', 'name']);

        $stores = \App\Models\Store::where('is_active', true)->orderBy('name')->get();
        $products = \App\Models\Product::where('is_active', true)->orderBy('name')->get(['id', 'name', 'sku', 'unit', 'category']);

        return view('general-service.maintenance.show', compact('maintenanceRequest', 'staff', 'stores', 'products'));
    }

    /**
     * Update status + notes + assignment.
     */
    public function updateStatus(Request $request, MaintenanceRequest $maintenanceRequest)
    {
        $validated = $request->validate([
            'status'                => 'required|in:pending,in_progress,sent_to_store_manager,resolved,closed',
            'admin_notes'           => 'nullable|string|max:3000',
            'assigned_to_user_id'   => 'nullable|exists:users,id',
            'replacement_action'    => 'nullable|string',
            'replacement_condition' => 'nullable|in:in_maintenance,unrepairable_damage',
        ]);

        $data = [
            'status'              => $validated['status'],
            'admin_notes'         => $validated['admin_notes'] ?? $maintenanceRequest->admin_notes,
            'assigned_to_user_id' => $validated['assigned_to_user_id'] ?? $maintenanceRequest->assigned_to_user_id,
        ];

        if ($validated['status'] === 'sent_to_store_manager') {
            $data['replacement_action'] = 'sent_to_store_manager';
            $data['replacement_condition'] = $validated['replacement_condition'] ?? 'in_maintenance';
            $data['sent_to_store_manager_at'] = now();
        }

        if ($validated['status'] === 'resolved' && !$maintenanceRequest->resolved_at) {
            $data['resolved_at'] = now();
        }

        $maintenanceRequest->update($data);

        $statusLabel = str_replace('_', ' ', $validated['status']);
        if (!empty($validated['replacement_condition'])) {
            $condLabel = $validated['replacement_condition'] === 'in_maintenance' ? 'In Maintenance' : 'Complete Damage (Unrepairable)';
            $statusLabel .= " ({$condLabel})";
        }

        \App\Models\ActivityLog::log(
            'updated',
            'Maintenance request ' . $maintenanceRequest->request_no . ' status updated to ' . $statusLabel,
            'Maintenance Requests',
            $maintenanceRequest
        );

        return back()->with('success', 'Request ' . $maintenanceRequest->request_no . ' updated successfully.');
    }

    /**
     * Ask Money (Expense Request) for this specific maintenance ticket.
     */
    public function askMoney(Request $request, MaintenanceRequest $maintenanceRequest)
    {
        $validated = $request->validate([
            'amount'      => 'required|numeric|min:1',
            'description' => 'required|string|max:2000',
            'attachment'  => 'nullable|file|mimes:jpeg,png,jpg,pdf,webp|max:10240',
        ]);

        $user = auth()->user();
        $employee = $user->employee ?? null;
        $requestNumber = 'REQ-' . date('Ymd') . '-' . strtoupper(\Illuminate\Support\Str::random(4));

        $attachmentUrl = null;
        if ($request->hasFile('attachment')) {
            try {
                $cloudinary = app(\App\Services\CloudinaryService::class);
                $attachmentUrl = $cloudinary->upload($request->file('attachment'), 'expense_receipts');
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Maintenance Ask Money attachment upload error: ' . $e->getMessage());
            }
        }

        $expense = \App\Models\ExpenseRequest::create([
            'request_number'         => $requestNumber,
            'user_id'                => $user->id,
            'employee_id'            => $employee ? $employee->id : null,
            'maintenance_request_id' => $maintenanceRequest->id,
            'category'               => 'Maintenance',
            'other_reason'           => 'Maintenance for ' . $maintenanceRequest->request_no . ' (' . $maintenanceRequest->asset_name . ')',
            'amount'                 => $validated['amount'],
            'description'            => $validated['description'],
            'attachment'             => $attachmentUrl,
            'status'                 => \App\Models\ExpenseRequest::STATUS_PENDING_HR,
        ]);

        \App\Models\ActivityLog::log(
            'created',
            "General Service submitted Expense Request #{$expense->request_number} for ETB " . number_format($expense->amount, 2) . " linked to Maintenance {$maintenanceRequest->request_no}",
            'Maintenance Requests',
            $maintenanceRequest
        );

        return back()->with('success', "Expense Request #{$expense->request_number} for ETB " . number_format($expense->amount, 2) . " submitted and linked to {$maintenanceRequest->request_no}!");
    }

    /**
     * Ask Material / Purchase for this specific maintenance ticket — sent to Store Manager.
     */
    public function askMaterial(Request $request, MaintenanceRequest $maintenanceRequest)
    {
        if (\Illuminate\Support\Facades\Schema::hasTable('material_requests') && !\Illuminate\Support\Facades\Schema::hasColumn('material_requests', 'maintenance_request_id')) {
            \Illuminate\Support\Facades\Schema::table('material_requests', function ($table) {
                $table->unsignedBigInteger('maintenance_request_id')->nullable()->index();
            });
        }

        $validated = $request->validate([
            'destination_store_id' => 'nullable|exists:stores,id',
            'required_date'        => 'required|date',
            'notes'                => 'nullable|string|max:3000',
            'items'                => 'required|array|min:1',
            'items.*.product_id'   => 'nullable',
            'items.*.custom_name'  => 'nullable|string|max:255',
            'items.*.quantity'     => 'required|numeric|min:0.01',
            'items.*.unit'         => 'nullable|string|max:50',
            'items.*.notes'        => 'nullable|string|max:500',
        ]);

        // Resolve store
        $storeId = $validated['destination_store_id'] ?? null;
        $store = null;
        if ($storeId) {
            $store = \App\Models\Store::find($storeId);
        } else {
            $store = \App\Models\Store::where('is_active', true)->first();
            $storeId = $store?->id;
        }

        if (!$storeId) {
            return back()->withErrors(['destination_store_id' => 'Please create or configure at least one active store.']);
        }

        // Resolve project
        $projectId = $store?->project_id;
        if (!$projectId) {
            $project = \App\Models\Project::whereIn('status', ['active', 'planning', 'in_progress'])->first() ?? \App\Models\Project::first();
            $projectId = $project?->id;
        }

        // Generate unique reference number
        $refNumber = 'MR-MNT-' . str_replace('MNT-', '', $maintenanceRequest->request_no) . '-' . strtoupper(\Illuminate\Support\Str::random(3));
        while (\App\Models\MaterialRequest::where('reference_number', $refNumber)->exists()) {
            $refNumber = 'MR-MNT-' . str_replace('MNT-', '', $maintenanceRequest->request_no) . '-' . strtoupper(\Illuminate\Support\Str::random(4));
        }

        $materialRequest = \App\Models\MaterialRequest::create([
            'project_id'             => $projectId,
            'destination_store_id'   => $storeId,
            'maintenance_request_id' => $maintenanceRequest->id,
            'reference_number'       => $refNumber,
            'source'                 => 'Maintenance — ' . $maintenanceRequest->request_no,
            'status'                 => 'sent_to_store_manager',
            'required_date'          => $validated['required_date'],
            'notes'                  => $validated['notes'] ?? ("Maintenance for {$maintenanceRequest->request_no} — {$maintenanceRequest->asset_name}"),
            'created_by'             => auth()->id(),
        ]);

        // Add requested items
        $itemCount = 0;
        foreach ($validated['items'] as $itemData) {
            $productId = $itemData['product_id'] ?? null;

            // If a custom material name is provided and no existing product chosen
            if ((!$productId || $productId === 'custom') && !empty($itemData['custom_name'])) {
                $product = \App\Models\Product::firstOrCreate(
                    ['name' => trim($itemData['custom_name'])],
                    [
                        'sku'      => 'MAT-' . strtoupper(\Illuminate\Support\Str::random(6)),
                        'unit'     => $itemData['unit'] ?? 'pcs',
                        'category' => 'Maintenance / Spare Parts',
                        'is_active'=> true,
                    ]
                );
                $productId = $product->id;
            }

            if ($productId && $productId !== 'custom') {
                $materialRequest->items()->create([
                    'product_id'         => $productId,
                    'quantity_requested' => $itemData['quantity'],
                    'notes'              => trim(($itemData['unit'] ? ('[' . $itemData['unit'] . '] ') : '') . ($itemData['notes'] ?? '')),
                ]);
                $itemCount++;
            }
        }

        \App\Models\ActivityLog::log(
            'created',
            "General Service submitted Material Request #{$materialRequest->reference_number} ({$itemCount} item(s)) linked to Maintenance {$maintenanceRequest->request_no} and routed to Store Manager for procurement/issuance",
            'Maintenance Requests',
            $maintenanceRequest
        );

        return back()->with('success', "Material Request #{$materialRequest->reference_number} with {$itemCount} item(s) created and sent to Store Manager for procurement & stock fulfillment!");
    }
}
