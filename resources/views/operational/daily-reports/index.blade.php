@extends('layouts.app')
@section('title', 'Daily Field Reports — Site Operations')

@section('content')
<div class="container-fluid px-0">
    {{-- Page Header --}}
    <x-page-header
        title="Daily Field Reports"
        subtitle="Construction site execution logs, daily weather conditions, deployed manpower, and operational milestones recorded by field engineers."
        :breadcrumbs="[
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'Site Operations', 'url' => route('dashboard.site-engineer')],
            ['label' => 'Daily Reports']
        ]">
        <x-slot name="actions">
            @can('approve', \App\Models\DailyReport::class)
                <a href="{{ route('daily-reports.approval') }}" class="btn btn-outline-warning btn-sm rounded-pill px-3 py-2 shadow-xs fw-semibold">
                    <i class="fa-solid fa-clipboard-check me-1.5"></i>Approval Center
                    @if(($statusCounts['submitted'] ?? 0) > 0)
                        <span class="badge bg-danger rounded-pill ms-1">{{ $statusCounts['submitted'] }}</span>
                    @endif
                </a>
            @endcan
            <x-export-menu :print="true" label="Print" />
            <a href="{{ route('daily-reports.create') }}" class="btn btn-primary btn-sm rounded-pill px-3 py-2 shadow-xs fw-semibold">
                <i class="fa-solid fa-plus me-1.5"></i>New Daily Log
            </a>
        </x-slot>
    </x-page-header>

    {{-- Executive Summary KPI Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Total Reports Filed"
                :value="number_format($reports->total())"
                changeLabel="All recorded site logs"
                icon="fa-solid fa-calendar-day"
                color="primary" />
        </div>
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Submitted (In Review)"
                :value="number_format($statusCounts['submitted'] ?? 0)"
                changeLabel="Awaiting approval"
                icon="fa-solid fa-clock"
                color="warning" />
        </div>
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Approved Reports"
                :value="number_format($statusCounts['approved'] ?? 0)"
                changeLabel="Verified by management"
                icon="fa-solid fa-circle-check"
                color="success" />
        </div>
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Draft Logs"
                :value="number_format($statusCounts['draft'] ?? 0)"
                changeLabel="Unsubmitted site entries"
                icon="fa-solid fa-pen-ruler"
                color="secondary" />
        </div>
    </div>

    {{-- Filter Bar --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('daily-reports.index') }}" class="row g-2 align-items-end">
                <div class="col-12 col-sm-6 col-md-3">
                    <label class="form-label text-muted small fw-bold text-uppercase mb-1" style="font-size: 0.72rem;">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="all" {{ request('status') === 'all' || !request('status') ? 'selected' : '' }}>All Statuses</option>
                        <option value="submitted" {{ request('status') === 'submitted' ? 'selected' : '' }}>Submitted (Pending)</option>
                        <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved</option>
                        <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Draft</option>
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-md-3">
                    <label class="form-label text-muted small fw-bold text-uppercase mb-1" style="font-size: 0.72rem;">Project</label>
                    <select name="project_id" class="form-select form-select-sm">
                        <option value="">All Projects</option>
                        @foreach($projects as $p)
                            <option value="{{ $p->id }}" {{ request('project_id') == $p->id ? 'selected' : '' }}>
                                {{ $p->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label text-muted small fw-bold text-uppercase mb-1" style="font-size: 0.72rem;">From Date</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label text-muted small fw-bold text-uppercase mb-1" style="font-size: 0.72rem;">To Date</label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
                </div>
                <div class="col-12 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm rounded-pill px-3 py-2 w-100 fw-semibold shadow-xs">
                        <i class="fa-solid fa-filter me-1.5"></i>Filter
                    </button>
                    <a href="{{ route('daily-reports.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 py-2 shadow-xs" title="Reset filter">
                        <i class="fa-solid fa-rotate-left"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- Daily Reports Table Card --}}
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-2.5 py-1 fw-bold small">
                    <i class="fa-solid fa-list-check me-1"></i>Site Logs
                </span>
                <h6 class="fw-bold mb-0 text-dark">Submitted Daily Work Reports</h6>
            </div>
            <span class="text-muted small">Showing {{ $reports->count() }} of {{ $reports->total() }} logs</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 wc-table-mobile-cards">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4 text-secondary text-uppercase small fw-bold">Report Date</th>
                        <th class="text-secondary text-uppercase small fw-bold">Construction Project</th>
                        <th class="text-secondary text-uppercase small fw-bold">Weather Condition</th>
                        <th class="text-center text-secondary text-uppercase small fw-bold">Total Manpower</th>
                        <th class="text-secondary text-uppercase small fw-bold">Status</th>
                        <th class="text-secondary text-uppercase small fw-bold">Filed By</th>
                        <th class="pe-4 text-end text-secondary text-uppercase small fw-bold">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reports as $report)
                        @php
                            $isApproved = $report->status === 'approved';
                            $isSubmitted = $report->status === 'submitted';
                            $statusBadge = match(strtolower($report->status)) {
                                'approved' => 'bg-success bg-opacity-10 text-success',
                                'submitted' => 'bg-warning bg-opacity-10 text-warning',
                                'rejected' => 'bg-danger bg-opacity-10 text-danger',
                                default => 'bg-secondary bg-opacity-10 text-secondary'
                            };
                        @endphp
                        <tr>
                            <td class="ps-4 font-monospace fw-bold text-dark" data-label="Date">
                                {{ \Carbon\Carbon::parse($report->report_date)->format('M d, Y') }}
                            </td>
                            <td data-label="Project">
                                <div class="fw-bold text-dark">{{ $report->project->name ?? 'N/A' }}</div>
                                @if($report->project->code)
                                    <span class="badge bg-light text-muted border font-monospace" style="font-size: 0.7rem;">{{ $report->project->code }}</span>
                                @endif
                            </td>
                            <td data-label="Weather">
                                <span class="badge bg-light text-dark border">
                                    <i class="fa-solid fa-cloud-sun me-1 text-warning"></i>{{ $report->weather_conditions ?? 'Normal' }}
                                </span>
                            </td>
                            <td class="text-center" data-label="Manpower">
                                <span class="badge bg-info bg-opacity-10 text-info rounded-pill px-3 py-1 font-tabular fw-bold">
                                    <i class="fa-solid fa-users me-1"></i>{{ $report->total_manpower }}
                                </span>
                            </td>
                            <td data-label="Status">
                                <span class="badge {{ $statusBadge }} rounded-pill px-2.5 py-1 fw-semibold">
                                    {{ ucfirst($report->status) }}
                                </span>
                            </td>
                            <td data-label="Filed By">
                                <small class="fw-semibold text-dark">{{ $report->createdBy->name ?? 'Field Engineer' }}</small>
                            </td>
                            <td class="pe-4 text-end" data-label="Action">
                                <a href="{{ route('daily-reports.show', $report) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 shadow-xs fw-semibold">
                                    <i class="fa-solid fa-eye me-1"></i>View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <x-empty-state
                                    title="No Daily Reports Found"
                                    description="No site daily reports match your filter criteria. Click New Daily Log to file today's report."
                                    icon="fa-solid fa-calendar-day" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($reports->hasPages())
            <div class="card-footer bg-white border-0 py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="text-muted small">
                    Showing {{ $reports->firstItem() ?? 0 }} to {{ $reports->lastItem() ?? 0 }} of {{ $reports->total() }} records
                </span>
                <div>
                    {{ $reports->withQueryString()->links() }}
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
