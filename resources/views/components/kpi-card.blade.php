@props([
    'label',
    'value',
    'delta' => null,
    'trend' => null, // 'up', 'down', 'neutral'
    'icon' => null,
    'subtext' => null,
    'theme' => 'neutral', // 'primary', 'success', 'warning', 'danger', 'neutral'
])

@php
    $themeStyles = [
        'primary' => ['text' => 'text-primary', 'bg' => 'var(--wc-primary-bg)', 'border' => 'var(--wc-primary)'],
        'success' => ['text' => 'text-success', 'bg' => 'var(--wc-success-bg)', 'border' => 'var(--wc-success)'],
        'warning' => ['text' => 'text-warning', 'bg' => 'var(--wc-warning-bg)', 'border' => 'var(--wc-warning)'],
        'danger'  => ['text' => 'text-danger',  'bg' => 'var(--wc-danger-bg)',  'border' => 'var(--wc-danger)'],
        'neutral' => ['text' => 'text-secondary', 'bg' => 'var(--wc-surface-subtle)', 'border' => 'var(--wc-border)'],
    ];
    $currentTheme = $themeStyles[$theme] ?? $themeStyles['neutral'];
@endphp

<div {{ $attributes->merge(['class' => 'wc-card p-3 p-md-4 h-100 position-relative']) }}>
    <div class="d-flex justify-content-between align-items-start mb-2">
        <span class="text-uppercase fw-semibold text-muted" style="font-size: var(--wc-text-xs); letter-spacing: 0.05em;">
            {{ $label }}
        </span>
        @if($icon)
            <div class="rounded p-2 d-flex align-items-center justify-content-center" 
                 style="width: 36px; height: 36px; background-color: {{ $currentTheme['bg'] }};">
                <i class="{{ $icon }} {{ $currentTheme['text'] }}" style="font-size: 1rem;"></i>
            </div>
        @endif
    </div>

    <div class="d-flex align-items-baseline gap-2 mt-1">
        <div class="fw-bold text-dark font-monospace" style="font-size: clamp(1.3rem, 3vw, 1.75rem); letter-spacing: -0.02em;">
            {{ $value }}
        </div>

        @if($delta)
            @php
                $isPositive = in_array(strtolower((string)$trend), ['up', 'positive', 'good']);
                $isNegative = in_array(strtolower((string)$trend), ['down', 'negative', 'critical']);
                $deltaColor = $isPositive ? 'var(--wc-success-text)' : ($isNegative ? 'var(--wc-danger-text)' : 'var(--wc-text-muted)');
                $deltaBg = $isPositive ? 'var(--wc-success-bg)' : ($isNegative ? 'var(--wc-danger-bg)' : 'var(--wc-surface-subtle)');
                $deltaIcon = $isPositive ? 'fa-arrow-trend-up' : ($isNegative ? 'fa-arrow-trend-down' : 'fa-minus');
            @endphp
            <span class="badge rounded-pill px-2 py-0.5 d-inline-flex align-items-center gap-1 font-monospace"
                  style="background-color: {{ $deltaBg }}; color: {{ $deltaColor }}; font-size: 0.72rem; font-weight: 600;">
                <i class="fa-solid {{ $deltaIcon }}" style="font-size: 0.65rem;"></i>
                {{ $delta }}
            </span>
        @endif
    </div>

    @if($subtext)
        <div class="text-muted mt-2" style="font-size: var(--wc-text-xs);">
            {{ $subtext }}
        </div>
    @endif
</div>
