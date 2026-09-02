<?php

namespace App\Http\Controllers;

use App\Models\ManpowerDailyReport;
use App\Models\Project;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ManpowerDailyReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    // ─── Site Engineer: Show today's report form ─────────────────────────────
    public function create(Request $request)
    {
        $user = Auth::user();

        // Get assigned projects
        $assignedProjectIds = collect();
        if ($user->projects()->exists()) {
            $assignedProjectIds = $assignedProjectIds->concat($user->projects()->pluck('projects.id'));
        }
        if ($user->employee && $user->employee->project_id) {
            $assignedProjectIds->push($user->employee->project_id);
        }
        if ($user->store && $user->store->project_id) {
            $assignedProjectIds->push($user->store->project_id);
        }
        try {
            if (Schema::hasTable('project_user')) {
                $pu = DB::table('project_user')->where('user_id', $user->id)->pluck('project_id');
                $assignedProjectIds = $assignedProjectIds->concat($pu);
            }
        } catch (\Throwable $e) {}
        $assignedProjectIds = $assignedProjectIds->filter()->unique();

        $projects = Project::where('status', '!=', 'cancelled')
            ->when($assignedProjectIds->isNotEmpty() && !$user->hasAnyRole(['admin', 'global_admin']), fn($q) => $q->whereIn('id', $assignedProjectIds))
            ->get();

        if ($projects->isEmpty()) {
            $projects = Project::where('status', '!=', 'cancelled')->get();
        }

        // Auto-select project
        $selectedProjectId = $request->query('project_id') ?? $assignedProjectIds->first() ?? $projects->first()?->id;

        // Check if report already submitted today
        $todayReport = ManpowerDailyReport::where('submitted_by', $user->id)
            ->where('report_date', today())
            ->where('project_id', $selectedProjectId)
            ->first();

        // Recent reports for this engineer
        $recentReports = ManpowerDailyReport::where('submitted_by', $user->id)
            ->with('project')
            ->orderByDesc('report_date')
            ->take(10)
            ->get();

        return view('site_engineer.manpower_report.create', compact(
            'projects', 'selectedProjectId', 'todayReport', 'recentReports'
        ));
    }

    // ─── Site Engineer: Submit morning report ─────────────────────────────────
    public function store(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'project_id'             => 'required|exists:projects,id',
            'report_date'            => 'required|date|before_or_equal:today',
            'skilled_workers'        => 'required|integer|min:0',
            'unskilled_workers'      => 'required|integer|min:0',
            'supervisors'            => 'required|integer|min:0',
            'engineers'              => 'required|integer|min:0',
            'operators'              => 'required|integer|min:0',
            'daily_laborers'         => 'required|integer|min:0',
            'subcontractor_workers'  => 'required|integer|min:0',
            'total_absent'           => 'nullable|integer|min:0',
            'work_area'              => 'nullable|string|max:255',
            'planned_activities'     => 'nullable|string',
            'completed_activities'   => 'nullable|string',
            'challenges'             => 'nullable|string',
            'notes'                  => 'nullable|string',
        ]);

        // Check duplicate
        $existing = ManpowerDailyReport::where('submitted_by', $user->id)
            ->where('report_date', $validated['report_date'])
            ->where('project_id', $validated['project_id'])
            ->first();

        if ($existing) {
            return back()->with('error', "You already submitted a manpower report for {$validated['report_date']}. You can only submit once per day per project.");
        }

        $validated['submitted_by'] = $user->id;
        $validated['status']        = 'pending';
        $validated['total_absent']  = $validated['total_absent'] ?? 0;

        ManpowerDailyReport::create($validated);

        return redirect()->route('manpower-daily-report.create')
            ->with('success', 'Morning Manpower Report submitted successfully and sent to Planning Manager for review.');
    }

    // ─── Site Engineer: My history ────────────────────────────────────────────
    public function index(Request $request)
    {
        $user = Auth::user();
        $isPlanningManager = $user->hasAnyRole(['planning_manager', 'planning', 'admin', 'global_admin']);

        if ($isPlanningManager) {
            return $this->planningIndex($request);
        }

        $reports = ManpowerDailyReport::where('submitted_by', $user->id)
            ->with(['project'])
            ->orderByDesc('report_date')
            ->paginate(20);

        return view('site_engineer.manpower_report.index', compact('reports'));
    }

    public function show(ManpowerDailyReport $manpowerDailyReport)
    {
        $manpowerDailyReport->load(['project', 'submittedBy', 'reviewer']);
        return view('site_engineer.manpower_report.show', compact('manpowerDailyReport'));
    }

    // ─── Planning Manager: Review Queue ───────────────────────────────────────
    public function planningIndex(Request $request)
    {
        $query = ManpowerDailyReport::with(['project', 'submittedBy', 'reviewer'])
            ->orderByDesc('report_date');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            $query->where('status', 'pending');
        }

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        if ($request->filled('date')) {
            $query->where('report_date', $request->date);
        }

        $reports = $query->paginate(20)->withQueryString();
        $projects = Project::where('status', '!=', 'cancelled')->get();

        $pendingCount = ManpowerDailyReport::where('status', 'pending')->count();
        $todayCount   = ManpowerDailyReport::whereDate('report_date', today())->count();

        return view('planning_manager.manpower_reports.index', compact(
            'reports', 'projects', 'pendingCount', 'todayCount'
        ));
    }

    // ─── Planning Manager: Approve / Reject ──────────────────────────────────
    public function review(Request $request, ManpowerDailyReport $manpowerDailyReport)
    {
        $user = Auth::user();

        if (!$user->hasAnyRole(['planning_manager', 'planning', 'admin', 'global_admin'])) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'action'       => 'required|in:approve,reject',
            'review_notes' => 'nullable|string|max:1000',
        ]);

        if ($validated['action'] === 'approve') {
            $request->validate(['review_notes' => 'nullable|string']);
        } else {
            $request->validate(['review_notes' => 'nullable|string']);
        }

        $manpowerDailyReport->update([
            'status'       => $validated['action'] === 'approve' ? 'approved' : 'rejected',
            'reviewed_by'  => $user->id,
            'reviewed_at'  => now(),
            'review_notes' => $validated['review_notes'] ?? null,
        ]);

        $label = $validated['action'] === 'approve' ? 'approved' : 'rejected';
        return back()->with('success', "Manpower Daily Report for {$manpowerDailyReport->report_date->format('M d, Y')} has been {$label}.");
    }
}
