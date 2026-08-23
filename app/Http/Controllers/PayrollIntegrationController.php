<?php

namespace App\Http\Controllers;

use App\Models\Payroll;
use App\Models\PayrollSummary;
use App\Models\SalaryStructure;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\PayrollAdjustment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PayrollIntegrationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display payroll dashboard
     */
    public function dashboard(Request $request)
    {
        $this->authorize('viewAny', Payroll::class);

        $employees = Employee::where('status', 'active')->get();

        $payrollData = [];
        $totals = [
            'employees' => $employees->count(),
            'gross' => 0,
            'tax' => 0,
            'emp_pension' => 0,
            'comp_pension' => 0,
            'net_payable' => 0,
        ];

        foreach ($employees as $emp) {
            $base = $emp->basic_salary ?? 0;
            $allowances = ($emp->transport_allowance ?? 0) + ($emp->house_allowance ?? 0) + ($emp->position_allowance ?? 0);
            
            $gross = $base + $allowances;
            $empPension = $base * 0.07;
            $compPension = $base * 0.11;
            
            $transport = $emp->transport_allowance ?? 0;
            $house     = $emp->house_allowance ?? 0;
            $position  = $emp->position_allowance ?? 0;

            // Taxable income (pension 7% is NOT deducted as per system rules)
            $taxable = Payroll::calculateTaxableIncome($base, $house, $position, $transport, 0);

            // Tax calculation
            $tax = Payroll::calculateIncomeTax($taxable);

            $deductions = 0; // Other deductions
            $net = $gross - $empPension - $tax - $deductions;

            $totals['gross'] += $gross;
            $totals['tax'] += $tax;
            $totals['emp_pension'] += $empPension;
            $totals['comp_pension'] += $compPension;
            $totals['net_payable'] += $net;

            $payrollData[] = [
                'emp_id' => $emp->employee_code,
                'name' => $emp->full_name,
                'department' => $emp->department ?? 'N/A',
                'base_salary' => $base,
                'gross_salary' => $gross,
                'taxable' => $taxable,
                'deductions' => $deductions,
                'pension' => $empPension,
                'tax_amount' => $tax,
                'net_salary' => $net,
                'id' => $emp->id,
            ];
        }

        return view('hr-manager.payroll.dashboard', compact('payrollData', 'totals'));
    }

    /**
     * Employee payroll history
     */
    public function employeePayroll(Employee $employee)
    {
        $this->authorize('viewAny', Payroll::class);

        $payrolls = $employee->payrolls()
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->paginate(12);

        $salaryStructure = $employee->salaryStructure;

        // Calculate YTD (Year-to-Date)
        $ytdTotal = Payroll::where('employee_id', $employee->id)
            ->where('year', Carbon::now()->year)
            ->sum('net_salary');

        return view('hr-manager.payroll.employee-history', compact(
            'employee',
            'payrolls',
            'salaryStructure',
            'ytdTotal'
        ));
    }

    /**
     * Salary structure management
     */
    public function salaryStructures(Request $request)
    {
        $this->authorize('viewAny', Payroll::class);

        $query = SalaryStructure::with('employee');

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active === 'true');
        }

        $structures = $query->paginate(15);
        $employees = Employee::where('status', 'active')->orderBy('full_name')->get();

        return view('hr-manager.payroll.salary-structures', compact('structures', 'employees'));
    }

    /**
     * Create salary structure
     */
    public function createSalaryStructure()
    {
        $this->authorize('create', SalaryStructure::class);

        $employees = Employee::where('status', 'active')->orderBy('full_name')->get();

        return view('hr-manager.payroll.create-structure', compact('employees'));
    }

    /**
     * Store salary structure
     */
    public function storeSalaryStructure(Request $request)
    {
        $this->authorize('create', SalaryStructure::class);

        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'base_salary' => 'required|numeric|min:0',
            'house_allowance' => 'nullable|numeric|min:0',
            'transport_allowance' => 'nullable|numeric|min:0',
            'meal_allowance' => 'nullable|numeric|min:0',
            'other_allowance' => 'nullable|numeric|min:0',
            'effective_from' => 'required|date',
        ]);

        $validated['gross_salary'] = $validated['base_salary'] +
            ($validated['house_allowance'] ?? 0) +
            ($validated['transport_allowance'] ?? 0) +
            ($validated['meal_allowance'] ?? 0) +
            ($validated['other_allowance'] ?? 0);

        $validated['is_active'] = true;

        // Deactivate previous active structure
        SalaryStructure::where('employee_id', $validated['employee_id'])
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'effective_to' => Carbon::now()->subDay()->toDateString(),
            ]);

        SalaryStructure::create($validated);

        return redirect()->route('payroll.salary-structures')
            ->with('success', 'Salary structure created');
    }

    /**
     * Advances management
     */
    public function advances(Request $request)
    {
        $this->authorize('viewAny', Payroll::class);

        $query = EmployeeAdvance::with('employee', 'approvedByUser');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        $advances = $query->orderBy('advance_date', 'desc')->paginate(15);

        $employees = Employee::where('status', 'active')->orderBy('full_name')->get();

        return view('hr-manager.payroll.advances', compact('advances', 'employees'));
    }

    /**
     * Request advance
     */
    /**
     * Request advance (By employee or selected employee)
     */
    public function requestAdvance(Request $request)
    {
        $validated = $request->validate([
            'employee_id'  => 'nullable|exists:employees,id',
            'amount'       => 'required|numeric|min:100',
            'installments' => 'required|integer|min:1|max:12',
            'reason'       => 'nullable|string|max:500',
        ]);

        $employeeId = $validated['employee_id'] ?? null;

        if (!$employeeId) {
            $employee = Employee::where('user_id', Auth::id())->first();
            if (!$employee) {
                return back()->with('error', 'No employee record linked to your user account.');
            }
            $employeeId = $employee->id;
        }

        EmployeeAdvance::create([
            'employee_id'  => $employeeId,
            'amount'       => $validated['amount'],
            'advance_date' => now()->toDateString(),
            'installments' => $validated['installments'],
            'reason'       => $validated['reason'] ?? 'Salary Advance Loan Request',
            'status'       => 'pending',
        ]);

        return back()->with('success', 'Salary advance loan request submitted successfully for GM approval.');
    }

    /**
     * GM Approves advance loan request
     */
    public function approveAdvance(Request $request, EmployeeAdvance $advance)
    {
        $advance->update([
            'status'      => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'gm_notes'    => $request->input('gm_notes'),
        ]);

        return back()->with('success', 'Salary advance loan request APPROVED by GM. Sent to Finance for payment disbursement.');
    }

    /**
     * GM Rejects advance loan request
     */
    public function rejectAdvance(Request $request, EmployeeAdvance $advance)
    {
        $advance->update([
            'status'      => 'rejected',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'gm_notes'    => $request->input('gm_notes', 'Loan request rejected by GM.'),
        ]);

        return back()->with('error', 'Salary advance loan request REJECTED.');
    }

    /**
     * Finance Disburses approved advance loan
     */
    public function disburseAdvance(Request $request, EmployeeAdvance $advance)
    {
        $advance->update([
            'status'        => 'disbursed',
            'disbursed_at'  => now(),
            'finance_notes' => $request->input('finance_notes'),
        ]);

        return back()->with('success', 'Salary advance loan DISBURSED by Finance. Monthly installments will automatically deduct from payroll.');
    }

    /**
     * Monthly payroll processing status
     */
    public function monthlyStatus(Request $request)
    {
        $this->authorize('viewAny', Payroll::class);

        $month = $request->get('month', Carbon::now()->month);
        $year = $request->get('year', Carbon::now()->year);

        $summary = PayrollSummary::where('year', $year)
            ->where('month', $month)
            ->firstOrCreate([
                'year' => $year,
                'month' => $month,
            ], [
                'total_employees' => Employee::where('is_active', true)->count(),
                'created_by' => Auth::id(),
            ]);

        $payrolls = Payroll::where('year', $year)
            ->where('month', $month)
            ->with('employee')
            ->get();

        return view('hr-manager.payroll.monthly-status', compact('summary', 'payrolls'));
    }

    /**
     * Payroll analytics
     */
    public function analytics(Request $request)
    {
        $this->authorize('viewAny', Payroll::class);

        $fromMonth = $request->get('from_month', Carbon::now()->subMonths(11)->month);
        $fromYear = $request->get('from_year', Carbon::now()->year);
        $toMonth = $request->get('to_month', Carbon::now()->month);
        $toYear = $request->get('to_year', Carbon::now()->year);

        // Salary trends
        $trends = PayrollSummary::whereBetween(DB::raw("CONCAT(year, '-', LPAD(month, 2, '0'))"),
            [$fromYear . '-' . str_pad($fromMonth, 2, '0', STR_PAD_LEFT),
             $toYear . '-' . str_pad($toMonth, 2, '0', STR_PAD_LEFT)])
            ->select('year', 'month', 'total_net', 'total_gross', 'processed_count')
            ->orderBy('year')
            ->orderBy('month')
            ->get();

        // Department-wise breakdown
        $departmentBreakdown = DB::table('payrolls')
            ->join('employees', 'payrolls.employee_id', '=', 'employees.id')
            ->join('departments', 'employees.department_id', '=', 'departments.id')
            ->where('payrolls.year', Carbon::now()->year)
            ->where('payrolls.month', Carbon::now()->month)
            ->selectRaw('departments.name, COUNT(*) as count, SUM(net_salary) as total')
            ->groupBy('departments.name')
            ->get();

        return view('hr-manager.payroll.analytics', compact('trends', 'departmentBreakdown'));
    }

    /**
     * Generate monthly summary
     */
    private function generateMonthlySummary($year, $month)
    {
        $payrolls = Payroll::where('year', $year)
            ->where('month', $month)
            ->get();

        return PayrollSummary::create([
            'year' => $year,
            'month' => $month,
            'total_employees' => Employee::where('is_active', true)->count(),
            'total_gross' => $payrolls->sum('gross_salary') ?? 0,
            'total_allowances' => $payrolls->sum('allowances') ?? 0,
            'total_deductions' => $payrolls->sum('deductions') ?? 0,
            'total_taxes' => $payrolls->sum('tax') ?? 0,
            'total_net' => $payrolls->sum('net_salary') ?? 0,
            'processed_count' => $payrolls->count(),
            'created_by' => Auth::id(),
        ]);
    }
}
