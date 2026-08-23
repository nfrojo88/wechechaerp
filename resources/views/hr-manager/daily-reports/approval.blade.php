@extends('layouts.app')
@section('title', 'Daily Reports Approval - HR Manager')
@section('content')

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">
                <i class="fas fa-file-check me-2 text-primary"></i>Daily Reports Approval
            </h1>
            <p class="text-muted mt-1">Review and approve pending daily reports from site</p>
        </div>
        <a href="{{ route('daily-reports.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-list me-1"></i>View All Reports
        </a>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                        Total Pending
                    </div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $statistics['total_pending'] ?? 0 }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                        Submitted
                    </div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $statistics['pending_submitted'] ?? 0 }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                        Total Manpower
                    </div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $statistics['total_manpower_pending'] ?? 0 }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                        Avg Manpower/Day
                    </div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800">{{ round($statistics['avg_manpower'] ?? 0, 1) }}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('daily-reports.approval') }}" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="all" @selected(request('status')=='all')>All Pending</option>
                        <option value="submitted" @selected(request('status')=='submitted')>Submitted</option>
                        <option value="draft" @selected(request('status')=='draft')>Draft</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Project</label>
                    <select name="project_id" class="form-select">
                        <option value="">All Projects</option>
                        @foreach($projects as $project)
                        <option value="{{ $project->id }}" @selected(request('project_id')==$project->id)>
                            {{ $project->project_name ?? $project->name }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Min Manpower</label>
                    <input type="number" name="min_manpower" class="form-control" 
                           value="{{ request('min_manpower') }}" placeholder="0">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Max Manpower</label>
                    <input type="number" name="max_manpower" class="form-control" 
                           value="{{ request('max_manpower') }}" placeholder="999">
                </div>
                <div class="col-md-2 d-flex gap-2 align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-1"></i>Filter
                    </button>
                    @if(request()->hasAny(['status', 'project_id', 'min_manpower', 'max_manpower']))
                    <a href="{{ route('daily-reports.approval') }}" class="btn btn-outline-secondary">Clear</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Bulk Actions -->
    <div class="card border-0 shadow-sm mb-4 d-none" id="bulkActionsCard">
        <div class="card-body d-flex gap-2 align-items-center">
            <span class="text-muted">Selected: <strong id="selectedCount">0</strong></span>
            <form action="{{ route('daily-reports.bulkApprove') }}" method="POST" class="d-inline">
                @csrf
                <input type="hidden" name="report_ids" id="bulkReportIds">
                <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Approve selected reports?')">
                    <i class="fas fa-check-circle me-1"></i>Approve Selected
                </button>
            </form>
        </div>
    </div>

    <!-- Reports Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-light">
            <h6 class="mb-0 font-weight-bold">
                <i class="fas fa-table me-2"></i>Pending Daily Reports
            </h6>
        </div>
        <div class="card-body p-0">
            <form id="approvalForm">
                @csrf
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50px;">
                                    <input type="checkbox" id="selectAll" class="form-check-input">
                                </th>
                                <th>Report ID</th>
                                <th>Project</th>
                                <th>Date</th>
                                <th>Submitted By</th>
                                <th class="text-center">
                                    <i class="fas fa-people-group me-1"></i>Sent Manpower
                                </th>
                                <th class="text-center">
                                    <i class="fas fa-tasks me-1"></i>Tasks Breakdown
                                </th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($reports as $report)
                            @php
                                $itemWorkers = $report->items->sum('workers_count');
                            @endphp
                            <tr>
                                <td>
                                    <input type="checkbox" class="form-check-input report-checkbox" 
                                           value="{{ $report->id }}" data-manpower="{{ $report->total_manpower }}">
                                </td>
                                <td>
                                    <a href="{{ route('daily-reports.show', $report) }}" class="text-decoration-none">
                                        <strong>#{{ $report->id }}</strong>
                                    </a>
                                </td>
                                <td>
                                    <small class="fw-bold">{{ $report->project->project_name ?? $report->project->name ?? 'N/A' }}</small>
                                </td>
                                <td>{{ $report->report_date->format('M d, Y') }}</td>
                                <td>
                                    <small><i class="fas fa-user-hard-hat me-1 text-secondary"></i>{{ $report->createdBy->name ?? 'Site Engineer' }}</small>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-primary fs-6 px-3 py-1">{{ $report->total_manpower }} Total Sent</span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark border">{{ $report->items->count() }} Tasks ({{ $itemWorkers }} Allocated)</span>
                                </td>
                                <td>
                                    <span class="badge bg-{{ $report->status === 'submitted' ? 'warning text-dark' : 'secondary' }}">
                                        {{ ucfirst($report->status) }}
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="{{ route('daily-reports.show', $report) }}" 
                                           class="btn btn-outline-primary" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <form action="{{ route('daily-reports.approve', $report) }}" method="POST" class="d-inline"
                                              onsubmit="return confirm('Approve this report?')">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-success" title="Approve">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </form>
                                        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" 
                                                data-bs-target="#rejectModal" 
                                                onclick="setRejectReport({{ $report->id }})" title="Reject">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <!-- Expandable Details Row -->
                            <tr class="collapse" id="details{{ $report->id }}">
                                <td colspan="9">
                                    <div class="p-3 bg-light">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <strong>General Notes:</strong>
                                                <p class="text-muted small">{{ $report->general_notes ?? 'N/A' }}</p>
                                                
                                                <strong>Safety Incidents:</strong>
                                                <p class="text-muted small">{{ $report->safety_incidents ?? 'None reported' }}</p>
                                            </div>
                                            <div class="col-md-6">
                                                <strong>Site Diary Remark:</strong>
                                                <p class="text-muted small">{{ $report->site_diary_remark ?? 'N/A' }}</p>
                                                
                                                <strong>Items Completed:</strong>
                                                <ul class="list-unstyled small text-muted">
                                                    @foreach($report->items as $item)
                                                    <li>• {{ $item->work_description ?? 'N/A' }} 
                                                        ({{ $item->qty_completed ?? 0 }} units, 
                                                        {{ $item->workers_count ?? 0 }} workers)
                                                    </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">
                                    <i class="fas fa-inbox fa-3x mb-3 opacity-50"></i>
                                    <p class="mb-0">No pending reports to approve</p>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </form>
        </div>
        @if($reports->hasPages())
        <div class="card-footer bg-light">
            {{ $reports->links() }}
        </div>
        @endif
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Reject Daily Report</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="rejectForm" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Rejection Reason <span class="text-danger">*</span></label>
                        <textarea name="rejection_reason" class="form-control" rows="4" required 
                                  placeholder="Please provide reason for rejection..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-times me-1"></i>Reject Report
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
document.getElementById('selectAll').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.report-checkbox');
    checkboxes.forEach(cb => cb.checked = this.checked);
    updateBulkActions();
});

document.querySelectorAll('.report-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', updateBulkActions);
});

function updateBulkActions() {
    const checked = document.querySelectorAll('.report-checkbox:checked');
    const bulkCard = document.getElementById('bulkActionsCard');
    
    if (checked.length > 0) {
        bulkCard.classList.remove('d-none');
        document.getElementById('selectedCount').textContent = checked.length;
        
        const ids = Array.from(checked).map(cb => cb.value);
        document.getElementById('bulkReportIds').value = JSON.stringify(ids);
    } else {
        bulkCard.classList.add('d-none');
    }
}
</script>
@endpush

@endsection
