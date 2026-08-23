@extends('layouts.app')
@section('title', 'Payslip Details')

@section('content')
<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Payslip: {{ date('F', mktime(0, 0, 0, $payroll->month, 10)) }} {{ $payroll->year }}</h1>
        <div>
            <a href="{{ route('payrolls.index') }}" class="btn btn-sm btn-secondary shadow-sm">
                <i class="fas fa-arrow-left fa-sm text-white-50"></i> Back
            </a>
            <button class="btn btn-sm btn-primary shadow-sm" onclick="window.print()"><i class="fas fa-print"></i> Print Payslip</button>
        </div>
    </div>

                    @php
                        $empPension = $payroll->pension ?? round($payroll->basic_salary * 0.07, 2);
                        $compPension = $payroll->company_pension ?? round($payroll->basic_salary * 0.11, 2);
                        $taxableInc = $payroll->taxable_income ?? \App\Models\Payroll::calculateTaxableIncome(
                            (float)$payroll->basic_salary,
                            (float)($payroll->house_allowance ?? 0),
                            (float)($payroll->position_allowance ?? 0),
                            (float)($payroll->transport_allowance ?? 0),
                            (float)($payroll->overtime_pay ?? 0)
                        );
                        $totalEarnings = ($payroll->gross_salary ?? ($payroll->basic_salary + $payroll->allowances + $payroll->overtime_pay));
                        $totalDeductions = ($payroll->deductions ?? 0) + ($payroll->tax ?? 0) + $empPension;
                        $netPay = $payroll->net_salary ?? ($totalEarnings - $totalDeductions);
                    @endphp

                    <!-- Financials -->
                    <div class="row">
                        <!-- Earnings -->
                        <div class="col-md-6">
                            <h6 class="font-weight-bold text-success border-bottom pb-2">Earnings</h6>
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td>Basic Salary</td>
                                    <td class="text-right">{{ number_format($payroll->basic_salary, 2) }} ETB</td>
                                </tr>
                                <tr>
                                    <td>Allowances</td>
                                    <td class="text-right">{{ number_format($payroll->allowances, 2) }} ETB</td>
                                </tr>
                                <tr>
                                    <td>Overtime Pay</td>
                                    <td class="text-right">{{ number_format($payroll->overtime_pay, 2) }} ETB</td>
                                </tr>
                                <tr class="font-weight-bold border-top">
                                    <td>Gross Earnings</td>
                                    <td class="text-right text-success">{{ number_format($totalEarnings, 2) }} ETB</td>
                                </tr>
                                <tr class="text-muted small">
                                    <td>Taxable Income</td>
                                    <td class="text-right">{{ number_format($taxableInc, 2) }} ETB</td>
                                </tr>
                            </table>
                        </div>
                        <!-- Deductions -->
                        <div class="col-md-6">
                            <h6 class="font-weight-bold text-danger border-bottom pb-2">Deductions & Contributions</h6>
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td>Emp. Pension (7%)</td>
                                    <td class="text-right text-danger">{{ number_format($empPension, 2) }} ETB</td>
                                </tr>
                                <tr>
                                    <td>Income Tax</td>
                                    <td class="text-right text-danger">{{ number_format($payroll->tax, 2) }} ETB</td>
                                </tr>
                                <tr>
                                    <td>Other Deductions</td>
                                    <td class="text-right text-danger">{{ number_format($payroll->deductions, 2) }} ETB</td>
                                </tr>
                                <tr class="font-weight-bold border-top">
                                    <td>Total Employee Deductions</td>
                                    <td class="text-right text-danger">{{ number_format($totalDeductions, 2) }} ETB</td>
                                </tr>
                                <tr class="text-muted small">
                                    <td style="color:#6f42c1;">Co. Pension (11% Employer)</td>
                                    <td class="text-right" style="color:#6f42c1;">{{ number_format($compPension, 2) }} ETB</td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Net Pay -->
                    <div class="row mt-4">
                        <div class="col-12 text-right">
                            <div class="p-3 bg-light rounded d-inline-block border-left-primary">
                                <span class="text-uppercase text-muted font-weight-bold mr-3">Net Take-Home Pay:</span>
                                <h3 class="mb-0 font-weight-bold text-success d-inline-block">{{ number_format($netPay, 2) }} ETB</h3>
                            </div>
                        </div>
                    </div>

                    @if($payroll->notes)
                    <div class="row mt-4">
                        <div class="col-12">
                            <p class="text-muted small"><strong>Remarks:</strong> {{ $payroll->notes }}</p>
                        </div>
                    </div>
                    @endif
                </div>

                @if($payroll->status == 'pending')
                <div class="card-footer text-center">
                    <form action="{{ route('payrolls.markPaid', $payroll) }}" method="POST">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn btn-success btn-lg px-5 shadow"><i class="fas fa-check-double"></i> Mark as Paid</button>
                    </form>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

<style>
    @media print {
        .navbar, .sidebar, .btn, footer { display: none !important; }
        .card { border: none !important; box-shadow: none !important; }
        body { background-color: #fff !important; }
    }
</style>
@endsection
