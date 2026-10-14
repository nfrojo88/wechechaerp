<?php

namespace App\Http\Controllers;

use App\Helpers\EthiopianCalendar;
use App\Models\Employee;
use App\Models\Payroll;
use Illuminate\Http\Request;

class FinancePayrollController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    // ─── Finance Head: Payroll Management ────────────────────────────────────

    /**
     * List payrolls for the selected Ethiopian attendance period (26th–25th).
     */
    public function index(Request $request)
    {
        // 1. Determine Period: Ethiopian Attendance Payroll Period (26th to 25th)
        $availablePeriods  = EthiopianCalendar::getAvailablePayrollPeriods();
        $selectedPeriodKey = $request->input('period');

        if ($selectedPeriodKey && str_contains($selectedPeriodKey, '-')) {
            [$ey, $em] = explode('-', $selectedPeriodKey);
            $periodData = EthiopianCalendar::getPayrollPeriod((int)$ey, (int)$em);
        } elseif ($request->filled('month') && $request->filled('year')) {
            $m = (int) $request->input('month');
            $y = (int) $request->input('year');
            if ($m >= 1 && $m <= 13) {
                $periodData = EthiopianCalendar::getPayrollPeriod($y, $m);
            } else {
                $periodData = EthiopianCalendar::getCurrentPayrollPeriod();
            }
        } else {
            $periodData = EthiopianCalendar::getCurrentPayrollPeriod();
        }

        $selectedPeriodKey = $periodData['period_key'];
        $month             = (int) $periodData['eth_month'];
        $year              = (int) $periodData['eth_year'];
        $startDate         = $request->input('start_date', $periodData['start_greg']);
        $endDate           = $request->input('end_date', $periodData['end_greg']);
        $selectedDate      = $endDate;

        // Human-readable attendance day window format (e.g. "26 Meskerem – 25 Tikimt (መስ 26 – ጥቅ 25)")
        $firstDay = $periodData['days'][0] ?? null;
        $lastDay  = !empty($periodData['days']) ? end($periodData['days']) : null;
        $attendanceDayWindow = ($firstDay && $lastDay)
            ? "{$firstDay['display_label']} – {$lastDay['display_label']} ({$firstDay['eth_label_am']} – {$lastDay['eth_label_am']})"
            : "{$startDate} – {$endDate}";

        // Load existing payrolls for this period
        $payrolls = Payroll::with('employee')
            ->where('month', $month)
            ->where('year',  $year)
            ->orderBy('id')
            ->get();

        $hasTransport = \Illuminate\Support\Facades\Schema::hasColumn('payrolls', 'transport_allowance');
        $hasGmStatus  = \Illuminate\Support\Facades\Schema::hasColumn('payrolls', 'gm_status');

        // Summary totals
        $totals = [
            'basic'             => $payrolls->sum('basic_salary'),
            'transport'         => $hasTransport ? $payrolls->sum('transport_allowance') : 0,
            'house'             => $hasTransport ? $payrolls->sum('house_allowance') : 0,
            'position'          => $hasTransport ? $payrolls->sum('position_allowance') : 0,
            'overtime'          => $payrolls->sum('overtime_pay'),
            'gross'             => $payrolls->sum(function($p) { return $p->gross_salary ?? ($p->basic_salary + $p->allowances + $p->overtime_pay); }),
            'taxable_income'    => $payrolls->sum(function($p) {
                return $p->taxable_income ?? Payroll::calculateTaxableIncome(
                    (float) $p->basic_salary,
                    (float) ($p->house_allowance ?? 0),
                    (float) ($p->position_allowance ?? 0),
                    (float) ($p->transport_allowance ?? 0),
                    (float) ($p->overtime_pay ?? 0)
                );
            }),
            'pension'           => $hasTransport ? $payrolls->sum('pension') : round($payrolls->sum('basic_salary') * 0.07, 2),
            'company_pension'   => $payrolls->sum(function($p) { return $p->company_pension ?? round($p->basic_salary * 0.11, 2); }),
            'tax'               => $payrolls->sum('tax'),
            'loan_deductions'   => $payrolls->sum(function($p) { return $p->loan_deduction ?? 0; }),
            'absence_deductions'=> $payrolls->sum(function($p) { return $p->absence_deduction ?? 0; }),
            'absent_days'       => $payrolls->sum('absent_days'),
            'deductions'        => $payrolls->sum('deductions'),
            'net'               => $payrolls->sum('net_salary'),
            'count'             => $payrolls->count(),
        ];

        // GM status for this batch
        $gmStatus  = $hasGmStatus ? ($payrolls->first()->gm_status ?? null) : null;
        $submitted = $hasGmStatus ? $payrolls->where('gm_status', 'submitted')->count() : 0;
        $approved  = $hasGmStatus ? $payrolls->where('gm_status', 'approved')->count() : 0;

        // Precompute attendance summary stats for employees in this period
        $employeeIds = $payrolls->pluck('employee_id')->filter()->unique()->toArray();
        $attendanceStats = $this->getBatchAttendanceStats($employeeIds, $startDate, $endDate, $periodData['days'] ?? []);

        return view('finance.payroll.index', compact(
            'payrolls', 'totals', 'month', 'year', 'startDate', 'endDate', 'selectedDate', 'gmStatus', 'submitted', 'approved',
            'availablePeriods', 'selectedPeriodKey', 'periodData', 'attendanceDayWindow', 'attendanceStats'
        ));
    }

    /**
     * Auto-generate payroll entries for all active employees for the period.
     */
    public function generate(Request $request)
    {
        $selectedPeriodKey = $request->input('period');
        if ($selectedPeriodKey && str_contains($selectedPeriodKey, '-')) {
            [$ey, $em] = explode('-', $selectedPeriodKey);
            $periodData = EthiopianCalendar::getPayrollPeriod((int)$ey, (int)$em);
            $month = (int) $periodData['eth_month'];
            $year  = (int) $periodData['eth_year'];
            $startDate = $periodData['start_greg'];
            $endDate   = $periodData['end_greg'];
        } else {
            $request->validate([
                'month' => 'required|integer|between:1,13',
                'year'  => 'required|integer|min:2000|max:2100',
            ]);
            $month = (int) $request->month;
            $year  = (int) $request->year;
            $periodData = EthiopianCalendar::getPayrollPeriod($year, $month);
            $startDate  = $request->input('start_date', $periodData['start_greg']);
            $endDate    = $request->input('end_date', $periodData['end_greg']);
        }

        $selectedDate = $endDate;
        $employees = Employee::activeRoster()->get();
        $created = 0;
        $skipped = 0;

        $hasTransport = \Illuminate\Support\Facades\Schema::hasColumn('payrolls', 'transport_allowance');
        $hasLoanDed   = \Illuminate\Support\Facades\Schema::hasColumn('payrolls', 'loan_deduction');

        foreach ($employees as $emp) {
            $exists = Payroll::where('employee_id', $emp->id)
                             ->where('month', $month)
                             ->where('year',  $year)
                             ->exists();
            if ($exists) { $skipped++; continue; }

            $basic     = $emp->basic_salary ?? 0;
            $transport = $emp->transport_allowance ?? 0;
            $house     = $emp->house_allowance ?? 0;
            $position  = $emp->position_allowance ?? 0;

            // 1. Salary advance loan monthly deduction
            $loanDeduction = Payroll::calculateLoanDeduction($emp->id);

            // 2. Unexcused attendance absences without approved leave between start date and end date
            // Daily rate = basic / 30; each missed day deducts (basic / 30)
            $absenceInfo      = Payroll::calculateUnexcusedAbsences($emp->id, $month, $year, $endDate, $startDate);
            $absentDays       = $absenceInfo['days'];
            $dailyRate        = $basic > 0 ? ($basic / 30) : 0;
            $absenceDeduction = round($dailyRate * $absentDays, 2);

            // Total other deductions = loan repayment + absence deduction
            $totalDeductions  = round($loanDeduction + $absenceDeduction, 2);

            // 3. Sum overtime pay from approved attendance records between start date and end date
            $overtimePay = round((float) \App\Models\Attendance::where('employee_id', $emp->id)
                ->where('is_approved', true)
                ->whereBetween('attendance_date', [$startDate, $endDate . ' 23:59:59'])
                ->sum('overtime_pay'), 2);

            // 4. Employee Pension (7% of basic) & Company Pension (11% of basic)
            $pension        = round($basic * 0.07, 2);
            $companyPension = round($basic * 0.11, 2);

            // 5. Taxable income and tax calculation
            $taxable  = Payroll::calculateTaxableIncome($basic, $house, $position, $transport, $overtimePay);
            $tax      = Payroll::calculateIncomeTax($taxable);

            $payload = [
                'employee_id'       => $emp->id,
                'month'             => $month,
                'year'              => $year,
                'basic_salary'      => $basic,
                'allowances'        => $transport + $house + $position,
                'overtime_pay'      => $overtimePay,
                'pension'           => $pension,
                'company_pension'   => $companyPension,
                'taxable_income'    => $taxable,
                'deductions'        => $totalDeductions,
                'tax'               => round($tax, 2),
                'status'            => 'draft',
                'created_by'        => auth()->id(),
            ];

            if ($hasLoanDed) {
                $payload['loan_deduction']    = $loanDeduction;
                $payload['absence_deduction'] = $absenceDeduction;
                $payload['absent_days']       = $absentDays;
            }

            if ($hasTransport) {
                $payload['transport_allowance'] = $transport;
                $payload['house_allowance']     = $house;
                $payload['position_allowance']  = $position;
            }

            Payroll::create($payload);
            $created++;
        }

        $periodLabel = ($periodData['full_label'] ?? "Month {$month}") . " (" . ($periodData['label_en'] ?? "EC") . ")";
        $dateNotice = " (Period: " . \Carbon\Carbon::parse($startDate)->format('M d, Y') . " – " . \Carbon\Carbon::parse($endDate)->format('M d, Y') . ")";
        $msg = "Generated {$created} payroll entries for {$periodLabel}{$dateNotice}.";
        if ($skipped) $msg .= " {$skipped} already existed and were skipped.";

        return redirect()->route('finance.payroll.index', [
            'period'     => $periodData['period_key'],
            'month'      => $month,
            'year'       => $year,
            'start_date' => $startDate,
            'end_date'   => $endDate,
        ])->with('success', $msg);
    }

    /**
     * Recalculate/Sync payroll for all employees in this period with latest attendance & loans.
     */
    public function recalculate(Request $request)
    {
        $selectedPeriodKey = $request->input('period');
        if ($selectedPeriodKey && str_contains($selectedPeriodKey, '-')) {
            [$ey, $em] = explode('-', $selectedPeriodKey);
            $periodData = EthiopianCalendar::getPayrollPeriod((int)$ey, (int)$em);
            $month = (int) $periodData['eth_month'];
            $year  = (int) $periodData['eth_year'];
            $startDate = $periodData['start_greg'];
            $endDate   = $periodData['end_greg'];
        } else {
            $request->validate([
                'month' => 'required|integer|between:1,13',
                'year'  => 'required|integer|min:2000|max:2100',
            ]);
            $month = (int) $request->month;
            $year  = (int) $request->year;
            $periodData = EthiopianCalendar::getPayrollPeriod($year, $month);
            $startDate  = $request->input('start_date', $periodData['start_greg']);
            $endDate    = $request->input('end_date', $periodData['end_greg']);
        }

        $employees = Employee::activeRoster()->get();
        $updatedCount = 0;
        $createdCount = 0;

        $hasTransport = \Illuminate\Support\Facades\Schema::hasColumn('payrolls', 'transport_allowance');
        $hasLoanDed   = \Illuminate\Support\Facades\Schema::hasColumn('payrolls', 'loan_deduction');

        foreach ($employees as $emp) {
            $basic     = $emp->basic_salary ?? 0;
            $transport = $emp->transport_allowance ?? 0;
            $house     = $emp->house_allowance ?? 0;
            $position  = $emp->position_allowance ?? 0;

            // 1. Salary advance loan monthly deduction
            $loanDeduction = Payroll::calculateLoanDeduction($emp->id);

            // 2. Unexcused attendance absences without approved leave between start date and end date
            $absenceInfo      = Payroll::calculateUnexcusedAbsences($emp->id, $month, $year, $endDate, $startDate);
            $absentDays       = $absenceInfo['days'];
            $dailyRate        = $basic > 0 ? ($basic / 30) : 0;
            $absenceDeduction = round($dailyRate * $absentDays, 2);

            // Total other deductions = loan repayment + absence deduction
            $totalDeductions  = round($loanDeduction + $absenceDeduction, 2);

            // 3. Overtime Pay from approved attendance records between start date and end date
            $overtimePay = round((float) \App\Models\Attendance::where('employee_id', $emp->id)
                ->where('is_approved', true)
                ->whereBetween('attendance_date', [$startDate, $endDate . ' 23:59:59'])
                ->sum('overtime_pay'), 2);

            // 4. Pensions & Taxable Income & Tax
            $pension        = round($basic * 0.07, 2);
            $companyPension = round($basic * 0.11, 2);
            $taxable        = Payroll::calculateTaxableIncome($basic, $house, $position, $transport, $overtimePay);
            $tax            = Payroll::calculateIncomeTax($taxable);

            $payload = [
                'employee_id'       => $emp->id,
                'month'             => $month,
                'year'              => $year,
                'basic_salary'      => $basic,
                'allowances'        => $transport + $house + $position,
                'overtime_pay'      => $overtimePay,
                'pension'           => $pension,
                'company_pension'   => $companyPension,
                'taxable_income'    => $taxable,
                'deductions'        => $totalDeductions,
                'tax'               => round($tax, 2),
            ];

            if ($hasLoanDed) {
                $payload['loan_deduction']    = $loanDeduction;
                $payload['absence_deduction'] = $absenceDeduction;
                $payload['absent_days']       = $absentDays;
            }

            if ($hasTransport) {
                $payload['transport_allowance'] = $transport;
                $payload['house_allowance']     = $house;
                $payload['position_allowance']  = $position;
            }

            $existing = Payroll::where('employee_id', $emp->id)
                ->where('month', $month)
                ->where('year', $year)
                ->first();

            if ($existing) {
                // Only update if not already approved by GM or marked paid
                if ($existing->gm_status !== 'approved' && $existing->status !== 'paid') {
                    $existing->update($payload);
                    $updatedCount++;
                }
            } else {
                $payload['status'] = 'draft';
                $payload['created_by'] = auth()->id();
                Payroll::create($payload);
                $createdCount++;
            }
        }

        $periodLabel = ($periodData['full_label'] ?? "Month {$month}") . " (" . ($periodData['label_en'] ?? "EC") . ")";
        $dateNotice = " (Period: " . \Carbon\Carbon::parse($startDate)->format('M d, Y') . " – " . \Carbon\Carbon::parse($endDate)->format('M d, Y') . ")";
        return redirect()->route('finance.payroll.index', [
            'period'     => $periodData['period_key'],
            'month'      => $month,
            'year'       => $year,
            'start_date' => $startDate,
            'end_date'   => $endDate,
        ])->with('success', "Recalculated payroll for {$periodLabel}{$dateNotice}: {$updatedCount} entries refreshed with latest attendance and loan deductions, {$createdCount} new entries added.");
    }

    /**
     * Submit the entire month's payrolls to GM for approval.
     */
    public function submitToGM(Request $request)
    {
        $request->validate([
            'month' => 'required|integer|between:1,13',
            'year'  => 'required|integer|min:2000|max:2100',
        ]);

        $month = (int) $request->month;
        $year  = (int) $request->year;
        $periodKey = $request->input('period', "{$year}-{$month}");

        $hasGmStatus = \Illuminate\Support\Facades\Schema::hasColumn('payrolls', 'gm_status');

        if (!$hasGmStatus) {
            return redirect()->route('finance.payroll.index', ['period' => $periodKey, 'month' => $month, 'year' => $year])
                             ->with('error', 'Database migration required before submitting to GM. Please run migrations.');
        }

        $updated = Payroll::where('month', $month)
            ->where('year',  $year)
            ->whereNotIn('gm_status', ['approved', 'rejected'])
            ->update([
                'gm_status'          => 'submitted',
                'submitted_to_gm_at' => now(),
            ]);

        $mName = EthiopianCalendar::MONTHS_EN[$month] ?? "Month {$month}";
        $period = "{$mName} {$year} EC";

        return redirect()->route('finance.payroll.index', ['period' => $periodKey, 'month' => $month, 'year' => $year])
                         ->with('success', "{$updated} payroll entries for {$period} submitted to GM for approval.");
    }

    // ─── GM: Approval Inbox ──────────────────────────────────────────────────

    /**
     * GM sees all submitted payroll batches (grouped by month/year).
     */
    public function gmIndex()
    {
        // Check if database table has gm_status column
        $hasGmStatus = \Illuminate\Support\Facades\Schema::hasColumn('payrolls', 'gm_status');

        if (!$hasGmStatus) {
            $batches = collect();
            $history = collect();
            return view('finance.payroll.gm-approval', compact('batches', 'history'));
        }

        // Get distinct month/year combos that have submitted payrolls
        $batches = Payroll::with('employee')
            ->where('gm_status', 'submitted')
            ->select('month', 'year')
            ->selectRaw('COUNT(*) as employee_count')
            ->selectRaw('SUM(basic_salary) as total_basic')
            ->selectRaw('SUM(net_salary) as total_net')
            ->selectRaw('COALESCE(SUM(basic_salary + allowances + overtime_pay), 0) as total_gross')
            ->selectRaw('MIN(submitted_to_gm_at) as submitted_at')
            ->groupBy('year', 'month')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get();

        // Also previously approved/rejected batches
        $history = Payroll::select('month', 'year', 'gm_status')
            ->selectRaw('COUNT(*) as employee_count')
            ->selectRaw('SUM(net_salary) as total_net')
            ->selectRaw('MIN(gm_approved_at) as decided_at')
            ->whereIn('gm_status', ['approved', 'rejected'])
            ->groupBy('year', 'month', 'gm_status')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get();

        return view('finance.payroll.gm-approval', compact('batches', 'history'));
    }

    /**
     * GM views detail of a specific batch before approving/rejecting.
     */
    public function gmBatchDetail(Request $request)
    {
        $selectedPeriodKey = $request->input('period');
        if ($selectedPeriodKey && str_contains($selectedPeriodKey, '-')) {
            [$ey, $em] = explode('-', $selectedPeriodKey);
            $month = (int)$em;
            $year  = (int)$ey;
            $periodData = EthiopianCalendar::getPayrollPeriod($year, $month);
        } else {
            $month = (int) $request->get('month', date('n'));
            $year  = (int) $request->get('year',  date('Y'));
            if ($month >= 1 && $month <= 13 && $year >= 2000 && $year <= 2050) {
                $periodData = EthiopianCalendar::getPayrollPeriod($year, $month);
            } else {
                $periodData = EthiopianCalendar::getCurrentPayrollPeriod();
            }
        }
        $selectedPeriodKey = $periodData['period_key'] ?? "{$year}-{$month}";

        $payrolls = Payroll::with('employee')
            ->where('month', $month)
            ->where('year',  $year)
            ->where('gm_status', 'submitted')
            ->get();

        if ($payrolls->isEmpty()) {
            return redirect()->route('finance.payroll.gm')
                             ->with('error', 'No submitted payrolls found for this period.');
        }

        $hasTransport = \Illuminate\Support\Facades\Schema::hasColumn('payrolls', 'transport_allowance');

        $totals = [
            'basic'           => $payrolls->sum('basic_salary'),
            'transport'       => $hasTransport ? $payrolls->sum('transport_allowance') : 0,
            'house'           => $hasTransport ? $payrolls->sum('house_allowance') : 0,
            'position'        => $hasTransport ? $payrolls->sum('position_allowance') : 0,
            'overtime'        => $payrolls->sum('overtime_pay'),
            'gross'           => $payrolls->sum(function($p) { return $p->gross_salary ?? ($p->basic_salary + $p->allowances + $p->overtime_pay); }),
            'taxable_income'  => $payrolls->sum(function($p) {
                return $p->taxable_income ?? Payroll::calculateTaxableIncome(
                    (float) $p->basic_salary,
                    (float) ($p->house_allowance ?? 0),
                    (float) ($p->position_allowance ?? 0),
                    (float) ($p->transport_allowance ?? 0),
                    (float) ($p->overtime_pay ?? 0)
                );
            }),
            'pension'         => $hasTransport ? $payrolls->sum('pension') : round($payrolls->sum('basic_salary') * 0.07, 2),
            'company_pension' => $payrolls->sum(function($p) { return $p->company_pension ?? round($p->basic_salary * 0.11, 2); }),
            'tax'             => $payrolls->sum('tax'),
            'net'             => $payrolls->sum('net_salary'),
        ];

        return view('finance.payroll.gm-batch-detail', compact('payrolls', 'totals', 'month', 'year', 'periodData', 'selectedPeriodKey'));
    }

    /**
     * GM approves the whole batch for a month/year.
     */
    public function gmApprove(Request $request)
    {
        $request->validate([
            'month'    => 'required|integer|between:1,13',
            'year'     => 'required|integer|min:2000|max:2100',
            'gm_notes' => 'nullable|string|max:500',
        ]);

        $month = (int) $request->month;
        $year  = (int) $request->year;

        Payroll::where('month', $month)
               ->where('year',  $year)
               ->where('gm_status', 'submitted')
               ->update([
                   'gm_status'       => 'approved',
                   'gm_notes'        => $request->gm_notes,
                   'gm_approved_by'  => auth()->id(),
                   'gm_approved_at'  => now(),
                   'status'          => 'pending', // ready for payment
               ]);

        $mName = EthiopianCalendar::MONTHS_EN[$month] ?? "Month {$month}";
        $period = "{$mName} {$year} EC";
        return redirect()->route('finance.payroll.gm')
                         ->with('success', "Payroll for {$period} APPROVED. Finance can now process payment.");
    }

    /**
     * GM rejects the batch.
     */
    public function gmReject(Request $request)
    {
        $request->validate([
            'month'    => 'required|integer|between:1,13',
            'year'     => 'required|integer|min:2000|max:2100',
            'gm_notes' => 'required|string|max:500',
        ]);

        $month = (int) $request->month;
        $year  = (int) $request->year;

        Payroll::where('month', $month)
               ->where('year',  $year)
               ->where('gm_status', 'submitted')
               ->update([
                   'gm_status'      => 'rejected',
                   'gm_notes'       => $request->gm_notes,
                   'gm_approved_by' => auth()->id(),
                   'gm_approved_at' => now(),
               ]);

        $mName = EthiopianCalendar::MONTHS_EN[$month] ?? "Month {$month}";
        $period = "{$mName} {$year} EC";
        return redirect()->route('finance.payroll.gm')
                         ->with('error', "Payroll for {$period} REJECTED. Notes sent to Finance Head.");
    }

    /**
     * Compute batch attendance statistics for given employee IDs in the period date range.
     * Aligns 1:1 with AttendanceController biometric punch & compliance logic.
     */
    protected function getBatchAttendanceStats(array $employeeIds, string $startDate, string $endDate, array $periodDays = []): array
    {
        if (empty($employeeIds)) {
            return [];
        }

        // 1. Batch query attendances in this window
        $attendances = \App\Models\Attendance::whereIn('employee_id', $employeeIds)
            ->whereBetween('attendance_date', [$startDate, $endDate . ' 23:59:59'])
            ->get()
            ->groupBy('employee_id');

        // 2. Approved site deployments
        $siteDeployments = collect();
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('site_deployment_requests')) {
                $siteDeployments = \App\Models\SiteDeploymentRequest::whereIn('employee_id', $employeeIds)
                    ->where('status', 'approved')
                    ->where(function($q) use ($startDate, $endDate) {
                        $q->whereBetween('start_date', [$startDate, $endDate])
                          ->orWhereBetween('end_date', [$startDate, $endDate])
                          ->orWhere(function($sq) use ($startDate, $endDate) {
                              $sq->where('start_date', '<=', $startDate)->where('end_date', '>=', $endDate);
                          });
                    })->get();
            }
        } catch (\Throwable $e) {}

        // 3. Approved leave requests
        $leaves = collect();
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('leave_requests')) {
                $leaves = \App\Models\LeaveRequest::whereIn('employee_id', $employeeIds)
                    ->where('status', 'approved')
                    ->where(function($q) use ($startDate, $endDate) {
                        $q->whereBetween('start_date', [$startDate, $endDate])
                          ->orWhereBetween('end_date', [$startDate, $endDate])
                          ->orWhere(function($sq) use ($startDate, $endDate) {
                              $sq->where('start_date', '<=', $startDate)->where('end_date', '>=', $endDate);
                          });
                    })->get();
            }
        } catch (\Throwable $e) {}

        // 4. If $periodDays is empty, build day list from start to end
        if (empty($periodDays)) {
            $curr = \Carbon\Carbon::parse($startDate)->startOfDay();
            $endC = \Carbon\Carbon::parse($endDate)->startOfDay();
            while ($curr->lte($endC)) {
                $greg = $curr->toDateString();
                $et = EthiopianCalendar::toEthiopian($curr);
                $periodDays[] = [
                    'greg_date'    => $greg,
                    'is_sunday'    => $curr->isSunday(),
                    'is_saturday'  => $curr->isSaturday(),
                    'display_label'=> ($et['day'] ?? '') . ' ' . ($et['month_en'] ?? ''),
                    'eth_label_am' => ($et['month_am'] ?? '') . ' ' . ($et['day'] ?? ''),
                ];
                $curr->addDay();
            }
        }

        $todayStr = today()->toDateString();
        $stats = [];

        foreach ($employeeIds as $empId) {
            $empAtt = ($attendances[$empId] ?? collect())->keyBy(function($item) {
                return $item->attendance_date ? \Carbon\Carbon::parse($item->attendance_date)->toDateString() : '';
            });

            $pCount = 0;
            $sCount = 0;
            $lCount = 0;
            $lateCount = 0;
            $absentCount = 0;
            $detailedAbsences = [];

            foreach ($periodDays as $dayItem) {
                $greg = $dayItem['greg_date'];
                $isSunday = $dayItem['is_sunday'] ?? false;
                $dayLabel = ($dayItem['display_label'] ?? $greg) . ' (' . ($dayItem['eth_label_am'] ?? '') . ')';

                $att = $empAtt->get($greg);

                $hasPunch = $att && (
                    !empty($att->morning_in) ||
                    !empty($att->morning_out) ||
                    !empty($att->afternoon_in) ||
                    !empty($att->afternoon_out) ||
                    !empty($att->check_in) ||
                    !empty($att->check_out) ||
                    (float)($att->hours_worked ?? 0) > 0
                );

                $isLate = false;
                if ($hasPunch) {
                    $lateMinutes = $att->late_minutes ?? 0;
                    $isLate = $lateMinutes > 0 || (!empty($att->morning_in) && substr(trim($att->morning_in), 0, 5) > '08:40');
                }

                // Check approved site deployment
                $siteDepRecord = $siteDeployments->first(function($sd) use ($empId, $greg) {
                    $sdStart = $sd->start_date ? \Carbon\Carbon::parse($sd->start_date)->toDateString() : null;
                    $sdEnd   = $sd->end_date ? \Carbon\Carbon::parse($sd->end_date)->toDateString() : null;
                    return (int)$sd->employee_id === (int)$empId && $sdStart && $sdEnd && $sdStart <= $greg && $sdEnd >= $greg;
                });

                $hasApprovedSite = ($att && ($att->status === 'S' || $att->source === 'site_dispatch' || (method_exists($att, 'isOnSite') && $att->isOnSite())))
                    ? true
                    : ($siteDepRecord !== null);

                // Check approved leave
                $leaveObj = $leaves->first(function($lv) use ($empId, $greg) {
                    $lvStart = $lv->start_date ? \Carbon\Carbon::parse($lv->start_date)->toDateString() : null;
                    $lvEnd   = $lv->end_date ? \Carbon\Carbon::parse($lv->end_date)->toDateString() : null;
                    return (int)$lv->employee_id === (int)$empId && $lvStart && $lvEnd && $lvStart <= $greg && $lvEnd >= $greg;
                });

                if ($isSunday) {
                    if ($hasPunch) {
                        $pCount++;
                    }
                } elseif ($hasApprovedSite) {
                    $sCount++;
                } elseif ($hasPunch) {
                    $pCount++;
                    if ($isLate) {
                        $lateCount++;
                    }
                } elseif ($leaveObj) {
                    $lCount++;
                } else {
                    if ($greg <= $todayStr) {
                        $absentCount++;
                        $detailedAbsences[] = [
                            'greg_date' => $greg,
                            'label'     => "{$dayLabel} — Absent (No punch)",
                            'type'      => 'absent',
                        ];
                    }
                }
            }

            // Late penalty: 3 late days = 1 absent penalty day
            $penaltyDays = intdiv($lateCount, 3);
            if ($penaltyDays > 0) {
                $detailedAbsences[] = [
                    'greg_date' => null,
                    'label'     => "Late Penalty: {$lateCount} late arrivals = {$penaltyDays} absent day(s) penalty (3 Late = 1 Absent rule)",
                    'type'      => 'penalty',
                ];
            }

            $effectiveAbsent = $absentCount + $penaltyDays;

            $stats[$empId] = [
                'present'          => $pCount,
                'site'             => $sCount,
                'leave'            => $lCount,
                'late'             => $lateCount,
                'late_penalty_days'=> $penaltyDays,
                'absent'           => $absentCount,
                'effective_absent' => $effectiveAbsent,
                'detailed_absences'=> $detailedAbsences,
            ];
        }

        return $stats;
    }
}
