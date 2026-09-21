<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->authorizeResource(Store::class, 'store');
    }

    public function index(Request $request)
    {
        $query = Store::with('project', 'manager')->latest();

        /** @var \App\Models\User|null $user */
        $user = auth()->user();
        if ($user && $user->hasRole('site_engineer') && !$user->hasAnyRole(['admin', 'global_admin', 'store_manager'])) {
            if ($user->store_id) {
                $query->where('id', $user->store_id);
            } else {
                $assignedProjectIds = $user->projects()->pluck('projects.id');
                $query->whereIn('project_id', $assignedProjectIds);
            }
        } elseif ($user && $user->hasRole('secretary') && !$user->hasAnyRole(['admin', 'global_admin', 'store_manager'])) {
            $query->where(function($q) {
                $q->where('name', 'like', '%Head Office%')
                  ->orWhere('name', 'like', '%HeadOffice%')
                  ->orWhere('name', 'like', '%Main%')
                  ->orWhere('name', 'like', '%Central%')
                  ->orWhere('name', 'like', '%ዋና ቢሮ%');
            });
        }
        
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%");
            });
        }
        
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }
        
        $stores = $query->paginate(15)->appends($request->query());
        return view('stores.index', compact('stores'));
    }

    public function create()
    {
        $projects = Project::where('status', 'active')->orderBy('name')->get();
        // Load only users that have the store_keeper role
        $managers = User::where('is_active', true)
            ->role('store_keeper')
            ->orderBy('name')
            ->get();
        return view('stores.create', compact('projects', 'managers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'code'       => ['required', 'string', 'max:50', 'unique:stores,code'],
            'address'    => ['nullable', 'string'],
            'type'       => ['required', 'in:site,warehouse,yard'],
            'is_active'  => ['boolean'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'manager_id' => ['nullable', 'exists:users,id'],
            'notes'      => ['nullable', 'string'],
        ]);

        Store::create($validated);

        return redirect()->route('stores.index')->with('success', 'Store created successfully.');
    }

    public function show(Store $store)
    {
        $store->load('project', 'manager', 'inventory.product');
        return view('stores.show', compact('store'));
    }

    public function edit(Store $store)
    {
        $projects = Project::where('status', 'active')->orderBy('name')->get();
        // Load only users that have the store_keeper role
        $managers = User::where('is_active', true)
            ->role('store_keeper')
            ->orderBy('name')
            ->get();
        return view('stores.edit', compact('store', 'projects', 'managers'));
    }

    public function update(Request $request, Store $store)
    {
        $validated = $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'code'       => ['required', 'string', 'max:50', 'unique:stores,code,' . $store->id],
            'address'    => ['nullable', 'string'],
            'type'       => ['required', 'in:site,warehouse,yard'],
            'is_active'  => ['boolean'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'manager_id' => ['nullable', 'exists:users,id'],
            'notes'      => ['nullable', 'string'],
        ]);

        $store->update($validated);

        return redirect()->route('stores.index')->with('success', 'Store updated successfully.');
    }

    public function destroy(Store $store)
    {
        $store->delete();
        return redirect()->route('stores.index')->with('success', 'Store archived.');
    }
}
