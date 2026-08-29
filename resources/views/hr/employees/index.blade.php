@extends('layouts.app')

@section('title', 'Employee Management')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0"><i class="fas fa-users me-2 text-primary"></i>Employee Management</h1>
        <p class="text-muted mt-1 mb-0">Manage all employees across projects and departments</p>
    </div>
    <div class="d-flex gap-2">
        @role('gm')
        <a href="{{ route('employees.pending-approval') }}" class="btn btn-warning position-relative text-dark fw-bold">
            <i class="fa-solid fa-user-clock me-1"></i> Pending GM Approvals
            @if(!empty($counts['pending']) && $counts['pending'] > 0)
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                {{ $counts['pending'] }}
            </span>
            @endif
        </a>
        @endrole
        @can('create', App\Models\Employee::class)
        <a href="{{ route('employees.create') }}" class="btn btn-primary shadow-sm">
            <i class="fa-solid fa-user-plus me-1"></i> Add New Employee
        </a>
        @endcan
    </div>
</div>

{{-- Rejection Attention Banner for HR --}}
@if(!empty($counts['rejected']) && $counts['rejected'] > 0)
<div class="alert alert-danger alert-dismissible fade show border-start border-4 border-danger shadow-sm mb-4" role="alert">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
        <div class="d-flex align-items-center">
            <div class="rounded-circle bg-danger bg-opacity-10 p-3 me-3">
                <i class="fa-solid fa-triangle-exclamation fa-2x text-danger"></i>
            </div>
            <div>
                <strong class="fs-6 d-block text-danger">{{ $counts['rejected'] }} Employee Registration(s) Returned by GM</strong>
                <span class="small text-muted">The General Manager has requested corrections with specific reasons. Please review, update the employee information, and resubmit.</span>
            </div>
        </div>
        <a href="{{ route('employees.index', ['approval_status' => 'rejected']) }}" class="btn btn-sm btn-danger fw-bold text-nowrap">
            <i class="fa-solid fa-wrench me-1"></i> View & Fix Rejected ({{ $counts['rejected'] }})
        </a>
    </div>
</div>
@endif

<!-- Filter Tabs -->
<div class="mb-3">
    <ul class="nav nav-pills gap-2 bg-light p-2 rounded-3 border">
        <li class="nav-item">
            <a class="nav-link {{ request('approval_status', 'all') === 'all' ? 'active bg-primary' : 'text-dark' }} py-2 px-3 fw-semibold" 
               href="{{ route('employees.index', array_merge(request()->except('approval_status', 'page'), ['approval_status' => 'all'])) }}">
                <i class="fa-solid fa-users me-1"></i> All Employees 
                <span class="badge {{ request('approval_status', 'all') === 'all' ? 'bg-light text-dark' : 'bg-secondary' }} ms-1">{{ $counts['all'] ?? $employees->total() }}</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request('approval_status') === 'approved' ? 'active bg-success' : 'text-dark' }} py-2 px-3 fw-semibold" 
               href="{{ route('employees.index', array_merge(request()->except('approval_status', 'page'), ['approval_status' => 'approved'])) }}">
                <i class="fa-solid fa-circle-check me-1"></i> Approved
                <span class="badge {{ request('approval_status') === 'approved' ? 'bg-light text-dark' : 'bg-success bg-opacity-25 text-dark' }} ms-1">{{ $counts['approved'] ?? 0 }}</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request('approval_status') === 'pending' ? 'active bg-warning text-dark' : 'text-dark' }} py-2 px-3 fw-semibold" 
               href="{{ route('employees.index', array_merge(request()->except('approval_status', 'page'), ['approval_status' => 'pending'])) }}">
                <i class="fa-solid fa-clock me-1"></i> Awaiting GM Approval
                <span class="badge {{ request('approval_status') === 'pending' ? 'bg-dark text-white' : 'bg-warning bg-opacity-50 text-dark' }} ms-1">{{ $counts['pending'] ?? 0 }}</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request('approval_status') === 'rejected' ? 'active bg-danger text-white' : 'text-dark' }} py-2 px-3 fw-semibold" 
               href="{{ route('employees.index', array_merge(request()->except('approval_status', 'page', 'probation_alert'), ['approval_status' => 'rejected'])) }}">
                <i class="fa-solid fa-rotate-left me-1"></i> Rejected by GM
                @if(!empty($counts['rejected']) && $counts['rejected'] > 0)
                <span class="badge bg-danger text-white ms-1">{{ $counts['rejected'] }}</span>
                @else
                <span class="badge bg-secondary ms-1">0</span>
                @endif
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request('probation_alert') == '1' || request('approval_status') === 'probation_alert' ? 'active bg-warning text-dark' : 'text-dark' }} py-2 px-3 fw-semibold" 
               href="{{ route('employees.index', array_merge(request()->except('approval_status', 'page'), ['probation_alert' => '1', 'approval_status' => 'probation_alert'])) }}">
                <i class="fa-solid fa-clock-rotate-left text-danger me-1"></i> Test Period Alert (Day 20–45)
                @if(!empty($counts['probation_alert']) && $counts['probation_alert'] > 0)
                    <span class="badge bg-danger text-white ms-1">{{ $counts['probation_alert'] }}</span>
                @else
                    <span class="badge bg-secondary ms-1">0</span>
                @endif
            </a>
        </li>
        <li class="nav-item ms-auto">
            <a class="nav-link text-danger py-2 px-3 fw-semibold" href="{{ route('employees.history') }}">
                <i class="fa-solid fa-user-clock me-1"></i> Employee History (Locked/Terminated)
                @if(!empty($counts['history']) && $counts['history'] > 0)
                    <span class="badge bg-secondary text-white ms-1">{{ $counts['history'] }}</span>
                @endif
            </a>
        </li>
    </ul>
</div>

<!-- Search & Filter Bar -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="{{ route('employees.index') }}" class="row g-2 align-items-center">
            <input type="hidden" name="approval_status" value="{{ request('approval_status', 'all') }}">
            <div class="col-md-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input type="text" name="search" class="form-control form-control-sm border-start-0" placeholder="Search by name, employee code, or role..." 
                           value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-md-4">
                <select name="department" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Departments</option>
                    @foreach($departments as $dept)
                    <option value="{{ $dept }}" @selected(request('department')==$dept)>{{ $dept }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary w-100 fw-semibold">
                    <i class="fas fa-filter me-1"></i>Apply Filter
                </button>
                @if(request()->hasAny(['search', 'department']) || request('approval_status', 'all') !== 'all')
                <a href="{{ route('employees.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                @endif
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-muted small text-uppercase fw-semibold">
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Role / Department</th>
                        <th>Project Site</th>
                        <th>Type</th>
                        <th>Basic Salary</th>
                        <th>Approval State</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($employees as $emp)
                    <tr class="{{ $emp->gm_approval_status === 'rejected' ? 'table-danger bg-opacity-10' : '' }}">
                        <td class="font-monospace small text-muted">{{ $emp->employee_code }}</td>
                        <td>
                            <div class="fw-bold text-dark">{{ $emp->full_name }}</div>
                            @if($emp->email)<small class="text-muted">{{ $emp->email }}</small>@endif
                        </td>
                        <td>
                            <div>{{ $emp->role_title ?? '—' }}</div>
                            <small class="text-muted">{{ $emp->department ?? '' }}</small>
                        </td>
                        <td>
                            @if($emp->project)
                            <a href="{{ route('projects.show', $emp->project) }}" class="text-decoration-none small">
                                {{ $emp->project->name }}
                            </a>
                            @else
                            <span class="text-muted small">HQ / Unassigned</span>
                            @endif
                        </td>
                        <td>
                            @php $typeColor = match($emp->employment_type){
                                'permanent' => 'success',
                                'contract'  => 'warning',
                                'daily'     => 'secondary',
                                default     => 'secondary'
                            }; @endphp
                            @if($emp->isProjectBased())
                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary-subtle" title="Until Project Completion">
                                    <i class="fa-solid fa-diagram-project me-1"></i>Project Contract
                                </span>
                            @else
                                <span class="badge bg-{{ $typeColor }}">{{ ucfirst($emp->employment_type) }}</span>
                            @endif
                            @if($emp->status === 'locked')
                                <span class="badge bg-danger ms-1" title="{{ $emp->lock_reason ?? 'Locked' }}"><i class="fa-solid fa-lock me-1"></i>Locked</span>
                            @endif
                        </td>

                        <td class="fw-semibold">{{ number_format($emp->basic_salary, 2) }} ETB</td>
                        <td>
                            @if($emp->is_approved_by_gm)
                                <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i>Approved</span>
                            @elseif($emp->gm_approval_status === 'rejected')
                                <div class="d-flex flex-column align-items-start gap-1">
                                    <span class="badge bg-danger"><i class="fa-solid fa-triangle-exclamation me-1"></i>Rejected by GM</span>
                                    <button type="button" class="btn btn-xs btn-outline-danger py-0 px-1 rounded" 
                                            onclick="showReason('{{ addslashes($emp->full_name) }}', '{{ addslashes($emp->gm_rejection_reason) }}', '{{ optional($emp->gm_rejected_at)->format('d M Y') }}')">
                                        <i class="fa-solid fa-eye me-1"></i>Reason
                                    </button>
                                </div>
                            @else
                                <span class="badge bg-warning text-dark"><i class="fa-solid fa-clock me-1"></i>Awaiting GM</span>
                            @endif
                        </td>
                        <td class="text-end pe-3">
                            <div class="btn-group" role="group">
                                <a href="{{ route('employees.show', $emp) }}" class="btn btn-sm btn-outline-primary" title="View Profile">
                                    <i class="fa-solid fa-eye me-1"></i>View
                                </a>
                                @can('update', $emp)
                                    @if($emp->gm_approval_status === 'rejected')
                                    <a href="{{ route('employees.edit', $emp) }}" class="btn btn-sm btn-danger fw-bold" title="Fix & Resubmit">
                                        <i class="fa-solid fa-wrench me-1"></i>Fix & Resubmit
                                    </a>
                                    @else
                                    <a href="{{ route('employees.edit', $emp) }}" class="btn btn-sm btn-outline-secondary" title="Edit Employee">
                                        <i class="fa-solid fa-pen-to-square me-1"></i>Edit
                                    </a>
                                    @endif
                                @endcan
                                @can('delete', $emp)
                                <form action="{{ route('employees.destroy', $emp) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete employee {{ addslashes($emp->full_name) }} ({{ $emp->employee_code }})?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Employee">
                                        <i class="fa-solid fa-trash me-1"></i>Delete
                                    </button>
                                </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-users fa-3x mb-3 opacity-50"></i>
                            <p class="mb-0">No employees found in this category.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($employees->hasPages())
    <div class="card-footer bg-white border-top py-3 d-flex justify-content-between align-items-center">
        <span class="text-muted small">Showing {{ $employees->firstItem() }} to {{ $employees->lastItem() }} of {{ $employees->total() }} employees</span>
        <div>{{ $employees->links('pagination::bootstrap-4') }}</div>
    </div>
    @endif
</div>

{{-- Reason View Modal for HR Officer --}}
<div class="modal fade" id="rejectionReasonModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fs-6"><i class="fa-solid fa-triangle-exclamation me-2"></i>GM Rejection / Return Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <small class="text-muted d-block">Employee Name:</small>
                    <strong class="fs-6 text-dark" id="reasonModalEmpName"></strong>
                </div>
                <div class="p-3 bg-light rounded border border-danger-subtle mb-3">
                    <small class="text-danger fw-bold d-block mb-1"><i class="fa-solid fa-comment-dots me-1"></i>Correction Instructions from GM:</small>
                    <p class="mb-0 text-dark" id="reasonModalText"></p>
                </div>
                <small class="text-muted d-block" id="reasonModalDate"></small>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function showReason(name, reason, date) {
    document.getElementById('reasonModalEmpName').textContent = name;
    document.getElementById('reasonModalText').textContent = reason || 'No specific reason entered.';
    document.getElementById('reasonModalDate').textContent = date ? 'Returned on: ' + date : '';
    new bootstrap.Modal(document.getElementById('rejectionReasonModal')).show();
}
</script>
@endsection
