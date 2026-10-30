@props([
    'icon' => 'fa-inbox',
    'title' => 'No records found',
    'description' => 'There are currently no records available in this view.',
    'actionUrl' => null,
    'actionText' => null,
    'actionIcon' => 'fa-plus',
])

<div {{ $attributes->merge(['class' => 'wc-empty-state text-center py-5 px-3']) }}>
    <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3" 
         style="width: 64px; height: 64px; background-color: var(--wc-surface-subtle); color: var(--wc-text-subtle);">
        <i class="fa-solid {{ $icon }} fs-3"></i>
    </div>
    
    <h5 class="fw-bold text-dark mb-1" style="font-size: var(--wc-text-base);">
        {{ $title }}
    </h5>
    
    <p class="text-muted small mx-auto mb-4" style="max-width: 360px; font-size: var(--wc-text-sm);">
        {{ $description }}
    </p>

    @if($actionUrl && $actionText)
        <a href="{{ $actionUrl }}" class="wc-btn-touch wc-btn-primary">
            <i class="fa-solid {{ $actionIcon }}"></i>
            <span>{{ $actionText }}</span>
        </a>
    @endif
</div>
