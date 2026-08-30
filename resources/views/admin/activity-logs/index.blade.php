@extends('layouts.app')
@section('title', 'System Activity Logs')

@section('content')
<div class="container-fluid px-4 py-3">

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <div class="d-flex align-items-center gap-2">
                <div class="p-2.5 rounded-3 text-white shadow-sm" style="background: linear-gradient(135deg, #0f172a, #334155);">
                    <i class="fa-solid fa-list-ol fa-lg"></i>
                </div>
                <div>
                    <h1 class="h3 mb-0 fw-bold" style="color:var(--brand-800)">System Activity Logs</h1>
                    <p class="text-muted small mb-0">Administrator audit trail of system events, role changes, and user operations</p>
                </div>
            </div>
        </div>
        <div class="d-flex gap-2">
            <span class="badge bg-light text-dark border font-monospace px-3 py-2 rounded-pill fs-6">
                Total {{ $logs->total() }} Log Records
            </span>
        </div>
    </div>

    <!-- Search & Filter Card -->
    <div class="card border-0 shadow-sm mb-4 rounded-4 bg-white">
        <div class="card-body p-3">
            <form action="{{ route('admin.activity-logs') }}" method="GET" class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1 fw-bold text-uppercase" style="font-size: 11px;">User</label>
                    <select name="user_id" class="form-select form-select-sm">
                        <option value="">All Users</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1 fw-bold text-uppercase" style="font-size: 11px;">Action</label>
                    <select name="action" class="form-select form-select-sm">
                        <option value="">All Actions</option>
                        @foreach($actions as $act)
                            <option value="{{ $act }}" {{ request('action') == $act ? 'selected' : '' }}>{{ ucfirst($act) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1 fw-bold text-uppercase" style="font-size: 11px;">Module</label>
                    <select name="module" class="form-select form-select-sm">
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
                    <button type="submit" class="btn btn-primary btn-sm rounded-3 w-100 fw-bold">
                        <i class="fa-solid fa-filter me-1"></i> Filter
                    </button>
                    <a href="{{ route('admin.activity-logs') }}" class="btn btn-light border btn-sm rounded-3 text-muted" title="Reset Filters">
                        <i class="fa-solid fa-rotate-left"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Activity Log Table Card -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="bg-light text-muted text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                        <tr>
                            <th class="ps-4 py-3">Time</th>
                            <th class="py-3">User</th>
                            <th class="py-3">Action</th>
                            <th class="py-3">Module</th>
                            <th class="py-3" style="min-width: 320px;">Description</th>
                            <th class="pe-4 py-3 text-end">IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                        <tr>
                            <td class="ps-4 py-3 text-nowrap text-muted">
                                <strong class="text-dark d-block">{{ $log->created_at->format('Y-m-d') }}</strong>
                                <small>{{ $log->created_at->format('H:i:s') }} ({{ $log->created_at->diffForHumans() }})</small>
                            </td>
                            <td class="py-3">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="avatar avatar-sm rounded-circle bg-primary bg-opacity-10 text-primary fw-bold d-flex align-items-center justify-content-center" style="width:30px;height:30px; font-size: 0.78rem;">
                                        {{ strtoupper(substr($log->user->name ?? 'S', 0, 1)) }}
                                    </div>
                                    <div>
                                        <strong class="text-dark d-block">{{ $log->user->name ?? 'System' }}</strong>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3">
                                <span class="badge bg-{{ $log->action_color }} rounded-pill px-2.5 py-1 font-monospace text-uppercase" style="font-size:0.7rem;">
                                    <i class="fa-solid {{ $log->action_icon }} me-1"></i>{{ ucfirst($log->action) }}
                                </span>
                            </td>
                            <td class="py-3">
                                <span class="badge bg-light text-dark border">{{ $log->module ?? '-' }}</span>
                            </td>
                            <td class="py-3">
                                <div style="line-height: 1.45; word-break: break-word;">
                                    {{ $log->description }}
                                </div>
                            </td>
                            <td class="pe-4 py-3 text-end">
                                <small class="text-muted font-monospace">{{ $log->ip_address ?? '-' }}</small>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-inbox fa-3x mb-3 opacity-25"></i>
                                <p class="mb-0 fw-semibold">No activity logs found matching the criteria.</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($logs->hasPages())
        <div class="card-footer bg-white border-0 py-3">
            {{ $logs->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
