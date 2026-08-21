<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\MaintenanceRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MaintenanceRequestController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Store a new maintenance request (submitted by employee from profile page).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'asset_name'          => 'required|string|max:255',
            'asset_code'          => 'nullable|string|max:100',
            'fixed_asset_unit_id' => 'nullable|integer|exists:fixed_asset_units,id',
            'employee_asset_id'   => 'nullable|integer',
            'issue_type'          => 'required|in:breakdown,damage,service_due,malfunction,needs_repair,other',
            'urgency'             => 'required|in:low,normal,urgent,critical',
            'description'         => 'required|string|max:3000',
        ]);

        $employee = Employee::where('user_id', Auth::id())->firstOrFail();

        $mr = MaintenanceRequest::create(array_merge($validated, [
            'employee_id'       => $employee->id,
            'status'            => 'pending',
            'reported_by_user_id' => Auth::id(),
        ]));

        \App\Models\ActivityLog::log(
            'created',
            'Maintenance request ' . $mr->request_no . ' submitted for asset: ' . $mr->asset_name,
            'Maintenance Requests',
            $mr
        );

        return redirect()->route('profile.edit')
            ->with('success', '✅ Maintenance request ' . $mr->request_no . ' submitted successfully. General Service team will be notified.');
    }

    /**
     * Show a single maintenance request (employee view — own requests only).
     */
    public function show(MaintenanceRequest $maintenanceRequest)
    {
        $employee = Employee::where('user_id', Auth::id())->firstOrFail();

        if ($maintenanceRequest->employee_id !== $employee->id) {
            abort(403, 'You are not allowed to view this maintenance request.');
        }

        $maintenanceRequest->load(['fixedAssetUnit.parentAsset', 'reportedBy', 'assignedTo']);

        return view('maintenance.show', compact('maintenanceRequest'));
    }
}
