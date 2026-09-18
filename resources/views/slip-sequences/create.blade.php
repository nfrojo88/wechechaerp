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
                        <h5>Book Range</h5>
                        <p class="text-muted small">Define the start and end slip numbers for this book</p>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Book Start No *</label>
                                <input type="number" name="book_start_no" class="form-control" 
                                       placeholder="E.g., 2100" required min="1">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Book End No *</label>
                                <input type="number" name="book_end_no" class="form-control" 
                                       placeholder="E.g., 2150" required min="2">
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <i class="fas fa-lightbulb me-2"></i>
                            <strong>Example:</strong> If you have a physical slip book numbered 2100 to 2150, 
                            enter those exact numbers. The system will then automatically assign 2100, 2101, 2102, etc.
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
