@extends('layouts.app')
@section('title', 'Weekly Site Progress Reports — Site Operations')

@section('content')
@php
    $totalReports = $reports->count();
    $avgPlanned = $totalReports > 0 ? $reports->avg('planned_progress_percent') : 0;
    $avgActual = $totalReports > 0 ? $reports->avg('actual_progress_percent') : 0;
    $approvedCount = $reports->where('status', 'approved')->count();
@endphp

<div class="container-fluid px-0">
    {{-- Page Header --}}
    <x-page-header
        title="Weekly Site Progress Reports"
        subtitle="Weekly construction milestones, physical progress vs. planned schedule, equipment utilization, and photo logs."
        :breadcrumbs="[
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'Site Operations', 'url' => route('dashboard.site-engineer')],
            ['label' => 'Weekly Reports']
        ]">
        <x-slot name="actions">
            <x-export-menu :print="true" label="Print" />
            <a href="{{ route('weekly-reports.create') }}" class="btn btn-primary btn-sm rounded-pill px-3 py-2 shadow-xs fw-semibold">
                <i class="fa-solid fa-plus me-1.5"></i>New Weekly Report
            </a>
        </x-slot>
    </x-page-header>

    {{-- Executive Summary KPI Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Weekly Reports"
                :value="number_format($totalReports)"
                changeLabel="Filed milestone logs"
                icon="fa-solid fa-calendar-week"
                color="primary" />
        </div>
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Avg Planned Progress"
                :value="number_format($avgPlanned, 1) . '%'"
                changeLabel="Target completion rate"
                icon="fa-solid fa-bullseye"
                color="info" />
        </div>
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Avg Actual Progress"
                :value="number_format($avgActual, 1) . '%'"
                changeLabel="Executed work on site"
                icon="fa-solid fa-bars-progress"
                :color="$avgActual >= $avgPlanned ? 'success' : 'warning'" />
        </div>
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Approved Reports"
                :value="number_format($approvedCount)"
                changeLabel="Validated by management"
                icon="fa-solid fa-circle-check"
                color="success" />
        </div>
    </div>

    {{-- Weekly Reports Table Card --}}
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-2.5 py-1 fw-bold small">
                    <i class="fa-solid fa-timeline me-1"></i>Milestones
                </span>
                <h6 class="fw-bold mb-0 text-dark">Weekly Site Progress Log</h6>
            </div>
            <span class="text-muted small">{{ $totalReports }} Reports Filed</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 wc-table-mobile-cards">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4 text-secondary text-uppercase small fw-bold">Week Range</th>
                        <th class="text-secondary text-uppercase small fw-bold">Project</th>
                        <th class="text-secondary text-uppercase small fw-bold" style="min-width: 160px;">Planned Progress</th>
                        <th class="text-secondary text-uppercase small fw-bold" style="min-width: 160px;">Actual Progress</th>
                        <th class="text-secondary text-uppercase small fw-bold">Status</th>
                        <th class="pe-4 text-end text-secondary text-uppercase small fw-bold">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reports as $report)
                        @php
                            $isApproved = $report->status === 'approved';
                            $variance = $report->actual_progress_percent - $report->planned_progress_percent;
                        @endphp
                        <tr>
                            <td class="ps-4 font-monospace fw-bold text-dark" data-label="Week Range">
                                {{ \Carbon\Carbon::parse($report->week_start)->format('d M') }} &ndash; {{ \Carbon\Carbon::parse($report->week_end)->format('d M, Y') }}
                            </td>
                            <td data-label="Project">
                                <div class="fw-semibold text-dark">{{ $report->project->name ?? 'N/A' }}</div>
                                @if($report->project->code)
                                    <span class="badge bg-light text-muted border font-monospace" style="font-size: 0.7rem;">{{ $report->project->code }}</span>
                                @endif
                            </td>
                            <td data-label="Planned %">
                                <div class="d-flex justify-content-between align-items-center mb-1 small">
                                    <span class="font-tabular fw-semibold text-dark">{{ number_format($report->planned_progress_percent, 1) }}%</span>
                                </div>
                                <div class="progress" style="height: 6px; background-color: #f1f5f9;">
                                    <div class="progress-bar bg-info" role="progressbar" style="width: {{ min(100, $report->planned_progress_percent) }}%"></div>
                                </div>
                            </td>
                            <td data-label="Actual %">
                                <div class="d-flex justify-content-between align-items-center mb-1 small">
                                    <span class="font-tabular fw-bold {{ $variance >= 0 ? 'text-success' : 'text-danger' }}">
                                        {{ number_format($report->actual_progress_percent, 1) }}%
                                        <small class="ms-1 font-monospace">({{ $variance >= 0 ? '+' : '' }}{{ number_format($variance, 1) }}%)</small>
                                    </span>
                                </div>
                                <div class="progress" style="height: 6px; background-color: #f1f5f9;">
                                    <div class="progress-bar {{ $variance >= 0 ? 'bg-success' : 'bg-warning' }}" role="progressbar" style="width: {{ min(100, $report->actual_progress_percent) }}%"></div>
                                </div>
                            </td>
                            <td data-label="Status">
                                <span class="badge {{ $isApproved ? 'bg-success bg-opacity-10 text-success' : 'bg-secondary bg-opacity-10 text-secondary' }} rounded-pill px-2.5 py-1 fw-semibold">
                                    {{ ucfirst($report->status) }}
                                </span>
                            </td>
                            <td class="pe-4 text-end" data-label="Action">
                                <a href="{{ route('weekly-reports.show', $report) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 shadow-xs fw-semibold">
                                    <i class="fa-solid fa-eye me-1"></i>View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <x-empty-state
                                    title="No Weekly Reports Found"
                                    description="No weekly progress reports have been filed yet. Click New Weekly Report to submit a milestone summary."
                                    icon="fa-solid fa-calendar-week" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
