@props([
    'action' => request()->url(),
    'activeFiltersCount' => 0,
    'searchPlaceholder' => 'Search records...',
    'showSearch' => true,
    'searchValue' => request('search', ''),
])

@php
    $uniqueSheetId = 'filterSheet_' . \Illuminate\Support\Str::random(6);
@endphp

<div {{ $attributes->merge(['class' => 'wc-filter-bar mb-3']) }} x-data="{ openMobileSheet: false }">
    {{-- Desktop Inline Bar (≥768px) --}}
    <div class="d-none d-md-block">
        <form method="GET" action="{{ $action }}" class="wc-card p-3">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="d-flex flex-wrap align-items-center gap-2 flex-grow-1">
                    @if($showSearch)
                        <div class="position-relative" style="min-width: 220px; max-width: 320px;">
                            <i class="fa-solid fa-magnifying-glass position-absolute top-50 start-0 translate-middle-y ms-3 text-muted" style="font-size: 0.85rem;"></i>
                            <input type="text" name="search" value="{{ $searchValue }}" 
                                   class="form-control ps-5" 
                                   style="height: var(--wc-touch-target); font-size: var(--wc-text-sm); border-radius: var(--wc-radius-sm); border-color: var(--wc-border);"
                                   placeholder="{{ $searchPlaceholder }}">
                        </div>
                    @endif

                    {{-- Additional Desktop Slot Fields --}}
                    {{ $slot }}
                </div>

                <div class="d-flex align-items-center gap-2">
                    <button type="submit" class="wc-btn-touch wc-btn-primary">
                        <i class="fa-solid fa-filter"></i> Apply Filters
                    </button>
                    @if(request()->hasAny(['search', 'date_from', 'date_to', 'status', 'project_id', 'department']))
                        <a href="{{ $action }}" class="wc-btn-touch wc-btn-outline" title="Clear all filters">
                            <i class="fa-solid fa-xmark"></i> Reset
                        </a>
                    @endif
                </div>
            </div>
        </form>
    </div>

    {{-- Mobile Filters Bar (<768px) --}}
    <div class="d-block d-md-none">
        <div class="d-flex align-items-center gap-2">
            @if($showSearch)
                <form method="GET" action="{{ $action }}" class="flex-grow-1 m-0">
                    <div class="position-relative">
                        <i class="fa-solid fa-magnifying-glass position-absolute top-50 start-0 translate-middle-y ms-3 text-muted" style="font-size: 0.9rem;"></i>
                        <input type="text" name="search" value="{{ $searchValue }}" 
                               class="form-control ps-5" 
                               style="height: var(--wc-touch-target); font-size: var(--wc-text-base); border-radius: var(--wc-radius-sm);"
                               placeholder="{{ $searchPlaceholder }}">
                    </div>
                </form>
            @endif

            <button type="button" 
                    @click="openMobileSheet = true"
                    class="wc-btn-touch wc-btn-outline position-relative flex-shrink-0">
                <i class="fa-solid fa-sliders"></i>
                <span>Filters</span>
                @if($activeFiltersCount > 0)
                    <span class="badge bg-primary text-white rounded-pill ms-1 font-monospace" style="font-size: 0.7rem;">
                        {{ $activeFiltersCount }}
                    </span>
                @endif
            </button>
        </div>

        {{-- Mobile Bottom Sheet --}}
        <div class="wc-bottom-sheet-backdrop" 
             :class="{ 'active': openMobileSheet }"
             @click.self="openMobileSheet = false"
             x-cloak>
            <div class="wc-bottom-sheet">
                <div class="wc-sheet-handle"></div>
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                    <h6 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-sliders text-primary me-2"></i>Filter Records</h6>
                    <button type="button" @click="openMobileSheet = false" class="btn btn-sm btn-link text-muted p-1">
                        <i class="fa-solid fa-xmark fs-5"></i>
                    </button>
                </div>

                <form method="GET" action="{{ $action }}" class="d-flex flex-column gap-3">
                    @if($showSearch && $searchValue)
                        <input type="hidden" name="search" value="{{ $searchValue }}">
                    @endif

                    {{-- Slot content rendered in mobile bottom sheet --}}
                    {{ $slot }}

                    <div class="d-flex gap-2 pt-3 border-top mt-2">
                        <a href="{{ $action }}" class="wc-btn-touch wc-btn-outline flex-grow-1 text-center">
                            Reset All
                        </a>
                        <button type="submit" class="wc-btn-touch wc-btn-primary flex-grow-1 text-center">
                            Apply ({{ $activeFiltersCount }})
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
