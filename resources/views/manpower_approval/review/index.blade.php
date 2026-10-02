@extends('layouts.app')
@section('title', 'Manpower Approvals Inbox')
@section('content')
<div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 pb-3 border-bottom gap-2">
        <div>
            <h4 class="fw-bold text-dark mb-0">
                <i class="fa-solid fa-clipboard-check text-primary me-2"></i>Manpower Approval Inbox
            </h4>
            <p class="text-muted small mb-0 mt-1">
                Review submitted daily manpower sheets, compare with biometric logs, verify rates, and approve or return.
            </p>
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-xs rounded-3 py-2 px-3 mb-3 d-flex align-items-center gap-2">
        <i class="fa-solid fa-circle-check text-success fs-5"></i>
        <div class="small flex-grow-1">{{ session('success') }}</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    {{-- Stage Switcher Tabs --}}
    <ul class="nav nav-pills mb-3 gap-2">
        <li class="nav-item">
            <a class="nav-link py-1.5 px-3 rounded-pill {{ $stage === 'planning_manager' ? 'active bg-primary' : 'bg-white border text-dark' }}" href="{{ route('manpower-approval.review.inbox', ['stage' => 'planning_manager']) }}">
                <i class="fa-solid fa-compass-drafting me-1"></i>Planning Review
                <span class="badge {{ $stage === 'planning_manager' ? 'bg-white text-primary' : 'bg-primary text-white' }} ms-1">{{ $stageCounts['planning'] }}</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link py-1.5 px-3 rounded-pill {{ $stage === 'coordinator' ? 'active bg-info text-white' : 'bg-white border text-dark' }}" href="{{ route('manpower-approval.review.inbox', ['stage' => 'coordinator']) }}">
                <i class="fa-solid fa-user-tie me-1"></i>Coordinator Review
                <span class="badge {{ $stage === 'coordinator' ? 'bg-white text-info' : 'bg-info text-white' }} ms-1">{{ $stageCounts['coordinator'] }}</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link py-1.5 px-3 rounded-pill {{ $stage === 'hr' ? 'active bg-warning text-dark' : 'bg-white border text-dark' }}" href="{{ route('manpower-approval.review.inbox', ['stage' => 'hr']) }}">
                <i class="fa-solid fa-user-check me-1"></i>HR Verification
                <span class="badge {{ $stage === 'hr' ? 'bg-dark text-white' : 'bg-warning text-dark' }} ms-1">{{ $stageCounts['hr'] }}</span>
            </a>
        </li>
    </ul>

    {{-- Filter Bar --}}
    <div class="card border-0 shadow-xs rounded-3 mb-3 bg-white">
        <div class="card-body p-3">
            <form action="{{ route('manpower-approval.review.inbox') }}" method="GET" class="row g-2 align-items-end">
                <input type="hidden" name="stage" value="{{ $stage }}">
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-dark mb-1">Project / Site</label>
                    <select name="project_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Projects</option>
                        @foreach($projects as $p)
                        <option value="{{ $p->id }}" {{ request('project_id') == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-dark mb-1">Date</label>
                    <input type="date" name="date" value="{{ request('date') }}" class="form-control form-control-sm" onchange="this.form.submit()">
                </div>
                <div class="col-md-3 d-flex gap-1">
                    <button type="submit" class="btn btn-sm btn-primary w-100 shadow-xs">
                        <i class="fa-solid fa-filter me-1"></i>Filter
                    </button>
                    @if(request()->hasAny(['project_id', 'date']))
                    <a href="{{ route('manpower-approval.review.inbox', ['stage' => $stage]) }}" class="btn btn-sm btn-outline-secondary" title="Reset">
                        <i class="fa-solid fa-rotate-left"></i>
                    </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    {{-- Sheets Table --}}
    <div class="card border-0 shadow-xs rounded-3 bg-white mb-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>Sheet #</th>
                            <th>Project</th>
                            <th>Date</th>
                            <th>Site Engineer</th>
                            <th>Trade / Gang</th>
                            <th>Headcount</th>
                            <th>Total Hours</th>
                            <th>Gross Amount</th>
                            <th>Current Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($sheets as $sheet)
                        <tr>
                            <td>
                                <a href="{{ route('manpower-approval.review.show', $sheet->id) }}" class="fw-bold font-monospace text-primary text-decoration-none">
                                    {{ $sheet->sheet_number }}
                                </a>
                            </td>
                            <td>
                                <strong class="text-dark d-block text-truncate" style="max-width: 160px;" title="{{ $sheet->project?->name }}">
                                    {{ $sheet->project?->name ?? 'General Site' }}
                                </strong>
                            </td>
                            <td>{{ $sheet->date->format('M d, Y') }}</td>
                            <td>
                                <div class="fw-semibold text-dark">{{ $sheet->siteEngineer?->name ?? 'Site Staff' }}</div>
                                <div class="text-muted" style="font-size: 0.68rem;">{{ $sheet->siteEngineer?->email }}</div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">{{ $sheet->trade ?: 'General Labor' }}</span>
                                @if($sheet->gang_subcontractor)
                                <div class="text-muted" style="font-size: 0.68rem;">{{ $sheet->gang_subcontractor }}</div>
                                @endif
                            </td>
                            <td><span class="badge bg-light text-dark border font-monospace">{{ $sheet->total_headcount }}</span></td>
                            <td class="font-monospace">{{ number_format($sheet->total_regular_hours + $sheet->total_overtime_hours, 1) }}h</td>
                            <td>
                                <div class="font-monospace fw-bold text-dark">ETB {{ number_format($sheet->effective_total_amount, 2) }}</div>
                                @if($sheet->total_adjusted_amount !== null && $sheet->total_adjusted_amount != $sheet->total_amount)
                                <div class="text-danger small font-monospace" style="font-size: 0.68rem;">
                                    Adjusted
                                </div>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                    {{ $sheet->status }}
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('manpower-approval.review.show', $sheet->id) }}" class="btn btn-sm btn-primary shadow-xs">
                                    <i class="fa-solid fa-magnifying-glass me-1"></i>Review
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10" class="py-5 text-center text-muted">
                                <i class="fa-solid fa-clipboard-check fs-2 mb-2 d-block text-success"></i>
                                Inbox is clean! No manpower sheets pending review in this stage.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($sheets->hasPages())
            <div class="p-3 border-top">
                {{ $sheets->links() }}
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
