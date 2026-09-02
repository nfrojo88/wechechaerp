@extends('layouts.app')
@section('title', 'HR Dashboard')
@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-users me-2"></i> HR Dashboard</h1>
        <span class="badge badge-secondary p-2">{{ now()->format('l, F j Y') }}</span>
    </div>

    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Active Employees</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $kpi['total_employees'] }}</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-id-badge fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Present Today</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $kpi['present_today'] }}</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-calendar-check fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending Payrolls</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $kpi['pending_payroll'] }}</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-money-bill-wave fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Official Letters Recorded</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $kpi['total_letters'] ?? 0 }}</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-file-signature fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            {{-- Recent Official Employee Letters --}}
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-file-lines me-2"></i> Recent Official Letters &amp; Notices
                    </h6>
                    <div class="d-flex gap-2">
                        <a href="{{ route('employee-letters.create') }}" class="btn btn-sm btn-primary shadow-sm">
                            <i class="fas fa-plus me-1"></i> Issue / Record Letter
                        </a>
                        <a href="{{ route('employee-letters.index') }}" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Ref / Date</th>
                                    <th>Employee</th>
                                    <th>Type</th>
                                    <th>Title</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentLetters ?? [] as $letter)
                                <tr>
                                    <td>
                                        <div class="font-monospace fw-bold small text-primary">{{ $letter->reference_number ?: 'LTR-#'.$letter->id }}</div>
                                        <small class="text-muted">{{ optional($letter->issued_date)->format('M d, Y') }}</small>
                                    </td>
                                    <td>
                                        <div class="fw-bold">{{ $letter->employee->full_name ?? 'N/A' }}</div>
                                        <small class="text-muted">{{ $letter->employee->employee_code ?? '' }}</small>
                                    </td>
                                    <td>
                                        <span class="badge {{ $letter->badge_class }}">{{ $letter->type_label }}</span>
                                    </td>
                                    <td>
                                        <span class="text-truncate d-inline-block" style="max-width: 200px;" title="{{ $letter->title }}">{{ $letter->title }}</span>
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('employee-letters.show', $letter) }}" class="btn btn-sm btn-outline-primary" title="View Letter">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="{{ route('employee-letters.print', $letter) }}" target="_blank" class="btn btn-sm btn-outline-secondary" title="Print Letterhead">
                                            <i class="fas fa-print"></i>
                                        </a>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        <i class="fas fa-envelope-open-text fa-2x mb-2 d-block opacity-25"></i>
                                        No official letters recorded yet. Click "Issue / Record Letter" above.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Recent Payrolls --}}
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-file-invoice-dollar mr-2"></i> Recent Payrolls</h6>
                    <a href="{{ route('payrolls.index') }}" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead class="thead-light"><tr><th>Employee</th><th>Period</th><th>Net Pay</th><th>Status</th></tr></thead>
                        <tbody>
                            @forelse($recentPayrolls as $payroll)
                            @php $net = $payroll->basic_salary + $payroll->allowances + $payroll->overtime_pay - $payroll->deductions - $payroll->tax; @endphp
                            <tr>
                                <td>{{ $payroll->employee->first_name ?? $payroll->employee->full_name ?? 'N/A' }} {{ $payroll->employee->last_name ?? '' }}</td>
                                <td>{{ date('M', mktime(0,0,0,$payroll->month,1)) }} {{ $payroll->year }}</td>
                                <td><strong>${{ number_format($net, 2) }}</strong></td>
                                <td><span class="badge badge-{{ $payroll->status == 'paid' ? 'success' : 'warning' }}">{{ ucfirst($payroll->status) }}</span></td>
                            </tr>
                            @empty
                            <tr><td colspan="4" class="text-center text-muted">No payroll records found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                </div>
                <div class="card-body d-flex flex-column gap-2">
                    <a href="{{ route('employee-letters.create') }}" class="btn btn-primary w-100 py-2 shadow-sm font-weight-bold">
                        <i class="fas fa-file-signature mr-2"></i> Record Official Letter
                    </a>
                    <a href="{{ route('employee-letters.index') }}" class="btn btn-outline-primary w-100 py-2">
                        <i class="fas fa-folder-open mr-2"></i> View Letter Archive
                    </a>
                    <hr class="my-2">
                    <a href="{{ route('payrolls.create') }}" class="btn btn-outline-secondary w-100 text-left"><i class="fas fa-plus mr-2"></i> Generate Payroll</a>
                    <a href="{{ route('employees.create') }}" class="btn btn-outline-success w-100 text-left"><i class="fas fa-user-plus mr-2"></i> Add Employee</a>
                    <a href="{{ route('attendance.create') }}" class="btn btn-outline-info w-100 text-left"><i class="fas fa-clipboard-user mr-2"></i> Mark Attendance</a>
                    <a href="{{ route('manpower-requests.create') }}" class="btn btn-outline-warning text-dark w-100 text-left"><i class="fas fa-person-circle-plus mr-2"></i> Manpower Request</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
