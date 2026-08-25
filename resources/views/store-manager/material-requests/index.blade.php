@extends('layouts.app')

@section('title', 'Material Requests - ' . ($isStoreKeeper ? 'Site Store' : 'Store Hub'))

@section('content')
<div class="container-fluid px-4 py-3">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0" style="color:var(--brand-800)">
                <i class="fas fa-clipboard-list me-2 text-primary"></i>{{ $isStoreKeeper ? 'Site Engineer Material Requests' : 'Material Requests' }}
            </h4>
            <p class="text-muted small mb-0">
                {{ $isStoreKeeper ? 'Review material requests from Site Engineers and issue materials directly from ' . ($assignedStore->name ?? 'your site store') : 'Review material requests and process transfers or purchase requests' }}
            </p>
        </div>
        @if($isStoreKeeper)
        <a href="{{ route('dashboard.store-keeper') }}" class="btn btn-outline-secondary btn-sm shadow-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Store Dashboard
        </a>
        @endif
    </div>

    {{-- Context Banner --}}
    @if($isStoreKeeper && $assignedStore)
    <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white" style="border-left: 5px solid #0284c7 !important;">
        <div class="card-body p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-3">
                <div class="p-2 rounded bg-primary bg-opacity-10 text-primary">
                    <i class="fa-solid fa-person-digging fa-lg"></i>
                </div>
                <div>
                    <h6 class="mb-0 fw-bold text-dark">Site Store: {{ $assignedStore->name }}</h6>
                    <small class="text-muted">Directly issue materials to Site Engineers on site.</small>
                </div>
            </div>
            <a href="{{ route('store-manager.inventory.all') }}" class="btn btn-sm btn-outline-primary">
                <i class="fa-solid fa-boxes-stacked me-1"></i>Check Store Stock
            </a>
        </div>
    </div>
    @endif

    {{-- Filters --}}
    <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body p-3">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-muted mb-1">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="issued" {{ request('status') == 'issued' ? 'selected' : '' }}>Issued / Handed Over</option>
                        <option value="processed" {{ request('status') == 'processed' ? 'selected' : '' }}>Processed</option>
                        <option value="needs_purchase" {{ request('status') == 'needs_purchase' ? 'selected' : '' }}>Sent to Purchase</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-muted mb-1">&nbsp;</label>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm px-3"><i class="fas fa-search me-1"></i>Filter</button>
                        <a href="{{ route('store-manager.material-requests.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Material Requests Table --}}
    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Request #</th>
                            <th>Project</th>
                            <th>Requested By (Site Engineer)</th>
                            <th>Items Count</th>
                            <th>Required Date</th>
                            <th>Status</th>
                            <th class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($requests as $req)
                        <tr>
                            <td class="ps-3">
                                <strong class="font-monospace text-primary">
                                    {{ $req->reference_number ?? '#MR-'.$req->id }}
                                </strong>
                                @if($req->maintenance_request_id && $req->maintenanceRequest)
                                    <div>
                                        <a href="{{ route('general-service.maintenance.show', $req->maintenanceRequest) }}" class="badge bg-warning text-dark text-decoration-none border shadow-xs" title="View linked maintenance ticket">
                                            <i class="fa-solid fa-screwdriver-wrench me-1"></i>{{ $req->maintenanceRequest->request_no }}
                                        </a>
                                    </div>
                                @endif
                            </td>
                            <td><span class="fw-semibold text-dark small">{{ $req->project->name ?? 'N/A' }}</span></td>
                            <td>
                                <span class="text-dark small d-block">{{ $req->requestedBy->name ?? 'Site Engineer' }}</span>
                                <small class="text-muted">{{ optional($req->created_at)->format('d M Y, H:i') }}</small>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">{{ $req->items->count() }} items</span>
                            </td>
                            <td><small class="text-muted">{{ $req->required_date ? $req->required_date->format('d M Y') : '-' }}</small></td>
                            <td>
                                @switch($req->status)
                                    @case('pending')
                                    @case('sent_to_store_manager')
                                        <span class="badge bg-warning text-dark">Pending Review</span>
                                        @break
                                    @case('issued')
                                        <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i>Issued</span>
                                        @break
                                    @case('processed')
                                        <span class="badge bg-info text-dark">Processed</span>
                                        @break
                                    @case('needs_purchase')
                                    @case('sent_to_pr')
                                        <span class="badge bg-secondary">Sent to Purchase</span>
                                        @break
                                    @default
                                        <span class="badge bg-secondary">{{ ucfirst(str_replace('_', ' ', $req->status)) }}</span>
                                @endswitch
                            </td>
                            <td class="text-end pe-3">
                                <button type="button" class="btn btn-sm btn-outline-info me-1" data-bs-toggle="modal" data-bs-target="#modal-{{ $req->id }}">
                                    <i class="fas fa-eye me-1"></i>Items
                                </button>

                                @if(in_array($req->status, ['pending', 'sent_to_store_manager']))
                                    {{-- Store Keeper Direct Issue Action --}}
                                    @if($isStoreKeeper)
                                    <form action="{{ route('store-manager.material-requests.issue', $req) }}" method="POST" class="d-inline" onsubmit="return confirm('Confirm issuing materials to the Site Engineer / Technician? Stock will be deducted from your store.');">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-success shadow-sm">
                                            <i class="fa-solid fa-hand-holding-box me-1"></i> Issue Material
                                        </button>
                                    </form>
                                    @else
                                    <form action="{{ route('store-manager.material-requests.process', $req) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-primary" onclick="return confirm('Process this request? Will check availability and create transfer or send to Purchase Manager.')">
                                            <i class="fas fa-check me-1"></i>Process
                                        </button>
                                    </form>
                                    @endif
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                <i class="fa-solid fa-clipboard-check fa-2x mb-2 d-block opacity-25"></i>
                                No material requests found.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($requests->hasPages())
        <div class="card-footer bg-white border-top py-2">
            {{ $requests->links() }}
        </div>
        @endif
    </div>

</div>

{{-- Modals for each request --}}
@foreach($requests as $req)
<div class="modal fade" id="modal-{{ $req->id }}" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-white border-bottom py-3">
                <h5 class="modal-title fw-bold text-dark">
                    <i class="fa-solid fa-clipboard-list me-2 text-primary"></i>Material Request {{ $req->reference_number ?? '#MR-'.$req->id }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="p-3 bg-light border-bottom">
                    <div class="row g-2 small">
                        <div class="col-md-6"><strong>Project:</strong> {{ $req->project->name ?? 'N/A' }}</div>
                        <div class="col-md-6"><strong>Requested By:</strong> {{ $req->requestedBy->name ?? 'Site Engineer' }}</div>
                        <div class="col-md-6"><strong>Required Date:</strong> {{ $req->required_date ? $req->required_date->format('d M Y') : '-' }}</div>
                        <div class="col-md-6"><strong>Notes:</strong> {{ $req->notes ?: 'None' }}</div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Product Name</th>
                                <th>Code / SKU</th>
                                <th class="text-center">Requested Qty</th>
                                <th class="text-center">On-Hand in Store</th>
                                <th class="text-center">Unit</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($req->items as $item)
                            @php
                                $availStock = $storeStock[$item->product_id] ?? null;
                            @endphp
                            <tr>
                                <td class="ps-3 fw-semibold text-dark">{{ $item->product->name ?? 'Material #'.$item->product_id }}</td>
                                <td><span class="font-monospace text-muted small">{{ $item->product->code ?? '—' }}</span></td>
                                <td class="text-center fw-bold text-primary">{{ number_format($item->quantity, 2) }}</td>
                                <td class="text-center">
                                    @if(!is_null($availStock))
                                        <span class="fw-bold {{ $availStock >= $item->quantity ? 'text-success' : 'text-danger' }}">
                                            {{ number_format($availStock, 2) }}
                                        </span>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                                <td class="text-center"><span class="badge bg-light text-dark">{{ $item->unit ?? 'pcs' }}</span></td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center py-3 text-muted">No items in this request</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                @if($req->status == 'pending' && $isStoreKeeper)
                <form action="{{ route('store-manager.material-requests.issue', $req) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-success" onclick="return confirm('Confirm issuing materials to the Site Engineer?');">
                        <i class="fa-solid fa-hand-holding-box me-1"></i> Issue to Engineer
                    </button>
                </form>
                @endif
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endforeach
@endsection
