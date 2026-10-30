@extends('layouts.app')
@section('title', 'Material Damage Reports — Site Operations')

@section('content')
@php
    $totalReports = $reports->count();
    $totalQuantity = $reports->sum('quantity');
    $approvedCount = $reports->where('status', 'approved')->count();
    $pendingCount = $reports->where('status', '!=', 'approved')->where('status', '!=', 'rejected')->count();
@endphp

<div class="container-fluid px-0">
    {{-- Page Header --}}
    <x-page-header
        title="Material Damage Reports"
        subtitle="General Service and site engineering log for broken, wasted, expired, or defective construction materials and salvage investigations."
        :breadcrumbs="[
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'Site Operations', 'url' => route('dashboard.site-engineer')],
            ['label' => 'Damage Reports']
        ]">
        <x-slot name="actions">
            <a href="{{ route('material-damage-reports.create') }}" class="btn btn-primary btn-sm rounded-pill px-3 py-2 shadow-xs fw-semibold">
                <i class="fa-solid fa-plus me-1.5"></i>Create Damage Log
            </a>
        </x-slot>
    </x-page-header>

    {{-- System Alerts --}}
    @if(session('success'))
        <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center gap-3 p-3 bg-success bg-opacity-10 border-start border-4 border-success">
            <div class="p-2 bg-success text-white rounded-circle fs-5 flex-shrink-0">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <div class="flex-grow-1 text-dark fw-medium">{{ session('success') }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Executive Summary KPI Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Damage Incidents"
                :value="number_format($totalReports)"
                changeLabel="Recorded site incident logs"
                icon="fa-solid fa-triangle-exclamation"
                color="danger" />
        </div>
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Total Units Damaged"
                :value="number_format($totalQuantity, 1)"
                changeLabel="Cumulative material loss"
                icon="fa-solid fa-cubes-stacked"
                color="warning" />
        </div>
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Investigated & Approved"
                :value="number_format($approvedCount)"
                changeLabel="Cleared by supervisor"
                icon="fa-solid fa-circle-check"
                color="success" />
        </div>
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Pending Review"
                :value="number_format($pendingCount)"
                changeLabel="Under field inspection"
                icon="fa-solid fa-hourglass-half"
                color="info" />
        </div>
    </div>

    {{-- Material Damage Reports Table Card --}}
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-2.5 py-1 fw-bold small">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i>Incident Log
                </span>
                <h6 class="fw-bold mb-0 text-dark">Material Damage &amp; Loss Records</h6>
            </div>
            <span class="text-muted small">{{ $totalReports }} Incidents Filed</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 wc-table-mobile-cards">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4 text-secondary text-uppercase small fw-bold">Report ID</th>
                        <th class="text-secondary text-uppercase small fw-bold">Project</th>
                        <th class="text-secondary text-uppercase small fw-bold">Material Item</th>
                        <th class="text-secondary text-uppercase small fw-bold">Quantity Damaged</th>
                        <th class="text-secondary text-uppercase small fw-bold">Damage Reason / Root Cause</th>
                        <th class="text-secondary text-uppercase small fw-bold">Reported By</th>
                        <th class="text-secondary text-uppercase small fw-bold">Status</th>
                        <th class="pe-4 text-end text-secondary text-uppercase small fw-bold">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reports as $report)
                        @php
                            $badge = match(strtolower($report->status)) {
                                'approved' => 'bg-success bg-opacity-10 text-success',
                                'rejected' => 'bg-danger bg-opacity-10 text-danger',
                                default => 'bg-warning bg-opacity-10 text-warning'
                            };
                        @endphp
                        <tr>
                            <td class="ps-4 font-monospace fw-bold text-danger" data-label="Report ID">
                                #MDR-{{ str_pad($report->id, 5, '0', STR_PAD_LEFT) }}
                            </td>
                            <td data-label="Project">
                                <span class="fw-semibold text-dark">{{ $report->project->name ?? 'N/A' }}</span>
                            </td>
                            <td data-label="Material Item">
                                <span class="fw-bold text-dark">{{ $report->product->name ?? 'N/A' }}</span>
                            </td>
                            <td data-label="Quantity">
                                <span class="font-tabular fw-bold text-danger">{{ number_format($report->quantity, 2) }}</span>
                                <small class="text-muted">{{ $report->product->unit ?? 'Units' }}</small>
                            </td>
                            <td data-label="Reason">
                                <span class="badge bg-light text-dark border">{{ $report->damage_reason }}</span>
                            </td>
                            <td data-label="Reported By">
                                <div class="fw-semibold text-dark">{{ $report->reporter->name ?? 'System User' }}</div>
                                <span class="font-monospace text-muted small" style="font-size: 0.72rem;">{{ $report->created_at->format('M d, Y H:i') }}</span>
                            </td>
                            <td data-label="Status">
                                <span class="badge {{ $badge }} rounded-pill px-2.5 py-1 fw-semibold">
                                    {{ ucfirst($report->status) }}
                                </span>
                            </td>
                            <td class="pe-4 text-end" data-label="Action">
                                <a href="{{ route('material-damage-reports.show', $report) }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1 shadow-xs fw-semibold">
                                    <i class="fa-solid fa-eye me-1"></i>View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <x-empty-state
                                    title="No Material Damage Reports"
                                    description="No damaged or wasted material incidents have been filed for active projects."
                                    icon="fa-solid fa-clipboard-check" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if(method_exists($reports, 'hasPages') && $reports->hasPages())
            <div class="card-footer bg-white border-0 py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="text-muted small">
                    Showing {{ $reports->firstItem() ?? 0 }} to {{ $reports->lastItem() ?? 0 }} of {{ $reports->total() }} reports
                </span>
                <div>
                    {{ $reports->links() }}
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
