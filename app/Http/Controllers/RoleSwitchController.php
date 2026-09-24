<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
use App\Models\ActivityLog;

class RoleSwitchController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Switch user's active role.
     */
    public function switchRole(Request $request)
    {
        $request->validate([
            'role' => 'required|string',
        ]);

        $user = Auth::user();
        $targetRole = trim($request->role);

        // Verify the role actually exists
        $roleExists = Role::where('name', $targetRole)->exists();
        if (!$roleExists) {
            return back()->with('error', "Role '{$targetRole}' does not exist in the system.");
        }

        // Verify user has this role OR user is admin/global_admin
        $hasRole = $user->hasRole($targetRole);
        $isAdmin = $user->hasAnyRole(['admin', 'global_admin']);

        if (!$hasRole && !$isAdmin) {
            return back()->with('error', 'Access denied. You are not assigned to the role: ' . ucwords(str_replace(['_', '-'], ' ', $targetRole)));
        }

        session(['active_role' => $targetRole]);

        try {
            ActivityLog::log(
                'role_switch',
                "User {$user->name} ({$user->email}) switched active workspace role to '{$targetRole}'",
                'Auth/Role'
            );
        } catch (\Throwable $e) {}

        $roleLabel = ucwords(str_replace(['_', '-'], ' ', $targetRole));

        return redirect()->route('dashboard')->with('success', "Active workspace switched to: {$roleLabel}");
    }

    /**
     * Reset active role back to user's default role.
     */
    public function resetActiveRole()
    {
        session()->forget('active_role');
        return redirect()->route('dashboard')->with('success', 'Active workspace reset to default.');
    }
}
