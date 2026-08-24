@extends('layouts.app')

@section('title', 'Employee History (Terminated & Locked)')

@section('content')
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('employees.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <h1 class="h3 mb-0"><i class="fa-solid fa-user-clock text-danger me-2"></i>Employee History</h1>
        </div>
        <small class="text-muted">Archive of terminated, suspended, and locked employee accounts (including expired 45-day test periods)</small>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="{{ route('employees.index') }}" class="btn btn-sm btn-outline-primary">
            <i class="fa-solid fa-users me-1"></i> Active Employees
        </a>
        <a href="{{ route('employees.create') }}" class="btn btn-sm btn-primary">
            <i class="fa-solid fa-plus me-1"></i> Add Employee
        </a>
    </div>
</div>

{{-- Filters Card --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="{{ route('employees.history') }}" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Search by name, ID code, TIN, or phone..." value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-md-3">
                <select name="department" class="form-select">
                    <option value="">All Departments</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept }}" {{ request('department') == $dept ? 'selected' : '' }}>{{ $dept }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="employment_type" class="form-select">
                    <option value="">All Types</option>
                    <option value="permanent" {{ request('employment_type') == 'permanent' ? 'selected' : '' }}>Permanent</option>
                    <option value="contract" {{ request('employment_type') == 'contract' ? 'selected' : '' }}>Contract</option>
                    <option value="daily" {{ request('employment_type') == 'daily' ? 'selected' : '' }}>Daily Labor</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-filter me-1"></i>Filter</button>
                @if(request()->hasAny(['search', 'department', 'employment_type']))
                    <a href="{{ route('employees.history') }}" class="btn btn-outline-secondary"><i class="fa-solid fa-rotate-left"></i></a>
                @endif
            </div>
        </form>
    </div>
</div>

{{-- Employees Table Card --}}
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-3">Employee</th>
                    <th>Dept &amp; Role</th>
                    <th>Employment Type</th>
                    <th>TIN Number</th>
                    <th>Status &amp; Reason</th>
                    <th>Joined / Deadlines</th>
                    <th class="text-end pe-3">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($employees as $emp)
                <tr>
                    <td class="ps-3">
                        <div class="d-flex align-items-center gap-2">
                            <img src="{{ $emp->profile_picture_url }}" alt="{{ $emp->full_name }}" class="rounded-circle shadow-xs" style="width:38px;height:38px;object-fit:cover;">
                            <div>
                                <a href="{{ route('employees.show', $emp) }}" class="fw-bold text-dark text-decoration-none d-block">
                                    {{ $emp->full_name }}
                                </a>
                                <span class="badge bg-secondary font-monospace" style="font-size:0.75rem;">{{ $emp->employee_code }}</span>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="fw-semibold text-dark">{{ $emp->department ?? 'General' }}</div>
                        <small class="text-muted">{{ $emp->role_title ?? 'Employee' }}</small>
                    </td>
                    <td>
                        <span class="badge bg-light text-dark border text-capitalize">{{ $emp->employment_type }}</span>
                        @if($emp->contract_end_date)
                            <small class="d-block text-muted" style="font-size:0.72rem;">Upto: {{ $emp->contract_end_date->format('d M Y') }}</small>
                        @endif
                    </td>
                    <td>
                        @if($emp->tin_number)
                            <span class="font-monospace text-dark">{{ $emp->tin_number }}</span>
                        @else
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="fa-solid fa-xmark me-1"></i>Missing TIN</span>
                        @endif
                    </td>
                    <td>
                        <div class="mb-1">
                            @if($emp->status === 'terminated')
                                <span class="badge bg-danger"><i class="fa-solid fa-lock me-1"></i>Terminated / Locked</span>
                            @elseif($emp->status === 'suspended')
                                <span class="badge bg-warning text-dark"><i class="fa-solid fa-pause me-1"></i>Suspended</span>
                            @else
                                <span class="badge bg-secondary">{{ ucfirst($emp->status) }}</span>
                            @endif
                        </div>
                        @if($emp->lock_reason)
                            <small class="text-danger d-block fw-semibold" style="font-size:0.75rem; max-width: 220px; line-height: 1.2;">
                                <i class="fa-solid fa-circle-exclamation me-1"></i>{{ $emp->lock_reason }}
                            </small>
                        @endif
                    </td>
                    <td>
                        <small class="text-muted d-block">Joined: {{ optional($emp->date_of_joining)->format('d M Y') ?? 'N/A' }}</small>
                        @if($emp->probation_end_date)
                            <small class="text-muted d-block">45d Test: {{ $emp->probation_end_date->format('d M Y') }}</small>
                        @endif
                    </td>
                    <td class="text-end pe-3">
                        <div class="d-flex justify-content-end gap-1">
                            <a href="{{ route('employees.show', $emp) }}" class="btn btn-xs btn-outline-primary" title="View Profile">
                                <i class="fa-solid fa-eye"></i>
                            </a>
                            <button type="button" class="btn btn-xs btn-success fw-semibold" data-bs-toggle="modal" data-bs-target="#renewModal{{ $emp->id }}" title="Renew / Reactivate">
                                <i class="fa-solid fa-arrow-rotate-right me-1"></i>Renew
                            </button>
                            <a href="{{ route('employees.edit', $emp) }}" class="btn btn-xs btn-outline-secondary" title="Edit">
                                <i class="fa-solid fa-edit"></i>
                            </a>
                        </div>

                        {{-- Renew Modal --}}
                        <div class="modal fade text-start" id="renewModal{{ $emp->id }}" tabindex="-1" aria-labelledby="renewModalLabel{{ $emp->id }}" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content border-0 shadow">
                                    <form action="{{ route('employees.renew', $emp) }}" method="POST" enctype="multipart/form-data">
                                        @csrf
                                        <div class="modal-header bg-success text-white">
                                            <h5 class="modal-title fs-6" id="renewModalLabel{{ $emp->id }}">
                                                <i class="fa-solid fa-user-check me-2"></i>Renew / Activate Employee: {{ $emp->full_name }}
                                            </h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p class="small text-muted mb-3">
                                                Transition or renew this employee to Permanent or Contract, provide Guarantee Letter &amp; TIN information, and restore active account login status.
                                            </p>

                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Employment Type <span class="text-danger">*</span></label>
                                                <select name="employment_type" id="renew_emp_type_{{ $emp->id }}" class="form-select" onchange="toggleRenewContractDate({{ $emp->id }})" required>
                                                    <option value="permanent" {{ $emp->employment_type === 'permanent' ? 'selected' : '' }}>Permanent</option>
                                                    <option value="contract" {{ $emp->employment_type === 'contract' ? 'selected' : '' }}>Contract (Specify End Date)</option>
                                                    <option value="daily" {{ $emp->employment_type === 'daily' ? 'selected' : '' }}>Daily Labor</option>
                                                </select>
                                            </div>

                                            <div class="mb-3 {{ $emp->employment_type === 'contract' ? '' : 'd-none' }}" id="renew_contract_end_div_{{ $emp->id }}">
                                                <label class="form-label fw-bold">Contract End Date (Valid Upto) <span class="text-danger">*</span></label>
                                                <input type="date" name="contract_end_date" class="form-control" value="{{ optional($emp->contract_end_date)->format('Y-m-d') }}">
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label fw-bold">TIN Number <span class="text-danger">*</span></label>
                                                <input type="text" name="tin_number" class="form-control font-monospace" placeholder="Enter Tax Identification Number" value="{{ $emp->tin_number }}" required>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Guarantee Letter Document (PDF / Image)</label>
                                                @if($emp->guarantee_letter)
                                                    <div class="mb-2">
                                                        <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i>Already On File</span>
                                                        <a href="{{ $emp->guarantee_letter_url }}" target="_blank" class="small ms-2">View Document →</a>
                                                    </div>
                                                @endif
                                                <input type="file" name="guarantee_letter" class="form-control" accept="application/pdf,image/jpeg,image/png,image/jpg">
                                                <small class="text-muted">Upload signed guarantee letter to complete renewal compliance.</small>
                                            </div>

                                            <div class="form-check form-switch mb-2">
                                                <input class="form-check-input" type="checkbox" name="probation_completed" value="1" id="probation_completed_{{ $emp->id }}" checked>
                                                <label class="form-check-label fw-semibold" for="probation_completed_{{ $emp->id }}">
                                                    Mark 45-Day Test Period / Probation Completed
                                                </label>
                                            </div>
                                        </div>
                                        <div class="modal-footer bg-light">
                                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-sm btn-success fw-bold">
                                                <i class="fa-solid fa-check me-1"></i> Save Renewal &amp; Activate
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center py-5 text-muted">
                        <i class="fa-solid fa-user-check fa-3x mb-3 text-secondary opacity-50"></i>
                        <h6>No Terminated or Locked Employees Found</h6>
                        <p class="small mb-0">All employee records are active and compliant.</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($employees->hasPages())
        <div class="card-footer bg-white p-3">
            {{ $employees->links() }}
        </div>
    @endif
</div>

@push('scripts')
<script>
function toggleRenewContractDate(empId) {
    const typeSelect = document.getElementById('renew_emp_type_' + empId);
    const dateDiv = document.getElementById('renew_contract_end_div_' + empId);
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
