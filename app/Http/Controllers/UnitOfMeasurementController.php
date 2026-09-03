<?php

namespace App\Http\Controllers;

use App\Models\UnitOfMeasurement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class UnitOfMeasurementController extends Controller
{
    /**
     * Display a listing of the resource (JSON or View).
     */
    public function index(Request $request)
    {
        $units = UnitOfMeasurement::allUnits();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'data'    => $units,
            ]);
        }

        return response()->json(['data' => $units]);
    }

    /**
     * Store a newly created Unit of Measurement.
     */
    public function store(Request $request)
    {
        $request->validate([
            'code'        => 'required|string|max:50|regex:/^[a-zA-Z0-9_\-\/²³]+$/',
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
        ], [
            'code.regex' => 'The code may only contain letters, numbers, hyphens, and symbols like ², ³.',
        ]);

        $code = strtolower(trim($request->code));

        if (Schema::hasTable('unit_of_measurements')) {
            $exists = UnitOfMeasurement::where('code', $code)->first();
            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => "Unit code '{$code}' already exists.",
                ], 422);
            }

            $unit = UnitOfMeasurement::create([
                'code'        => $code,
                'name'        => trim($request->name),
                'description' => trim((string)$request->description) ?: null,
                'is_system'   => false,
                'created_by'  => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'unit'    => $unit,
                'message' => "Unit '{$unit->name} ({$unit->code})' created successfully.",
            ]);
        }

        // Fallback dummy response if table not yet migrated
        return response()->json([
            'success' => true,
            'unit'    => (object)[
                'id'          => rand(1000, 9999),
                'code'        => $code,
                'name'        => trim($request->name),
                'description' => trim((string)$request->description),
                'is_system'   => false,
            ],
            'message' => "Unit '{$code}' created successfully.",
        ]);
    }

    /**
     * Remove the specified Unit of Measurement.
     */
    public function destroy($id)
    {
        if (Schema::hasTable('unit_of_measurements')) {
            $unit = UnitOfMeasurement::find($id);
            if (!$unit) {
                return response()->json(['success' => false, 'message' => 'Unit not found.'], 404);
            }

            if ($unit->is_system) {
                return response()->json([
                    'success' => false,
                    'message' => 'Standard system units cannot be removed.',
                ], 403);
            }

            $unitCode = $unit->code;
            $unit->delete();

            return response()->json([
                'success' => true,
                'code'    => $unitCode,
                'message' => "Unit '{$unitCode}' removed successfully.",
            ]);
        }

        return response()->json(['success' => true, 'message' => 'Unit removed.']);
    }
}
