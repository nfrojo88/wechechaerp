@extends('layouts.app')
@section('title', 'Daily Reports Approval Center — HR & Management')

@section('content')
<div class="container-fluid px-0">
    {{-- Page Header --}}
    <x-page-header
        title="Daily Reports Approval Center"
        subtitle="Review, audit manpower gang counts, and authorize or reject pending field daily reports submitted by Site Engineers."
        :breadcrumbs="[
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'Site Operations', 'url' => route('dashboard.site-engineer')],
            ['label' => 'Daily Reports', 'url' => route('daily-reports.index')],
            ['label' => 'Approval Center']
        ]">
        <x-slot name="actions">
            <a href="{{ route('daily-reports.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 py-2 shadow-xs fw-semibold">
                <i class="fa-solid fa-list me-1.5"></i>View All Reports
            </a>
        </x-slot>
    </x-page-header>

    {{-- Executive Summary KPI Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Total Pending"
                :value="number_format($statistics['total_pending'] ?? 0)"
                changeLabel="Reports in approval queue"
                icon="fa-solid fa-hourglass-half"
                color="primary" />
        </div>
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Submitted Logs"
                :value="number_format($statistics['pending_submitted'] ?? 0)"
                changeLabel="Awaiting management review"
                icon="fa-solid fa-clock"
                color="warning" />
        </div>
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Total Manpower"
                :value="number_format($statistics['total_manpower_pending'] ?? 0)"
                changeLabel="Workers in pending reports"
                icon="fa-solid fa-people-group"
                color="info" />
        </div>
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Avg Manpower / Day"
                :value="number_format(round($statistics['avg_manpower'] ?? 0, 1), 1)"
                changeLabel="Mean gang size per site"
                icon="fa-solid fa-calculator"
                color="success" />
        </div>
    </div>

    {{-- Filters Card --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('daily-reports.approval') }}" class="row g-2 align-items-end">
                <div class="col-12 col-sm-6 col-md-3">
                    <label class="form-label text-muted small fw-bold text-uppercase mb-1" style="font-size: 0.72rem;">Review Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="all" @selected(request('status')=='all')>All Pending</option>
                        <option value="submitted" @selected(request('status')=='submitted')>Submitted</option>
                        <option value="draft" @selected(request('status')=='draft')>Draft</option>
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-md-3">
                    <label class="form-label text-muted small fw-bold text-uppercase mb-1" style="font-size: 0.72rem;">Project Filter</label>
                    <select name="project_id" class="form-select form-select-sm">
                        <option value="">All Projects</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}" @selected(request('project_id')==$project->id)>
                                {{ $project->project_name ?? $project->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label text-muted small fw-bold text-uppercase mb-1" style="font-size: 0.72rem;">Min Manpower</label>
                    <input type="number" name="min_manpower" class="form-control form-control-sm" value="{{ request('min_manpower') }}" placeholder="0">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label text-muted small fw-bold text-uppercase mb-1" style="font-size: 0.72rem;">Max Manpower</label>
                    <input type="number" name="max_manpower" class="form-control form-control-sm" value="{{ request('max_manpower') }}" placeholder="999">
                </div>
                <div class="col-12 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm rounded-pill px-3 py-2 w-100 fw-semibold shadow-xs">
                        <i class="fa-solid fa-filter me-1.5"></i>Filter
                    </button>
                    @if(request()->hasAny(['status', 'project_id', 'min_manpower', 'max_manpower']))
                        <a href="{{ route('daily-reports.approval') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 py-2 shadow-xs" title="Reset filter">
                            <i class="fa-solid fa-rotate-left"></i>
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    {{-- Bulk Actions Card --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4 d-none bg-primary bg-opacity-10 border border-primary border-opacity-25" id="bulkActionsCard">
        <div class="card-body p-3 d-flex flex-wrap gap-3 align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary rounded-pill px-3 py-1.5 fs-6 font-tabular">
                    <span id="selectedCount">0</span> Selected
                </span>
                <span class="text-dark fw-medium small">Daily field reports selected for batch action.</span>
            </div>
            <form action="{{ route('daily-reports.bulkApprove') }}" method="POST" class="d-inline">
                @csrf
                <input type="hidden" name="report_ids" id="bulkReportIds">
                <button type="submit" class="btn btn-success btn-sm rounded-pill px-4 py-2 shadow-xs fw-semibold" onclick="return confirm('Approve all selected daily reports now?')">
                    <i class="fa-solid fa-circle-check me-1.5"></i>Approve Selected Reports
                </button>
            </form>
        </div>
    </div>

    {{-- Reports Approval Table Card --}}
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-warning bg-opacity-10 text-warning rounded-pill px-2.5 py-1 fw-bold small">
                    <i class="fa-solid fa-clipboard-check me-1"></i>Approval Queue
                </span>
                <h6 class="fw-bold mb-0 text-dark">Pending Daily Reports</h6>
            </div>
            <span class="text-muted small">Showing {{ $reports->count() }} of {{ $reports->total() }} reports</span>
        </div>
        <div class="card-body p-0">
            <form id="approvalForm">
                @csrf
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 wc-table-mobile-cards">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4" style="width: 44px;">
                                    <input type="checkbox" id="selectAll" class="form-check-input">
                                </th>
                                <th class="text-secondary text-uppercase small fw-bold">Report ID</th>
                                <th class="text-secondary text-uppercase small fw-bold">Project</th>
                                <th class="text-secondary text-uppercase small fw-bold">Date</th>
                                <th class="text-secondary text-uppercase small fw-bold">Submitted By</th>
                                <th class="text-center text-secondary text-uppercase small fw-bold">Sent Manpower</th>
                                <th class="text-center text-secondary text-uppercase small fw-bold">Tasks Breakdown</th>
                                <th class="text-secondary text-uppercase small fw-bold">Status</th>
                                <th class="pe-4 text-end text-secondary text-uppercase small fw-bold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($reports as $report)
                                @php
                                    $itemWorkers = $report->items->sum('workers_count');
                                    $isSubmitted = $report->status === 'submitted';
                                @endphp
                                <tr>
                                    <td class="ps-4" data-label="Select">
                                        <input type="checkbox" class="form-check-input report-checkbox" value="{{ $report->id }}">
                                    </td>
                                    <td class="font-monospace fw-bold text-primary" data-label="Report ID">
                                        #DR-{{ str_pad($report->id, 5, '0', STR_PAD_LEFT) }}
                                    </td>
                                    <td data-label="Project">
                                        <div class="fw-semibold text-dark">{{ $report->project->project_name ?? $report->project->name }}</div>
                                    </td>
                                    <td class="font-monospace text-muted small" data-label="Date">
                                        {{ $report->report_date->format('M d, Y') }}
                                    </td>
                                    <td data-label="Submitted By">
                                        <small class="fw-semibold text-dark">{{ $report->createdBy->name ?? 'Field Engineer' }}</small>
                                    </td>
                                    <td class="text-center" data-label="Manpower">
                                        <span class="badge bg-info bg-opacity-10 text-info rounded-pill px-3 py-1 font-tabular fw-bold">
                                            <i class="fa-solid fa-users me-1"></i>{{ $report->total_manpower }}
                                        </span>
                                    </td>
                                    <td class="text-center" data-label="Tasks">
                                        <span class="badge bg-light text-muted border font-tabular">
                                            {{ $report->items->count() }} items ({{ $itemWorkers }} crew)
                                        </span>
                                    </td>
                                    <td data-label="Status">
                                        @if($isSubmitted)
                                            <span class="badge bg-warning bg-opacity-10 text-warning rounded-pill px-2.5 py-1 fw-bold">Submitted</span>
                                        @else
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-2.5 py-1">Draft</span>
                                        @endif
                                    </td>
                                    <td class="pe-4 text-end" data-label="Actions">
                                        <div class="d-inline-flex gap-1">
                                            <a href="{{ route('daily-reports.show', $report) }}" class="btn btn-sm btn-outline-primary rounded-pill px-2.5 py-1 shadow-xs" title="View Full Report">
                                                <i class="fa-solid fa-eye"></i>
                                            </a>
                                            <form action="{{ route('daily-reports.approve', $report) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-success rounded-pill px-2.5 py-1 shadow-xs" onclick="return confirm('Approve this daily report?')" title="Approve Report">
                                                    <i class="fa-solid fa-check"></i>
                                                </button>
                                            </form>
                                            <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-2.5 py-1 shadow-xs" 
                                                    data-bs-toggle="modal" data-bs-target="#rejectModal" onclick="setRejectReport({{ $report->id }})" title="Reject Report">
                                                <i class="fa-solid fa-xmark"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center py-5 text-muted">
                                        <x-empty-state
                                            title="No Pending Reports to Approve"
                                            description="All submitted daily site logs have been approved. The approval queue is clear."
                                            icon="fa-solid fa-circle-check" />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </form>
        </div>
        @if($reports->hasPages())
            <div class="card-footer bg-white border-0 py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="text-muted small">
                    Showing {{ $reports->firstItem() ?? 0 }} to {{ $reports->lastItem() ?? 0 }} of {{ $reports->total() }} reports
                </span>
                <div>
                    {{ $reports->links() }}
                </div>
            </div>
        @endif
    </div>
</div>

{{-- Reject Modal --}}
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-bottom py-3 px-4">
                <h5 class="modal-title fw-bold text-danger">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i>Reject Daily Report
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="rejectForm" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary text-uppercase" style="font-size: 0.72rem;">Rejection Reason <span class="text-danger">*</span></label>
                        <textarea name="rejection_reason" class="form-control" rows="4" required placeholder="Specify why this report is rejected or needs revision..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top py-2 px-4">
                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm rounded-pill px-4 shadow-sm fw-semibold">
                        <i class="fa-solid fa-xmark me-1"></i>Confirm Rejection
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function setRejectReport(reportId) {
    document.getElementById('rejectForm').action = `/daily-reports/${reportId}/reject`;
}

// Checkbox selection
const selectAllEl = document.getElementById('selectAll');
if (selectAllEl) {
    selectAllEl.addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.report-checkbox');
        checkboxes.forEach(cb => cb.checked = this.checked);
        updateBulkActions();
    });
}

document.querySelectorAll('.report-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', updateBulkActions);
});

function updateBulkActions() {
    const checked = document.querySelectorAll('.report-checkbox:checked');
    const bulkCard = document.getElementById('bulkActionsCard');
    
    if (bulkCard) {
        if (checked.length > 0) {
            bulkCard.classList.remove('d-none');
            document.getElementById('selectedCount').textContent = checked.length;
            const ids = Array.from(checked).map(cb => cb.value);
            document.getElementById('bulkReportIds').value = JSON.stringify(ids);
        } else {
            bulkCard.classList.add('d-none');
        }
    }
}
</script>
@endpush
@endsection
