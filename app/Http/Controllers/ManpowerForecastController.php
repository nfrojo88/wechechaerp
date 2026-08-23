<?php

namespace App\Http\Controllers;

use App\Models\ManpowerForecast;
use App\Models\ManpowerAssignment;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Designation;
use App\Models\ResourceAvailability;
use App\Models\EmployeeSkill;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ManpowerForecastController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display manpower forecast dashboard
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', ManpowerForecast::class);

        // Calculate next week range (Monday to Sunday)
        $nextWeekStart = Carbon::now()->addWeek()->startOfWeek();
        $nextWeekEnd   = Carbon::now()->addWeek()->endOfWeek();

        // 1. Fetch Approved ERP Plans & Next Week Manpower Breakdown
        $erpPlans = \App\Models\ErpPlanHeader::with([
            'project',
            'approver',
            'tasks' => function($tq) {
                $tq->with(['resources' => function($rq) {
                    $rq->whereIn('resource_type', ['manpower', 'regular_manpower', 'scientific_manpower'])
                       ->orWhere('resource_type', 'like', '%manpower%');
                }]);
            }
        ])
        ->where(function($q) {
            $q->where('status', 'approved')
              ->orWhereNotNull('approved_at')
              ->orWhereNotNull('approved_by');
        })
        ->get();

        if ($erpPlans->isEmpty()) {
            $erpPlans = \App\Models\ErpPlanHeader::with([
                'project',
                'approver',
                'tasks' => function($tq) {
                    $tq->with(['resources' => function($rq) {
                        $rq->whereIn('resource_type', ['manpower', 'regular_manpower', 'scientific_manpower'])
                           ->orWhere('resource_type', 'like', '%manpower%');
                    }]);
                }
            ])->latest()->get();
        }

        $projectManpowerForecasts = [];
        $totalErpNextWeekHeadcount = 0;
        $totalErpNextWeekCost = 0;

        foreach ($erpPlans as $plan) {
            $project = $plan->project;
            if (!$project) continue;

            $projId = $project->id;
            if (!isset($projectManpowerForecasts[$projId])) {
                $projectManpowerForecasts[$projId] = [
                    'project'         => $project,
                    'plan'            => $plan,
                    'plan_title'      => $plan->plan_title ?? $plan->name ?? ("ERP Plan #" . $plan->id),
                    'approved_by'     => $plan->approver->name ?? 'Planning Manager',
                    'approved_at'     => $plan->approved_at,
                    'tasks'           => [],
                    'manpower_roles'  => [],
                    'total_headcount' => 0,
                    'total_cost'      => 0,
                ];
            }

            foreach ($plan->tasks as $task) {
                $taskManpowerList = [];
                foreach ($task->resources as $res) {
                    $roleName = trim($res->resource_name);
                    $qty = (float)$res->quantity;
                    $cost = (float)$res->total_cost ?: ($qty * (float)$res->rate);

                    if ($qty > 0) {
                        if (!isset($projectManpowerForecasts[$projId]['manpower_roles'][$roleName])) {
                            $projectManpowerForecasts[$projId]['manpower_roles'][$roleName] = [
                                'count' => 0,
                                'unit'  => $res->unit ?: 'workers',
                                'cost'  => 0,
                            ];
                        }
                        $projectManpowerForecasts[$projId]['manpower_roles'][$roleName]['count'] += $qty;
                        $projectManpowerForecasts[$projId]['manpower_roles'][$roleName]['cost'] += $cost;

                        $projectManpowerForecasts[$projId]['total_headcount'] += $qty;
                        $projectManpowerForecasts[$projId]['total_cost'] += $cost;

                        $totalErpNextWeekHeadcount += $qty;
                        $totalErpNextWeekCost += $cost;

                        $taskManpowerList[] = "{$roleName} ({$qty} {$res->unit})";
                    }
                }

                if (!empty($taskManpowerList)) {
                    $projectManpowerForecasts[$projId]['tasks'][] = [
                        'name'             => $task->task_name ?? $task->name ?? ("Task #" . $task->id),
                        'wbs'              => $task->wbs_code ?? '-',
                        'start_date'       => $task->start_date,
                        'end_date'         => $task->end_date,
                        'manpower_summary' => implode(', ', $taskManpowerList),
                    ];
                }
            }
        }

        // 2. Custom Manpower Forecasts Query
        $query = ManpowerForecast::with(['project', 'designation', 'assignments']);

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        if ($request->filled('week_starting')) {
            $query->whereDate('week_starting', $request->week_starting);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $forecasts = $query->orderBy('week_starting', 'desc')->paginate(15);

        $projects = Project::where(function($q) {
            $q->where('status', 'active')->orWhere('status', 'planning');
        })->orderBy('name')->get();

        $designations = Designation::all();

        $stats = [
            'total_forecasts' => ManpowerForecast::count(),
            'pending_approval' => ManpowerForecast::where('status', 'submitted')->count(),
            'this_week' => ManpowerForecast::whereDate('week_starting', Carbon::now()->startOfWeek())->count(),
            'total_headcount_forecast' => ManpowerForecast::sum('forecasted_headcount'),
            'erp_next_week_headcount' => $totalErpNextWeekHeadcount,
            'erp_next_week_projects'  => count($projectManpowerForecasts),
        ];

        return view('hr-manager.manpower-forecast.index', compact(
            'forecasts',
            'projects',
            'designations',
            'stats',
            'projectManpowerForecasts',
            'nextWeekStart',
            'nextWeekEnd',
            'totalErpNextWeekHeadcount',
            'totalErpNextWeekCost'
        ));
    }

    /**
     * Create new manpower forecast
     */
    public function create()
    {
        $this->authorize('create', ManpowerForecast::class);

        $projects = Project::where('is_active', true)->orderBy('name')->get();
        $designations = Designation::where('is_active', true)->get();

        return view('hr-manager.manpower-forecast.create', compact('projects', 'designations'));
    }

    /**
     * Store manpower forecast
     */
    public function store(Request $request)
    {
        $this->authorize('create', ManpowerForecast::class);

        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'week_starting' => 'required|date|after_or_equal:today',
            'designation_id' => 'required|exists:designations,id',
            'forecasted_headcount' => 'required|numeric|min:1|max:999',
            'forecasted_hours' => 'required|numeric|min:1|max:9999',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Ensure week_starting is a Monday
        $weekStart = Carbon::parse($validated['week_starting'])->startOfWeek();
        $validated['week_starting'] = $weekStart->toDateString();
        $validated['created_by'] = Auth::id();
        $validated['status'] = 'draft';

        $forecast = ManpowerForecast::create($validated);

        return redirect()->route('manpower-forecast.show', $forecast->id)
            ->with('success', 'Manpower forecast created successfully');
    }

    /**
     * Show forecast details
     */
    public function show(ManpowerForecast $forecast)
    {
        $this->authorize('view', $forecast);

        $forecast->load(['project', 'designation', 'assignments.employee', 'createdBy']);

        // Get available employees for assignment
        $weekEnd = $forecast->week_starting->addDays(6);
        $availableEmployees = $this->getAvailableEmployees(
            $forecast->week_starting,
            $weekEnd,
            $forecast->designation_id
        );

        return view('hr-manager.manpower-forecast.show', compact('forecast', 'availableEmployees'));
    }

    /**
     * Assign employee to forecast
     */
    public function assignEmployee(Request $request, ManpowerForecast $forecast)
    {
        $this->authorize('update', $forecast);

        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'hours_assigned' => 'required|numeric|min:1|max:168',
            'billable' => 'boolean',
            'notes' => 'nullable|string|max:500',
        ]);

        // Check if already assigned
        $exists = ManpowerAssignment::where('manpower_forecast_id', $forecast->id)
            ->where('employee_id', $validated['employee_id'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['employee_id' => 'Employee already assigned']);
        }

        ManpowerAssignment::create([
            'manpower_forecast_id' => $forecast->id,
            'employee_id' => $validated['employee_id'],
            'hours_assigned' => $validated['hours_assigned'],
            'billable' => $validated['billable'] ?? true,
            'notes' => $validated['notes'],
        ]);

        return back()->with('success', 'Employee assigned successfully');
    }

    /**
     * Remove assignment
     */
    public function removeAssignment(ManpowerAssignment $assignment)
    {
        $this->authorize('update', $assignment->forecast);

        $assignment->delete();

        return back()->with('success', 'Assignment removed');
    }

    /**
     * Submit forecast for approval
     */
    public function submit(ManpowerForecast $forecast)
    {
        $this->authorize('update', $forecast);

        if ($forecast->status !== 'draft') {
            return back()->withErrors(['status' => 'Only draft forecasts can be submitted']);
        }

        $forecast->update(['status' => 'submitted']);

        return back()->with('success', 'Forecast submitted for approval');
    }

    /**
     * Approve forecast
     */
    public function approve(ManpowerForecast $forecast)
    {
        $this->authorize('approve', $forecast);

        if ($forecast->status !== 'submitted') {
            return back()->withErrors(['status' => 'Only submitted forecasts can be approved']);
        }

        $forecast->update(['status' => 'approved']);

        return back()->with('success', 'Forecast approved');
    }

    /**
     * Reject forecast
     */
    public function reject(Request $request, ManpowerForecast $forecast)
    {
        $this->authorize('approve', $forecast);

        if ($forecast->status !== 'submitted') {
            return back()->withErrors(['status' => 'Only submitted forecasts can be rejected']);
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string|min:10|max:500',
        ]);

        $forecast->update([
            'status' => 'rejected',
            'notes' => $validated['rejection_reason'],
        ]);

        return back()->with('success', 'Forecast rejected');
    }

    /**
     * Get available employees for a period
     */
    private function getAvailableEmployees($fromDate, $toDate, $designationId = null)
    {
        $query = Employee::whereHas('availability', function ($q) use ($fromDate, $toDate) {
            $q->where('is_active', true)
              ->where('available_from', '<=', $fromDate)
              ->where('available_until', '>=', $toDate);
        });

        if ($designationId) {
            // If using designation relation, add it here based on your structure
        }

        return $query->with('skills', 'availability')
            ->orderBy('name')
            ->get();
    }

    /**
     * Get resource availability
     */
    public function getResourceAvailability(Request $request)
    {
        $employee = Employee::with('availability', 'skills', 'manpowerAssignments')->findOrFail($request->employee_id);

        return response()->json([
            'employee' => $employee,
            'current_availability' => $employee->availability()
                ->where('is_active', true)
                ->orderBy('available_from')
                ->get(),
            'skills' => $employee->skills,
            'assignments' => $employee->manpowerAssignments()
                ->with('forecast')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(),
        ]);
    }

    /**
     * Export forecast to CSV
     */
    public function exportCSV(Request $request)
    {
        $query = ManpowerForecast::with(['project', 'designation', 'assignments']);

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        $forecasts = $query->get();

        $fileName = 'manpower-forecast-' . now()->format('Y-m-d-His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"$fileName\"",
        ];

        $callback = function () use ($forecasts) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Project', 'Week Starting', 'Designation', 'Forecasted Headcount', 'Forecasted Hours', 'Assigned', 'Status']);

            foreach ($forecasts as $forecast) {
                fputcsv($file, [
                    $forecast->project->name,
                    $forecast->week_starting->format('Y-m-d'),
                    $forecast->designation->name,
                    $forecast->forecasted_headcount,
                    $forecast->forecasted_hours,
                    $forecast->assignments()->count(),
                    $forecast->status,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
