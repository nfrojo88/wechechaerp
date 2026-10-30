@props([
    'status' => 'pending', // 'pending', 'approved', 'rejected'
    'sendGmUrl' => null,
    'approveUrl' => null,
    'rejectUrl' => null,
    'rejectionModalId' => null,
    'itemName' => 'Record',
])

<div {{ $attributes->merge(['class' => 'wc-approval-bar-wrapper']) }}>
    <style>
        @media (max-width: 768px) {
            .wc-approval-sticky-mobile {
                position: fixed;
                bottom: var(--wc-bottom-nav-height);
                left: 0;
                right: 0;
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(8px);
                border-top: 1px solid var(--wc-border);
                padding: var(--wc-space-3);
                z-index: 1030;
                box-shadow: 0 -4px 12px rgba(15, 23, 42, 0.08);
            }
        }
    </style>

    <div class="wc-card p-3 wc-approval-sticky-mobile">
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-2">
                <span class="text-muted small fw-medium">Approval Status:</span>
                <x-status-badge :status="$status" />
            </div>

            <div class="d-flex flex-wrap align-items-center gap-2 w-100 w-sm-auto justify-content-end">
                @if($sendGmUrl && $status !== 'approved')
                    <form method="POST" action="{{ $sendGmUrl }}" class="m-0 flex-grow-1 flex-sm-grow-0">
                        @csrf
                        <button type="submit" 
                                onclick="this.disabled=true; this.form.submit();"
                                class="wc-btn-touch wc-btn-primary w-100 justify-content-center">
                            <i class="fa-solid fa-paper-plane"></i>
                            <span>Send to GM</span>
                        </button>
                    </form>
                @endif

                @if($rejectUrl && $status !== 'rejected')
                    @if($rejectionModalId)
                        <button type="button" 
                                data-bs-toggle="modal" 
                                data-bs-target="#{{ $rejectionModalId }}"
                                class="wc-btn-touch btn btn-outline-danger flex-grow-1 flex-sm-grow-0 justify-content-center">
                            <i class="fa-solid fa-xmark"></i>
                            <span>Reject / Query</span>
                        </button>
                    @else
                        <form method="POST" action="{{ $rejectUrl }}" class="m-0 flex-grow-1 flex-sm-grow-0">
                            @csrf
                            <button type="submit" 
                                    onclick="return confirm('Are you sure you want to reject this {{ $itemName }}?');"
                                    class="wc-btn-touch btn btn-outline-danger w-100 justify-content-center">
                                <i class="fa-solid fa-xmark"></i>
                                <span>Reject</span>
                            </button>
                        </form>
                    @endif
                @endif

                @if($approveUrl && $status !== 'approved')
                    <form method="POST" action="{{ $approveUrl }}" class="m-0 flex-grow-1 flex-sm-grow-0">
                        @csrf
                        <button type="submit" 
                                onclick="this.disabled=true; this.form.submit();"
                                class="wc-btn-touch btn btn-success text-white w-100 justify-content-center">
                            <i class="fa-solid fa-check"></i>
                            <span>Approve {{ $itemName }}</span>
                        </button>
                    </form>
                @endif

                {{ $slot }}
            </div>
        </div>
    </div>
</div>
