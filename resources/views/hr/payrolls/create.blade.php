@extends('layouts.app')
@section('title', 'Generate Payroll')

@section('content')
<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Generate Payroll Entry</h1>
        <a href="{{ route('payrolls.index') }}" class="btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-arrow-left fa-sm text-white-50"></i> Back to List
        </a>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4 border-left-primary">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Payroll Calculation</h6>
                </div>
                <div class="card-body">
                    <form action="{{ route('payrolls.store') }}" method="POST">
                        @csrf
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label class="font-weight-bold">Employee <span class="text-danger">*</span></label>
                                <select name="employee_id" class="form-control" required>
                                    <option value="">Select Employee...</option>
                                    @foreach($employees as $employee)
                                        <option value="{{ $employee->id }}">[{{ $employee->employee_id }}] {{ $employee->first_name }} {{ $employee->last_name }} ({{ $employee->department->name ?? 'N/A' }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="font-weight-bold">Payroll Month <span class="text-danger">*</span></label>
                                <select name="month" class="form-control" required>
                                    @for($m=1; $m<=12; ++$m)
                                        <option value="{{ $m }}" {{ date('m') == $m ? 'selected' : '' }}>{{ date('F', mktime(0, 0, 0, $m, 10)) }}</option>
                                    @endfor
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="font-weight-bold">Payroll Year <span class="text-danger">*</span></label>
                                <input type="number" name="year" class="form-control" value="{{ date('Y') }}" required>
                            </div>
                        </div>

                        <hr>
                        <h6 class="font-weight-bold text-success mb-3">Earnings (+)</h6>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label>Basic Salary <span class="text-danger">*</span></label>
                                <input type="number" name="basic_salary" class="form-control amount-input" step="0.01" min="0" required>
                            </div>
                            <div class="col-md-4">
                                <label>Allowances</label>
                                <input type="number" name="allowances" class="form-control amount-input" step="0.01" min="0" value="0">
                            </div>
                            <div class="col-md-4">
                                <label>Overtime Pay</label>
                                <input type="number" name="overtime_pay" class="form-control amount-input" step="0.01" min="0" value="0">
                            </div>
                        </div>

                        <hr>
                        <h6 class="font-weight-bold text-danger mb-3">Deductions (-)</h6>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label>Other Deductions (Loans, Absences)</label>
                                <input type="number" name="deductions" class="form-control amount-input text-danger" step="0.01" min="0" value="0">
                            </div>
                            <div class="col-md-6">
                                <label>Income Tax (Auto-calculated on Taxable Income)</label>
                                <input type="number" name="tax" class="form-control amount-input text-danger" step="0.01" min="0" value="0">
                            </div>
                        </div>
                        
                        {{-- Live Summary Breakdown --}}
                        <div class="row g-2 mb-3">
                            <div class="col-md-4">
                                <div class="p-2 border rounded bg-light text-center">
                                    <small class="text-muted d-block">Taxable Income</small>
                                    <strong class="text-dark"><span id="taxableDisplay">0.00</span> ETB</strong>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-2 border rounded bg-light text-center">
                                    <small class="text-muted d-block">Emp. Pension (7%)</small>
                                    <strong class="text-secondary"><span id="empPensionDisplay">0.00</span> ETB</strong>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-2 border rounded bg-light text-center">
                                    <small class="text-muted d-block">Co. Pension (11%)</small>
                                    <strong style="color:#6f42c1;"><span id="compPensionDisplay">0.00</span> ETB</strong>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-success border-left-success mt-3">
                            <strong>Estimated Net Pay: </strong><span id="netPayDisplay" class="fw-bold fs-5">0.00</span> ETB
                        </div>

                        <div class="form-group mb-3">
                            <label>Notes / Remarks</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-file-invoice-dollar me-1"></i> Generate Payroll</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const basicInput = document.querySelector('[name="basic_salary"]');
        const allowInput = document.querySelector('[name="allowances"]');
        const overInput  = document.querySelector('[name="overtime_pay"]');
        const dedInput   = document.querySelector('[name="deductions"]');
        const taxInput   = document.querySelector('[name="tax"]');

        const taxableDisplay     = document.getElementById('taxableDisplay');
        const empPensionDisplay  = document.getElementById('empPensionDisplay');
        const compPensionDisplay = document.getElementById('compPensionDisplay');
        const netDisplay         = document.getElementById('netPayDisplay');

        function calculateTax(taxable) {
            if (taxable <= 2000) return 0;
            if (taxable <= 4000) return (taxable * 0.15) - 300;
            if (taxable <= 7000) return (taxable * 0.20) - 500;
            if (taxable <= 10000) return (taxable * 0.25) - 850;
            if (taxable <= 14000) return (taxable * 0.30) - 1350;
            return (taxable * 0.35) - 2050;
        }

        let userCustomizedTax = false;
        taxInput.addEventListener('input', function() {
            userCustomizedTax = true;
            calculateNet();
        });

        function calculateNet() {
            const basic = parseFloat(basicInput.value) || 0;
            const allow = parseFloat(allowInput.value) || 0;
            const over  = parseFloat(overInput.value) || 0;
            const ded   = parseFloat(dedInput.value) || 0;

            // Company Pension = 11% of basic salary
            const compPension = basic * 0.11;
            // Employee Pension = 7% of basic salary
            const empPension = basic * 0.07;
            // Taxable income (pension 7% is NOT subtracted)
            const taxable = basic + allow + over;

            if (!userCustomizedTax) {
                const autoTax = Math.max(0, calculateTax(taxable));
                taxInput.value = autoTax.toFixed(2);
            }

            const tax = parseFloat(taxInput.value) || 0;
            const gross = basic + allow + over;
            const net = gross - empPension - tax - ded;

            taxableDisplay.textContent     = taxable.toFixed(2);
            empPensionDisplay.textContent  = empPension.toFixed(2);
            compPensionDisplay.textContent = compPension.toFixed(2);
            netDisplay.textContent         = net.toFixed(2);
        }

        [basicInput, allowInput, overInput, dedInput].forEach(input => {
            if (input) input.addEventListener('input', calculateNet);
        });
    });
</script>
@endsection
