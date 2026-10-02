@extends('layouts.app')
@section('title', 'HR Officer - Weekly Manpower Collection')
@section('content')
<div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 pb-3 border-bottom gap-2">
        <div>
            <h4 class="fw-bold text-dark mb-0">
                <i class="fa-solid fa-calendar-week text-primary me-2"></i>Weekly Manpower Collection &amp; Batching
            </h4>
            <p class="text-muted small mb-0 mt-1">
                Collect HR-approved daily sheets, aggregate worker wages by week (Mon&ndash;Sun), apply deductions/advances, and generate GM payment batches.
            </p>
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-xs rounded-3 py-2 px-3 mb-3 d-flex align-items-center gap-2">
        <i class="fa-solid fa-circle-check text-success fs-5"></i>
        <div class="small flex-grow-1">{{ session('success') }}</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    {{-- Filter by Project --}}
    <div class="card border-0 shadow-xs rounded-3 mb-3 bg-white">
        <div class="card-body p-3">
            <form action="{{ route('manpower-approval.weekly.index') }}" method="GET" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-dark mb-1">Select Project / Site</label>
                    <select name="project_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        @foreach($projects as $p)
                        <option value="{{ $p->id }}" {{ $projectId == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
        </div>
    </div>

    {{-- Section 1: Unbatched HR-Approved Daily Sheets (Ready for Batching) --}}
    <div class="card border-0 shadow-xs rounded-3 mb-4 bg-white">
        <div class="card-header bg-white py-3 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h6 class="fw-bold text-dark mb-0">
                    <i class="fa-solid fa-circle-check text-success me-1"></i>Approved Sheets Pool (Unbatched)
                </h6>
                <small class="text-muted">Select daily sheets to consolidate into a weekly payment batch.</small>
            </div>
            <div>
                <button type="button" class="btn btn-sm btn-primary shadow-xs" onclick="proceedToBatchCreation()">
                    <i class="fa-solid fa-boxes-packing me-1"></i>Create Weekly Batch from Selected
                </button>
            </div>
        </div>

        <form action="{{ route('manpower-approval.weekly.create-batch') }}" method="POST" id="batchSelectForm">
            @csrf
            <input type="hidden" name="project_id" value="{{ $projectId }}">

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small text-center">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 40px;">
                                    <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll(this)">
                                </th>
                                <th>Sheet #</th>
                                <th>Date</th>
                                <th>Site Engineer</th>
                                <th>Trade</th>
                                <th>Headcount</th>
                                <th>Hours</th>
                                <th>Net Amount</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($unbatchedSheets as $sheet)
                            <tr>
                                <td>
                                    <input type="checkbox" name="sheet_ids[]" value="{{ $sheet->id }}" class="sheet-checkbox">
                                </td>
                                <td>
                                    <a href="{{ route('manpower-approval.review.show', $sheet->id) }}" class="fw-bold font-monospace text-primary text-decoration-none">
                                        {{ $sheet->sheet_number }}
                                    </a>
                                </td>
                                <td>{{ $sheet->date->format('M d, Y (D)') }}</td>
                                <td>{{ $sheet->siteEngineer?->name }}</td>
                                <td><span class="badge bg-light text-dark border">{{ $sheet->trade ?: 'General' }}</span></td>
                                <td><span class="badge bg-light text-dark border font-monospace">{{ $sheet->total_headcount }}</span></td>
                                <td class="font-monospace">{{ number_format($sheet->total_regular_hours + $sheet->total_overtime_hours, 1) }}h</td>
                                <td class="font-monospace fw-bold text-success">ETB {{ number_format($sheet->effective_total_amount, 2) }}</td>
                                <td>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                                        HR Approved
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('manpower-approval.review.show', $sheet->id) }}" class="btn btn-xs btn-outline-secondary">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="10" class="py-4 text-center text-muted">
                                    No unbatched HR-approved sheets currently available for this project.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </form>
    </div>

    {{-- Section 2: Generated Weekly Batches --}}
    <div class="card border-0 shadow-xs rounded-3 bg-white mb-4">
        <div class="card-header bg-white py-3 px-3 border-bottom">
            <h6 class="fw-bold text-dark mb-0">
                <i class="fa-solid fa-file-invoice-dollar text-primary me-1"></i>Weekly Payment Batches Archive
            </h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small text-center">
                    <thead class="table-light">
                        <tr>
                            <th>Batch Number</th>
                            <th>Week Range</th>
                            <th>Workers</th>
                            <th>Days Worked</th>
                            <th>Gross Total</th>
                            <th>Deductions / Adv</th>
                            <th>Net Payable</th>
                            <th>Batch Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($batches as $batch)
                        <tr>
                            <td>
                                <a href="{{ route('manpower-approval.weekly.show', $batch->id) }}" class="fw-bold font-monospace text-primary text-decoration-none">
                                    {{ $batch->batch_number }}
                                </a>
                            </td>
                            <td>{{ $batch->week_start->format('M d') }} &ndash; {{ $batch->week_end->format('M d, Y') }}</td>
                            <td><span class="badge bg-light text-dark border font-monospace">{{ $batch->total_workers_count }}</span></td>
                            <td class="font-monospace">{{ number_format($batch->total_days_worked, 1) }}</td>
                            <td class="font-monospace">ETB {{ number_format($batch->total_gross_amount, 2) }}</td>
                            <td class="font-monospace text-danger">-ETB {{ number_format($batch->total_deductions + $batch->total_advances, 2) }}</td>
                            <td class="font-monospace fw-bold text-success fs-6">ETB {{ number_format($batch->total_net_payable, 2) }}</td>
                            <td>
                                @if($batch->status === 'Submitted_GM')
                                    <span class="badge bg-warning text-dark">Pending GM Approval</span>
                                @elseif($batch->status === 'GM_Approved')
                                    <span class="badge bg-info text-white">Pending Finance Payment</span>
                                @elseif($batch->status === 'Paid')
                                    <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i>Paid</span>
                                @elseif($batch->status === 'Held')
                                    <span class="badge bg-warning text-dark">On Hold</span>
                                @elseif($batch->status === 'Rejected')
                                    <span class="badge bg-danger">Returned by GM</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('manpower-approval.weekly.show', $batch->id) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="fa-solid fa-eye me-1"></i>View Batch
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="py-4 text-center text-muted">No weekly payment batches created yet for this project.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($batches->hasPages())
            <div class="p-3 border-top">
                {{ $batches->links() }}
            </div>
            @endif
        </div>
    </div>
</div>

<script>
function toggleSelectAll(master) {
    document.querySelectorAll('.sheet-checkbox').forEach(cb => cb.checked = master.checked);
}

function proceedToBatchCreation() {
    const checked = document.querySelectorAll('.sheet-checkbox:checked');
    if (checked.length === 0) {
        alert('Please select at least one daily manpower sheet to batch.');
        return;
    }
    document.getElementById('batchSelectForm').submit();
}
</script>
@endsection
