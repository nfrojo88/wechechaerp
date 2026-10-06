@extends('layouts.app')
@section('title', 'My IT Problem Reports & Suggestions')
@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h1 class="h3 mb-0 text-gray-800 font-weight-bold">
                <i class="fa-solid fa-laptop-medical text-primary me-2"></i>My IT Problem Reports & Suggestions
            </h1>
            <p class="text-muted small mb-0">Track all your submitted IT issues, improvement ideas, and resolution updates.</p>
        </div>
        <a href="{{ route('tickets.create') }}" class="btn btn-primary shadow-sm">
            <i class="fa-solid fa-plus-circle me-1"></i> New IT Report / Suggestion
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3 border-bottom">
            <div class="d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-dark">Submitted Reports</h6>
                <span class="badge bg-light text-dark border">{{ $tickets->total() }} Record(s)</span>
            </div>
        </div>
        <div class="card-body p-0">
            @if($tickets->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Ref Number</th>
                                <th>Type</th>
                                <th>Subject</th>
                                <th>Category / System</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Submitted Date</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($tickets as $ticket)
                            <tr>
                                <td>
                                    <span class="font-weight-bold text-dark">{{ $ticket->ticket_no }}</span>
                                </td>
                                <td>{!! $ticket->submission_type_badge !!}</td>
                                <td>
                                    <a href="{{ route('tickets.show', $ticket) }}" class="text-decoration-none text-dark font-weight-bold">
                                        {{ Str::limit($ticket->subject, 45) }}
                                    </a>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">{{ $ticket->category }}</span>
                                    @if($ticket->affected_system)
                                        <small class="text-muted d-block mt-1">{{ $ticket->affected_system }}</small>
                                    @endif
                                </td>
                                <td>{!! $ticket->priority_badge !!}</td>
                                <td>{!! $ticket->status_badge !!}</td>
                                <td>{{ $ticket->submitted_date ? $ticket->submitted_date->format('M d, Y') : $ticket->created_at->format('M d, Y') }}</td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('tickets.show', $ticket) }}" class="btn btn-outline-primary" title="View details">
                                            <i class="fa-solid fa-eye me-1"></i> View
                                        </a>
                                        <a href="{{ route('tickets.print', $ticket) }}" target="_blank" class="btn btn-outline-dark" title="Print Official Form">
                                            <i class="fa-solid fa-print"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="p-5 text-center text-muted">
                    <i class="fa-solid fa-clipboard-question fs-1 text-gray-300 mb-3"></i>
                    <h5>No IT Reports Found</h5>
                    <p class="mb-4 small">You haven't submitted any IT problem reports or suggestions yet.</p>
                    <a href="{{ route('tickets.create') }}" class="btn btn-primary">
                        <i class="fa-solid fa-plus-circle me-1"></i> Submit Problem Report or Idea
                    </a>
                </div>
            @endif
        </div>
        @if($tickets->hasPages())
        <div class="card-footer bg-white">
            {{ $tickets->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
