@extends('layouts.app')
@section('title', 'Subcontractor Agreements')
@section('content')

<div class="container-fluid px-4 py-3">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-1" style="color:var(--brand-800)">
                <i class="fa-solid fa-file-signature text-primary me-2"></i>Subcontractor Agreements &amp; Contracts
            </h4>
            <p class="text-muted small mb-0">Manage subcontractor agreements, scope of work, advance terms, and uploaded signed contract documents.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('subcon-agreements.create') }}" class="btn btn-primary btn-sm shadow-sm">
                <i class="fa-solid fa-plus me-1"></i>New Agreement &amp; Upload
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-4 shadow-sm" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show mb-4 shadow-sm" role="alert">
            <i class="fa-solid fa-circle-xmark me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- KPI Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3 h-100" style="background: linear-gradient(135deg, #ffffff 0%, #eff6ff 100%);">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small fw-semibold text-uppercase">Total Agreements</div>
                            <div class="fs-3 fw-bold text-primary mt-1">{{ number_format($statusCounts['all'] ?? 0) }}</div>
                            <small class="text-muted">All registered subcon agreements</small>
                        </div>
                        <div class="p-3 rounded-3 bg-primary bg-opacity-10 text-primary">
                            <i class="fa-solid fa-file-contract fa-xl"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3 h-100" style="background: linear-gradient(135deg, #ffffff 0%, #f0fdf4 100%);">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small fw-semibold text-uppercase">Active Contracts</div>
                            <div class="fs-3 fw-bold text-success mt-1">{{ number_format($statusCounts['active'] ?? 0) }}</div>
                            <small class="text-muted">Ongoing site works</small>
                        </div>
                        <div class="p-3 rounded-3 bg-success bg-opacity-10 text-success">
                            <i class="fa-solid fa-handshake-simple fa-xl"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3 h-100" style="background: linear-gradient(135deg, #ffffff 0%, #fffbeb 100%);">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small fw-semibold text-uppercase">Pending Approvals</div>
                            <div class="fs-3 fw-bold text-warning mt-1">{{ number_format(($statusCounts['draft'] ?? 0) + ($statusCounts['pending'] ?? 0)) }}</div>
                            <small class="text-muted">Draft &amp; awaiting HR review</small>
                        </div>
                        <div class="p-3 rounded-3 bg-warning bg-opacity-10 text-warning">
                            <i class="fa-solid fa-hourglass-half fa-xl"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3 h-100" style="background: linear-gradient(135deg, #ffffff 0%, #faf5ff 100%);">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small fw-semibold text-uppercase">Completed</div>
                            <div class="fs-3 fw-bold text-purple mt-1" style="color:#7c3aed;">{{ number_format($statusCounts['completed'] ?? 0) }}</div>
                            <small class="text-muted">Finalized agreements</small>
                        </div>
                        <div class="p-3 rounded-3 bg-opacity-10" style="background:rgba(124, 58, 237, 0.1); color:#7c3aed;">
                            <i class="fa-solid fa-circle-check fa-xl"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Bar & Search -->
    <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body p-3">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 border-bottom pb-3 mb-3">
                <ul class="nav nav-pills gap-1">
                    <li class="nav-item">
                        <a class="nav-link py-1.5 px-3 small {{ empty($status) || $status === 'all' ? 'active' : '' }}" 
                           href="{{ route('subcon-agreements.index', array_merge(request()->except('status', 'page'), ['status' => 'all'])) }}">
                            All <span class="badge bg-secondary ms-1">{{ $statusCounts['all'] ?? 0 }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link py-1.5 px-3 small {{ ($status ?? '') === 'draft' ? 'active' : '' }}" 
                           href="{{ route('subcon-agreements.index', array_merge(request()->except('status', 'page'), ['status' => 'draft'])) }}">
                            Draft <span class="badge bg-secondary ms-1">{{ $statusCounts['draft'] ?? 0 }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link py-1.5 px-3 small {{ ($status ?? '') === 'approved' ? 'active' : '' }}" 
                           href="{{ route('subcon-agreements.index', array_merge(request()->except('status', 'page'), ['status' => 'approved'])) }}">
                            Approved <span class="badge bg-success ms-1">{{ $statusCounts['approved'] ?? 0 }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link py-1.5 px-3 small {{ ($status ?? '') === 'active' ? 'active' : '' }}" 
                           href="{{ route('subcon-agreements.index', array_merge(request()->except('status', 'page'), ['status' => 'active'])) }}">
                            Active <span class="badge bg-info text-dark ms-1">{{ $statusCounts['active'] ?? 0 }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link py-1.5 px-3 small {{ ($status ?? '') === 'completed' ? 'active' : '' }}" 
                           href="{{ route('subcon-agreements.index', array_merge(request()->except('status', 'page'), ['status' => 'completed'])) }}">
                            Completed <span class="badge bg-secondary ms-1">{{ $statusCounts['completed'] ?? 0 }}</span>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Search Form -->
            <form method="GET" action="{{ route('subcon-agreements.index') }}" class="row g-2 align-items-center">
                @if(!empty($status))
                    <input type="hidden" name="status" value="{{ $status }}">
                @endif
                <div class="col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light"><i class="fa-solid fa-magnifying-glass"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Search agreement #, subcontractor, work..." value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-md-4">
                    <select name="project_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">-- All Projects --</option>
                        @foreach($projects as $prj)
                        <option value="{{ $prj->id }}" @selected(request('project_id') == $prj->id)>
                            {{ $prj->project_name ?? $prj->name }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary px-3">Filter</button>
                    @if(request('search') || request('project_id') || (request('status') && request('status') !== 'all'))
                        <a href="{{ route('subcon-agreements.index') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Agreements Table Card -->
    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3" style="width:130px;">Agreement #</th>
                            <th>Project</th>
                            <th>Subcontractor</th>
                            <th>Duration</th>
                            <th class="text-end">Contract Value</th>
                            <th class="text-center">Uploaded Document</th>
                            <th class="text-center">Status</th>
                            <th class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($agreements as $agreement)
                        <tr>
                            <td class="ps-3">
                                <a href="{{ route('subcon-agreements.show', $agreement) }}" class="fw-bold text-decoration-none text-primary">
                                    {{ $agreement->agreement_no }}
                                </a>
                            </td>
                            <td>
                                <strong class="d-block text-dark small">{{ $agreement->project->project_name ?? $agreement->project->name ?? 'N/A' }}</strong>
                                <span class="text-muted" style="font-size:0.75rem;">{{ Str::limit($agreement->work_description ?? $agreement->scope_of_work ?? 'No description', 40) }}</span>
                            </td>
                            <td>
                                <span class="fw-semibold text-dark">{{ $agreement->subcontractor_display_name }}</span>
                                @if($agreement->subcontractor_contact)
                                    <small class="text-muted d-block" style="font-size:0.75rem;"><i class="fa-solid fa-phone me-1"></i>{{ $agreement->subcontractor_contact }}</small>
                                @endif
                            </td>
                            <td>
                                <small class="text-muted">
                                    {{ optional($agreement->start_date)->format('M d, Y') ?? 'N/A' }}
                                    @if($agreement->end_date)
                                        <br><span class="text-secondary">to {{ $agreement->end_date->format('M d, Y') }}</span>
                                    @endif
                                </small>
                            </td>
                            <td class="text-end">
                                <strong class="text-dark">{{ number_format($agreement->effective_total_amount, 2) }} ETB</strong>
                                @if($agreement->retention_percent > 0)
                                    <small class="text-muted d-block" style="font-size:0.72rem;">Ret: {{ $agreement->retention_percent }}%</small>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($agreement->agreement_file)
                                    <a href="{{ route('subcon-agreements.download-file', $agreement) }}" class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2.5 py-1.5 text-decoration-none" title="Download / View Document">
                                        <i class="fa-solid fa-paperclip me-1"></i>View File
                                    </a>
                                @else
                                    <button type="button" class="badge bg-light text-muted border px-2 py-1 btn btn-sm p-0" 
                                            onclick="openUploadModal('{{ $agreement->id }}', '{{ $agreement->agreement_no }}')" title="Upload Agreement File">
                                        <i class="fa-solid fa-cloud-arrow-up me-1 text-primary"></i>Upload
                                    </button>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="badge bg-{{ $agreement->status_badge }}">
                                    {{ ucfirst(str_replace('_', ' ', $agreement->status)) }}
                                </span>
                            </td>
                            <td class="text-end pe-3">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('subcon-agreements.show', $agreement) }}" class="btn btn-outline-primary" title="View Agreement Details">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                    
                                    <button type="button" class="btn btn-outline-secondary" title="Upload / Update Agreement File" 
                                            onclick="openUploadModal('{{ $agreement->id }}', '{{ $agreement->agreement_no }}')">
                                        <i class="fa-solid fa-upload"></i>
                                    </button>

                                    @if($agreement->status === 'draft')
                                    <form action="{{ route('subcon-agreements.approve', $agreement) }}" method="POST" class="d-inline"
                                          onsubmit="return confirm('Approve agreement {{ $agreement->agreement_no }}?')">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-success" title="Approve Agreement">
                                            <i class="fa-solid fa-check"></i>
                                        </button>
                                    </form>
                                    @endif

                                    @if($agreement->status === 'approved')
                                    <form action="{{ route('subcon-agreements.activate', $agreement) }}" method="POST" class="d-inline"
                                          onsubmit="return confirm('Activate agreement {{ $agreement->agreement_no }} for site operations?')">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-info" title="Activate Agreement">
                                            <i class="fa-solid fa-play"></i>
                                        </button>
                                    </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-file-contract fa-3x mb-3 opacity-25"></i>
                                <h6 class="fw-bold">No Subcontractor Agreements Found</h6>
                                <p class="small mb-3">Create a new agreement or upload contract documents to start tracking.</p>
                                <a href="{{ route('subcon-agreements.create') }}" class="btn btn-sm btn-primary">
                                    <i class="fa-solid fa-plus me-1"></i>Create First Agreement
                                </a>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($agreements->hasPages())
        <div class="card-footer bg-white border-top py-3">
            {{ $agreements->links() }}
        </div>
        @endif
    </div>
</div>

<!-- Upload Agreement Document Modal -->
<div class="modal fade" id="uploadAgreementModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form id="uploadAgreementForm" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold text-dark">
                        <i class="fa-solid fa-cloud-arrow-up text-primary me-2"></i>Upload Agreement File
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-muted small mb-3">
                        Upload or replace the signed subcontractor agreement document for <strong id="modalAgreementNo" class="text-dark">#</strong>.
                    </p>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Select Document File <span class="text-danger">*</span></label>
                        <input type="file" name="agreement_file" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp" required>
                        <small class="text-muted">Accepted: PDF, DOCX, DOC, JPG, PNG (Max 20MB)</small>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-upload me-1"></i> Upload File
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function openUploadModal(id, agreementNo) {
        document.getElementById('modalAgreementNo').textContent = agreementNo;
        document.getElementById('uploadAgreementForm').action = `/subcon-agreements/${id}/upload-file`;
        const modal = new bootstrap.Modal(document.getElementById('uploadAgreementModal'));
        modal.show();
    }
</script>
@endpush
@endsection
