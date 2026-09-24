<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Employee;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RoleAssignmentController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $user = auth()->user();
            if (!$user || !$user->roles || !$user->roles->whereIn('name', ['global_admin', 'admin'])->count()) {
                abort(403, 'Access denied. Admin only.');
            }
            return $next($request);
        });
    }

    public function index(Request $request)
    {
        // Users with NO roles assigned yet
        $unassigned = User::whereDoesntHave('roles')
            ->with('employee')
            ->latest()
            ->get();

        // All users with their roles
        $allUsers = User::with('roles', 'employee')
            ->when($request->filled('search'), function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('email', 'like', '%' . $request->search . '%');
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $roles = Role::orderBy('name')->get();

        return view('admin.roles.assign', compact('unassigned', 'allUsers', 'roles'));
    }

    public function assign(Request $request, User $user)
    {
        $submittedRoles = $request->input('roles') ?? ($request->has('role') ? (array)$request->input('role') : []);
        $submittedRoles = array_values(array_filter((array)$submittedRoles));

        if (empty($submittedRoles)) {
            return back()->with('error', 'Please select at least one role to assign.');
        }

        // Validate all role names
        $validRoleCount = Role::whereIn('name', $submittedRoles)->count();
        if ($validRoleCount !== count($submittedRoles)) {
            return back()->with('error', 'One or more selected roles are invalid.');
        }

        // Sync roles (multi-role support)
        $user->syncRoles($submittedRoles);

        \App\Models\ActivityLog::log(
            'updated',
            'Admin assigned role(s) [' . implode(', ', $submittedRoles) . '] to user ' . $user->name,
            'Admin/Roles'
        );

        return back()->with('success', 'Assigned ' . count($submittedRoles) . ' role(s) to ' . $user->name . ' successfully.');
    }

    public function removeRole(User $user)
    {
        $user->syncRoles([]);

        return back()->with('success', 'All roles removed from ' . $user->name . '.');
    }

    public function storeRole(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:50|unique:roles,name',
        ]);

        $roleName = strtolower(trim(preg_replace('/[^a-zA-Z0-9_]+/', '_', $request->name), '_'));
        Role::create(['name' => $roleName, 'guard_name' => 'web']);

        return back()->with('success', 'New role "' . $roleName . '" created successfully.');
    }

    public function destroyRole(Role $role)
    {
        if (in_array($role->name, ['admin', 'global_admin', 'gm', 'secretary'])) {
            return back()->with('error', 'Core system role "' . $role->name . '" cannot be deleted.');
        }

        $role->delete();

        return back()->with('success', 'Role deleted successfully.');
    }
}
