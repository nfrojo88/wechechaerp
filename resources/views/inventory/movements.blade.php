@extends('layouts.app')

@section('title', 'Inventory Movement History — ' . $inventory->product->name)

@section('content')
<div class="container-fluid px-3 px-md-4 py-3">

    {{-- Page Header --}}
    <x-page-header 
        title="Stock Movement Ledger" 
        subtitle="Chronological audit trail of material receipts, dispatches, jobsite transfers, and inventory adjustments">
        <x-slot name="actions">
            <a href="{{ route('inventory.show', $inventory) }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-xs">
                <i class="fa-solid fa-arrow-left me-1"></i>Back to Inventory Card
            </a>
            <button class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-xs" onclick="window.print()">
                <i class="fa-solid fa-print me-1"></i>Print Ledger
            </button>
        </x-slot>
    </x-page-header>

    {{-- Product & Stock Summary Banner --}}
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-sm-6">
            <x-kpi-card 
                label="Product / Item" 
                value="{{ $inventory->product->name }}" 
                icon="fa-solid fa-box" 
                variant="primary" 
                helpText="SKU: {{ $inventory->product->sku ?? 'N/A' }}" />
        </div>
        <div class="col-xl-3 col-sm-6">
            <x-kpi-card 
                label="Current Stock Level" 
                value="{{ number_format($inventory->quantity, 3) }} {{ $inventory->product->unit_of_measure ?? 'Units' }}" 
                icon="fa-solid fa-boxes-stacked" 
                variant="success" 
                helpText="Available on-hand in warehouse" />
        </div>
        <div class="col-xl-3 col-sm-6">
            <x-kpi-card 
                label="Assigned Store" 
                value="{{ $inventory->store->name }}" 
                icon="fa-solid fa-warehouse" 
                variant="info" 
                helpText="{{ ucfirst($inventory->store->type ?? 'Store') }}" />
        </div>
        <div class="col-xl-3 col-sm-6">
            <x-kpi-card 
                label="Total Movements" 
                value="{{ number_format($movements->total()) }}" 
                icon="fa-solid fa-receipt" 
                variant="warning" 
                helpText="Recorded ledger entries" />
        </div>
    </div>

    {{-- Movement Ledger Table --}}
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold" style="color: var(--brand-900);">
                <i class="fa-solid fa-clock-rotate-left text-primary me-2"></i>Movement Audit Trail
            </h6>
            <span class="badge bg-light text-dark border px-3 py-1 font-monospace">
                Showing {{ $movements->count() }} of {{ $movements->total() }} records
            </span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 wc-table-mobile-cards">
                    <thead class="table-light text-uppercase small text-muted" style="letter-spacing: 0.5px;">
                        <tr>
                            <th class="ps-4 py-3">Date &amp; Time</th>
                            <th class="py-3 text-center">Type</th>
                            <th class="py-3 text-end">Quantity</th>
                            <th class="py-3 text-end">Balance Before</th>
                            <th class="py-3 text-end">Balance After</th>
                            <th class="py-3">Performed By</th>
                            <th class="py-3">Reference</th>
                            <th class="pe-4 py-3">Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($movements as $mov)
                        @php
                            $badge = match($mov->type) {
                                'in', 'purchase_intake', 'grn', 'fa_register' => 'success',
                                'out', 'dispatch', 'issue', 'fa_damaged' => 'danger',
                                'transfer', 'transfer_in', 'transfer_out' => 'info',
                                'adjustment' => 'warning',
                                'reserve', 'fa_assign' => 'secondary',
                                'release', 'fa_return' => 'primary',
                                default => 'secondary'
                            };
                            $qty = (float) $mov->quantity;
                            $isPositive = $qty > 0;
                            $isNegative = $qty < 0;
                        @endphp
                        <tr>
                            <td class="ps-4 py-3" data-label="Date & Time">
                                <div class="fw-semibold text-dark font-tabular">{{ $mov->created_at->format('d M Y') }}</div>
                                <div class="text-muted small font-tabular">{{ $mov->created_at->format('H:i:s') }}</div>
                            </td>
                            <td class="py-3 text-center" data-label="Type">
                                <span class="badge bg-{{ $badge }}-subtle text-{{ $badge }} border border-{{ $badge }}-subtle rounded-pill px-2.5 py-1 font-monospace text-uppercase" style="font-size:0.75rem;">
                                    {{ strtoupper(str_replace('_', ' ', $mov->type)) }}
                                </span>
                            </td>
                            <td class="py-3 text-end font-tabular fw-bold fs-6 {{ $isPositive ? 'text-success' : ($isNegative ? 'text-danger' : 'text-secondary') }}" data-label="Quantity">
                                {{ $isPositive ? '+' : ($isNegative ? '-' : '') }}{{ number_format(abs($qty), 3) }}
                            </td>
                            <td class="py-3 text-end font-tabular text-muted" data-label="Balance Before">
                                {{ $mov->balance_before !== null ? number_format((float)$mov->balance_before, 3) : '—' }}
                            </td>
                            <td class="py-3 text-end font-tabular fw-bold text-dark" data-label="Balance After">
                                {{ $mov->balance_after !== null ? number_format((float)$mov->balance_after, 3) : '—' }}
                            </td>
                            <td class="py-3" data-label="Performed By">
                                <div class="fw-semibold text-dark">{{ $mov->performer?->name ?? 'System' }}</div>
                            </td>
                            <td class="py-3" data-label="Reference">
                                @if($mov->reference_type && $mov->reference_id)
                                    <span class="badge bg-light text-dark border font-monospace" style="font-size:0.75rem;">
                                        {{ class_basename($mov->reference_type) }} #{{ $mov->reference_id }}
                                    </span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="pe-4 py-3 text-wrap" style="max-width: 250px;" data-label="Remarks">
                                <span class="text-muted small">{{ $mov->remarks ?? '—' }}</span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <x-empty-state 
                                    icon="fa-solid fa-clock-rotate-left" 
                                    title="No Movements Recorded Yet" 
                                    message="No stock transaction history found for this product inventory ledger." />
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($movements->hasPages())
        <div class="card-footer bg-white border-top py-3 px-4">
            {{ $movements->links() }}
        </div>
        @endif
    </div>

</div>
@endsection
