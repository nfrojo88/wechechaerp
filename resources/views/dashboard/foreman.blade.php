@extends('layouts.app')
@section('title', 'Foreman Dashboard')
@section('content')
<div class="wc-container py-3 py-md-4">
    <x-page-header
        title="Foreman Dashboard"
        subtitle="Site crew attendance, material requisition, daily reports &amp; obstacle logs"
        badge="Field Operations"
    >
        <x-slot name="actions">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="badge bg-white text-secondary border px-3 py-2 shadow-xs rounded-pill d-none d-sm-inline-flex align-items-center">
                    <i class="fa-regular fa-calendar text-primary me-2"></i>{{ now()->format('l, F j, Y') }}
                </span>
                <a href="{{ route('material-requests.create') }}" class="btn btn-primary btn-sm shadow-xs" style="min-height: 38px; display: inline-flex; align-items: center;">
                    <i class="fas fa-plus me-1"></i> New Material Request
                </a>
            </div>
        </x-slot>
    </x-page-header>

    {{-- KPI Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <x-kpi-card
                title="Present Today (Site)"
                :value="$kpi['attendance_today'] ?? 0"
                subtitle="Workers on site"
                icon="fa-solid fa-users"
                tone="primary"
            />
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <x-kpi-card
                title="Pending Material Requests"
                :value="$kpi['my_mr_pending'] ?? 0"
                subtitle="Awaiting store / approval"
                icon="fa-solid fa-cart-flatbed"
                tone="danger"
            />
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <x-kpi-card
                title="Daily Reports (This Mo.)"
                :value="$kpi['daily_reports'] ?? 0"
                subtitle="Submitted site logs"
                icon="fa-solid fa-file-contract"
                tone="success"
            />
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <x-kpi-card
                title="Open Site Issues"
                :value="$kpi['issues_open'] ?? 0"
                subtitle="Obstacles needing action"
                icon="fa-solid fa-triangle-exclamation"
                tone="warning"
            />
        </div>
    </div>

    {{-- Main Content Grid --}}
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 bg-white">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h6 class="m-0 fw-bold text-dark">
                        <i class="fas fa-box text-primary me-2"></i>My Recent Material Requests
                    </h6>
                    <a href="{{ route('material-requests.index') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3" style="min-height: 34px; display: inline-flex; align-items: center;">
                        View All <i class="fa-solid fa-arrow-right ms-1"></i>
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive wc-table-mobile-cards">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Request ID</th>
                                    <th>Project</th>
                                    <th>Created</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentDaily as $mr)
                                <tr>
                                    <td data-label="Request ID" class="ps-3 fw-bold text-primary">#{{ $mr->id }}</td>
                                    <td data-label="Project">{{ $mr->project->name ?? 'N/A' }}</td>
                                    <td data-label="Created" class="small text-muted">{{ $mr->created_at->format('M d, Y') }}</td>
                                    <td data-label="Status">
                                        <x-status-badge :status="$mr->status" />
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">No recent material requests found.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm border-0 bg-white">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="m-0 fw-bold text-dark"><i class="fa-solid fa-bolt text-warning me-2"></i>Quick Actions</h6>
                </div>
                <div class="card-body d-flex flex-column gap-2 p-3">
                    <a href="{{ route('material-requests.create') }}" class="btn btn-primary text-start fw-semibold py-2 d-flex align-items-center" style="min-height: 44px;">
                        <i class="fas fa-plus me-2"></i> New Material Request
                    </a>
                    <a href="{{ route('issues.create') }}" class="btn btn-outline-danger text-start fw-semibold py-2 d-flex align-items-center" style="min-height: 44px;">
                        <i class="fas fa-bug me-2"></i> Report Issue / Obstacle
                    </a>
                    <a href="{{ route('waste.create') }}" class="btn btn-outline-warning text-dark text-start fw-semibold py-2 d-flex align-items-center" style="min-height: 44px;">
                        <i class="fas fa-trash me-2"></i> Log Scrap &amp; Waste
                    </a>
                    <a href="{{ route('attendance.index') }}" class="btn btn-outline-success text-start fw-semibold py-2 d-flex align-items-center" style="min-height: 44px;">
                        <i class="fas fa-user-check me-2"></i> Attendance Roster
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
