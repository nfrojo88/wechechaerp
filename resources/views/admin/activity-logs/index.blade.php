@extends('layouts.app')
@section('title', 'System Activity Logs — Audit Trail')

@push('styles')
<style>
    @media print {
        .wc-no-print { display: none !important; }
        .card { border: 1px solid #e2e8f0 !important; box-shadow: none !important; }
    }
</style>
@endpush

@section('content')
<div class="container-fluid px-3 px-md-4 py-3">

    {{-- Page Header --}}
    <x-page-header 
        title="System Activity Logs" 
        subtitle="Immutable administrator audit trail of system events, role modifications, authorization changes, and user transactions">
        <x-slot:actions>
            <button class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-xs wc-no-print" onclick="window.print()">
                <i class="fa-solid fa-print me-1"></i>Print Audit Log
            </button>
        </x-slot:actions>
    </x-page-header>

    {{-- KPI Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-sm-6">
            <x-kpi-card 
                label="Total Activity Records" 
                value="{{ number_format($logs->total()) }}" 
                icon="fa-solid fa-list-ol" 
                variant="primary" 
                helpText="Logged system operations" />
        </div>
        <div class="col-xl-3 col-sm-6">
            <x-kpi-card 
                label="Active Operators" 
                value="{{ count($users) }}" 
                icon="fa-solid fa-users" 
                variant="info" 
                helpText="Staff with recorded activities" />
        </div>
        <div class="col-xl-3 col-sm-6">
            <x-kpi-card 
                label="Modules Audited" 
                value="{{ count($modules) }}" 
                icon="fa-solid fa-cubes" 
                variant="success" 
                helpText="Protected application services" />
        </div>
        <div class="col-xl-3 col-sm-6">
            <x-kpi-card 
                label="Compliance Status" 
                value="Active" 
                icon="fa-solid fa-shield-check" 
                variant="warning" 
                helpText="Tamper-evident audit logging" />
        </div>
    </div>

    {{-- Search & Filter Card --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white wc-no-print">
        <div class="card-body p-3">
            <form action="{{ route('admin.activity-logs') }}" method="GET" class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1 fw-bold text-uppercase" style="font-size: 11px;">User / Operator</label>
                    <select name="user_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Users</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1 fw-bold text-uppercase" style="font-size: 11px;">Action</label>
                    <select name="action" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Actions</option>
                        @foreach($actions as $act)
                            <option value="{{ $act }}" {{ request('action') == $act ? 'selected' : '' }}>{{ ucfirst($act) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1 fw-bold text-uppercase" style="font-size: 11px;">Module</label>
                    <select name="module" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Modules</option>
                        @foreach($modules as $mod)
                            <option value="{{ $mod }}" {{ request('module') == $mod ? 'selected' : '' }}>{{ $mod }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1 fw-bold text-uppercase" style="font-size: 11px;">Date Range</label>
                    <div class="d-flex gap-1">
                        <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}" placeholder="From Date">
                        <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}" placeholder="To Date">
                    </div>
                </div>
                <div class="col-md-3 d-flex gap-1">
                    <button type="submit" class="btn btn-primary btn-sm rounded-pill w-100 fw-bold">
                        <i class="fa-solid fa-filter me-1"></i> Filter
                    </button>
                    @if(request('user_id') || request('action') || request('module') || request('date_from') || request('date_to'))
                    <a href="{{ route('admin.activity-logs') }}" class="btn btn-light border btn-sm rounded-pill px-2.5 text-muted" title="Reset Filters">
                        <i class="fa-solid fa-rotate-left"></i>
                    </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    {{-- Activity Log Table Card --}}
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4 bg-white">
        <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold" style="color: var(--brand-900);">
                <i class="fa-solid fa-shield-halved text-primary me-2"></i>Audit Trail Log Records
            </h6>
            <span class="badge bg-light text-dark border px-3 py-1 font-monospace">
                Showing {{ $logs->count() }} of {{ $logs->total() }} events
            </span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 wc-table-mobile-cards">
                    <thead class="table-light text-muted small text-uppercase" style="letter-spacing: 0.5px;">
                        <tr>
                            <th class="ps-4 py-3">Timestamp</th>
                            <th class="py-3">User / Operator</th>
                            <th class="py-3 text-center">Action</th>
                            <th class="py-3 text-center">Module</th>
                            <th class="py-3" style="min-width: 300px;">Description &amp; Context</th>
                            <th class="pe-4 py-3 text-end">IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                        <tr>
                            <td class="ps-4 py-3 font-tabular" data-label="Timestamp">
                                <strong class="text-dark d-block">{{ $log->created_at->format('Y-m-d') }}</strong>
                                <small class="text-muted">{{ $log->created_at->format('H:i:s') }} ({{ $log->created_at->diffForHumans() }})</small>
                            </td>
                            <td class="py-3" data-label="User">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="avatar avatar-sm rounded-circle bg-primary bg-opacity-10 text-primary fw-bold d-flex align-items-center justify-content-center" style="width:30px;height:30px; font-size: 0.78rem;">
                                        {{ strtoupper(substr($log->user->name ?? 'S', 0, 1)) }}
                                    </div>
                                    <div>
                                        <strong class="text-dark d-block">{{ $log->user->name ?? 'System' }}</strong>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3 text-center" data-label="Action">
                                <span class="badge bg-{{ $log->action_color }}-subtle text-{{ $log->action_color }} border border-{{ $log->action_color }}-subtle rounded-pill px-2.5 py-1 font-monospace text-uppercase" style="font-size:0.7rem;">
                                    <i class="fa-solid {{ $log->action_icon }} me-1"></i>{{ ucfirst($log->action) }}
                                </span>
                            </td>
                            <td class="py-3 text-center" data-label="Module">
                                <span class="badge bg-light text-dark border rounded-pill px-2.5 py-1">{{ $log->module ?? '-' }}</span>
                            </td>
                            <td class="py-3" style="line-height: 1.45; word-break: break-word;" data-label="Description">
                                <span class="text-dark small">{{ $log->description }}</span>
                            </td>
                            <td class="pe-4 py-3 text-end font-tabular" data-label="IP">
                                <code class="text-muted small">{{ $log->ip_address ?? '-' }}</code>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <x-empty-state 
                                    icon="fa-solid fa-list-ol" 
                                    title="No Activity Logs Found" 
                                    message="No system activity events matched the selected filter criteria." />
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($logs->hasPages())
        <div class="card-footer bg-white border-top py-3 px-4">
            {{ $logs->links() }}
        </div>
        @endif
    </div>

</div>
@endsection
