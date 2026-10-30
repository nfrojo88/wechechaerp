@extends('layouts.app')
@section('title', 'Weekly Manpower Report — Wechecha ERP')

@section('content')
<div class="container-fluid px-0">
    <x-print-header title="Weekly Manpower & Site Deployment Report" :subtitle="'Week #' . $weekNumber" :dateRange="\Carbon\Carbon::parse($startDate)->format('M d, Y') . ' to ' . \Carbon\Carbon::parse($endDate)->format('M d, Y')" />

    {{-- Page Header --}}
    <x-page-header
        title="Weekly Manpower & Site Deployment Report"
        :subtitle="'Week #' . $weekNumber . ' — ' . \Carbon\Carbon::parse($startDate)->format('M d, Y') . ' to ' . \Carbon\Carbon::parse($endDate)->format('M d, Y')"
        :breadcrumbs="[
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'HR Management', 'url' => route('dashboard.hr')],
            ['label' => 'Weekly Manpower']
        ]">
        <x-slot name="actions">
            <x-export-menu :csvUrl="route('weekly-manpower.export', ['start_date' => $startDate, 'end_date' => $endDate])" :print="true" />
            <button type="button" class="btn btn-primary btn-sm rounded-pill px-3 py-2 shadow-xs fw-semibold" data-bs-toggle="modal" data-bs-target="#sendGMModal">
                <i class="fa-solid fa-paper-plane me-1.5"></i>Send to GM
            </button>
        </x-slot>
    </x-page-header>

    {{-- Date Range & Project Filter Bar --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('weekly-manpower.index') }}" class="row g-2 align-items-end">
                <div class="col-12 col-sm-6 col-md-3">
                    <label class="form-label text-muted small fw-bold text-uppercase mb-1" style="font-size: 0.72rem;">Start Date</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="{{ $startDate }}" required>
                </div>
                <div class="col-12 col-sm-6 col-md-3">
                    <label class="form-label text-muted small fw-bold text-uppercase mb-1" style="font-size: 0.72rem;">End Date</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="{{ $endDate }}" required>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label text-muted small fw-bold text-uppercase mb-1" style="font-size: 0.72rem;">Project Filter</label>
                    <select name="project_id" class="form-select form-select-sm">
                        <option value="">All Projects</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}" @selected(request('project_id') == $project->id)>
                                {{ $project->project_name ?? $project->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm rounded-pill px-3 py-2 w-100 fw-semibold shadow-xs">
                        <i class="fa-solid fa-filter me-1.5"></i>Filter
                    </button>
                    <a href="{{ route('weekly-manpower.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 py-2 shadow-xs" title="Reset filter">
                        <i class="fa-solid fa-rotate-left"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- Executive Summary KPI Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Total Mandays"
                :value="number_format($summary['total_mandays'] ?? 0)"
                changeLabel="Cumulative site work shifts"
                icon="fa-solid fa-people-group"
                color="primary" />
        </div>
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Avg Daily Manpower"
                :value="number_format($summary['avg_daily_manpower'] ?? 0, 1)"
                changeLabel="Mean headcount / day"
                icon="fa-solid fa-calculator"
                color="info" />
        </div>
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Peak Manpower Day"
                :value="number_format($summary['peak_manpower_day'] ?? 0)"
                changeLabel="Maximum workers on single day"
                icon="fa-solid fa-arrow-trend-up"
                color="success" />
        </div>
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Active Projects"
                :value="number_format($summary['projects_count'] ?? 0)"
                changeLabel="Sites reporting attendance"
                icon="fa-solid fa-city"
                color="warning" />
        </div>
    </div>

    {{-- Visual Chart & Project Breakdown --}}
    <div class="row g-3 mb-4">
        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden">
                <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <span class="p-2 rounded-3 bg-primary bg-opacity-10 text-primary fs-6">
                            <i class="fa-solid fa-chart-line"></i>
                        </span>
                        <h6 class="fw-bold mb-0 text-dark">Daily Manpower Deployment Trend</h6>
                    </div>
                </div>
                <div class="card-body p-4">
                    <canvas id="manpowerChart" height="90"></canvas>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden">
                <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <span class="p-2 rounded-3 bg-info bg-opacity-10 text-info fs-6">
                            <i class="fa-solid fa-building"></i>
                        </span>
                        <h6 class="fw-bold mb-0 text-dark">Manpower by Project</h6>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3 text-secondary text-uppercase fw-bold">Project</th>
                                <th class="text-center text-secondary text-uppercase fw-bold">Manpower</th>
                                <th class="pe-3 text-end text-secondary text-uppercase fw-bold">Avg/Day</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($summary['by_project'] ?? [] as $proj)
                                <tr>
                                    <td class="ps-3 fw-semibold text-dark">{{ $proj['name'] }}</td>
                                    <td class="text-center font-tabular fw-bold text-primary">{{ $proj['manpower'] }}</td>
                                    <td class="pe-3 text-end font-tabular text-muted">{{ $proj['avg_daily'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center py-4 text-muted">No site allocations found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Daily Breakdown Table --}}
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-2.5 py-1 fw-bold small">
                    <i class="fa-solid fa-calendar-day me-1"></i>Daily Log
                </span>
                <h6 class="fw-bold mb-0 text-dark">Daily Workforce Breakdown</h6>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 wc-table-mobile-cards">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4 text-secondary text-uppercase small fw-bold">Date</th>
                        <th class="text-center text-secondary text-uppercase small fw-bold">Total Deployed Manpower</th>
                        <th class="text-center text-secondary text-uppercase small fw-bold">Daily Reports Filed</th>
                        <th class="pe-4 text-center text-secondary text-uppercase small fw-bold">Reporting Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($summary['daily_breakdown'] ?? [] as $date => $day)
                        <tr>
                            <td class="ps-4 fw-semibold text-dark" data-label="Date">
                                {{ $day['date'] }}, {{ \Carbon\Carbon::parse($date)->format('M d, Y') }}
                            </td>
                            <td class="text-center" data-label="Manpower">
                                <span class="badge bg-info bg-opacity-10 text-info rounded-pill px-3 py-1 font-tabular fw-bold">
                                    <i class="fa-solid fa-users me-1"></i>{{ $day['manpower'] }} workers
                                </span>
                            </td>
                            <td class="text-center font-tabular text-muted" data-label="Reports">{{ $day['reports'] }}</td>
                            <td class="pe-4 text-center" data-label="Status">
                                @if($day['manpower'] > 0)
                                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1 fw-semibold">
                                        <i class="fa-solid fa-check me-1"></i>Reported
                                    </span>
                                @else
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-3 py-1">
                                        No Data
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- All Submitted Reports Table --}}
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-2.5 py-1 fw-bold small">
                    <i class="fa-solid fa-list-check me-1"></i>Logs
                </span>
                <h6 class="fw-bold mb-0 text-dark">Field Reports in Period</h6>
            </div>
            <span class="text-muted small">{{ count($reports) }} Reports</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 wc-table-mobile-cards">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4 text-secondary text-uppercase small fw-bold">Project</th>
                        <th class="text-secondary text-uppercase small fw-bold">Report Date</th>
                        <th class="text-center text-secondary text-uppercase small fw-bold">Manpower</th>
                        <th class="text-center text-secondary text-uppercase small fw-bold">Work Items</th>
                        <th class="text-secondary text-uppercase small fw-bold">Reported By</th>
                        <th class="text-center text-secondary text-uppercase small fw-bold">Status</th>
                        <th class="pe-4 text-center text-secondary text-uppercase small fw-bold">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reports as $report)
                        <tr>
                            <td class="ps-4 fw-semibold text-dark" data-label="Project">
                                {{ $report->project->project_name ?? $report->project->name }}
                            </td>
                            <td class="font-monospace text-muted small" data-label="Date">
                                {{ $report->report_date->format('M d, Y') }}
                            </td>
                            <td class="text-center font-tabular fw-bold text-primary" data-label="Manpower">
                                {{ $report->total_manpower }}
                            </td>
                            <td class="text-center font-tabular" data-label="Items">
                                <span class="badge bg-light text-muted border">{{ $report->items->count() }} items</span>
                            </td>
                            <td data-label="Reported By">
                                <small class="text-dark fw-medium">{{ $report->createdBy->name ?? 'System' }}</small>
                            </td>
                            <td class="text-center" data-label="Status">
                                <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-2.5 py-1 fw-semibold">
                                    Approved
                                </span>
                            </td>
                            <td class="pe-4 text-center" data-label="Action">
                                <a href="{{ route('daily-reports.show', $report) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 shadow-xs fw-semibold">
                                    <i class="fa-solid fa-eye me-1"></i>View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <x-empty-state
                                    title="No Submitted Field Reports"
                                    description="No site daily logs were submitted for the selected week period."
                                    icon="fa-solid fa-calendar-xmark" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Official Print Signature Block --}}
    <x-print-signatures 
        preparedTitle="Prepared By" 
        preparedRole="Site Timekeeper / Foreman" 
        checkedTitle="Verified By" 
        checkedRole="Site Engineer / Project Manager" 
        approvedTitle="Approved By" 
        approvedRole="General Manager" />
</div>

{{-- Send to GM Modal --}}
<div class="modal fade" id="sendGMModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-bottom py-3 px-4">
                <h5 class="modal-title fw-bold text-dark">
                    <i class="fa-solid fa-paper-plane text-primary me-2"></i>Send Weekly Report to General Manager
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('weekly-manpower.sendGM') }}" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <input type="hidden" name="start_date" value="{{ $startDate }}">
                    <input type="hidden" name="end_date" value="{{ $endDate }}">
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary text-uppercase" style="font-size: 0.72rem;">GM Email Address <span class="text-danger">*</span></label>
                        <input type="email" name="gm_email" class="form-control" required placeholder="gm@wechecha.com">
                    </div>

                    <div class="form-check p-2.5 bg-light rounded-3 border">
                        <input type="checkbox" class="form-check-input ms-1 me-2" name="include_details" id="includeDetails" checked>
                        <label class="form-check-label small text-dark fw-semibold" for="includeDetails">
                            Include daily manpower breakdown and project gang tables
                        </label>
                    </div>
                </div>
                <div class="modal-footer border-top py-2 px-4">
                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm rounded-pill px-4 shadow-sm fw-semibold">
                        <i class="fa-solid fa-paper-plane me-1"></i>Dispatch Report
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const dailyData = {!! json_encode($summary['daily_breakdown'] ?? []) !!};
    const labels = Object.values(dailyData).map(d => d.date);
    const data = Object.values(dailyData).map(d => d.manpower);

    const ctx = document.getElementById('manpowerChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Daily Field Manpower',
                    data: data,
                    borderColor: '#0F2944',
                    backgroundColor: 'rgba(15, 41, 68, 0.08)',
                    borderWidth: 2.5,
                    fill: true,
                    tension: 0.35,
                    pointRadius: 5,
                    pointBackgroundColor: '#0F2944',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: '#f1f5f9'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }
});
</script>
@endpush
@endsection
