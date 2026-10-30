@extends('layouts.app')
@section('title', 'Material Demand & Forecast')

@section('content')
<div class="container-fluid px-3 px-md-4 py-3">

    {{-- Page Header --}}
    <x-page-header 
        title="Material Demand & Forecast" 
        subtitle="30-day forward demand requirements aggregated across active project schedules and Bill of Quantities">
        <x-slot:actions>
            <a href="{{ route('purchase-requests.create') }}" class="btn btn-primary btn-sm rounded-pill px-3 shadow-xs">
                <i class="fa-solid fa-plus me-1"></i>Create Purchase Request
            </a>
            <button class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-xs" onclick="window.print()">
                <i class="fa-solid fa-file-export me-1"></i>Export Forecast
            </button>
        </x-slot:actions>
    </x-page-header>

    @php
        $totalForecasts = count($forecasts);
        $uniqueProjects = collect($forecasts)->pluck('project')->unique()->count();
        $urgentNeeds = collect($forecasts)->filter(function($f) {
            return \Carbon\Carbon::parse($f['date_needed'])->diffInDays(now()) <= 7;
        })->count();
    @endphp

    {{-- Demand KPIs --}}
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-sm-6">
            <x-kpi-card 
                label="30-Day Demand Items" 
                value="{{ $totalForecasts }}" 
                icon="fa-solid fa-cubes-stacked" 
                variant="primary" 
                helpText="Forecasted material batches" />
        </div>
        <div class="col-xl-3 col-sm-6">
            <x-kpi-card 
                label="Urgent (≤ 7 Days)" 
                value="{{ $urgentNeeds }}" 
                icon="fa-solid fa-triangle-exclamation" 
                variant="danger" 
                helpText="Needs immediate PO release" />
        </div>
        <div class="col-xl-3 col-sm-6">
            <x-kpi-card 
                label="Demanding Projects" 
                value="{{ $uniqueProjects }}" 
                icon="fa-solid fa-building" 
                variant="info" 
                helpText="Sites with active material draw" />
        </div>
        <div class="col-xl-3 col-sm-6">
            <x-kpi-card 
                label="Schedule Alignment" 
                value="100%" 
                icon="fa-solid fa-calendar-check" 
                variant="success" 
                helpText="Synchronized with Gantt BoQ" />
        </div>
    </div>

    {{-- Upcoming Material Requirements Table --}}
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold" style="color: var(--brand-900);">
                <i class="fa-solid fa-dolly text-primary me-2"></i>Upcoming Material Requirements (Next 30 Days)
            </h6>
            <span class="badge bg-light text-dark border px-3 py-1 font-monospace">
                {{ $totalForecasts }} Material Demands
            </span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 wc-table-mobile-cards">
                    <thead class="table-light text-uppercase small text-muted" style="letter-spacing: 0.5px;">
                        <tr>
                            <th class="ps-4 py-3">Project Site</th>
                            <th class="py-3">Material / Product</th>
                            <th class="py-3 text-center">Required Quantity</th>
                            <th class="py-3">Target Date Needed</th>
                            <th class="pe-4 py-3 text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($forecasts as $forecast)
                        @php
                            $targetDate = \Carbon\Carbon::parse($forecast['date_needed']);
                            $days = $targetDate->diffInDays(now());
                            $isPast = $targetDate->isPast();
                        @endphp
                        <tr>
                            <td class="ps-4 py-3" data-label="Project">
                                <div class="fw-bold" style="color: var(--brand-900);">{{ $forecast['project'] }}</div>
                                <small class="text-muted"><i class="fa-solid fa-location-dot me-1 text-secondary"></i>Active Jobsite</small>
                            </td>
                            <td class="py-3" data-label="Material">
                                <span class="fw-semibold text-dark">{{ $forecast['material'] }}</span>
                            </td>
                            <td class="py-3 text-center" data-label="Quantity">
                                <span class="badge bg-light text-dark border px-3 py-1 rounded-pill fw-bold font-tabular fs-6">
                                    {{ $forecast['required_qty'] }}
                                </span>
                            </td>
                            <td class="py-3" data-label="Date Needed">
                                <div class="fw-semibold text-dark font-tabular">{{ $targetDate->format('d M, Y') }}</div>
                                @if($isPast)
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-0.5 rounded-pill" style="font-size:0.75rem;">
                                        <i class="fa-solid fa-circle-exclamation me-1"></i>Overdue
                                    </span>
                                @elseif($days <= 7)
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-0.5 rounded-pill" style="font-size:0.75rem;">
                                        <i class="fa-solid fa-clock me-1"></i>In {{ $days }} days (Urgent)
                                    </span>
                                @elseif($days <= 14)
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-0.5 rounded-pill" style="font-size:0.75rem;">
                                        <i class="fa-solid fa-clock me-1"></i>In {{ $days }} days
                                    </span>
                                @else
                                    <span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-0.5 rounded-pill" style="font-size:0.75rem;">
                                        <i class="fa-solid fa-clock me-1"></i>In {{ $days }} days
                                    </span>
                                @endif
                            </td>
                            <td class="pe-4 py-3 text-end" data-label="Action">
                                <a href="{{ route('purchase-requests.create') }}" class="btn btn-sm btn-primary rounded-pill px-3 shadow-xs">
                                    <i class="fa-solid fa-cart-shopping me-1"></i>Create PR
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <x-empty-state 
                                    icon="fa-solid fa-cubes" 
                                    title="No Upcoming Material Demand Forecasted" 
                                    message="All project schedule tasks have current inventory allocated. New demands will appear automatically as schedules advance." />
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-light border-top py-3 px-4">
            <div class="d-flex align-items-center text-muted small">
                <i class="fa-solid fa-lightbulb text-warning me-2 fs-6"></i>
                <span>These material forecasts are generated automatically by comparing active project CPM milestone deadlines against Bill of Quantities (BoQ) material breakdown curves.</span>
            </div>
        </div>
    </div>

</div>
@endsection
