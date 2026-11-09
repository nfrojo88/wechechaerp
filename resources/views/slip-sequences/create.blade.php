@extends('layouts.app')

@section('title', 'Configure Slip Sequence')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h4><i class="fas fa-plus me-2"></i>Configure New Slip Sequence</h4>
        </div>
    </div>

    {{-- Error and Notification Alerts --}}
    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 border-start border-4 border-danger mb-4" role="alert">
            <div class="d-flex align-items-center mb-1">
                <i class="fas fa-exclamation-triangle me-2 fs-5 text-danger"></i>
                <strong class="fs-6">Please correct the following errors:</strong>
            </div>
            <ul class="mb-0 ps-4 small">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm mb-4" role="alert">
            <i class="fas fa-times-circle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm mb-4" role="alert">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-body">
                    <form action="{{ route('store-manager.slip-sequences.store') }}" method="POST">
                        @csrf

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Store *</label>
                                <select name="store_id" class="form-select @error('store_id') is-invalid @enderror" required>
                                    <option value="">Select Store</option>
                                    @foreach($stores as $store)
                                    <option value="{{ $store->id }}" {{ old('store_id') == $store->id ? 'selected' : '' }}>{{ $store->name }} ({{ $store->type }})</option>
                                    @endforeach
                                </select>
                                @error('store_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Slip Type *</label>
                                <select name="slip_type" id="slip_type" class="form-select @error('slip_type') is-invalid @enderror" required onchange="updateLabel()">
                                    <option value="">Select Type</option>
                                    <option value="receive" {{ old('slip_type') === 'receive' ? 'selected' : '' }}>Receiving (GRN)</option>
                                    <option value="send" {{ old('slip_type') === 'send' ? 'selected' : '' }}>Outgoing (SIN)</option>
                                </select>
                                @error('slip_type')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Label</label>
                                <input type="text" name="label" id="label" class="form-control @error('label') is-invalid @enderror" 
                                       placeholder="E.g., Receiving (GRN)" value="{{ old('label') }}">
                                <small class="text-muted">Display name for this sequence (auto-filled by Slip Type if left blank)</small>
                                @error('label')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Prefix (Optional)</label>
                                <input type="text" name="prefix" class="form-control @error('prefix') is-invalid @enderror" 
                                       placeholder="E.g., REC or leave blank for numeric only" maxlength="50" value="{{ old('prefix') }}">
                                <small class="text-muted">E.g., REC, OUT - Leave blank for numeric-only slips (e.g., 01042)</small>
                                @error('prefix')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <hr>
                        <h5 class="fw-bold text-dark"><i class="fas fa-book-open me-2 text-primary"></i>Book Range &amp; Next Available Slip</h5>
                        <p class="text-muted small">Define the start and end slip numbers for this physical pad, and choose which number to start issuing from.</p>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Book Start No *</label>
                                <input type="number" name="book_start_no" id="book_start_no" class="form-control font-monospace fw-bold @error('book_start_no') is-invalid @enderror" 
                                       placeholder="E.g., 2100" required min="1" value="{{ old('book_start_no') }}" oninput="syncNextAvailableSlipNo()">
                                <small class="text-muted">First leaf number in physical pad</small>
                                @error('book_start_no')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Book End No *</label>
                                <input type="number" name="book_end_no" id="book_end_no" class="form-control font-monospace fw-bold @error('book_end_no') is-invalid @enderror" 
                                       placeholder="E.g., 2150" required min="2" value="{{ old('book_end_no') }}" oninput="updateBookCapacity()">
                                <small class="text-muted" id="bookCapacityText">Last leaf number in physical pad</small>
                                @error('book_end_no')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-primary">
                                    <i class="fas fa-play-circle me-1"></i>Next Available Slip (Start With No)
                                </label>
                                <input type="number" name="current_slip_no" id="current_slip_no" class="form-control font-monospace fw-bold border-primary shadow-xs @error('current_slip_no') is-invalid @enderror" 
                                       placeholder="Defaults to Start No" min="1" value="{{ old('current_slip_no') }}">
                                <small class="text-muted">Start issuing slips from this number</small>
                                @error('current_slip_no')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="alert alert-info py-2 px-3 small border-start border-4 border-info">
                            <i class="fas fa-lightbulb me-1 text-warning"></i>
                            <strong>Next Available Slip Option:</strong> If you are entering a physical pad where some slips were already used offline/on paper (e.g. pad is numbered 2100 to 2150, but slips up to 2124 were already issued), specify <strong>2125</strong> in <strong>Next Available Slip (Start With No)</strong>. The system will start assigning numbers directly from <strong>2125</strong>.
                        </div>

                        <div class="row mb-3">
                            <div class="col-12">
                                <label class="form-label">Notes</label>
                                <textarea name="notes" class="form-control" rows="3" 
                                          placeholder="E.g., Book #1, Location: Warehouse A">{{ old('notes') }}</textarea>
                            </div>
                        </div>

                        <hr>
                        <div class="d-flex justify-content-end gap-2">
                            <a href="{{ route('store-manager.slip-sequences.index') }}" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary fw-bold shadow-sm">
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
// Track whether user has manually edited the "Next Available Slip No" field
let userEditedNextSlip = {{ old('current_slip_no') ? 'true' : 'false' }};

document.addEventListener('DOMContentLoaded', function() {
    const currentInput = document.getElementById('current_slip_no');
    const startInput = document.getElementById('book_start_no');

    if (currentInput) {
        currentInput.addEventListener('input', function() {
            userEditedNextSlip = (this.value.trim() !== '');
        });
    }

    if (startInput) {
        startInput.addEventListener('input', function() {
            syncNextAvailableSlipNo();
        });
    }

    // On page load: sync if start_no exists and next slip is empty
    if (startInput && startInput.value && currentInput && !currentInput.value) {
        currentInput.value = startInput.value;
    }

    updateBookCapacity();
    updateLabel();
});

function syncNextAvailableSlipNo() {
    const startInput = document.getElementById('book_start_no');
    const currentInput = document.getElementById('current_slip_no');
    if (!startInput || !currentInput) return;

    const startVal = startInput.value.trim();
    if (!userEditedNextSlip && startVal !== '') {
        currentInput.value = startVal;
    }
    updateBookCapacity();
}

function updateBookCapacity() {
    const startInput = document.getElementById('book_start_no');
    const endInput = document.getElementById('book_end_no');
    const currentInput = document.getElementById('current_slip_no');
    const capacityText = document.getElementById('bookCapacityText');

    const startVal = parseInt(startInput?.value) || 0;
    const endVal = parseInt(endInput?.value) || 0;

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
    const slipTypeElem = document.getElementById('slip_type') || document.querySelector('select[name="slip_type"]');
    const labelInput = document.getElementById('label');
    if (!slipTypeElem || !labelInput) return;

    const slipType = slipTypeElem.value;
    if (slipType === 'receive') {
        if (!labelInput.value || labelInput.value === 'Outgoing (SIN)') {
            labelInput.value = 'Receiving (GRN)';
        }
    } else if (slipType === 'send') {
        if (!labelInput.value || labelInput.value === 'Receiving (GRN)') {
            labelInput.value = 'Outgoing (SIN)';
        }
    }
}
</script>
@endpush
@endsection
