@extends('layouts.app')
@section('title', 'Receipts & Delivery Verification')

@section('content')
<div class="container-fluid py-3">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h1 class="h3 mb-1 text-dark fw-bold">
                <i class="fa-solid fa-receipt text-warning me-2"></i>Receipts & Delivery Verification
            </h1>
            <p class="text-muted small mb-0">Finance verification of vendor purchase receipts, store delivery slips (GRN / Model 19), and credit invoices.</p>
        </div>
        <div class="d-flex gap-2">
            @canany(['purchases.receive', 'purchases.*'])
                <a href="{{ route('delivery-receipts.create') }}" class="btn btn-outline-primary rounded-pill px-3 shadow-sm fw-semibold">
                    <i class="fas fa-plus me-1"></i> New Store Intake Slip
                </a>
            @endcanany
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm border-0 d-flex align-items-center mb-4">
            <i class="fas fa-check-circle fa-lg me-2"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($inquiredReceiptsCount > 0)
        <div class="alert alert-warning border-0 shadow-sm rounded-4 d-flex flex-wrap justify-content-between align-items-center p-3 mb-4 border-start border-4 border-warning">
            <div class="d-flex align-items-center gap-3 mb-2 mb-md-0">
                <div class="rounded-circle bg-warning text-dark p-3 d-flex align-items-center justify-content-center shadow-sm" style="width: 48px; height: 48px;">
                    <i class="fa-solid fa-bell-concierge fa-xl"></i>
                </div>
                <div>
                    <div class="fw-bold text-dark fs-6">
                        Auditor Inquiries: {{ $inquiredReceiptsCount }} Receipt(s) Requested (የተጠየቁ ደረሰኞች)
                    </div>
                    <div class="text-muted small">
                        The auditor asked for official invoices/receipts for payments assigned to you or paid from your assigned account.
                    </div>
                </div>
            </div>
            <a href="{{ request()->fullUrlWithQuery(['tab' => 'inquired_receipts']) }}" class="btn btn-warning text-dark fw-bold rounded-pill px-4 shadow-sm">
                <i class="fas fa-upload me-1"></i> View & Upload Requested Receipts ({{ $inquiredReceiptsCount }})
            </a>
        </div>
    @endif

    <!-- Stat Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="rounded-4 bg-warning bg-opacity-10 text-warning p-3 me-3 d-flex align-items-center justify-content-center" style="width: 54px; height: 54px;">
                        <i class="fa-solid fa-hourglass-half fa-xl"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Pending Verification</div>
                        <div class="h4 mb-0 fw-bold text-dark">{{ $pendingPrReceiptsCount }}</div>
                        <small class="text-warning fw-semibold">Action required by Finance</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="rounded-4 bg-success bg-opacity-10 text-success p-3 me-3 d-flex align-items-center justify-content-center" style="width: 54px; height: 54px;">
                        <i class="fa-solid fa-circle-check fa-xl"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Verified Receipts</div>
                        <div class="h4 mb-0 fw-bold text-dark">{{ $verifiedPrReceiptsCount }}</div>
                        <small class="text-success fw-semibold">Approved vendor receipts</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="rounded-4 bg-primary bg-opacity-10 text-primary p-3 me-3 d-flex align-items-center justify-content-center" style="width: 54px; height: 54px;">
                        <i class="fa-solid fa-file-invoice-dollar fa-xl"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Total PR Receipts</div>
                        <div class="h4 mb-0 fw-bold text-dark">{{ $totalPrReceiptsCount }}</div>
                        <small class="text-muted">Procurement receipts uploaded</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="rounded-4 bg-info bg-opacity-10 text-info p-3 me-3 d-flex align-items-center justify-content-center" style="width: 54px; height: 54px;">
                        <i class="fa-solid fa-boxes-packing fa-xl"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Store Delivery Vouchers</div>
                        <div class="h4 mb-0 fw-bold text-dark">{{ $totalDeliveryReceiptsCount }}</div>
                        <small class="text-muted">Warehouse Model 19 / GRNs</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter & Tab Controls -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <!-- Tabs -->
                <ul class="nav nav-pills gap-2" id="receiptTabs">
                    <li class="nav-item">
                        <a class="nav-link rounded-pill px-3 py-2 fw-semibold {{ $activeTab === 'inquired_receipts' ? 'active bg-warning text-dark shadow-sm' : 'text-secondary bg-light' }}" 
                           href="{{ request()->fullUrlWithQuery(['tab' => 'inquired_receipts']) }}">
                            <i class="fa-solid fa-file-circle-question me-1 {{ $activeTab === 'inquired_receipts' ? 'text-dark' : 'text-warning' }}"></i> Auditor Inquiries (የተጠየቁ ደረሰኞች)
                            @if($inquiredReceiptsCount > 0)
                                <span class="badge bg-danger text-white rounded-pill ms-1">{{ $inquiredReceiptsCount }}</span>
                            @else
                                <span class="badge bg-secondary rounded-pill ms-1">0</span>
                            @endif
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link rounded-pill px-3 py-2 fw-semibold {{ $activeTab === 'pr_receipts' ? 'active bg-primary text-white shadow-sm' : 'text-secondary bg-light' }}" 
                           href="{{ request()->fullUrlWithQuery(['tab' => 'pr_receipts']) }}">
                            <i class="fa-solid fa-file-invoice me-1"></i> Vendor Purchase Receipts
                            @if($pendingPrReceiptsCount > 0)
                                <span class="badge {{ $activeTab === 'pr_receipts' ? 'bg-white text-primary' : 'bg-warning text-dark' }} rounded-pill ms-1">{{ $pendingPrReceiptsCount }} Pending</span>
                            @else
                                <span class="badge {{ $activeTab === 'pr_receipts' ? 'bg-white text-primary' : 'bg-secondary' }} rounded-pill ms-1">{{ $procurementReceipts->total() }}</span>
                            @endif
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link rounded-pill px-3 py-2 fw-semibold {{ $activeTab === 'delivery_receipts' ? 'active bg-primary text-white shadow-sm' : 'text-secondary bg-light' }}" 
                           href="{{ request()->fullUrlWithQuery(['tab' => 'delivery_receipts']) }}">
                            <i class="fa-solid fa-truck-ramp-box me-1"></i> Warehouse Intake & GRNs
                            <span class="badge {{ $activeTab === 'delivery_receipts' ? 'bg-white text-primary' : 'bg-secondary' }} rounded-pill ms-1">{{ $deliveryReceipts->total() }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link rounded-pill px-3 py-2 fw-semibold {{ $activeTab === 'credit_receipts' ? 'active bg-primary text-white shadow-sm' : 'text-secondary bg-light' }}" 
                           href="{{ request()->fullUrlWithQuery(['tab' => 'credit_receipts']) }}">
                            <i class="fa-solid fa-credit-card me-1"></i> Credit Invoices (COA 5110)
                            <span class="badge {{ $activeTab === 'credit_receipts' ? 'bg-white text-primary' : 'bg-secondary' }} rounded-pill ms-1">{{ $creditReceipts->total() }}</span>
                        </a>
                    </li>
                </ul>

                <!-- Filter Form -->
                <form method="GET" action="{{ route('delivery-receipts.index') }}" class="d-flex align-items-center gap-2 flex-grow-1 flex-md-grow-0">
                    <input type="hidden" name="tab" value="{{ $activeTab }}">
                    
                    @if($activeTab === 'pr_receipts')
                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width: 140px;">
                        <option value="all" {{ request('status') === 'all' ? 'selected' : '' }}>All Statuses</option>
                        <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending Verification</option>
                        <option value="verified" {{ request('status') === 'verified' ? 'selected' : '' }}>Verified</option>
                        <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                    </select>
                    @endif

                    <div class="input-group input-group-sm" style="min-width: 200px;">
                        <input type="text" name="search" class="form-control" placeholder="Search reference, PR, supplier..." value="{{ request('search') }}">
                        <button class="btn btn-outline-secondary" type="submit"><i class="fas fa-search"></i></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- TAB CONTENT 1: Vendor Purchase Receipts -->
    @if($activeTab === 'pr_receipts')
    <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-2 d-flex justify-content-between align-items-center">
            <div>
                <h5 class="fw-bold text-dark mb-0">
                    <i class="fas fa-file-invoice text-primary me-2"></i>Vendor Purchase Receipts (Purchase Requests)
                </h5>
                <p class="text-muted small mb-0">Uploaded by Procurement after payment. Finance staff must verify receipt authenticity & amount.</p>
            </div>
            <span class="badge bg-light text-secondary border rounded-pill px-3 py-2">
                {{ $procurementReceipts->total() }} Receipts
            </span>
        </div>

        <div class="card-body p-0 mt-2">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-muted small text-uppercase">
                        <tr>
                            <th class="px-4 py-3">Receipt & PR #</th>
                            <th class="py-3">Project & Vendor</th>
                            <th class="py-3">Amount & Funding Account</th>
                            <th class="py-3">Uploaded By / Date</th>
                            <th class="py-3">Receipt Document</th>
                            <th class="py-3 text-center">Finance Status</th>
                            <th class="px-4 py-3 text-end">Verification Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($procurementReceipts as $rec)
                            @php
                                $pr = $rec->purchaseRequest;
                                $fileUrl = \App\Services\FileUploadService::url($rec->file_path);
                                $isPdf = strtolower(pathinfo($rec->file_path, PATHINFO_EXTENSION)) === 'pdf';
                                $amount = $pr?->payment?->amount ?? $pr?->direct_buy_amount ?? 0;
                            @endphp
                            <tr>
                                <!-- PR & Reference -->
                                <td class="px-4">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="badge bg-primary-subtle text-primary p-2 rounded-3">
                                            <i class="fas fa-receipt fa-lg"></i>
                                        </div>
                                        <div>
                                            <a href="{{ $pr ? route('purchase-requests.show', $pr->id) : '#' }}" class="fw-bold text-primary font-monospace text-decoration-none">
                                                PR #{{ $pr?->pr_no ?? $rec->purchase_request_id }}
                                            </a>
                                            <div class="small text-muted text-truncate" style="max-width: 180px;">
                                                {{ $pr?->title ?? 'Material Purchase' }}
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Project & Supplier -->
                                <td>
                                    <div class="fw-semibold text-dark">{{ $pr?->project?->name ?? 'General' }}</div>
                                    <small class="text-muted">
                                        <i class="fas fa-store me-1"></i>{{ $pr?->supplier?->name ?? ($pr?->proformaInvoices()->where('gm_selected', true)->first()?->supplier_name ?? 'Vendor') }}
                                    </small>
                                </td>

                                <!-- Amount & Funding COA -->
                                <td>
                                    <div class="fw-bold text-success fs-6">ETB {{ number_format($amount, 2) }}</div>
                                    <small class="text-muted">
                                        <i class="fas fa-wallet me-1"></i>{{ $pr?->payment?->coaAccount?->name ?? 'COA Assigned' }}
                                    </small>
                                </td>

                                <!-- Uploaded By -->
                                <td>
                                    <div class="fw-semibold text-dark">{{ $rec->uploadedBy?->name ?? 'Procurement' }}</div>
                                    <small class="text-muted">{{ $rec->created_at->format('M d, Y h:i A') }}</small>
                                </td>

                                <!-- Document Preview / Download -->
                                <td>
                                    @if($fileUrl)
                                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 shadow-sm" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#previewModal{{ $rec->id }}">
                                            <i class="fas {{ $isPdf ? 'fa-file-pdf text-danger' : 'fa-image text-primary' }} me-1"></i>
                                            View Receipt
                                        </button>
                                    @else
                                        <span class="text-muted small">No file attached</span>
                                    @endif
                                </td>

                                <!-- Status -->
                                <td class="text-center">
                                    @if($rec->verification_status === 'verified')
                                        <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill">
                                            <i class="fas fa-check-circle me-1"></i> Verified
                                        </span>
                                        @if($rec->verifiedBy)
                                            <div class="small text-muted mt-1" style="font-size: 0.72rem;">
                                                By {{ $rec->verifiedBy->name }} ({{ $rec->verified_at?->format('M d') }})
                                            </div>
                                        @endif
                                    @elseif($rec->verification_status === 'receipt_requested')
                                        <span class="badge bg-warning text-dark border border-warning px-3 py-2 rounded-pill shadow-sm">
                                            <i class="fas fa-paper-plane me-1"></i> Receipt Inquired
                                        </span>
                                        @if($rec->verification_notes)
                                            <div class="small text-warning-emphasis mt-1 text-truncate" style="max-width: 160px;" title="{{ $rec->verification_notes }}">
                                                💬 {{ $rec->verification_notes }}
                                            </div>
                                        @endif
                                    @elseif($rec->verification_status === 'rejected')
                                        <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-2 rounded-pill">
                                            <i class="fas fa-times-circle me-1"></i> Rejected
                                        </span>
                                        @if($rec->verification_notes)
                                            <div class="small text-danger mt-1 text-truncate" style="max-width: 150px;" title="{{ $rec->verification_notes }}">
                                                {{ $rec->verification_notes }}
                                            </div>
                                        @endif
                                    @else
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning px-3 py-2 rounded-pill">
                                            <i class="fas fa-clock me-1"></i> Pending Verification
                                        </span>
                                    @endif
                                </td>

                                <!-- Actions -->
                                <td class="px-4 text-end">
                                    <div class="d-flex justify-content-end align-items-center gap-1">
                                        @if($rec->verification_status === 'receipt_requested')
                                            <button type="button" class="btn btn-sm btn-dark rounded-pill px-3 fw-semibold shadow-sm"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#uploadInquiredModal_pr_{{ $pr?->id ?? $rec->purchase_request_id }}">
                                                <i class="fas fa-upload me-1"></i> Add Receipt
                                            </button>
                                        @elseif($rec->verification_status !== 'verified')
                                            <button type="button" class="btn btn-sm btn-success rounded-pill px-3 fw-semibold shadow-sm"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#verifyModal{{ $rec->id }}">
                                                <i class="fas fa-check-double me-1"></i> Verify
                                            </button>
                                        @endif

                                        @if($pr)
                                            <a href="{{ route('purchase-requests.show', $pr->id) }}" class="btn btn-sm btn-outline-secondary rounded-pill px-2 shadow-sm" title="View Full Purchase Request">
                                                <i class="fas fa-arrow-up-right-from-square"></i>
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="fas fa-receipt fa-3x mb-3 text-secondary opacity-50"></i>
                                    <h6>No vendor purchase receipts found</h6>
                                    <p class="small text-muted mb-0">Receipts uploaded by the procurement team will appear here for finance verification.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($procurementReceipts->hasPages())
            <div class="card-footer bg-white border-0 py-3 px-4">
                {{ $procurementReceipts->links() }}
            </div>
        @endif
    </div>

    <!-- TAB CONTENT 2: Warehouse Intake & GRNs -->
    @elseif($activeTab === 'delivery_receipts')
    <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-2 d-flex justify-content-between align-items-center">
            <div>
                <h5 class="fw-bold text-dark mb-0">
                    <i class="fas fa-truck-ramp-box text-primary me-2"></i>Warehouse Intake & Receiving Slips (Model 19 / GRN)
                </h5>
                <p class="text-muted small mb-0">Physical warehouse receipt vouchers and goods received notes recorded by Store Managers.</p>
            </div>
            <span class="badge bg-light text-secondary border rounded-pill px-3 py-2">
                {{ $deliveryReceipts->total() }} Delivery Slips
            </span>
        </div>

        <div class="card-body p-0 mt-2">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-muted small text-uppercase">
                        <tr>
                            <th class="px-4 py-3">Receiving Slip # (DR #)</th>
                            <th class="py-3">PO Reference</th>
                            <th class="py-3">Supplier</th>
                            <th class="py-3">Store / Site</th>
                            <th class="py-3">Received By / Date</th>
                            <th class="py-3 text-center">Status</th>
                            <th class="px-4 py-3 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($deliveryReceipts as $dr)
                            <tr>
                                <td class="px-4">
                                    <span class="fw-bold text-primary font-monospace">{{ $dr->dr_no }}</span>
                                    @if($dr->challan_no)
                                        <div class="small text-muted">Slip/Challan: {{ $dr->challan_no }}</div>
                                    @endif
                                </td>
                                <td>
                                    @if($dr->purchaseOrder)
                                        <span class="badge bg-light text-dark border">{{ $dr->purchaseOrder->po_no }}</span>
                                    @else
                                        <span class="text-muted small">Direct Delivery</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $dr->purchaseOrder->supplier->name ?? 'N/A' }}</div>
                                    @if($dr->vehicle_no)
                                        <small class="text-muted"><i class="fas fa-truck me-1"></i>{{ $dr->vehicle_no }}</small>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-primary-subtle text-primary border">{{ $dr->store->name ?? 'General Store' }}</span>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $dr->receivedBy->name ?? 'Store Keeper' }}</div>
                                    <small class="text-muted">{{ $dr->received_date ? $dr->received_date->format('M d, Y') : '-' }}</small>
                                </td>
                                <td class="text-center">
                                    @if($dr->status === 'verified')
                                        <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill">
                                            <i class="fas fa-check-circle me-1"></i> Verified
                                        </span>
                                    @else
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning px-3 py-2 rounded-pill">
                                            <i class="fas fa-clock me-1"></i> {{ ucfirst($dr->status ?? 'Recorded') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 text-end">
                                    <a href="{{ route('delivery-receipts.show', $dr->id) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3 shadow-sm">
                                        <i class="fas fa-eye me-1"></i> View Receipt
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="fas fa-boxes-packing fa-3x mb-3 text-secondary opacity-50"></i>
                                    <h6>No store delivery vouchers found</h6>
                                    <p class="small text-muted mb-0">Delivery vouchers will appear here when goods are received by the Store Manager.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($deliveryReceipts->hasPages())
            <div class="card-footer bg-white border-0 py-3 px-4">
                {{ $deliveryReceipts->links() }}
            </div>
        @endif
    </div>

    <!-- TAB CONTENT 3: Credit Invoices -->
    @elseif($activeTab === 'credit_receipts')
    <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-2 d-flex justify-content-between align-items-center">
            <div>
                <h5 class="fw-bold text-dark mb-0">
                    <i class="fas fa-credit-card text-info me-2"></i>Credit Purchase Invoices & Settlement Receipts (COA 5110)
                </h5>
                <p class="text-muted small mb-0">Credit purchase vouchers and debt settlement receipts attached in Credit Store Ledger.</p>
            </div>
            <span class="badge bg-light text-secondary border rounded-pill px-3 py-2">
                {{ $creditReceipts->total() }} Invoices
            </span>
        </div>

        <div class="card-body p-0 mt-2">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-muted small text-uppercase">
                        <tr>
                            <th class="px-4 py-3">Credit Ref / PR #</th>
                            <th class="py-3">Supplier & Project</th>
                            <th class="py-3">Amount (ETB)</th>
                            <th class="py-3">Attached Document</th>
                            <th class="py-3 text-center">Status</th>
                            <th class="px-4 py-3 text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($creditReceipts as $cRec)
                            <tr>
                                <td class="px-4">
                                    <span class="fw-bold text-dark font-monospace">{{ $cRec->pr_no ?? 'CR-' . $cRec->id }}</span>
                                    @if($cRec->purchaseRequest)
                                        <div>
                                            <a href="{{ route('purchase-requests.show', $cRec->purchaseRequest->id) }}" class="small text-primary text-decoration-none">
                                                PR #{{ $cRec->purchaseRequest->pr_no }}
                                            </a>
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $cRec->supplier_name ?? 'Supplier' }}</div>
                                    <small class="text-muted">{{ $cRec->project?->name ?? $cRec->purchaseRequest?->project?->name ?? 'General' }}</small>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark">ETB {{ number_format($cRec->credit_amount, 2) }}</div>
                                    <small class="text-muted">Settled: ETB {{ number_format($cRec->paid_amount, 2) }}</small>
                                </td>
                                <td>
                                    @php
                                        $paidPayments = $cRec->payments->filter(fn($p) => !empty($p->receipt_path));
                                    @endphp
                                    @if($paidPayments->count() > 0)
                                        @foreach($paidPayments as $pay)
                                            @php
                                                $pUrl = \App\Services\FileUploadService::url($pay->receipt_path);
                                                $isPdf = strtolower(pathinfo($pay->receipt_path, PATHINFO_EXTENSION)) === 'pdf';
                                            @endphp
                                            <a href="{{ $pUrl }}" target="_blank" class="btn btn-sm btn-outline-info rounded-pill px-2 py-1 mb-1 me-1 shadow-sm" style="font-size: 0.75rem;">
                                                <i class="fas {{ $isPdf ? 'fa-file-pdf text-danger' : 'fa-file-invoice text-info' }} me-1"></i> Receipt (ETB {{ number_format($pay->amount, 2) }})
                                            </a>
                                        @endforeach
                                    @else
                                        <span class="text-muted small">No payment receipt yet</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($cRec->status === 'fully_paid')
                                        <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill">
                                            <i class="fas fa-check-circle me-1"></i> Paid & Settled
                                        </span>
                                    @elseif($cRec->status === 'partially_paid')
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning px-3 py-2 rounded-pill">
                                            <i class="fas fa-clock me-1"></i> Partially Paid
                                        </span>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-2 rounded-pill">
                                            <i class="fas fa-exclamation-circle me-1"></i> Outstanding
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 text-end">
                                    <a href="{{ \Illuminate\Support\Facades\Route::has('finance.credit-store.index') ? route('finance.credit-store.index') : url('/finance/credit-store') }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3 shadow-sm">
                                        <i class="fas fa-list me-1"></i> Open Ledger
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="fas fa-credit-card fa-3x mb-3 text-secondary opacity-50"></i>
                                    <h6>No credit invoices attached</h6>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($creditReceipts->hasPages())
            <div class="card-footer bg-white border-0 py-3 px-4">
                {{ $creditReceipts->links() }}
            </div>
        @endif
    </div>
    @endif

    <!-- TAB CONTENT 4: Auditor Inquiries (የተጠየቁ ደረሰኞች) -->
    @if($activeTab === 'inquired_receipts')
    <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h5 class="fw-bold text-dark mb-0">
                    <i class="fas fa-file-circle-question text-warning me-2"></i>Auditor Inquiries & Requested Receipts (የተጠየቁ ደረሰኞች)
                </h5>
                <p class="text-muted small mb-0">Payments where the Auditor has requested official receipts. Overdue >3 days alerts Auditor & Finance Head. Overdue >5 days escalates directly to GM & General Admin via SMS.</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <form method="POST" action="{{ \Illuminate\Support\Facades\Route::has('delivery-receipts.trigger-audit-escalations') ? route('delivery-receipts.trigger-audit-escalations') : url('/delivery-receipts/trigger-audit-escalations') }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-warning text-dark fw-bold rounded-pill px-3 shadow-sm" title="Check inquiries and trigger escalation SMS if overdue">
                        <i class="fas fa-tower-broadcast me-1 text-warning"></i> Check & Send Escalation SMS
                    </button>
                </form>
                <span class="badge bg-warning bg-opacity-10 text-dark border border-warning rounded-pill px-3 py-2 fw-bold">
                    {{ $inquiredReceiptsCount }} Pending Inquir{{ $inquiredReceiptsCount === 1 ? 'y' : 'ies' }}
                </span>
            </div>
        </div>

        <div class="card-body p-0 mt-2">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-muted small text-uppercase">
                        <tr>
                            <th class="px-4 py-3">Reference & Date</th>
                            <th class="py-3">Requester & Dept</th>
                            <th class="py-3">Amount & Paying Account</th>
                            <th class="py-3" style="max-width: 280px;">Auditor Inquiry Note</th>
                            <th class="py-3 text-center">Receipt & Escalation Status</th>
                            <th class="px-4 py-3 text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($inquiredReceipts as $inq)
                            <tr>
                                <!-- Reference & Date -->
                                <td class="px-4">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="badge bg-warning bg-opacity-10 text-dark p-2 rounded-3 border border-warning border-opacity-50">
                                            <i class="fas fa-receipt fa-lg text-warning"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark font-monospace">
                                                {{ $inq->reference_no }}
                                            </div>
                                            <small class="text-muted d-block">
                                                {{ $inq->date ? \Carbon\Carbon::parse($inq->date)->format('M d, Y') : 'N/A' }}
                                            </small>
                                            <span class="badge bg-light text-secondary border rounded-pill" style="font-size: 0.7rem;">
                                                {{ ucfirst(str_replace('_', ' ', $inq->source_type)) }}
                                            </span>
                                        </div>
                                    </div>
                                </td>

                                <!-- Requester & Dept -->
                                <td>
                                    <div class="fw-bold text-dark">{{ $inq->requester }}</div>
                                    <small class="text-muted d-block">{{ $inq->department }}</small>
                                    @if($inq->category)
                                        <span class="badge bg-secondary-subtle text-secondary rounded-pill" style="font-size: 0.72rem;">
                                            {{ $inq->category }}
                                        </span>
                                    @endif
                                    @if($inq->description)
                                        <div class="small text-muted text-truncate mt-1" style="max-width: 200px;" title="{{ $inq->description }}">
                                            {{ $inq->description }}
                                        </div>
                                    @endif
                                </td>

                                <!-- Amount & Paying Account -->
                                <td>
                                    <div class="fw-bold text-success fs-6">ETB {{ number_format($inq->amount, 2) }}</div>
                                    <div class="small text-muted mt-1">
                                        <i class="fas fa-wallet me-1 text-secondary"></i>{{ $inq->paying_account }}
                                    </div>
                                    <span class="badge bg-success-subtle text-success border border-success rounded-pill mt-1" style="font-size: 0.7rem;">
                                        {{ $inq->payment_status }}
                                    </span>
                                </td>

                                <!-- Auditor Inquiry Note -->
                                <td style="max-width: 280px;">
                                    <div class="p-2 rounded-3 bg-warning bg-opacity-10 border border-warning border-opacity-25 text-warning-emphasis small">
                                        <div class="fw-bold mb-1">
                                            <i class="fas fa-comment-dots me-1 text-warning"></i> Audit Note:
                                        </div>
                                        <div class="text-wrap" style="line-height: 1.35;">
                                            {{ $inq->audit_note ?: 'Official VAT receipt / sales invoice required for audit approval.' }}
                                        </div>
                                    </div>
                                    @if($inq->requested_at)
                                        <small class="text-muted d-block mt-1" style="font-size: 0.72rem;">
                                            <i class="fas fa-clock me-1"></i>Requested: {{ \Carbon\Carbon::parse($inq->requested_at)->diffForHumans() }} ({{ $inq->requested_by }})
                                        </small>
                                    @endif
                                </td>

                                <!-- Receipt & Escalation Status -->
                                <td class="text-center">
                                    <span class="badge bg-warning text-dark border border-warning px-3 py-2 rounded-pill shadow-sm">
                                        <i class="fas fa-paper-plane me-1"></i> Receipt Inquired
                                    </span>

                                    <!-- Escalation status according to age -->
                                    <div class="mt-2">
                                        @if(($inq->age_days ?? 0) >= 5 || !empty($inq->escalated_5day_at))
                                            <span class="badge bg-danger rounded-pill px-2 py-1 shadow-sm" title="Overdue > 5 days. Escalated directly to GM & General Admin">
                                                <i class="fas fa-triangle-exclamation me-1"></i> Overdue ({{ $inq->age_days }}d): GM & Admin Alerted
                                            </span>
                                            @if($inq->escalated_5day_at)
                                                <div class="text-danger small mt-1 fw-bold" style="font-size: 0.7rem;">
                                                    <i class="fas fa-sms me-1"></i> SMS Dispatched to GM & Admin
                                                </div>
                                            @endif
                                        @elseif(($inq->age_days ?? 0) >= 3 || !empty($inq->escalated_3day_at))
                                            <span class="badge bg-warning text-dark border border-warning rounded-pill px-2 py-1 shadow-sm" title="Overdue > 3 days. Escalated to Auditor & Finance Head">
                                                <i class="fas fa-envelope-open-text me-1"></i> Overdue ({{ $inq->age_days }}d): Auditor & Finance Head Alerted
                                            </span>
                                            @if($inq->escalated_3day_at)
                                                <div class="text-warning-emphasis small mt-1 fw-bold" style="font-size: 0.7rem;">
                                                    <i class="fas fa-sms me-1"></i> SMS Dispatched to Auditor & Finance Head
                                                </div>
                                            @endif
                                        @else
                                            <span class="badge bg-light text-secondary border rounded-pill px-2 py-1" style="font-size: 0.72rem;">
                                                <i class="fas fa-hourglass-start me-1 text-primary"></i> Pending {{ $inq->age_days ?? 0 }} day{{ ($inq->age_days ?? 0) === 1 ? '' : 's' }}
                                            </span>
                                        @endif
                                    </div>

                                    @if($inq->has_receipt && $inq->receipt_url)
                                        <div class="mt-2">
                                            <a href="{{ $inq->receipt_url }}" target="_blank" class="btn btn-xs btn-outline-secondary rounded-pill px-2 py-1 small" style="font-size: 0.75rem;">
                                                <i class="fas fa-paperclip me-1"></i> View Current File
                                            </a>
                                        </div>
                                    @else
                                        <div class="small text-danger mt-1" style="font-size: 0.75rem;">
                                            <i class="fas fa-exclamation-circle me-1"></i> Missing official receipt
                                        </div>
                                    @endif
                                </td>

                                <!-- Action: Add Receipt -->
                                <td class="px-4 text-end">
                                    <button type="button" class="btn btn-sm btn-dark rounded-pill px-3 shadow-sm fw-bold"
                                            data-bs-toggle="modal"
                                            data-bs-target="#uploadInquiredModal_{{ $inq->unique_key }}">
                                        <i class="fas fa-upload me-1"></i> Add Receipt
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <div class="rounded-circle bg-light d-inline-flex p-4 mb-3">
                                        <i class="fas fa-check-circle fa-3x text-success"></i>
                                    </div>
                                    <h6 class="fw-bold text-dark">No Pending Auditor Inquiries</h6>
                                    <p class="small text-muted mb-0">All transactions have valid receipts attached, or no auditor inquiries are currently pending for you.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif
</div>

<!-- ========================================== -->
<!-- MODALS FOR PR RECEIPT PREVIEW & VERIFY     -->
<!-- ========================================== -->
@foreach($procurementReceipts as $rec)
    @php
        $pr = $rec->purchaseRequest;
        $fileUrl = \App\Services\FileUploadService::url($rec->file_path);
        $isPdf = strtolower(pathinfo($rec->file_path, PATHINFO_EXTENSION)) === 'pdf';
        $amount = $pr?->payment?->amount ?? $pr?->direct_buy_amount ?? 0;
    @endphp

    <!-- 1. Document Preview Modal -->
    <div class="modal fade" id="previewModal{{ $rec->id }}" tabindex="-1" aria-labelledby="previewModalLabel{{ $rec->id }}" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header bg-white border-bottom py-3 px-4">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-file-invoice text-primary fs-5"></i>
                        <div>
                            <h5 class="modal-title fw-bold text-dark mb-0" id="previewModalLabel{{ $rec->id }}">
                                Vendor Receipt: PR #{{ $pr?->pr_no ?? $rec->purchase_request_id }}
                            </h5>
                            <small class="text-muted">Total: <strong>ETB {{ number_format($amount, 2) }}</strong> &bull; File: {{ $rec->original_filename }}</small>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <a href="{{ $fileUrl }}" target="_blank" download="{{ $rec->original_filename }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                            <i class="fas fa-download me-1"></i> Download
                        </a>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
                <div class="modal-body p-0 bg-dark d-flex justify-content-center align-items-center" style="min-height: 500px;">
                    @if($isPdf)
                        <iframe src="{{ $fileUrl }}" class="w-100 border-0" style="height: 75vh;"></iframe>
                    @else
                        <img src="{{ $fileUrl }}" class="img-fluid p-2" style="max-height: 80vh; object-fit: contain;" alt="Purchase Receipt">
                    @endif
                </div>
                <div class="modal-footer bg-white border-top py-2 px-4 d-flex justify-content-between">
                    <div class="small text-muted">
                        Uploaded by <strong>{{ $rec->uploadedBy?->name ?? 'Procurement' }}</strong> on {{ $rec->created_at->format('M d, Y h:i A') }}
                    </div>
                    <div>
                        @if($rec->verification_status !== 'verified')
                            <button type="button" class="btn btn-success btn-sm rounded-pill px-4 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#verifyModal{{ $rec->id }}">
                                <i class="fas fa-check-double me-1"></i> Verify Receipt
                            </button>
                        @endif
                        <button type="button" class="btn btn-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Verify Modal -->
    <div class="modal fade" id="verifyModal{{ $rec->id }}" tabindex="-1" aria-labelledby="verifyModalLabel{{ $rec->id }}" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <form method="POST" action="{{ route('procurement-receipts.verify', $rec->id) }}">
                    @csrf
                    <div class="modal-header bg-success text-white py-3 px-4">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fas fa-stamp fs-5"></i>
                            <h5 class="modal-title fw-bold mb-0" id="verifyModalLabel{{ $rec->id }}">
                                Verify Vendor Receipt (PR #{{ $pr?->pr_no }})
                            </h5>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4 bg-white">
                        <div class="alert alert-info py-2 px-3 small mb-3 border-start border-4 border-info">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">Purchase Request:</span>
                                <strong class="text-dark">PR #{{ $pr?->pr_no }}</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">Receipt Amount:</span>
                                <strong class="text-success fs-6">ETB {{ number_format($amount, 2) }}</strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">Funding Account:</span>
                                <strong class="text-dark">{{ $pr?->payment?->coaAccount?->name ?? 'COA' }}</strong>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-dark text-uppercase">Verification Decision <span class="text-danger">*</span></label>
                            <select name="verification_status" class="form-select form-select-sm" required>
                                <option value="verified" selected>✓ Verify & Approve (Receipt Valid)</option>
                                <option value="rejected">✗ Reject Receipt (Invalid / Incorrect Amount)</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted text-uppercase">Verification Notes / Remarks</label>
                            <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="e.g. Verified against bank transfer ref, invoice matches PR items."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer bg-light border-0 py-3 px-4">
                        <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm">
                            <i class="fas fa-check-double me-1"></i> Submit Verification
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endforeach

<!-- ======================================================== -->
<!-- MODALS FOR UPLOADING REQUESTED RECEIPTS (AUDITOR INQUIRIES) -->
<!-- ======================================================== -->
@foreach($inquiredReceipts as $inq)
    <div class="modal fade" id="uploadInquiredModal_{{ $inq->unique_key }}" tabindex="-1" aria-labelledby="uploadInquiredModalLabel_{{ $inq->unique_key }}" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <form method="POST" action="{{ \Illuminate\Support\Facades\Route::has('delivery-receipts.upload-inquired-receipt') ? route('delivery-receipts.upload-inquired-receipt') : url('/delivery-receipts/upload-inquired-receipt') }}" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="source_type" value="{{ $inq->source_type }}">
                    <input type="hidden" name="source_id" value="{{ $inq->source_id }}">

                    <div class="modal-header bg-dark text-white py-3 px-4">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fas fa-file-arrow-up fs-5 text-warning"></i>
                            <h5 class="modal-title fw-bold mb-0" id="uploadInquiredModalLabel_{{ $inq->unique_key }}">
                                Add / Upload Receipt: {{ $inq->reference_no }}
                            </h5>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body p-4 bg-white">
                        <!-- Auditor Note Reminder Card -->
                        <div class="alert alert-warning border-0 rounded-3 p-3 mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted small">Reference:</span>
                                <strong class="text-dark font-monospace small">{{ $inq->reference_no }}</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted small">Amount:</span>
                                <strong class="text-success small">ETB {{ number_format($inq->amount, 2) }}</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted small">Paying Account:</span>
                                <strong class="text-dark small">{{ $inq->paying_account }}</strong>
                            </div>
                            <div class="pt-2 border-top border-warning border-opacity-50">
                                <div class="text-warning-emphasis small fw-bold">
                                    <i class="fas fa-comment-dots me-1"></i> Auditor Note:
                                </div>
                                <div class="small text-dark mt-1 fw-semibold">
                                    {{ $inq->audit_note ?: 'Official VAT receipt / sales invoice required.' }}
                                </div>
                            </div>
                        </div>

                        <!-- File Upload -->
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-dark text-uppercase">
                                Upload Receipt Document <span class="text-danger">*</span>
                            </label>
                            <input type="file" name="receipt_file" class="form-control form-control-sm rounded-3" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
                            <div class="form-text small text-muted">
                                <i class="fas fa-info-circle me-1"></i> Accepted formats: PDF, JPG, PNG, WEBP (Max 10 MB).
                            </div>
                        </div>

                        <!-- Notes / Explanation -->
                        <div class="mb-2">
                            <label class="form-label small fw-bold text-muted text-uppercase">
                                Notes / Remarks for Auditor (Optional)
                            </label>
                            <textarea name="notes" class="form-control form-control-sm rounded-3" rows="2" placeholder="e.g. Official VAT receipt attached from vendor, matches payment..."></textarea>
                        </div>
                    </div>

                    <div class="modal-footer bg-light border-0 py-3 px-4">
                        <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
                            <i class="fas fa-upload me-1"></i> Attach & Submit to Audit
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endforeach

@endsection
