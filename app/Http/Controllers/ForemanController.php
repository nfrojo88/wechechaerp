<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\FixedAsset;
use App\Models\FixedAssetUnit;
use App\Models\MaintenanceRequest;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ForemanController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    // ─── Helper: resolve the Foreman's assigned store ─────────────────────────

    private function getMyStore(): ?Store
    {
        $user = Auth::user();

        // Primary: user has a store_id (assigned store/site)
        if ($user->store_id) {
            return Store::find($user->store_id);
        }

        // Fallback: try employee → project → first active store on that project
        $employee = Employee::where('user_id', $user->id)->first();
        if ($employee && $employee->project_id) {
            $store = Store::where('project_id', $employee->project_id)
                          ->where('is_active', true)
                          ->first();
            if ($store) return $store;
        }

        return null;
    }

    // ─── Foreman: Fixed Asset Inventory (filtered to their site) ──────────────

    /**
     * Show fixed assets assigned to the Foreman's site/store.
     * A Foreman can only see units whose parent FixedAsset belongs to their store.
     */
    public function fixedAssetsIndex(Request $request)
    {
        $user  = Auth::user();
        $store = $this->getMyStore();

        // Admin bypass: show everything
        $isAdmin = $user->hasAnyRole(['admin', 'global_admin']);

        $search   = $request->input('search');
        $status   = $request->input('status');
        $category = $request->input('category');

        // Auto-sync from Inventory & Catalog
        try {
            FixedAsset::syncFromInventory(null, $search);
        } catch (\Throwable $e) {}

        // Build query for FixedAssetUnits at this site
        $unitsQuery = FixedAssetUnit::with(['parentAsset.store', 'assignedEmployee'])
            ->whereNull('fixed_asset_units.deleted_at')
            ->join('fixed_assets', 'fixed_asset_units.fixed_asset_id', '=', 'fixed_assets.id')
            ->whereNull('fixed_assets.deleted_at')
            ->select('fixed_asset_units.*');

        // Scope to site unless admin
        if (!$isAdmin && $store) {
            $unitsQuery->where('fixed_assets.store_id', $store->id);
        }

        if ($search) {
            $unitsQuery->where(function ($q) use ($search) {
                $q->where('fixed_asset_units.unit_code', 'like', "%{$search}%")
                  ->orWhere('fixed_asset_units.serial_number', 'like', "%{$search}%")
                  ->orWhere('fixed_asset_units.plate_number', 'like', "%{$search}%")
                  ->orWhere('fixed_assets.name', 'like', "%{$search}%");
            });
        }

        if ($status) {
            $unitsQuery->where('fixed_asset_units.status', $status);
        }

        if ($category) {
            $unitsQuery->where('fixed_assets.category', $category);
        }

        $units = $unitsQuery->orderBy('fixed_assets.name')->orderBy('fixed_asset_units.sequence_number')->paginate(20)->withQueryString();

        // Get IDs of units that already have an OPEN maintenance request
        $openRequestUnitIds = MaintenanceRequest::whereNotIn('status', ['resolved', 'closed'])
            ->whereNotNull('fixed_asset_unit_id')
            ->pluck('fixed_asset_unit_id')
            ->toArray();

        // KPI counts for this site
        $kpi = [
            'total'       => $units->total(),
            'in_store'    => (clone $unitsQuery)->where('fixed_asset_units.status', 'in_store')->count(),
            'assigned'    => (clone $unitsQuery)->where('fixed_asset_units.status', 'assigned')->count(),
            'maintenance' => (clone $unitsQuery)->where('fixed_asset_units.status', 'maintenance')->count(),
        ];

        // Categories for filter
        $categories = FixedAsset::select('category')->distinct()->orderBy('category')->pluck('category');

        return view('foreman.fixed-assets.index', compact(
            'units', 'store', 'kpi', 'categories', 'openRequestUnitIds',
            'search', 'status', 'category', 'isAdmin'
        ));
    }

    // ─── Foreman: Submit a Maintenance Request ────────────────────────────────

    /**
     * Store a new maintenance request from Foreman or staff.
     */
    public function storeMaintenanceRequest(Request $request)
    {
        $validated = $request->validate([
            'fixed_asset_unit_id' => 'nullable|exists:fixed_asset_units,id',
            'asset_name'          => 'required_without:fixed_asset_unit_id|nullable|string|max:255',
            'asset_code'          => 'nullable|string|max:100',
            'issue_type'          => 'required|string|max:100',
            'urgency'             => 'required|in:low,normal,urgent,critical',
            'description'         => 'required|string|max:3000',
        ]);

        $user = Auth::user();

        // 1. Resolve asset details
        $unit = null;
        if (!empty($validated['fixed_asset_unit_id'])) {
            $unit = FixedAssetUnit::with('parentAsset')->find($validated['fixed_asset_unit_id']);
        }

        $assetName = $unit ? ($unit->parentAsset?->name ?? $unit->unit_code) : ($validated['asset_name'] ?? 'Asset');
        $assetCode = $unit ? $unit->unit_code : ($validated['asset_code'] ?? null);

        // Ensure no open request already exists for this unit
        if ($unit) {
            $existingOpen = MaintenanceRequest::where('fixed_asset_unit_id', $unit->id)
                ->whereNotIn('status', ['resolved', 'closed'])
                ->exists();

            if ($existingOpen) {
                return back()->with('error', "⚠️ Asset [{$unit->unit_code}] already has an active maintenance request. It cannot be reported again until the current request is closed.");
            }
        }

        // 2. Resolve employee profile
        $employee = Employee::where('user_id', $user->id)->first();
        if (!$employee && !empty($user->email)) {
            $employee = Employee::where('email', $user->email)->first();
            if ($employee && empty($employee->user_id)) {
                $employee->update(['user_id' => $user->id]);
            }
        }
        if (!$employee) {
            $employee = Employee::where('status', 'active')->first();
        }
        if (!$employee) {
            $nameParts = explode(' ', trim($user->name));
            $employee = Employee::create([
                'user_id'    => $user->id,
                'first_name' => $nameParts[0] ?? $user->name,
                'last_name'  => $nameParts[1] ?? 'Staff',
                'email'      => $user->email ?? ($user->username . '@wechecha.com'),
                'status'     => 'active',
            ]);
        }

        // 3. Create the request — starts as "pending" (awaiting GS pickup)
        $mr = MaintenanceRequest::create([
            'employee_id'         => $employee->id,
            'fixed_asset_unit_id' => $unit?->id,
            'asset_name'          => $assetName,
            'asset_code'          => $assetCode,
            'issue_type'          => $validated['issue_type'],
            'urgency'             => $validated['urgency'],
            'description'         => $validated['description'],
            'status'              => 'pending',
            'reported_by_user_id' => $user->id,
        ]);

        // 4. Mark the unit as under maintenance if unit linked
        if ($unit) {
            try {
                $unit->update(['status' => FixedAssetUnit::STATUS_MAINTENANCE]);
            } catch (\Throwable $e) {}
        }

        // 5. Activity log
        try {
            \App\Models\ActivityLog::log(
                'created',
                "Maintenance request {$mr->request_no} reported by {$user->name} for asset: {$assetName}" . ($assetCode ? " ({$assetCode})" : ''),
                'Maintenance Requests',
                $mr
            );
        } catch (\Throwable $e) {}

        return redirect()->route('foreman.my-maintenance-requests')
            ->with('success', "✅ Maintenance request {$mr->request_no} submitted successfully! General Service will be notified.");
    }

    // ─── Foreman: My Submitted Maintenance Requests ───────────────────────────

    /**
     * Show all maintenance requests submitted by this Foreman/User.
     */
    public function myMaintenanceRequests(Request $request)
    {
        $user = Auth::user();
        $employee = Employee::where('user_id', $user->id)->first();

        $query = MaintenanceRequest::with(['fixedAssetUnit.parentAsset', 'assignedTo'])
            ->where(function ($q) use ($user, $employee) {
                $q->where('reported_by_user_id', $user->id);
                if ($employee) {
                    $q->orWhere('employee_id', $employee->id);
                }
            });

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $requests = $query->latest()->paginate(15)->withQueryString();

        $baseCountQuery = function ($status = null) use ($user, $employee) {
            $q = MaintenanceRequest::where(function ($sq) use ($user, $employee) {
                $sq->where('reported_by_user_id', $user->id);
                if ($employee) {
                    $sq->orWhere('employee_id', $employee->id);
                }
            });
            if ($status) {
                $q->where('status', $status);
            }
            return $q->count();
        };

        $stats = [
            'pending'               => $baseCountQuery('pending'),
            'in_progress'           => $baseCountQuery('in_progress'),
            'resolved'              => $baseCountQuery('resolved'),
            'sent_to_store_manager' => $baseCountQuery('sent_to_store_manager'),
        ];

        // Fetch available units for the report modal
        $store = $this->getMyStore();
        $isAdmin = $user->hasAnyRole(['admin', 'global_admin']);

        $unitsQuery = FixedAssetUnit::with(['parentAsset.store'])
            ->whereNull('deleted_at')
            ->where('status', '!=', FixedAssetUnit::STATUS_DISPOSED);

        if (!$isAdmin && $store) {
            $unitsQuery->whereHas('parentAsset', function ($q) use ($store) {
                $q->where('store_id', $store->id);
            });
        }

        $availableUnits = $unitsQuery->orderBy('unit_code')->get();

        return view('foreman.maintenance.my-requests', compact('requests', 'stats', 'availableUnits', 'store'));
    }

    // ─── Store Manager: Damaged Assets Panel ──────────────────────────────────

    /**
     * Store Manager view — list all assets sent to them as "Damaged / Not Repairable".
     */
    public function storeManagerDamagedAssets(Request $request)
    {
        $user = Auth::user();

        // Store manager, admin, global_admin, and foreman (read-only view)
        if (!$user->hasAnyRole(['store_manager', 'admin', 'global_admin', 'foreman', 'Foreman'])) {
            abort(403, 'Access denied.');
        }

        $query = MaintenanceRequest::with([
            'fixedAssetUnit.parentAsset.store',
            'employee',
            'reportedBy',
            'assignedTo',
        ])->where('status', 'sent_to_store_manager');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('request_no', 'like', "%$s%")
                  ->orWhere('asset_name', 'like', "%$s%")
                  ->orWhere('asset_code', 'like', "%$s%");
            });
        }

        $requests = $query->latest('sent_to_store_manager_at')->paginate(20)->withQueryString();

        return view('store-manager.damaged-assets.index', compact('requests'));
    }

    /**
     * Store Manager action — Receive / Dispose / Write-off a damaged asset.
     */
    public function disposeDamagedAsset(Request $request, MaintenanceRequest $maintenanceRequest)
    {
        $user = Auth::user();
        if (!$user->hasAnyRole(['store_manager', 'admin', 'global_admin'])) {
            abort(403, 'Access denied.');
        }

        $validated = $request->validate([
            'action'       => 'required|in:received,disposed,write_off',
            'notes'        => 'nullable|string|max:1000',
        ]);

        $unit = $maintenanceRequest->fixedAssetUnit;

        $statusMap = [
            'received'  => 'sent_to_store_manager', // stays, just acknowledged
            'disposed'  => 'closed',
            'write_off' => 'closed',
        ];

        $newStatus = $statusMap[$validated['action']];

        $maintenanceRequest->update([
            'status'      => $newStatus,
            'admin_notes' => ($maintenanceRequest->admin_notes ? $maintenanceRequest->admin_notes . "\n" : '')
                           . "[Store Manager — " . now()->format('d M Y H:i') . "] "
                           . ucfirst($validated['action']) . ": " . ($validated['notes'] ?? 'No notes.'),
        ]);

        // If disposed or written off, mark unit as disposed
        if ($unit && in_array($validated['action'], ['disposed', 'write_off'])) {
            $unit->update([
                'status'    => FixedAssetUnit::STATUS_DISPOSED,
                'condition' => 'damaged',
            ]);
        }

        \App\Models\ActivityLog::log(
            'updated',
            "Store Manager {$user->name} actioned damaged asset {$maintenanceRequest->request_no}: {$validated['action']}",
            'Maintenance Requests',
            $maintenanceRequest
        );

        $actionLabel = match($validated['action']) {
            'received'  => 'marked as received',
            'disposed'  => 'disposed / retired',
            'write_off' => 'written off',
        };

        return back()->with('success', "Asset {$maintenanceRequest->asset_code} {$actionLabel} successfully.");
    }
}
