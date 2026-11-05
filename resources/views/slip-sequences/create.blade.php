@extends('layouts.app')

@section('title', 'Configure Slip Sequence')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h4><i class="fas fa-plus me-2"></i>Configure New Slip Sequence</h4>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-body">
                    <form action="{{ route('store-manager.slip-sequences.store') }}" method="POST">
                        @csrf

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Store *</label>
                                <select name="store_id" class="form-select" required>
                                    <option value="">Select Store</option>
                                    @foreach($stores as $store)
                                    <option value="{{ $store->id }}">{{ $store->name }} ({{ $store->type }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Slip Type *</label>
                                <select name="slip_type" class="form-select" required onchange="updateLabel()">
                                    <option value="">Select Type</option>
                                    <option value="receive">Receiving (GRN)</option>
                                    <option value="send">Outgoing (SIN)</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Label *</label>
                                <input type="text" name="label" id="label" class="form-control" 
                                       placeholder="E.g., Receiving (GRN)" required>
                                <small class="text-muted">Display name for this sequence</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Prefix (Optional)</label>
                                <input type="text" name="prefix" class="form-control" 
                                       placeholder="E.g., REC or leave blank for numeric only" maxlength="50">
                                <small class="text-muted">E.g., REC, OUT - Leave blank for numeric-only slips (e.g., 01042)</small>
                            </div>
                        </div>

                        <hr>
                        <h5 class="fw-bold text-dark"><i class="fas fa-book-open me-2 text-primary"></i>Book Range &amp; Next Available Slip</h5>
                        <p class="text-muted small">Define the start and end slip numbers for this physical pad, and choose which number to start issuing from.</p>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Book Start No *</label>
                                <input type="number" name="book_start_no" id="book_start_no" class="form-control font-monospace fw-bold" 
                                       placeholder="E.g., 2100" required min="1" value="{{ old('book_start_no') }}" oninput="syncNextAvailableSlipNo()">
                                <small class="text-muted">First leaf number in physical pad</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Book End No *</label>
                                <input type="number" name="book_end_no" id="book_end_no" class="form-control font-monospace fw-bold" 
                                       placeholder="E.g., 2150" required min="2" value="{{ old('book_end_no') }}" oninput="updateBookCapacity()">
                                <small class="text-muted" id="bookCapacityText">Last leaf number in physical pad</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-primary">
                                    <i class="fas fa-play-circle me-1"></i>Next Available Slip (Start With No)
                                </label>
                                <input type="number" name="current_slip_no" id="current_slip_no" class="form-control font-monospace fw-bold border-primary shadow-xs" 
                                       placeholder="Defaults to Start No" min="1" value="{{ old('current_slip_no') }}">
                                <small class="text-muted">Start issuing slips from this number</small>
                            </div>
                        </div>

                        <div class="alert alert-info py-2 px-3 small border-start border-4 border-info">
                            <i class="fas fa-lightbulb me-1 text-warning"></i>
                            <strong>Next Available Slip Option:</strong> If you are entering a physical pad where some slips were already used offline/on paper (e.g. pad is numbered 2100 to 2150, but slips up to 2124 were already issued), specify <strong>2125</strong> in <strong>Next Available Slip (Start With No)</strong>. The system will start assigning numbers directly from <strong>2125</strong>.
                        </div>

                        <div class="row mb-3">
                            <div class="col-12">
                                <div class="form-check form-switch bg-light p-3 rounded border">
                                    <input class="form-check-input ms-0 me-2" type="checkbox" name="archive_previous" id="archivePreviousSwitch" value="1" checked>
                                    <label class="form-check-label fw-semibold text-dark" for="archivePreviousSwitch">
                                        Archive any existing active sequence book for this store & type
                                    </label>
                                    <div class="small text-muted ms-4 ps-1">
                                        Safely preserves all existing slip history. The previous book will be marked as completed/full and never deleted.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-12">
                                <label class="form-label">Notes</label>
                                <textarea name="notes" class="form-control" rows="3" 
                                          placeholder="E.g., Book #1, Location: Warehouse A"></textarea>
                            </div>
                        </div>

                        <hr>
                        <div class="d-flex justify-content-end gap-2">
                            <a href="{{ route('store-manager.slip-sequences.index') }}" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>Save Configuration
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card bg-light shadow-sm">
                <div class="card-header bg-white">
                    <h6 class="mb-0">Configuration Tips</h6>
                </div>
                <div class="card-body">
                    <h6><i class="fas fa-arrow-down text-success me-2"></i>GRN - Receiving</h6>
                    <p class="small">Use this for goods received from suppliers. Typical prefix: REC or GRN</p>

                    <h6><i class="fas fa-arrow-up text-info me-2"></i>SIN - Outgoing</h6>
                    <p class="small">Use this for materials sent to project sites. Typical prefix: OUT or SIN</p>

                    <hr>
                    <h6>Book Numbers</h6>
                    <p class="small">Match these with your physical slip books. The system will track sequence and flag gaps if physical numbers don't match.</p>

                    <h6>Prefix</h6>
                    <ul class="small">
                        <li><strong>With prefix:</strong> REC2100, REC2101, REC2102...</li>
                        <li><strong>Without prefix:</strong> 2100, 2101, 2102...</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
let userEditedNextSlip = false;

document.addEventListener('DOMContentLoaded', function() {
    const currentInput = document.getElementById('current_slip_no');
    if (currentInput) {
        currentInput.addEventListener('input', function() {
            userEditedNextSlip = (this.value.trim() !== '');
        });
    }
});

function syncNextAvailableSlipNo() {
    const startVal = document.getElementById('book_start_no')?.value;
    const currentInput = document.getElementById('current_slip_no');
    if (currentInput && !userEditedNextSlip) {
        currentInput.value = startVal;
    }
    updateBookCapacity();
}

function updateBookCapacity() {
    const startVal = parseInt(document.getElementById('book_start_no')?.value) || 0;
    const endVal = parseInt(document.getElementById('book_end_no')?.value) || 0;
    const currentInput = document.getElementById('current_slip_no');
    const capacityText = document.getElementById('bookCapacityText');

    if (startVal > 0 && currentInput) {
        currentInput.min = startVal;
    }
    if (endVal > 0 && currentInput) {
        currentInput.max = endVal;
    }

    if (startVal > 0 && endVal >= startVal && capacityText) {
        const capacity = endVal - startVal + 1;
        capacityText.innerHTML = `Total capacity: <strong>${capacity}</strong> slips`;
    }
}

function updateLabel() {
    const slipType = document.querySelector('select[name="slip_type"]').value;
    const labelInput = document.getElementById('label');
    
    if (slipType === 'receive') {
        labelInput.value = 'Receiving (GRN)';
    } else if (slipType === 'send') {
        labelInput.value = 'Outgoing (SIN)';
    }
}
</script>
@endpush
@endsection
