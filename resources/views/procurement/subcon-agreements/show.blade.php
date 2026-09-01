@extends('layouts.app')
@section('title', 'Agreement Details - ' . ($subconAgreement->agreement_no ?? 'Agreement'))

@section('content')
<div class="container-fluid px-4 py-3">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <h4 class="fw-bold mb-0" style="color:var(--brand-800)">
                    <i class="fa-solid fa-file-signature text-primary me-2"></i>Agreement {{ $subconAgreement->agreement_no }}
                </h4>
                <span class="badge bg-{{ $subconAgreement->status_badge }} px-3 py-2 fs-6 rounded-pill">
                    {{ ucfirst(str_replace('_', ' ', $subconAgreement->status)) }}
                </span>
            </div>
            <p class="text-muted small mb-0 mt-1">
                Project: <strong>{{ $subconAgreement->project->project_name ?? $subconAgreement->project->name ?? 'N/A' }}</strong> &bull; 
                Subcontractor: <strong>{{ $subconAgreement->subcontractor_display_name }}</strong>
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('subcon-agreements.index') }}" class="btn btn-outline-secondary btn-sm shadow-sm">
                <i class="fa-solid fa-arrow-left me-1"></i>Back to Agreements
            </a>

            <button type="button" class="btn btn-outline-primary btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#uploadDocModal">
                <i class="fa-solid fa-cloud-arrow-up me-1"></i>{{ $subconAgreement->agreement_file ? 'Replace Document' : 'Upload Agreement File' }}
            </button>

            @if($subconAgreement->status === 'draft')
                <form action="{{ route('subcon-agreements.approve', $subconAgreement) }}" method="POST" class="d-inline"
                      onsubmit="return confirm('Are you sure you want to approve this subcontractor agreement?')">
                    @csrf
                    <button type="submit" class="btn btn-success btn-sm shadow-sm">
                        <i class="fa-solid fa-check me-1"></i>Approve Agreement
                    </button>
                </form>

                <button type="button" class="btn btn-outline-danger btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#rejectAgreementModal">
                    <i class="fa-solid fa-xmark me-1"></i>Reject
                </button>
            @endif

            @if($subconAgreement->status === 'approved')
                <form action="{{ route('subcon-agreements.activate', $subconAgreement) }}" method="POST" class="d-inline"
                      onsubmit="return confirm('Activate this agreement for site operations and IPC certifications?')">
                    @csrf
                    <button type="submit" class="btn btn-info btn-sm text-dark shadow-sm fw-bold">
                        <i class="fa-solid fa-play me-1"></i>Activate Contract
                    </button>
                </form>
            @endif
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

    @if($subconAgreement->status === 'rejected' && $subconAgreement->rejection_reason)
        <div class="alert alert-danger border-start border-4 border-danger shadow-sm mb-4">
            <div class="d-flex align-items-center">
                <i class="fa-solid fa-circle-exclamation fs-4 me-3 text-danger"></i>
                <div>
                    <strong class="d-block">Agreement Rejected</strong>
                    <span class="small">{{ $subconAgreement->rejection_reason }}</span>
                </div>
            </div>
        </div>
    @endif

    <div class="row g-4">
        <!-- Left Column: Key Details & Work Scope -->
        <div class="col-lg-7">
            <!-- Agreement Overview Card -->
            <div class="card shadow-sm border-0 rounded-3 mb-4">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center">
                    <i class="fa-solid fa-circle-info text-primary me-2"></i>
                    <h6 class="fw-bold mb-0 text-dark">Agreement Overview &amp; Parties</h6>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="text-muted small fw-semibold d-block text-uppercase">Project</label>
                            <span class="fs-6 fw-bold text-dark">{{ $subconAgreement->project->project_name ?? $subconAgreement->project->name ?? 'N/A' }}</span>
                            @if($subconAgreement->project?->location)
                                <small class="text-muted d-block"><i class="fa-solid fa-location-dot me-1"></i>{{ $subconAgreement->project->location }}</small>
                            @endif
                        </div>

                        <div class="col-sm-6">
                            <label class="text-muted small fw-semibold d-block text-uppercase">Subcontractor</label>
                            <span class="fs-6 fw-bold text-dark">{{ $subconAgreement->subcontractor_display_name }}</span>
                            @if($subconAgreement->subcontractor_contact)
                                <small class="text-muted d-block"><i class="fa-solid fa-phone me-1"></i>{{ $subconAgreement->subcontractor_contact }}</small>
                            @endif
                        </div>

                        <div class="col-sm-6">
                            <label class="text-muted small fw-semibold d-block text-uppercase">Start Date</label>
                            <span class="fs-6 fw-semibold text-dark">{{ optional($subconAgreement->start_date)->format('d M Y') ?? 'Not Set' }}</span>
                        </div>

                        <div class="col-sm-6">
                            <label class="text-muted small fw-semibold d-block text-uppercase">End / Completion Date</label>
                            <span class="fs-6 fw-semibold text-dark">{{ optional($subconAgreement->end_date)->format('d M Y') ?? 'Not Set' }}</span>
                        </div>

                        <div class="col-12">
                            <label class="text-muted small fw-semibold d-block text-uppercase">Work Description &amp; Scope</label>
                            <div class="p-3 bg-light rounded-3 border text-dark">
                                {{ $subconAgreement->work_description ?? $subconAgreement->scope_of_work ?? 'No description provided.' }}
                            </div>
                        </div>

                        @if($subconAgreement->terms_conditions)
                        <div class="col-12">
                            <label class="text-muted small fw-semibold d-block text-uppercase">Payment Milestones &amp; Terms</label>
                            <div class="p-3 bg-light rounded-3 border text-dark small">
                                {{ $subconAgreement->terms_conditions }}
                            </div>
                        </div>
                        @endif

                        <div class="col-sm-6">
                            <label class="text-muted small fw-semibold d-block text-uppercase">Created By</label>
                            <span class="small text-dark">{{ $subconAgreement->createdBy->name ?? 'System' }} &bull; {{ $subconAgreement->created_at->format('d M Y H:i') }}</span>
                        </div>

                        @if($subconAgreement->approvedBy)
                        <div class="col-sm-6">
                            <label class="text-muted small fw-semibold d-block text-uppercase">Approved By</label>
                            <span class="small text-success fw-semibold">{{ $subconAgreement->approvedBy->name }} &bull; {{ optional($subconAgreement->approved_at)->format('d M Y') }}</span>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- BOQ / Line Items Breakdown -->
            @if($subconAgreement->items->isNotEmpty())
            <div class="card shadow-sm border-0 rounded-3 mb-4">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="fa-solid fa-list-check text-info me-2"></i>Task &amp; BOQ Breakdown
                    </h6>
                    <span class="badge bg-secondary">{{ $subconAgreement->items->count() }} items</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:40px;">#</th>
                                    <th>Task Description</th>
                                    <th class="text-center">Quantity</th>
                                    <th class="text-end">Unit Rate</th>
                                    <th class="text-end">Total Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($subconAgreement->items as $idx => $item)
                                <tr>
                                    <td class="text-muted small">{{ $idx + 1 }}</td>
                                    <td><strong>{{ $item->task_description }}</strong></td>
                                    <td class="text-center">{{ number_format($item->quantity, 2) }} <span class="badge bg-light text-dark border">{{ $item->unit }}</span></td>
                                    <td class="text-end">{{ number_format($item->unit_rate, 2) }} ETB</td>
                                    <td class="text-end fw-bold text-dark">{{ number_format($item->total_amount, 2) }} ETB</td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <th colspan="4" class="text-end">Total BOQ Sum:</th>
                                    <th class="text-end text-primary fw-bold">{{ number_format($subconAgreement->items->sum('total_amount'), 2) }} ETB</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            <!-- IPCs & Payment History -->
            <div class="card shadow-sm border-0 rounded-3 mb-4">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="fa-solid fa-money-check-dollar text-success me-2"></i>Interim Payment Certificates (IPCs)
                    </h6>
                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">{{ $subconAgreement->ipcs->count() }} IPCs</span>
                </div>
                <div class="card-body p-0">
                    @if($subconAgreement->ipcs->isNotEmpty())
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>IPC #</th>
                                    <th>Period</th>
                                    <th class="text-end">Certified Amount</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($subconAgreement->ipcs as $ipc)
                                <tr>
                                    <td><strong>{{ $ipc->ipc_no }}</strong></td>
                                    <td class="small">{{ optional($ipc->period_from)->format('d M') }} - {{ optional($ipc->period_to)->format('d M Y') }}</td>
                                    <td class="text-end fw-bold">{{ number_format($ipc->net_payable ?? $ipc->current_work_value ?? 0, 2) }} ETB</td>
                                    <td class="text-center"><span class="badge bg-info">{{ ucfirst($ipc->status ?? 'Active') }}</span></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <div class="text-center py-4 text-muted">
                        <p class="small mb-0">No IPC certificates generated yet for this agreement.</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Right Column: Document Preview & Financial Metrics -->
        <div class="col-lg-5">
            <!-- Signed Agreement Document Section -->
            <div class="card shadow-sm border-0 rounded-3 mb-4">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <i class="fa-solid fa-file-contract text-success me-2"></i>
                        <h6 class="fw-bold mb-0 text-dark">Uploaded Signed Agreement</h6>
                    </div>
                    @if($subconAgreement->agreement_file)
                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Document Attached</span>
                    @else
                        <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25">No File</span>
                    @endif
                </div>
                <div class="card-body p-4">
                    @if($subconAgreement->agreement_file)
                        <div class="p-3 bg-light rounded-3 border mb-3">
                            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                                <div class="d-flex align-items-center">
                                    @if($subconAgreement->is_pdf)
                                        <i class="fa-solid fa-file-pdf text-danger fa-2x me-3"></i>
                                    @elseif($subconAgreement->is_image)
                                        <i class="fa-solid fa-file-image text-info fa-2x me-3"></i>
                                    @else
                                        <i class="fa-solid fa-file-word text-primary fa-2x me-3"></i>
                                    @endif
                                    <div>
                                        <strong class="d-block text-dark small">{{ basename($subconAgreement->agreement_file) }}</strong>
                                        <span class="text-muted small">Signed Subcontractor Contract</span>
                                    </div>
                                </div>
                                <div class="d-flex gap-1">
                                    <a href="{{ route('subcon-agreements.download-file', $subconAgreement) }}" class="btn btn-sm btn-primary shadow-sm" download>
                                        <i class="fa-solid fa-download me-1"></i>Download
                                    </a>
                                    <a href="{{ $subconAgreement->agreement_file_url }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                    </a>
                                </div>
                            </div>

                            <!-- PDF or Image Inline Viewer -->
                            @if($subconAgreement->is_pdf)
                                <div class="ratio ratio-4x3 border rounded bg-white mt-2">
                                    <iframe src="{{ $subconAgreement->agreement_file_url }}" allowfullscreen></iframe>
                                </div>
                            @elseif($subconAgreement->is_image)
                                <div class="text-center mt-2">
                                    <a href="{{ $subconAgreement->agreement_file_url }}" target="_blank">
                                        <img src="{{ $subconAgreement->agreement_file_url }}" alt="Agreement Document" class="img-fluid rounded border shadow-sm" style="max-height:300px;">
                                    </a>
                                </div>
                            @endif
                        </div>

                        <button type="button" class="btn btn-outline-secondary btn-sm w-100" data-bs-toggle="modal" data-bs-target="#uploadDocModal">
                            <i class="fa-solid fa-cloud-arrow-up me-1"></i>Replace / Upload Newer Scan
                        </button>
                    @else
                        <div class="text-center py-4 border border-2 border-dashed rounded-3 bg-light p-4">
                            <i class="fa-solid fa-file-arrow-up text-secondary fa-3x mb-3 opacity-50"></i>
                            <h6 class="fw-bold text-dark mb-1">No Agreement Document Attached</h6>
                            <p class="text-muted small mb-3">Upload the signed contract, stamp scan, or digital document for complete compliance records.</p>
                            <button type="button" class="btn btn-primary btn-sm px-4" data-bs-toggle="modal" data-bs-target="#uploadDocModal">
                                <i class="fa-solid fa-upload me-1"></i>Upload Agreement File
                            </button>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Financial Summary Card -->
            <div class="card shadow-sm border-0 rounded-3 mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="fa-solid fa-calculator text-primary me-2"></i>Financial &amp; Retention Summary
                    </h6>
                </div>
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted">Total Contract Value:</span>
                        <strong class="fs-5 text-dark">{{ number_format($subconAgreement->effective_total_amount, 2) }} ETB</strong>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted">Retention Rate:</span>
                        <span class="badge bg-secondary">{{ $subconAgreement->retention_percent ?? 10 }}%</span>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted">Retention Guarantee Amount:</span>
                        <strong class="text-warning">{{ number_format($subconAgreement->retention_amount > 0 ? $subconAgreement->retention_amount : ($subconAgreement->effective_total_amount * (($subconAgreement->retention_percent ?? 10)/100)), 2) }} ETB</strong>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted">Paid to Date:</span>
                        <strong class="text-info">{{ number_format($subconAgreement->paid_to_date ?? 0, 2) }} ETB</strong>
                    </div>

                    <hr class="my-3">

                    @php
                        $retAmt = $subconAgreement->retention_amount > 0 ? $subconAgreement->retention_amount : ($subconAgreement->effective_total_amount * (($subconAgreement->retention_percent ?? 10)/100));
                        $netRemaining = $subconAgreement->effective_total_amount - ($subconAgreement->paid_to_date ?? 0);
                    @endphp
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-bold text-dark">Net Payable Balance:</span>
                        <strong class="fs-5 text-success">{{ number_format($netRemaining, 2) }} ETB</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Upload / Replace Agreement Document Modal -->
<div class="modal fade" id="uploadDocModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="{{ route('subcon-agreements.upload-file', $subconAgreement) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold text-dark">
                        <i class="fa-solid fa-cloud-arrow-up text-primary me-2"></i>Upload Agreement File
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-muted small mb-3">
                        Upload or replace the signed agreement document for <strong>{{ $subconAgreement->agreement_no }}</strong> ({{ $subconAgreement->subcontractor_display_name }}).
                    </p>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Select Document File <span class="text-danger">*</span></label>
                        <input type="file" name="agreement_file" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp" required>
                        <small class="text-muted">Accepted formats: PDF, DOCX, DOC, JPG, JPEG, PNG, WEBP (Max 20MB)</small>
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

<!-- Reject Agreement Modal -->
<div class="modal fade" id="rejectAgreementModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="{{ route('subcon-agreements.reject', $subconAgreement) }}" method="POST">
                @csrf
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fw-bold">
                        <i class="fa-solid fa-xmark me-2"></i>Reject Agreement
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Rejection Reason <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="3" placeholder="Explain why this agreement is rejected or needs revision..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fa-solid fa-ban me-1"></i> Confirm Rejection
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
