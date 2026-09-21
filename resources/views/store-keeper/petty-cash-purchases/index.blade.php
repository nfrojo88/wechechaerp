@extends('layouts.app')
@section('title', 'Petty Cash Material Purchases - Store Keeper')

@section('content')
<div class="container-fluid px-4 py-3">

    {{-- ── Top Navigation & Page Title ────────────────────────────────────────── --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h3 class="fw-bold text-dark mb-0">
                    <i class="fa-solid fa-cart-flatbed text-success me-2"></i>Petty Cash Material Purchases
                </h3>
                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">
                    <i class="fa-solid fa-unlock me-1"></i>Direct Site Intake
                </span>
            </div>
            <p class="text-muted small mb-0">
                Direct spot material purchases using Petty Cash funds with immediate inventory intake for 
                <strong>{{ $assignedStore->name ?? 'Site Store' }}</strong>.
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @if($assignedStore)
                <a href="{{ route('store-manager.inventory.all') }}" class="btn btn-outline-secondary btn-sm shadow-sm">
                    <i class="fa-solid fa-boxes-stacked me-1"></i> View Stock
                </a>
            @endif
            <a href="{{ route('store-keeper.petty-cash-purchases.create') }}" class="btn btn-success btn-sm shadow-sm px-3 fw-bold">
                <i class="fa-solid fa-plus-circle me-1"></i> Buy Material (Petty Cash)
            </a>
        </div>
    </div>

    {{-- ── KPI Cards ────────────────────────────────────────────────────────── --}}
    <div class="row g-3 mb-4">
        {{-- Total Spent --}}
        <div class="col-sm-6 col-xl-4">
            <div class="card border-0 shadow-sm rounded-3 h-100" style="border-left: 4px solid #10b981 !important;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted fw-semibold mb-1" style="font-size: 0.72rem; letter-spacing: .06em; text-transform: uppercase;">Total Petty Cash Spent</p>
                            <h3 class="fw-bold text-success mb-0">ETB {{ number_format($totalSpent, 2) }}</h3>
                        </div>
                        <div class="p-2 rounded-3" style="background: rgba(16,185,129,.12);">
                            <i class="fa-solid fa-wallet fa-lg text-success"></i>
                        </div>
                    </div>
                    <small class="text-muted mt-2 d-block" style="font-size:0.73rem;">Cumulative direct purchases on site</small>
                </div>
            </div>
        </div>

        {{-- Total Purchases Count --}}
        <div class="col-sm-6 col-xl-4">
            <div class="card border-0 shadow-sm rounded-3 h-100" style="border-left: 4px solid #0284c7 !important;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted fw-semibold mb-1" style="font-size: 0.72rem; letter-spacing: .06em; text-transform: uppercase;">Purchases Logged</p>
                            <h3 class="fw-bold text-dark mb-0">{{ number_format($totalPurchases) }}</h3>
                        </div>
                        <div class="p-2 rounded-3" style="background: rgba(2,132,199,.12);">
                            <i class="fa-solid fa-receipt fa-lg text-primary"></i>
                        </div>
                    </div>
                    <small class="text-muted mt-2 d-block" style="font-size:0.73rem;">Verified vendor cash receipts</small>
                </div>
            </div>
        </div>

        {{-- Current Petty Cash Fund Status --}}
        <div class="col-sm-6 col-xl-4">
            <div class="card border-0 shadow-sm rounded-3 h-100" style="border-left: 4px solid #f59e0b !important;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted fw-semibold mb-1" style="font-size: 0.72rem; letter-spacing: .06em; text-transform: uppercase;">Site Petty Cash Balance</p>
                            <h3 class="fw-bold text-warning mb-0">
                                ETB {{ number_format($pettyCashAccount->current_balance ?? 0, 2) }}
                            </h3>
                        </div>
                        <div class="p-2 rounded-3" style="background: rgba(245,158,11,.12);">
                            <i class="fa-solid fa-coins fa-lg text-warning"></i>
                        </div>
                    </div>
                    <small class="text-muted mt-2 d-block" style="font-size:0.73rem;">
                        Dedicated Fund: <strong>{{ $pettyCashAccount->name ?? 'Site Petty Cash' }}</strong> [{{ $pettyCashAccount->code ?? 'Site Fund' }}]
                    </small>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Filters & Search ─────────────────────────────────────────────────── --}}
    <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('store-keeper.petty-cash-purchases.index') }}" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text" name="search" class="form-control bg-light border-0" placeholder="Search purchase #, supplier, receipt #..." value="{{ request('search') }}">
                    </div>
                </div>

                @if(!$isStoreKeeper)
                <div class="col-md-3">
                    <select name="store_id" class="form-select form-select-sm bg-light border-0">
                        <option value="">-- All Stores --</option>
                        @foreach($stores as $st)
                            <option value="{{ $st->id }}" {{ request('store_id') == $st->id ? 'selected' : '' }}>{{ $st->name }}</option>
                        @endforeach
                    </select>
                </div>
                @endif

                <div class="col-md-2 col-6">
                    <input type="date" name="from_date" class="form-control form-control-sm bg-light border-0" value="{{ request('from_date') }}" title="From Date">
                </div>
                <div class="col-md-2 col-6">
                    <input type="date" name="to_date" class="form-control form-control-sm bg-light border-0" value="{{ request('to_date') }}" title="To Date">
                </div>

                <div class="col-md-1 d-flex gap-1">
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fa-solid fa-filter me-1"></i>Filter</button>
                    @if(request()->hasAny(['search', 'store_id', 'from_date', 'to_date']))
                        <a href="{{ route('store-keeper.petty-cash-purchases.index') }}" class="btn btn-outline-secondary btn-sm" title="Clear Filters"><i class="fa-solid fa-xmark"></i></a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    {{-- ── Purchases Table ─────────────────────────────────────────────────── --}}
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-dark">
                <i class="fa-solid fa-list-check text-primary me-2"></i>Purchase Log &amp; Goods Intake History
            </h6>
            <span class="text-muted small">Showing {{ $purchases->firstItem() ?? 0 }}-{{ $purchases->lastItem() ?? 0 }} of {{ $purchases->total() }}</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Purchase #</th>
                        <th>Date</th>
                        <th>Store Location</th>
                        <th>Supplier / Vendor</th>
                        <th>Receipt #</th>
                        <th>Items Count</th>
                        <th class="text-end">Total Amount</th>
                        <th>Receive Slip (GRN)</th>
                        <th class="text-end pe-3">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($purchases as $p)
                    <tr>
                        <td class="ps-3">
                            <a href="{{ route('store-keeper.petty-cash-purchases.show', $p) }}" class="fw-bold font-monospace text-primary text-decoration-none">
                                {{ $p->purchase_no }}
                            </a>
                        </td>
                        <td>
                            <span class="text-dark small">{{ optional($p->purchase_date)->format('d M Y') }}</span>
                            <small class="text-muted d-block" style="font-size:0.7rem;">By {{ $p->purchaser->name ?? 'Store Keeper' }}</small>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border">
                                <i class="fa-solid fa-warehouse me-1 text-secondary"></i>{{ $p->store->name ?? 'Main Store' }}
                            </span>
                        </td>
                        <td>
                            <strong class="text-dark d-block small">{{ $p->supplier_name ?: 'Local Vendor' }}</strong>
                        </td>
                        <td>
                            @if($p->receipt_no)
                                <span class="badge bg-secondary font-monospace" style="font-size: 0.75rem;">{{ $p->receipt_no }}</span>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                            @if($p->attachment_path)
                                <a href="{{ asset($p->attachment_path) }}" target="_blank" class="ms-1 text-primary" title="View Receipt Document">
                                    <i class="fa-solid fa-paperclip"></i>
                                </a>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-info text-dark">{{ $p->items->count() }} {{ Str::plural('item', $p->items->count()) }}</span>
                        </td>
                        <td class="text-end">
                            <strong class="text-success font-monospace">ETB {{ number_format($p->total_amount, 2) }}</strong>
                        </td>
                        <td>
                            @if($p->deliveryReceipt)
                                <span class="badge bg-success font-monospace" style="font-size:0.75rem;">
                                    <i class="fa-solid fa-check-circle me-1"></i>{{ $p->deliveryReceipt->dr_no }}
                                </span>
                            @else
                                <span class="badge bg-light text-secondary">Direct Added</span>
                            @endif
                        </td>
                        <td class="text-end pe-3">
                            <a href="{{ route('store-keeper.petty-cash-purchases.show', $p) }}" class="btn btn-sm btn-outline-primary shadow-xs">
                                <i class="fa-solid fa-eye me-1"></i>View
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-cart-flatbed fa-3x mb-3 text-secondary opacity-25 d-block"></i>
                            <h6 class="fw-bold">No Petty Cash Material Purchases Recorded</h6>
                            <p class="small text-muted mb-3">You can buy small site materials directly using Petty Cash and have them added immediately to store inventory.</p>
                            <a href="{{ route('store-keeper.petty-cash-purchases.create') }}" class="btn btn-success btn-sm">
                                <i class="fa-solid fa-plus-circle me-1"></i> Record First Petty Cash Buy
                            </a>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($purchases->hasPages())
        <div class="card-footer bg-white border-top py-2 px-3">
            {{ $purchases->links() }}
        </div>
        @endif
    </div>

</div>
@endsection
