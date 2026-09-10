@extends('layouts.app')

@section('title', 'Announcement Details & SMS Logs — ' . $announcement->title)

@section('content')
<div class="container-fluid px-4 py-4">
    {{-- Breadcrumb / Header --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-2 border-bottom">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="{{ route('admin.announcements.index') }}" class="text-decoration-none">Announcements</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Broadcast Report #{{ $announcement->id }}</li>
                </ol>
            </nav>
            <h3 class="fw-bold text-dark mb-0">
                <i class="fa-solid fa-chart-pie text-primary me-2"></i>{{ $announcement->title }}
            </h3>
        </div>
        <div class="d-flex gap-2 mt-2 mt-md-0">
            <a href="{{ route('admin.announcements.index') }}" class="btn btn-outline-secondary rounded-pill px-3 shadow-xs">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Announcements
            </a>
            <form method="POST" action="{{ route('admin.announcements.toggle-publish', $announcement->id) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn {{ $announcement->is_published ? 'btn-outline-warning' : 'btn-outline-success' }} rounded-pill px-3 shadow-xs">
                    <i class="fa-solid {{ $announcement->is_published ? 'fa-eye-slash' : 'fa-eye' }} me-1"></i>
                    {{ $announcement->is_published ? 'Deactivate In-App Banner' : 'Activate In-App Banner' }}
                </button>
            </form>
        </div>
    </div>

    {{-- Details Card --}}
    <div class="row g-4 mb-4">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-4 bg-white">
                <h5 class="fw-bold text-dark mb-3">Broadcast Message Content</h5>
                <div class="p-3 bg-light rounded-3 border mb-3 text-dark fs-6" style="white-space: pre-wrap; line-height: 1.6;">
                    {{ $announcement->message }}
                </div>
                <div class="row g-2 text-muted small">
                    <div class="col-sm-6">
                        <strong>Target Audience:</strong> 
                        <span class="text-dark">{{ ucfirst($announcement->target_type) }}</span>
                    </div>
                    <div class="col-sm-6">
                        <strong>Dispatched At:</strong> 
                        <span class="text-dark">{{ $announcement->created_at->format('M d, Y H:i:s') }}</span>
                    </div>
                    <div class="col-sm-6">
                        <strong>Dispatched By:</strong> 
                        <span class="text-dark">{{ $announcement->author->name ?? 'Global Admin' }}</span>
                    </div>
                    <div class="col-sm-6">
                        <strong>In-App Banner Status:</strong> 
                        @if($announcement->is_published)
                            <span class="badge bg-success">Active Live</span>
                        @else
                            <span class="badge bg-secondary">Inactive</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-4 bg-white">
                <h5 class="fw-bold text-dark mb-3">SMS Delivery Statistics</h5>
                <div class="row g-3 text-center">
                    <div class="col-4">
                        <div class="p-3 bg-light rounded-3 border">
                            <span class="text-muted small d-block">Recipients</span>
                            <h4 class="fw-bold text-dark mb-0">{{ $announcement->total_recipients }}</h4>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-3 bg-success-subtle rounded-3 border border-success-subtle">
                            <span class="text-success small d-block">Delivered</span>
                            <h4 class="fw-bold text-success mb-0">{{ $announcement->sms_sent_count }}</h4>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-3 bg-danger-subtle rounded-3 border border-danger-subtle">
                            <span class="text-danger small d-block">Failed/Skipped</span>
                            <h4 class="fw-bold text-danger mb-0">{{ $announcement->sms_failed_count }}</h4>
                        </div>
                    </div>
                </div>

                @php
                    $rate = $announcement->total_recipients > 0 ? round(($announcement->sms_sent_count / $announcement->total_recipients) * 100) : 0;
                @endphp
                <div class="mt-4">
                    <div class="d-flex justify-content-between small text-muted mb-1">
                        <span>Delivery Rate</span>
                        <span class="fw-bold text-dark">{{ $rate }}%</span>
                    </div>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar bg-success" role="progressbar" style="width: {{ $rate }}%" aria-valuenow="{{ $rate }}" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Individual Delivery Logs --}}
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
        <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center border-bottom">
            <h5 class="mb-0 fw-bold text-dark">
                <i class="fa-solid fa-list-check text-secondary me-2"></i>Recipient Delivery Logs
            </h5>
            <span class="badge bg-light text-dark border">{{ $logs->total() }} Logged</span>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light table-light small text-uppercase fw-semibold">
                        <tr>
                            <th class="ps-4">Recipient Staff</th>
                            <th>Phone Number</th>
                            <th>Status</th>
                            <th>Status Note / Gateway Details</th>
                            <th class="text-end pe-4">Logged At</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark">{{ $log->recipient_name ?? $log->employee?->full_name ?? 'Staff' }}</div>
                                    @if($log->employee)
                                        <small class="text-muted">{{ $log->employee->department ?? '' }} ({{ $log->employee->employee_code ?? '' }})</small>
                                    @endif
                                </td>
                                <td class="font-monospace small">
                                    {{ $log->phone_number }}
                                </td>
                                <td>
                                    @if($log->status === 'sent')
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                            <i class="fa-solid fa-check me-1"></i>Delivered
                                        </span>
                                    @elseif($log->status === 'skipped')
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1">
                                            <i class="fa-solid fa-ban me-1"></i>Skipped (No Phone)
                                        </span>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">
                                            <i class="fa-solid fa-xmark me-1"></i>Failed
                                        </span>
                                    @endif
                                </td>
                                <td class="small text-muted">
                                    {{ $log->error_message ?? 'Message accepted by AfroMessage gateway.' }}
                                </td>
                                <td class="text-end pe-4 small text-muted">
                                    {{ $log->created_at->format('M d, Y H:i:s') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">
                                    No individual SMS delivery logs recorded for this broadcast.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($logs->hasPages())
                <div class="p-3 border-top d-flex justify-content-end">
                    {{ $logs->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
