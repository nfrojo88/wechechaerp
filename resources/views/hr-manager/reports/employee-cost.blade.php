@extends('layouts.app')
@section('title', 'HR Employee Cost Breakdown - Construct-Pro ERP')

@section('content')
<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="h3 mb-1 fw-bold">
                <i class="fa-solid fa-id-card-clip text-primary me-2"></i>Employee Cost Breakdown
            </h2>
            <p class="text-muted small mb-0">Detailed salary, allowances, taxes, deductions, and net payouts per employee</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('reports.attendance') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-arrow-left me-1"></i>All Reports
            </a>
        </div>
    </div>

    <!-- Navigation Report Tabs -->
    <div class="mb-4">
        <div class="nav nav-pills flex-column flex-sm-row gap-2 bg-light p-2 rounded-4 border">
            <a href="{{ route('reports.attendance') }}" class="nav-link rounded-pill px-3 fw-semibold text-secondary">
                <i class="fa-solid fa-calendar-check me-1"></i>Attendance Report
            </a>
            <a href="{{ route('reports.turnover') }}" class="nav-link rounded-pill px-3 fw-semibold text-secondary">
                <i class="fa-solid fa-user-slash me-1"></i>Turnover &amp; Retention
            </a>
            <a href="{{ route('reports.cost-analysis') }}" class="nav-link rounded-pill px-3 fw-semibold text-secondary">
                <i class="fa-solid fa-money-bill-trend-up me-1"></i>Payroll Cost Analysis
            </a>
            <a href="{{ route('reports.leave-analysis') }}" class="nav-link rounded-pill px-3 fw-semibold text-secondary">
                <i class="fa-solid fa-plane-departure me-1"></i>Leave Analysis
            </a>
            <a href="{{ route('reports.employee-cost') }}" class="nav-link active rounded-pill px-3 fw-semibold">
                <i class="fa-solid fa-id-card-clip me-1"></i>Employee Cost Breakdown
            </a>
        </div>
    </div>

    <!-- Employee Cost Table -->
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-light py-3 border-0 rounded-top-4">
            <h5 class="fw-bold mb-0 text-dark">
                <i class="fa-solid fa-users me-2 text-primary"></i>Staff Cost Breakdown for {{ Carbon\Carbon::create($year, $month)->format('F Y') }}
            </h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Employee</th>
                        <th>Department</th>
                        <th class="text-end">Base Salary (ETB)</th>
                        <th class="text-end">Allowances</th>
                        <th class="text-end">Gross Salary</th>
                        <th class="text-end">Deductions</th>
                        <th class="text-end">Tax</th>
                        <th class="text-end">Net Pay (ETB)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($employeeCosts as $row)
                    <tr>
                        <td>
                            <div class="fw-bold text-dark">{{ $row['employee']->name ?? $row['employee']->full_name }}</div>
                            <small class="text-muted">{{ $row['employee']->employee_id ?? 'EMP-' . $row['employee']->id }}</small>
                        </td>
                        <td>
                            <span class="badge bg-secondary bg-opacity-10 text-secondary border">
                                {{ $row['employee']->department->name ?? ($row['employee']->department_name ?? 'General') }}
                            </span>
                        </td>
                        <td class="text-end fw-semibold">{{ number_format($row['basic_salary'], 2) }}</td>
                        <td class="text-end text-success">+{{ number_format($row['allowances'], 2) }}</td>
                        <td class="text-end fw-bold text-primary">{{ number_format($row['gross_salary'], 2) }}</td>
                        <td class="text-end text-danger">-{{ number_format($row['deductions'], 2) }}</td>
                        <td class="text-end text-warning">{{ number_format($row['tax'], 2) }}</td>
                        <td class="text-end fw-bold text-success">{{ number_format($row['net_salary'], 2) }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">No employee cost records found for this period.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
