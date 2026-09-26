<?php

namespace App\Http\Controllers;

use App\Models\ManpowerDailyReport;
use App\Models\ManpowerRole;
use App\Models\Project;
use App\Models\Store;
use App\Models\SubconAgreement;
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

    private function ensureSchema(): void
    {
        try {
            if (Schema::hasTable('manpower_daily_reports') && !Schema::hasColumn('manpower_daily_reports', 'subcontractors_breakdown')) {
                Schema::table('manpower_daily_reports', function (\Illuminate\Database\Schema\Blueprint $table) {
                    $table->json('subcontractors_breakdown')->nullable()->after('roles_breakdown');
                });
            }
        } catch (\Throwable $e) {}
    }

    // ─── Site Engineer: Show today's report form ─────────────────────────────
    public function create(Request $request)
    {
        $this->ensureSchema();
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

        $isPrivileged = $user->hasAnyRole(['admin', 'global_admin', 'planning_manager', 'planning', 'technical_manager', 'gm']);

        if ($isPrivileged || $assignedProjectIds->isEmpty()) {
            $projects = Project::where('status', '!=', 'cancelled')->orderBy('name')->get();
        } else {
            $projects = Project::whereIn('id', $assignedProjectIds)->where('status', '!=', 'cancelled')->orderBy('name')->get();
        }

        // Auto-select project
        $selectedProjectId = $request->query('project_id') ?? $assignedProjectIds->first() ?? $projects->first()?->id;

        // Check if report already submitted today
        $todayReport = ManpowerDailyReport::where('submitted_by', $user->id)
            ->where('report_date', today())
            ->where('project_id', $selectedProjectId)
            ->first();

        // Available Manpower Roles / Designations for dynamic selection
        $manpowerRoles = ManpowerRole::orderBy('name')->get();

        // Active Subcontractor Agreements assigned ONLY to THIS selected site/project
        $subconAgreements = SubconAgreement::with(['supplier'])
            ->where('project_id', $selectedProjectId)
            ->whereNotIn('status', ['rejected', 'cancelled', 'terminated'])
            ->get()
            ->map(function ($sa) {
                return [
                    'id'                  => $sa->id,
                    'project_id'          => $sa->project_id,
                    'agreement_no'        => $sa->agreement_no ?? ('SUB-' . $sa->id),
                    'subcontractor_name'  => $sa->subcontractor_display_name,
                    'trade'               => $sa->description_display ?: ($sa->service_type ?? 'Subcontract Work'),
                    'supplier_phone'      => $sa->supplier?->phone ?? '',
                ];
            });

        // Recent reports for this engineer
        $recentReports = ManpowerDailyReport::where('submitted_by', $user->id)
            ->with('project')
            ->orderByDesc('report_date')
            ->take(10)
            ->get();

        return view('site_engineer.manpower_report.create', compact(
            'projects', 'selectedProjectId', 'todayReport', 'recentReports', 'manpowerRoles', 'subconAgreements'
        ));
    }

    // ─── Site Engineer: Submit morning report ─────────────────────────────────
    public function store(Request $request)
    {
        $this->ensureSchema();
        $user = Auth::user();

        $validated = $request->validate([
            'project_id'                          => 'required|exists:projects,id',
            'report_date'                         => 'required|date|before_or_equal:today',
            'roles'                               => 'nullable|array',
            'roles.*.role_id'                     => 'nullable|integer',
            'roles.*.role_name'                   => 'nullable|string|max:150',
            'roles.*.category'                    => 'nullable|string|max:100',
            'roles.*.count'                       => 'nullable|integer|min:0',
            'subcontractors'                      => 'nullable|array',
            'subcontractors.*.agreement_id'       => 'nullable|integer',
            'subcontractors.*.subcontractor_name' => 'nullable|string|max:150',
            'subcontractors.*.agreement_no'       => 'nullable|string|max:100',
            'subcontractors.*.role_name'          => 'nullable|string|max:150',
            'subcontractors.*.category'           => 'nullable|string|max:100',
            'subcontractors.*.trade'              => 'nullable|string|max:150',
            'subcontractors.*.workers_count'      => 'nullable|integer|min:0',
            'subcontractors.*.notes'              => 'nullable|string|max:255',
            'skilled_workers'                     => 'nullable|integer|min:0',
            'unskilled_workers'                   => 'nullable|integer|min:0',
            'supervisors'                         => 'nullable|integer|min:0',
            'engineers'                           => 'nullable|integer|min:0',
            'operators'                           => 'nullable|integer|min:0',
            'daily_laborers'                      => 'nullable|integer|min:0',
            'subcontractor_workers'               => 'nullable|integer|min:0',
            'total_absent'                        => 'nullable|integer|min:0',
            'work_area'                           => 'nullable|string|max:255',
            'planned_activities'                  => 'nullable|string',
            'completed_activities'                => 'nullable|string',
            'challenges'                          => 'nullable|string',
            'notes'                               => 'nullable|string',
        ]);

        // Process dynamic roles breakdown (Our Company Labour)
        $rolesInput = $request->input('roles', []);
        $filteredRoles = [];
        $totalFromRoles = 0;
        $skilled = (int)($request->input('skilled_workers', 0));
        $unskilled = (int)($request->input('unskilled_workers', 0));
        $supervisors = (int)($request->input('supervisors', 0));
        $operators = (int)($request->input('operators', 0));
        $dailyLaborers = (int)($request->input('daily_laborers', 0));
        $subcontractors = (int)($request->input('subcontractor_workers', 0));
        $engineers = (int)($request->input('engineers', 0));

        if (!empty($rolesInput) && is_array($rolesInput)) {
            $skilled = 0;
            $unskilled = 0;
            $supervisors = 0;
            $operators = 0;
            $dailyLaborers = 0;
            $subcontractors = 0;
            $engineers = 0;

            foreach ($rolesInput as $item) {
                $roleName = trim($item['role_name'] ?? '');
                $count = (int)($item['count'] ?? 0);
                $category = trim($item['category'] ?? 'Skilled Labor');

                if ($count > 0 && !empty($roleName)) {
                    $filteredRoles[] = [
                        'role_id'   => !empty($item['role_id']) ? (int)$item['role_id'] : null,
                        'role_name' => $roleName,
                        'category'  => $category,
                        'count'     => $count,
                    ];
                    $totalFromRoles += $count;

                    // Automatically categorize into legacy summary categories for charts/dashboards
                    $catLower = strtolower($category);
                    $nameLower = strtolower($roleName);

                    if (str_contains($catLower, 'supervis') || str_contains($nameLower, 'supervis') || str_contains($nameLower, 'foreman')) {
                        $supervisors += $count;
                    } elseif (str_contains($catLower, 'operator') || str_contains($nameLower, 'operator') || str_contains($nameLower, 'driver')) {
                        $operators += $count;
                    } elseif (str_contains($catLower, 'unskill') || str_contains($nameLower, 'unskill') || str_contains($nameLower, 'helper')) {
                        $unskilled += $count;
                    } elseif (str_contains($catLower, 'labor') || str_contains($nameLower, 'daily') || str_contains($nameLower, 'laborer')) {
                        $dailyLaborers += $count;
                    } elseif (str_contains($catLower, 'subcon') || str_contains($nameLower, 'subcon')) {
                        $subcontractors += $count;
                    } elseif (str_contains($nameLower, 'engineer') || str_contains($nameLower, 'surveyor')) {
                        $engineers += $count;
                    } else {
                        $skilled += $count;
                    }
                }
            }
        }

        // Process Subcontractors Breakdown
        $subconsInput = $request->input('subcontractors', []);
        $filteredSubcons = [];
        $totalFromSubcon = 0;

        if (!empty($subconsInput) && is_array($subconsInput)) {
            foreach ($subconsInput as $item) {
                $subName = trim($item['subcontractor_name'] ?? '');
                $agreementNo = trim($item['agreement_no'] ?? '');
                $agreementId = !empty($item['agreement_id']) ? (int)$item['agreement_id'] : null;
                $trade = trim($item['trade'] ?? '');
                $notes = trim($item['notes'] ?? '');

                // Check nested roles under this subcontractor
                $rolesInput = $item['roles'] ?? [];
                $filteredRolesForSubcon = [];
                $subTotalWorkers = 0;

                if (!empty($rolesInput) && is_array($rolesInput)) {
                    foreach ($rolesInput as $r) {
                        $roleName = trim($r['role_name'] ?? '');
                        $count = (int)($r['count'] ?? 0);
                        $category = trim($r['category'] ?? 'Skilled Labor');

                        if ($count > 0 && !empty($roleName)) {
                            $filteredRolesForSubcon[] = [
                                'role_name' => $roleName,
                                'category'  => $category,
                                'count'     => $count,
                            ];
                            $subTotalWorkers += $count;
                        }
                    }
                }

                // Fallback for flat structure if roles array was empty or flat count provided
                $flatCount = (int)($item['workers_count'] ?? 0);
                if ($subTotalWorkers === 0 && $flatCount > 0) {
                    $roleName = trim($item['role_name'] ?? ($trade ?: 'Subcontract Work'));
                    $category = trim($item['category'] ?? 'Skilled Labor');
                    $filteredRolesForSubcon[] = [
                        'role_name' => $roleName,
                        'category'  => $category,
                        'count'     => $flatCount,
                    ];
                    $subTotalWorkers = $flatCount;
                }

                if ($subTotalWorkers > 0 && !empty($subName)) {
                    $firstRole = $filteredRolesForSubcon[0] ?? null;
                    $filteredSubcons[] = [
                        'agreement_id'       => $agreementId,
                        'subcontractor_name' => $subName,
                        'agreement_no'       => $agreementNo,
                        'trade'              => $trade ?: ($firstRole['role_name'] ?? 'Subcontract Work'),
                        'role_name'          => $firstRole['role_name'] ?? ($trade ?: 'Subcontract Work'),
                        'category'           => $firstRole['category'] ?? 'Subcontractor',
                        'workers_count'      => $subTotalWorkers,
                        'roles'              => $filteredRolesForSubcon,
                        'notes'              => $notes,
                    ];
                    $totalFromSubcon += $subTotalWorkers;
                }
            }
        }

        // Check duplicate
        $existing = ManpowerDailyReport::where('submitted_by', $user->id)
            ->where('report_date', $validated['report_date'])
            ->where('project_id', $validated['project_id'])
            ->first();

        if ($existing) {
            return back()->with('error', "You already submitted a manpower report for {$validated['report_date']}. You can only submit once per day per project.");
        }

        $finalSubconWorkers = !empty($filteredSubcons) ? $totalFromSubcon : $subcontractors;
        $totalCompany = !empty($filteredRoles) ? $totalFromRoles : ($skilled + $unskilled + $supervisors + $operators + $dailyLaborers + $engineers);

        $validated['submitted_by']            = $user->id;
        $validated['status']                  = 'pending';
        $validated['total_absent']            = $validated['total_absent'] ?? 0;
        $validated['roles_breakdown']         = !empty($filteredRoles) ? $filteredRoles : null;
        $validated['subcontractors_breakdown']= !empty($filteredSubcons) ? $filteredSubcons : null;
        $validated['skilled_workers']         = $skilled;
        $validated['unskilled_workers']       = $unskilled;
        $validated['supervisors']             = $supervisors;
        $validated['operators']               = $operators;
        $validated['daily_laborers']          = $dailyLaborers;
        $validated['subcontractor_workers']   = $finalSubconWorkers;
        $validated['engineers']               = $engineers;
        $validated['total_present']           = $totalCompany + $finalSubconWorkers;

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
