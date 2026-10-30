<div x-data="{
        toasts: [],
        addToast(message, type = 'success') {
            const id = Date.now();
            this.toasts.push({ id, message, type });
            setTimeout(() => this.removeToast(id), 5000);
        },
        removeToast(id) {
            this.toasts = this.toasts.filter(t => t.id !== id);
        }
     }"
     @notify.window="addToast($event.detail.message, $event.detail.type)"
     class="toast-container position-fixed bottom-0 end-0 p-3" 
     style="z-index: 1090; max-width: 380px;">

    {{-- Initial Session Flashes --}}
    @if(session('success'))
        <div class="toast show align-items-center border-0 shadow-sm mb-2" 
             style="background-color: var(--wc-surface); border-left: 4px solid var(--wc-success) !important; border-radius: var(--wc-radius-md);" 
             role="alert">
            <div class="d-flex p-3 align-items-center">
                <i class="fa-solid fa-circle-check text-success fs-5 me-3"></i>
                <div class="toast-body p-0 text-dark fw-medium" style="font-size: var(--wc-text-sm);">
                    {{ session('success') }}
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    @endif

    @if(session('error'))
        <div class="toast show align-items-center border-0 shadow-sm mb-2" 
             style="background-color: var(--wc-surface); border-left: 4px solid var(--wc-danger) !important; border-radius: var(--wc-radius-md);" 
             role="alert">
            <div class="d-flex p-3 align-items-center">
                <i class="fa-solid fa-circle-xmark text-danger fs-5 me-3"></i>
                <div class="toast-body p-0 text-dark fw-medium" style="font-size: var(--wc-text-sm);">
                    {{ session('error') }}
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    @endif

    {{-- Dynamic Alpine Toasts --}}
    <template x-for="toast in toasts" :key="toast.id">
        <div class="toast show align-items-center border-0 shadow-sm mb-2"
             :style="{
                backgroundColor: 'var(--wc-surface)',
                borderLeft: '4px solid ' + (toast.type === 'error' ? 'var(--wc-danger)' : (toast.type === 'warning' ? 'var(--wc-warning)' : 'var(--wc-success)')) + ' !important',
                borderRadius: 'var(--wc-radius-md)'
             }"
             role="alert">
            <div class="d-flex p-3 align-items-center">
                <i class="fa-solid fs-5 me-3" 
                   :class="toast.type === 'error' ? 'fa-circle-xmark text-danger' : (toast.type === 'warning' ? 'fa-triangle-exclamation text-warning' : 'fa-circle-check text-success')"></i>
                <div class="toast-body p-0 text-dark fw-medium" style="font-size: var(--wc-text-sm);" x-text="toast.message"></div>
                <button type="button" @click="removeToast(toast.id)" class="btn-close ms-auto" aria-label="Close"></button>
            </div>
        </div>
    </template>
</div>
