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

    public function getDailyReportsAjax(Request $request)
    {
        $request->validate([
            'project_id' => 'required|integer',
            'week_start' => 'required|date',
            'week_end'   => 'required|date',
        ]);

        $reports = \App\Models\DailyReport::with(['items', 'createdBy'])
            ->where('project_id', $request->project_id)
            ->whereBetween('report_date', [$request->week_start, $request->week_end])
            ->orderBy('report_date', 'asc')
            ->get();

        $formatted = $reports->map(function ($dr) {
            return [
                'id'                 => $dr->id,
                'report_date'        => $dr->report_date ? $dr->report_date->format('Y-m-d') : null,
                'formatted_date'     => $dr->report_date ? $dr->report_date->format('D, M d, Y') : 'N/A',
                'weather_conditions' => $dr->weather_conditions,
                'temperature'        => $dr->temperature,
                'total_manpower'     => (int) $dr->total_manpower,
                'general_notes'      => $dr->general_notes,
                'safety_incidents'   => $dr->safety_incidents,
                'site_diary_remark'  => $dr->site_diary_remark,
                'site_book_pic'      => $dr->site_book_pic ? uploaded_asset($dr->site_book_pic) : null,
                'status'             => $dr->status,
                'created_by_name'    => $dr->createdBy->name ?? 'Site Engineer',
                'items'              => $dr->items->map(function ($it) {
                    return [
                        'id'               => $it->id,
                        'work_description' => $it->work_description,
                        'qty_completed'    => (float) $it->qty_completed,
                        'workers_count'    => (int) $it->workers_count,
                        'equipment_used'   => $it->equipment_used,
                        'issues'           => $it->issues,
                    ];
                }),
            ];
        });

        return response()->json([
            'success' => true,
            'count'   => $formatted->count(),
            'reports' => $formatted,
        ]);
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
            'daily_report_ids'         => 'nullable|array',
            'daily_report_ids.*'       => 'nullable|integer',
        ]);

        $dailyReportIds = $request->input('daily_report_ids', []);
        if (empty($dailyReportIds)) {
            $dailyReportIds = \App\Models\DailyReport::where('project_id', $request->project_id)
                ->whereBetween('report_date', [$request->week_start, $request->week_end])
                ->pluck('id')
                ->toArray();
        } else {
            $dailyReportIds = array_values(array_filter($dailyReportIds));
        }

        $payload = [
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
        ];

        if (\Illuminate\Support\Facades\Schema::hasColumn('weekly_reports', 'daily_report_ids')) {
            $payload['daily_report_ids'] = $dailyReportIds;
        }

        $weeklyReport = WeeklyReport::create($payload);

        return redirect()->route('weekly-reports.show', $weeklyReport)
            ->with('success', 'Weekly progress report created and submitted to Planning with attached daily reports.');
    }

    public function show(WeeklyReport $weeklyReport)
    {
        $weeklyReport->load(['project', 'createdBy']);
        $dailyReports = $weeklyReport->attached_daily_reports;
        return view('operational.weekly-reports.show', compact('weeklyReport', 'dailyReports'));
    }
}
