<?php

namespace App\Http\Controllers;

use App\Models\ManpowerRole;
use Illuminate\Http\Request;

class ManpowerRoleController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:planning_manager|planning|technical_manager|site_engineer|Site Engineer|admin|global_admin');
    }

    /** Return all roles as JSON (for modals / selects) */
    public function index()
    {
        return response()->json(ManpowerRole::orderBy('name')->get());
    }

    /** Save a new role */
    public function store(Request $request)
    {
        $request->validate([
            'name'         => 'required|string|max:100',
            'default_unit' => 'nullable|string',
            'category'     => 'nullable|string|max:100',
        ]);

        $role = ManpowerRole::firstOrCreate(
            ['name' => $request->name],
            [
                'default_unit' => $request->default_unit ?? 'day',
                'category'     => $request->category ?? 'Skilled Labor',
            ]
        );

        // Sync with Designation table used across ERP
        \App\Models\Designation::firstOrCreate(
            ['title' => $request->name],
            ['is_active' => true]
        );

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'role' => $role]);
        }

        return redirect()->back()->with('success', 'New Manpower Role "' . $role->name . '" created successfully!');
    }

    /** Delete a role */
    public function destroy(ManpowerRole $manpowerRole)
    {
        $manpowerRole->delete();
        return response()->json(['success' => true]);
    }
}
