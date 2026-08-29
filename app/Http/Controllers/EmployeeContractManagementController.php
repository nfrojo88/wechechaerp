<?php

namespace App\Http\Controllers;

use App\Models\EmployeeContract;
use App\Models\ContractMilestone;
use App\Models\ContractAmendment;
use App\Models\ContractRenewal;
use App\Models\ContractApproval;
use App\Models\Employee;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class EmployeeContractManagementController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display all contracts
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', EmployeeContract::class);

        $query = EmployeeContract::with(['employee', 'createdBy', 'approvedBy']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->filled('contract_type')) {
            $query->where('contract_type', $request->contract_type);
        }

        $contracts = $query->orderBy('end_date', 'asc')->paginate(15);

        $stats = [
            'active' => EmployeeContract::where('status', 'active')->count(),
            'expiring_soon' => EmployeeContract::where('status', 'active')
                ->whereDate('end_date', '<=', Carbon::now()->addDays(30))
                ->whereDate('end_date', '>=', Carbon::now())
                ->count(),
            'expired' => EmployeeContract::where('status', 'expired')->count(),
            'pending_approval' => EmployeeContract::where('status', 'pending_approval')->count(),
        ];

        $employees = Employee::where('status', 'active')->orderBy('full_name')->get();

        return view('hr-manager.contracts.index', compact('contracts', 'stats', 'employees'));
    }

    /**
     * Show contract details
     */
    public function show(EmployeeContract $contract)
    {
        $this->authorize('view', $contract);

        $contract->load([
            'employee', 'createdBy', 'approvedBy',
            'milestones', 'amendments', 'renewals', 'approvals'
        ]);

        return view('hr-manager.contracts.show', compact('contract'));
    }

    /**
     * Create new contract
     */
    public function create()
    {
        $this->authorize('create', EmployeeContract::class);

        $employees = Employee::where('status', 'active')->orderBy('full_name')->get();
        $contractTypes = ['Permanent', 'Temporary', 'Contract', 'Casual', 'Probation'];

        return view('hr-manager.contracts.create', compact('employees', 'contractTypes'));
    }

    /**
     * Store new contract
     */
    public function store(Request $request)
    {
        $this->authorize('create', EmployeeContract::class);

        $validated = $request->validate([
            'employee_id'     => 'required|exists:employees,id',
            'project_id'      => 'nullable|exists:projects,id',
            'contract_type'   => 'required|string',
            'duration_type'   => 'nullable|in:fixed_date,until_project_completion',
            'is_project_based'=> 'nullable|boolean',
            'start_date'      => 'required|date',
            'end_date'        => 'nullable|date|after:start_date',
            'salary'          => 'required|numeric|min:0',
            'benefits_amount' => 'nullable|numeric|min:0',
            'terms'           => 'required|string|min:20',
            'special_terms'   => 'nullable|string',
            'contract_file'   => 'nullable|file|mimes:pdf,doc,docx|max:5120',
        ]);

        if (($validated['duration_type'] ?? '') === 'until_project_completion' || $request->boolean('is_project_based')) {
            $validated['duration_type'] = 'until_project_completion';
            $validated['is_project_based'] = true;
        } else {
            $validated['duration_type'] = $validated['duration_type'] ?? 'fixed_date';
            $validated['is_project_based'] = false;
        }

        // Generate contract number
        $contractNumber = 'CNT-' . date('Y') . '-' . str_pad(EmployeeContract::whereYear('created_at', date('Y'))->count() + 1, 4, '0', STR_PAD_LEFT);
        $validated['contract_number'] = $contractNumber;

        if ($request->hasFile('contract_file')) {
            $validated['contract_file'] = \App\Services\FileUploadService::upload($request->file('contract_file'), 'contracts');
        }

        $validated['created_by'] = Auth::id();
        $validated['status'] = 'draft';

        $contract = EmployeeContract::create($validated);


        return redirect()->route('contracts.show', $contract->id)
            ->with('success', 'Contract created successfully. Pending approval.');
    }

    /**
     * Submit contract for approval
     */
    public function submitForApproval(EmployeeContract $contract)
    {
        $this->authorize('update', $contract);

        if ($contract->status !== 'draft') {
            return back()->withErrors(['status' => 'Only draft contracts can be submitted']);
        }

        $contract->update(['status' => 'pending_approval']);

        // Create approval workflow (Manager → HR → Finance)
        $approvers = [
            ['level' => 1, 'role' => 'manager'],
            ['level' => 2, 'role' => 'hr_manager'],
            ['level' => 3, 'role' => 'finance_manager'],
        ];

        foreach ($approvers as $approver) {
            $user = User::role($approver['role'])->first();
            if ($user) {
                ContractApproval::create([
                    'employee_contract_id' => $contract->id,
                    'approver_id' => $user->id,
                    'approval_level' => $approver['level'],
                    'status' => 'pending',
                ]);
            }
        }

        return back()->with('success', 'Contract submitted for approval');
    }

    /**
     * Approve contract
     */
    public function approve(ContractApproval $approval)
    {
        $this->authorize('approve', $approval->contract);

        $validated = request()->validate([
            'comments' => 'nullable|string|max:500',
        ]);

        $approval->update([
            'status' => 'approved',
            'comments' => $validated['comments'] ?? null,
            'responded_at' => now(),
        ]);

        // Check if all approvals are done
        $contract = $approval->contract;
        $pendingApprovals = $contract->approvals()->where('status', 'pending')->count();

        if ($pendingApprovals == 0) {
            $contract->update([
                'status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            return back()->with('success', 'Contract approved and activated');
        }

        return back()->with('success', 'Approval recorded. Awaiting other approvals.');
    }

    /**
     * Reject contract
     */
    public function reject(ContractApproval $approval)
    {
        $this->authorize('approve', $approval->contract);

        $validated = request()->validate([
            'comments' => 'required|string|min:10',
        ]);

        $approval->update([
            'status' => 'rejected',
            'comments' => $validated['comments'],
            'responded_at' => now(),
        ]);

        $contract = $approval->contract;
        $contract->update(['status' => 'draft']);

        return back()->with('success', 'Contract rejected. Returned to draft.');
    }

    /**
     * Add milestone
     */
    public function addMilestone(Request $request, EmployeeContract $contract)
    {
        $this->authorize('update', $contract);

        $validated = $request->validate([
            'milestone_name' => 'required|string',
            'milestone_date' => 'required|date',
            'description' => 'nullable|string',
        ]);

        ContractMilestone::create([
            'employee_contract_id' => $contract->id,
            'status' => 'pending',
            ...$validated
        ]);

        return back()->with('success', 'Milestone added');
    }

    /**
     * Request contract renewal
     */
    public function requestRenewal(Request $request, EmployeeContract $contract)
    {
        $this->authorize('update', $contract);

        if (!$contract->is_renewable) {
            return back()->withErrors(['renewal' => 'This contract is not renewable']);
        }

        $validated = $request->validate([
            'new_end_date' => 'required|date|after:' . $contract->end_date->toDateString(),
            'new_salary' => 'nullable|numeric|min:0',
            'renewal_terms' => 'nullable|string',
        ]);

        $renewal = ContractRenewal::create([
            'employee_contract_id' => $contract->id,
            'renewal_date' => now()->toDateString(),
            'status' => 'proposed',
            'proposed_by' => Auth::id(),
            ...$validated
        ]);

        return back()->with('success', 'Renewal request created');
    }

    /**
     * Approve renewal
     */
    public function approveRenewal(ContractRenewal $renewal)
    {
        $this->authorize('approve', $renewal->contract);

        $contract = $renewal->contract;

        $renewal->update([
            'status' => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        // Update contract
        $contract->update([
            'end_date' => $renewal->new_end_date,
            'salary' => $renewal->new_salary ?? $contract->salary,
            'renewal_count' => $contract->renewal_count + 1,
        ]);

        $renewal->update(['status' => 'completed']);

        return back()->with('success', 'Renewal approved and contract updated');
    }

    /**
     * Request contract amendment
     */
    public function requestAmendment(Request $request, EmployeeContract $contract)
    {
        $this->authorize('update', $contract);

        $validated = $request->validate([
            'amendment_title' => 'required|string',
            'changes_description' => 'required|string|min:20',
            'effective_date' => 'required|date|after_or_equal:today',
        ]);

        ContractAmendment::create([
            'employee_contract_id' => $contract->id,
            'status' => 'draft',
            'requested_by' => Auth::id(),
            ...$validated
        ]);

        return back()->with('success', 'Amendment request created');
    }

    /**
     * Approve amendment
     */
    public function approveAmendment(ContractAmendment $amendment)
    {
        $this->authorize('approve', $amendment->contract);

        $amendment->update([
            'status' => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return back()->with('success', 'Amendment approved');
    }

    /**
     * Export contracts report
     */
    public function exportReport(Request $request)
    {
        $this->authorize('viewAny', EmployeeContract::class);

        $query = EmployeeContract::with('employee');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $contracts = $query->get();

        $fileName = 'contracts-' . now()->format('Y-m-d-His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"$fileName\"",
        ];

        $callback = function () use ($contracts) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Employee', 'Type', 'Start Date', 'End Date', 'Salary', 'Benefits', 'Status', 'Days Remaining']);

            foreach ($contracts as $contract) {
                fputcsv($file, [
                    $contract->employee->name,
                    $contract->contract_type,
                    $contract->start_date->format('Y-m-d'),
                    $contract->end_date->format('Y-m-d'),
                    $contract->salary,
                    $contract->benefits_amount,
                    $contract->status,
                    $contract->days_remaining,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Get expiring contracts dashboard
     */
    public function expiringContracts()
    {
        $this->authorize('viewAny', EmployeeContract::class);

        $expiringIn30 = EmployeeContract::where('status', 'active')
            ->whereDate('end_date', '<=', Carbon::now()->addDays(30))
            ->whereDate('end_date', '>=', Carbon::now())
            ->with('employee')
            ->orderBy('end_date', 'asc')
            ->get();

        $expiringIn90 = EmployeeContract::where('status', 'active')
            ->whereDate('end_date', '<=', Carbon::now()->addDays(90))
            ->whereDate('end_date', '>', Carbon::now()->addDays(30))
            ->with('employee')
            ->orderBy('end_date', 'asc')
            ->get();

        return view('hr-manager.contracts.expiring', compact('expiringIn30', 'expiringIn90'));
    }
}
