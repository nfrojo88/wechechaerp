@props([
    'title',
    'subtitle' => null,
    'breadcrumbs' => [],
    'backUrl' => null,
])

<div {{ $attributes->merge(['class' => 'wc-page-header mb-4']) }}>
    {{-- Desktop Breadcrumbs --}}
    @if(!empty($breadcrumbs))
        <nav aria-label="breadcrumb" class="d-none d-md-block mb-2">
            <ol class="breadcrumb mb-0" style="font-size: var(--wc-text-xs);">
                <li class="breadcrumb-item"><a href="{{ url('/dashboard') }}" class="text-decoration-none text-muted">Home</a></li>
                @foreach($breadcrumbs as $crumbTitle => $crumbUrl)
                    @if($loop->last || empty($crumbUrl))
                        <li class="breadcrumb-item active text-dark fw-medium" aria-current="page">{{ $crumbTitle }}</li>
                    @else
                        <li class="breadcrumb-item"><a href="{{ $crumbUrl }}" class="text-decoration-none text-muted">{{ $crumbTitle }}</a></li>
                    @endif
                @endforeach
            </ol>
        </nav>
    @endif

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div class="d-flex align-items-center gap-2">
            @if($backUrl)
                <a href="{{ $backUrl }}" class="wc-btn-touch wc-btn-outline p-2 me-1 text-decoration-none" title="Go back">
                    <i class="fa-solid fa-arrow-left"></i>
                </a>
            @endif
            <div>
                <h1 class="h4 mb-0 fw-bold text-dark tracking-tight" style="font-size: clamp(1.2rem, 2.5vw, 1.5rem); letter-spacing: -0.02em;">
                    {{ $title }}
                </h1>
                @if($subtitle)
                    <p class="text-muted small mb-0 mt-0.5" style="font-size: var(--wc-text-sm);">
                        {{ $subtitle }}
                    </p>
                @endif
            </div>
        </div>

        {{-- Actions slot --}}
        @if(isset($actions))
            <div class="d-flex flex-wrap align-items-center gap-2">
                {{ $actions }}
            </div>
        @endif
    </div>
</div>
