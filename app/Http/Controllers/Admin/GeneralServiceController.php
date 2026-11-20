<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\ExpenseRequest;
use App\Models\FixedAssetUnit;
use App\Models\MaintenanceRequest;
use App\Models\MaterialRequest;
use App\Models\NotificationLog;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\CloudinaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class GeneralServiceController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Defensive schema verification for maintenance workflow columns.
     */
    protected function ensureWorkflowColumns(): void
    {
        MaintenanceRequest::ensureSchema();
    }

    /**
     * Get eligible Petty Cash owners / custodians.
     */
    protected function getPettyCashOwners()
    {
        $pettyCashOwners = User::whereHas('assignedPettyCashAccounts')
            ->orWhereHas('assignedAccounts')
            ->orWhereHas('roles', fn($q) => $q->whereIn('name', ['admin', 'global_admin', 'store_keeper', 'store_manager', 'general_service', 'finance_officer', 'accountant']))
            ->get(['id', 'name', 'email']);

        if ($pettyCashOwners->isEmpty()) {
            $pettyCashOwners = User::orderBy('name')->get(['id', 'name', 'email']);
        }

        return $pettyCashOwners;
    }

    /**
     * General Service Maintenance Dashboard — list all requests.
     */
    public function index(Request $request)
    {
        $this->ensureWorkflowColumns();

        $query = MaintenanceRequest::with([
            'employee', 
            'reportedBy.employee',
            'reportedBy.roles',
            'assignedTo.employee',
            'assignedTo.roles',
            'pettyCashOwner',
            'fixedAssetUnit.parentAsset.store',
            'expenseRequests',
            'materialRequests'
        ])->withTrashed(false);

        if ($request->filled('gs_status')) {
            $query->where('gs_status', $request->gs_status);
        }
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
                  ->orWhere('asset_code', 'like', "%$s%")
                  ->orWhereHas('employee', fn($q2) => $q2->where('full_name', 'like', "%$s%"));
            });
        }

        $requests = $query->latest()->paginate(20)->withQueryString();

        $stats = [
            'pending'             => MaintenanceRequest::where('status', 'pending')->count(),
            'pending_gs'          => MaintenanceRequest::where(function($q) {
                                        $q->where('gs_status', 'pending_gs')->orWhereNull('gs_status');
                                     })->whereNotIn('status', ['resolved', 'closed', 'rejected'])->count(),
            'gs_verified'         => MaintenanceRequest::where('gs_status', 'gs_verified')->count(),
            'submitted_to_gm'     => MaintenanceRequest::where('gs_status', 'submitted_to_gm')->count(),
            'gm_on_hold'          => MaintenanceRequest::whereIn('gs_status', ['gm_on_hold', 'gm_on_hold_final'])->count(),
            'gm_approved_initial' => MaintenanceRequest::where('gs_status', 'gm_approved_initial')->count(),
            'pending_gm_final'    => MaintenanceRequest::where('gs_status', 'pending_gm_final')->count(),
            'in_progress'         => MaintenanceRequest::where('status', 'in_progress')->count(),
            'approved'            => MaintenanceRequest::where('gs_status', 'approved')->count(),
            'resolved'            => MaintenanceRequest::where('status', 'resolved')->count(),
            'total'               => MaintenanceRequest::count(),
        ];

        $staff = User::whereHas('roles', fn($q) => $q->whereIn('name', [
            'global_admin', 'admin', 'store_manager', 'general_service'
        ]))->get(['id', 'name']);

        $pettyCashOwners = $this->getPettyCashOwners();

        $fixedAssetUnits = FixedAssetUnit::with(['parentAsset', 'assignedEmployee'])
            ->whereNull('deleted_at')
            ->orderBy('unit_code')
            ->get();
        $employees = Employee::where('status', 'active')->orderBy('full_name')->get();
        $stores = Store::where('is_active', true)->orderBy('name')->get();
        $products = Product::where('is_active', true)->orderBy('name')->get(['id', 'name', 'sku', 'unit', 'category']);

        return view('general-service.maintenance.index', compact('requests', 'stats', 'staff', 'pettyCashOwners', 'fixedAssetUnits', 'employees', 'stores', 'products'));
    }

    /**
     * Create a new maintenance & service request for Fixed Asset (Form View).
     */
    public function create()
    {
        $fixedAssetUnits = FixedAssetUnit::with(['parentAsset', 'assignedEmployee'])
            ->whereNull('deleted_at')
            ->orderBy('unit_code')
            ->get();

        $employees = Employee::where('status', 'active')->orderBy('full_name')->get();
        $stores = Store::where('is_active', true)->orderBy('name')->get();
        $staff = User::whereHas('roles', fn($q) => $q->whereIn('name', [
            'global_admin', 'admin', 'store_manager', 'general_service'
        ]))->get(['id', 'name']);
        $products = Product::where('is_active', true)->orderBy('name')->get(['id', 'name', 'sku', 'unit', 'category']);

        return view('general-service.maintenance.create', compact('fixedAssetUnits', 'employees', 'stores', 'staff', 'products'));
    }

    /**
     * Stage 1: Store a newly created maintenance ticket.
     * System sets FixedAssetUnit status to in_maintenance, status = pending, gs_status = pending_gs.
     * NO materials, NO money at Stage 1/Stage 2.
     */
    public function store(Request $request)
    {
        $this->ensureWorkflowColumns();

        $validated = $request->validate([
            'fixed_asset_unit_id' => 'nullable|exists:fixed_asset_units,id',
            'asset_name'          => 'required|string|max:255',
            'asset_code'          => 'nullable|string|max:100',
            'employee_id'         => 'nullable|exists:employees,id',
            'issue_type'          => 'required|string|max:100',
            'urgency'             => 'required|in:low,normal,urgent,critical',
            'description'         => 'required|string|max:4000',
            'odometer'            => 'nullable|string|max:100',
            'admin_notes'         => 'nullable|string|max:3000',
            'assigned_to_user_id' => 'nullable|exists:users,id',
        ]);

        $user = Auth::user();

        // Normalize issue_type for strict MySQL ENUM compatibility
        $issueType = $validated['issue_type'] ?? 'needs_repair';
        if ($issueType === 'routine_service') {
            $issueType = 'service_due';
        }
        $validEnums = ['breakdown', 'damage', 'service_due', 'malfunction', 'needs_repair', 'other'];
        if (!in_array($issueType, $validEnums)) {
            $issueType = 'other';
        }

        // If fixed asset unit chosen, record previous status and update unit to in_maintenance
        $prevAssetStatus = null;
        if (!empty($validated['fixed_asset_unit_id'])) {
            $unit = FixedAssetUnit::with('parentAsset')->find($validated['fixed_asset_unit_id']);
            if ($unit) {
                $prevAssetStatus = $unit->status;
                if (empty($validated['asset_code'])) {
                    $validated['asset_code'] = $unit->unit_code;
                }
                if (empty($validated['employee_id']) && $unit->assigned_to_employee_id) {
                    $validated['employee_id'] = $unit->assigned_to_employee_id;
                }
                // Mark unit status as in_maintenance
                $unit->update(['status' => 'in_maintenance']);
            }
        }

        // Resolve employee ID
        $employeeId = $validated['employee_id'] ?? ($user->employee?->id);
        if (!$employeeId) {
            $employeeId = Employee::where('status', 'active')->value('id') ?? Employee::value('id');
        }

        // Resolve assigned_to_user_id
        $assignedToUserId = $validated['assigned_to_user_id'] ?? null;
        if (!$assignedToUserId) {
            $gsUser = User::whereHas('roles', fn($q) => $q->where('name', 'general_service'))->first();
            $assignedToUserId = $gsUser?->id ?? $user->id;
        }

        $maintenanceRequest = MaintenanceRequest::create([
            'employee_id'           => $employeeId,
            'fixed_asset_unit_id'   => $validated['fixed_asset_unit_id'] ?? null,
            'asset_name'            => $validated['asset_name'],
            'asset_code'            => $validated['asset_code'] ?? null,
            'issue_type'            => $issueType,
            'urgency'               => $validated['urgency'],
            'description'           => $validated['description'],
            'odometer'              => $validated['odometer'] ?? null,
            'admin_notes'           => $validated['admin_notes'] ?? null,
            'previous_asset_status' => $prevAssetStatus,
            'status'                => 'pending',
            'gs_status'             => 'pending_gs',
            'reported_by_user_id'   => $user->id,
            'assigned_to_user_id'   => $assignedToUserId,
        ]);

        ActivityLog::log(
            'created',
            "Maintenance request #{$maintenanceRequest->request_no} created for asset {$maintenanceRequest->asset_name} (Status: Pending GS Inspection)",
            'Maintenance Requests',
            $maintenanceRequest
        );

        return redirect()->route('general-service.maintenance.show', $maintenanceRequest->id)
            ->with('success', "Maintenance Request #{$maintenanceRequest->request_no} created successfully! Please inspect the asset to complete verification.");
    }

    /**
     * Stage 2: GS Inspection and Verification — ACCEPT (Verify).
     * GS inspects asset, writes diagnosis, uploads photos, and accepts.
     * gs_status = gs_verified (or submitted_to_gm if sent to GM directly).
     * Asset appears in site Inventory under 'Under Maintenance'.
     */
    public function gsVerify(Request $request, MaintenanceRequest $maintenanceRequest)
    {
        $this->ensureWorkflowColumns();

        if ($maintenanceRequest->isLocked()) {
            abort(403, "Ticket #{$maintenanceRequest->request_no} is locked and cannot be modified.");
        }

        $validated = $request->validate([
            'diagnosis'     => 'required|string|max:4000',
            'odometer'      => 'nullable|string|max:100',
            'attachments.*' => 'nullable|file|mimes:jpeg,png,jpg,pdf,webp|max:10240',
            'send_to_gm'    => 'nullable|boolean',
            'remarks'       => 'nullable|string|max:2000',
        ]);

        $user = Auth::user();

        // Process attachments
        $attachmentUrls = is_array($maintenanceRequest->diagnosis_attachments) ? $maintenanceRequest->diagnosis_attachments : [];
        if ($request->hasFile('attachments')) {
            $cloudinary = app(CloudinaryService::class);
            foreach ($request->file('attachments') as $file) {
                try {
                    $url = $cloudinary->upload($file, 'maintenance_diagnosis');
                    if ($url) {
                        $attachmentUrls[] = $url;
                    }
                } catch (\Throwable $e) {
                    Log::error("Diagnosis upload error: " . $e->getMessage());
                }
            }
        }

        $sendToGm = $request->boolean('send_to_gm');
        $newGsStatus = $sendToGm ? 'submitted_to_gm' : 'gs_verified';

        $timestamp = now()->format('d M Y, H:i');
        $existingNotes = $maintenanceRequest->admin_notes ? ($maintenanceRequest->admin_notes . "\n") : '';
        $newNotes = $existingNotes . "[GS Verified on {$timestamp} by {$user->name}]: Inspection completed. Maintenance is confirmed as needed.";
        if (!empty($validated['remarks'])) {
            $newNotes .= " Remarks: " . $validated['remarks'];
        }

        $maintenanceRequest->update([
            'diagnosis'             => $validated['diagnosis'],
            'odometer'              => $validated['odometer'] ?? $maintenanceRequest->odometer,
            'diagnosis_attachments' => $attachmentUrls,
            'gs_verified_at'        => now(),
            'gs_verified_by'        => $user->id,
            'gs_status'             => $newGsStatus,
            'admin_notes'           => $newNotes,
        ]);

        ActivityLog::log(
            'verified',
            "General Service verified Maintenance #{$maintenanceRequest->request_no} for {$maintenanceRequest->asset_name} (Status: {$newGsStatus})",
            'Maintenance Requests',
            $maintenanceRequest
        );

        if ($sendToGm) {
            $this->notifyGm($maintenanceRequest, "Maintenance request #{$maintenanceRequest->request_no} verified and submitted for your First Review.");
            return back()->with('success', "Ticket #{$maintenanceRequest->request_no} verified and submitted to the General Manager for First Review!");
        }

        return back()->with('success', "Ticket #{$maintenanceRequest->request_no} verified! The asset is now listed under 'Under Maintenance' in the site inventory. Click 'Send to GM' when ready for executive review.");
    }

    /**
     * Stage 2: GS Inspection and Verification — REJECT.
     * Ticket is closed as rejected, asset returns to its previous status, requester is notified with reason.
     */
    public function gsReject(Request $request, MaintenanceRequest $maintenanceRequest)
    {
        $this->ensureWorkflowColumns();

        if ($maintenanceRequest->isLocked()) {
            abort(403, "Ticket #{$maintenanceRequest->request_no} is locked and cannot be modified.");
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:2000',
        ]);

        $user = Auth::user();
        $reason = trim($validated['rejection_reason']);
        $timestamp = now()->format('d M Y, H:i');

        $existingNotes = $maintenanceRequest->admin_notes ? ($maintenanceRequest->admin_notes . "\n") : '';
        $newNotes = $existingNotes . "[GS Inspection Rejection {$timestamp} by {$user->name}]: Maintenance rejected. Reason: {$reason}";

        $maintenanceRequest->update([
            'status'           => 'rejected',
            'gs_status'        => 'gs_rejected',
            'rejection_reason' => $reason,
            'gs_rejected_at'   => now(),
            'gs_rejected_by'   => $user->id,
            'admin_notes'      => $newNotes,
        ]);

        // Release asset unit back to previous status
        if ($maintenanceRequest->fixedAssetUnit) {
            $prevStatus = $maintenanceRequest->previous_asset_status ?: 'active';
            $maintenanceRequest->fixedAssetUnit->update(['status' => $prevStatus]);
        }

        // Notify requester
        $requesterUserId = $maintenanceRequest->reported_by_user_id ?? $maintenanceRequest->employee?->user_id;
        if ($requesterUserId) {
            try {
                NotificationLog::create([
                    'request_type'      => 'maintenance_request',
                    'request_id'        => $maintenanceRequest->id,
                    'action'            => 'rejected',
                    'sender_user_id'    => $user->id,
                    'recipient_user_id' => $requesterUserId,
                    'role'              => 'requester',
                    'message'           => "Your maintenance request #{$maintenanceRequest->request_no} ({$maintenanceRequest->asset_name}) was rejected by General Service. Reason: {$reason}",
                    'status'            => 'sent',
                ]);
            } catch (\Throwable $e) {}
        }

        ActivityLog::log(
            'rejected',
            "General Service rejected Maintenance #{$maintenanceRequest->request_no} ({$maintenanceRequest->asset_name}). Asset released. Reason: {$reason}",
            'Maintenance Requests',
            $maintenanceRequest
        );

        return back()->with('warning', "Maintenance Request #{$maintenanceRequest->request_no} has been rejected. The asset has been released back to its previous status and the requester notified.");
    }

    /**
     * Send verified ticket to GM for First Review.
     */
    public function submitToGm(Request $request, MaintenanceRequest $maintenanceRequest)
    {
        $this->ensureWorkflowColumns();

        if ($maintenanceRequest->isLocked()) {
            abort(403, "Ticket #{$maintenanceRequest->request_no} is locked and cannot be modified.");
        }

        $user = Auth::user();
        $timestamp = now()->format('d M Y, H:i');
        $existingNotes = $maintenanceRequest->admin_notes ? ($maintenanceRequest->admin_notes . "\n") : '';
        $newNotes = $existingNotes . "[Submitted to GM {$timestamp} by {$user->name}]: Awaiting GM Initial Review.";

        $maintenanceRequest->update([
            'gs_status'   => 'submitted_to_gm',
            'admin_notes' => $newNotes,
        ]);

        $this->notifyGm($maintenanceRequest, "Maintenance request #{$maintenanceRequest->request_no} ({$maintenanceRequest->asset_name}) submitted for GM First Review.");

        ActivityLog::log(
            'submitted',
            "General Service submitted Maintenance #{$maintenanceRequest->request_no} to GM for First Review",
            'Maintenance Requests',
            $maintenanceRequest
        );

        return back()->with('success', "Maintenance Request #{$maintenanceRequest->request_no} submitted to General Manager for First Review!");
    }

    /**
     * Stage 4 & Stage 5: GS Adds Details & Submits to GM for Final Approval.
     * Only available after GM initial approval (gs_status === 'gm_approved_initial').
     * Fills materials, labor cost, technician/labor bank account details, condition rating, proformas.
     * Validates all required fields, sets gs_status = pending_gm_final, and IMMEDIATELY LOCKS the ticket!
     */
    public function submitDetails(Request $request, MaintenanceRequest $maintenanceRequest)
    {
        $this->ensureWorkflowColumns();

        if ($maintenanceRequest->isLocked()) {
            abort(403, "Ticket #{$maintenanceRequest->request_no} is locked and awaiting GM decision. Edits are strictly forbidden.");
        }

        if (!in_array($maintenanceRequest->gs_status, ['gm_approved_initial', 'gm_returned_to_gs'])) {
            return back()->withErrors(['gs_status' => 'Details can only be filled after initial GM approval. Current status: ' . ($maintenanceRequest->gs_status_badge['label'] ?? $maintenanceRequest->gs_status)]);
        }

        $validated = $request->validate([
            'technician_name'           => 'required|string|max:255',
            'technician_phone'          => 'required|string|max:100',
            'technician_bank_name'      => 'required|string|max:255',
            'technician_account_name'   => 'required|string|max:255',
            'technician_account_number' => 'required|string|max:255',
            'labor_cost'                => 'required|numeric|min:0',
            'condition_rating'          => 'required|in:repairable,needs_replacement,scrapped',
            'estimated_time'            => 'nullable|string|max:150',
            'remarks'                   => 'nullable|string|max:2000',
            'materials'                 => 'nullable|array',
            'materials.*.name'          => 'required_with:materials|string|max:255',
            'materials.*.quantity'      => 'required_with:materials|numeric|min:0.01',
            'materials.*.unit'          => 'nullable|string|max:50',
            'materials.*.estimated_unit_cost' => 'nullable|numeric|min:0',
            'quotations.*'              => 'nullable|file|mimes:jpeg,png,jpg,pdf,webp|max:10240',
        ]);

        $user = Auth::user();

        // Process materials and calculate material_cost
        $materialsData = [];
        $materialCost = 0.0;
        if (!empty($validated['materials'])) {
            foreach ($validated['materials'] as $mat) {
                if (!empty($mat['name']) && !empty($mat['quantity'])) {
                    $qty = (float) $mat['quantity'];
                    $unitCost = isset($mat['estimated_unit_cost']) ? (float)$mat['estimated_unit_cost'] : 0.0;
                    $lineTotal = round($qty * $unitCost, 2);
                    $materialCost += $lineTotal;

                    $materialsData[] = [
                        'name'                => trim($mat['name']),
                        'quantity'            => $qty,
                        'unit'                => $mat['unit'] ?? 'pcs',
                        'estimated_unit_cost' => $unitCost,
                        'total_cost'          => $lineTotal,
                    ];
                }
            }
        }

        $laborCost = (float) $validated['labor_cost'];
        $totalCost = round($laborCost + $materialCost, 2);

        // Process uploaded quotations/proformas
        $quotationUrls = is_array($maintenanceRequest->quotation_attachments) ? $maintenanceRequest->quotation_attachments : [];
        if ($request->hasFile('quotations')) {
            $cloudinary = app(CloudinaryService::class);
            foreach ($request->file('quotations') as $file) {
                try {
                    $url = $cloudinary->upload($file, 'maintenance_quotations');
                    if ($url) {
                        $quotationUrls[] = $url;
                    }
                } catch (\Throwable $e) {
                    Log::error("Quotation upload error: " . $e->getMessage());
                }
            }
        }

        $timestamp = now()->format('d M Y, H:i');
        $existingNotes = $maintenanceRequest->admin_notes ? ($maintenanceRequest->admin_notes . "\n") : '';
        $newNotes = $existingNotes . "[Details Submitted {$timestamp} by {$user->name}]: Labor ETB " . number_format($laborCost, 2) . ", Materials ETB " . number_format($materialCost, 2) . " (Total: ETB " . number_format($totalCost, 2) . "). Tech: {$validated['technician_name']} ({$validated['technician_bank_name']} - Acc {$validated['technician_account_number']}). Locked for GM final approval.";
        if (!empty($validated['remarks'])) {
            $newNotes .= " Remarks: " . $validated['remarks'];
        }

        // Summary text of materials
        $materialDescription = collect($materialsData)->map(fn($m) => "{$m['name']} ({$m['quantity']} {$m['unit']} @ ETB {$m['estimated_unit_cost']})")->implode(', ');

        $updateData = [
            'technician_name'            => $validated['technician_name'],
            'technician_phone'           => $validated['technician_phone'],
            'technician_bank_name'       => $validated['technician_bank_name'],
            'technician_account_name'    => $validated['technician_account_name'],
            'technician_account_number'  => $validated['technician_account_number'],
            // Sync with legacy fields
            'maintenance_person_name'    => $validated['technician_name'],
            'maintenance_person_phone'   => $validated['technician_phone'],
            'maintenance_person_account' => $validated['technician_account_number'],
            'labor_cost'                 => $laborCost,
            'material_cost'              => $materialCost,
            'total_cost'                 => $totalCost,
            'money_amount'               => $laborCost,
            'materials_data'             => $materialsData,
            'material_description'       => $materialDescription,
            'condition_rating'           => $validated['condition_rating'],
            'estimated_time'             => $validated['estimated_time'] ?? $maintenanceRequest->estimated_time,
            'quotation_attachments'      => $quotationUrls,
            'remarks'                    => $validated['remarks'] ?? null,
            'admin_notes'                => $newNotes,
            // Stage 5 Lock: IMMEDIATELY LOCK TICKET
            'gs_status'                  => 'pending_gm_final',
            'gs_details_locked'          => true,
        ];

        if (Schema::hasColumn('maintenance_requests', 'replacement_condition')) {
            $updateData['replacement_condition'] = $validated['condition_rating'];
        }

        $maintenanceRequest->update($updateData);

        ActivityLog::log(
            'submitted',
            "General Service completed Stage 4 details for Maintenance #{$maintenanceRequest->request_no} (Labor: ETB " . number_format($laborCost, 2) . ", Materials: ETB " . number_format($materialCost, 2) . ", Total: ETB " . number_format($totalCost, 2) . "). Ticket LOCKED awaiting GM Final Decision.",
            'Maintenance Requests',
            $maintenanceRequest
        );

        $this->notifyGm($maintenanceRequest, "Maintenance request #{$maintenanceRequest->request_no} ({$maintenanceRequest->asset_name}) has complete labor/parts details (Total: ETB " . number_format($totalCost, 2) . ") and is awaiting your Final Decision.");

        return back()->with('success', "Ticket #{$maintenanceRequest->request_no} submitted for Final GM Approval! The ticket is now LOCKED awaiting GM decision.");
    }

    /**
     * Backward-compatible alias for Stage 4/5 submission.
     */
    public function assignPettyCash(Request $request, MaintenanceRequest $maintenanceRequest)
    {
        return $this->submitDetails($request, $maintenanceRequest);
    }

    /**
     * Printable Maintenance & Service Report Voucher.
     */
    public function report(MaintenanceRequest $maintenanceRequest)
    {
        $this->ensureWorkflowColumns();

        $maintenanceRequest->load([
            'employee',
            'reportedBy',
            'assignedTo',
            'gmApprover',
            'pettyCashOwner',
            'gsVerifiedBy',
            'fixedAssetUnit.parentAsset.store',
            'expenseRequests',
            'materialRequests.items.product',
        ]);

        return view('general-service.maintenance.report', compact('maintenanceRequest'));
    }

    /**
     * Show a single maintenance request detail page.
     */
    public function show(MaintenanceRequest $maintenanceRequest)
    {
        $this->ensureWorkflowColumns();

        $maintenanceRequest->load([
            'employee',
            'reportedBy.employee',
            'assignedTo.employee',
            'gmApprover',
            'pettyCashOwner',
            'gsVerifiedBy',
            'fixedAssetUnit.parentAsset.store',
            'expenseRequests',
            'materialRequests.items.product'
        ]);

        $staff = User::whereHas('roles', fn($q) => $q->whereIn('name', [
            'global_admin', 'admin', 'store_manager', 'general_service'
        ]))->get(['id', 'name']);

        $pettyCashOwners = $this->getPettyCashOwners();
        $stores = Store::where('is_active', true)->orderBy('name')->get();
        $products = Product::where('is_active', true)->orderBy('name')->get(['id', 'name', 'sku', 'unit', 'category']);
        $historySummary = $maintenanceRequest->history_summary;
        $maintenanceHistory = $maintenanceRequest->maintenance_history;

        return view('general-service.maintenance.show', compact(
            'maintenanceRequest',
            'staff',
            'pettyCashOwners',
            'stores',
            'products',
            'historySummary',
            'maintenanceHistory'
        ));
    }

    /**
     * Update status (e.g. mark resolved when repairs are complete).
     * Protected by Stage 5/6 lock check.
     */
    public function updateStatus(Request $request, MaintenanceRequest $maintenanceRequest)
    {
        $this->ensureWorkflowColumns();

        // 403 Forbidden if ticket is locked awaiting GM decision
        if ($maintenanceRequest->isLocked() && $maintenanceRequest->gs_status !== 'approved') {
            abort(403, "Ticket #{$maintenanceRequest->request_no} is locked awaiting GM decision and cannot be modified.");
        }

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
            if (Schema::hasColumn('maintenance_requests', 'replacement_action')) {
                $data['replacement_action'] = $validated['replacement_action'] ?? 'sent_to_store_manager';
            }
            if (Schema::hasColumn('maintenance_requests', 'replacement_condition')) {
                $data['replacement_condition'] = $validated['replacement_condition'] ?? 'in_maintenance';
            }
            if (Schema::hasColumn('maintenance_requests', 'sent_to_store_manager_at')) {
                $data['sent_to_store_manager_at'] = now();
            }
        }

        if ($validated['status'] === 'resolved' && !$maintenanceRequest->resolved_at) {
            $data['resolved_at'] = now();
            // Release asset back to active / in_use
            if ($maintenanceRequest->fixedAssetUnit) {
                $maintenanceRequest->fixedAssetUnit->update(['status' => 'active']);
            }
        }

        $maintenanceRequest->update($data);

        $statusLabel = str_replace('_', ' ', $validated['status']);
        ActivityLog::log(
            'updated',
            "Maintenance request #{$maintenanceRequest->request_no} status updated to {$statusLabel}",
            'Maintenance Requests',
            $maintenanceRequest
        );

        return back()->with('success', "Request #{$maintenanceRequest->request_no} updated successfully.");
    }

    /**
     * Helper to notify General Manager.
     */
    protected function notifyGm(MaintenanceRequest $maintenanceRequest, string $message): void
    {
        try {
            $gmUsers = User::whereHas('roles', fn($q) => $q->whereIn('name', ['gm', 'executive_gm', 'General Manager']))->get();
            if ($gmUsers->isEmpty()) {
                $gmUsers = User::whereHas('roles', fn($q) => $q->where('name', 'admin'))->get();
            }

            foreach ($gmUsers as $gmUser) {
                NotificationLog::create([
                    'request_type'      => 'maintenance_request',
                    'request_id'        => $maintenanceRequest->id,
                    'action'            => 'gm_review',
                    'sender_user_id'    => Auth::id(),
                    'recipient_user_id' => $gmUser->id,
                    'role'              => 'gm',
                    'message'           => $message,
                    'status'            => 'sent',
                ]);
            }
        } catch (\Throwable $e) {}
    }

    /**
     * Step 5: If GM Returns request, General Service provides Money, Time, Material details + technician and Petty Cash Owner.
     */
    public function resubmitReturned(Request $request, MaintenanceRequest $maintenanceRequest)
    {
        $this->ensureWorkflowColumns();

        $validated = $request->validate([
            'maintenance_person_name'    => 'required|string|max:255',
            'maintenance_person_account' => 'required|string|max:255',
            'maintenance_person_phone'   => 'required|string|max:100',
            'petty_cash_owner_id'        => 'required|exists:users,id',
            'money_amount'               => 'required|numeric|min:0',
            'estimated_time'             => 'required|string|max:150',
            'material_description'       => 'required|string|max:3000',
            'notes'                      => 'nullable|string|max:2000',
        ]);

        $pettyCashOwner = User::find($validated['petty_cash_owner_id']);
        $user = auth()->user();
        $timestamp = now()->format('d M Y, H:i');

        $existingNotes = $maintenanceRequest->admin_notes ? ($maintenanceRequest->admin_notes . "\n") : '';
        $newNotes = $existingNotes . "[GS Resubmitted Returned Ticket {$timestamp} by {$user->name}]: Money: ETB " . number_format($validated['money_amount'], 2) . ", Est. Time: {$validated['estimated_time']}, Materials: {$validated['material_description']}. Assigned Petty Cash: {$pettyCashOwner->name}. Technician: {$validated['maintenance_person_name']} (Acc: {$validated['maintenance_person_account']}, Tel: {$validated['maintenance_person_phone']}).";
        if (!empty($validated['notes'])) {
            $newNotes .= " Remarks: {$validated['notes']}";
        }

        $maintenanceRequest->update([
            'maintenance_person_name'    => $validated['maintenance_person_name'],
            'maintenance_person_account' => $validated['maintenance_person_account'],
            'maintenance_person_phone'   => $validated['maintenance_person_phone'],
            'petty_cash_owner_id'        => $validated['petty_cash_owner_id'],
            'money_amount'               => $validated['money_amount'],
            'estimated_time'             => $validated['estimated_time'],
            'material_description'       => $validated['material_description'],
            'is_returned_flow'           => true,
            'gs_status'                  => 'pending_gm_final',
            'admin_notes'                => $newNotes,
        ]);

        // Auto-create/sync MaterialRequest if material details provided so Store Manager can fulfill/PR
        if (!empty($validated['material_description'])) {
            $defaultStore = Store::where('is_active', true)->first();
            $project = \App\Models\Project::whereIn('status', ['active', 'in_progress'])->first() ?? \App\Models\Project::first();

            $refNumber = 'MR-MNT-' . str_replace('MNT-', '', $maintenanceRequest->request_no) . '-' . strtoupper(Str::random(3));
            while (MaterialRequest::where('reference_number', $refNumber)->exists()) {
                $refNumber = 'MR-MNT-' . str_replace('MNT-', '', $maintenanceRequest->request_no) . '-' . strtoupper(Str::random(4));
            }

            $matReq = MaterialRequest::firstOrCreate(
                ['maintenance_request_id' => $maintenanceRequest->id],
                [
                    'project_id'             => $project?->id,
                    'destination_store_id'   => $defaultStore?->id,
                    'reference_number'       => $refNumber,
                    'source'                 => 'Maintenance — ' . $maintenanceRequest->request_no,
                    'status'                 => 'sent_to_store_manager',
                    'required_date'          => now()->addDays(2),
                    'notes'                  => "Maintenance Materials: {$validated['material_description']}. Asset: {$maintenanceRequest->asset_name}. Est. Time: {$validated['estimated_time']}.",
                    'created_by'             => $user->id,
                ]
            );

            // Add standard material product line if empty
            if ($matReq->items()->count() === 0) {
                $product = Product::firstOrCreate(
                    ['name' => "Parts/Materials for {$maintenanceRequest->asset_name}"],
                    [
                        'sku'       => 'MAT-MNT-' . strtoupper(Str::random(5)),
                        'unit'      => 'pcs',
                        'category'  => 'Maintenance / Spare Parts',
                        'is_active' => true,
                    ]
                );
                $matReq->items()->create([
                    'product_id'         => $product->id,
                    'quantity_requested' => 1,
                    'notes'              => $validated['material_description'],
                ]);
            }
        }

        ActivityLog::log(
            'updated',
            "General Service resubmitted returned Maintenance #{$maintenanceRequest->request_no} with Money (ETB {$validated['money_amount']}), Time ({$validated['estimated_time']}), Materials, and Petty Cash Owner ({$pettyCashOwner->name}) to GM",
            'Maintenance Requests',
            $maintenanceRequest
        );

        return back()->with('success', "Returned Maintenance Request #{$maintenanceRequest->request_no} updated with Money, Time, Material & Petty Cash assignment, and submitted to General Manager!");
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
        $requestNumber = 'REQ-' . date('Ymd') . '-' . strtoupper(Str::random(4));

        $attachmentUrl = null;
        if ($request->hasFile('attachment')) {
            try {
                $cloudinary = app(CloudinaryService::class);
                $attachmentUrl = $cloudinary->upload($request->file('attachment'), 'expense_receipts');
            } catch (\Throwable $e) {
                Log::error('Maintenance Ask Money attachment upload error: ' . $e->getMessage());
            }
        }

        $expense = ExpenseRequest::create([
            'request_number'         => $requestNumber,
            'user_id'                => $user->id,
            'employee_id'            => $employee ? $employee->id : null,
            'maintenance_request_id' => $maintenanceRequest->id,
            'category'               => 'Maintenance',
            'other_reason'           => 'Maintenance for ' . $maintenanceRequest->request_no . ' (' . $maintenanceRequest->asset_name . ')',
            'amount'                 => $validated['amount'],
            'description'            => $validated['description'],
            'attachment'             => $attachmentUrl,
            'status'                 => ExpenseRequest::STATUS_PENDING_HR,
        ]);

        ActivityLog::log(
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
        if (Schema::hasTable('material_requests') && !Schema::hasColumn('material_requests', 'maintenance_request_id')) {
            Schema::table('material_requests', function ($table) {
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
            $store = Store::find($storeId);
        } else {
            $store = Store::where('is_active', true)->first();
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
        $refNumber = 'MR-MNT-' . str_replace('MNT-', '', $maintenanceRequest->request_no) . '-' . strtoupper(Str::random(3));
        while (MaterialRequest::where('reference_number', $refNumber)->exists()) {
            $refNumber = 'MR-MNT-' . str_replace('MNT-', '', $maintenanceRequest->request_no) . '-' . strtoupper(Str::random(4));
        }

        $materialRequest = MaterialRequest::create([
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
                $product = Product::firstOrCreate(
                    ['name' => trim($itemData['custom_name'])],
                    [
                        'sku'      => 'MAT-' . strtoupper(Str::random(6)),
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

        ActivityLog::log(
            'created',
            "General Service submitted Material Request #{$materialRequest->reference_number} ({$itemCount} item(s)) linked to Maintenance {$maintenanceRequest->request_no} and routed to Store Manager for procurement/issuance",
            'Maintenance Requests',
            $maintenanceRequest
        );

        return back()->with('success', "Material Request #{$materialRequest->reference_number} with {$itemCount} item(s) created and sent to Store Manager for procurement & stock fulfillment!");
    }
}
