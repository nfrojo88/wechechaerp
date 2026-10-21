<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\FixedAssetUnit;
use App\Models\VehicleKmLog;
use App\Services\FileUploadService;
use App\Services\VehicleKmService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VehicleKmLogController extends Controller
{
    protected VehicleKmService $kmService;

    public function __construct(VehicleKmService $kmService)
    {
        $this->middleware('auth');
        $this->kmService = $kmService;
    }

    /**
     * Resolve vehicles accessible by the authenticated user.
     */
    protected function getAccessibleVehicles()
    {
        $user = Auth::user();
        $isElevated = $user && $user->hasAnyRole(['general_service', 'admin', 'global_admin', 'gm', 'finance_head', 'finance_manager']);

        if ($isElevated) {
            return FixedAssetUnit::with(['parentAsset', 'assignedEmployee'])
                ->whereHas('parentAsset', fn($q) => $q->whereIn('category', ['Vehicles', 'Vehicle', 'Cars', 'Car', 'Vehicles/Cars']))
                ->orderBy('unit_code')
                ->get();
        }

        // Driver: Only assigned vehicles
        $empId = $user?->employee?->id;
        if (!$empId) {
            return collect();
        }

        return FixedAssetUnit::with(['parentAsset', 'assignedEmployee'])
            ->where('assigned_to_employee_id', $empId)
            ->get();
    }

    /**
     * Check if user is authorized to manage the specific vehicle.
     */
    protected function authorizeVehicleAccess(int $unitId): void
    {
        $user = Auth::user();
        if ($user && $user->hasAnyRole(['general_service', 'admin', 'global_admin', 'gm', 'finance_head'])) {
            return;
        }

        $empId = $user?->employee?->id;
        $isAssigned = FixedAssetUnit::where('id', $unitId)
            ->where('assigned_to_employee_id', $empId)
            ->exists();

        if (!$isAssigned) {
            abort(403, 'Unauthorized. Drivers may only log KM for their assigned vehicle.');
        }
    }

    /**
     * Display a listing of KM logs.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $vehicles = $this->getAccessibleVehicles();
        $accessibleIds = $vehicles->pluck('id')->toArray();

        $query = VehicleKmLog::with(['assetUnit.parentAsset', 'driver', 'loggedBy'])
            ->whereIn('fixed_asset_unit_id', $accessibleIds);

        if ($request->filled('vehicle_id')) {
            $query->where('fixed_asset_unit_id', $request->vehicle_id);
        }

        if ($request->filled('date_from')) {
            $query->where('km_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('km_date', '<=', $request->date_to);
        }

        if ($request->filled('source')) {
            $query->where('source', $request->source);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('assetUnit', fn($u) => $u->where('plate_number', 'like', "%{$search}%")->orWhere('unit_code', 'like', "%{$search}%"))
                  ->orWhereHas('driver', fn($d) => $d->where('full_name', 'like', "%{$search}%"))
                  ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        $logs = $query->latestFirst()->paginate(20)->withQueryString();

        return view('general-service.km-logs.index', compact('logs', 'vehicles'));
    }

    /**
     * Show the form for creating a new KM log.
     */
    public function create(Request $request)
    {
        $vehicles = $this->getAccessibleVehicles();
        $selectedUnitId = $request->query('vehicle_id', $vehicles->first()?->id);
        
        $selectedVehicle = null;
        $serviceStatus = null;
        $lastOdometer = 0;

        if ($selectedUnitId) {
            $selectedVehicle = $vehicles->firstWhere('id', $selectedUnitId);
            if ($selectedVehicle) {
                $lastOdometer = $this->kmService->getLastOdometer($selectedVehicle->id);
                $serviceStatus = $this->kmService->getServiceStatus($selectedVehicle->id);
            }
        }

        $drivers = Employee::activeRoster()->driversOnly()->orderBy('full_name')->get();

        return view('general-service.km-logs.create', compact(
            'vehicles',
            'selectedVehicle',
            'selectedUnitId',
            'lastOdometer',
            'serviceStatus',
            'drivers'
        ));
    }

    /**
     * Store a newly created KM log in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'fixed_asset_unit_id' => 'required|exists:fixed_asset_units,id',
            'odometer_km'         => 'required|integer|min:0',
            'km_date'             => 'required|date',
            'driver_employee_id'  => 'nullable|exists:employees,id',
            'odometer_photo'      => 'nullable|file|mimes:jpeg,png,jpg,webp,pdf|max:10240',
            'notes'               => 'nullable|string|max:1000',
        ]);

        $unitId = (int) $request->fixed_asset_unit_id;
        $this->authorizeVehicleAccess($unitId);

        $photoPath = null;
        if ($request->hasFile('odometer_photo')) {
            $photoPath = FileUploadService::upload($request->file('odometer_photo'), 'vehicle_km');
        }

        $driverEmployeeId = $request->driver_employee_id;
        if (!$driverEmployeeId && Auth::user()?->employee) {
            $driverEmployeeId = Auth::user()->employee->id;
        }

        $log = $this->kmService->recordKm(
            unitId: $unitId,
            newOdometerKm: (int) $request->odometer_km,
            kmDate: $request->km_date,
            driverEmployeeId: $driverEmployeeId ? (int) $driverEmployeeId : null,
            loggedByUserId: Auth::id(),
            photoPath: $photoPath,
            source: VehicleKmLog::SOURCE_DIRECT_LOG,
            notes: $request->notes
        );

        $warningMsg = $log->is_large_jump 
            ? " (Note: A large KM increase of " . number_format($log->km_difference) . " KM was recorded and flagged for review)."
            : "";

        return redirect()->route('vehicle-km-logs.index')
            ->with('success', "Odometer reading of " . number_format($log->odometer_km) . " KM saved successfully! Vehicle service status synchronized{$warningMsg}.");
    }

    /**
     * API / AJAX helper to get current odometer and service reminder status for a vehicle.
     */
    public function vehicleStatus(FixedAssetUnit $unit)
    {
        $this->authorizeVehicleAccess($unit->id);
        
        $lastKm = $this->kmService->getLastOdometer($unit->id);
        $serviceStatus = $this->kmService->getServiceStatus($unit->id);

        return response()->json([
            'unit_id'        => $unit->id,
            'plate_number'   => $unit->plate_number,
            'unit_code'      => $unit->unit_code,
            'last_odometer'  => $lastKm,
            'service_status' => $serviceStatus,
        ]);
    }
}
