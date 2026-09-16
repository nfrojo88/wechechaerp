<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->authorizeResource(User::class, 'user');
    }

    public function index(Request $request)
    {
        $query = User::with('roles')->latest();
        
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }
        
        if ($request->filled('role')) {
            $role = $request->input('role');
            $query->whereHas('roles', function($q) use ($role) {
                $q->where('name', $role);
            });
        }
        
        $users = $query->paginate(15)->appends($request->query());
        $roles = Role::all();
        return view('users.index', compact('users', 'roles'));
    }

    public function create()
    {
        $roles = Role::all();
        return view('users.create', compact('roles'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role'     => ['required', 'exists:roles,name'],
            'store_id' => ['nullable', 'exists:stores,id'],
        ]);

        // Strict Rule: Do not give login credentials to Dead File employees
        $inDeadFile = \App\Models\Employee::inDeadFile()
            ->where('email', $validated['email'])
            ->exists();

        if ($inDeadFile) {
            return back()->withErrors([
                'email' => 'Strict Rule Violation: This person is archived in the Dead File. Granting login credentials to Dead File employees is strictly prohibited.',
            ])->withInput();
        }

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => bcrypt($validated['password']),
            'store_id' => $validated['store_id'] ?? null,
            'is_active' => true,
        ]);

        $user->assignRole($validated['role']);

        return redirect()->route('users.index')->with('success', 'User created successfully.');
    }

    public function edit(User $user)
    {
        $roles = Role::all();
        return view('users.edit', compact('user', 'roles'));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'unique:users,email,' . $user->id],
            'role'     => ['required', 'exists:roles,name'],
            'store_id' => ['nullable', 'exists:stores,id'],
            'is_active'=> ['boolean'],
        ]);

        // Strict Rule: Do not give login credentials to Dead File employees
        if ($user->isInDeadFile()) {
            return back()->withErrors([
                'email' => 'Strict Rule Violation: This user is linked to an employee in the Dead File. Modifying or enabling login credentials for Dead File employees is strictly prohibited.',
            ])->withInput();
        }

        $user->update([
            'name'      => $validated['name'],
            'email'     => $validated['email'],
            'store_id'  => $validated['store_id'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        $user->syncRoles([$validated['role']]);

        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }

    public function destroy(User $user)
    {
        $user->delete();
        return redirect()->route('users.index')->with('success', 'User deactivated.');
    }
}
