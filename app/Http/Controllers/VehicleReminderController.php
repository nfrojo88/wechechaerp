<?php

namespace App\Http\Controllers;

use App\Models\FixedAsset;
use App\Models\FixedAssetUnit;
use App\Models\VehicleReminder;
use App\Models\VehicleReminderHistory;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class VehicleReminderController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    // ── Dashboard ─────────────────────────────────────────────────────────────

    public function dashboard()
    {
        $stats = $this->getDashboardStats();
        $urgentItems = $this->getUrgentItems(10);
        return view('general-service.vehicle-reminders.dashboard', compact('stats', 'urgentItems'));
    }

    // ── Index / List ──────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $query = VehicleReminder::with(['assetUnit', 'fixedAsset', 'creator'])
            ->whereNull('deleted_at');

        // Filters
        if ($request->filled('vehicle_id')) {
            $query->where('fixed_asset_unit_id', $request->vehicle_id);
        }
        if ($request->filled('reminder_type')) {
            $query->where('reminder_type', $request->reminder_type);
        }
        if ($request->filled('status')) {
            $status = $request->status;
            if ($status === 'expired') {
                $today = Carbon::today();
                $query->where(function ($q) use ($today) {
                    $q->where(function ($q) use ($today) {
                        $q->where('reminder_type', VehicleReminder::TYPE_BOLO)->where('bolo_expiry_date', '<', $today);
                    })->orWhere(function ($q) use ($today) {
                        $q->whereIn('reminder_type', [VehicleReminder::TYPE_THIRD_PARTY_INS, VehicleReminder::TYPE_INSURANCE])
                          ->where('insurance_expiry_date', '<', $today);
                    });
                });
            } elseif ($status === 'due_soon') {
                $today = Carbon::today();
                $in30 = $today->copy()->addDays(30);
                $query->where(function ($q) use ($today, $in30) {
                    $q->whereBetween('bolo_expiry_date', [$today, $in30])
                      ->orWhereBetween('insurance_expiry_date', [$today, $in30]);
                });
            } else {
                $query->where('status', $status);
            }
        }
        if ($request->filled('date_from')) {
            $query->where(function ($q) use ($request) {
                $q->where('bolo_expiry_date', '>=', $request->date_from)
                  ->orWhere('insurance_expiry_date', '>=', $request->date_from);
            });
        }
        if ($request->filled('date_to')) {
            $query->where(function ($q) use ($request) {
                $q->where('bolo_expiry_date', '<=', $request->date_to)
                  ->orWhere('insurance_expiry_date', '<=', $request->date_to);
            });
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('assetUnit', fn($q) => $q->where('plate_number', 'like', "%$search%")->orWhere('unit_code', 'like', "%$search%"))
                  ->orWhereHas('fixedAsset', fn($q) => $q->where('name', 'like', "%$search%"))
                  ->orWhere('policy_number', 'like', "%$search%")
                  ->orWhere('insurance_company', 'like', "%$search%");
            });
        }

        $reminders = $query->latest()->paginate(20)->withQueryString();

        // Vehicle options for filter
        $vehicleUnits = $this->getVehicleUnits();

        return view('general-service.vehicle-reminders.index', compact('reminders', 'vehicleUnits'));
    }

    // ── Create / Store ────────────────────────────────────────────────────────

    public function create()
    {
        $vehicleUnits = $this->getVehicleUnits();
        $reminderTypes = VehicleReminder::TYPES;
        return view('general-service.vehicle-reminders.create', compact('vehicleUnits', 'reminderTypes'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateReminderRequest($request);

        // Check for duplicate active reminder
        $existing = VehicleReminder::where('fixed_asset_unit_id', $validated['fixed_asset_unit_id'])
            ->where('reminder_type', $validated['reminder_type'])
            ->whereNotIn('status', [VehicleReminder::STATUS_RENEWED, VehicleReminder::STATUS_SERVICED])
            ->whereNull('deleted_at')
            ->first();

        if ($existing) {
            return back()->withInput()->with('error',
                'An active reminder of this type already exists for this vehicle. Please renew or update the existing record.'
            );
        }

        // Auto-calculate next_service_km
        if ($validated['reminder_type'] === VehicleReminder::TYPE_SERVICE_KM) {
            $validated['next_service_km'] = ($validated['last_service_km'] ?? 0) + ($validated['service_interval_km'] ?? 0);
        }

        // Handle file upload
        if ($request->hasFile('attachment_file')) {
            $validated['attachment'] = $request->file('attachment_file')->store('vehicle-reminders', 'public');
        }

        $validated['created_by'] = Auth::id();

        try {
            $reminder = VehicleReminder::create($validated);
            return redirect()->route('general-service.vehicle-reminders.show', $reminder)
                ->with('success', 'Vehicle reminder created successfully.');
        } catch (\Throwable $e) {
            Log::error('VehicleReminder store failed: ' . $e->getMessage());
            return back()->withInput()->with('error', 'Failed to create reminder: ' . $e->getMessage());
        }
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function show(VehicleReminder $vehicleReminder)
    {
        $vehicleReminder->load(['assetUnit', 'fixedAsset', 'creator', 'history.performer']);
        // Other reminders for the same vehicle
        $otherReminders = VehicleReminder::where('fixed_asset_unit_id', $vehicleReminder->fixed_asset_unit_id)
            ->where('id', '!=', $vehicleReminder->id)
            ->with(['assetUnit', 'fixedAsset'])
            ->get();
        return view('general-service.vehicle-reminders.show', compact('vehicleReminder', 'otherReminders'));
    }

    // ── Edit / Update ─────────────────────────────────────────────────────────

    public function edit(VehicleReminder $vehicleReminder)
    {
        $vehicleUnits = $this->getVehicleUnits();
        $reminderTypes = VehicleReminder::TYPES;
        return view('general-service.vehicle-reminders.edit', compact('vehicleReminder', 'vehicleUnits', 'reminderTypes'));
    }

    public function update(Request $request, VehicleReminder $vehicleReminder)
    {
        $validated = $this->validateReminderRequest($request, $vehicleReminder->id);

        // Auto-calculate next_service_km
        if ($validated['reminder_type'] === VehicleReminder::TYPE_SERVICE_KM) {
            $validated['next_service_km'] = ($validated['last_service_km'] ?? 0) + ($validated['service_interval_km'] ?? 0);
        }

        // Handle file upload
        if ($request->hasFile('attachment_file')) {
            $validated['attachment'] = $request->file('attachment_file')->store('vehicle-reminders', 'public');
        }

        $validated['updated_by'] = Auth::id();

        // Save history snapshot
        VehicleReminderHistory::create([
            'vehicle_reminder_id' => $vehicleReminder->id,
            'fixed_asset_unit_id' => $vehicleReminder->fixed_asset_unit_id,
            'reminder_type'       => $vehicleReminder->reminder_type,
            'action'              => 'updated',
            'snapshot'            => $vehicleReminder->toArray(),
            'notes'               => 'Record updated',
            'performed_by'        => Auth::id(),
            'performed_at'        => now(),
        ]);

        $vehicleReminder->update($validated);

        return redirect()->route('general-service.vehicle-reminders.show', $vehicleReminder)
            ->with('success', 'Reminder updated successfully.');
    }

    // ── Update Odometer ───────────────────────────────────────────────────────

    public function updateOdometer(Request $request, VehicleReminder $vehicleReminder)
    {
        $request->validate([
            'current_odometer_km' => 'required|integer|min:0',
            'notes'               => 'nullable|string|max:500',
        ]);

        $oldOdometer = $vehicleReminder->current_odometer_km;

        // Save snapshot before update
        VehicleReminderHistory::create([
            'vehicle_reminder_id' => $vehicleReminder->id,
            'fixed_asset_unit_id' => $vehicleReminder->fixed_asset_unit_id,
            'reminder_type'       => $vehicleReminder->reminder_type,
            'action'              => 'odometer_updated',
            'snapshot'            => ['old_odometer' => $oldOdometer, 'new_odometer' => $request->current_odometer_km],
            'notes'               => "Odometer updated from {$oldOdometer} to {$request->current_odometer_km} KM. " . $request->notes,
            'performed_by'        => Auth::id(),
            'performed_at'        => now(),
        ]);

        $vehicleReminder->update([
            'current_odometer_km' => $request->current_odometer_km,
            'updated_by'          => Auth::id(),
        ]);

        return back()->with('success', 'Odometer updated to ' . number_format($request->current_odometer_km) . ' KM.');
    }

    // ── Renew ─────────────────────────────────────────────────────────────────

    public function renew(Request $request, VehicleReminder $vehicleReminder)
    {
        $validated = $this->validateRenewRequest($request, $vehicleReminder);

        DB::transaction(function () use ($vehicleReminder, $validated, $request) {
            // 1. Archive the old record
            VehicleReminderHistory::create([
                'vehicle_reminder_id' => $vehicleReminder->id,
                'fixed_asset_unit_id' => $vehicleReminder->fixed_asset_unit_id,
                'reminder_type'       => $vehicleReminder->reminder_type,
                'action'              => 'renewed',
                'snapshot'            => $vehicleReminder->toArray(),
                'notes'               => $request->notes ?? 'Record renewed',
                'performed_by'        => Auth::id(),
                'performed_at'        => now(),
            ]);

            // 2. Mark old as renewed
            $vehicleReminder->update(['status' => VehicleReminder::STATUS_RENEWED, 'updated_by' => Auth::id()]);

            // 3. Create new active reminder with updated fields
            $newData = array_merge($vehicleReminder->toArray(), $validated, [
                'status'     => VehicleReminder::STATUS_ACTIVE,
                'created_by' => Auth::id(),
                'updated_by' => null,
                'id'         => null,
                'created_at' => null,
                'updated_at' => null,
                'deleted_at' => null,
            ]);

            // Handle new attachment
            if ($request->hasFile('attachment_file')) {
                $newData['attachment'] = $request->file('attachment_file')->store('vehicle-reminders', 'public');
            }

            // Recalculate next_service_km for service reminders
            if ($vehicleReminder->reminder_type === VehicleReminder::TYPE_SERVICE_KM) {
                $newData['next_service_km'] = ($newData['last_service_km'] ?? 0) + ($newData['service_interval_km'] ?? 0);
            }

            VehicleReminder::create($newData);
        });

        return redirect()->route('general-service.vehicle-reminders.index')
            ->with('success', 'Reminder renewed successfully. A new active record has been created.');
    }

    // ── Destroy / Delete ──────────────────────────────────────────────────────

    public function destroy(VehicleReminder $vehicleReminder)
    {
        try {
            VehicleReminderHistory::create([
                'vehicle_reminder_id' => $vehicleReminder->id,
                'fixed_asset_unit_id' => $vehicleReminder->fixed_asset_unit_id,
                'reminder_type'       => $vehicleReminder->reminder_type,
                'action'              => 'deleted',
                'snapshot'            => $vehicleReminder->toArray(),
                'notes'               => 'Reminder deleted',
                'performed_by'        => Auth::id(),
                'performed_at'        => now(),
            ]);

            $vehicleReminder->delete();

            return redirect()->route('general-service.vehicle-reminders.index')
                ->with('success', 'Vehicle reminder deleted successfully.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to delete reminder: ' . $e->getMessage());
        }
    }

    // ── Vehicle Detail (all reminders for one vehicle) ────────────────────────

    public function vehicleDetail(FixedAssetUnit $unit)
    {
        $unit->load('parentAsset');
        $reminders = VehicleReminder::where('fixed_asset_unit_id', $unit->id)
            ->with(['history.performer', 'creator'])
            ->latest()
            ->get();
        return view('general-service.vehicle-reminders.vehicle-detail', compact('unit', 'reminders'));
    }

    // ── Export to Excel (simple CSV) ──────────────────────────────────────────

    public function export(Request $request)
    {
        $query = VehicleReminder::with(['assetUnit', 'fixedAsset'])->whereNull('deleted_at');

        if ($request->filled('reminder_type')) {
            $query->where('reminder_type', $request->reminder_type);
        }

        $reminders = $query->latest()->get();

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="vehicle-reminders-' . date('Y-m-d') . '.csv"',
        ];

        $callback = function () use ($reminders) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Vehicle', 'Plate', 'Reminder Type', 'Status', 'Expiry/Next Service', 'Notes', 'Created At']);

            foreach ($reminders as $r) {
                $expiry = $r->getExpiryDate() ? $r->getExpiryDate()->format('Y-m-d') : ($r->next_service_km ? $r->next_service_km . ' KM' : '-');
                fputcsv($handle, [
                    $r->id,
                    $r->fixedAsset?->name ?? '-',
                    $r->assetUnit?->plate_number ?? '-',
                    $r->reminder_type_label,
                    $r->computeStatus(),
                    $expiry,
                    $r->notes,
                    $r->created_at?->format('Y-m-d'),
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ── API: Search vehicles ──────────────────────────────────────────────────

    public function searchVehicles(Request $request)
    {
        $search = $request->get('q', '');

        $units = FixedAssetUnit::with('parentAsset')
            ->whereHas('parentAsset', fn($q) =>
                $q->whereIn('category', ['Vehicles', 'Vehicle', 'Cars', 'Car', 'Vehicles/Cars'])
            )
            ->where(function ($q) use ($search) {
                $q->where('plate_number', 'like', "%$search%")
                  ->orWhere('unit_code', 'like', "%$search%")
                  ->orWhereHas('parentAsset', fn($q) => $q->where('name', 'like', "%$search%"));
            })
            ->limit(20)
            ->get();

        return response()->json($units->map(fn($u) => [
            'id'           => $u->id,
            'fixed_asset_id' => $u->fixed_asset_id,
            'unit_code'    => $u->unit_code,
            'plate_number' => $u->plate_number,
            'name'         => $u->parentAsset?->name ?? 'Unknown',
            'label'        => $u->unit_code . ' — ' . ($u->parentAsset?->name ?? '') . ($u->plate_number ? ' | Plate: ' . $u->plate_number : ''),
            'model'        => trim(($u->brand ?? '') . ' ' . ($u->model ?? '')),
        ]));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function getVehicleUnits()
    {
        return FixedAssetUnit::with('parentAsset')
            ->whereHas('parentAsset', fn($q) =>
                $q->whereIn('category', ['Vehicles', 'Vehicle', 'Cars', 'Car', 'Vehicles/Cars'])
            )
            ->orderBy('unit_code')
            ->get();
    }

    private function getDashboardStats(): array
    {
        $today = Carbon::today();
        $in30 = $today->copy()->addDays(30);

        try {
            return [
                'bolo_expiring'   => VehicleReminder::where('reminder_type', VehicleReminder::TYPE_BOLO)
                    ->whereBetween('bolo_expiry_date', [$today, $in30])->count(),
                'bolo_expired'    => VehicleReminder::where('reminder_type', VehicleReminder::TYPE_BOLO)
                    ->where('bolo_expiry_date', '<', $today)->count(),
                'services_due'    => VehicleReminder::where('reminder_type', VehicleReminder::TYPE_SERVICE_KM)
                    ->whereRaw('next_service_km - current_odometer_km <= reminder_threshold_km')->count(),
                'insurance_expiring' => VehicleReminder::whereIn('reminder_type', [VehicleReminder::TYPE_THIRD_PARTY_INS, VehicleReminder::TYPE_INSURANCE])
                    ->whereBetween('insurance_expiry_date', [$today, $in30])->count(),
                'insurance_expired' => VehicleReminder::whereIn('reminder_type', [VehicleReminder::TYPE_THIRD_PARTY_INS, VehicleReminder::TYPE_INSURANCE])
                    ->where('insurance_expiry_date', '<', $today)->count(),
                'total_active'    => VehicleReminder::whereNotIn('status', [VehicleReminder::STATUS_RENEWED, VehicleReminder::STATUS_SERVICED])->count(),
            ];
        } catch (\Throwable $e) {
            return ['bolo_expiring' => 0, 'bolo_expired' => 0, 'services_due' => 0, 'insurance_expiring' => 0, 'insurance_expired' => 0, 'total_active' => 0];
        }
    }

    private function getUrgentItems(int $limit = 10)
    {
        try {
            $today = Carbon::today();
            $in30 = $today->copy()->addDays(30);

            return VehicleReminder::with(['assetUnit', 'fixedAsset'])
                ->where(function ($q) use ($today, $in30) {
                    $q->where(function ($q) use ($today, $in30) {
                        // Date-based expiring or expired
                        $q->where('bolo_expiry_date', '<=', $in30)
                          ->orWhere('insurance_expiry_date', '<=', $in30);
                    })->orWhere(function ($q) {
                        // KM-based near or overdue
                        $q->where('reminder_type', VehicleReminder::TYPE_SERVICE_KM)
                          ->whereRaw('next_service_km IS NOT NULL AND current_odometer_km IS NOT NULL')
                          ->whereRaw('next_service_km - current_odometer_km <= COALESCE(reminder_threshold_km, 500)');
                    });
                })
                ->whereNotIn('status', [VehicleReminder::STATUS_RENEWED, VehicleReminder::STATUS_SERVICED])
                ->orderByRaw('COALESCE(bolo_expiry_date, insurance_expiry_date) ASC')
                ->limit($limit)
                ->get();
        } catch (\Throwable $e) {
            return collect();
        }
    }

    private function validateReminderRequest(Request $request, $exceptId = null): array
    {
        $type = $request->reminder_type;

        $rules = [
            'fixed_asset_unit_id' => 'required|integer|exists:fixed_asset_units,id',
            'fixed_asset_id'      => 'required|integer|exists:fixed_assets,id',
            'reminder_type'       => 'required|in:' . implode(',', array_keys(VehicleReminder::TYPES)),
            'notes'               => 'nullable|string|max:2000',
            'attachment_file'     => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
            'alert_days_before'   => 'nullable|array',
            'alert_days_before.*' => 'integer|in:30,15,7,1,0',
        ];

        if ($type === VehicleReminder::TYPE_BOLO) {
            $rules['bolo_last_date']   = 'nullable|date';
            $rules['bolo_expiry_date'] = 'required|date|after:today';
        }

        if ($type === VehicleReminder::TYPE_SERVICE_KM) {
            $rules['current_odometer_km']   = 'required|integer|min:0';
            $rules['last_service_km']       = 'required|integer|min:0';
            $rules['service_interval_km']   = 'required|integer|min:1';
            $rules['last_service_date']     = 'nullable|date';
            $rules['reminder_threshold_km'] = 'nullable|integer|min:0';
        }

        if (in_array($type, [VehicleReminder::TYPE_THIRD_PARTY_INS, VehicleReminder::TYPE_INSURANCE])) {
            $rules['insurance_company']     = 'required|string|max:255';
            $rules['policy_number']         = 'nullable|string|max:100';
            $rules['insurance_start_date']  = 'required|date';
            $rules['insurance_expiry_date'] = 'required|date|after:insurance_start_date';
            $rules['premium_amount']        = 'nullable|numeric|min:0';

            if ($type === VehicleReminder::TYPE_INSURANCE) {
                $rules['coverage_type'] = 'nullable|string|max:100';
            }
        }

        return $request->validate($rules);
    }

    private function validateRenewRequest(Request $request, VehicleReminder $reminder): array
    {
        $type = $reminder->reminder_type;
        $rules = ['notes' => 'nullable|string|max:1000', 'attachment_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240'];

        if ($type === VehicleReminder::TYPE_BOLO) {
            $rules['bolo_last_date']   = 'nullable|date';
            $rules['bolo_expiry_date'] = 'required|date|after:today';
        }
        if ($type === VehicleReminder::TYPE_SERVICE_KM) {
            $rules['last_service_km']     = 'required|integer|min:0';
            $rules['service_interval_km'] = 'required|integer|min:1';
            $rules['last_service_date']   = 'nullable|date';
            $rules['current_odometer_km'] = 'required|integer|min:0';
        }
        if (in_array($type, [VehicleReminder::TYPE_THIRD_PARTY_INS, VehicleReminder::TYPE_INSURANCE])) {
            $rules['insurance_company']     = 'required|string|max:255';
            $rules['policy_number']         = 'nullable|string|max:100';
            $rules['insurance_start_date']  = 'required|date';
            $rules['insurance_expiry_date'] = 'required|date|after:insurance_start_date';
            $rules['premium_amount']        = 'nullable|numeric|min:0';
            if ($type === VehicleReminder::TYPE_INSURANCE) {
                $rules['coverage_type'] = 'nullable|string|max:100';
            }
        }

        return $request->validate($rules);
    }
}
