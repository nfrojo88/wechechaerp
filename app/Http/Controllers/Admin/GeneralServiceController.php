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
            'pending'     => MaintenanceRequest::where('status', 'pending')->count(),
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
        $maintenanceRequest->load(['employee', 'reportedBy', 'assignedTo', 'fixedAssetUnit.parentAsset']);

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
            'status'               => 'required|in:pending,in_progress,resolved,closed',
            'admin_notes'          => 'nullable|string|max:3000',
            'assigned_to_user_id'  => 'nullable|exists:users,id',
        ]);

        $data = [
            'status'              => $validated['status'],
            'admin_notes'         => $validated['admin_notes'] ?? $maintenanceRequest->admin_notes,
            'assigned_to_user_id' => $validated['assigned_to_user_id'] ?? $maintenanceRequest->assigned_to_user_id,
        ];

        if ($validated['status'] === 'resolved' && !$maintenanceRequest->resolved_at) {
            $data['resolved_at'] = now();
        }

        $maintenanceRequest->update($data);

        \App\Models\ActivityLog::log(
            'updated',
            'Maintenance request ' . $maintenanceRequest->request_no . ' status updated to ' . $validated['status'],
            'Maintenance Requests',
            $maintenanceRequest
        );

        return back()->with('success', 'Request ' . $maintenanceRequest->request_no . ' updated successfully.');
    }
}
