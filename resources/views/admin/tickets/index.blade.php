@extends('layouts.app')
@section('title', 'Manage IT Problem Reports & Suggestions')
@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h1 class="h3 mb-0 text-gray-800 font-weight-bold">
                <i class="fa-solid fa-headset text-primary me-2"></i>IT Department Problem Reports & Suggestions
            </h1>
            <p class="text-muted small mb-0">Monitor IT incidents, suggestions, GM/Admin SMS notifications, and department resolutions.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('tickets.create') }}" class="btn btn-primary shadow-sm">
                <i class="fa-solid fa-plus-circle me-1"></i> New IT Report / Idea
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Ticket Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm border-start border-4 border-danger h-100 py-2">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Open / New Reports</div>
                            <div class="h4 mb-0 font-weight-bold text-gray-800">{{ $stats['open'] }}</div>
                            @if(!empty($stats['critical']))
                                <small class="text-danger font-weight-bold"><i class="fa-solid fa-triangle-exclamation"></i> {{ $stats['critical'] }} Critical</small>
                            @endif
                        </div>
                        <div class="fs-1 text-danger-subtle"><i class="fa-solid fa-triangle-exclamation"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm border-start border-4 border-warning h-100 py-2">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">In Progress</div>
                            <div class="h4 mb-0 font-weight-bold text-gray-800">{{ $stats['in_progress'] }}</div>
                            <small class="text-muted">Under investigation</small>
                        </div>
                        <div class="fs-1 text-warning-subtle"><i class="fa-solid fa-spinner fa-spin"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm border-start border-4 border-success h-100 py-2">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Resolved</div>
                            <div class="h4 mb-0 font-weight-bold text-gray-800">{{ $stats['resolved'] }}</div>
                            <small class="text-muted">Fix confirmed</small>
                        </div>
                        <div class="fs-1 text-success-subtle"><i class="fa-solid fa-circle-check"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm border-start border-4 border-secondary h-100 py-2">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Total Submissions</div>
                            <div class="h4 mb-0 font-weight-bold text-gray-800">{{ $stats['total'] }}</div>
                            <small class="text-muted">All-time tickets</small>
                        </div>
                        <div class="fs-1 text-secondary-subtle"><i class="fa-solid fa-folder-closed"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Ticket List Table & Filters -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3 border-bottom">
            <form action="{{ route('admin.tickets.index') }}" method="GET" class="row g-2 align-items-center">
                <div class="col-md-2">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <option value="new" {{ request('status') == 'new' ? 'selected' : '' }}>New</option>
                        <option value="open" {{ request('status') == 'open' ? 'selected' : '' }}>Open</option>
                        <option value="in_progress" {{ request('status') == 'in_progress' ? 'selected' : '' }}>In Progress</option>
                        <option value="waiting_for_user" {{ request('status') == 'waiting_for_user' ? 'selected' : '' }}>Waiting for User</option>
                        <option value="resolved" {{ request('status') == 'resolved' ? 'selected' : '' }}>Resolved</option>
                        <option value="closed" {{ request('status') == 'closed' ? 'selected' : '' }}>Closed</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="priority" class="form-select form-select-sm">
                        <option value="">All Priorities</option>
                        <option value="critical" {{ request('priority') == 'critical' ? 'selected' : '' }}>Critical</option>
                        <option value="urgent" {{ request('priority') == 'urgent' ? 'selected' : '' }}>Urgent</option>
                        <option value="high" {{ request('priority') == 'high' ? 'selected' : '' }}>High</option>
                        <option value="medium" {{ request('priority') == 'medium' ? 'selected' : '' }}>Medium</option>
                        <option value="low" {{ request('priority') == 'low' ? 'selected' : '' }}>Low</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="submission_type" class="form-select form-select-sm">
                        <option value="">All Types</option>
                        <option value="problem" {{ request('submission_type') == 'problem' ? 'selected' : '' }}>Problem / Issue</option>
                        <option value="suggestion" {{ request('submission_type') == 'suggestion' ? 'selected' : '' }}>Suggestion / Idea</option>
                        <option value="both" {{ request('submission_type') == 'both' ? 'selected' : '' }}>Both</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <div class="input-group input-group-sm">
                        <input type="text" name="search" class="form-control" placeholder="Search Subject, Ref, Submitter, Dept..." value="{{ request('search') }}">
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-search"></i> Search</button>
                    </div>
                </div>
                <div class="col-md-2 text-end">
                    <a href="{{ route('admin.tickets.index') }}" class="btn btn-sm btn-outline-secondary w-100">
                        <i class="fa-solid fa-rotate-left me-1"></i> Reset
                    </a>
                </div>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Ref Number</th>
                            <th>Type</th>
                            <th>Submitter & Dept</th>
                            <th>Subject / System</th>
                            <th>Priority (SLA)</th>
                            <th>Status</th>
                            <th>SMS Escalation</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($tickets as $ticket)
                        <tr>
                            <td>
                                <div class="font-weight-bold text-dark">{{ $ticket->ticket_no }}</div>
                                <div class="text-xs text-muted">{{ $ticket->created_at->format('M d, Y') }}</div>
                            </td>
                            <td>
                                {!! $ticket->submission_type_badge !!}
                            </td>
                            <td>
                                <div class="font-weight-bold text-dark">{{ $ticket->submitter_name ?: ($ticket->user->name ?? 'Staff') }}</div>
                                <div class="text-xs text-muted">{{ $ticket->department ?: ($ticket->user->employee->department ?? 'General') }}</div>
                            </td>
                            <td>
                                <a href="{{ route('admin.tickets.show', $ticket) }}" class="text-decoration-none font-weight-bold text-dark d-block">
                                    {{ Str::limit($ticket->subject, 45) }}
                                </a>
                                @if($ticket->affected_system)
                                    <span class="text-xs text-muted"><i class="fa-solid fa-microchip me-1"></i>{{ $ticket->affected_system }}</span>
                                @endif
                            </td>
                            <td>{!! $ticket->priority_badge !!}</td>
                            <td>{!! $ticket->status_badge !!}</td>
                            <td>
                                @if($ticket->sms_alert_sent)
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                                        <i class="fa-solid fa-tower-broadcast me-1"></i>GM Alerted
                                    </span>
                                @else
                                    <span class="badge bg-light text-muted border">None</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('admin.tickets.show', $ticket) }}" class="btn btn-outline-primary" title="View & Process">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </a>
                                    <a href="{{ route('admin.tickets.print', $ticket) }}" target="_blank" class="btn btn-outline-dark" title="Print Official Form">
                                        <i class="fa-solid fa-print"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-clipboard-list fs-1 text-gray-300 d-block mb-3"></i>
                                <h5>No IT reports found</h5>
                                <p class="small mb-0">No records match the current filter criteria.</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($tickets->hasPages())
        <div class="card-footer bg-white">
            {{ $tickets->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
