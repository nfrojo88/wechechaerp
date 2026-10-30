@extends('layouts.app')
@section('title', 'Project Resource Report')

@push('styles')
<style>
    @media print {
        .wc-no-print { display: none !important; }
        .card { border: 1px solid #e2e8f0 !important; box-shadow: none !important; }
        .table thead th { background-color: #f1f5f9 !important; color: #0f172a !important; -webkit-print-color-adjust: exact; }
    }
</style>
@endpush

@section('content')
<div class="container-fluid px-3 px-md-4 py-3">
    <x-print-header title="Project Resource Report" subtitle="Live Resource Allocation Overview Across Active Construction Sites" />

    {{-- Page Header --}}
    <x-page-header 
        title="Project Resource Report" 
        subtitle="Live resource allocation overview across all active construction projects">
        <x-slot:actions>
            <x-export-menu :print="true" label="Print / Export" />
        </x-slot:actions>
    </x-page-header>

    @if(count($reportData) === 0)
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-5">
            <x-empty-state 
                icon="fa-solid fa-chart-bar" 
                title="No Active Projects Found" 
                message="Resource allocation reports will appear here once active construction projects are added with assigned resources." />
        </div>
    </div>
    @else
    {{-- Resource KPI Cards --}}
    @php
        $totalManpower = array_sum(array_column($reportData, 'manpower_active'));
        $totalEquipment = array_sum(array_column($reportData, 'equipment_active'));
        $totalCement = array_sum(array_column($reportData, 'cement_qty'));
        $totalSteel = array_sum(array_column($reportData, 'steel_qty'));
        $totalSand = array_sum(array_column($reportData, 'sand_qty'));
    @endphp

    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-sm-6">
            <x-kpi-card 
                label="Total Active Manpower" 
                value="{{ number_format($totalManpower) }}" 
                icon="fa-solid fa-users" 
                variant="primary" 
                helpText="Labor deployed across sites" />
        </div>
        <div class="col-xl-3 col-sm-6">
            <x-kpi-card 
                label="Total Equipment Units" 
                value="{{ number_format($totalEquipment) }}" 
                icon="fa-solid fa-tractor" 
                variant="success" 
                helpText="Active machinery & fleet" />
        </div>
        <div class="col-xl-3 col-sm-6">
            <x-kpi-card 
                label="Cement Allocation" 
                value="{{ number_format($totalCement) }} Bags" 
                icon="fa-solid fa-cubes-stacked" 
                variant="warning" 
                helpText="Cement stock allocated" />
        </div>
        <div class="col-xl-3 col-sm-6">
            <x-kpi-card 
                label="Reinforcement Steel" 
                value="{{ number_format($totalSteel) }} Tons" 
                icon="fa-solid fa-weight-scale" 
                variant="info" 
                helpText="Rebar & structural steel" />
        </div>
    </div>

    {{-- Per-project breakdown --}}
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold" style="color: var(--brand-900);">
                <i class="fa-solid fa-table me-2 text-primary"></i>Resource Breakdown by Project
            </h6>
            <span class="badge bg-light text-dark border px-3 py-1 font-monospace">
                {{ count($reportData) }} Projects
            </span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 wc-table-mobile-cards">
                    <thead class="table-light text-uppercase small text-muted" style="letter-spacing: 0.5px;">
                        <tr>
                            <th class="ps-4 py-3" style="width: 50px;">#</th>
                            <th class="py-3">Project &amp; Location</th>
                            <th class="py-3 text-center">Status</th>
                            <th class="py-3 text-center">Manpower</th>
                            <th class="py-3 text-center">Equipment</th>
                            <th class="py-3 text-end">Cement (Bags)</th>
                            <th class="py-3 text-end">Steel (Tons)</th>
                            <th class="pe-4 py-3 text-end">Sand (m³)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($reportData as $i => $row)
                        <tr>
                            <td class="ps-4 py-3 text-muted font-tabular" data-label="#">{{ $i + 1 }}</td>
                            <td class="py-3" data-label="Project">
                                <div class="fw-bold" style="color: var(--brand-900);">{{ $row['project']->name }}</div>
                                <small class="text-muted"><i class="fa-solid fa-location-dot me-1 text-secondary"></i>{{ $row['project']->location ?? 'N/A' }}</small>
                            </td>
                            <td class="py-3 text-center" data-label="Status">
                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1 rounded-pill">
                                    <i class="fa-solid fa-circle-check me-1"></i>Active
                                </span>
                            </td>
                            <td class="py-3 text-center" data-label="Manpower">
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-1 fw-bold font-tabular">
                                    {{ $row['manpower_active'] }}
                                </span>
                            </td>
                            <td class="py-3 text-center" data-label="Equipment">
                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1 fw-bold font-tabular">
                                    {{ $row['equipment_active'] }}
                                </span>
                            </td>
                            <td class="py-3 text-end font-tabular fw-semibold text-dark" data-label="Cement (Bags)">
                                {{ number_format($row['cement_qty']) }}
                            </td>
                            <td class="py-3 text-end font-tabular fw-semibold text-dark" data-label="Steel (Tons)">
                                {{ number_format($row['steel_qty']) }}
                            </td>
                            <td class="pe-4 py-3 text-end font-tabular fw-semibold text-dark" data-label="Sand (m³)">
                                {{ number_format($row['sand_qty']) }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="table-light fw-bold border-top-2">
                        <tr>
                            <td colspan="3" class="ps-4 py-3 text-uppercase text-muted" style="letter-spacing: 0.5px;">Portfolio Totals:</td>
                            <td class="py-3 text-center font-tabular text-primary">{{ number_format($totalManpower) }}</td>
                            <td class="py-3 text-center font-tabular text-success">{{ number_format($totalEquipment) }}</td>
                            <td class="py-3 text-end font-tabular">{{ number_format($totalCement) }}</td>
                            <td class="py-3 text-end font-tabular">{{ number_format($totalSteel) }}</td>
                            <td class="pe-4 py-3 text-end font-tabular">{{ number_format($totalSand) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    {{-- Official Print Signature Block --}}
    <x-print-signatures 
        preparedTitle="Prepared By" 
        preparedRole="Planning Engineer" 
        checkedTitle="Verified By" 
        checkedRole="Planning Manager" 
        approvedTitle="Approved By" 
        approvedRole="General Manager" />
    @endif
</div>
@endsection
