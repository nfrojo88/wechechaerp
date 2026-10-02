<?php

namespace App\Http\Controllers;

use App\Models\DailyManpowerSheet;
use App\Models\DailyManpowerLine;
use App\Models\WeeklyManpowerBatch;
use App\Models\WeeklyBatchItem;
use App\Models\Worker;
use App\Models\AttendanceRawPunch;
use App\Models\DeviceAttendanceLog;
use App\Models\Project;
use App\Models\User;
use App\Services\ManpowerApprovalService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManpowerApprovalController extends Controller
{
    protected ManpowerApprovalService $approvalService;

    public function __construct(ManpowerApprovalService $approvalService)
    {
        $this->approvalService = $approvalService;
        $this->middleware('auth');
    }

    /**
     * Smart redirect or executive overview based on user role.
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        if ($user->hasRole('site_engineer') && !$user->hasAnyRole(['admin', 'global_admin', 'planning_manager', 'coordinator', 'hr', 'gm', 'finance'])) {
            return redirect()->route('manpower-approval.site-engineer.index');
        }

        if ($user->hasAnyRole(['planning_manager', 'planning', 'coordinator'])) {
            return redirect()->route('manpower-approval.review.inbox');
        }

        if ($user->hasRole('hr_officer')) {
            return redirect()->route('manpower-approval.weekly.index');
        }

        if ($user->hasAnyRole(['finance', 'finance_head'])) {
            return redirect()->route('manpower-approval.payments.index');
        }

        // Admin / GM / HR Manager Overview
        $pendingPlanning = DailyManpowerSheet::where('current_stage', ManpowerApprovalService::STAGE_PLANNING_MANAGER)->count();
        $pendingCoordinator = DailyManpowerSheet::where('current_stage', ManpowerApprovalService::STAGE_COORDINATOR)->count();
        $pendingHr = DailyManpowerSheet::where('current_stage', ManpowerApprovalService::STAGE_HR)->count();
        $readyForBatch = DailyManpowerSheet::where('status', ManpowerApprovalService::STATUS_HR_APPROVED)->whereNull('weekly_batch_id')->count();
        $pendingGm = WeeklyManpowerBatch::where('status', 'Submitted_GM')->count();
        $pendingFinance = WeeklyManpowerBatch::where('status', 'GM_Approved')->count();
        $totalPaid = WeeklyManpowerBatch::where('status', 'Paid')->sum('total_net_payable');

        $recentSheets = DailyManpowerSheet::with(['project', 'siteEngineer'])->latest()->limit(10)->get();
        $recentBatches = WeeklyManpowerBatch::with(['project', 'preparedBy'])->latest()->limit(10)->get();

        return view('manpower_approval.overview', compact(
            'pendingPlanning',
            'pendingCoordinator',
            'pendingHr',
            'readyForBatch',
            'pendingGm',
            'pendingFinance',
            'totalPaid',
            'recentSheets',
            'recentBatches'
        ));
    }

    // ══════════════════════════════════════════════════════════════════════
    // 1. SITE ENGINEER
    // ══════════════════════════════════════════════════════════════════════

    public function siteEngineerIndex(Request $request)
    {
        $user = auth()->user();
        $tab = $request->input('tab', 'my_sheets'); // 'my_sheets' or 'returned'

        $query = DailyManpowerSheet::with(['project', 'lines'])
            ->where(function ($q) use ($user) {
                if (!$user->hasAnyRole(['admin', 'global_admin'])) {
                    $q->where('site_engineer_id', $user->id);
                }
            });

        if ($tab === 'returned') {
            $query->where('status', ManpowerApprovalService::STATUS_REJECTED);
        } else {
            $query->where('status', '!=', ManpowerApprovalService::STATUS_REJECTED);
        }

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }
        if ($request->filled('date')) {
            $query->where('date', $request->date);
        }

        $sheets = $query->orderBy('date', 'desc')->paginate(15);
        $projects = Project::orderBy('name')->get();

        $returnedCount = DailyManpowerSheet::where('site_engineer_id', $user->id)
            ->where('status', ManpowerApprovalService::STATUS_REJECTED)
            ->count();

        return view('manpower_approval.site_engineer.index', compact('sheets', 'projects', 'tab', 'returnedCount'));
    }

    public function createSheet(Request $request)
    {
        $projects = Project::orderBy('name')->get();
        $workers = Worker::active()->orderBy('name')->get();
        $defaultDate = $request->input('date', today()->toDateString());
        $selectedProjectId = $request->input('project_id', $projects->first()?->id);

        return view('manpower_approval.site_engineer.create', compact('projects', 'workers', 'defaultDate', 'selectedProjectId'));
    }

    public function storeSheet(Request $request)
    {
        $request->validate([
            'project_id'         => 'required|exists:projects,id',
            'date'               => 'required|date',
            'trade'              => 'nullable|string|max:100',
            'gang_subcontractor' => 'nullable|string|max:150',
            'lines'              => 'required|array|min:1',
            'lines.*.worker_id'  => 'required|exists:workers,id',
            'lines.*.daily_rate' => 'required|numeric|min:0',
            'action'             => 'required|in:save_draft,submit',
        ]);

        $user = auth()->user();
        $dateStr = Carbon::parse($request->date)->format('Ymd');
        $sheetNumber = "MS-{$dateStr}-" . strtoupper(substr(uniqid(), -4));

        $sheet = DB::transaction(function () use ($request, $user, $sheetNumber) {
            $sheet = DailyManpowerSheet::create([
                'sheet_number'       => $sheetNumber,
                'project_id'         => $request->project_id,
                'date'               => $request->date,
                'site_engineer_id'   => $user->id,
                'trade'              => $request->trade,
                'gang_subcontractor' => $request->gang_subcontractor,
                'status'             => ManpowerApprovalService::STATUS_DRAFT,
                'current_stage'      => ManpowerApprovalService::STAGE_SITE_ENGINEER,
                'notes'              => $request->notes,
            ]);

            foreach ($request->lines as $lineData) {
                $regH = (float)($lineData['regular_hours'] ?? 8.0);
                $otH  = (float)($lineData['overtime_hours'] ?? 0);
                $rate = (float)($lineData['daily_rate'] ?? 0);
                
                // Calculate amount: daily_rate + overtime proportional (rate / 8 * ot_hours * 1.5 or 1.25)
                $amount = ($rate * ($regH / 8.0)) + ($rate / 8.0 * $otH * 1.25);

                DailyManpowerLine::create([
                    'sheet_id'          => $sheet->id,
                    'worker_id'         => $lineData['worker_id'],
                    'check_in'          => $lineData['check_in'] ?? null,
                    'check_out'         => $lineData['check_out'] ?? null,
                    'regular_hours'     => $regH,
                    'overtime_hours'    => $otH,
                    'daily_rate'        => $rate,
                    'amount'            => $amount,
                    'attendance_status' => $lineData['attendance_status'] ?? 'present',
                    'source'            => $lineData['source'] ?? 'manual',
                    'device_user_id'    => $lineData['device_user_id'] ?? null,
                    'remark'            => $lineData['remark'] ?? null,
                ]);
            }

            $this->approvalService->recalculateSheetTotals($sheet);

            if ($request->action === 'submit') {
                $this->approvalService->submitSheet($sheet, $user);
            }

            return $sheet;
        });

        $msg = $request->action === 'submit' 
            ? "Manpower Sheet [{$sheet->sheet_number}] submitted to Planning Manager!"
            : "Manpower Sheet [{$sheet->sheet_number}] saved as Draft.";

        return redirect()->route('manpower-approval.site-engineer.index')->with('success', $msg);
    }

    public function editSheet($id)
    {
        $sheet = DailyManpowerSheet::with(['lines.worker', 'project'])->findOrFail($id);
        $user = auth()->user();

        if (!in_array($sheet->status, [ManpowerApprovalService::STATUS_DRAFT, ManpowerApprovalService::STATUS_REJECTED]) && !$user->hasAnyRole(['admin', 'global_admin'])) {
            return redirect()->route('manpower-approval.site-engineer.index')->with('error', 'Only Draft or Returned sheets can be edited.');
        }

        $projects = Project::orderBy('name')->get();
        $workers = Worker::active()->orderBy('name')->get();

        return view('manpower_approval.site_engineer.edit', compact('sheet', 'projects', 'workers'));
    }

    public function updateSheet(Request $request, $id)
    {
        $sheet = DailyManpowerSheet::findOrFail($id);
        $user = auth()->user();

        if (!in_array($sheet->status, [ManpowerApprovalService::STATUS_DRAFT, ManpowerApprovalService::STATUS_REJECTED]) && !$user->hasAnyRole(['admin', 'global_admin'])) {
            return redirect()->route('manpower-approval.site-engineer.index')->with('error', 'Only Draft or Returned sheets can be updated.');
        }

        $request->validate([
            'lines'              => 'required|array|min:1',
            'lines.*.worker_id'  => 'required|exists:workers,id',
            'lines.*.daily_rate' => 'required|numeric|min:0',
            'action'             => 'required|in:save_draft,submit',
        ]);

        DB::transaction(function () use ($request, $sheet, $user) {
            $sheet->update([
                'trade'              => $request->trade,
                'gang_subcontractor' => $request->gang_subcontractor,
                'notes'              => $request->notes,
            ]);

            // Replace lines
            $sheet->lines()->delete();

            foreach ($request->lines as $lineData) {
                $regH = (float)($lineData['regular_hours'] ?? 8.0);
                $otH  = (float)($lineData['overtime_hours'] ?? 0);
                $rate = (float)($lineData['daily_rate'] ?? 0);
                $amount = ($rate * ($regH / 8.0)) + ($rate / 8.0 * $otH * 1.25);

                DailyManpowerLine::create([
                    'sheet_id'          => $sheet->id,
                    'worker_id'         => $lineData['worker_id'],
                    'check_in'          => $lineData['check_in'] ?? null,
                    'check_out'         => $lineData['check_out'] ?? null,
                    'regular_hours'     => $regH,
                    'overtime_hours'    => $otH,
                    'daily_rate'        => $rate,
                    'amount'            => $amount,
                    'attendance_status' => $lineData['attendance_status'] ?? 'present',
                    'source'            => $lineData['source'] ?? 'manual',
                    'device_user_id'    => $lineData['device_user_id'] ?? null,
                    'remark'            => $lineData['remark'] ?? null,
                ]);
            }

            $this->approvalService->recalculateSheetTotals($sheet);

            if ($request->action === 'submit') {
                $this->approvalService->submitSheet($sheet, $user, $request->notes);
            }
        });

        $msg = $request->action === 'submit' 
            ? "Manpower Sheet [{$sheet->sheet_number}] resubmitted to Planning Manager!"
            : "Manpower Sheet [{$sheet->sheet_number}] changes saved.";

        return redirect()->route('manpower-approval.site-engineer.index')->with('success', $msg);
    }

    // ══════════════════════════════════════════════════════════════════════
    // 2. REVIEW & APPROVAL SCREENS (Planning Manager, Coordinator, HR)
    // ══════════════════════════════════════════════════════════════════════

    public function reviewInbox(Request $request)
    {
        $user = auth()->user();
        $stage = $request->input('stage');

        if (!$stage) {
            if ($user->hasAnyRole(['planning_manager', 'planning', 'technical_manager'])) {
                $stage = ManpowerApprovalService::STAGE_PLANNING_MANAGER;
            } elseif ($user->hasRole('coordinator')) {
                $stage = ManpowerApprovalService::STAGE_COORDINATOR;
            } elseif ($user->hasAnyRole(['hr', 'hr_manager'])) {
                $stage = ManpowerApprovalService::STAGE_HR;
            } else {
                $stage = ManpowerApprovalService::STAGE_PLANNING_MANAGER;
            }
        }

        $pendingQuery = DailyManpowerSheet::with(['project', 'siteEngineer', 'lines.worker'])
            ->where('current_stage', $stage);

        if ($request->filled('project_id')) {
            $pendingQuery->where('project_id', $request->project_id);
        }
        if ($request->filled('date')) {
            $pendingQuery->where('date', $request->date);
        }

        $sheets = $pendingQuery->orderBy('date', 'desc')->paginate(15);
        $projects = Project::orderBy('name')->get();

        $stageCounts = [
            'planning'    => DailyManpowerSheet::where('current_stage', ManpowerApprovalService::STAGE_PLANNING_MANAGER)->count(),
            'coordinator' => DailyManpowerSheet::where('current_stage', ManpowerApprovalService::STAGE_COORDINATOR)->count(),
            'hr'          => DailyManpowerSheet::where('current_stage', ManpowerApprovalService::STAGE_HR)->count(),
        ];

        return view('manpower_approval.review.index', compact('sheets', 'projects', 'stage', 'stageCounts'));
    }

    public function showSheet($id)
    {
        $sheet = DailyManpowerSheet::with([
            'project',
            'siteEngineer',
            'lines.worker',
            'approvalLogs.user',
            'weeklyBatch',
        ])->findOrFail($id);

        $user = auth()->user();
        $canAct = ManpowerApprovalService::canUserActOnSheet($user, $sheet);

        // Fetch raw machine punches for this project + date for side-by-side verification
        $rawPunches = AttendanceRawPunch::where('site_id', $sheet->project_id)
            ->whereDate('punch_time', $sheet->date)
            ->get()
            ->groupBy('device_user_id');

        // Also check device_attendance_logs
        $deviceLogs = DeviceAttendanceLog::whereDate('punch_time', $sheet->date)
            ->get()
            ->groupBy('device_user_id');

        return view('manpower_approval.review.show', compact('sheet', 'canAct', 'rawPunches', 'deviceLogs'));
    }

    public function processReview(Request $request, $id)
    {
        $request->validate([
            'decision'         => 'required|in:approve,reject',
            'reason'           => 'nullable|string|max:150',
            'comment'          => 'nullable|string',
            'line_adjustments' => 'nullable|array',
        ]);

        $sheet = DailyManpowerSheet::findOrFail($id);
        $user = auth()->user();

        if (!ManpowerApprovalService::canUserActOnSheet($user, $sheet)) {
            abort(403, 'Unauthorized to act on this manpower sheet at the current stage.');
        }

        $decision = $request->decision;
        $reason = $request->reason;
        $comment = $request->comment;
        $lineAdjustments = $request->input('line_adjustments', []);

        switch ($sheet->current_stage) {
            case ManpowerApprovalService::STAGE_PLANNING_MANAGER:
                $this->approvalService->reviewPlanning($sheet, $user, $decision, $reason, $comment, $lineAdjustments);
                break;

            case ManpowerApprovalService::STAGE_COORDINATOR:
                $this->approvalService->reviewCoordinator($sheet, $user, $decision, $reason, $comment, $lineAdjustments);
                break;

            case ManpowerApprovalService::STAGE_HR:
                $this->approvalService->reviewHr($sheet, $user, $decision, $reason, $comment, $lineAdjustments);
                break;

            default:
                abort(400, 'Invalid approval stage.');
        }

        $msg = $decision === 'approve'
            ? "Sheet [{$sheet->sheet_number}] has been approved and advanced to the next stage!"
            : "Sheet [{$sheet->sheet_number}] was returned to the Site Engineer with rejection remarks.";

        return redirect()->route('manpower-approval.review.inbox')->with('success', $msg);
    }

    // ══════════════════════════════════════════════════════════════════════
    // 3. HR OFFICER - WEEKLY COLLECTION & BATCH GENERATION
    // ══════════════════════════════════════════════════════════════════════

    public function weeklyIndex(Request $request)
    {
        $projects = Project::orderBy('name')->get();
        $projectId = $request->input('project_id', $projects->first()?->id);

        // Fetch unbatched HR-approved sheets grouped by project
        $unbatchedSheets = DailyManpowerSheet::with(['project', 'siteEngineer', 'lines.worker'])
            ->where('status', ManpowerApprovalService::STATUS_HR_APPROVED)
            ->whereNull('weekly_batch_id')
            ->when($projectId, fn($q) => $q->where('project_id', $projectId))
            ->orderBy('date', 'asc')
            ->get();

        // Existing batches
        $batches = WeeklyManpowerBatch::with(['project', 'preparedBy', 'approvedByGm', 'paidByFinance'])
            ->when($projectId, fn($q) => $q->where('project_id', $projectId))
            ->orderBy('week_start', 'desc')
            ->paginate(15);

        return view('manpower_approval.weekly.index', compact('projects', 'projectId', 'unbatchedSheets', 'batches'));
    }

    public function createWeeklyBatch(Request $request)
    {
        $request->validate([
            'project_id' => 'required|exists:projects,id',
            'sheet_ids'  => 'required|array|min:1',
            'sheet_ids.*'=> 'exists:daily_manpower_sheets,id',
        ]);

        $project = Project::findOrFail($request->project_id);
        $sheets = DailyManpowerSheet::whereIn('id', $request->sheet_ids)
            ->where('project_id', $project->id)
            ->where('status', ManpowerApprovalService::STATUS_HR_APPROVED)
            ->with(['lines.worker'])
            ->get();

        if ($sheets->isEmpty()) {
            return redirect()->back()->with('error', 'No HR-approved sheets selected.');
        }

        // Determine min and max date
        $minDate = $sheets->min('date');
        $maxDate = $sheets->max('date');

        // Group lines by worker
        $workersSummary = [];
        foreach ($sheets as $s) {
            foreach ($s->lines as $line) {
                $wId = $line->worker_id;
                if (!isset($workersSummary[$wId])) {
                    $workersSummary[$wId] = [
                        'worker'          => $line->worker,
                        'days_worked'     => 0,
                        'regular_hours'   => 0,
                        'overtime_hours'  => 0,
                        'gross_amount'    => 0,
                    ];
                }
                if ($line->attendance_status === 'present' || $line->effective_amount > 0) {
                    $workersSummary[$wId]['days_worked']++;
                }
                $workersSummary[$wId]['regular_hours']  += (float)$line->regular_hours;
                $workersSummary[$wId]['overtime_hours'] += (float)$line->overtime_hours;
                $workersSummary[$wId]['gross_amount']   += (float)$line->effective_amount;
            }
        }

        return view('manpower_approval.weekly.create_batch', compact('project', 'sheets', 'minDate', 'maxDate', 'workersSummary'));
    }

    public function storeWeeklyBatch(Request $request)
    {
        $request->validate([
            'project_id'         => 'required|exists:projects,id',
            'week_start'         => 'required|date',
            'week_end'           => 'required|date|after_or_equal:week_start',
            'sheet_ids'          => 'required|array|min:1',
            'worker_deductions'  => 'nullable|array',
            'worker_advances'    => 'nullable|array',
            'notes'              => 'nullable|string',
        ]);

        $user = auth()->user();

        $batch = $this->approvalService->generateWeeklyBatch(
            (int)$request->project_id,
            $request->week_start,
            $request->week_end,
            $request->sheet_ids,
            $user,
            $request->input('worker_deductions', []),
            $request->input('worker_advances', []),
            $request->notes
        );

        return redirect()->route('manpower-approval.weekly.show', $batch->id)
            ->with('success', "Weekly Batch [{$batch->batch_number}] generated and submitted to General Manager!");
    }

    public function showWeeklyBatch($id)
    {
        $batch = WeeklyManpowerBatch::with([
            'project',
            'preparedBy',
            'approvedByGm',
            'paidByFinance',
            'items.worker',
            'dailySheets.lines',
            'approvalLogs.user',
        ])->findOrFail($id);

        $user = auth()->user();
        $canGmAct = ($batch->status === 'Submitted_GM' && $user->hasAnyRole(['gm', 'general_manager', 'admin', 'global_admin']));
        $canFinanceAct = ($batch->status === 'GM_Approved' && $user->hasAnyRole(['finance', 'finance_head', 'admin', 'global_admin']));

        return view('manpower_approval.weekly.show', compact('batch', 'canGmAct', 'canFinanceAct'));
    }

    // ══════════════════════════════════════════════════════════════════════
    // 4. GENERAL MANAGER REVIEW
    // ══════════════════════════════════════════════════════════════════════

    public function processGmBatch(Request $request, $id)
    {
        $request->validate([
            'decision' => 'required|in:approve,reject',
            'reason'   => 'nullable|string|max:150',
            'comment'  => 'nullable|string',
        ]);

        $batch = WeeklyManpowerBatch::findOrFail($id);
        $user = auth()->user();

        if (!$user->hasAnyRole(['gm', 'general_manager', 'admin', 'global_admin'])) {
            abort(403, 'Unauthorized. General Manager role required.');
        }

        $this->approvalService->reviewBatchGm($batch, $user, $request->decision, $request->reason, $request->comment);

        $msg = $request->decision === 'approve'
            ? "Weekly Batch [{$batch->batch_number}] has been approved by GM and forwarded to Finance for payment!"
            : "Weekly Batch [{$batch->batch_number}] was returned to HR Officer with remarks.";

        return redirect()->route('manpower-approval.weekly.show', $batch->id)->with('success', $msg);
    }

    // ══════════════════════════════════════════════════════════════════════
    // 5. FINANCE PAYMENTS
    // ══════════════════════════════════════════════════════════════════════

    public function paymentsIndex(Request $request)
    {
        $status = $request->input('status', 'pending'); // 'pending' (GM_Approved) or 'paid' or 'all'

        $query = WeeklyManpowerBatch::with(['project', 'preparedBy', 'approvedByGm', 'paidByFinance']);

        if ($status === 'pending') {
            $query->where('status', 'GM_Approved');
        } elseif ($status === 'paid') {
            $query->where('status', 'Paid');
        }

        $batches = $query->orderBy('updated_at', 'desc')->paginate(15);
        $pendingCount = WeeklyManpowerBatch::where('status', 'GM_Approved')->count();
        $paidCount = WeeklyManpowerBatch::where('status', 'Paid')->count();

        return view('manpower_approval.payments.index', compact('batches', 'status', 'pendingCount', 'paidCount'));
    }

    public function recordBatchPayment(Request $request, $id)
    {
        $request->validate([
            'payment_date'       => 'required|date',
            'payment_method'     => 'required|string|max:50',
            'payment_reference'  => 'nullable|string|max:100',
            'payment_attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'payment_notes'      => 'nullable|string',
        ]);

        $batch = WeeklyManpowerBatch::findOrFail($id);
        $user = auth()->user();

        if (!$user->hasAnyRole(['finance', 'finance_head', 'admin', 'global_admin'])) {
            abort(403, 'Unauthorized. Finance role required.');
        }

        $attachmentUrl = null;
        if ($request->hasFile('payment_attachment')) {
            $path = $request->file('payment_attachment')->store('manpower_payments', 'public');
            $attachmentUrl = "/storage/{$path}";
        }

        $this->approvalService->recordPayment(
            $batch,
            $user,
            $request->payment_date,
            $request->payment_method,
            $request->payment_reference,
            $attachmentUrl,
            $request->payment_notes
        );

        return redirect()->route('manpower-approval.payments.index')
            ->with('success', "Batch [{$batch->batch_number}] successfully marked as PAID! Labor expense officially recorded.");
    }

    public function holdBatchPayment(Request $request, $id)
    {
        $request->validate([
            'hold_reason' => 'required|string|min:5|max:255',
        ]);

        $batch = WeeklyManpowerBatch::findOrFail($id);
        $user = auth()->user();

        if (!$user->hasAnyRole(['finance', 'finance_head', 'admin', 'global_admin'])) {
            abort(403, 'Unauthorized.');
        }

        $this->approvalService->holdBatch($batch, $user, $request->hold_reason);

        return redirect()->route('manpower-approval.payments.index')
            ->with('warning', "Batch [{$batch->batch_number}] put on HOLD: {$request->hold_reason}");
    }

    // ══════════════════════════════════════════════════════════════════════
    // 6. WORKER MASTER & AJAX HELPER APIS
    // ══════════════════════════════════════════════════════════════════════

    public function quickAddWorker(Request $request)
    {
        $request->validate([
            'name'       => 'required|string|max:150',
            'phone'      => 'nullable|string|max:50',
            'trade'      => 'required|string|max:100',
            'daily_rate' => 'required|numeric|min:0',
        ]);

        $code = 'WRK-' . strtoupper(substr(uniqid(), -5));
        $worker = Worker::create([
            'worker_code' => $code,
            'name'        => $request->name,
            'phone'       => $request->phone,
            'trade'       => $request->trade,
            'daily_rate'  => $request->daily_rate,
            'status'      => 'active',
            'created_by'  => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'worker'  => $worker,
            'message' => 'Worker created successfully!',
        ]);
    }

    public function fetchPunches(Request $request)
    {
        $request->validate([
            'site_id' => 'required|integer',
            'date'    => 'required|date',
        ]);

        $siteId = $request->site_id;
        $date = $request->date;

        $rawPunches = AttendanceRawPunch::where('site_id', $siteId)
            ->whereDate('punch_time', $date)
            ->get();

        $deviceLogs = DeviceAttendanceLog::whereDate('punch_time', $date)->get();

        return response()->json([
            'success'     => true,
            'raw_punches' => $rawPunches,
            'device_logs' => $deviceLogs,
        ]);
    }

    public function ingestRawPunch(Request $request)
    {
        $request->validate([
            'device_user_id' => 'required|string|max:50',
            'punch_time'     => 'required|date',
            'device_id'      => 'nullable|string|max:100',
            'site_id'        => 'nullable|integer',
            'punch_type'     => 'nullable|string|in:check_in,check_out,general',
        ]);

        $punch = AttendanceRawPunch::create([
            'device_user_id' => $request->device_user_id,
            'punch_time'     => $request->punch_time,
            'device_id'      => $request->device_id,
            'site_id'        => $request->site_id,
            'punch_type'     => $request->punch_type ?? 'check_in',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Raw punch logged successfully',
            'punch'   => $punch,
        ], 201);
    }
}
