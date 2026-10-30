@props([
    'headers' => [], // Array of header labels or ['label' => '...', 'align' => 'right|left|center', 'class' => '...']
    'scrollable' => false, // Set true for extra-wide financial tables like General Ledger, Expense Hub 22-cols
    'pagination' => null, // Paginator instance
    'emptyMessage' => 'No records found matching your criteria.',
])

<div {{ $attributes->merge(['class' => 'wc-data-table-wrapper']) }}>
    @if($scrollable)
        {{-- Swipe hint banner for wide tables on small screens --}}
        <div class="d-flex align-items-center justify-content-between p-2 mb-2 bg-light rounded text-muted small d-md-none" style="font-size: 0.75rem;">
            <span><i class="fa-solid fa-arrows-left-right me-1 text-primary"></i> Scroll horizontally to view all columns</span>
            <span class="badge bg-secondary">Wide Table</span>
        </div>
    @endif

    <div class="wc-card">
        <div class="table-responsive {{ $scrollable ? 'wc-wide-table-container' : '' }}" style="max-height: 75vh;">
            <table class="table table-hover align-middle mb-0 {{ !$scrollable ? 'wc-table-mobile-cards' : '' }}">
                <thead class="bg-light sticky-top" style="z-index: 2;">
                    <tr>
                        @foreach($headers as $header)
                            @php
                                $label = is_array($header) ? ($header['label'] ?? '') : $header;
                                $align = is_array($header) ? ($header['align'] ?? 'left') : 'left';
                                $thClass = is_array($header) ? ($header['class'] ?? '') : '';
                                $alignClass = $align === 'right' ? 'text-end wc-num' : ($align === 'center' ? 'text-center' : 'text-start');
                            @endphp
                            <th class="py-3 px-3 fw-bold text-muted text-uppercase {{ $alignClass }} {{ $thClass }}" 
                                style="font-size: var(--wc-text-xs); letter-spacing: 0.05em; border-bottom: 1px solid var(--wc-border); background-color: var(--wc-surface-subtle);">
                                {{ $label }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100" style="font-size: var(--wc-text-sm);">
                    {{ $slot }}
                </tbody>
            </table>
        </div>

        @if($pagination && method_exists($pagination, 'hasPages') && $pagination->hasPages())
            <div class="p-3 border-top d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2 bg-white" style="border-color: var(--wc-border);">
                <div class="text-muted small">
                    Showing <span class="fw-semibold text-dark">{{ $pagination->firstItem() ?? 0 }}</span> to <span class="fw-semibold text-dark">{{ $pagination->lastItem() ?? 0 }}</span> of <span class="fw-semibold text-dark">{{ $pagination->total() ?? 0 }}</span> results
                </div>
                <div class="wc-pagination-wrapper">
                    {{ $pagination->onEachSide(1)->links('pagination::bootstrap-5') }}
                </div>
            </div>
        @endif
    </div>
</div>
