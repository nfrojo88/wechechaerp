@extends('layouts.app')

@section('title', 'Store Inventory Reconciliation & Audit — Store Manager')

@section('content')
@push('styles')
@include('layouts._store_mobile')
<style>
    @media print {
        .wc-no-print { display: none !important; }
        .card { border: 1px solid #e2e8f0 !important; box-shadow: none !important; }
    }
</style>
@endpush

<div class="container-fluid px-3 px-md-4 py-3">
    <x-print-header title="Store Inventory Reconciliation & Physical Audit" subtitle="Three-Way Stock Consistency Verification: Store vs Ledger vs Fixed Assets" />

    {{-- Page Header --}}
    <x-page-header 
        title="Store Inventory Reconciliation & Audit" 
        subtitle="Three-way consistency check across Physical Store Inventory, Movement Ledger, and Fixed Asset Unit records">
        <x-slot:actions>
            <a href="{{ route('store-manager.inventory.all') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-xs">
                <i class="fa-solid fa-arrow-left me-1"></i>Back to Inventory
            </a>
            <a href="{{ route('inventory.all-movements') }}" class="btn btn-outline-info btn-sm rounded-pill px-3 shadow-xs">
                <i class="fa-solid fa-list-check me-1"></i>Movement Ledger
            </a>
            <x-export-menu :pdfUrl="Route::has('store-manager.inventory.export-pdf') ? route('store-manager.inventory.export-pdf', request()->query()) : null" :print="true" label="Print / Export" />
        </x-slot:actions>
    </x-page-header>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm border-0 d-flex align-items-center mb-4" role="alert">
            <i class="fa-solid fa-circle-check fs-5 me-2"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error') || $errors->any())
        <div class="alert alert-danger alert-dismissible fade show rounded-4 shadow-sm border-0 d-flex align-items-center mb-4" role="alert">
            <i class="fa-solid fa-triangle-exclamation fs-5 me-2"></i>
            <div>{{ session('error') ?? $errors->first() }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- KPI Cards Row --}}
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-sm-6">
            <x-kpi-card 
                label="Total Audited Items" 
                value="{{ number_format($kpis['total']) }}" 
                icon="fa-solid fa-boxes-stacked" 
                variant="primary" 
                helpText="Active inventory records checked" />
        </div>
        <div class="col-xl-3 col-sm-6">
            <x-kpi-card 
                label="100% Consistent" 
                value="{{ number_format($kpis['consistent']) }}" 
                icon="fa-solid fa-circle-check" 
                variant="success" 
                helpText="On-Hand = Ledger = FA Units" />
        </div>
        <div class="col-xl-3 col-sm-6">
            <x-kpi-card 
                label="Ledger Drift Discrepancy" 
                value="{{ number_format($kpis['ledger_drift']) }}" 
                icon="fa-solid fa-receipt" 
                variant="{{ $kpis['ledger_drift'] > 0 ? 'danger' : 'success' }}" 
                helpText="Movement sum ≠ On-hand balance" />
        </div>
        <div class="col-xl-3 col-sm-6">
            <x-kpi-card 
                label="FA Unit Count Mismatch" 
                value="{{ number_format($kpis['unit_mismatch']) }}" 
                icon="fa-solid fa-tag" 
                variant="{{ $kpis['unit_mismatch'] > 0 ? 'warning' : 'success' }}" 
                helpText="Serial tags ≠ Stock balance" />
        </div>
    </div>

    {{-- Filter Bar --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white wc-no-print">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('store-manager.inventory.reconciliation') }}" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text" name="search" class="form-control form-control-sm border-start-0 ps-0" placeholder="Search product name, SKU, or code..." value="{{ $search }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="store_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Warehouses &amp; Stores</option>
                        @foreach($stores as $st)
                            <option value="{{ $st->id }}" {{ (string)$storeId === (string)$st->id ? 'selected' : '' }}>
                                {{ $st->name }} ({{ ucfirst($st->type) }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="filter" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="all" {{ $filter === 'all' ? 'selected' : '' }}>All Audit Records</option>
                        <option value="drift" {{ $filter === 'drift' ? 'selected' : '' }}>Ledger Drift Only</option>
                        <option value="mismatch" {{ $filter === 'mismatch' ? 'selected' : '' }}>Fixed Asset Mismatch Only</option>
                        <option value="consistent" {{ $filter === 'consistent' ? 'selected' : '' }}>Consistent Only</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-1 justify-content-end">
                    <button type="submit" class="btn btn-primary btn-sm rounded-pill px-3 fw-bold w-100">
                        <i class="fa-solid fa-filter me-1"></i> Filter
                    </button>
                    @if($search || $storeId || $filter !== 'all')
                    <a href="{{ route('store-manager.inventory.reconciliation') }}" class="btn btn-light border btn-sm rounded-pill px-2 text-muted" title="Reset Filters">
                        <i class="fa-solid fa-rotate-left"></i>
                    </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    {{-- Reconciliation Table --}}
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold" style="color: var(--brand-900);">
                <i class="fa-solid fa-scale-balanced text-primary me-2"></i>Physical Inventory Audit Ledger
            </h6>
            <span class="badge bg-light text-dark border px-3 py-1 font-monospace">
                {{ count($report) }} Discrepancy Checks
            </span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 wc-table-mobile-cards">
                    <thead class="table-light text-uppercase small text-muted" style="letter-spacing: 0.5px;">
                        <tr>
                            <th class="ps-4 py-3">Store Location</th>
                            <th class="py-3">SKU / Product</th>
                            <th class="py-3 text-center">Category</th>
                            <th class="py-3 text-end">On-Hand</th>
                            <th class="py-3 text-end">Reserved</th>
                            <th class="py-3 text-end">Ledger Sum</th>
                            <th class="py-3 text-end">Ledger Diff</th>
                            <th class="py-3 text-end">FA Units</th>
                            <th class="py-3 text-end">FA Diff</th>
                            <th class="py-3 text-center">Status</th>
                            <th class="pe-4 py-3 text-end">Audit Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($report as $idx => $r)
                        <tr>
                            <td class="ps-4 py-3" data-label="Store">
                                <span class="fw-semibold text-dark">{{ $r['store']->name ?? "Store #{$r['inventory']->store_id}" }}</span>
                            </td>
                            <td class="py-3" data-label="Product">
                                <div class="fw-bold" style="color: var(--brand-900);">{{ $r['product']->name ?? "Product #{$r['inventory']->product_id}" }}</div>
                                <code class="small text-muted">{{ $r['product']->sku ?? '—' }}</code>
                            </td>
                            <td class="py-3 text-center" data-label="Category">
                                @if($r['is_fa'])
                                    <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2.5 py-1">
                                        <i class="fa-solid fa-computer me-1"></i>Fixed Asset
                                    </span>
                                @else
                                    <span class="badge bg-light text-dark border rounded-pill px-2.5 py-1">Consumable</span>
                                @endif
                            </td>
                            <td class="py-3 text-end font-tabular fw-bold text-dark fs-6" data-label="On-Hand">
                                {{ number_format($r['on_hand'], 3) }}
                            </td>
                            <td class="py-3 text-end font-tabular text-muted" data-label="Reserved">
                                {{ number_format($r['reserved'], 3) }}
                            </td>
                            <td class="py-3 text-end font-tabular text-dark" data-label="Ledger Sum">
                                {{ number_format($r['ledger_sum'], 3) }}
                            </td>
                            <td class="py-3 text-end font-tabular" data-label="Ledger Diff">
                                @if($r['has_ledger_drift'])
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-0.5 rounded-pill fw-bold">
                                        {{ ($r['ledger_diff'] > 0 ? '+' : '') . number_format($r['ledger_diff'], 3) }}
                                    </span>
                                @else
                                    <span class="text-success"><i class="fa-solid fa-check me-1"></i>0.000</span>
                                @endif
                            </td>
                            <td class="py-3 text-end font-tabular" data-label="FA Units">
                                @if($r['is_fa'])
                                    <span class="fw-bold text-dark">{{ $r['active_units'] !== null ? $r['active_units'] : '0' }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="py-3 text-end font-tabular" data-label="FA Diff">
                                @if($r['is_fa'] && $r['unit_diff'] !== null)
                                    @if($r['has_unit_mismatch'])
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-0.5 rounded-pill fw-bold">
                                            {{ ($r['unit_diff'] > 0 ? '+' : '') . $r['unit_diff'] }}
                                        </span>
                                    @else
                                        <span class="text-success"><i class="fa-solid fa-check me-1"></i>0</span>
                                    @endif
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="py-3 text-center" data-label="Status">
                                @if($r['status'] === 'OK')
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1 rounded-pill">
                                        <i class="fa-solid fa-circle-check me-1"></i>Consistent
                                    </span>
                                @elseif($r['status'] === 'LEDGER_DRIFT')
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2.5 py-1 rounded-pill">
                                        <i class="fa-solid fa-triangle-exclamation me-1"></i>Ledger Drift
                                    </span>
                                @elseif($r['status'] === 'UNIT_MISMATCH')
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2.5 py-1 rounded-pill">
                                        <i class="fa-solid fa-circle-exclamation me-1"></i>Unit Mismatch
                                    </span>
                                @else
                                    <span class="badge bg-danger text-white px-2.5 py-1 rounded-pill">
                                        <i class="fa-solid fa-triangle-exclamation me-1"></i>Drift &amp; Mismatch
                                    </span>
                                @endif
                            </td>
                            <td class="pe-4 py-3 text-end" data-label="Action">
                                @if($r['has_ledger_drift'] || $r['has_unit_mismatch'])
                                    <button class="btn btn-sm btn-primary rounded-pill px-3 shadow-xs" data-bs-toggle="modal" data-bs-target="#repairModal{{ $idx }}">
                                        <i class="fa-solid fa-wrench me-1"></i>Reconcile
                                    </button>
                                @else
                                    <span class="text-success small fw-semibold">
                                        <i class="fa-solid fa-circle-check me-1"></i>Verified
                                    </span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="11" class="text-center py-5">
                                <x-empty-state 
                                    icon="fa-solid fa-circle-check" 
                                    title="No Discrepancies Found" 
                                    message="Physical store inventory matches the movement ledger and fixed asset serialized units perfectly." />
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Official Print Signature Block --}}
    <x-print-signatures 
        preparedTitle="Audited By" 
        preparedRole="Inventory Auditor / Store Manager" 
        checkedTitle="Verified By" 
        checkedRole="Operations Coordinator" 
        approvedTitle="Approved By" 
        approvedRole="General Manager" />
</div>

{{-- Discrepancy Reconciliation Modals --}}
@foreach($report as $idx => $r)
@if($r['has_ledger_drift'] || $r['has_unit_mismatch'])
<div class="modal fade" id="repairModal{{ $idx }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg text-start">
            <form method="POST" action="{{ route('store-manager.inventory.reconciliation.repair') }}">
                @csrf
                <input type="hidden" name="inventory_id" value="{{ $r['inventory']->id }}">
                <div class="modal-header bg-primary text-white border-0 py-3 px-4">
                    <h5 class="modal-title fw-bold">
                        <i class="fa-solid fa-scale-balanced me-2"></i>Reconcile Stock Discrepancy
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 bg-white">
                    <div class="p-3 bg-light rounded-3 mb-3 border">
                        <div class="fw-bold fs-6" style="color: var(--brand-900);">{{ $r['product']->name }}</div>
                        <div class="small text-muted">Store: {{ $r['store']->name }} | SKU: {{ $r['product']->sku ?? 'N/A' }}</div>
                        <hr class="my-2">
                        <div class="row text-center font-tabular">
                            <div class="col-4">
                                <div class="small text-muted">On-Hand</div>
                                <div class="fw-bold text-dark fs-6">{{ number_format($r['on_hand'], 3) }}</div>
                            </div>
                            <div class="col-4">
                                <div class="small text-muted">Ledger Sum</div>
                                <div class="fw-bold text-dark fs-6">{{ number_format($r['ledger_sum'], 3) }}</div>
                            </div>
                            <div class="col-4">
                                <div class="small text-muted">FA Units</div>
                                <div class="fw-bold text-dark fs-6">{{ $r['active_units'] !== null ? $r['active_units'] : 'N/A' }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase text-muted">Select Reconciliation Action *</label>
                        @if($r['has_ledger_drift'])
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="action" id="actLedger{{ $idx }}" value="balance_ledger" checked>
                                <label class="form-check-label small" for="actLedger{{ $idx }}">
                                    <strong>Balance Movement Ledger:</strong> add balancing movement row ({{ ($r['ledger_diff'] > 0 ? '+' : '') . number_format($r['ledger_diff'], 3) }}) to match On-Hand stock ({{ number_format($r['on_hand'], 3) }})
                                </label>
                            </div>
                        @endif

                        @if($r['has_unit_mismatch'])
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="action" id="actUnits{{ $idx }}" value="sync_units" {{ !$r['has_ledger_drift'] ? 'checked' : '' }}>
                                <label class="form-check-label small" for="actUnits{{ $idx }}">
                                    <strong>Synchronize Fixed Asset Units:</strong> {{ $r['unit_diff'] > 0 ? 'generate ' . $r['unit_diff'] . ' missing units flagged "Details incomplete"' : 'mark ' . abs($r['unit_diff']) . ' unassigned units as disposed' }} to match Inventory On-Hand ({{ number_format($r['on_hand'], 3) }})
                                </label>
                            </div>
                        @endif

                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="action" id="actCustom{{ $idx }}" value="custom_stock">
                            <label class="form-check-label small" for="actCustom{{ $idx }}">
                                <strong>Set Verified Physical Count:</strong> enter exact verified stock count
                            </label>
                        </div>
                    </div>

                    <div class="mb-3" id="customCountDiv{{ $idx }}">
                        <label class="form-label fw-bold small text-uppercase text-muted">Verified Physical Stock Count</label>
                        <input type="number" step="0.001" min="0" name="custom_count" class="form-control" placeholder="e.g. {{ $r['on_hand'] }}">
                        <small class="text-muted">Will record an explicit audit reconciliation movement with the difference.</small>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 py-3 px-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
                        <i class="fa-solid fa-check me-1"></i> Apply Reconciliation
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endforeach

@endsection
