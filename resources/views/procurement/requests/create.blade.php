@extends('layouts.app')

@section('title', 'Create Emergency Material Request')

@section('content')
<div class="container-fluid px-4 py-3">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div class="d-flex align-items-center">
            <a href="{{ $redirectBack ?? route('material-requests.index') }}" class="btn btn-sm btn-outline-secondary me-3 shadow-sm">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div>
                <h4 class="fw-bold mb-1" style="color:var(--brand-800)">
                    <i class="fa-solid fa-bolt text-danger me-2"></i>Create Emergency Material Request
                </h4>
                <p class="text-muted small mb-0">Select site materials and send directly to Planning Manager for urgent review &amp; approval.</p>
            </div>
        </div>
        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-3 py-2 fs-6 rounded-pill fw-bold">
            <i class="fa-solid fa-truck-fast me-1"></i> Direct Route: Site Engineer &rarr; Planning Manager &rarr; Coordinator
        </span>
    </div>

    <!-- Direct Route Workflow Notice -->
    <div class="alert alert-primary border-start border-4 border-primary shadow-sm mb-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center">
                <div class="p-2 rounded-circle bg-primary bg-opacity-10 text-primary me-3">
                    <i class="fa-solid fa-paper-plane fa-lg"></i>
                </div>
                <div>
                    <strong class="d-block text-dark">Direct Planning Approval Flow</strong>
                    <span class="text-muted small">This emergency request goes directly to the <strong>Planning Manager</strong> for approval. Upon approval, it lands immediately in the <strong>Coordinator's Procurement Queue</strong> for dispatch, PR buy, or store transfer.</span>
                </div>
            </div>
            <span class="badge bg-danger text-white px-3 py-2"><i class="fa-solid fa-fire me-1"></i>High Priority</span>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show mb-4 shadow-sm" role="alert">
            <div class="d-flex align-items-center">
                <i class="fa-solid fa-circle-exclamation fs-5 me-2"></i>
                <div>
                    <strong>Please check the errors below:</strong>
                    <ul class="mb-0 mt-1 small">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <form method="POST" action="{{ route('material-requests.store') }}" id="materialRequestForm">
        @csrf
        @if(!empty($redirectBack))
            <input type="hidden" name="redirect_back" value="{{ $redirectBack }}">
        @endif

        <div class="row g-4">
            <!-- Left & Middle: Request Meta & Material Selection -->
            <div class="col-lg-8">
                <!-- Project & Store Configuration -->
                <div class="card shadow-sm border-0 rounded-3 mb-4">
                    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                        <h6 class="fw-bold mb-0 text-dark">
                            <i class="fa-solid fa-building text-primary me-2"></i>Project &amp; Store Location
                        </h6>
                        <span class="badge bg-light text-secondary border">Origin: {{ $source }}</span>
                    </div>
                    <div class="card-body p-4">
                        <input type="hidden" name="source" value="{{ $source }}">

                        <div class="row g-3">
                            {{-- Associated Project --}}
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark">Associated Project <span class="text-danger">*</span></label>
                                <select name="project_id" id="projectSelect" class="form-select @error('project_id') is-invalid @enderror" required>
                                    <option value="">— Select Project —</option>
                                    @foreach($projects as $project)
                                    <option value="{{ $project->id }}" 
                                            data-store-id="{{ $project->default_store_id ?? ($project->stores->first()->id ?? '') }}"
                                            @selected(old('project_id', $selectedProjectId ?? '') == $project->id)>
                                        {{ $project->project_name ?? $project->name }} ({{ $project->code ?? 'PRJ-' . $project->id }})
                                    </option>
                                    @endforeach
                                </select>
                                @error('project_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            {{-- Destination Store --}}
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark">Destination Store <span class="text-danger">*</span></label>
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
                                <div class="form-text small">Receiving site store where materials will be delivered.</div>
                                @error('destination_store_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            {{-- Reference Number --}}
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark">Reference Number <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-hashtag"></i></span>
                                    <input type="text" name="reference_number" class="form-control @error('reference_number') is-invalid @enderror"
                                           value="{{ old('reference_number', 'MR-'.date('Ym').'-'.rand(1000,9999)) }}" required>
                                </div>
                                @error('reference_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            {{-- Required Date --}}
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark">Required On Site By <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-calendar-day"></i></span>
                                    <input type="date" name="required_date" class="form-control @error('required_date') is-invalid @enderror"
                                           value="{{ old('required_date', $dateNeeded ?? date('Y-m-d')) }}" required>
                                </div>
                                @error('required_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Select Materials Table Card -->
                <div class="card shadow-sm border-0 rounded-3 mb-4">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h6 class="fw-bold mb-0 text-dark">
                                <i class="fa-solid fa-boxes-stacked text-primary me-2"></i>Select Materials &amp; Quantities
                            </h6>
                            <small class="text-muted">Choose items from the catalog or specify quantity needed on site.</small>
                        </div>
                        <button type="button" class="btn btn-sm btn-primary shadow-sm" onclick="addMaterialRow()">
                            <i class="fa-solid fa-plus me-1"></i> Add Another Material
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="materialsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th style="min-width: 260px;">Select Material / Product <span class="text-danger">*</span></th>
                                        <th style="width: 140px;">Quantity <span class="text-danger">*</span></th>
                                        <th style="width: 100px;">Unit</th>
                                        <th style="min-width: 180px;">Item Notes / Specification</th>
                                        <th style="width: 50px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="materialsBody">
                                    <!-- Dynamic Material Rows -->
                                </tbody>
                            </table>
                        </div>
                        <div class="p-3 bg-light border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="addMaterialRow()">
                                <i class="fa-solid fa-plus me-1"></i> Add Another Material
                            </button>
                            <span class="text-muted small" id="itemsCountBadge">0 materials selected</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Urgency & Submit Button -->
            <div class="col-lg-4">
                <div class="card shadow-sm border-0 rounded-3 mb-4 sticky-top" style="top: 20px; z-index: 10;">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h6 class="fw-bold mb-0 text-dark">
                            <i class="fa-solid fa-paper-plane text-primary me-2"></i>Submission &amp; Urgency
                        </h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-dark">Emergency Justification / Notes</label>
                            <textarea name="notes" rows="4" class="form-control" 
                                      placeholder="Explain the urgency, specific work area (e.g. 3rd Floor Slab Casting), or reason for immediate dispatch...">{{ old('notes') }}</textarea>
                            <small class="text-muted">Will be highlighted to the Planning Manager.</small>
                        </div>

                        <div class="p-3 bg-light rounded-3 mb-4 border">
                            <div class="d-flex align-items-center text-primary mb-2">
                                <i class="fa-solid fa-shield-halved me-2 fs-5"></i>
                                <strong>Next Step After Submit:</strong>
                            </div>
                            <ol class="small text-muted ps-3 mb-0">
                                <li class="mb-1"><strong>Planning Manager</strong> receives notification &amp; reviews technical demand.</li>
                                <li class="mb-1">Upon approval, automatically routes to <strong>Coordinator</strong>.</li>
                                <li><strong>Coordinator</strong> dispatches store stock or initiates purchase.</li>
                            </ol>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-100 py-3 fw-bold shadow-sm" id="submitBtn">
                            <i class="fa-solid fa-paper-plane me-2"></i> Send to Planning Manager
                        </button>

                        <a href="{{ $redirectBack ?? route('material-requests.index') }}" class="btn btn-outline-secondary w-100 mt-2 py-2">
                            Cancel
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Products JSON for Dynamic Selector -->
<script>
    const availableProducts = @json($products ?? []);
    let materialRowIndex = 0;

    function addMaterialRow(selectedProductId = '', qty = '', unitText = '', notesText = '') {
        const tbody = document.getElementById('materialsBody');
        const tr = document.createElement('tr');
        tr.id = `mat_row_${materialRowIndex}`;

        let optionsHtml = '<option value="">-- Choose Material / Product --</option>';
        availableProducts.forEach(p => {
            const isSel = (selectedProductId == p.id) ? 'selected' : '';
            const codeStr = p.code ? `[${p.code}] ` : '';
            optionsHtml += `<option value="${p.id}" data-unit="${p.unit || 'pcs'}" ${isSel}>${codeStr}${p.name}</option>`;
        });

        tr.innerHTML = `
            <td>
                <select name="items[${materialRowIndex}][product_id]" class="form-select product-selector" onchange="onProductChanged(${materialRowIndex})" required>
                    ${optionsHtml}
                </select>
            </td>
            <td>
                <input type="number" step="0.001" min="0.001" name="items[${materialRowIndex}][quantity]" class="form-control item-quantity" placeholder="Quantity" value="${qty}" oninput="updateItemsCount()" required>
            </td>
            <td>
                <span class="badge bg-light text-dark border px-2.5 py-1.5 fs-7 d-inline-block w-100 text-center unit-badge" id="unit_badge_${materialRowIndex}">
                    ${unitText || 'pcs'}
                </span>
            </td>
            <td>
                <input type="text" name="items[${materialRowIndex}][notes]" class="form-control form-control-sm" placeholder="e.g. Grade 60, specific size..." value="${notesText}">
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm text-danger p-1" onclick="removeMaterialRow(${materialRowIndex})" title="Remove item">
                    <i class="fa-solid fa-trash-can"></i>
                </button>
            </td>
        `;

        tbody.appendChild(tr);
        materialRowIndex++;
        updateItemsCount();
    }

    function removeMaterialRow(idx) {
        const row = document.getElementById(`mat_row_${idx}`);
        if (row) {
            row.remove();
        }
        updateItemsCount();
    }

    function onProductChanged(idx) {
        const row = document.getElementById(`mat_row_${idx}`);
        if (!row) return;
        const select = row.querySelector('.product-selector');
        const selectedOpt = select.options[select.selectedIndex];
        const unit = selectedOpt.getAttribute('data-unit') || 'pcs';
        const unitBadge = document.getElementById(`unit_badge_${idx}`);
        if (unitBadge) {
            unitBadge.textContent = unit;
        }
        updateItemsCount();
    }

    function updateItemsCount() {
        const count = document.querySelectorAll('#materialsBody tr').length;
        const countBadge = document.getElementById('itemsCountBadge');
        if (countBadge) {
            countBadge.textContent = count === 1 ? '1 material item added' : `${count} material items added`;
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const projectSelect = document.getElementById('projectSelect');
        const storeSelect = document.getElementById('storeSelect');
        if (projectSelect && storeSelect) {
            const allStoreOptions = Array.from(storeSelect.querySelectorAll('option')).filter(opt => opt.value !== '');

            function syncProjectAndStore() {
                // If project is not selected yet, auto-select the assigned or first valid project
                if (!projectSelect.value && projectSelect.options.length > 1) {
                    let chosenOpt = Array.from(projectSelect.options).find(opt => opt.value !== '' && opt.hasAttribute('selected'));
                    if (!chosenOpt) {
                        chosenOpt = Array.from(projectSelect.options).find(opt => opt.value !== '');
                    }
                    if (chosenOpt) {
                        projectSelect.value = chosenOpt.value;
                    }
                }

                const selectedProjectId = projectSelect.value;
                const currentSelectedStoreId = storeSelect.value;

                storeSelect.innerHTML = '<option value="">— Select Receiving Store —</option>';

                let matchingStoreCount = 0;
                let exactMatchedStoreId = '';

                allStoreOptions.forEach(opt => {
                    const storeProjId = opt.getAttribute('data-project-id');
                    if (!selectedProjectId || storeProjId === selectedProjectId || (!storeProjId && !selectedProjectId)) {
                        storeSelect.appendChild(opt.cloneNode(true));
                        matchingStoreCount++;
                        if (!exactMatchedStoreId && storeProjId === selectedProjectId) {
                            exactMatchedStoreId = opt.value;
                        }
                    }
                });

                if (currentSelectedStoreId && storeSelect.querySelector(`option[value="${currentSelectedStoreId}"]`)) {
                    storeSelect.value = currentSelectedStoreId;
                } else if (exactMatchedStoreId) {
                    storeSelect.value = exactMatchedStoreId;
                } else if (matchingStoreCount >= 1) {
                    storeSelect.selectedIndex = 1;
                }
            }

            projectSelect.addEventListener('change', syncProjectAndStore);
            syncProjectAndStore();
        }

        // Check if pre-filled single material was passed
        @if(!empty($materialName))
            const prefilledMatName = "{{ $materialName }}".toLowerCase();
            const matchedProd = availableProducts.find(p => p.name.toLowerCase().includes(prefilledMatName));
            if (matchedProd) {
                addMaterialRow(matchedProd.id, "{{ $quantity ?? 1 }}", matchedProd.unit, "Forecast Demand Request");
            } else {
                addMaterialRow('', "{{ $quantity ?? 1 }}", "{{ $unit ?? 'pcs' }}", "{{ $materialName }}");
            }
        @else
            // Add initial empty material rows
            addMaterialRow();
            addMaterialRow();
        @endif
    });
</script>
@endsection
