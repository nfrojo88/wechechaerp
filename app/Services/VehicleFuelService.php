<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\ExpenseRequest;
use App\Models\FixedAssetUnit;
use App\Models\User;
use App\Models\VehicleFuelRequest;
use App\Models\VehicleKmLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class VehicleFuelService
{
    protected VehicleKmService $kmService;
    protected ProcurementSmsService $smsService;

    public function __construct(
        ?VehicleKmService $kmService = null,
        ?ProcurementSmsService $smsService = null
    ) {
        $this->kmService  = $kmService ?? app(VehicleKmService::class);
        $this->smsService = $smsService ?? app(ProcurementSmsService::class);
    }

    /**
     * Check if the previous fuel request for this vehicle has a receipt attached.
     * Returns true if clear, false if pending receipt.
     */
    public function hasUnreceiptedFuelRequest(int $unitId): ?VehicleFuelRequest
    {
        return VehicleFuelRequest::where('fixed_asset_unit_id', $unitId)
            ->whereIn('status', [VehicleFuelRequest::STATUS_PAID, 'paid'])
            ->whereNull('receipt_photo')
            ->latest('id')
            ->first();
    }

    /**
     * Get the unreceipted policy: 'block' or 'warn'.
     */
    public function getUnreceiptedPolicy(): string
    {
        return config('services.fuel.unreceipted_policy', env('FUEL_UNRECEIPTED_POLICY', 'block'));
    }

    /**
     * Create a new fuel request, auto-log the KM, and auto-create the Expense Request for Finance Head.
     *
     * @param array $data
     * @return VehicleFuelRequest
     * @throws ValidationException
     */
    public function createFuelRequest(array $data): VehicleFuelRequest
    {
        $unitId           = (int) $data['fixed_asset_unit_id'];
        $currentKm        = (int) $data['current_odometer_km'];
        $fuelType         = $data['fuel_type'] ?? VehicleFuelRequest::FUEL_TYPE_DIESEL;
        $litersRequested  = (float) $data['liters_requested'];
        $estimatedAmount  = (float) $data['estimated_amount'];
        $reason           = $data['reason'] ?? 'Routine fuel replenishment';
        $driverEmployeeId = !empty($data['driver_employee_id']) ? (int) $data['driver_employee_id'] : null;
        $driverBookingId  = !empty($data['driver_booking_id']) ? (int) $data['driver_booking_id'] : null;
        $projectId        = !empty($data['project_id']) ? (int) $data['project_id'] : null;
        $odometerPhoto    = $data['odometer_photo'] ?? null;
        $userId           = Auth::id() ?: ($data['requested_by_user_id'] ?? 1);

        $unit = FixedAssetUnit::with(['parentAsset', 'assignedEmployee'])->findOrFail($unitId);
        $plateNumber = $unit->plate_number ?: ($unit->unit_code ?: 'Vehicle #' . $unit->id);

        // 1. Check Unreceipted Policy
        $unreceipted = $this->hasUnreceiptedFuelRequest($unitId);
        if ($unreceipted) {
            $policy = $this->getUnreceiptedPolicy();
            if ($policy === 'block' && empty($data['override_unreceipted'])) {
                throw ValidationException::withMessages([
                    'fixed_asset_unit_id' => [
                        "Cannot submit a new fuel request for plate {$plateNumber}. The previous fuel request #{$unreceipted->request_no} has no receipt uploaded yet. Please attach the fuel station receipt first."
                    ]
                ]);
            }
        }

        // 2. Service reminder snapshot & proximity check
        $serviceInfo = $this->kmService->getServiceStatus($unitId);
        $serviceWarning = $serviceInfo['is_due_soon'] || $serviceInfo['is_overdue'];
        $serviceDueKm   = $serviceInfo['remaining_km'];

        return DB::transaction(function () use (
            $unit,
            $unitId,
            $currentKm,
            $fuelType,
            $litersRequested,
            $estimatedAmount,
            $reason,
            $driverEmployeeId,
            $driverBookingId,
            $projectId,
            $odometerPhoto,
            $userId,
            $serviceWarning,
            $serviceDueKm,
            $plateNumber
        ) {
            // A. Record KM log
            $today = date('Y-m-d');
            $kmLog = $this->kmService->recordKm(
                unitId: $unitId,
                newOdometerKm: $currentKm,
                kmDate: $today,
                driverEmployeeId: $driverEmployeeId,
                loggedByUserId: $userId,
                photoPath: $odometerPhoto,
                source: VehicleKmLog::SOURCE_FUEL_REQUEST,
                notes: "Logged via Fuel Request: {$litersRequested} L {$fuelType}"
            );

            // B. Create VehicleFuelRequest
            $fuelRequest = VehicleFuelRequest::create([
                'fixed_asset_unit_id'  => $unitId,
                'driver_employee_id'   => $driverEmployeeId,
                'requested_by_user_id' => $userId,
                'driver_booking_id'    => $driverBookingId,
                'project_id'           => $projectId,
                'current_odometer_km'  => $currentKm,
                'fuel_type'            => $fuelType,
                'liters_requested'     => $litersRequested,
                'estimated_amount'     => $estimatedAmount,
                'reason'               => $reason,
                'status'               => VehicleFuelRequest::STATUS_PENDING_FINANCE,
                'service_due_warning'  => $serviceWarning,
                'service_due_km'       => $serviceDueKm,
            ]);

            // C. Create ExpenseRequest linked directly to Finance Head
            $reqYear = date('Y');
            $lastExp = ExpenseRequest::withTrashed()->orderBy('id', 'desc')->first();
            $nextExpId = $lastExp ? ($lastExp->id + 1) : 1;
            $expRequestNumber = 'EXP-FUEL-' . $reqYear . '-' . str_pad($nextExpId, 4, '0', STR_PAD_LEFT);

            $submitterUser = User::find($userId);
            $driverEmployee = $driverEmployeeId ? Employee::find($driverEmployeeId) : ($unit->assignedEmployee ?? null);
            $driverName = $driverEmployee ? $driverEmployee->full_name : ($submitterUser ? $submitterUser->name : 'Driver');

            $expenseRequest = ExpenseRequest::create([
                'request_number' => $expRequestNumber,
                'user_id'        => $userId,
                'employee_id'    => $driverEmployee?->id,
                'project_id'     => $projectId,
                'category'       => ExpenseRequest::CATEGORY_TRANSPORT,
                'service_type'   => 'Vehicle Fuel (' . ucfirst($fuelType) . ')',
                'other_reason'   => "Fuel Request #{$fuelRequest->request_no} (Plate: {$plateNumber})",
                'amount'         => $estimatedAmount,
                'gross_amount'   => $estimatedAmount,
                'net_amount'     => $estimatedAmount,
                'is_bank_payment'=> false,
                'description'    => "Vehicle Fuel Request #{$fuelRequest->request_no}: {$litersRequested} L {$fuelType} for vehicle {$plateNumber} (Odometer: {$currentKm} KM). Purpose/Trip: {$reason}",
                'attachment'     => $odometerPhoto,
                'status'         => ExpenseRequest::STATUS_APPROVED_ASSIGNED, // Directly in Finance Head queue!
            ]);

            // Link ExpenseRequest back to VehicleFuelRequest
            $fuelRequest->update(['expense_request_id' => $expenseRequest->id]);

            // D. Dispatches SMS to Finance Head with recipient priority cascade
            $this->notifyFinanceHeadAndGeneralService($fuelRequest, $unit, $driverName, $plateNumber);

            return $fuelRequest;
        });
    }

    /**
     * Send SMS notification to Finance Head (with role fallback) and General Service.
     */
    protected function notifyFinanceHeadAndGeneralService(
        VehicleFuelRequest $fuelRequest,
        FixedAssetUnit $unit,
        string $driverName,
        string $plateNumber
    ): void {
        $liters = number_format($fuelRequest->liters_requested, 1);
        $amount = number_format($fuelRequest->estimated_amount, 2);

        // Required message format:
        // "Fuel request #{request_no} for plate {plate}, {liters} L, {amount} ETB by {name}. Needs your review."
        $message = "Fuel request #{$fuelRequest->request_no} for plate {$plateNumber}, {$liters} L, {$amount} ETB by {$driverName}. Needs your review.";

        try {
            // Notify Finance Head via 4-stage priority (direct person -> project -> role -> global admin fallback)
            $this->smsService->notifyRole(
                purchaseRequestId: 0,
                roleName: 'finance_head',
                message: $message,
                projectId: $fuelRequest->project_id
            );
        } catch (\Throwable $e) {
            Log::warning("VehicleFuelService: Failed to dispatch SMS to Finance Head: " . $e->getMessage());
        }

        try {
            // Send tracking notification to General Service
            $gsMessage = "[GS Info] New fuel request #{$fuelRequest->request_no} submitted for plate {$plateNumber} ({$liters} L / {$amount} ETB by {$driverName}).";
            $this->smsService->notifyRole(
                purchaseRequestId: 0,
                roleName: 'general_service',
                message: $gsMessage,
                projectId: $fuelRequest->project_id
            );
        } catch (\Throwable $e) {
            Log::warning("VehicleFuelService: Failed to dispatch SMS to General Service: " . $e->getMessage());
        }
    }

    /**
     * Attach fuel station receipt and close the fuel request.
     *
     * @param VehicleFuelRequest $fuelRequest
     * @param array $receiptData
     * @return VehicleFuelRequest
     * @throws ValidationException
     */
    public function attachReceiptAndClose(VehicleFuelRequest $fuelRequest, array $receiptData): VehicleFuelRequest
    {
        $receiptPhoto      = $receiptData['receipt_photo'] ?? null;
        $receiptOdometerKm = !empty($receiptData['receipt_odometer_km']) ? (int) $receiptData['receipt_odometer_km'] : null;
        $actualLiters      = !empty($receiptData['actual_liters']) ? (float) $receiptData['actual_liters'] : null;
        $actualAmount      = !empty($receiptData['actual_amount']) ? (float) $receiptData['actual_amount'] : null;
        $stationName       = $receiptData['fuel_station_name'] ?? null;
        $stationTin        = $receiptData['fuel_station_tin'] ?? null;
        $userId            = Auth::id() ?: 1;

        if (!$receiptPhoto && empty($fuelRequest->receipt_photo)) {
            throw ValidationException::withMessages([
                'receipt_photo' => ['A photo of the official fuel station receipt is required.'],
            ]);
        }

        return DB::transaction(function () use (
            $fuelRequest,
            $receiptPhoto,
            $receiptOdometerKm,
            $actualLiters,
            $actualAmount,
            $stationName,
            $stationTin,
            $userId
        ) {
            // If new odometer reading is provided and greater than previous, update KM log
            if ($receiptOdometerKm && $receiptOdometerKm >= $fuelRequest->current_odometer_km) {
                $this->kmService->recordKm(
                    unitId: $fuelRequest->fixed_asset_unit_id,
                    newOdometerKm: $receiptOdometerKm,
                    kmDate: date('Y-m-d'),
                    driverEmployeeId: $fuelRequest->driver_employee_id,
                    loggedByUserId: $userId,
                    photoPath: $receiptPhoto,
                    source: VehicleKmLog::SOURCE_FUEL_REQUEST,
                    notes: "Post-fueling odometer update from receipt for Fuel Request #{$fuelRequest->request_no}"
                );
            }

            // Update VehicleFuelRequest
            $fuelRequest->update([
                'receipt_photo'       => $receiptPhoto ?: $fuelRequest->receipt_photo,
                'receipt_odometer_km' => $receiptOdometerKm ?: $fuelRequest->receipt_odometer_km,
                'actual_liters'       => $actualLiters ?: ($fuelRequest->actual_liters ?: $fuelRequest->liters_requested),
                'actual_amount'       => $actualAmount ?: ($fuelRequest->actual_amount ?: $fuelRequest->estimated_amount),
                'fuel_station_name'   => $stationName ?: $fuelRequest->fuel_station_name,
                'fuel_station_tin'    => $stationTin ?: $fuelRequest->fuel_station_tin,
                'receipt_uploaded_at' => now(),
                'status'              => VehicleFuelRequest::STATUS_CLOSED,
                'closed_at'           => now(),
                'closed_by'           => $userId,
            ]);

            // Sync to ExpenseRequest for Expense Hub / VAT compliance
            if ($fuelRequest->expenseRequest) {
                $notes = "Fuel Station: " . ($stationName ?: 'N/A') . ($stationTin ? " | TIN: {$stationTin}" : "");
                $fuelRequest->expenseRequest->update([
                    'attachment'    => $receiptPhoto ?: $fuelRequest->expenseRequest->attachment,
                    'payment_notes' => trim(($fuelRequest->expenseRequest->payment_notes ? $fuelRequest->expenseRequest->payment_notes . "\n" : "") . $notes),
                ]);
            }

            return $fuelRequest;
        });
    }

    /**
     * Compute fuel consumption metrics and identify anomalies for a vehicle unit.
     */
    public function getVehicleConsumptionMetrics(int $unitId): array
    {
        $requests = VehicleFuelRequest::where('fixed_asset_unit_id', $unitId)
            ->whereNotNull('receipt_odometer_km')
            ->whereNotNull('actual_liters')
            ->where('status', VehicleFuelRequest::STATUS_CLOSED)
            ->orderBy('id', 'asc')
            ->get();

        $monthlyCost = (float) VehicleFuelRequest::where('fixed_asset_unit_id', $unitId)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->whereIn('status', [VehicleFuelRequest::STATUS_PAID, VehicleFuelRequest::STATUS_CLOSED])
            ->sum(DB::raw('COALESCE(actual_amount, estimated_amount)'));

        if ($requests->isEmpty()) {
            return [
                'has_data'           => false,
                'total_trips'        => 0,
                'avg_km_per_liter'   => null,
                'avg_cost_per_km'    => null,
                'monthly_fuel_cost'  => $monthlyCost,
                'is_efficiency_flag' => false,
                'efficiency_drop'    => 0,
            ];
        }

        $kmPerLiterList = [];
        $costPerKmList  = [];

        foreach ($requests as $req) {
            $kpl = $req->km_per_liter;
            $cpk = $req->cost_per_km;
            if ($kpl && $kpl > 0) $kmPerLiterList[] = $kpl;
            if ($cpk && $cpk > 0) $costPerKmList[]  = $cpk;
        }

        $avgKmPerLiter = !empty($kmPerLiterList) ? round(array_sum($kmPerLiterList) / count($kmPerLiterList), 2) : null;
        $avgCostPerKm  = !empty($costPerKmList)  ? round(array_sum($costPerKmList) / count($costPerKmList), 2) : null;

        // Check if the most recent trip's KM/L is > 25% worse than historical average
        $isEfficiencyFlag = false;
        $efficiencyDrop   = 0;
        $latest = $requests->last();
        if ($latest && $latest->km_per_liter && $avgKmPerLiter && $avgKmPerLiter > 0) {
            if ($latest->km_per_liter < ($avgKmPerLiter * 0.75)) {
                $isEfficiencyFlag = true;
                $efficiencyDrop = round((($avgKmPerLiter - $latest->km_per_liter) / $avgKmPerLiter) * 100);
            }
        }

        return [
            'has_data'           => true,
            'total_trips'        => count($requests),
            'avg_km_per_liter'   => $avgKmPerLiter,
            'avg_cost_per_km'    => $avgCostPerKm,
            'monthly_fuel_cost'  => $monthlyCost,
            'latest_km_per_liter'=> $latest?->km_per_liter,
            'is_efficiency_flag' => $isEfficiencyFlag,
            'efficiency_drop'    => $efficiencyDrop,
        ];
    }
}
