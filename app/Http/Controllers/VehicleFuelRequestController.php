<?php

namespace App\Http\Controllers;

use App\Models\DriverBooking;
use App\Models\Employee;
use App\Models\FixedAssetUnit;
use App\Models\Project;
use App\Models\User;
use App\Models\VehicleFuelRequest;
use App\Services\FileUploadService;
use App\Services\VehicleFuelService;
use App\Services\VehicleKmService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class VehicleFuelRequestController extends Controller
{
    protected VehicleFuelService $fuelService;
    protected VehicleKmService $kmService;

    public function __construct(VehicleFuelService $fuelService, VehicleKmService $kmService)
    {
        $this->middleware('auth');
        $this->fuelService = $fuelService;
        $this->kmService   = $kmService;
    }

    /**
     * Runtime self-healing database schema verification.
     */
    public function ensureSchema(): void
    {
        try {
            if (Schema::hasTable('vehicle_fuel_requests')) {
                Schema::table('vehicle_fuel_requests', function ($table) {
                    if (!Schema::hasColumn('vehicle_fuel_requests', 'is_km_skipped')) {
                        $table->boolean('is_km_skipped')->default(false)->after('current_odometer_km')->index();
                    }
                    if (!Schema::hasColumn('vehicle_fuel_requests', 'price_per_liter')) {
                        $table->decimal('price_per_liter', 10, 2)->nullable()->after('liters_requested');
                    }
                });
            }
        } catch (\Throwable $e) {}
    }

    /**
     * Check if the authenticated user has General Admin or Super Admin privileges.
     */
    public function isGeneralAdmin(?User $user = null): bool
    {
        $user = $user ?: Auth::user();
        if (!$user) {
            return false;
        }

        if (method_exists($user, 'isGlobalAdmin') && $user->isGlobalAdmin()) {
            return true;
        }

        return $user->hasAnyRole([
            'admin',
            'Admin',
            'global_admin',
            'Global Admin',
            'general_admin',
            'General Admin',
            'general_service',
            'General Service',
            'gm',
            'GM',
            'super_admin',
            'Super Admin',
        ]);
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
        if ($user && $user->hasAnyRole(['general_service', 'admin', 'global_admin', 'gm', 'finance_head', 'finance_manager'])) {
            return;
        }

        $empId = $user?->employee?->id;
        $isAssigned = FixedAssetUnit::where('id', $unitId)
            ->where('assigned_to_employee_id', $empId)
            ->exists();

        if (!$isAssigned) {
            abort(403, 'Unauthorized. Drivers may only request fuel for their assigned vehicle.');
        }
    }

    /**
     * Display a listing of fuel requests.
     */
    public function index(Request $request)
    {
        $this->ensureSchema();
        $user = Auth::user();
        $isGeneralAdmin = $this->isGeneralAdmin($user);
        $vehicles = $this->getAccessibleVehicles();
        $accessibleIds = $vehicles->pluck('id')->toArray();

        $query = VehicleFuelRequest::with([
            'assetUnit.parentAsset',
            'driver',
            'requestedBy',
            'project',
            'expenseRequest.assignedFinanceStaff',
            'expenseRequest.paidBy'
        ])->whereIn('fixed_asset_unit_id', $accessibleIds);

        if ($request->filled('vehicle_id')) {
            $query->where('fixed_asset_unit_id', $request->vehicle_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('fuel_type')) {
            $query->where('fuel_type', $request->fuel_type);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('request_no', 'like', "%{$search}%")
                  ->orWhereHas('assetUnit', fn($u) => $u->where('plate_number', 'like', "%{$search}%")->orWhere('unit_code', 'like', "%{$search}%"))
                  ->orWhereHas('driver', fn($d) => $d->where('full_name', 'like', "%{$search}%"))
                  ->orWhere('reason', 'like', "%{$search}%")
                  ->orWhere('fuel_station_name', 'like', "%{$search}%");
            });
        }

        $requests = $query->latest('id')->paginate(15)->withQueryString();

        return view('general-service.fuel-requests.index', compact('requests', 'vehicles', 'isGeneralAdmin'));
    }

    /**
     * Show the form for creating a new fuel request.
     */
    public function create(Request $request)
    {
        $this->ensureSchema();
        $user = Auth::user();
        $isGeneralAdmin = $this->isGeneralAdmin($user);
        $vehicles = $this->getAccessibleVehicles();
        $selectedUnitId = $request->query('vehicle_id', $vehicles->first()?->id);

        $selectedVehicle = null;
        $serviceStatus   = null;
        $lastOdometer    = 0;
        $unreceiptedReq  = null;
        $policy          = $this->fuelService->getUnreceiptedPolicy();

        if ($selectedUnitId) {
            $selectedVehicle = $vehicles->firstWhere('id', $selectedUnitId);
            if ($selectedVehicle) {
                $lastOdometer   = $this->kmService->getLastOdometer($selectedVehicle->id);
                $serviceStatus  = $this->kmService->getServiceStatus($selectedVehicle->id);
                $unreceiptedReq = $this->fuelService->hasUnreceiptedFuelRequest($selectedVehicle->id);
            }
        }

        $drivers = Employee::activeRoster()->driversOnly()->orderBy('full_name')->get();
        $projects = Project::whereIn('status', ['active', 'planning', 'in_progress'])->orderBy('name')->get();
        $recentBookings = DriverBooking::with(['purchaseRequest', 'driver'])->latest('scheduled_at')->take(10)->get();

        return view('general-service.fuel-requests.create', compact(
            'vehicles',
            'selectedVehicle',
            'selectedUnitId',
            'lastOdometer',
            'serviceStatus',
            'unreceiptedReq',
            'policy',
            'drivers',
            'projects',
            'recentBookings',
            'isGeneralAdmin'
        ));
    }

    /**
     * Store a newly created fuel request.
     */
    public function store(Request $request)
    {
        $this->ensureSchema();
        $user = Auth::user();
        $isGeneralAdmin = $this->isGeneralAdmin($user);
        $skipKm = $isGeneralAdmin && $request->boolean('skip_km');

        $rules = [
            'fixed_asset_unit_id' => 'required|exists:fixed_asset_units,id',
            'fuel_type'           => 'required|in:diesel,benzine',
            'estimated_amount'    => 'required|numeric|min:1',
            'price_per_liter'     => 'nullable|numeric|min:0.01',
            'reason'              => 'nullable|string|max:1000',
            'driver_employee_id'  => 'nullable|exists:employees,id',
            'driver_booking_id'   => 'nullable|exists:driver_bookings,id',
            'project_id'          => 'nullable|exists:projects,id',
            'odometer_photo'      => 'nullable|file|mimes:jpeg,png,jpg,webp,pdf|max:10240',
        ];

        if ($skipKm) {
            $rules['current_odometer_km'] = 'nullable|integer|min:0';
            $rules['liters_requested']    = 'nullable|numeric|min:0.01';
        } else {
            $rules['current_odometer_km'] = 'required|integer|min:0';
            $rules['liters_requested']    = 'required|numeric|min:0.5';
        }

        $request->validate($rules);

        $unitId = (int) $request->fixed_asset_unit_id;
        $this->authorizeVehicleAccess($unitId);

        $pricePerLiter = $request->filled('price_per_liter') ? (float) $request->price_per_liter : null;
        $estimatedAmount = (float) $request->estimated_amount;
        $litersRequested = (float) $request->liters_requested;

        if ($litersRequested <= 0 && $pricePerLiter && $pricePerLiter > 0 && $estimatedAmount > 0) {
            $litersRequested = round($estimatedAmount / $pricePerLiter, 2);
        }

        $photoPath = null;
        if ($request->hasFile('odometer_photo')) {
            $photoPath = FileUploadService::upload($request->file('odometer_photo'), 'fuel_odometers');
        }

        $driverEmployeeId = $request->driver_employee_id;
        if (!$driverEmployeeId && Auth::user()?->employee) {
            $driverEmployeeId = Auth::user()->employee->id;
        }

        $fuelRequest = $this->fuelService->createFuelRequest([
            'fixed_asset_unit_id'  => $unitId,
            'current_odometer_km'  => $request->filled('current_odometer_km') ? (int) $request->current_odometer_km : null,
            'skip_km'              => $skipKm,
            'is_km_skipped'        => $skipKm,
            'price_per_liter'      => $pricePerLiter,
            'fuel_type'            => $request->fuel_type,
            'liters_requested'     => $litersRequested,
            'estimated_amount'     => $estimatedAmount,
            'reason'               => $request->reason,
            'driver_employee_id'   => $driverEmployeeId,
            'driver_booking_id'    => $request->driver_booking_id,
            'project_id'           => $request->project_id,
            'odometer_photo'       => $photoPath,
            'requested_by_user_id' => Auth::id(),
            'override_unreceipted' => $skipKm ? true : $request->boolean('override_unreceipted'),
        ]);

        $warningNote = $fuelRequest->service_due_warning 
            ? " (Attention: This vehicle is within 500 KM of routine service or overdue)."
            : "";

        return redirect()->route('vehicle-fuel-requests.show', $fuelRequest->id)
            ->with('success', "Fuel Request #{$fuelRequest->request_no} submitted successfully! Expense Request automatically generated and dispatched to the Finance Head for disbursement{$warningNote}.");
    }

    /**
     * Display the specified fuel request with expense & reconciliation status.
     */
    public function show(VehicleFuelRequest $fuelRequest)
    {
        $this->ensureSchema();
        $user = Auth::user();
        $isGeneralAdmin = $this->isGeneralAdmin($user);
        $fuelRequest->load([
            'assetUnit.parentAsset',
            'driver',
            'requestedBy',
            'project',
            'expenseRequest.assignedFinanceStaff',
            'expenseRequest.paidBy',
            'expenseRequest.chartOfAccount',
            'expenseRequest.bankAccount',
            'closedByUser'
        ]);

        $this->authorizeVehicleAccess($fuelRequest->fixed_asset_unit_id);

        $serviceStatus = $this->kmService->getServiceStatus($fuelRequest->fixed_asset_unit_id);
        $consumptionMetrics = $this->fuelService->getVehicleConsumptionMetrics($fuelRequest->fixed_asset_unit_id);

        return view('general-service.fuel-requests.show', compact('fuelRequest', 'serviceStatus', 'consumptionMetrics', 'isGeneralAdmin'));
    }

    /**
     * Upload fuel station receipt, record actual liters and close the request.
     */
    public function uploadReceipt(Request $request, VehicleFuelRequest $fuelRequest)
    {
        $this->authorizeVehicleAccess($fuelRequest->fixed_asset_unit_id);

        $request->validate([
            'receipt_photo'       => 'required|file|mimes:jpeg,png,jpg,webp,pdf|max:10240',
            'receipt_odometer_km' => 'nullable|integer|min:' . $fuelRequest->current_odometer_km,
            'actual_liters'       => 'nullable|numeric|min:0.1',
            'actual_amount'       => 'nullable|numeric|min:1',
            'fuel_station_name'   => 'nullable|string|max:255',
            'fuel_station_tin'    => 'nullable|string|max:50',
        ]);

        $receiptPath = FileUploadService::upload($request->file('receipt_photo'), 'fuel_receipts');

        $this->fuelService->attachReceiptAndClose($fuelRequest, [
            'receipt_photo'       => $receiptPath,
            'receipt_odometer_km' => $request->receipt_odometer_km,
            'actual_liters'       => $request->actual_liters,
            'actual_amount'       => $request->actual_amount,
            'fuel_station_name'   => $request->fuel_station_name,
            'fuel_station_tin'    => $request->fuel_station_tin,
        ]);

        return redirect()->route('vehicle-fuel-requests.show', $fuelRequest->id)
            ->with('success', "Fuel station receipt attached successfully! Request #{$fuelRequest->request_no} has been verified and closed.");
    }

    /**
     * Control and reports dashboard: Fuel consumption, efficiency flags, and monthly costs.
     */
    public function reports(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasAnyRole(['general_service', 'admin', 'global_admin', 'gm', 'finance_head', 'finance_manager'])) {
            abort(403, 'Unauthorized access to fuel control reports.');
        }

        $vehicles = FixedAssetUnit::with(['parentAsset', 'assignedEmployee'])
            ->whereHas('parentAsset', fn($q) => $q->whereIn('category', ['Vehicles', 'Vehicle', 'Cars', 'Car', 'Vehicles/Cars']))
            ->get();

        $reportData = [];
        $totalMonthlyExpense = 0;
        $totalMonthlyLiters  = 0;
        $flaggedVehiclesCount = 0;

        foreach ($vehicles as $v) {
            $metrics = $this->fuelService->getVehicleConsumptionMetrics($v->id);
            $service = $this->kmService->getServiceStatus($v->id);

            $monthlyLiters = (float) VehicleFuelRequest::where('fixed_asset_unit_id', $v->id)
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->whereIn('status', [VehicleFuelRequest::STATUS_PAID, VehicleFuelRequest::STATUS_CLOSED])
                ->sum('actual_liters');

            $totalMonthlyExpense += $metrics['monthly_fuel_cost'];
            $totalMonthlyLiters  += $monthlyLiters;

            if ($metrics['is_efficiency_flag']) {
                $flaggedVehiclesCount++;
            }

            $reportData[] = [
                'vehicle'        => $v,
                'metrics'        => $metrics,
                'service'        => $service,
                'monthly_liters' => $monthlyLiters,
            ];
        }

        return view('general-service.fuel-requests.reports', compact(
            'reportData',
            'totalMonthlyExpense',
            'totalMonthlyLiters',
            'flaggedVehiclesCount'
        ));
    }
}
