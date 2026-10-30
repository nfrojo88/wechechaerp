@extends('layouts.app')
@section('title', 'Incoming HR Reports — General Manager')

@section('content')
<div class="container-fluid px-0">
    {{-- Page Header --}}
    <x-page-header
        title="Incoming HR Reports for General Manager"
        subtitle="Executive review center for HR attendance submissions, turnover registers, and monthly payroll expenditure reports."
        :breadcrumbs="[
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'Executive Office', 'url' => route('dashboard.gm')],
            ['label' => 'Incoming HR Reports']
        ]">
        <x-slot:actions>
            <a href="{{ route('dashboard.gm') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 py-2 shadow-xs fw-semibold">
                <i class="fa-solid fa-arrow-left me-1.5"></i>GM Dashboard
            </a>
            <a href="{{ route('reports.attendance') }}" class="btn btn-primary btn-sm rounded-pill px-3 py-2 shadow-xs fw-semibold">
                <i class="fa-solid fa-chart-pie me-1.5"></i>Attendance Analytics
            </a>
        </x-slot:actions>
    </x-page-header>

    {{-- System Alerts --}}
    @if(session('success'))
        <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center gap-3 p-3 bg-success bg-opacity-10 border-start border-4 border-success">
            <div class="p-2 bg-success text-white rounded-circle fs-5 flex-shrink-0">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <div class="flex-grow-1 text-dark fw-medium">{{ session('success') }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Executive Summary KPI Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <x-kpi-card
                title="Total Submissions"
                :value="number_format($submissions->total())"
                changeLabel="All incoming HR logs"
                icon="fa-solid fa-inbox"
                color="primary" />
        </div>
        <div class="col-12 col-md-4">
            <x-kpi-card
                title="Pending Review"
                :value="number_format($submissions->where('status', '!=', 'reviewed')->where('status', '!=', 'acknowledged')->count())"
                changeLabel="Awaiting GM feedback"
                icon="fa-solid fa-clock"
                color="warning" />
        </div>
        <div class="col-12 col-md-4">
            <x-kpi-card
                title="Acknowledged / Reviewed"
                :value="number_format($submissions->whereIn('status', ['reviewed', 'acknowledged'])->count())"
                changeLabel="Executive decisions recorded"
                icon="fa-solid fa-circle-check"
                color="success" />
        </div>
    </div>

    {{-- Submissions Table Card --}}
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-2.5 py-1 fw-bold small">
                    <i class="fa-solid fa-inbox me-1"></i>Inbox
                </span>
                <h6 class="fw-bold mb-0 text-dark">Submitted HR Reports Log</h6>
            </div>
            <span class="text-muted small">Showing {{ $submissions->count() }} of {{ $submissions->total() }} reports</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 wc-table-mobile-cards">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4 text-secondary text-uppercase small fw-bold">Report Type</th>
                        <th class="text-secondary text-uppercase small fw-bold">Period Scope</th>
                        <th class="text-center text-secondary text-uppercase small fw-bold">Avg Attendance</th>
                        <th class="text-secondary text-uppercase small fw-bold">Submitted By</th>
                        <th class="text-secondary text-uppercase small fw-bold">Date Received</th>
                        <th class="text-secondary text-uppercase small fw-bold">Status</th>
                        <th class="pe-4 text-end text-secondary text-uppercase small fw-bold">Decision</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($submissions as $sub)
                        <tr>
                            <td class="ps-4" data-label="Report Type">
                                <div class="fw-bold text-dark">{{ $sub->report_type }}</div>
                                @if($sub->notes)
                                    <small class="text-muted d-block"><i class="fa-solid fa-quote-left me-1 opacity-50"></i>{{ Str::limit($sub->notes, 60) }}</small>
                                @endif
                            </td>
                            <td data-label="Period">
                                <div class="fw-semibold text-dark">{{ $sub->from_date ? $sub->from_date->format('d M') : '-' }} &ndash; {{ $sub->to_date ? $sub->to_date->format('d M Y') : '-' }}</div>
                                <span class="badge bg-light text-muted border" style="font-size: 0.7rem;">{{ $sub->total_working_days }} working days</span>
                            </td>
                            <td class="text-center" data-label="Attendance">
                                <span class="badge {{ $sub->avg_attendance_rate >= 80 ? 'bg-success' : 'bg-warning text-dark' }} rounded-pill px-3 py-1 font-tabular">
                                    {{ number_format($sub->avg_attendance_rate, 1) }}%
                                </span>
                            </td>
                            <td data-label="Submitted By">
                                <div class="fw-semibold text-dark">{{ $sub->submitter->name ?? 'HR Officer' }}</div>
                                <small class="text-muted">{{ $sub->submitter->email ?? '' }}</small>
                            </td>
                            <td class="font-monospace text-muted small" data-label="Date Received">
                                {{ $sub->created_at ? $sub->created_at->format('d M Y, H:i') : '-' }}
                            </td>
                            <td data-label="Status">
                                @if($sub->status === 'reviewed' || $sub->status === 'acknowledged')
                                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1 fw-bold">
                                        <i class="fa-solid fa-check-double me-1"></i>{{ ucfirst($sub->status) }}
                                    </span>
                                @else
                                    <span class="badge bg-warning bg-opacity-10 text-warning rounded-pill px-3 py-1 fw-bold">
                                        <i class="fa-solid fa-clock me-1"></i>Pending Review
                                    </span>
                                @endif
                            </td>
                            <td class="pe-4 text-end" data-label="Decision">
                                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1.5 shadow-xs fw-semibold" data-bs-toggle="modal" data-bs-target="#reviewModal{{ $sub->id }}">
                                    <i class="fa-solid fa-clipboard-check me-1"></i>Review / Acknowledge
                                </button>
                            </td>
                        </tr>

                        {{-- Modal for GM Review --}}
                        <div class="modal fade" id="reviewModal{{ $sub->id }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content rounded-4 border-0 shadow">
                                    <form action="{{ Route::has('reports.submission.review') ? route('reports.submission.review', $sub->id) : url('/reports/submission/' . $sub->id . '/review') }}" method="POST">
                                        @csrf
                                        <div class="modal-header border-bottom py-3 px-4">
                                            <h5 class="modal-title fw-bold text-dark">
                                                <i class="fa-solid fa-check-to-slot text-primary me-2"></i>Review Report #{{ $sub->id }}
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body p-4">
                                            <div class="bg-light p-3 rounded-4 mb-3 border">
                                                <div class="fw-bold text-dark fs-6">{{ $sub->report_type }}</div>
                                                <div class="text-muted small">Period: {{ $sub->from_date?->format('d M Y') }} – {{ $sub->to_date?->format('d M Y') }}</div>
                                                <div class="text-muted small">Submitted by: {{ $sub->submitter?->name }} ({{ $sub->created_at?->format('d M Y, H:i') }})</div>
                                                @if($sub->notes)
                                                    <div class="mt-2 text-dark small bg-white p-2.5 rounded-3 border">
                                                        <strong>HR Notes:</strong> {{ $sub->notes }}
                                                    </div>
                                                @endif
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label small fw-bold text-secondary text-uppercase" style="font-size: 0.72rem;">GM Review Action</label>
                                                <select name="status" class="form-select" required>
                                                    <option value="reviewed" {{ $sub->status === 'reviewed' ? 'selected' : '' }}>Mark as Reviewed</option>
                                                    <option value="acknowledged" {{ $sub->status === 'acknowledged' ? 'selected' : '' }}>Acknowledge &amp; Accept</option>
                                                    <option value="rejected" {{ $sub->status === 'rejected' ? 'selected' : '' }}>Return / Inquire with HR</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label small fw-bold text-secondary text-uppercase" style="font-size: 0.72rem;">GM Remarks / Instructions (Optional)</label>
                                                <textarea name="gm_remarks" class="form-control" rows="3" placeholder="Enter remarks or instructions for HR...">{{ $sub->gm_remarks }}</textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer border-top py-2 px-4 d-flex justify-content-between">
                                            <a href="{{ route('reports.attendance', ['from_date' => $sub->from_date?->toDateString(), 'to_date' => $sub->to_date?->toDateString()]) }}" target="_blank" class="btn btn-outline-info btn-sm rounded-pill px-3">
                                                <i class="fa-solid fa-arrow-up-right-from-square me-1"></i>Inspect Analytics
                                            </a>
                                            <div class="d-flex gap-2">
                                                <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">Close</button>
                                                <button type="submit" class="btn btn-primary btn-sm rounded-pill px-4 shadow-sm fw-semibold">Save Decision</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <x-empty-state
                                    title="No HR Reports In Queue"
                                    description="No attendance or manpower reports have been submitted to the General Manager inbox."
                                    icon="fa-solid fa-inbox" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($submissions->hasPages())
            <div class="card-footer bg-white border-0 py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="text-muted small">
                    Showing {{ $submissions->firstItem() ?? 0 }} to {{ $submissions->lastItem() ?? 0 }} of {{ $submissions->total() }} reports
                </span>
                <div>
                    {{ $submissions->links() }}
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
