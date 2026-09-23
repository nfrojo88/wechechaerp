@extends('layouts.app')

@section('title', 'Receipt — ' . $receipt->receipt_number)

@section('content')
<div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
    <a href="{{ route('receipts.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="fa-solid fa-arrow-left"></i>
    </a>
    <div class="flex-fill">
        <h1 class="h3 mb-0 fw-bold">
            <i class="fa-solid fa-receipt text-primary me-2"></i>{{ $receipt->receipt_number }}
        </h1>
        <div class="text-muted small">
            Uploaded by {{ $receipt->uploader->name ?? '—' }}
            on {{ $receipt->created_at->format('d M Y, H:i') }}
        </div>
    </div>

    {{-- Status badge --}}
    @php
        $badgeMap = ['approved' => 'success', 'rejected' => 'danger', 'pending' => 'warning'];
        $badge    = $badgeMap[$receipt->status] ?? 'secondary';
    @endphp
    <span class="badge bg-{{ $badge }} fs-6 px-3 py-2">{{ ucfirst($receipt->status) }}</span>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4">
        <i class="fa-solid fa-circle-check me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if(session('warning'))
    <div class="alert alert-warning alert-dismissible fade show border-0 shadow-sm mb-4">
        <i class="fa-solid fa-triangle-exclamation me-2"></i>{{ session('warning') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="row g-4">

    {{-- ── LEFT: Image Preview + OCR Text ─────────────────────────────── --}}
    <div class="col-lg-5">

        {{-- File preview --}}
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-transparent border-bottom py-2">
                <h6 class="mb-0 fw-semibold"><i class="fa-solid fa-image text-secondary me-2"></i>Receipt File</h6>
            </div>
            <div class="card-body text-center p-3">
                @if($receipt->file_type === 'pdf')
                    <div class="py-4">
                        <i class="fa-solid fa-file-pdf fa-4x text-danger mb-3 d-block"></i>
                        <a href="{{ Storage::url($receipt->file_path) }}" target="_blank"
                           class="btn btn-outline-danger btn-sm">
                            <i class="fa-solid fa-external-link-alt me-1"></i>Open PDF
                        </a>
                    </div>
                @else
                    <img src="{{ Storage::url($receipt->file_path) }}"
                         alt="Receipt"
                         class="img-fluid rounded shadow-sm"
                         style="max-height:380px;object-fit:contain;">
                @endif
            </div>
        </div>

        {{-- OCR parse status --}}
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-transparent border-bottom py-2 d-flex justify-content-between">
                <h6 class="mb-0 fw-semibold"><i class="fa-solid fa-eye text-secondary me-2"></i>OCR Parse Result</h6>
                @if($receipt->parse_status === 'parsed')
                    <span class="badge bg-success-subtle text-success"><i class="fa-solid fa-check me-1"></i>Parsed</span>
                @elseif($receipt->parse_status === 'failed')
                    <span class="badge bg-danger-subtle text-danger"><i class="fa-solid fa-xmark me-1"></i>Failed</span>
                @endif
            </div>
            <div class="card-body p-0">
                @if($receipt->parse_error)
                    <div class="alert alert-warning m-3 mb-0 small">
                        <strong>OCR Error:</strong> {{ $receipt->parse_error }}
                    </div>
                @endif

                {{-- Raw OCR text collapsible --}}
                @if($receipt->ocr_raw_text)
                <div class="px-3 py-2">
                    <button class="btn btn-sm btn-outline-secondary w-100"
                            type="button" data-bs-toggle="collapse" data-bs-target="#ocrRawText">
                        <i class="fa-solid fa-align-left me-1"></i>Show Raw OCR Text
                    </button>
                    <div class="collapse mt-2" id="ocrRawText">
                        <pre class="bg-light rounded p-3 small" style="max-height:250px;overflow-y:auto;white-space:pre-wrap;word-break:break-word;">{{ $receipt->ocr_raw_text }}</pre>
                    </div>
                </div>
                @else
                    <p class="text-muted small m-3">No OCR text captured.</p>
                @endif
            </div>
        </div>

    </div>

    {{-- ── RIGHT: Parsed Fields + Actions ─────────────────────────────── --}}
    <div class="col-lg-7">

        {{-- Editable fields form --}}
        @if(auth()->user()->hasPermissionTo('manage-receipts') && $receipt->status === 'pending')
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-transparent border-bottom py-2">
                <h6 class="mb-0 fw-semibold"><i class="fa-solid fa-pen-to-square text-primary me-2"></i>Parsed Fields <small class="text-muted fw-normal">(edit & correct as needed)</small></h6>
            </div>
            <div class="card-body">
                <form action="{{ route('receipts.update', $receipt) }}" method="POST" id="form-update-receipt">
                    @csrf @method('PUT')
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label form-label-sm">Vendor Name</label>
                            <input type="text" name="vendor_name" class="form-control form-control-sm"
                                   value="{{ old('vendor_name', $receipt->vendor_name) }}"
                                   placeholder="e.g. ABC Trading PLC">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-sm">Vendor TIN</label>
                            <input type="text" name="vendor_tin" class="form-control form-control-sm"
                                   value="{{ old('vendor_tin', $receipt->vendor_tin) }}"
                                   placeholder="10-digit TIN">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-sm">Receipt Date</label>
                            <input type="date" name="receipt_date" class="form-control form-control-sm"
                                   value="{{ old('receipt_date', $receipt->receipt_date?->toDateString()) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-sm">Category</label>
                            <select name="category" class="form-select form-select-sm">
                                @foreach($categories as $key => $label)
                                    <option value="{{ $key }}" @selected(old('category', $receipt->category) == $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label form-label-sm">Subtotal (ETB)</label>
                            <input type="number" name="subtotal" step="0.01" min="0"
                                   class="form-control form-control-sm"
                                   value="{{ old('subtotal', $receipt->subtotal) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label form-label-sm">VAT (ETB)</label>
                            <input type="number" name="vat_amount" step="0.01" min="0"
                                   class="form-control form-control-sm"
                                   value="{{ old('vat_amount', $receipt->vat_amount) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label form-label-sm">Total Amount (ETB)</label>
                            <input type="number" name="total_amount" step="0.01" min="0"
                                   class="form-control form-control-sm fw-bold"
                                   value="{{ old('total_amount', $receipt->total_amount) }}"
                                   id="field-total">
                        </div>
                        <div class="col-12">
                            <label class="form-label form-label-sm">Description</label>
                            <textarea name="description" rows="2" class="form-control form-control-sm"
                                      placeholder="Brief description of what was purchased">{{ old('description', $receipt->description) }}</textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label form-label-sm">Notes</label>
                            <textarea name="notes" rows="2" class="form-control form-control-sm"
                                      placeholder="Internal notes...">{{ old('notes', $receipt->notes) }}</textarea>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-sm btn-primary">
                            <i class="fa-solid fa-floppy-disk me-1"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
        @else
        {{-- Read-only view when not pending --}}
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-transparent border-bottom py-2">
                <h6 class="mb-0 fw-semibold"><i class="fa-solid fa-circle-info text-secondary me-2"></i>Receipt Details</h6>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4 text-muted small fw-normal">Vendor</dt>
                    <dd class="col-sm-8 small">{{ $receipt->vendor_name ?? '—' }}</dd>

                    <dt class="col-sm-4 text-muted small fw-normal">TIN</dt>
                    <dd class="col-sm-8 small">{{ $receipt->vendor_tin ?? '—' }}</dd>

                    <dt class="col-sm-4 text-muted small fw-normal">Receipt Date</dt>
                    <dd class="col-sm-8 small">{{ $receipt->receipt_date?->format('d M Y') ?? '—' }}</dd>

                    <dt class="col-sm-4 text-muted small fw-normal">Category</dt>
                    <dd class="col-sm-8 small">{{ $categories[$receipt->category] ?? $receipt->category }}</dd>

                    <dt class="col-sm-4 text-muted small fw-normal">Subtotal</dt>
                    <dd class="col-sm-8 small">ETB {{ number_format($receipt->subtotal, 2) }}</dd>

                    <dt class="col-sm-4 text-muted small fw-normal">VAT</dt>
                    <dd class="col-sm-8 small">ETB {{ number_format($receipt->vat_amount, 2) }}</dd>

                    <dt class="col-sm-4 text-muted small fw-normal">Total</dt>
                    <dd class="col-sm-8 fw-bold">ETB {{ number_format($receipt->total_amount, 2) }}</dd>

                    <dt class="col-sm-4 text-muted small fw-normal">Description</dt>
                    <dd class="col-sm-8 small">{{ $receipt->description ?? '—' }}</dd>

                    <dt class="col-sm-4 text-muted small fw-normal">Notes</dt>
                    <dd class="col-sm-8 small">{{ $receipt->notes ?? '—' }}</dd>
                </dl>
            </div>
        </div>
        @endif

        {{-- Approval Actions --}}
        @if(auth()->user()->hasPermissionTo('manage-receipts') && $receipt->status === 'pending')
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-transparent border-bottom py-2">
                <h6 class="mb-0 fw-semibold"><i class="fa-solid fa-gavel text-secondary me-2"></i>Actions</h6>
            </div>
            <div class="card-body d-flex gap-2 flex-wrap">
                <form action="{{ route('receipts.approve', $receipt) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-success"
                            onclick="return confirm('Approve this receipt?')">
                        <i class="fa-solid fa-circle-check me-1"></i>Approve
                    </button>
                </form>
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="collapse"
                        data-bs-target="#reject-form">
                    <i class="fa-solid fa-ban me-1"></i>Reject
                </button>
            </div>
            <div class="collapse" id="reject-form">
                <div class="card-body border-top pt-3">
                    <form action="{{ route('receipts.reject', $receipt) }}" method="POST">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label form-label-sm">Rejection Reason <span class="text-muted">(optional)</span></label>
                            <textarea name="rejection_reason" rows="2" class="form-control form-control-sm"
                                      placeholder="Explain why this receipt is being rejected..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-danger btn-sm">
                            <i class="fa-solid fa-ban me-1"></i>Confirm Rejection
                        </button>
                    </form>
                </div>
            </div>
        </div>
        @endif

        {{-- Approval trail --}}
        @if($receipt->approved_by || $receipt->status !== 'pending')
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-bottom py-2">
                <h6 class="mb-0 fw-semibold"><i class="fa-solid fa-timeline text-secondary me-2"></i>Audit Trail</h6>
            </div>
            <div class="card-body small">
                <ul class="list-unstyled mb-0">
                    <li class="d-flex align-items-start gap-2 mb-2">
                        <span class="badge bg-primary rounded-circle p-1 mt-1" style="width:8px;height:8px;"></span>
                        <span>
                            Uploaded by <strong>{{ $receipt->uploader->name ?? '—' }}</strong>
                            on {{ $receipt->created_at->format('d M Y, H:i') }}
                        </span>
                    </li>
                    @if($receipt->approved_by && $receipt->approver)
                    <li class="d-flex align-items-start gap-2">
                        <span class="badge bg-{{ $badge }} rounded-circle p-1 mt-1" style="width:8px;height:8px;"></span>
                        <span>
                            {{ ucfirst($receipt->status) }} by <strong>{{ $receipt->approver->name }}</strong>
                            on {{ $receipt->approved_at?->format('d M Y, H:i') ?? '—' }}
                        </span>
                    </li>
                    @endif
                </ul>
            </div>
        </div>
        @endif

        {{-- Admin delete --}}
        @if(auth()->user()->hasAnyRole(['admin','global_admin']))
        <div class="mt-3 text-end">
            <form action="{{ route('receipts.destroy', $receipt) }}" method="POST"
                  onsubmit="return confirm('Permanently delete this receipt? This cannot be undone.')">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-sm btn-outline-danger">
                    <i class="fa-solid fa-trash me-1"></i>Delete Receipt
                </button>
            </form>
        </div>
        @endif

    </div>
</div>
@endsection
