@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 mb-0 text-gray-800 fw-bold">
                <i class="fa-solid fa-triangle-exclamation text-danger me-2"></i>Material Damage Reports
            </h2>
            <p class="text-muted small mb-0">General Service Material Damage Log &amp; Reporting</p>
        </div>
        <a href="{{ route('material-damage-reports.create') }}" class="btn btn-primary shadow-sm">
            <i class="fa-solid fa-plus me-1"></i>Create Report
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-4">Report ID</th>
                            <th>Project</th>
                            <th>Material Item</th>
                            <th>Quantity Damaged</th>
                            <th>Reason / Type</th>
                            <th>Reported By</th>
                            <th>Status</th>
                            <th class="pe-4 text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reports as $report)
                        <tr>
                            <td class="ps-4 fw-bold text-primary">#MDR-{{ str_pad($report->id, 4, '0', STR_PAD_LEFT) }}</td>
                            <td class="fw-semibold">{{ $report->project->name ?? 'N/A' }}</td>
                            <td>{{ $report->product->name ?? 'N/A' }}</td>
                            <td>
                                <span class="fw-bold text-danger">{{ number_format($report->quantity, 2) }}</span> 
                                <small class="text-muted">{{ $report->product->unit ?? 'Units' }}</small>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">{{ $report->damage_reason }}</span>
                            </td>
                            <td>
                                <small class="fw-semibold text-dark">{{ $report->reporter->name ?? 'System User' }}</small>
                                <div class="text-muted" style="font-size: 11px;">{{ $report->created_at->format('M d, Y h:i A') }}</div>
                            </td>
                            <td>
                                @php
                                    $badge = match(strtolower($report->status)) {
                                        'approved' => 'bg-success',
                                        'rejected' => 'bg-danger',
                                        default => 'bg-warning text-dark'
                                    };
                                @endphp
                                <span class="badge {{ $badge }}">{{ ucfirst($report->status) }}</span>
                            </td>
                            <td class="pe-4 text-end">
                                <a href="{{ route('material-damage-reports.show', $report) }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="fa-solid fa-eye me-1"></i>View
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-clipboard-check fa-3x mb-3 text-secondary opacity-50"></i>
                                <h5>No Material Damage Reports Found</h5>
                                <p class="small text-muted mb-0">No damaged material reports have been submitted yet.</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
