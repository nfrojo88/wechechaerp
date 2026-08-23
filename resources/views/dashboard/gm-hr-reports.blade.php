@extends('layouts.app')
@section('title', 'GM - Incoming HR Reports - Construct-Pro ERP')

@section('content')
<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="h3 mb-1 fw-bold">
                <i class="fa-solid fa-file-signature text-primary me-2"></i>Incoming HR Reports for General Manager
            </h2>
            <p class="text-muted small mb-0">Review submitted HR attendance, turnover, and payroll cost summaries from HR</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('reports.attendance') }}" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                <i class="fa-solid fa-chart-pie me-1"></i>View Full Attendance Analytics
            </a>
        </div>
    </div>

    <!-- Submissions Table -->
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-light py-3 border-0 rounded-top-4">
            <h5 class="fw-bold mb-0 text-dark">
                <i class="fa-solid fa-inbox me-2 text-primary"></i>Submitted HR Reports Log
            </h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Report Type</th>
                        <th>Period</th>
                        <th>Avg Attendance</th>
                        <th>Submitted By</th>
                        <th>Submitted At</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($submissions as $sub)
                    <tr>
                        <td>
                            <div class="fw-bold text-dark">{{ $sub->report_type }}</div>
                            @if($sub->notes)
                                <small class="text-muted"><i class="fa-solid fa-quote-left me-1 opacity-50"></i>{{ Str::limit($sub->notes, 60) }}</small>
                            @endif
                        </td>
                        <td>
                            <strong>{{ $sub->from_date ? $sub->from_date->format('d M') : '-' }}</strong> to <strong>{{ $sub->to_date ? $sub->to_date->format('d M Y') : '-' }}</strong>
                            <small class="d-block text-muted">{{ $sub->total_working_days }} working days</small>
                        </td>
                        <td>
                            <span class="badge {{ $sub->avg_attendance_rate >= 80 ? 'bg-success' : 'bg-warning text-dark' }} rounded-pill px-3 py-1">
                                {{ number_format($sub->avg_attendance_rate, 1) }}%
                            </span>
                        </td>
                        <td>
                            <div class="fw-semibold">{{ $sub->submitter->name ?? 'HR Officer' }}</div>
                            <small class="text-muted">{{ $sub->submitter->email ?? '' }}</small>
                        </td>
                        <td>{{ $sub->created_at ? $sub->created_at->format('d M Y, h:i A') : '-' }}</td>
                        <td>
                            @if($sub->status === 'reviewed' || $sub->status === 'acknowledged')
                                <span class="badge bg-success rounded-pill px-3">
                                    <i class="fa-solid fa-check-double me-1"></i>{{ ucfirst($sub->status) }}
                                </span>
                            @else
                                <span class="badge bg-warning text-dark rounded-pill px-3">
                                    <i class="fa-solid fa-clock me-1"></i>Pending Review
                                </span>
                            @endif
                        </td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#reviewModal{{ $sub->id }}">
                                <i class="fa-solid fa-eye me-1"></i>Review / Acknowledge
                            </button>
                        </td>
                    </tr>

                    <!-- Modal for GM Review -->
                    <div class="modal fade" id="reviewModal{{ $sub->id }}" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content rounded-4 border-0 shadow">
                                <form action="{{ route('reports.submission.review', $sub->id) }}" method="POST">
                                    @csrf
                                    <div class="modal-header border-0 bg-light rounded-top-4 py-3">
                                        <h5 class="modal-title fw-bold text-dark">
                                            <i class="fa-solid fa-check-to-slot text-primary me-2"></i>Review Report #{{ $sub->id }}
                                        </h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body p-4">
                                        <div class="bg-light p-3 rounded-3 mb-3">
                                            <div class="fw-bold text-dark">{{ $sub->report_type }}</div>
                                            <small class="text-muted d-block">Period: {{ $sub->from_date?->format('d M Y') }} – {{ $sub->to_date?->format('d M Y') }}</small>
                                            <small class="text-muted d-block">Submitted by: {{ $sub->submitter?->name }} ({{ $sub->created_at?->format('d M Y, h:i A') }})</small>
                                            @if($sub->notes)
                                                <div class="mt-2 text-dark small bg-white p-2 rounded border"><strong>HR Notes:</strong> {{ $sub->notes }}</div>
                                            @endif
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label small fw-bold text-secondary">GM Review Action</label>
                                            <select name="status" class="form-select rounded-3" required>
                                                <option value="reviewed" {{ $sub->status === 'reviewed' ? 'selected' : '' }}>Mark as Reviewed</option>
                                                <option value="acknowledged" {{ $sub->status === 'acknowledged' ? 'selected' : '' }}>Acknowledge &amp; Accept</option>
                                                <option value="rejected" {{ $sub->status === 'rejected' ? 'selected' : '' }}>Return / Inquire with HR</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label small fw-bold text-secondary">GM Remarks / Feedback (Optional)</label>
                                            <textarea name="gm_remarks" class="form-control rounded-3" rows="3" placeholder="Enter remarks or instructions for HR...">{{ $sub->gm_remarks }}</textarea>
                                        </div>
                                    </div>
                                    <div class="modal-footer border-0 bg-light rounded-bottom-4 py-2">
                                        <a href="{{ route('reports.attendance', ['from_date' => $sub->from_date?->toDateString(), 'to_date' => $sub->to_date?->toDateString()]) }}" target="_blank" class="btn btn-outline-info btn-sm rounded-pill me-auto">
                                            <i class="fa-solid fa-arrow-up-right-from-square me-1"></i>View Full Data
                                        </a>
                                        <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">Close</button>
                                        <button type="submit" class="btn btn-primary btn-sm rounded-pill px-4 shadow-sm">Save Action</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-inbox fa-3x mb-2 opacity-50"></i>
                            <p class="mb-0">No HR reports submitted to GM yet.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($submissions->hasPages())
            <div class="card-footer bg-light border-0">
                {{ $submissions->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
