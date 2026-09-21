<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Store;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->authorizeResource(Project::class, 'project');
    }

    public function index(Request $request)
    {
        $query = Project::with('defaultStore', 'creator')->latest();

        /** @var \App\Models\User|null $user */
        $user = auth()->user();
        if ($user && $user->hasRole('site_engineer') && !$user->hasAnyRole(['admin', 'global_admin', 'gm', 'planning_manager'])) {
            $assignedProjectIds = $user->projects()->pluck('projects.id');
            if ($user->store && $user->store->project_id) {
                $assignedProjectIds->push($user->store->project_id);
            }
            $query->whereIn('id', $assignedProjectIds->unique());
        } elseif ($user && $user->hasRole('secretary') && !$user->hasAnyRole(['admin', 'global_admin', 'gm', 'planning_manager'])) {
            $query->where(function($q) {
                $q->where('name', 'like', '%Head Office%')
                  ->orWhere('name', 'like', '%HeadOffice%')
                  ->orWhere('name', 'like', '%HQ%')
                  ->orWhere('name', 'like', '%Central%')
                  ->orWhere('name', 'like', '%ዋና ቢሮ%')
                  ->orWhere('code', 'like', '%HO%');
            });
        }
        
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('client_name', 'like', "%{$search}%");
            });
        }
        
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        
        $projects = $query->paginate(15)->appends($request->query());
        return view('projects.index', compact('projects'));
    }

    public function create()
    {
        $stores = Store::where('is_active', true)->orderBy('name')->get();
        return view('projects.create', compact('stores'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'              => ['required', 'string', 'max:255'],
            'code'              => ['required', 'string', 'max:50', 'unique:projects,code'],
            'description'       => ['nullable', 'string'],
            'location'          => ['nullable', 'string', 'max:255'],
            'client_name'       => ['nullable', 'string', 'max:255'],
            'client_contact'    => ['nullable', 'string', 'max:255'],
            'status'            => ['required', 'in:planning,bidding,active,on_hold,completed,cancelled,handover'],
            'start_date'        => ['nullable', 'date'],
            'end_date'          => ['nullable', 'date', 'after_or_equal:start_date'],
            'default_store_id'  => ['nullable', 'exists:stores,id'],
        ]);

        // Contract Value → sourced from BOQ (set to 0 at creation)
        // Budget Allocated → set by GM via workflow approval (set to 0 at creation)
        $validated['contract_value']  = 0;
        $validated['budget_allocated'] = 0;
        $validated['created_by'] = auth()->id();

        $project = Project::create($validated);

        // Auto-create the store using the project name
        $storeName = $project->name;
        $storeCode = 'STR-' . ($project->code ?: strtoupper(uniqid()));

        $store = \App\Models\Store::create([
            'name' => $storeName,
            'code' => $storeCode,
            'type' => 'site',
            'is_active' => true,
            'project_id' => $project->id,
            'notes' => 'Automatically created store for project: ' . $project->name,
        ]);

        // Set as default store for project
        $project->update(['default_store_id' => $store->id]);

        return redirect()->route('projects.index')->with('success', 'Project created successfully along with ' . $storeName . '.');
    }

    public function show(Project $project)
    {
        $erp_plan = \App\Models\ErpPlanHeader::where('project_id', $project->id)->latest()->first();

        if ($erp_plan) {
            $erp_plan->load('tasks.dependencies', 'tasks.resources', 'baselines', 'project', 'creator');
            return view('erp_plans.show', compact('erp_plan'));
        }

        $project->load('defaultStore', 'stores', 'creator');
        return view('projects.show', compact('project'));
    }

    public function edit(Project $project)
    {
        $stores = Store::where('is_active', true)->orderBy('name')->get();

        // Status is always read-only in the edit view — driven by the workflow
        return view('projects.edit', compact('project', 'stores'));
    }

    public function update(Request $request, Project $project)
    {
        $validated = $request->validate([
            'name'              => ['required', 'string', 'max:255'],
            'code'              => ['required', 'string', 'max:50', 'unique:projects,code,' . $project->id],
            'description'       => ['nullable', 'string'],
            'location'          => ['nullable', 'string', 'max:255'],
            'client_name'       => ['nullable', 'string', 'max:255'],
            'client_contact'    => ['nullable', 'string', 'max:255'],
            'status'            => ['required', 'in:planning,bidding,active,on_hold,completed,cancelled,handover'],
            'start_date'        => ['nullable', 'date'],
            'end_date'          => ['nullable', 'date', 'after_or_equal:start_date'],
            'default_store_id'  => ['nullable', 'exists:stores,id'],
            // contract_value  → locked, sourced from BOQ
            // budget_allocated → locked, set by GM via workflow
        ]);

        // ── Protect auto-managed status ────────────────────────────────────
        // If there is an active (in-progress) workflow, the 'status' field is
        // owned by the workflow engine. We silently drop any manual override
        // so the workflow result stays authoritative.
        $hasActiveWorkflow = $project->planWorkflows()
            ->whereNotIn('status', ['gm_approved', 'rejected'])
            ->exists();

        if ($hasActiveWorkflow) {
            // Keep current status — workflow controls it
            unset($validated['status']);
        } elseif (
            $project->planning_phase_status === 'gm_approved' &&
            ($validated['status'] ?? '') === 'planning'
        ) {
            // Prevent manually downgrading a GM-approved project back to planning
            unset($validated['status']);
        }
        // ── End protect ───────────────────────────────────────────────────

        $project->update($validated);

        return redirect()->route('projects.index')->with('success', 'Project updated successfully.');
    }

    public function destroy(Project $project)
    {
        $project->delete();
        return redirect()->route('projects.index')->with('success', 'Project archived.');
    }
}
