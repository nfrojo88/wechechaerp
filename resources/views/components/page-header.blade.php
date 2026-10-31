@props([
    'title'       => null,
    'subtitle'    => null,
    'breadcrumbs' => [],
    'backUrl'     => null,
    'badge'       => null,
])

<div {{ $attributes->merge(['class' => 'wc-page-header mb-4']) }}>
    {{-- Desktop Breadcrumbs --}}
    @if(!empty($breadcrumbs))
        <nav aria-label="breadcrumb" class="d-none d-md-block mb-2">
            <ol class="breadcrumb mb-0" style="font-size: var(--wc-text-xs);">
                <li class="breadcrumb-item"><a href="{{ url('/dashboard') }}" class="text-decoration-none text-muted">Home</a></li>
                @foreach($breadcrumbs as $key => $item)
                    @php
                        $crumbTitle = '';
                        $crumbUrl   = null;

                        if (is_array($item)) {
                            $crumbTitle = $item['label'] ?? ($item['title'] ?? ($item['name'] ?? ''));
                            $crumbUrl   = $item['url'] ?? ($item['link'] ?? ($item['href'] ?? null));
                        } elseif (is_numeric($key)) {
                            $crumbTitle = (string) $item;
                            $crumbUrl   = null;
                        } else {
                            $crumbTitle = (string) $key;
                            $crumbUrl   = is_string($item) ? $item : null;
                        }
                    @endphp
                    @if(!empty($crumbTitle))
                        @if($loop->last || empty($crumbUrl))
                            <li class="breadcrumb-item active text-dark fw-medium" aria-current="page">{{ $crumbTitle }}</li>
                        @else
                            <li class="breadcrumb-item"><a href="{{ $crumbUrl }}" class="text-decoration-none text-muted">{{ $crumbTitle }}</a></li>
                        @endif
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
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <h1 class="h4 mb-0 fw-bold text-dark tracking-tight" style="font-size: clamp(1.2rem, 2.5vw, 1.5rem); letter-spacing: -0.02em;">
                        {{ is_array($title) ? ($title['text'] ?? ($title['label'] ?? '')) : ($title ?? '') }}
                    </h1>
                    @if(!empty($badge))
                        <span class="badge bg-primary bg-opacity-10 text-primary fw-semibold rounded-pill px-2 py-1" style="font-size: 0.7rem;">
                            {{ is_array($badge) ? ($badge['text'] ?? ($badge['label'] ?? '')) : $badge }}
                        </span>
                    @endif
                </div>
                @if(!empty($subtitle))
                    <p class="text-muted small mb-0 mt-0.5" style="font-size: var(--wc-text-sm);">
                        {{ is_array($subtitle) ? ($subtitle['text'] ?? ($subtitle['label'] ?? '')) : $subtitle }}
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
