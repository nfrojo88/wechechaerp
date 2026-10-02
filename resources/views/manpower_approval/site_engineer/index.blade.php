@extends('layouts.app')
@section('title', 'Site Engineer - Daily Manpower')
@section('content')
<div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 pb-3 border-bottom gap-2">
        <div>
            <h4 class="fw-bold text-dark mb-0">
                <i class="fa-solid fa-person-digging text-primary me-2"></i>Site Engineer - Daily Manpower Sheets
            </h4>
            <p class="text-muted small mb-0 mt-1">
                Record and submit daily site labor rosters compared against attendance machine punches.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('manpower-approval.site-engineer.create') }}" class="btn btn-sm btn-primary shadow-xs">
                <i class="fa-solid fa-plus me-1"></i>New Daily Sheet
            </a>
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-xs rounded-3 py-2 px-3 mb-3 d-flex align-items-center gap-2">
        <i class="fa-solid fa-circle-check text-success fs-5"></i>
        <div class="small flex-grow-1">{{ session('success') }}</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-xs rounded-3 py-2 px-3 mb-3 d-flex align-items-center gap-2">
        <i class="fa-solid fa-triangle-exclamation text-danger fs-5"></i>
        <div class="small flex-grow-1">{{ session('error') }}</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    {{-- Tabs --}}
    <ul class="nav nav-tabs mb-3 border-bottom-0">
        <li class="nav-item">
            <a class="nav-link {{ $tab !== 'returned' ? 'active fw-bold text-primary' : 'text-secondary' }}" href="{{ route('manpower-approval.site-engineer.index', ['tab' => 'my_sheets']) }}">
                <i class="fa-solid fa-list-check me-1"></i>My Daily Sheets
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'returned' ? 'active fw-bold text-danger' : 'text-secondary' }}" href="{{ route('manpower-approval.site-engineer.index', ['tab' => 'returned']) }}">
                <i class="fa-solid fa-rotate-left me-1"></i>Returned / Rejected
                @if($returnedCount > 0)
                <span class="badge bg-danger ms-1">{{ $returnedCount }}</span>
                @endif
            </a>
        </li>
    </ul>

    {{-- Filter Bar --}}
    <div class="card border-0 shadow-xs rounded-3 mb-3 bg-white">
        <div class="card-body p-3">
            <form action="{{ route('manpower-approval.site-engineer.index') }}" method="GET" class="row g-2 align-items-end">
                <input type="hidden" name="tab" value="{{ $tab }}">
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
                    <a href="{{ route('manpower-approval.site-engineer.index', ['tab' => $tab]) }}" class="btn btn-sm btn-outline-secondary" title="Reset">
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
                            <th>Trade / Gang</th>
                            <th>Headcount</th>
                            <th>Total Hours</th>
                            <th>Total Amount</th>
                            <th>Status &amp; Stage</th>
                            @if($tab === 'returned')
                            <th class="text-danger">Rejection Details</th>
                            @endif
                            <th class="text-end">Actions</th>
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
                                    Adj from {{ number_format($sheet->total_amount, 2) }}
                                </div>
                                @endif
                            </td>
                            <td>
                                @if($sheet->status === 'Draft')
                                    <span class="badge bg-secondary">Draft</span>
                                @elseif($sheet->status === 'Submitted')
                                    <span class="badge bg-primary">With Planning</span>
                                @elseif($sheet->status === 'Planning Approved')
                                    <span class="badge bg-info text-white">With Coordinator</span>
                                @elseif($sheet->status === 'Coordinator Approved')
                                    <span class="badge bg-warning text-dark">With HR</span>
                                @elseif($sheet->status === 'HR Approved')
                                    <span class="badge bg-success">HR Approved (Weekly Pool)</span>
                                @elseif($sheet->status === 'In Weekly Batch')
                                    <span class="badge bg-purple text-white">In Weekly Batch</span>
                                @elseif($sheet->status === 'GM Approved')
                                    <span class="badge bg-success">GM Approved</span>
                                @elseif($sheet->status === 'Paid')
                                    <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i>Paid</span>
                                @elseif($sheet->status === 'Rejected')
                                    <span class="badge bg-danger">Returned</span>
                                @endif
                            </td>
                            @if($tab === 'returned')
                            <td>
                                <div class="text-danger fw-bold small mb-0.5">
                                    <i class="fa-solid fa-circle-exclamation me-1"></i>{{ $sheet->rejection_reason }}
                                </div>
                                <div class="text-muted small" style="font-size: 0.72rem;">{{ $sheet->rejection_comment }}</div>
                            </td>
                            @endif
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('manpower-approval.review.show', $sheet->id) }}" class="btn btn-outline-secondary" title="View Details">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                    @if(in_array($sheet->status, ['Draft', 'Rejected']))
                                    <a href="{{ route('manpower-approval.site-engineer.edit', $sheet->id) }}" class="btn btn-outline-primary" title="Edit &amp; Resubmit">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="{{ $tab === 'returned' ? 10 : 9 }}" class="py-5 text-center text-muted">
                                <i class="fa-solid fa-clipboard-list fs-2 mb-2 d-block text-secondary"></i>
                                No daily manpower sheets found matching the current criteria.
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
