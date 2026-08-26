@extends('layouts.app')
@section('title', 'Employee Letters & History Records')

@section('content')
<div class="container-fluid px-4 py-3">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-0 fw-bold" style="color:var(--brand-800)">
                <i class="fa-solid fa-envelope-open-text text-primary me-2"></i>Employee Letters &amp; History
            </h1>
            <p class="text-muted small mb-0">
                Official employee correspondence, appreciation letters, warnings, and disciplinary records
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('employee-letters.create') }}" class="btn btn-primary btn-sm shadow-sm">
                <i class="fa-solid fa-plus me-1"></i> Issue Official Letter
            </a>
            <a href="{{ route('employees.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-users me-1"></i> Employee Directory
            </a>
        </div>
    </div>

    {{-- KPI Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 h-100" style="border-left: 4px solid #0284c7 !important;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted fw-semibold mb-1" style="font-size:0.72rem; letter-spacing:.06em; text-transform:uppercase;">Total Letters Issued</p>
                            <h3 class="fw-bold text-dark mb-0">{{ number_format($kpi['total']) }}</h3>
                        </div>
                        <div class="p-2 rounded-3 bg-primary bg-opacity-10 text-primary">
                            <i class="fa-solid fa-folder-open fa-lg"></i>
                        </div>
                    </div>
                    <small class="text-muted mt-2 d-block" style="font-size:0.73rem;">Total official letters on record</small>
                </div>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 h-100" style="border-left: 4px solid #10b981 !important;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted fw-semibold mb-1" style="font-size:0.72rem; letter-spacing:.06em; text-transform:uppercase;">Thanks &amp; Recognition</p>
                            <h3 class="fw-bold text-success mb-0">{{ number_format($kpi['appreciation']) }}</h3>
                        </div>
                        <div class="p-2 rounded-3 bg-success bg-opacity-10 text-success">
                            <i class="fa-solid fa-award fa-lg"></i>
                        </div>
                    </div>
                    <small class="text-muted mt-2 d-block" style="font-size:0.73rem;">Appreciation &amp; promotions</small>
                </div>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 h-100" style="border-left: 4px solid #f59e0b !important;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted fw-semibold mb-1" style="font-size:0.72rem; letter-spacing:.06em; text-transform:uppercase;">Written Warnings</p>
                            <h3 class="fw-bold text-warning mb-0">{{ number_format($kpi['warnings']) }}</h3>
                        </div>
                        <div class="p-2 rounded-3 bg-warning bg-opacity-10 text-warning">
                            <i class="fa-solid fa-triangle-exclamation fa-lg"></i>
                        </div>
                    </div>
                    <small class="text-muted mt-2 d-block" style="font-size:0.73rem;">1st &amp; 2nd warning notices</small>
                </div>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 h-100" style="border-left: 4px solid #ef4444 !important;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted fw-semibold mb-1" style="font-size:0.72rem; letter-spacing:.06em; text-transform:uppercase;">Final Warnings &amp; Actions</p>
                            <h3 class="fw-bold text-danger mb-0">{{ number_format($kpi['final_warnings']) }}</h3>
                        </div>
                        <div class="p-2 rounded-3 bg-danger bg-opacity-10 text-danger">
                            <i class="fa-solid fa-circle-exclamation fa-lg"></i>
                        </div>
                    </div>
                    <small class="text-muted mt-2 d-block" style="font-size:0.73rem;">Final warnings &amp; disciplinary</small>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters Card --}}
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('employee-letters.index') }}" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-muted mb-1">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Subject, Ref #, Employee Name..." value="{{ request('search') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-muted mb-1">Letter Type</label>
                    <select name="letter_type" class="form-select form-select-sm">
                        <option value="">All Letter Types</option>
                        <option value="thanks_letter" {{ request('letter_type') == 'thanks_letter' ? 'selected' : '' }}>Thanks / Appreciation Letter</option>
                        <option value="appreciation" {{ request('letter_type') == 'appreciation' ? 'selected' : '' }}>Letter of Recognition</option>
                        <option value="power_of_attorney" {{ request('letter_type') == 'power_of_attorney' ? 'selected' : '' }}>Power of Attorney / Representation (ውክልና)</option>
                        <option value="first_warning" {{ request('letter_type') == 'first_warning' ? 'selected' : '' }}>First Written Warning</option>
                        <option value="second_warning" {{ request('letter_type') == 'second_warning' ? 'selected' : '' }}>Second Written Warning</option>
                        <option value="final_warning" {{ request('letter_type') == 'final_warning' ? 'selected' : '' }}>Final Warning Letter</option>
                        <option value="show_cause" {{ request('letter_type') == 'show_cause' ? 'selected' : '' }}>Show Cause / Query</option>
                        <option value="suspension" {{ request('letter_type') == 'suspension' ? 'selected' : '' }}>Suspension Letter</option>
                        <option value="promotion" {{ request('letter_type') == 'promotion' ? 'selected' : '' }}>Promotion Letter</option>
                        <option value="termination" {{ request('letter_type') == 'termination' ? 'selected' : '' }}>Termination Letter</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-muted mb-1">Employee</label>
                    <select name="employee_id" class="form-select form-select-sm select2">
                        <option value="">All Employees</option>
                        @foreach($employees as $emp)
                        <option value="{{ $emp->id }}" {{ request('employee_id') == $emp->id ? 'selected' : '' }}>
                            {{ $emp->full_name }} ({{ $emp->employee_code }})
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-muted mb-1">&nbsp;</label>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm px-3"><i class="fa-solid fa-filter me-1"></i> Filter</button>
                        <a href="{{ route('employee-letters.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Letters Table --}}
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-dark">
                <i class="fa-solid fa-list text-primary me-2"></i>Official Letter Records
            </h6>
            <span class="badge bg-light text-dark">{{ $letters->total() }} Records</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Ref #</th>
                        <th>Letter Type</th>
                        <th>Employee</th>
                        <th>Subject / Title</th>
                        <th>Issued Date</th>
                        <th>Issued By</th>
                        <th>Signed File</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($letters as $letter)
                    <tr>
                        <td class="ps-3">
                            <a href="{{ route('employee-letters.show', $letter) }}" class="fw-bold font-monospace text-primary text-decoration-none">
                                {{ $letter->reference_number ?: 'LTR-#'.$letter->id }}
                            </a>
                        </td>
                        <td>
                            <span class="badge {{ $letter->badge_class }} px-2 py-1">
                                <i class="{{ $letter->icon }} me-1"></i>{{ $letter->type_label }}
                            </span>
                        </td>
                        <td>
                            @if($letter->employee)
                            <a href="{{ route('employees.show', $letter->employee) }}" class="fw-semibold text-dark text-decoration-none d-block">
                                {{ $letter->employee->full_name }}
                            </a>
                            <small class="text-muted font-monospace">{{ $letter->employee->employee_code }} · {{ $letter->employee->role_title ?? $letter->employee->department }}</small>
                            @else
                            <span class="text-muted small">Employee Deleted</span>
                            @endif
                        </td>
                        <td>
                            <strong class="text-dark small d-block">{{ Str::limit($letter->title, 45) }}</strong>
                            <small class="text-muted">{{ Str::limit(strip_tags($letter->content), 55) }}</small>
                        </td>
                        <td>
                            <small class="text-dark fw-semibold">{{ optional($letter->issued_date)->format('d M Y') }}</small>
                        </td>
                        <td>
                            <small class="text-muted">{{ $letter->issuer->name ?? 'HR Admin' }}</small>
                        </td>
                        <td>
                            @if($letter->attachment_path)
                            <a href="{{ asset('storage/' . $letter->attachment_path) }}" target="_blank" class="btn btn-sm btn-outline-secondary" title="View Uploaded Signed Document">
                                <i class="fa-solid fa-paperclip text-primary"></i>
                            </a>
                            @else
                            <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td class="text-end pe-3">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('employee-letters.show', $letter) }}" class="btn btn-outline-primary" title="View Letter">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                                <a href="{{ route('employee-letters.print', $letter) }}" target="_blank" class="btn btn-outline-secondary" title="Print Letterhead">
                                    <i class="fa-solid fa-print"></i>
                                </a>
                                <a href="{{ route('employee-letters.edit', $letter) }}" class="btn btn-outline-warning" title="Edit">
                                    <i class="fa-solid fa-pen"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-envelope-open-text fa-3x mb-3 d-block opacity-25"></i>
                            <h6 class="fw-bold text-dark">No Official Employee Letters on Record</h6>
                            <p class="small text-muted mb-3">Issue appreciation letters, written warnings, or final warnings to track employee records.</p>
                            <a href="{{ route('employee-letters.create') }}" class="btn btn-primary btn-sm">
                                <i class="fa-solid fa-plus me-1"></i> Issue First Letter
                            </a>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($letters->hasPages())
        <div class="card-footer bg-white border-top py-2">
            {{ $letters->links() }}
        </div>
        @endif
    </div>

</div>
@endsection
