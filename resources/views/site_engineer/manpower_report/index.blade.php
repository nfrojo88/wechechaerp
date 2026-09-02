@extends('layouts.app')
@section('title', 'My Manpower Reports')

@section('content')
<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-1" style="color:#1e3a5f;">
                <i class="fa-solid fa-clock-rotate-left text-primary me-2"></i>My Morning Manpower Reports
            </h4>
            <p class="text-muted small mb-0">History of all your submitted daily workforce reports</p>
        </div>
        <a href="{{ route('manpower-daily-report.create') }}" class="btn btn-primary shadow-sm">
            <i class="fa-solid fa-plus me-1"></i> New Morning Report
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4">
            <i class="fa-solid fa-circle-check me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Project</th>
                            <th class="text-center">Total Present</th>
                            <th>Work Area</th>
                            <th>Submitted At</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Review Note</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reports as $report)
                        <tr>
                            <td class="fw-semibold">{{ $report->report_date->format('D, d M Y') }}</td>
                            <td>{{ $report->project->name ?? 'N/A' }}</td>
                            <td class="text-center">
                                <span class="badge bg-primary rounded-pill px-3">{{ $report->total_present }}</span>
                            </td>
                            <td class="text-muted small">{{ $report->work_area ?: '—' }}</td>
                            <td class="text-muted small">{{ $report->created_at->format('h:i A') }}</td>
                            <td class="text-center">
                                <span class="badge {{ $report->status_badge_class }}">{{ $report->status_label }}</span>
                            </td>
                            <td class="text-muted small">
                                {{ $report->review_notes ? \Illuminate\Support\Str::limit($report->review_notes, 40) : '—' }}
                            </td>
                            <td class="text-end">
                                <a href="{{ route('manpower-daily-report.show', $report) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-5">
                                <i class="fa-solid fa-inbox fa-3x mb-3 d-block"></i>
                                No manpower reports submitted yet.
                                <br>
                                <a href="{{ route('manpower-daily-report.create') }}" class="btn btn-sm btn-primary mt-3">Submit Today's Report</a>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($reports->hasPages())
        <div class="card-footer bg-white py-3">
            {{ $reports->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
