@extends('layouts.app')
@section('title', 'Expiring Contracts')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2 pb-2 border-bottom">
        <div>
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('contracts.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-xs">
                    <i class="fa-solid fa-arrow-left me-1"></i>All Contracts
                </a>
                <span class="badge bg-warning bg-opacity-10 text-warning-emphasis px-3 py-1 rounded-pill fw-semibold">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i>Contract Expiration Alerts
                </span>
            </div>
            <h1 class="h3 mb-0 text-dark fw-bold mt-2">
                <i class="fa-solid fa-hourglass-end text-warning me-2"></i>Expiring Employee Contracts
            </h1>
            <p class="text-muted small mb-0 mt-1">
                Monitor upcoming contract expirations to initiate renewal, conversion, or transition plans.
            </p>
        </div>
        <div>
            <a href="{{ route('contracts.create') }}" class="btn btn-primary btn-sm rounded-pill px-3 shadow-xs">
                <i class="fa-solid fa-plus me-1"></i>New Contract
            </a>
        </div>
    </div>

    {{-- Expiring within 30 Days --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4" style="border-left: 4px solid #ef4444 !important;">
        <div class="card-header bg-white border-0 py-3 px-4 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold text-danger mb-0">
                <i class="fa-solid fa-fire me-2"></i>Expiring Within 30 Days (Urgent Action Required)
            </h5>
            <span class="badge bg-danger rounded-pill px-3 py-1">{{ $expiringIn30->count() }} Contract(s)</span>
        </div>
        <div class="card-body p-0">
            @if($expiringIn30->isNotEmpty())
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size:0.88rem;">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Employee</th>
                                <th>Contract #</th>
                                <th>Type</th>
                                <th>End Date</th>
                                <th>Days Left</th>
                                <th>Salary</th>
                                <th class="pe-4 text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($expiringIn30 as $c)
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-bold text-dark">{{ $c->employee->full_name ?? ($c->employee->first_name . ' ' . $c->employee->last_name) }}</div>
                                        <small class="text-muted">{{ $c->employee->designation ?? 'Staff' }}</small>
                                    </td>
                                    <td class="font-monospace fw-bold text-dark">{{ $c->contract_number }}</td>
                                    <td><span class="badge bg-secondary rounded-pill">{{ $c->contract_type }}</span></td>
                                    <td class="fw-bold text-danger">{{ $c->end_date ? $c->end_date->format('M d, Y') : '—' }}</td>
                                    <td>
                                        <span class="badge bg-danger rounded-pill px-2 py-1">
                                            {{ now()->diffInDays($c->end_date, false) }} days
                                        </span>
                                    </td>
                                    <td class="font-monospace text-success fw-bold">{{ number_format($c->salary, 2) }} ETB</td>
                                    <td class="pe-4 text-end">
                                        <a href="{{ route('contracts.show', $c) }}" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                                            Manage / Renew
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="p-4 text-center text-muted small">
                    <i class="fa-solid fa-circle-check text-success fs-4 mb-2 d-block"></i>
                    No contracts expiring within the next 30 days.
                </div>
            @endif
        </div>
    </div>

    {{-- Expiring within 31-90 Days --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4" style="border-left: 4px solid #f59e0b !important;">
        <div class="card-header bg-white border-0 py-3 px-4 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold text-warning-emphasis mb-0">
                <i class="fa-solid fa-clock me-2 text-warning"></i>Expiring Within 31 to 90 Days (Upcoming)
            </h5>
            <span class="badge bg-warning text-dark rounded-pill px-3 py-1">{{ $expiringIn90->count() }} Contract(s)</span>
        </div>
        <div class="card-body p-0">
            @if($expiringIn90->isNotEmpty())
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size:0.88rem;">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Employee</th>
                                <th>Contract #</th>
                                <th>Type</th>
                                <th>End Date</th>
                                <th>Days Left</th>
                                <th>Salary</th>
                                <th class="pe-4 text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($expiringIn90 as $c)
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-bold text-dark">{{ $c->employee->full_name ?? ($c->employee->first_name . ' ' . $c->employee->last_name) }}</div>
                                        <small class="text-muted">{{ $c->employee->designation ?? 'Staff' }}</small>
                                    </td>
                                    <td class="font-monospace fw-bold text-dark">{{ $c->contract_number }}</td>
                                    <td><span class="badge bg-secondary rounded-pill">{{ $c->contract_type }}</span></td>
                                    <td class="fw-semibold text-dark">{{ $c->end_date ? $c->end_date->format('M d, Y') : '—' }}</td>
                                    <td>
                                        <span class="badge bg-warning text-dark rounded-pill px-2 py-1">
                                            {{ now()->diffInDays($c->end_date, false) }} days
                                        </span>
                                    </td>
                                    <td class="font-monospace text-success fw-bold">{{ number_format($c->salary, 2) }} ETB</td>
                                    <td class="pe-4 text-end">
                                        <a href="{{ route('contracts.show', $c) }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                                            View Details
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="p-4 text-center text-muted small">
                    <i class="fa-solid fa-circle-check text-success fs-4 mb-2 d-block"></i>
                    No contracts expiring in the 31-90 day window.
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
