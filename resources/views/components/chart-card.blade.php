@props([
    'title'    => null,
    'chartId'  => null,
    'subtitle' => null,
    'height'   => '280px',
])

<div {{ $attributes->merge(['class' => 'wc-card p-3 p-md-4 h-100']) }}>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h6 class="mb-0 fw-bold text-dark" style="font-size: var(--wc-text-sm);">
                {{ $title }}
            </h6>
            @if($subtitle)
                <p class="text-muted small mb-0" style="font-size: var(--wc-text-xs);">
                    {{ $subtitle }}
                </p>
            @endif
        </div>
        @if(isset($actions))
            <div class="d-flex align-items-center gap-2">
                {{ $actions }}
            </div>
        @endif
    </div>

    <div class="position-relative w-100" style="height: {{ $height }}; max-height: 380px;">
        <canvas id="{{ $chartId }}"></canvas>
    </div>

    @if(isset($legend))
        <div class="mt-3 pt-2 border-top d-flex flex-wrap align-items-center justify-content-center gap-3" style="font-size: var(--wc-text-xs);">
            {{ $legend }}
        </div>
    @endif
</div>
