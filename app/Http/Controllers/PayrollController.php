<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Payroll;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PayrollController extends Controller
{
    public function index()
    {
        Gate::authorize('viewAny', Payroll::class);
        $payrolls = Payroll::with('employee')
            ->orderByDesc('year')->orderByDesc('month')
            ->paginate(25);
        return view('hr.payrolls.index', compact('payrolls'));
    }

    public function create()
    {
        Gate::authorize('create', Payroll::class);
        $employees = Employee::where('status', 'active')->get();
        return view('hr.payrolls.create', compact('employees'));
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Payroll::class);

        $validated = $request->validate([
            'employee_id'  => 'required|exists:employees,id',
            'month'        => 'required|integer|between:1,12',
            'year'         => 'required|integer|min:2020|max:2099',
            'basic_salary' => 'required|numeric|min:0',
            'allowances'   => 'nullable|numeric|min:0',
            'overtime_pay' => 'nullable|numeric|min:0',
            'deductions'   => 'nullable|numeric|min:0',
            'tax'          => 'nullable|numeric|min:0',
            'notes'        => 'nullable|string',
        ]);

        $basic = (float) $validated['basic_salary'];
        $allow = (float) ($validated['allowances'] ?? 0);
        $ot    = (float) ($validated['overtime_pay'] ?? 0);

        $validated['created_by']      = auth()->id();
        $validated['allowances']      = $allow;
        $validated['overtime_pay']    = $ot;
        $validated['pension']         = round($basic * 0.07, 2);
        $validated['company_pension'] = round($basic * 0.11, 2);
        $validated['taxable_income']  = round($basic + $allow + $ot, 2);
        $validated['deductions']      = $validated['deductions']  ?? 0;
        $validated['tax']             = $validated['tax'] ?? Payroll::calculateIncomeTax($validated['taxable_income']);

        $payroll = Payroll::create($validated);

        return redirect()->route('payrolls.show', $payroll)
            ->with('success', 'Payroll entry created.');
    }

    public function show(Payroll $payroll)
    {
        Gate::authorize('view', $payroll);
        $payroll->load('employee');
        return view('hr.payrolls.show', compact('payroll'));
    }

    public function markPaid(Payroll $payroll)
    {
        Gate::authorize('update', $payroll);

        $payroll->update([
            'status'  => 'paid',
            'paid_at' => now(),
        ]);

        // Auto-recover advance loans linked to this employee
        if (\Illuminate\Support\Facades\Schema::hasTable('employee_advances')) {
            $advances = \App\Models\EmployeeAdvance::where('employee_id', $payroll->employee_id)
                ->where('status', 'disbursed')
                ->get();

            foreach ($advances as $adv) {
                // If this is the last installment or single installment, mark recovered
                $adv->update([
                    'status'       => 'recovered',
                    'recovered_at' => now(),
                ]);
            }
        }

        return back()->with('success', 'Payroll marked as paid and advance loan deductions updated.');
    }
}
