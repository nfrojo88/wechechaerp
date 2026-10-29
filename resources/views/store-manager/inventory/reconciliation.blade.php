@extends('layouts.app')

@section('title', 'Inventory Reconciliation & Audit - Store Manager')

@section('content')
@push('styles')
@include('layouts._store_mobile')
<style>
    .kpi-card { border-radius: 10px; transition: transform 0.15s ease-in-out; }
    .kpi-card:hover { transform: translateY(-2px); }
</style>
@endpush

<div class="container-fluid">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm mb-3" role="alert">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error') || $errors->any())
        <div class="alert alert-danger alert-dismissible fade show shadow-sm mb-3" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            {{ session('error') ?? $errors->first() }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0">
                <i class="fas fa-scale-balanced me-2 text-primary"></i>Store Inventory Reconciliation &amp; Audit
            </h4>
            <small class="text-muted">
                Three-way consistency check across Physical Store Inventory, Movement Ledger, and Fixed Asset Unit records.
            </small>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('store-manager.inventory.all') }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back to Inventory
            </a>
            <a href="{{ route('inventory.all-movements') }}" class="btn btn-outline-info">
                <i class="fas fa-list-check me-1"></i> Movement Ledger
            </a>
        </div>
    </div>

    <!-- KPI Cards Row -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card kpi-card shadow-sm border-0 bg-light">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small fw-bold text-uppercase">Total Audited</div>
                            <div class="fs-4 fw-bold text-dark mt-1">{{ number_format($kpis['total']) }}</div>
                        </div>
                        <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle">
                            <i class="fas fa-boxes-stacked fa-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card kpi-card shadow-sm border-0 bg-light">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small fw-bold text-uppercase">100% Consistent</div>
                            <div class="fs-4 fw-bold text-success mt-1">{{ number_format($kpis['consistent']) }}</div>
                        </div>
                        <div class="bg-success bg-opacity-10 text-success p-3 rounded-circle">
                            <i class="fas fa-check-double fa-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card kpi-card shadow-sm border-0 bg-light">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small fw-bold text-uppercase">Ledger Drift</div>
                            <div class="fs-4 fw-bold {{ $kpis['ledger_drift'] > 0 ? 'text-danger' : 'text-success' }} mt-1">
                                {{ number_format($kpis['ledger_drift']) }}
                            </div>
                        </div>
                        <div class="bg-warning bg-opacity-10 text-warning p-3 rounded-circle">
                            <i class="fas fa-receipt fa-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card kpi-card shadow-sm border-0 bg-light">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small fw-bold text-uppercase">FA Unit Mismatch</div>
                            <div class="fs-4 fw-bold {{ $kpis['unit_mismatch'] > 0 ? 'text-warning text-dark' : 'text-success' }} mt-1">
                                {{ number_format($kpis['unit_mismatch']) }}
                            </div>
                        </div>
                        <div class="bg-info bg-opacity-10 text-info p-3 rounded-circle">
                            <i class="fas fa-tag fa-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('store-manager.inventory.reconciliation') }}" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Search product name, SKU, or code..." value="{{ $search }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="store_id" class="form-select">
                        <option value="">All Stores</option>
                        @foreach($stores as $st)
                            <option value="{{ $st->id }}" {{ (string)$storeId === (string)$st->id ? 'selected' : '' }}>
                                {{ $st->name }} ({{ ucfirst($st->type) }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="filter" class="form-select">
                        <option value="all" {{ $filter === 'all' ? 'selected' : '' }}>All Items</option>
                        <option value="drift" {{ $filter === 'drift' ? 'selected' : '' }}>Ledger Drift Only</option>
                        <option value="mismatch" {{ $filter === 'mismatch' ? 'selected' : '' }}>Fixed Asset Mismatch Only</option>
                        <option value="consistent" {{ $filter === 'consistent' ? 'selected' : '' }}>Consistent Only</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-1"></i> Filter
                    </button>
                    <a href="{{ route('store-manager.inventory.reconciliation') }}" class="btn btn-outline-secondary" title="Reset Filters">
                        <i class="fas fa-rotate-left"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Reconciliation Table -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr class="small text-uppercase text-muted">
                            <th>Store</th>
                            <th>SKU / Product</th>
                            <th>Category</th>
                            <th class="text-end">On-Hand</th>
                            <th class="text-end">Reserved</th>
                            <th class="text-end">Ledger Sum</th>
                            <th class="text-end">Ledger Diff</th>
                            <th class="text-end">FA Units</th>
                            <th class="text-end">FA Diff</th>
                            <th class="text-center">Status</th>
                            <th class="text-end pe-3">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($report as $idx => $r)
                        <tr>
                            <td>
                                <span class="fw-semibold">{{ $r['store']->name ?? "Store #{$r['inventory']->store_id}" }}</span>
                            </td>
                            <td>
                                <div class="fw-bold">{{ $r['product']->name ?? "Product #{$r['inventory']->product_id}" }}</div>
                                <code class="small text-muted">{{ $r['product']->sku ?? '—' }}</code>
                            </td>
                            <td>
                                @if($r['is_fa'])
                                    <span class="badge bg-info text-dark"><i class="fas fa-computer me-1"></i>Fixed Asset</span>
                                @else
                                    <span class="badge bg-light text-dark border">Consumable</span>
                                @endif
                            </td>
                            <td class="text-end fw-bold fs-6">
                                {{ number_format($r['on_hand'], 3) }}
                            </td>
                            <td class="text-end text-muted">
                                {{ number_format($r['reserved'], 3) }}
                            </td>
                            <td class="text-end font-monospace">
                                {{ number_format($r['ledger_sum'], 3) }}
                            </td>
                            <td class="text-end font-monospace">
                                @if($r['has_ledger_drift'])
                                    <span class="badge bg-danger">
                                        {{ ($r['ledger_diff'] > 0 ? '+' : '') . number_format($r['ledger_diff'], 3) }}
                                    </span>
                                @else
                                    <span class="text-success"><i class="fas fa-check"></i> 0.000</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if($r['is_fa'])
                                    <span class="fw-bold">{{ $r['active_units'] !== null ? $r['active_units'] : '0' }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if($r['is_fa'] && $r['unit_diff'] !== null)
                                    @if($r['has_unit_mismatch'])
                                        <span class="badge bg-warning text-dark">
                                            {{ ($r['unit_diff'] > 0 ? '+' : '') . $r['unit_diff'] }}
                                        </span>
                                    @else
                                        <span class="text-success"><i class="fas fa-check"></i> 0</span>
                                    @endif
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($r['status'] === 'OK')
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                        <i class="fas fa-circle-check me-1"></i>Consistent
                                    </span>
                                @elseif($r['status'] === 'LEDGER_DRIFT')
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">
                                        <i class="fas fa-triangle-exclamation me-1"></i>Ledger Drift
                                    </span>
                                @elseif($r['status'] === 'UNIT_MISMATCH')
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1">
                                        <i class="fas fa-circle-exclamation me-1"></i>Unit Mismatch
                                    </span>
                                @else
                                    <span class="badge bg-danger text-white px-2 py-1">
                                        <i class="fas fa-triangle-exclamation me-1"></i>Drift &amp; Mismatch
                                    </span>
                                @endif
                            </td>
                            <td class="text-end pe-3">
                                @if($r['has_ledger_drift'] || $r['has_unit_mismatch'])
                                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#repairModal{{ $idx }}">
                                        <i class="fas fa-wrench me-1"></i> Reconcile
                                    </button>

                                    <!-- Repair Modal -->
                                    <div class="modal fade" id="repairModal{{ $idx }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content text-start">
                                                <form method="POST" action="{{ route('store-manager.inventory.reconciliation.repair') }}">
                                                    @csrf
                                                    <input type="hidden" name="inventory_id" value="{{ $r['inventory']->id }}">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">
                                                            <i class="fas fa-scale-balanced me-2 text-primary"></i>Reconcile Stock Discrepancy
                                                        </h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="p-3 bg-light rounded mb-3">
                                                            <div class="fw-bold fs-6">{{ $r['product']->name }}</div>
                                                            <div class="small text-muted">Store: {{ $r['store']->name }} | SKU: {{ $r['product']->sku }}</div>
                                                            <hr class="my-2">
                                                            <div class="row text-center">
                                                                <div class="col-4">
                                                                    <div class="small text-muted">On-Hand</div>
                                                                    <div class="fw-bold">{{ number_format($r['on_hand'], 3) }}</div>
                                                                </div>
                                                                <div class="col-4">
                                                                    <div class="small text-muted">Ledger Sum</div>
                                                                    <div class="fw-bold font-monospace">{{ number_format($r['ledger_sum'], 3) }}</div>
                                                                </div>
                                                                <div class="col-4">
                                                                    <div class="small text-muted">FA Units</div>
                                                                    <div class="fw-bold">{{ $r['active_units'] !== null ? $r['active_units'] : 'N/A' }}</div>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold">Select Reconciliation Action *</label>
                                                            @if($r['has_ledger_drift'])
                                                                <div class="form-check mb-2">
                                                                    <input class="form-check-input" type="radio" name="action" id="actLedger{{ $idx }}" value="balance_ledger" checked>
                                                                    <label class="form-check-label" for="actLedger{{ $idx }}">
                                                                        <strong>Balance Movement Ledger:</strong> add balancing movement row ({{ ($r['ledger_diff'] > 0 ? '+' : '') . number_format($r['ledger_diff'], 3) }}) to match On-Hand stock ({{ number_format($r['on_hand'], 3) }})
                                                                    </label>
                                                                </div>
                                                            @endif

                                                            @if($r['has_unit_mismatch'])
                                                                <div class="form-check mb-2">
                                                                    <input class="form-check-input" type="radio" name="action" id="actUnits{{ $idx }}" value="sync_units" {{ !$r['has_ledger_drift'] ? 'checked' : '' }}>
                                                                    <label class="form-check-label" for="actUnits{{ $idx }}">
                                                                        <strong>Synchronize Fixed Asset Units:</strong> {{ $r['unit_diff'] > 0 ? 'generate ' . $r['unit_diff'] . ' missing units flagged "Details incomplete"' : 'mark ' . abs($r['unit_diff']) . ' unassigned units as disposed' }} to match Inventory On-Hand ({{ number_format($r['on_hand'], 3) }})
                                                                    </label>
                                                                </div>
                                                            @endif

                                                            <div class="form-check mb-2">
                                                                <input class="form-check-input" type="radio" name="action" id="actCustom{{ $idx }}" value="custom_stock">
                                                                <label class="form-check-label" for="actCustom{{ $idx }}">
                                                                    <strong>Set Verified Physical Count:</strong> enter exact verified stock count
                                                                </label>
                                                            </div>
                                                        </div>

                                                        <div class="mb-3" id="customCountDiv{{ $idx }}">
                                                            <label class="form-label">Verified Physical Stock Count</label>
                                                            <input type="number" step="0.001" min="0" name="custom_count" class="form-control" placeholder="e.g. {{ $r['on_hand'] }}">
                                                            <small class="text-muted">Will record an explicit audit reconciliation movement with the difference.</small>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-primary">
                                                            <i class="fas fa-check me-1"></i> Apply Reconciliation
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <span class="text-success small fw-semibold">
                                        <i class="fas fa-check-circle me-1"></i>Verified
                                    </span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="11" class="text-center py-4 text-muted">
                                <i class="fas fa-check-circle text-success fa-2x mb-2 d-block"></i>
                                No inventory discrepancies found for the selected filter!
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
