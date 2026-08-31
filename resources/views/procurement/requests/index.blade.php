@extends('layouts.app')

@section('title', 'Material Requests')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Material Requests</h1>
    @can('material_requests.create')
    @if(!auth()->user()->hasRole('site_engineer'))
    <a href="{{ route('material-requests.create') }}" class="btn btn-primary">
        <i class="fa-solid fa-plus me-1"></i> New Request
    </a>
    @endif
    @endcan
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Reference</th>
                        <th>Source</th>
                        <th>Project</th>
                        <th>Destination Store</th>
                        <th>Required Date</th>
                        <th>Status</th>
                        <th>Created By</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $req)
                    <tr>
                        <td class="fw-semibold">{{ $req->reference_number }}</td>
                        <td><small class="badge bg-light text-dark border">{{ $req->source ?? 'Manual Creation' }}</small></td>
                        <td>
                            @if($req->project)
                                <a href="{{ route('projects.show', $req->project) }}" class="text-decoration-none">
                                    {{ $req->project->name }}
                                </a>
                            @else
                                <span class="text-muted">Central/HQ</span>
                            @endif
                        </td>
                        <td>{{ $req->store?->name ?? 'General Store' }}</td>
                        <td>
                            <span class="{{ optional($req->required_date)->isPast() && $req->status != 'fulfilled' ? 'text-danger fw-bold' : '' }}">
                                {{ optional($req->required_date)->format('d M Y') ?? '-' }}
                            </span>
                        </td>
                        <td>
                            @php
                                $badge = match($req->status) {
                                    'draft' => 'secondary',
                                    'pending_planning', 'submitted' => 'warning',
                                    'planning_approved' => 'info',
                                    'sent_to_store_manager' => 'primary',
                                    'sent_to_pr', 'transfer_created', 'fulfilled', 'approved' => 'success',
                                    'rejected' => 'danger',
                                    default => 'secondary'
                                };
                                $statusText = match($req->status) {
                                    'pending_planning', 'submitted' => 'PENDING PLANNING',
                                    'planning_approved' => 'PLANNING APPROVED',
                                    'sent_to_store_manager' => 'SENT TO STORE MANAGER',
                                    'sent_to_pr' => 'SENT TO PR',
                                    'transfer_created' => 'TRANSFER CREATED',
                                    default => strtoupper(str_replace('_', ' ', $req->status ?? 'pending'))
                                };
                            @endphp
                            <span class="badge bg-{{ $badge }}">{{ $statusText }}</span>
                        </td>
                        <td class="small text-muted">{{ $req->creator?->name ?? 'Staff' }}</td>
                        <td class="text-end">
                            <a href="{{ route('material-requests.show', $req) }}" class="btn btn-sm btn-outline-primary">View</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-file-signature fa-3x mb-3 opacity-50"></i>
                            <p class="mb-0">No material requests found.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($requests->hasPages())
    <div class="card-footer bg-transparent">
        {{ $requests->links() }}
    </div>
    @endif
</div>
@endsection
