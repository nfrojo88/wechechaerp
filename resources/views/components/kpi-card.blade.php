@props([
    'label'       => null,   // primary label / card title
    'title'       => null,   // alias for label
    'value'       => null,
    'delta'       => null,
    'changeLabel' => null,   // alias for delta
    'trend'       => null,   // 'up', 'down', 'neutral'
    'icon'        => null,
    'subtext'     => null,   // primary sub-text
    'subtitle'    => null,   // alias for subtext
    'helpText'    => null,   // alias for subtext
    'theme'       => null,   // 'primary', 'success', 'warning', 'danger', 'neutral'
    'tone'        => null,   // alias for theme
    'color'       => null,   // alias for theme
    'variant'     => null,   // alias for theme
    'badge'       => null,
])

@php
    // Resolve aliases
    $resolvedLabel   = $label   ?? $title   ?? '';
    $resolvedValue   = $value   ?? '';
    $resolvedDelta   = $delta   ?? $changeLabel ?? null;
    $resolvedSubtext = $subtext ?? $subtitle ?? $helpText ?? null;
    $resolvedTheme   = $theme   ?? $tone    ?? $color    ?? $variant ?? 'neutral';

    // Theme map — accepts both new tokens and Bootstrap-style names
    $themeMap = [
        'primary' => ['text' => 'text-primary',   'bg' => 'var(--wc-primary-bg)',       'border' => 'var(--wc-primary)'],
        'success' => ['text' => 'text-success',   'bg' => 'var(--wc-success-bg)',       'border' => 'var(--wc-success)'],
        'warning' => ['text' => 'text-warning',   'bg' => 'var(--wc-warning-bg)',       'border' => 'var(--wc-warning)'],
        'danger'  => ['text' => 'text-danger',    'bg' => 'var(--wc-danger-bg)',        'border' => 'var(--wc-danger)'],
        'info'    => ['text' => 'text-info',      'bg' => 'rgba(13,202,240,.08)',       'border' => '#0dcaf0'],
        'purple'  => ['text' => 'text-purple',    'bg' => 'rgba(111,66,193,.08)',       'border' => '#6f42c1'],
        'dark'    => ['text' => 'text-dark',      'bg' => 'rgba(33,37,41,.06)',         'border' => '#343a40'],
        'neutral' => ['text' => 'text-secondary', 'bg' => 'var(--wc-surface-subtle)',  'border' => 'var(--wc-border)'],
    ];
    $currentTheme = $themeMap[$resolvedTheme] ?? $themeMap['neutral'];
@endphp

<div {{ $attributes->merge(['class' => 'wc-card p-3 p-md-4 h-100 position-relative']) }}>
    <div class="d-flex justify-content-between align-items-start mb-2">
        <span class="text-uppercase fw-semibold text-muted" style="font-size: var(--wc-text-xs); letter-spacing: 0.05em;">
            {{ $resolvedLabel }}
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
            {{ $resolvedValue }}
        </div>

        @if($resolvedDelta)
            @php
                $trendVal   = (string)($trend ?? '');
                $isPositive = in_array(strtolower($trendVal), ['up', 'positive', 'good']);
                $isNegative = in_array(strtolower($trendVal), ['down', 'negative', 'critical']);
                $deltaColor = $isPositive ? 'var(--wc-success-text)' : ($isNegative ? 'var(--wc-danger-text)' : 'var(--wc-text-muted)');
                $deltaBg    = $isPositive ? 'var(--wc-success-bg)'   : ($isNegative ? 'var(--wc-danger-bg)'  : 'var(--wc-surface-subtle)');
                $deltaIcon  = $isPositive ? 'fa-arrow-trend-up'      : ($isNegative ? 'fa-arrow-trend-down' : 'fa-minus');
            @endphp
            <span class="badge rounded-pill px-2 d-inline-flex align-items-center gap-1 font-monospace"
                  style="background-color: {{ $deltaBg }}; color: {{ $deltaColor }}; font-size: 0.72rem; font-weight: 600;">
                <i class="fa-solid {{ $deltaIcon }}" style="font-size: 0.65rem;"></i>
                {{ $resolvedDelta }}
            </span>
        @endif
    </div>

    @if($resolvedSubtext)
        <div class="text-muted mt-2" style="font-size: var(--wc-text-xs);">
            {{ $resolvedSubtext }}
        </div>
    @endif

    @if($badge)
        <span class="position-absolute top-0 end-0 m-2 badge rounded-pill bg-secondary bg-opacity-10 text-secondary" style="font-size: 0.65rem;">
            {{ $badge }}
        </span>
    @endif
</div>
