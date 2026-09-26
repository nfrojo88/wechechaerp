@extends('layouts.app')

@section('title', 'Slip Records - Store Manager')

@section('content')
@push('styles')
@include('layouts._store_mobile')
@endpush

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-6">
            <h4><i class="fas fa-file-invoice me-2"></i>Slip Records</h4>
            <p class="text-muted">Manage all Receive and Send slips with sequence validation</p>
        </div>
        <div class="col-md-6 text-end">
            <a href="{{ route('store-manager.slips.create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i>Create New Slip
            </a>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-left border-4 border-success shadow-sm">
                <div class="card-body">
                    <div class="text-uppercase text-muted small">Total Receive Slips</div>
                    <div class="fs-5 fw-bold">{{ $stats['receive_total'] ?? 0 }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-left border-4 border-info shadow-sm">
                <div class="card-body">
                    <div class="text-uppercase text-muted small">Total Send Slips</div>
                    <div class="fs-5 fw-bold">{{ $stats['send_total'] ?? 0 }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-left border-4 border-warning shadow-sm">
                <div class="card-body">
                    <div class="text-uppercase text-muted small">Sequence Gaps</div>
                    <div class="fs-5 fw-bold text-warning">{{ $stats['gaps'] ?? 0 }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-left border-4 border-danger shadow-sm">
                <div class="card-body">
                    <div class="text-uppercase text-muted small">Void Slips</div>
                    <div class="fs-5 fw-bold text-danger">{{ $stats['void'] ?? 0 }}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 g-md-3 filter-form">
                <div class="col-md-2">
                    <label class="form-label">Store</label>
                    <select name="store_id" class="form-select" onchange="this.form.submit()">
                        <option value="">All Stores</option>
                        @foreach($stores as $store)
                        <option value="{{ $store->id }}" {{ request('store_id') == $store->id ? 'selected' : '' }}>
                            {{ $store->name }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Slip Type</label>
                    <select name="slip_type" class="form-select" onchange="this.form.submit()">
                        <option value="">All Types</option>
                        <option value="receive" {{ request('slip_type') == 'receive' ? 'selected' : '' }}>Receive Slips</option>
                        <option value="send" {{ request('slip_type') == 'send' ? 'selected' : '' }}>Send Slips</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">All Status</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                        <option value="void" {{ request('status') == 'void' ? 'selected' : '' }}>Void</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Slip No Search</label>
                    <input type="text" name="slip_search" class="form-control" value="{{ request('slip_search') }}" placeholder="Search slip number">
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search me-1"></i>Filter</button>
                        <a href="{{ route('store-manager.slips.index') }}" class="btn btn-secondary">Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Slips Table -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Slip #</th>
                            <th>Type</th>
                            <th>Store</th>
                            <th>Date</th>
                            <th>Items</th>
                            <th>Supplier/Reference</th>
                            <th>Status</th>
                            <th>Sequence Check</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($slips as $slip)
                        <tr class="{{ $slip->is_void ? 'table-danger' : '' }}">
                            <td>
                                <strong>{{ $slip->dr_no }}</strong>
                                @if($slip->is_void)
                                <br><span class="badge bg-danger"><i class="fas fa-exclamation-triangle me-1"></i>VOID</span>
                                @endif
                            </td>
                            <td>
                                @if($slip->slip_type === 'receive')
                                <span class="badge bg-success"><i class="fas fa-arrow-down me-1"></i>Receive</span>
                                @else
                                <span class="badge bg-info"><i class="fas fa-arrow-up me-1"></i>Send</span>
                                @endif
                            </td>
                            <td>{{ $slip->store->name ?? 'N/A' }}</td>
                            <td>{{ $slip->received_date ? $slip->received_date->format('M d, Y') : '-' }}</td>
                            <td>
                                <span class="badge bg-primary">{{ $slip->items->count() }} items</span>
                            </td>
                            <td>{{ $slip->supplier_name ?? $slip->reference_no ?? '-' }}</td>
                            <td>
                                @if($slip->is_void)
                                <span class="badge bg-danger">Void</span>
                                @elseif($slip->status == 'pending')
                                <span class="badge bg-warning">Pending</span>
                                @elseif($slip->status == 'draft')
                                <span class="badge bg-secondary">Draft</span>
                                @else
                                <span class="badge bg-success">{{ ucfirst($slip->status) }}</span>
                                @endif
                            </td>
                            <td>
                                @if($slip->sequence_status === 'valid')
                                <span class="badge bg-success" title="Sequence validated"><i class="fas fa-check"></i> Valid</span>
                                @elseif($slip->sequence_status === 'gap')
                                <span class="badge bg-warning" title="Sequence gap detected"><i class="fas fa-exclamation-circle"></i> Gap</span>
                                @else
                                <span class="badge bg-secondary" title="Waiting validation"><i class="fas fa-hourglass-half"></i> Pending</span>
                                @endif
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modal-{{ $slip->id }}">
                                    <i class="fas fa-eye me-1"></i>View
                                </button>
                                @if(!$slip->is_void && in_array($slip->status, ['pending', 'draft']))
                                <form action="{{ route('store-manager.slips.void', $slip) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Mark this slip as void?')">
                                        <i class="fas fa-ban"></i>
                                    </button>
                                </form>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">No slip records found</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer">
            {{ $slips->links() }}
        </div>
    </div>
</div>

<!-- Modals for viewing slip details -->
@foreach($slips as $slip)
<div class="modal fade" id="modal-{{ $slip->id }}" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title">{{ $slip->dr_no }} - {{ ucfirst($slip->slip_type) }} Slip</h5>
                    <small class="text-muted">{{ $slip->received_date?->format('M d, Y H:i') }}</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Store:</strong> {{ $slip->store->name ?? 'N/A' }}
                    </div>
                    <div class="col-md-6">
                        <strong>Status:</strong> 
                        @if($slip->is_void)
                        <span class="badge bg-danger">Void</span>
                        @else
                        <span class="badge bg-{{ $slip->status == 'pending' ? 'warning' : ($slip->status == 'draft' ? 'secondary' : 'success') }}">{{ ucfirst($slip->status) }}</span>
                        @endif
                    </div>
                </div>
                @if($slip->supplier_name)
                <div class="row mb-3">
                    <div class="col-md-12">
                        <strong>Supplier:</strong> {{ $slip->supplier_name }}
                    </div>
                </div>
                @endif
                @if($slip->reference_no)
                <div class="row mb-3">
                    <div class="col-md-12">
                        <strong>Reference:</strong> {{ $slip->reference_no }}
                    </div>
                </div>
                @endif

                <hr>
                <h6>Items</h6>
                <table class="table table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>Product</th>
                            <th class="text-end">Qty</th>
                            <th>Unit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($slip->items as $item)
                        <tr>
                            <td>{{ $item->product->name ?? 'N/A' }}</td>
                            <td class="text-end">{{ number_format($item->quantity_received, 3) }}</td>
                            <td>{{ $item->unit ?? 'pcs' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endforeach
@endsection

