@extends('layouts.app')

@php
    $authUser = auth()->user();
    $rawUserRoles = $authUser ? $authUser->roles->pluck('name')->map(fn($r) => strtolower(str_replace([' ', '-'], '_', trim($r))))->toArray() : [];
    $isAuditorUser = in_array('auditor', $rawUserRoles) || in_array('audit', $rawUserRoles) || in_array('internal_auditor', $rawUserRoles) || in_array('audit_team', $rawUserRoles) || ($authUser && $authUser->hasAnyRole(['auditor', 'audit', 'internal_auditor', 'Auditor', 'Audit']));
@endphp

@section('title', $isAuditorUser ? 'Purchase Requests (Read-Only)' : 'Purchase Requests')
@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-0"><i class="fas {{ $isAuditorUser ? 'fa-shield-halved text-info' : 'fa-file-invoice text-primary' }} me-2"></i>Purchase Requests</h1>
            @if($isAuditorUser)
                <small class="text-muted">Internal Audit view of all submitted purchase requests and requisition statuses</small>
            @endif
        </div>
        @if(!$isAuditorUser)
            <a href="{{ route('purchase-requests.create') }}" class="btn btn-primary"><i class="fas fa-plus me-1"></i>New PR</a>
        @else
            <span class="badge bg-info text-dark px-3 py-2 fs-6 rounded-pill fw-bold"><i class="fa-solid fa-eye me-1"></i>Read-Only Audit Stream</span>
        @endif
    </div>
    @if(session('success'))<div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>@endif
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr><th>PR No</th><th>Project</th><th>Priority</th><th>Type</th><th>Required Date</th><th>Status</th><th>Requested By</th><th class="text-center">Action</th></tr>
                    </thead>
                    <tbody>
                        @forelse($prs as $pr)
                        <tr>
                            <td><strong>{{ $pr->pr_no }}</strong></td>
                            <td>{{ $pr->project?->name ?? $pr->project?->project_name ?? 'N/A' }}</td>
                            <td><span class="badge bg-{{ $pr->priority === 'urgent' ? 'danger' : ($pr->priority === 'high' ? 'warning' : 'secondary') }}">{{ ucfirst($pr->priority ?? 'normal') }}</span></td>
                            <td>{{ ucfirst($pr->type ?? 'material') }}</td>
                            <td>{{ optional($pr->required_date)->format('d M Y') ?? '-' }}</td>
                            <td><span class="badge bg-info">{{ str_replace('_',' ',ucfirst($pr->status ?? 'pending')) }}</span></td>
                            <td>{{ $pr->requestedBy?->name ?? 'Staff' }}</td>
                            <td class="text-center"><a href="{{ route('purchase-requests.show', $pr) }}" class="btn btn-sm btn-outline-info"><i class="fas fa-eye"></i></a></td>
                        </tr>
                        @empty
                        <tr><td colspan="8" class="text-center py-4 text-muted">No purchase requests found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer">{{ $prs->links() }}</div>
    </div>
</div>
@endsection
