<?php

namespace App\Http\Controllers;

use App\Models\DailyReport;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class DailyReportController extends Controller
{
    public function index()
    {
        $status = request('status', 'all');
        $projectId = request('project_id');
        $dateFrom = request('date_from');
        $dateTo = request('date_to');

        $query = DailyReport::with(['project', 'createdBy', 'approvedBy']);

        /** @var \App\Models\User|null $user */
        $user = auth()->user();
        if ($user && $user->hasRole('site_engineer') && !$user->hasAnyRole(['admin', 'global_admin', 'gm', 'planning_manager', 'hr_manager'])) {
            $assignedProjectIds = $user->projects()->pluck('projects.id');
            if ($user->store && $user->store->project_id) {
                $assignedProjectIds->push($user->store->project_id);
            }
            $query->whereIn('project_id', $assignedProjectIds->unique());
        }

        // Filter by status
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        // Filter by project
        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        // Filter by date range
        if ($dateFrom) {
            $query->whereDate('report_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('report_date', '<=', $dateTo);
        }

        $reports = $query->latest('report_date')->paginate(20);

        $projects = \App\Models\Project::where('status', 'active')->get();
        $statusCounts = [
            'draft' => DailyReport::where('status', 'draft')->count(),
            'submitted' => DailyReport::where('status', 'submitted')->count(),
            'approved' => DailyReport::where('status', 'approved')->count(),
        ];

        return view('operational.daily-reports.index', compact('reports', 'projects', 'statusCounts', 'status'));
    }

    /**
     * Approval dashboard for HR Officer - shows pending reports with manpower details
     */
    public function approvalDashboard(Request $request)
    {
        $status = $request->input('status', 'submitted');
        $projectId = $request->input('project_id');
        $minManpower = $request->input('min_manpower');
        $maxManpower = $request->input('max_manpower');

        $query = DailyReport::with(['project', 'items', 'createdBy'])
            ->whereHas('createdBy.roles', fn($r) => $r->where('name', 'site_engineer'));

        // Filter by status (pending approval)
        if ($status === 'all') {
            $query->whereIn('status', ['submitted', 'draft']);
        } else {
            $query->where('status', $status);
        }

        // Filter by project
        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        // Filter by manpower range
        if ($minManpower) {
            $query->where('total_manpower', '>=', $minManpower);
        }
        if ($maxManpower) {
            $query->where('total_manpower', '<=', $maxManpower);
        }

        $reports = $query->latest('report_date')->paginate(25);

        // Summary statistics
        $statistics = [
            'total_pending' => DailyReport::whereIn('status', ['submitted', 'draft'])->count(),
            'pending_submitted' => DailyReport::where('status', 'submitted')->count(),
            'pending_draft' => DailyReport::where('status', 'draft')->count(),
            'total_manpower_pending' => DailyReport::whereIn('status', ['submitted', 'draft'])
                ->sum('total_manpower'),
            'avg_manpower' => DailyReport::whereIn('status', ['submitted', 'draft'])
                ->avg('total_manpower'),
        ];

        $projects = \App\Models\Project::where('status', 'active')->get();

        return view('hr-manager.daily-reports.approval', compact('reports', 'projects', 'statistics', 'status'));
    }

    public function create()
    {
        $projects = \App\Models\Project::where('status', 'active')->get();
        return view('operational.daily-reports.create', compact('projects'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'project_id'         => 'required|exists:projects,id',
            'report_date'        => 'required|date',
            'weather_conditions' => 'nullable|string|max:255',
            'temperature'        => 'nullable|integer',
            'total_manpower'     => 'required|integer|min:0',
            'general_notes'      => 'nullable|string',
            'safety_incidents'   => 'nullable|string',
            'site_diary_remark'  => 'nullable|string',
            'site_book_pic'      => 'nullable|image|max:2048',
            'items'              => 'required|array|min:1',
            'items.*.work_description' => 'required|string',
            'items.*.qty_completed'    => 'nullable|numeric|min:0',
            'items.*.workers_count'    => 'nullable|integer|min:0',
            'items.*.equipment_used'   => 'nullable|string|max:255',
            'items.*.issues'           => 'nullable|string',
        ]);

        $picPath = null;
        if ($request->hasFile('site_book_pic')) {
            $picPath = \App\Services\FileUploadService::upload($request->file('site_book_pic'), 'daily_reports');
        }

        DB::transaction(function () use ($request, $picPath) {
            $report = DailyReport::create([
                'project_id'         => $request->project_id,
                'report_date'        => $request->report_date,
                'weather_conditions' => $request->weather_conditions,
                'temperature'        => $request->temperature,
                'total_manpower'     => $request->total_manpower,
                'general_notes'      => $request->general_notes,
                'safety_incidents'   => $request->safety_incidents,
                'site_diary_remark'  => $request->site_diary_remark,
                'site_book_pic'      => $picPath,
                'status'             => 'submitted',
                'created_by'         => auth()->id(),
            ]);

            foreach ($request->items as $item) {
                $report->items()->create([
                    'work_description' => $item['work_description'],
                    'qty_completed'    => $item['qty_completed'] ?? 0,
                    'workers_count'    => $item['workers_count'] ?? 0,
                    'equipment_used'   => $item['equipment_used'] ?? null,
                    'issues'           => $item['issues'] ?? null,
                ]);
            }
        });

        return redirect()->route('daily-reports.index')->with('success', 'Daily report created successfully.');
    }

    public function show(DailyReport $dailyReport)
    {
        $dailyReport->load(['project', 'items.scheduleTask', 'createdBy', 'approvedBy']);
        return view('operational.daily-reports.show', compact('dailyReport'));
    }

    /**
     * Approve daily report - only for HR Officer
     */
    public function approve(Request $request, DailyReport $dailyReport)
    {
        $this->authorize('hr.manage');

        $request->validate([
            'approval_notes' => 'nullable|string|max:1000',
        ]);

        DB::transaction(function () use ($request, $dailyReport) {
            $dailyReport->update([
                'status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            // Automatically create ExpenseRequest for Finance Head upon manpower approval
            if ($dailyReport->total_manpower > 0) {
                $user = auth()->user();
                $reqNo = 'EXP-MP-' . date('Ymd') . '-' . strtoupper(\Illuminate\Support\Str::random(4));
                $projectName = $dailyReport->project->project_name ?? $dailyReport->project->name ?? 'Site Project';
                $reportDateStr = $dailyReport->report_date ? $dailyReport->report_date->format('d M Y') : now()->format('d M Y');

                // Check if expense already created for this daily report to avoid duplication
                $existingExpense = \App\Models\ExpenseRequest::where('other_reason', 'like', "%Daily Report #{$dailyReport->id}%")->first();

                if (!$existingExpense) {
                    \App\Models\ExpenseRequest::create([
                        'request_number' => $reqNo,
                        'user_id'        => $user->id,
                        'employee_id'    => $user->employee->id ?? null,
                        'category'       => \App\Models\ExpenseRequest::CATEGORY_CONTRACT_WORK,
                        'other_reason'   => "Approved Manpower Expense for Site Daily Report #{$dailyReport->id} ({$projectName})",
                        'amount'         => 0.00, // Finance Head / Admin can specify rate or amount
                        'description'    => "Daily Manpower Report approved on {$reportDateStr} by HR Officer.\nProject: {$projectName}\nTotal Manpower: {$dailyReport->total_manpower} workers.\nReported By: " . ($dailyReport->createdBy->name ?? 'Site Engineer') . ".\nNotes: " . ($request->approval_notes ?? 'Approved by HR Officer'),
                        'status'         => \App\Models\ExpenseRequest::STATUS_ASSIGNED,
                        'hr_reviewer_id' => $user->id,
                        'hr_reviewed_at' => now(),
                    ]);
                }
            }
        });

        return back()->with('success', 'Daily report approved and manpower expense record sent to Finance Head successfully!');
    }

    /**
     * Reject daily report - for HR Officer
     */
    public function reject(Request $request, DailyReport $dailyReport)
    {
        $this->authorize('hr.manage');

        $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        $dailyReport->update([
            'status' => 'draft',
            'approved_by' => Auth::id(),
        ]);

        return back()->with('success', 'Daily report returned for revision.');
    }

    /**
     * Bulk approve daily reports
     */
    public function bulkApprove(Request $request)
    {
        $this->authorize('hr.manage');

        $request->validate([
            'report_ids' => 'required|array|min:1',
            'report_ids.*' => 'exists:daily_reports,id',
        ]);

        $count = DailyReport::whereIn('id', $request->report_ids)
            ->where('status', 'submitted')
            ->update([
                'status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

        return back()->with('success', "$count daily reports approved successfully.");
    }

    /**
     * Get daily report manpower statistics
     */
    public function getManpowerStats(Request $request)
    {
        $dateFrom = $request->input('date_from', now()->subDays(7)->toDateString());
        $dateTo = $request->input('date_to', now()->toDateString());
        $projectId = $request->input('project_id');

        $query = DailyReport::whereBetween('report_date', [$dateFrom, $dateTo])
            ->where('status', 'approved');

        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        $stats = [
            'total_mandays' => $query->sum('total_manpower'),
            'avg_daily_manpower' => round($query->avg('total_manpower'), 2),
            'max_daily_manpower' => $query->max('total_manpower'),
            'min_daily_manpower' => $query->min('total_manpower'),
            'total_reports' => $query->count(),
        ];

        return response()->json($stats);
    }
}
