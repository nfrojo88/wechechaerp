@props([
    'id',
    'title' => 'Are you sure?',
    'message' => 'This action cannot be undone. Do you wish to proceed?',
    'action' => '#',
    'method' => 'POST',
    'confirmText' => 'Confirm',
    'confirmTheme' => 'danger', // 'danger' or 'primary'
])

<div class="modal fade" id="{{ $id }}" tabindex="-1" aria-labelledby="{{ $id }}Label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm-bottom-sheet">
        <div class="modal-content border-0 shadow-lg" style="border-radius: var(--wc-radius-lg);">
            <div class="modal-header border-bottom-0 pb-1 pt-3 px-4">
                <h6 class="modal-title fw-bold text-dark" id="{{ $id }}Label">
                    <i class="fa-solid {{ $confirmTheme === 'danger' ? 'fa-triangle-exclamation text-danger' : 'fa-circle-question text-primary' }} me-2"></i>
                    {{ $title }}
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form action="{{ $action }}" method="POST">
                @csrf
                @if(in_array(strtoupper($method), ['PUT', 'PATCH', 'DELETE']))
                    @method($method)
                @endif

                <div class="modal-body py-2 px-4 text-muted" style="font-size: var(--wc-text-sm);">
                    <p class="mb-0">{{ $message }}</p>
                    {{ $slot }}
                </div>

                <div class="modal-footer border-top-0 pt-3 pb-4 px-4 d-flex gap-2">
                    <button type="button" class="wc-btn-touch wc-btn-outline flex-grow-1" data-bs-dismiss="modal">
                        Cancel
                    </button>
                    <button type="submit" class="wc-btn-touch {{ $confirmTheme === 'danger' ? 'btn btn-danger' : 'wc-btn-primary' }} flex-grow-1">
                        {{ $confirmText }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
