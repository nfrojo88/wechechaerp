@extends('layouts.app')

@section('title', 'Create Material Request')

@section('content')
<div class="d-flex align-items-center mb-4">
    <a href="{{ $redirectBack ?? route('material-requests.index') }}" class="btn btn-sm btn-outline-secondary me-3">
        <i class="fa-solid fa-arrow-left"></i>
    </a>
    <h1 class="h3 mb-0">Create Material Request</h1>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ route('material-requests.store') }}">
            @csrf
            @if(!empty($redirectBack))
                <input type="hidden" name="redirect_back" value="{{ $redirectBack }}">
            @endif
            @if(!empty($materialName))
                <input type="hidden" name="material_name" value="{{ $materialName }}">
                <input type="hidden" name="quantity" value="{{ $quantity }}">
                <input type="hidden" name="unit" value="{{ $unit }}">
            @endif
            <div class="row g-3">
                {{-- Source Section (Non-editable Box) --}}
                <div class="col-12">
                    <label class="form-label fw-bold">Request Source</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="fa-solid fa-code-branch text-primary"></i></span>
                        <input type="text" class="form-control bg-light fw-bold text-dark" value="{{ $source }}" readonly>
                        <input type="hidden" name="source" value="{{ $source }}">
                    </div>
                    <div class="form-text">System-detected origin of this request (Non-editable).</div>
                </div>

                {{-- Associated Project --}}
                <div class="col-md-6">
                    <label class="form-label fw-bold">Associated Project <span class="text-danger">*</span></label>
                    <select name="project_id" id="projectSelect" class="form-select @error('project_id') is-invalid @enderror" required>
                        <option value="">— Select Project —</option>
                        @foreach($projects as $project)
                        <option value="{{ $project->id }}" 
                                data-store-id="{{ $project->default_store_id ?? ($project->stores->first()->id ?? '') }}"
                                @selected(old('project_id', $selectedProjectId ?? '') == $project->id)>
                            {{ $project->name }} ({{ $project->code }})
                        </option>
                        @endforeach
                    </select>
                    @error('project_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- Destination Store (Auto-selected) --}}
                <div class="col-md-6">
                    <label class="form-label fw-bold">Destination Store <span class="text-danger">*</span></label>
                    <select name="destination_store_id" id="storeSelect" class="form-select @error('destination_store_id') is-invalid @enderror" required>
                        <option value="">— Select Receiving Store —</option>
                        @foreach($stores as $store)
                        <option value="{{ $store->id }}" 
                                data-project-id="{{ $store->project_id }}"
                                @selected(old('destination_store_id', $selectedStoreId ?? '') == $store->id)>
                            {{ $store->name }} ({{ $store->code }})
                        </option>
                        @endforeach
                    </select>
                    <div class="form-text">Automatically set to project's store when a project is chosen.</div>
                    @error('destination_store_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- Reference Number --}}
                <div class="col-md-6">
                    <label class="form-label fw-bold">Reference Number <span class="text-danger">*</span></label>
                    <input type="text" name="reference_number" class="form-control @error('reference_number') is-invalid @enderror"
                           value="{{ old('reference_number', 'MR-'.date('Ym').'-'.rand(1000,9999)) }}" required>
                    @error('reference_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- Required Date --}}
                <div class="col-md-6">
                    <label class="form-label fw-bold">Required By Date <span class="text-danger">*</span></label>
                    <input type="date" name="required_date" class="form-control @error('required_date') is-invalid @enderror"
                           value="{{ old('required_date', $dateNeeded ?? '') }}" required>
                    @error('required_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- General Notes --}}
                <div class="col-12">
                    <label class="form-label fw-bold">General Notes / Justification</label>
                    <textarea name="notes" rows="3" class="form-control" placeholder="Enter optional notes or justification...">{{ old('notes') }}</textarea>
                </div>

                <div class="col-12 d-flex justify-content-end gap-2 mt-4">
                    <a href="{{ $redirectBack ?? route('material-requests.index') }}" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Create Draft Request</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const projectSelect = document.getElementById('projectSelect');
    const storeSelect = document.getElementById('storeSelect');
    if (!projectSelect || !storeSelect) return;

    // Cache original store options (excluding the placeholder)
    const allStoreOptions = Array.from(storeSelect.querySelectorAll('option')).filter(opt => opt.value !== '');

    function syncProjectAndStore() {
        // If no project selected yet and there is only 1 project option, auto-select it
        if (!projectSelect.value && projectSelect.options.length === 2) {
            projectSelect.selectedIndex = 1;
        }

        const selectedProjectId = projectSelect.value;
        const currentSelectedStoreId = storeSelect.value;

        // Clear existing store options
        storeSelect.innerHTML = '<option value="">— Select Receiving Store —</option>';

        let matchingStoreCount = 0;
        let exactMatchedStoreId = '';

        allStoreOptions.forEach(opt => {
            const storeProjId = opt.getAttribute('data-project-id');
            // If project is chosen, only include stores linked to this project
            if (!selectedProjectId || storeProjId === selectedProjectId || (!storeProjId && !selectedProjectId)) {
                storeSelect.appendChild(opt.cloneNode(true));
                matchingStoreCount++;
                if (!exactMatchedStoreId && storeProjId === selectedProjectId) {
                    exactMatchedStoreId = opt.value;
                }
            }
        });

        // Try restoring previous selection if still available
        if (currentSelectedStoreId && storeSelect.querySelector(`option[value="${currentSelectedStoreId}"]`)) {
            storeSelect.value = currentSelectedStoreId;
        } else if (exactMatchedStoreId) {
            storeSelect.value = exactMatchedStoreId;
        } else if (matchingStoreCount === 1) {
            storeSelect.selectedIndex = 1;
        }
    }

    projectSelect.addEventListener('change', syncProjectAndStore);
    syncProjectAndStore();
});
</script>
@endpush

