<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payroll extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id', 'month', 'year',
        'basic_salary',
        'transport_allowance', 'house_allowance', 'position_allowance',
        'allowances',      // total allowances (sum of transport+house+position)
        'overtime_pay',
        'pension',         // 7% of basic (employee contribution)
        'company_pension', // 11% of basic (employer contribution)
        'taxable_income',  // gross income WITHOUT deducting pension — used for income tax calculation
        'loan_deduction',  // salary advance monthly payment
        'absence_deduction', // missed attendance without approved leave deduction (basic/30 * days)
        'absent_days',     // number of unexcused absent days
        'deductions',      // total other deductions (loan + absence + misc)
        'tax',             // income tax
        'gross_salary',
        'net_salary',
        'status',          // draft | pending | paid
        'gm_status',       // null | submitted | approved | rejected
        'gm_notes',
        'gm_approved_by',
        'gm_approved_at',
        'submitted_to_gm_at',
        'paid_at', 'created_by', 'notes',
        'payroll_ref', 'remarks',
        'payment_method', 'processed_at', 'processed_by',
    ];

    protected $casts = [
        'paid_at'             => 'datetime',
        'processed_at'        => 'datetime',
        'gm_approved_at'      => 'datetime',
        'submitted_to_gm_at'  => 'datetime',
        'basic_salary'        => 'decimal:2',
        'transport_allowance' => 'decimal:2',
        'house_allowance'     => 'decimal:2',
        'position_allowance'  => 'decimal:2',
        'allowances'          => 'decimal:2',
        'overtime_pay'        => 'decimal:2',
        'pension'             => 'decimal:2',
        'company_pension'     => 'decimal:2',
        'taxable_income'      => 'decimal:2',
        'loan_deduction'      => 'decimal:2',
        'absence_deduction'   => 'decimal:2',
        'absent_days'         => 'integer',
        'deductions'          => 'decimal:2',
        'tax'                 => 'decimal:2',
        'net_salary'          => 'decimal:2',
        'gross_salary'        => 'decimal:2',
    ];

    public function employee()   { return $this->belongsTo(Employee::class); }
    public function creator()    { return $this->belongsTo(User::class, 'created_by'); }
    public function processedBy(){ return $this->belongsTo(User::class, 'processed_by'); }
    public function gmApprover() { return $this->belongsTo(User::class, 'gm_approved_by'); }
    public function components() { return $this->hasMany(PayrollComponent::class); }
    public function adjustments(){ return $this->hasMany(PayrollAdjustment::class); }

    /** Auto-calculate gross, net, taxable income & company pension before every save */
    protected static function booted()
    {
        static::saving(function (Payroll $p) {
            $hasTransport = \Illuminate\Support\Facades\Schema::hasColumn('payrolls', 'transport_allowance');

            if ($hasTransport) {
                // Total allowances = individual parts if available
                $p->allowances  = ($p->transport_allowance ?? 0)
                                + ($p->house_allowance     ?? 0)
                                + ($p->position_allowance  ?? 0);

                // Employee Pension = 7% of basic
                $p->pension     = round(($p->basic_salary ?? 0) * 0.07, 2);

                // Company Pension = 11% of basic (employer contribution)
                $p->company_pension = round(($p->basic_salary ?? 0) * 0.11, 2);
            }

            // Gross = basic + allowances + overtime
            $p->gross_salary = ($p->basic_salary  ?? 0)
                             + ($p->allowances     ?? 0)
                             + ($p->overtime_pay   ?? 0);

            // Taxable Income = Gross (does NOT deduct employee pension)
            // Ethiopian tax law: taxable income = basic + house + position + max(0, transport-2200) + overtime
            // i.e., pension 7% is NOT subtracted before calculating income tax
            $transport       = $p->transport_allowance ?? 0;
            $taxableTransport = max(0, $transport - 2200);
            $p->taxable_income = round(
                ($p->basic_salary       ?? 0)
              + ($p->house_allowance    ?? 0)
              + ($p->position_allowance ?? 0)
              + $taxableTransport
              + ($p->overtime_pay       ?? 0),
            2);

            // Ensure deductions includes loan + absence deductions if set
            if (($p->loan_deduction !== null || $p->absence_deduction !== null) && ($p->loan_deduction > 0 || $p->absence_deduction > 0)) {
                $p->deductions = round(($p->loan_deduction ?? 0) + ($p->absence_deduction ?? 0), 2);
            }

            // Net = gross − employee pension − tax − other deductions
            $p->net_salary  = $p->gross_salary
                            - ($p->pension     ?? 0)
                            - ($p->tax         ?? 0)
                            - ($p->deductions  ?? 0);
        });
    }

    /**
     * Calculate unexcused absences (days absent without an approved leave request).
     */
    public static function calculateUnexcusedAbsences(int $employeeId, int $month, int $year): array
    {
        $absentRecords = Attendance::where('employee_id', $employeeId)
            ->whereYear('attendance_date', $year)
            ->whereMonth('attendance_date', $month)
            ->where('status', 'absent')
            ->get();

        $unexcusedDays = 0;
        foreach ($absentRecords as $att) {
            $date = $att->attendance_date;
            $hasApprovedLeave = LeaveRequest::where('employee_id', $employeeId)
                ->where('status', 'approved')
                ->whereDate('start_date', '<=', $date)
                ->whereDate('end_date', '>=', $date)
                ->exists();

            if (!$hasApprovedLeave) {
                $unexcusedDays++;
            }
        }

        return [
            'days' => $unexcusedDays,
        ];
    }

    /**
     * Calculate monthly salary advance loan deduction for an employee.
     */
    public static function calculateLoanDeduction(int $employeeId): float
    {
        $loanDeduction = 0;
        if (\Illuminate\Support\Facades\Schema::hasTable('employee_advances')) {
            $activeAdvances = \App\Models\EmployeeAdvance::where('employee_id', $employeeId)
                ->where('status', 'disbursed')
                ->get();

            foreach ($activeAdvances as $adv) {
                $monthly = $adv->installments > 0 ? round($adv->amount / $adv->installments, 2) : $adv->amount;
                $loanDeduction += $monthly;
            }
        }
        return round($loanDeduction, 2);
    }

    public function isPaid() { return $this->status === 'paid' && $this->paid_at !== null; }

    public function getPeriodAttribute()
    {
        return date('F Y', strtotime($this->year . '-' . $this->month . '-01'));
    }

    /**
     * Calculate taxable income (WITHOUT deducting 7% pension).
     * Ethiopian rule: taxable = basic + house + position + max(0, transport-2200) + overtime
     * Pension is NOT deducted before tax calculation.
     */
    public static function calculateTaxableIncome(float $basic, float $house, float $position, float $transport, float $overtime = 0): float
    {
        $taxableTransport = max(0, $transport - 2200);
        $taxable = $basic + $house + $position + $taxableTransport + $overtime;
        return max(0, round($taxable, 2));
    }

    /** Ethiopian income tax calculation */
    public static function calculateIncomeTax(float $taxableIncome): float
    {
        if ($taxableIncome <= 2000)  return 0;
        if ($taxableIncome <= 4000)  return ($taxableIncome * 0.15) - 300;
        if ($taxableIncome <= 7000)  return ($taxableIncome * 0.20) - 500;
        if ($taxableIncome <= 10000) return ($taxableIncome * 0.25) - 850;
        if ($taxableIncome <= 14000) return ($taxableIncome * 0.30) - 1350;
        return ($taxableIncome * 0.35) - 2050;
    }

    /**
     * Calculate overtime pay.
     *
     * OT types & coefficients:
     *   holiday   → × 2.5  (public holiday)
     *   rest_day  → × 2.0  (Sunday full day OR Saturday after normal hours)
     *   night_12_4→ × 1.5  (00:00 – 04:00)
     *   night_4_12→ × 1.75 (16:00 – 00:00)
     *
     * Formula: basic / 30 / 8 × coefficient × hours
     */
    public static function calculateOvertimePay(float $basic, float $hours, string $type): float
    {
        if ($hours <= 0) return 0;

        $coefficients = [
            'holiday'    => 2.5,
            'rest_day'   => 2.0,
            'night_12_4' => 1.5,
            'night_4_12' => 1.75,
            'none'       => 0,
        ];

        $coefficient = $coefficients[$type] ?? 0;
        if ($coefficient <= 0) return 0;

        $hourlyRate = $basic / 30 / 8;
        return round($hourlyRate * $coefficient * $hours, 2);
    }
}


