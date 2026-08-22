@extends('layouts.app')
@section('title', 'System Role Assignment')
@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fa-solid fa-user-tag me-2"></i>System Role Assignment</h1>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Action Required: Unassigned Users --}}
    <div class="row">
        <div class="col-lg-12 mb-4">
            <div class="card shadow" style="border-left: 4px solid #f6c23e;">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-warning">
                        <i class="fa-solid fa-circle-exclamation me-1"></i>
                        Action Required: Users Without a System Role
                        @if($unassigned->count() > 0)
                            <span class="badge bg-warning text-dark ms-2">{{ $unassigned->count() }}</span>
                        @endif
                    </h6>
                </div>
                <div class="card-body p-0">
                    @if($unassigned->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Employee</th>
                                        <th>Department / Role Title</th>
                                        <th>Email</th>
                                        <th>Registered</th>
                                        <th class="text-center">Assign Role</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($unassigned as $u)
                                    @php $isNew = (session('highlight_user_id') == $u->id); @endphp
                                    <tr id="user-row-{{ $u->id }}" class="{{ $isNew ? 'table-warning' : '' }}" style="{{ $isNew ? 'border-left: 4px solid #f6c23e;' : '' }}">
                                        <td>
                                            <div class="fw-bold">
                                                {{ $u->name }}
                                                @if($isNew)
                                                    <span class="badge bg-success ms-1">New</span>
                                                @endif
                                            </div>
                                            @if($u->employee)
                                                <div class="text-xs text-muted">
                                                    <i class="fa-solid fa-id-badge me-1"></i>{{ $u->employee->employee_code }}
                                                </div>
                                            @endif
                                        </td>
                                        <td>
                                            @if($u->employee)
                                                <div>{{ $u->employee->department ?? '—' }}</div>
                                                <div class="text-xs text-muted">{{ $u->employee->role_title ?? '' }}</div>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>{{ $u->email }}</td>
                                        <td>{{ $u->created_at->format('M d, Y') }}</td>
                                        <td class="text-center">
                                            <button class="btn btn-sm btn-warning fw-bold"
                                                data-bs-toggle="modal"
                                                data-bs-target="#assignModal{{ $u->id }}">
                                                <i class="fa-solid fa-user-tag me-1"></i> Assign Role
                                            </button>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="p-4 text-center text-muted">
                            <i class="fa-solid fa-check-circle fs-3 text-success mb-2"></i>
                            <p class="mb-0">All registered users have been assigned roles.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Role Management Card --}}
        <div class="col-lg-12 mb-4">
            <div class="card shadow border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fa-solid fa-tags me-2"></i>System Roles Management ({{ $roles->count() }})
                    </h6>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="collapse" data-bs-target="#createRoleCollapse">
                        <i class="fa-solid fa-plus me-1"></i> Create New Role
                    </button>
                </div>
                <div class="collapse p-3 bg-light border-bottom" id="createRoleCollapse">
                    <form action="{{ Route::has('admin.roles.store') ? route('admin.roles.store') : (Route::has('admin.role-assignment.store') ? route('admin.role-assignment.store') : url('/admin/roles')) }}" method="POST" class="row g-2 align-items-center">
                        @csrf
                        <div class="col-md-6">
                            <input type="text" name="name" class="form-control form-control-sm" placeholder="Enter new role name (e.g., Secretary, Legal, Audit)..." required>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-sm btn-success fw-bold px-3">
                                <i class="fa-solid fa-plus me-1"></i> Add Role
                            </button>
                        </div>
                    </form>
                </div>
                <div class="card-body p-3">
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($roles as $r)
                            <div class="badge bg-white text-dark border p-2 shadow-sm d-flex align-items-center gap-2">
                                <i class="fa-solid fa-user-tag text-primary"></i>
                                <span class="fw-bold">{{ ucfirst(str_replace('_', ' ', $r->name)) }}</span>
                                @if(!in_array($r->name, ['admin', 'global_admin', 'gm', 'secretary']))
                                    <form action="{{ Route::has('admin.roles.destroy') ? route('admin.roles.destroy', $r->id) : (Route::has('admin.role-assignment.destroy') ? route('admin.role-assignment.destroy', $r->id) : url('/admin/roles/' . $r->id)) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this role?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-link text-danger p-0 ms-1" style="font-size: 0.8rem;" title="Delete Role">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- All System Users --}}
        <div class="col-lg-12">
            <div class="card shadow">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">All System Users</h6>
                    <form action="{{ route('admin.role-assignment.index') }}" method="GET" class="d-flex" style="width: 320px;">
                        <input type="text" name="search" class="form-control form-control-sm me-2"
                            placeholder="Search by name or email..."
                            value="{{ request('search') }}">
                        <button class="btn btn-sm btn-primary" type="submit"><i class="fa-solid fa-search"></i></button>
                    </form>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Name</th>
                                    <th>Department / Role Title</th>
                                    <th>Email</th>
                                    <th>Current System Role</th>
                                    <th class="text-end">Manage</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($allUsers as $u)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $u->name }}</div>
                                        @if($u->employee)
                                            <div class="text-xs text-muted">
                                                <i class="fa-solid fa-id-badge me-1"></i>{{ $u->employee->employee_code }}
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        @if($u->employee)
                                            <div>{{ $u->employee->department ?? '—' }}</div>
                                            <div class="text-xs text-muted">{{ $u->employee->role_title ?? '' }}</div>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>{{ $u->email }}</td>
                                    <td>
                                        @if($u->roles->count() > 0)
                                            <span class="badge bg-primary">{{ ucfirst(str_replace('_', ' ', $u->roles->first()->name)) }}</span>
                                        @else
                                            <span class="badge bg-warning text-dark">No Role</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#assignModal{{ $u->id }}">
                                            <i class="fa-solid fa-edit"></i> Edit
                                        </button>
                                        @if($u->roles->count() > 0)
                                        <form action="{{ route('admin.role-assignment.remove', $u) }}" method="POST" class="d-inline-block"
                                            onsubmit="return confirm('Remove all roles from {{ $u->name }}? They will lose dashboard access.');">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-danger">
                                                <i class="fa-solid fa-times"></i>
                                            </button>
                                        </form>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($allUsers->hasPages())
                <div class="card-footer">
                    {{ $allUsers->links() }}
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Role Assignment Modals --}}
@foreach($unassigned->merge($allUsers)->unique('id') as $u)
<div class="modal fade text-start" id="assignModal{{ $u->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('admin.role-assignment.assign', $u) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fa-solid fa-user-tag me-2"></i>Assign Role — {{ $u->name }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @if($u->employee)
                    <div class="alert alert-light border mb-3">
                        <div class="row g-2 small">
                            <div class="col-6">
                                <span class="text-muted">Employee Code:</span><br>
                                <strong>{{ $u->employee->employee_code }}</strong>
                            </div>
                            <div class="col-6">
                                <span class="text-muted">Department:</span><br>
                                <strong>{{ $u->employee->department ?? '—' }}</strong>
                            </div>
                            <div class="col-6">
                                <span class="text-muted">Job Title:</span><br>
                                <strong>{{ $u->employee->role_title ?? '—' }}</strong>
                            </div>
                            <div class="col-6">
                                <span class="text-muted">Employment Type:</span><br>
                                <strong>{{ ucfirst($u->employee->employment_type ?? '—') }}</strong>
                            </div>
                        </div>
                    </div>
                    @endif
                    <p class="text-sm text-muted mb-3">
                        Selecting a role grants this user access to the corresponding dashboard and features in the system.
                    </p>
                    <div class="mb-3">
                        <label class="form-label fw-bold">System Role <span class="text-danger">*</span></label>
                        <select name="role" class="form-select" required>
                            <option value="">— Select a role —</option>
                            @foreach($roles as $role)
                                <option value="{{ $role->name }}" {{ $u->hasRole($role->name) ? 'selected' : '' }}>
                                    {{ ucfirst(str_replace('_', ' ', $role->name)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-save me-1"></i> Save Role
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach

@if(session('highlight_user_id'))
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Auto-open the modal for the newly added employee
        var modal = new bootstrap.Modal(document.getElementById('assignModal{{ session("highlight_user_id") }}'));
        modal.show();

        // Scroll to their row
        var row = document.getElementById('user-row-{{ session("highlight_user_id") }}');
        if (row) row.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
</script>
@endif

@endsection
