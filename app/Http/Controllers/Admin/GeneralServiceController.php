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

        $fixedAssetUnits = \App\Models\FixedAssetUnit::with(['parentAsset', 'assignedEmployee'])
            ->whereNull('deleted_at')
            ->orderBy('unit_code')
            ->get();
        $employees = \App\Models\Employee::where('status', 'active')->orderBy('full_name')->get();
        $stores = \App\Models\Store::where('is_active', true)->orderBy('name')->get();
        $products = \App\Models\Product::where('is_active', true)->orderBy('name')->get(['id', 'name', 'sku', 'unit', 'category']);

        return view('general-service.maintenance.index', compact('requests', 'stats', 'staff', 'fixedAssetUnits', 'employees', 'stores', 'products'));
    }

    /**
     * Create a new maintenance & service request for Fixed Asset (Form View).
     */
    public function create()
    {
        $fixedAssetUnits = \App\Models\FixedAssetUnit::with(['parentAsset', 'assignedEmployee'])
            ->whereNull('deleted_at')
            ->orderBy('unit_code')
            ->get();

        $employees = \App\Models\Employee::where('status', 'active')->orderBy('full_name')->get();
        $stores = \App\Models\Store::where('is_active', true)->orderBy('name')->get();
        $products = \App\Models\Product::where('is_active', true)->orderBy('name')->get(['id', 'name', 'sku', 'unit', 'category']);
        $staff = User::whereHas('roles', fn($q) => $q->whereIn('name', [
            'global_admin', 'admin', 'store_manager', 'general_service'
        ]))->get(['id', 'name']);

        return view('general-service.maintenance.create', compact('fixedAssetUnits', 'employees', 'stores', 'products', 'staff'));
    }

    /**
     * Store a new service / maintenance request from General Service.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'fixed_asset_unit_id' => 'nullable|exists:fixed_asset_units,id',
            'asset_name'          => 'required|string|max:255',
            'asset_code'          => 'nullable|string|max:100',
            'employee_id'         => 'nullable|exists:employees,id',
            'issue_type'          => 'required|in:breakdown,damage,service_due,malfunction,needs_repair,routine_service,other',
            'urgency'             => 'required|in:low,normal,urgent,critical',
            'description'         => 'required|string|max:4000',
            'admin_notes'         => 'nullable|string|max:3000',
            'assigned_to_user_id' => 'nullable|exists:users,id',
            
            // Optional direct Ask Money
            'ask_money'           => 'nullable',
            'money_amount'        => 'nullable|numeric|min:1',
            'money_description'   => 'nullable|string|max:2000',
            'money_attachment'    => 'nullable|file|mimes:jpeg,png,jpg,pdf,webp|max:10240',

            // Optional direct Ask Material
            'ask_material'        => 'nullable',
            'destination_store_id'=> 'nullable|exists:stores,id',
            'required_date'       => 'nullable|date',
            'material_notes'      => 'nullable|string|max:2000',
            'items'               => 'nullable|array',
            'items.*.product_id'  => 'nullable',
            'items.*.custom_name' => 'nullable|string|max:255',
            'items.*.quantity'    => 'nullable|numeric|min:0.01',
            'items.*.unit'        => 'nullable|string|max:50',
            'items.*.notes'       => 'nullable|string|max:500',
        ]);

        $user = \Illuminate\Support\Facades\Auth::user();

        // If fixed asset unit chosen, resolve employee and details if not provided
        $unit = null;
        if (!empty($validated['fixed_asset_unit_id'])) {
            $unit = \App\Models\FixedAssetUnit::with('parentAsset')->find($validated['fixed_asset_unit_id']);
            if ($unit) {
                if (empty($validated['asset_code'])) {
                    $validated['asset_code'] = $unit->unit_code;
                }
                if (empty($validated['employee_id']) && $unit->assigned_to_employee_id) {
                    $validated['employee_id'] = $unit->assigned_to_employee_id;
                }
                // Mark unit status as in maintenance
                $unit->update(['status' => \App\Models\FixedAssetUnit::STATUS_MAINTENANCE]);
            }
        }

        // Resolve employee ID
        $employeeId = $validated['employee_id'] ?? ($user->employee?->id);

        $maintenanceRequest = MaintenanceRequest::create([
            'employee_id'         => $employeeId,
            'fixed_asset_unit_id' => $validated['fixed_asset_unit_id'] ?? null,
            'asset_name'          => $validated['asset_name'],
            'asset_code'          => $validated['asset_code'] ?? null,
            'issue_type'          => $validated['issue_type'],
            'urgency'             => $validated['urgency'],
            'description'         => $validated['description'],
            'admin_notes'         => $validated['admin_notes'] ?? null,
            'status'              => 'in_progress',
            'reported_by_user_id' => $user->id,
            'assigned_to_user_id' => $validated['assigned_to_user_id'] ?? $user->id,
        ]);

        // Process direct Ask Money if requested
        if ($request->boolean('ask_money') && !empty($validated['money_amount'])) {
            $attachmentUrl = null;
            if ($request->hasFile('money_attachment')) {
                try {
                    $cloudinary = app(\App\Services\CloudinaryService::class);
                    $attachmentUrl = $cloudinary->upload($request->file('money_attachment'), 'expense_receipts');
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error('Maintenance Ask Money attachment upload error: ' . $e->getMessage());
                }
            }

            $requestNumber = 'REQ-' . date('Ymd') . '-' . strtoupper(\Illuminate\Support\Str::random(4));
            \App\Models\ExpenseRequest::create([
                'request_number'         => $requestNumber,
                'user_id'                => $user->id,
                'employee_id'            => $employeeId,
                'maintenance_request_id' => $maintenanceRequest->id,
                'category'               => 'Maintenance',
                'other_reason'           => 'Maintenance for ' . $maintenanceRequest->request_no . ' (' . $maintenanceRequest->asset_name . ')',
                'amount'                 => $validated['money_amount'],
                'description'            => $validated['money_description'] ?? ("Maintenance expense for " . $maintenanceRequest->asset_name),
                'attachment'             => $attachmentUrl,
                'status'                 => \App\Models\ExpenseRequest::STATUS_PENDING_HR,
            ]);
        }

        // Process direct Ask Material if requested
        if ($request->boolean('ask_material') && !empty($validated['items'])) {
            $storeId = $validated['destination_store_id'] ?? \App\Models\Store::where('is_active', true)->value('id');
            $store = \App\Models\Store::find($storeId);
            $projectId = $store?->project_id ?? (\App\Models\Project::where('status', 'active')->value('id') ?? \App\Models\Project::value('id'));

            $refNumber = 'MR-MNT-' . str_replace('MNT-', '', $maintenanceRequest->request_no) . '-' . strtoupper(\Illuminate\Support\Str::random(3));
            $materialRequest = \App\Models\MaterialRequest::create([
                'project_id'             => $projectId,
                'destination_store_id'   => $storeId,
                'maintenance_request_id' => $maintenanceRequest->id,
                'reference_number'       => $refNumber,
                'source'                 => 'Maintenance — ' . $maintenanceRequest->request_no,
                'status'                 => 'sent_to_store_manager',
                'required_date'          => $validated['required_date'] ?? now()->addDays(2),
                'notes'                  => $validated['material_notes'] ?? ("Maintenance spare parts for {$maintenanceRequest->request_no} — {$maintenanceRequest->asset_name}"),
                'created_by'             => $user->id,
            ]);

            foreach ($validated['items'] as $itemData) {
                if (empty($itemData['quantity'])) continue;
                $productId = $itemData['product_id'] ?? null;
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
                }
            }
        }

        \App\Models\ActivityLog::log(
            'created',
            "General Service created service ticket {$maintenanceRequest->request_no} for asset: {$maintenanceRequest->asset_name}",
            'Maintenance Requests',
            $maintenanceRequest
        );

        return redirect()->route('general-service.maintenance.show', $maintenanceRequest)
            ->with('success', "Service & Maintenance Request #{$maintenanceRequest->request_no} created successfully!");
    }

    /**
     * Printable Maintenance & Service Report.
     */
    public function report(MaintenanceRequest $maintenanceRequest)
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

        return view('general-service.maintenance.report', compact('maintenanceRequest'));
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
