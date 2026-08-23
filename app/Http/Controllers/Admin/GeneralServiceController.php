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
        $query = MaintenanceRequest::with(['employee', 'reportedBy', 'assignedTo', 'fixedAssetUnit.parentAsset'])
            ->withTrashed(false);

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
            'expenseRequests.financeStaff'
        ]);

        $staff = User::whereHas('roles', fn($q) => $q->whereIn('name', [
            'global_admin', 'admin', 'store_manager', 'general_service'
        ]))->get(['id', 'name']);

        return view('general-service.maintenance.show', compact('maintenanceRequest', 'staff'));
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
}
