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
                                        @if($rec->verification_status !== 'verified')
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
                            @php
                                $cUrl = \App\Services\FileUploadService::url($cRec->receipt_attachment_path);
                                $isPdf = strtolower(pathinfo($cRec->receipt_attachment_path, PATHINFO_EXTENSION)) === 'pdf';
                            @endphp
                            <tr>
                                <td class="px-4">
                                    <span class="fw-bold text-dark font-monospace">{{ $cRec->invoice_reference ?? 'CR-' . $cRec->id }}</span>
                                    @if($cRec->purchaseRequest)
                                        <div>
                                            <a href="{{ route('purchase-requests.show', $cRec->purchaseRequest->id) }}" class="small text-primary text-decoration-none">
                                                PR #{{ $cRec->purchaseRequest->pr_no }}
                                            </a>
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $cRec->supplier_name ?? $cRec->supplier?->name ?? 'Supplier' }}</div>
                                    <small class="text-muted">{{ $cRec->purchaseRequest?->project?->name ?? 'General' }}</small>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark">ETB {{ number_format($cRec->amount, 2) }}</div>
                                </td>
                                <td>
                                    @if($cUrl)
                                        <a href="{{ $cUrl }}" target="_blank" class="btn btn-sm btn-outline-info rounded-pill px-3 shadow-sm">
                                            <i class="fas {{ $isPdf ? 'fa-file-pdf text-danger' : 'fa-file-invoice text-info' }} me-1"></i> View Invoice
                                        </a>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($cRec->status === 'cleared')
                                        <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill">
                                            <i class="fas fa-check-circle me-1"></i> Paid & Settled
                                        </span>
                                    @else
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning px-3 py-2 rounded-pill">
                                            <i class="fas fa-clock me-1"></i> {{ ucfirst($cRec->status ?? 'Outstanding') }}
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

@endsection
