@extends('layouts.app')

@section('title', 'Issued Materials - Store Manager')

@section('content')
@push('styles')
@include('layouts._store_mobile')
@endpush

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h4><i class="fas fa-hand-holding me-2"></i>Issued Materials</h4>
            <p class="text-muted">View all materials that have been issued from stores</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 g-md-3 filter-form">
                <div class="col-md-4">
                    <label class="form-label">Store</label>
                    <select name="store_id" class="form-select">
                        <option value="">All Stores</option>
                        @foreach($stores as $store)
                        <option value="{{ $store->id }}" {{ request('store_id') == $store->id ? 'selected' : '' }}>
                            {{ $store->name }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">&nbsp;</label>
                    <div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search me-1"></i>Filter</button>
                        <a href="{{ route('store-manager.issued.index') }}" class="btn btn-secondary">Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Issued Materials Table -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Slip #</th>
                            <th>Store</th>
                            <th>Issue Date</th>
                            <th>Product</th>
                            <th class="text-end">Quantity</th>
                            <th>Issued By</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($issued as $slip)
                            @foreach($slip->items as $item)
                            <tr>
                                <td><strong>{{ $slip->receipt_no }}</strong></td>
                                <td>{{ $slip->store->name ?? 'N/A' }}</td>
                                <td>{{ $slip->receipt_date ? $slip->receipt_date->format('M d, Y') : '-' }}</td>
                                <td>
                                    <strong>{{ $item->product->name ?? 'N/A' }}</strong>
                                    <br><small class="text-muted">{{ $item->product->code ?? '' }}</small>
                                </td>
                                <td class="text-end fw-bold">{{ number_format($item->quantity, 3) }}</td>
                                <td>{{ $slip->receivedBy->name ?? '-' }}</td>
                            </tr>
                            @endforeach
                        @empty
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">No issued materials found</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer">
            {{ $issued->links() }}
        </div>
    </div>
</div>
@endsection

