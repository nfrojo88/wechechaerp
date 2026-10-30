@props([
    'type' => 'table', // 'kpi', 'table', 'chart', 'list'
    'rows' => 4,
])

<div {{ $attributes->merge(['class' => 'wc-skeleton-wrapper w-100']) }}>
    <style>
        @keyframes wcShimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }
        .wc-skeleton-shimmer {
            background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
            background-size: 200% 100%;
            animation: wcShimmer 1.5s infinite linear;
            border-radius: var(--wc-radius-sm);
        }
    </style>

    @if($type === 'kpi')
        <div class="row g-3">
            @for($i = 0; $i < $rows; $i++)
                <div class="col-6 col-md-3">
                    <div class="wc-card p-3">
                        <div class="wc-skeleton-shimmer mb-2" style="height: 12px; width: 60%;"></div>
                        <div class="wc-skeleton-shimmer mb-2" style="height: 28px; width: 85%;"></div>
                        <div class="wc-skeleton-shimmer" style="height: 10px; width: 45%;"></div>
                    </div>
                </div>
            @endfor
        </div>

    @elseif($type === 'chart')
        <div class="wc-card p-4">
            <div class="d-flex justify-content-between mb-3">
                <div class="wc-skeleton-shimmer" style="height: 18px; width: 35%;"></div>
                <div class="wc-skeleton-shimmer" style="height: 18px; width: 15%;"></div>
            </div>
            <div class="wc-skeleton-shimmer w-100" style="height: 240px;"></div>
        </div>

    @else {{-- Table / List --}}
        <div class="wc-card p-3">
            <div class="wc-skeleton-shimmer mb-3" style="height: 38px; width: 100%;"></div>
            @for($i = 0; $i < $rows; $i++)
                <div class="d-flex align-items-center gap-3 py-2 border-bottom">
                    <div class="wc-skeleton-shimmer" style="height: 16px; width: 25%;"></div>
                    <div class="wc-skeleton-shimmer" style="height: 16px; width: 35%;"></div>
                    <div class="wc-skeleton-shimmer" style="height: 16px; width: 20%;"></div>
                    <div class="wc-skeleton-shimmer ms-auto" style="height: 16px; width: 10%;"></div>
                </div>
            @endfor
        </div>
    @endif
</div>
