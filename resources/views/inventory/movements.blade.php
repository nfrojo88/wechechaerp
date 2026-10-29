@extends('layouts.app')

@section('title', 'Inventory Movements')

@section('content')
<div class="d-flex align-items-center mb-4">
    <a href="{{ route('inventory.show', $inventory) }}" class="btn btn-sm btn-outline-secondary me-3">
        <i class="fa-solid fa-arrow-left"></i>
    </a>
    <h1 class="h3 mb-0 me-3">Movement History</h1>
    <span class="text-muted border-start ps-3">
        {{ $inventory->product->name }} &mdash; {{ $inventory->store->name }}
    </span>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date & Time</th>
                        <th>Type</th>
                        <th class="text-end">Quantity</th>
                        <th class="text-end">Balance Before</th>
                        <th class="text-end">Balance After</th>
                        <th>Performed By</th>
                        <th>Reference</th>
                        <th>Remarks</th>
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
                        <td>
                            <div class="fw-semibold">{{ $mov->created_at->format('d M Y') }}</div>
                            <div class="text-muted small">{{ $mov->created_at->format('H:i:s') }}</div>
                        </td>
                        <td>
                            <span class="badge bg-{{ $badge }}">{{ strtoupper($mov->type) }}</span>
                        </td>
                        <td class="text-end font-monospace fw-bold fs-6 {{ $isPositive ? 'text-success' : ($isNegative ? 'text-danger' : 'text-secondary') }}">
                            {{ $isPositive ? '+' : ($isNegative ? '-' : '') }}{{ number_format(abs($qty), 3) }}
                        </td>
                        <td class="text-end font-monospace text-muted">
                            {{ $mov->balance_before !== null ? number_format((float)$mov->balance_before, 3) : '—' }}
                        </td>
                        <td class="text-end font-monospace fw-semibold text-dark">
                            {{ $mov->balance_after !== null ? number_format((float)$mov->balance_after, 3) : '—' }}
                        </td>
                        <td>{{ $mov->performer?->name ?? 'System' }}</td>
                        <td>
                            @if($mov->reference_type && $mov->reference_id)
                                <code class="small text-muted">{{ class_basename($mov->reference_type) }} #{{ $mov->reference_id }}</code>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-wrap" style="max-width: 300px;">{{ $mov->remarks ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">No movements recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($movements->hasPages())
    <div class="card-footer bg-transparent">
        {{ $movements->links() }}
    </div>
    @endif
</div>
@endsection
