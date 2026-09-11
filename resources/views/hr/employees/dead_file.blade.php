@extends('layouts.app')

@section('title', 'Dead File Section — Inactive & Archived Employees')

@section('content')
{{-- Page Header --}}
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('employees.index') }}" class="btn btn-sm btn-outline-secondary shadow-xs">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <h1 class="h3 mb-0 fw-bold">
                <i class="fa-solid fa-box-archive text-danger me-2"></i>Dead File Section
            </h1>
            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger-subtle px-2 py-1 fs-7">የሞቱ ፋይሎች</span>
        </div>
        <p class="text-muted mt-1 mb-0 small">
            Permanent compliance archive of separated employees. Records are never deleted to preserve payroll, pension, guarantee, and legal audit trails.
        </p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="{{ route('employees.index') }}" class="btn btn-sm btn-outline-primary shadow-xs">
            <i class="fa-solid fa-users me-1"></i> Active Employees
        </a>
        @can('create', App\Models\Employee::class)
        <a href="{{ route('employees.create') }}" class="btn btn-sm btn-primary shadow-xs">
            <i class="fa-solid fa-plus me-1"></i> Add Employee
        </a>
        @endcan
    </div>
</div>

{{-- KPI Summary Cards --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm h-100 bg-white border-start border-4 border-dark">
            <div class="card-body p-3">
                <div class="text-muted small text-uppercase fw-bold">Total in Dead File</div>
                <div class="fs-4 fw-bold text-dark mt-1">{{ number_format($counts['total'] ?? 0) }}</div>
                <div class="small text-muted mt-1"><i class="fa-solid fa-box-archive text-dark me-1"></i>Archived records</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm h-100 bg-white border-start border-4 border-warning">
            <div class="card-body p-3">
                <div class="text-muted small text-uppercase fw-bold">Resignations</div>
                <div class="fs-4 fw-bold text-warning mt-1">{{ number_format($counts['resigned'] ?? 0) }}</div>
                <div class="small text-muted mt-1"><i class="fa-solid fa-user-xmark text-warning me-1"></i>መልቀቂያ የወሰዱ</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm h-100 bg-white border-start border-4 border-info">
            <div class="card-body p-3">
                <div class="text-muted small text-uppercase fw-bold">Contract Ended</div>
                <div class="fs-4 fw-bold text-info mt-1">{{ number_format($counts['contract_ended'] ?? 0) }}</div>
                <div class="small text-muted mt-1"><i class="fa-solid fa-calendar-xmark text-info me-1"></i>የውል ጊዜያቸው ያለቀ</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm h-100 bg-white border-start border-4 border-danger">
            <div class="card-body p-3">
                <div class="text-muted small text-uppercase fw-bold">Terminated / Dismissed</div>
                <div class="fs-4 fw-bold text-danger mt-1">{{ number_format($counts['terminated'] ?? 0) }}</div>
                <div class="small text-muted mt-1"><i class="fa-solid fa-ban text-danger me-1"></i>ከሥራ የተሰናበቱ</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm h-100 bg-white border-start border-4 border-primary">
            <div class="card-body p-3">
                <div class="text-muted small text-uppercase fw-bold">Project Done</div>
                <div class="fs-4 fw-bold text-primary mt-1">{{ number_format($counts['project_done'] ?? 0) }}</div>
                <div class="small text-muted mt-1"><i class="fa-solid fa-diagram-project text-primary me-1"></i>ፕሮጀክት ያለቀላቸው</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm h-100 bg-white border-start border-4 border-success">
            <div class="card-body p-3">
                <div class="text-muted small text-uppercase fw-bold">Re-Hire Eligible</div>
                <div class="fs-4 fw-bold text-success mt-1"><i class="fa-solid fa-rotate-left me-1"></i>Ready</div>
                <div class="small text-muted mt-1">One-click restore</div>
            </div>
        </div>
    </div>
</div>

{{-- Search & Filter Card --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="{{ route('employees.dead-file') }}" class="row g-2 align-items-center">
            <div class="col-md-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search by name, ID code, phone, TIN, or reason..." value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-md-2">
                <select name="department" class="form-select form-select-sm">
                    <option value="">All Departments</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept }}" {{ request('department') == $dept ? 'selected' : '' }}>{{ $dept }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select name="reason" class="form-select form-select-sm">
                    <option value="">All Departure Reasons</option>
                    <option value="Resignation" {{ request('reason') == 'Resignation' ? 'selected' : '' }}>Resignation (የመልቀቂያ ፈቃድ)</option>
                    <option value="Contract Expired" {{ request('reason') == 'Contract Expired' ? 'selected' : '' }}>Contract Expired (የውል ጊዜ ማብቂያ)</option>
                    <option value="Termination" {{ request('reason') == 'Termination' ? 'selected' : '' }}>Termination / Dismissal (ከሥራ መሰናበት)</option>
                    <option value="Project Completed" {{ request('reason') == 'Project Completed' ? 'selected' : '' }}>Project Completed (የፕሮጀክት ማጠናቀቂያ)</option>
                    <option value="Retirement" {{ request('reason') == 'Retirement' ? 'selected' : '' }}>Retirement (የጡረታ መውጫ)</option>
                    <option value="Deceased" {{ request('reason') == 'Deceased' ? 'selected' : '' }}>Deceased (ህልፈተ ህይወት)</option>
                    <option value="Other" {{ request('reason') == 'Other' ? 'selected' : '' }}>Other Reasons</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary w-100 fw-semibold">
                    <i class="fa-solid fa-filter me-1"></i> Filter
                </button>
                @if(request()->hasAny(['search', 'department', 'reason', 'date_from', 'date_to']))
                    <a href="{{ route('employees.dead-file') }}" class="btn btn-sm btn-outline-secondary" title="Reset Filters">
                        <i class="fa-solid fa-rotate-left"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>
</div>

{{-- Dead File Records Table --}}
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold text-dark">
            <i class="fa-solid fa-folder-closed text-danger me-2"></i>Archived Employee File Records ({{ $employees->total() }})
        </h6>
        <span class="text-muted small">Showing {{ $employees->firstItem() ?? 0 }} to {{ $employees->lastItem() ?? 0 }} of {{ $employees->total() }}</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-muted small text-uppercase fw-semibold">
                    <tr>
                        <th class="ps-3">Employee</th>
                        <th>Role / Dept</th>
                        <th>Project Site</th>
                        <th>Departure Reason</th>
                        <th>Date Sent to Dead File</th>
                        <th>Handover &amp; File Notes</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($employees as $emp)
                    <tr>
                        <td class="ps-3">
                            <div class="d-flex align-items-center gap-2">
                                <div class="position-relative">
                                    @if($emp->profile_picture)
                                        <img src="{{ route('employees.profile-picture', $emp) }}" alt="{{ $emp->full_name }}" class="rounded-circle shadow-xs" style="width:40px;height:40px;object-fit:cover;">
                                    @else
                                        <div class="rounded-circle bg-secondary bg-opacity-25 d-flex align-items-center justify-content-center text-dark fw-bold" style="width:40px;height:40px;font-size:0.9rem;">
                                            {{ strtoupper(substr($emp->full_name, 0, 2)) }}
                                        </div>
                                    @endif
                                    <span class="position-absolute bottom-0 end-0 bg-danger border border-white rounded-circle" style="width:10px;height:10px;" title="In Dead File"></span>
                                </div>
                                <div>
                                    <a href="{{ route('employees.show', $emp) }}" class="fw-bold text-dark text-decoration-none d-block">
                                        {{ $emp->full_name }}
                                    </a>
                                    <span class="badge bg-secondary font-monospace" style="font-size:0.75rem;">{{ $emp->employee_code }}</span>
                                    @if($emp->phone)<small class="text-muted ms-1"><i class="fa-solid fa-phone me-1"></i>{{ $emp->phone }}</small>@endif
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="fw-semibold text-dark">{{ $emp->role_title ?? '—' }}</div>
                            <small class="text-muted">{{ $emp->department ?? 'General' }}</small>
                        </td>
                        <td>
                            @if($emp->project)
                                <a href="{{ route('projects.show', $emp->project) }}" class="text-decoration-none small fw-semibold">
                                    {{ $emp->project->name }}
                                </a>
                            @else
                                <span class="text-muted small">HQ / Unassigned</span>
                            @endif
                            <div class="small text-muted">Type: {{ ucfirst($emp->employment_type) }}</div>
                        </td>
                        <td>
                            @php
                                $reasonLower = strtolower($emp->dead_file_reason ?? '');
                                $badgeClass = match(true) {
                                    str_contains($reasonLower, 'resig') || str_contains($reasonLower, 'መልቀቅ') => 'bg-warning text-dark border-warning-subtle',
                                    str_contains($reasonLower, 'contract') || str_contains($reasonLower, 'ውል') => 'bg-info bg-opacity-10 text-info border-info-subtle',
                                    str_contains($reasonLower, 'project') || str_contains($reasonLower, 'ፕሮጀክት') => 'bg-primary bg-opacity-10 text-primary border-primary-subtle',
                                    str_contains($reasonLower, 'deceased') || str_contains($reasonLower, 'ህልፈት') => 'bg-dark text-white border-dark',
                                    str_contains($reasonLower, 'terminat') || str_contains($reasonLower, 'dismiss') || str_contains($reasonLower, 'ስንብት') => 'bg-danger bg-opacity-10 text-danger border-danger-subtle',
                                    default => 'bg-secondary bg-opacity-10 text-secondary border-secondary-subtle',
                                };
                            @endphp
                            <span class="badge {{ $badgeClass }} border py-1 px-2">
                                <i class="fa-solid fa-tag me-1"></i>{{ $emp->dead_file_reason ?? 'Archived to Dead File' }}
                            </span>
                        </td>
                        <td>
                            <div class="fw-semibold text-dark">
                                {{ $emp->dead_file_at ? $emp->dead_file_at->format('d M Y') : optional($emp->updated_at)->format('d M Y') }}
                            </div>
                            @if($emp->deadFileArchivedBy)
                                <small class="text-muted d-block" style="font-size:0.75rem;">
                                    By: {{ $emp->deadFileArchivedBy->name }}
                                </small>
                            @endif
                        </td>
                        <td>
                            @if($emp->dead_file_notes)
                                <div class="text-truncate text-muted small" style="max-width: 250px;" title="{{ $emp->dead_file_notes }}">
                                    <i class="fa-solid fa-note-sticky text-secondary me-1"></i>{{ $emp->dead_file_notes }}
                                </div>
                            @else
                                <span class="text-muted small fst-italic">— No archive notes —</span>
                            @endif
                            @if($emp->tin_number)
                                <small class="text-muted d-block font-monospace">TIN: {{ $emp->tin_number }}</small>
                            @endif
                        </td>
                        <td class="text-end pe-3">
                            <div class="btn-group" role="group">
                                <a href="{{ route('employees.show', $emp) }}" class="btn btn-sm btn-outline-primary" title="View Full Employee File">
                                    <i class="fa-solid fa-eye me-1"></i>View
                                </a>
                                @can('update', $emp)
                                <button type="button" class="btn btn-sm btn-success fw-semibold" data-bs-toggle="modal" data-bs-target="#restoreModal{{ $emp->id }}" title="Restore / Rehire Employee">
                                    <i class="fa-solid fa-rotate-left me-1"></i>Restore
                                </button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-box-open fa-3x mb-3 text-secondary opacity-50"></i>
                            <h5 class="fw-bold">No Dead File Records Found</h5>
                            <p class="small text-muted mb-0">When employees leave, use "Send to Dead File" in the employee table to archive them here safely.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($employees->hasPages())
    <div class="card-footer bg-white border-top py-3 d-flex justify-content-between align-items-center">
        <span class="text-muted small">Showing {{ $employees->firstItem() }} to {{ $employees->lastItem() }} of {{ $employees->total() }} archived records</span>
        <div>{{ $employees->links('pagination::bootstrap-4') }}</div>
    </div>
    @endif
</div>

{{-- Restore / Rehire Modals --}}
@foreach($employees as $emp)
<div class="modal fade" id="restoreModal{{ $emp->id }}" tabindex="-1" aria-labelledby="restoreModalLabel{{ $emp->id }}" aria-hidden="true" data-bs-backdrop="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
            <form action="{{ route('employees.restore-from-dead-file', $emp) }}" method="POST">
                @csrf
                <div class="modal-header bg-success text-white py-3 px-4">
                    <h5 class="modal-title fs-6 fw-bold mb-0" id="restoreModalLabel{{ $emp->id }}">
                        <i class="fa-solid fa-user-check me-2"></i>Restore / Rehire: {{ $emp->full_name }}
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 bg-white">
                    <div class="alert alert-info py-2 px-3 small border-0 mb-3">
                        <i class="fa-solid fa-circle-info me-1"></i>
                        Restoring will move <strong>{{ $emp->full_name }} ({{ $emp->employee_code }})</strong> from the Dead File back to the <strong>Active Employees</strong> roster.
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark small">New Employment Type <span class="text-danger">*</span></label>
                        <select name="employment_type" id="restore_emp_type_{{ $emp->id }}" class="form-select" onchange="toggleRestoreContractDate({{ $emp->id }})" required>
                            <option value="permanent" {{ $emp->employment_type === 'permanent' ? 'selected' : '' }}>Permanent</option>
                            <option value="contract" {{ $emp->employment_type === 'contract' ? 'selected' : '' }}>Contract (Specify End Date)</option>
                            <option value="daily" {{ $emp->employment_type === 'daily' ? 'selected' : '' }}>Daily Labor</option>
                        </select>
                    </div>

                    <div class="mb-3 {{ $emp->employment_type === 'contract' ? '' : 'd-none' }}" id="restore_contract_end_div_{{ $emp->id }}">
                        <label class="form-label fw-bold text-dark small">Contract End Date <span class="text-danger">*</span></label>
                        <input type="date" name="contract_end_date" class="form-control" value="{{ optional($emp->contract_end_date)->format('Y-m-d') }}">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark small">Rehire / Restoration Remarks</label>
                        <textarea name="restore_notes" class="form-control" rows="2" placeholder="Enter rehire terms, return reason, or approval notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 px-4 border-top">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-success fw-bold px-3">
                        <i class="fa-solid fa-rotate-left me-1"></i> Confirm Restore to Active
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach

@push('scripts')
<script>
function toggleRestoreContractDate(empId) {
    const typeSelect = document.getElementById('restore_emp_type_' + empId);
    const dateDiv = document.getElementById('restore_contract_end_div_' + empId);
    if (!typeSelect || !dateDiv) return;
    if (typeSelect.value === 'contract') {
        dateDiv.classList.remove('d-none');
    } else {
        dateDiv.classList.add('d-none');
    }
}
</script>
@endpush
@endsection
