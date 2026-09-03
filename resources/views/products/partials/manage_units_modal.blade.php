<!-- Modal: Manage Units of Measurement -->
<div class="modal fade" id="manageUnitsModal" tabindex="-1" aria-labelledby="manageUnitsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-primary text-white py-3 px-4">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-white bg-opacity-20 text-white p-2 rounded-3 fs-6">
                        <i class="fa-solid fa-ruler-combined"></i>
                    </span>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="manageUnitsModalLabel">Manage Units of Measurement</h5>
                        <small class="text-white-50">Create and manage measurement units for materials, products, and inventory.</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4 bg-white">
                <!-- Alert Feedback Container -->
                <div id="uomAlertContainer"></div>

                <!-- Add New Unit Form Section -->
                <div class="card border border-primary-subtle bg-light-subtle rounded-3 p-3 mb-4 shadow-sm">
                    <div class="d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom">
                        <h6 class="fw-bold text-primary mb-0 small text-uppercase">
                            <i class="fa-solid fa-plus-circle me-1"></i> Add New Unit of Measurement
                        </h6>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-0">Fast Add</span>
                    </div>
                    <form id="addUomForm" onsubmit="handleCreateUom(event)">
                        <div class="row g-2">
                            <div class="col-md-3">
                                <label class="form-label small fw-bold text-dark mb-1">Unit Code <span class="text-danger">*</span></label>
                                <input type="text" id="uom_input_code" class="form-control form-control-sm text-lowercase font-monospace" placeholder="e.g. pkt, drum" required>
                                <small class="text-muted" style="font-size:0.75rem;">Short symbol (e.g. kg, pcs)</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-dark mb-1">Full Name <span class="text-danger">*</span></label>
                                <input type="text" id="uom_input_name" class="form-control form-control-sm" placeholder="e.g. Packet, Drum" required>
                                <small class="text-muted" style="font-size:0.75rem;">Descriptive name</small>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold text-dark mb-1">Description</label>
                                <input type="text" id="uom_input_desc" class="form-control form-control-sm" placeholder="Optional notes">
                                <small class="text-muted" style="font-size:0.75rem;">Packaging details</small>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" id="btnSaveUom" class="btn btn-primary btn-sm w-100 fw-bold shadow-sm" style="height: 31px;">
                                    <i class="fa-solid fa-plus me-1"></i> Add
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Existing Units Table Section -->
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold text-dark mb-0 small text-uppercase">
                        <i class="fa-solid fa-list-check text-secondary me-1"></i> Existing Units (<span id="uomCountBadge">{{ is_countable($units) ? count($units) : 0 }}</span>)
                    </h6>
                    <div class="w-50">
                        <input type="text" id="uomSearchInput" class="form-control form-control-sm" placeholder="Search units by code or name..." oninput="filterUomTable(this.value)">
                    </div>
                </div>

                <div class="table-responsive border rounded-3" style="max-height: 280px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="bg-light sticky-top shadow-xs">
                            <tr>
                                <th class="ps-3 py-2 text-uppercase text-muted">Code</th>
                                <th class="py-2 text-uppercase text-muted">Full Name</th>
                                <th class="py-2 text-uppercase text-muted">Description</th>
                                <th class="py-2 text-uppercase text-muted text-center">Type</th>
                                <th class="pe-3 py-2 text-uppercase text-muted text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody id="uomTableBody">
                            @foreach($units as $u)
                                @php
                                    $uCode = is_object($u) ? $u->code : $u;
                                    $uName = is_object($u) ? $u->name : ucfirst($u);
                                    $uDesc = is_object($u) ? ($u->description ?? 'Standard unit') : 'Standard unit';
                                    $uSys  = is_object($u) ? ($u->is_system ?? false) : true;
                                    $uId   = is_object($u) ? ($u->id ?? null) : null;
                                @endphp
                                <tr id="uomRow_{{ $uCode }}" data-code="{{ strtolower($uCode) }}" data-name="{{ strtolower($uName) }}">
                                    <td class="ps-3 py-2">
                                        <span class="badge bg-light text-primary border font-monospace fs-6 px-2 py-1">{{ $uCode }}</span>
                                    </td>
                                    <td class="py-2 fw-semibold text-dark">{{ $uName }}</td>
                                    <td class="py-2 text-muted">{{ $uDesc }}</td>
                                    <td class="py-2 text-center">
                                        @if($uSys)
                                            <span class="badge bg-secondary-subtle text-secondary border px-2">Standard</span>
                                        @else
                                            <span class="badge bg-info-subtle text-info border px-2">Custom</span>
                                        @endif
                                    </td>
                                    <td class="pe-3 py-2 text-end">
                                        @if(!$uSys && $uId)
                                            <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2 rounded-pill" onclick="handleDeleteUom({{ $uId }}, '{{ $uCode }}')" title="Remove custom unit">
                                                <i class="fa-solid fa-trash-can" style="font-size: 0.75rem;"></i>
                                            </button>
                                        @else
                                            <span class="text-muted" style="font-size:0.75rem;"><i class="fa-solid fa-lock text-muted opacity-50" title="Protected standard unit"></i></span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="modal-footer bg-light border-0 py-2 px-4">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Done &amp; Close</button>
            </div>
        </div>
    </div>
</div>

<script>
/**
 * Create new Unit of Measurement via AJAX
 */
function handleCreateUom(event) {
    event.preventDefault();

    const codeInput = document.getElementById('uom_input_code');
    const nameInput = document.getElementById('uom_input_name');
    const descInput = document.getElementById('uom_input_desc');
    const btnSave = document.getElementById('btnSaveUom');

    const code = codeInput.value.trim().toLowerCase();
    const name = nameInput.value.trim();
    const desc = descInput.value.trim();

    if (!code || !name) {
        showUomAlert('danger', 'Please enter both Unit Code and Full Name.');
        return;
    }

    btnSave.disabled = true;
    btnSave.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') 
        || document.querySelector('input[name="_token"]')?.value;

    fetch('{{ route("units-of-measurement.store") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({
            code: code,
            name: name,
            description: desc
        })
    })
    .then(async response => {
        const data = await response.json();
        if (!response.ok) {
            throw new Error(data.message || 'Failed to create unit.');
        }
        return data;
    })
    .then(res => {
        showUomAlert('success', res.message || 'Unit created successfully.');

        // Clear input form
        codeInput.value = '';
        nameInput.value = '';
        descInput.value = '';

        const unit = res.unit;

        // 1. Add to page select dropdown(s) and auto-select it!
        const selectEls = document.querySelectorAll('select[name="unit"]');
        selectEls.forEach(sel => {
            let optionExists = false;
            for (let i = 0; i < sel.options.length; i++) {
                if (sel.options[i].value.toLowerCase() === unit.code.toLowerCase()) {
                    optionExists = true;
                    sel.selectedIndex = i;
                    break;
                }
            }
            if (!optionExists) {
                const newOpt = document.createElement('option');
                newOpt.value = unit.code;
                newOpt.text = unit.name + ' (' + unit.code + ')';
                newOpt.selected = true;
                sel.appendChild(newOpt);
            }
        });

        // 2. Append row to modal table
        const tbody = document.getElementById('uomTableBody');
        const existingRow = document.getElementById('uomRow_' + unit.code);
        if (!existingRow) {
            const tr = document.createElement('tr');
            tr.id = 'uomRow_' + unit.code;
            tr.setAttribute('data-code', unit.code.toLowerCase());
            tr.setAttribute('data-name', unit.name.toLowerCase());
            tr.innerHTML = `
                <td class="ps-3 py-2">
                    <span class="badge bg-light text-primary border font-monospace fs-6 px-2 py-1">${unit.code}</span>
                </td>
                <td class="py-2 fw-semibold text-dark">${unit.name}</td>
                <td class="py-2 text-muted">${unit.description || 'Custom unit'}</td>
                <td class="py-2 text-center">
                    <span class="badge bg-info-subtle text-info border px-2">Custom</span>
                </td>
                <td class="pe-3 py-2 text-end">
                    <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2 rounded-pill" onclick="handleDeleteUom(${unit.id}, '${unit.code}')" title="Remove custom unit">
                        <i class="fa-solid fa-trash-can" style="font-size: 0.75rem;"></i>
                    </button>
                </td>
            `;
            tbody.prepend(tr);
        }

        updateUomCount();
    })
    .catch(err => {
        showUomAlert('danger', err.message);
    })
    .finally(() => {
        btnSave.disabled = false;
        btnSave.innerHTML = '<i class="fa-solid fa-plus me-1"></i> Add';
    });
}

/**
 * Delete custom unit via AJAX
 */
function handleDeleteUom(id, code) {
    if (!confirm(`Are you sure you want to remove the unit '${code}'?`)) return;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') 
        || document.querySelector('input[name="_token"]')?.value;

    fetch(`{{ url('units-of-measurement') }}/${id}`, {
        method: 'DELETE',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        }
    })
    .then(async response => {
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || 'Could not delete unit.');
        return data;
    })
    .then(res => {
        showUomAlert('success', res.message || `Unit '${code}' removed.`);

        // Remove row from table
        const row = document.getElementById('uomRow_' + code);
        if (row) row.remove();

        // Remove from select dropdowns
        const selectEls = document.querySelectorAll('select[name="unit"]');
        selectEls.forEach(sel => {
            for (let i = 0; i < sel.options.length; i++) {
                if (sel.options[i].value.toLowerCase() === code.toLowerCase()) {
                    sel.remove(i);
                    break;
                }
            }
        });

        updateUomCount();
    })
    .catch(err => {
        showUomAlert('danger', err.message);
    });
}

function showUomAlert(type, message) {
    const container = document.getElementById('uomAlertContainer');
    if (!container) return;
    container.innerHTML = `
        <div class="alert alert-${type} alert-dismissible fade show py-2 px-3 small rounded-3" role="alert">
            <i class="fa-solid fa-${type === 'success' ? 'circle-check' : 'circle-exclamation'} me-1"></i> ${message}
            <button type="button" class="btn-close py-2" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
}

function filterUomTable(query) {
    const q = (query || '').toLowerCase().trim();
    const rows = document.querySelectorAll('#uomTableBody tr');
    rows.forEach(r => {
        const code = r.getAttribute('data-code') || '';
        const name = r.getAttribute('data-name') || '';
        if (!q || code.includes(q) || name.includes(q)) {
            r.style.display = '';
        } else {
            r.style.display = 'none';
        }
    });
}

function updateUomCount() {
    const visibleCount = document.querySelectorAll('#uomTableBody tr').length;
    const badge = document.getElementById('uomCountBadge');
    if (badge) badge.innerText = visibleCount;
}
</script>
