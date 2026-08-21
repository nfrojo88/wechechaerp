@extends('layouts.app')
@section('title', 'Departments')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark"><i class="fas fa-building text-primary me-2"></i>Departments</h1>
            <p class="text-muted small mb-0">Manage organizational departments, assigned department heads, and staffing.</p>
        </div>
        <a href="{{ route('departments.create') }}" class="btn btn-primary rounded-pill px-4 shadow-sm">
            <i class="fas fa-plus me-1"></i>Add Department
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm" role="alert">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-3">
        @forelse($departments as $dept)
        <div class="col-md-6 col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden">
                <div class="card-body p-4 d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <div class="p-2 rounded-3 bg-primary bg-opacity-10 text-primary">
                                <i class="fas fa-building fs-5"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold text-dark mb-0">{{ $dept->name }}</h5>
                                <span class="badge bg-light text-secondary border font-monospace">{{ $dept->code }}</span>
                            </div>
                        </div>
                        <span class="badge rounded-pill {{ $dept->is_active ? 'bg-success' : 'bg-secondary' }}">
                            {{ $dept->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>

                    <div class="mt-3 mb-3 text-muted small flex-grow-1">
                        @if($dept->head)
                            <div class="d-flex align-items-center gap-2 mb-2 text-dark fw-semibold">
                                <i class="fas fa-user-tie text-primary"></i>
                                <span>Head: {{ $dept->head->full_name ?? ($dept->head->first_name . ' ' . $dept->head->last_name) }}</span>
                            </div>
                        @else
                            <div class="d-flex align-items-center gap-2 mb-2 text-muted">
                                <i class="fas fa-user-slash"></i>
                                <span>No department head assigned</span>
                            </div>
                        @endif

                        <div class="d-flex align-items-center gap-2 mb-2">
                            <i class="fas fa-users text-info"></i>
                            <span>{{ $dept->employees->count() }} {{ Str::plural('employee', $dept->employees->count()) }}</span>
                        </div>

                        @if($dept->description)
                            <p class="mb-0 text-secondary small mt-2">{{ Str::limit($dept->description, 100) }}</p>
                        @endif
                    </div>

                    <div class="pt-3 border-top d-flex justify-content-between align-items-center mt-auto">
                        <span class="text-muted small">Updated {{ $dept->updated_at ? $dept->updated_at->diffForHumans() : 'recently' }}</span>
                        <a href="{{ route('departments.edit', $dept) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                            <i class="fas fa-edit me-1"></i>Edit
                        </a>
                    </div>
                </div>
            </div>
        </div>
        @empty
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4 text-center py-5">
                <div class="card-body">
                    <i class="fas fa-building text-muted mb-3" style="font-size: 3rem;"></i>
                    <h5 class="fw-bold text-dark">No Departments Found</h5>
                    <p class="text-muted">Start organizing your organization structure by adding departments.</p>
                    <a href="{{ route('departments.create') }}" class="btn btn-primary rounded-pill px-4">
                        <i class="fas fa-plus me-1"></i>Create First Department
                    </a>
                </div>
            </div>
        </div>
        @endforelse
    </div>
</div>
@endsection
