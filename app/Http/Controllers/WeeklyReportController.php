<?php

namespace App\Http\Controllers;

use App\Models\WeeklyReport;
use Illuminate\Http\Request;

class WeeklyReportController extends Controller
{
    public function index()
    {
        $query = WeeklyReport::with(['project', 'createdBy'])->latest();

        /** @var \App\Models\User|null $user */
        $user = auth()->user();
        if ($user && $user->hasRole('site_engineer') && !$user->hasAnyRole(['admin', 'global_admin', 'gm', 'planning_manager', 'planning', 'coordinator'])) {
            $assignedProjectIds = $user->projects()->pluck('projects.id');
            if ($user->store && $user->store->project_id) {
                $assignedProjectIds->push($user->store->project_id);
            }
            if ($user->employee && $user->employee->project_id) {
                $assignedProjectIds->push($user->employee->project_id);
            }
            $assignedProjectIds = $assignedProjectIds->filter()->unique();

            if ($assignedProjectIds->isNotEmpty()) {
                $query->whereIn('project_id', $assignedProjectIds);
            }
        }

        $reports = $query->get();
        return view('operational.weekly-reports.index', compact('reports'));
    }

    public function create()
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();
        $assignedProjectIds = collect();

        if ($user && $user->hasRole('site_engineer') && !$user->hasAnyRole(['admin', 'global_admin', 'gm', 'planning_manager', 'planning', 'coordinator'])) {
            $assignedProjectIds = $user->projects()->pluck('projects.id');
            if ($user->store && $user->store->project_id) {
                $assignedProjectIds->push($user->store->project_id);
            }
            if ($user->employee && $user->employee->project_id) {
                $assignedProjectIds->push($user->employee->project_id);
            }
            $assignedProjectIds = $assignedProjectIds->filter()->unique();
        }

        $query = \App\Models\Project::query();
        if ($assignedProjectIds->isNotEmpty()) {
            $query->whereIn('id', $assignedProjectIds);
        }

        // Exclude cancelled projects
        $projects = (clone $query)->whereNotIn('status', ['cancelled', 'Cancelled'])->orderBy('name')->get();

        // Fallback 1: If user has assigned project filter but no projects returned, get all non-cancelled projects
        if ($projects->isEmpty()) {
            $projects = \App\Models\Project::whereNotIn('status', ['cancelled', 'Cancelled'])->orderBy('name')->get();
        }

        // Fallback 2: If still empty, return all projects
        if ($projects->isEmpty()) {
            $projects = \App\Models\Project::orderBy('name')->get();
        }

        return view('operational.weekly-reports.create', compact('projects'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'project_id'               => 'required|exists:projects,id',
            'week_start'               => 'required|date',
            'week_end'                 => 'required|date|after_or_equal:week_start',
            'executive_summary'        => 'nullable|string',
            'planned_progress_percent' => 'nullable|numeric|min:0|max:100',
            'actual_progress_percent'  => 'nullable|numeric|min:0|max:100',
            'critical_issues'          => 'nullable|string',
            'next_week_plan'           => 'nullable|string',
        ]);

        WeeklyReport::create([
            'project_id'               => $request->project_id,
            'week_start'               => $request->week_start,
            'week_end'                 => $request->week_end,
            'executive_summary'        => $request->executive_summary,
            'planned_progress_percent' => $request->planned_progress_percent ?? 0,
            'actual_progress_percent'  => $request->actual_progress_percent ?? 0,
            'critical_issues'          => $request->critical_issues,
            'next_week_plan'           => $request->next_week_plan,
            'status'                   => 'submitted',
            'created_by'               => auth()->id(),
        ]);

        return redirect()->route('weekly-reports.index')->with('success', 'Weekly progress report created successfully.');
    }

    public function show(WeeklyReport $weeklyReport)
    {
        $weeklyReport->load(['project', 'createdBy']);
        return view('operational.weekly-reports.show', compact('weeklyReport'));
    }
}
