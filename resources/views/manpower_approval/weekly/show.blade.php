@extends('layouts.app')
@section('title', 'Weekly Manpower Batch ' . $batch->batch_number)
@section('content')
<div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 pb-3 border-bottom gap-2">
        <div>
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('manpower-approval.weekly.index', ['project_id' => $batch->project_id]) }}" class="btn btn-sm btn-outline-secondary">
                    <i class="fa-solid fa-arrow-left"></i>
                </a>
                <h4 class="fw-bold text-dark mb-0">
                    <i class="fa-solid fa-file-invoice-dollar text-primary me-2"></i>Weekly Batch: <span class="font-monospace text-primary">{{ $batch->batch_number }}</span>
                </h4>
                <span class="badge {{ $batch->status === 'Paid' ? 'bg-success' : ($batch->status === 'GM_Approved' ? 'bg-info text-white' : ($batch->status === 'Rejected' ? 'bg-danger' : 'bg-warning text-dark')) }} font-monospace px-2 py-1">
                    {{ str_replace('_', ' ', $batch->status) }}
                </span>
            </div>
            <p class="text-muted small mb-0 mt-1">
                Project: <strong>{{ $batch->project?->name }}</strong> &bull; Week: <strong>{{ $batch->week_start->format('M d') }} &ndash; {{ $batch->week_end->format('M d, Y') }}</strong> &bull; Prepared by: <strong>{{ $batch->preparedBy?->name }}</strong>
            </p>
        </div>

        {{-- GM Decision Buttons --}}
        @if($canGmAct)
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-outline-danger shadow-xs" data-bs-toggle="modal" data-bs-target="#gmRejectModal">
                <i class="fa-solid fa-rotate-left me-1"></i>Return to HR
            </button>
            <form action="{{ route('manpower-approval.weekly.gm-process', $batch->id) }}" method="POST" class="d-inline">
                @csrf
                <input type="hidden" name="decision" value="approve">
                <button type="submit" class="btn btn-sm btn-success shadow-xs">
                    <i class="fa-solid fa-stamp me-1"></i>GM Authorize for Payment
                </button>
            </form>
        </div>
        @endif

        {{-- Finance Payment Buttons --}}
        @if($canFinanceAct)
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-outline-warning shadow-xs" data-bs-toggle="modal" data-bs-target="#financeHoldModal">
                <i class="fa-solid fa-pause me-1"></i>Hold Batch
            </button>
            <button type="button" class="btn btn-sm btn-success shadow-xs" data-bs-toggle="modal" data-bs-target="#financePayModal">
                <i class="fa-solid fa-money-bill-transfer me-1"></i>Execute Payment
            </button>
        </div>
        @endif
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-xs rounded-3 py-2 px-3 mb-3 d-flex align-items-center gap-2">
        <i class="fa-solid fa-circle-check text-success fs-5"></i>
        <div class="small flex-grow-1">{{ session('success') }}</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    @if(session('warning'))
    <div class="alert alert-warning alert-dismissible fade show border-0 shadow-xs rounded-3 py-2 px-3 mb-3 d-flex align-items-center gap-2">
        <i class="fa-solid fa-circle-exclamation text-warning fs-5"></i>
        <div class="small flex-grow-1">{{ session('warning') }}</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    {{-- Payment Proof Banner if Paid --}}
    @if($batch->status === 'Paid')
    <div class="card border-success border-opacity-50 shadow-xs rounded-3 mb-3 bg-success bg-opacity-10">
        <div class="card-body p-3">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h6 class="fw-bold text-success mb-1">
                        <i class="fa-solid fa-circle-check me-1"></i>Payment Completed &amp; Cost Posted
                    </h6>
                    <div class="small text-dark">
                        Paid on <strong>{{ optional($batch->payment_date)->format('M d, Y') }}</strong> via <strong>{{ $batch->payment_method }}</strong>
                        @if($batch->payment_reference) &bull; Ref: <span class="font-monospace fw-bold">{{ $batch->payment_reference }}</span> @endif
                        &bull; Paid By: <strong>{{ $batch->paidByFinance?->name ?? 'Finance Officer' }}</strong>
                    </div>
                </div>
                @if($batch->payment_attachment)
                <a href="{{ $batch->payment_attachment }}" target="_blank" class="btn btn-sm btn-outline-success shadow-xs">
                    <i class="fa-solid fa-paperclip me-1"></i>View Payment Proof
                </a>
                @endif
            </div>
        </div>
    </div>
    @endif

    {{-- Financial KPI Cards --}}
    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-xs rounded-3 bg-white p-3 h-100">
                <span class="text-muted small text-uppercase fw-semibold" style="font-size: 0.68rem;">Total Workers</span>
                <div class="fs-4 fw-bold font-monospace text-dark">{{ $batch->total_workers_count }}</div>
                <span class="text-muted small" style="font-size: 0.72rem;">{{ number_format($batch->total_days_worked, 1) }} total days worked</span>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-xs rounded-3 bg-white p-3 h-100">
                <span class="text-muted small text-uppercase fw-semibold" style="font-size: 0.68rem;">Gross Wages</span>
                <div class="fs-4 fw-bold font-monospace text-primary">ETB {{ number_format($batch->total_gross_amount, 2) }}</div>
                <span class="text-muted small" style="font-size: 0.72rem;">Before deductions</span>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-xs rounded-3 bg-white p-3 h-100">
                <span class="text-danger small text-uppercase fw-semibold" style="font-size: 0.68rem;">Deductions &amp; Adv</span>
                <div class="fs-4 fw-bold font-monospace text-danger">-ETB {{ number_format($batch->total_deductions + $batch->total_advances, 2) }}</div>
                <span class="text-muted small" style="font-size: 0.72rem;">Ded: {{ number_format($batch->total_deductions, 2) }} &bull; Adv: {{ number_format($batch->total_advances, 2) }}</span>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-xs rounded-3 bg-success bg-opacity-10 border-success border-opacity-25 p-3 h-100">
                <span class="text-success small text-uppercase fw-semibold" style="font-size: 0.68rem;">Net Payable</span>
                <div class="fs-4 fw-bold font-monospace text-success">ETB {{ number_format($batch->total_net_payable, 2) }}</div>
                <span class="text-success text-opacity-75 small" style="font-size: 0.72rem;">Final disbursement amount</span>
            </div>
        </div>
    </div>

    {{-- Worker Items Breakdown Table --}}
    <div class="card border-0 shadow-xs rounded-3 bg-white mb-4">
        <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
            <strong class="text-dark small"><i class="fa-solid fa-users text-primary me-1"></i>Worker Payroll Breakdown</strong>
            <span class="badge bg-light text-dark border font-monospace">{{ $batch->items->count() }} Workers</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0 small text-center">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 40px;">#</th>
                            <th class="text-start">Worker Name &amp; Code</th>
                            <th>Trade</th>
                            <th>Days Worked</th>
                            <th>Reg. Hours</th>
                            <th>OT Hours</th>
                            <th>Gross Wages</th>
                            <th class="text-danger">Deductions</th>
                            <th class="text-danger">Advances</th>
                            <th class="bg-success-subtle text-success">Net Payable</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($batch->items as $idx => $item)
                        @php $w = $item->worker; @endphp
                        <tr>
                            <td class="font-monospace text-muted">{{ $idx + 1 }}</td>
                            <td class="text-start">
                                <strong class="text-dark d-block">{{ $w?->name ?? 'Worker' }}</strong>
                                <span class="font-monospace text-primary" style="font-size: 0.68rem;">{{ $w?->worker_code }}</span>
                                @if($w?->phone)
                                <span class="text-muted" style="font-size: 0.68rem;">&bull; {{ $w->phone }}</span>
                                @endif
                            </td>
                            <td><span class="badge bg-light text-dark border">{{ $w?->trade ?: 'General' }}</span></td>
                            <td class="font-monospace">{{ number_format($item->days_worked, 1) }}</td>
                            <td class="font-monospace">{{ number_format($item->total_regular_hours, 1) }}h</td>
                            <td class="font-monospace">{{ $item->total_overtime_hours > 0 ? number_format($item->total_overtime_hours, 1) . 'h' : '—' }}</td>
                            <td class="font-monospace">ETB {{ number_format($item->gross_amount, 2) }}</td>
                            <td class="font-monospace text-danger">{{ $item->deductions > 0 ? '-ETB ' . number_format($item->deductions, 2) : '—' }}</td>
                            <td class="font-monospace text-danger">{{ $item->advances > 0 ? '-ETB ' . number_format($item->advances, 2) : '—' }}</td>
                            <td class="font-monospace fw-bold text-success fs-6 bg-success-subtle">
                                ETB {{ number_format($item->net_payable, 2) }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="table-light fw-bold font-monospace">
                        <tr>
                            <td colspan="6" class="text-end">TOTALS:</td>
                            <td>ETB {{ number_format($batch->total_gross_amount, 2) }}</td>
                            <td class="text-danger">-ETB {{ number_format($batch->total_deductions, 2) }}</td>
                            <td class="text-danger">-ETB {{ number_format($batch->total_advances, 2) }}</td>
                            <td class="text-success fs-6">ETB {{ number_format($batch->total_net_payable, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    {{-- Daily Sheets Included & Audit Trail --}}
    <div class="row g-3 mb-4">
        {{-- Sheets Included --}}
        <div class="col-lg-6">
            <div class="card border-0 shadow-xs rounded-3 bg-white h-100">
                <div class="card-header bg-white py-2 px-3 border-bottom">
                    <strong class="text-dark small"><i class="fa-solid fa-list-check text-primary me-1"></i>Consolidated Daily Sheets ({{ $batch->dailySheets->count() }})</strong>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 280px;">
                        <table class="table table-hover align-middle mb-0 small text-center">
                            <thead class="table-light">
                                <tr>
                                    <th>Sheet #</th>
                                    <th>Date</th>
                                    <th>Headcount</th>
                                    <th>Amount</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($batch->dailySheets as $ds)
                                <tr>
                                    <td>
                                        <a href="{{ route('manpower-approval.review.show', $ds->id) }}" class="fw-bold font-monospace text-primary text-decoration-none">
                                            {{ $ds->sheet_number }}
                                        </a>
                                    </td>
                                    <td>{{ $ds->date->format('M d, Y') }}</td>
                                    <td>{{ $ds->total_headcount }}</td>
                                    <td class="font-monospace fw-bold">ETB {{ number_format($ds->effective_total_amount, 2) }}</td>
                                    <td>
                                        <a href="{{ route('manpower-approval.review.show', $ds->id) }}" class="btn btn-xs btn-outline-secondary">
                                            <i class="fa-solid fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Approval Logs --}}
        <div class="col-lg-6">
            <div class="card border-0 shadow-xs rounded-3 bg-white h-100">
                <div class="card-header bg-white py-2 px-3 border-bottom">
                    <strong class="text-dark small"><i class="fa-solid fa-clock-rotate-left text-primary me-1"></i>Batch Audit Trail</strong>
                </div>
                <div class="card-body p-3">
                    <ul class="list-unstyled mb-0 small">
                        @forelse($batch->approvalLogs as $log)
                        <li class="d-flex align-items-start gap-2 mb-2 pb-2 border-bottom">
                            <i class="fa-solid fa-circle-check text-success mt-1"></i>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-center">
                                    <strong class="text-dark">{{ ucwords(str_replace('_', ' ', $log->stage)) }}: {{ ucfirst($log->action) }}</strong>
                                    <span class="text-muted font-monospace" style="font-size: 0.68rem;">{{ $log->created_at->format('M d, H:i') }}</span>
                                </div>
                                <div class="text-muted" style="font-size: 0.72rem;">By: {{ $log->user?->name }} &bull; {{ $log->comment }}</div>
                            </div>
                        </li>
                        @empty
                        <li class="text-muted py-3 text-center">Batch generated and submitted to GM.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- MODAL: GM Reject Batch --}}
@if($canGmAct)
<div class="modal fade" id="gmRejectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-3 border-0 shadow">
            <form action="{{ route('manpower-approval.weekly.gm-process', $batch->id) }}" method="POST">
                @csrf
                <input type="hidden" name="decision" value="reject">
                <div class="modal-header bg-danger text-white py-2 px-3">
                    <h6 class="modal-title fw-bold">
                        <i class="fa-solid fa-rotate-left me-1"></i>Return Batch to HR Officer
                    </h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-3">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Rejection Reason <span class="text-danger">*</span></label>
                        <select name="reason" class="form-select form-select-sm" required>
                            <option value="">Select Reason...</option>
                            <option value="Budget exceeded">Budget exceeded</option>
                            <option value="Incorrect overtime rates">Incorrect overtime rates</option>
                            <option value="Missing deduction adjustment">Missing deduction adjustment</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Executive Feedback <span class="text-danger">*</span></label>
                        <textarea name="comment" rows="3" class="form-control form-control-sm" placeholder="Specify instructions for the HR Officer..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 px-3">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-danger">Confirm Return</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

{{-- MODAL: Finance Record Payment --}}
@if($canFinanceAct)
<div class="modal fade" id="financePayModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-3 border-0 shadow">
            <form action="{{ route('manpower-approval.payments.pay', $batch->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header bg-success text-white py-2 px-3">
                    <h6 class="modal-title fw-bold">
                        <i class="fa-solid fa-hand-holding-dollar me-1"></i>Execute Batch Payment
                    </h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-3">
                    <div class="alert alert-info py-2 small mb-3">
                        Total Net Payable: <strong class="fs-6 font-monospace">ETB {{ number_format($batch->total_net_payable, 2) }}</strong>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Payment Date <span class="text-danger">*</span></label>
                            <input type="date" name="payment_date" class="form-control form-control-sm" value="{{ today()->toDateString() }}" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Payment Method <span class="text-danger">*</span></label>
                            <select name="payment_method" class="form-select form-select-sm" required>
                                <option value="Bank Transfer">Bank Transfer (CBE / Awash)</option>
                                <option value="Telebirr">Telebirr SuperApp</option>
                                <option value="CBE Birr">CBE Birr</option>
                                <option value="Cash">Cash Payroll</option>
                                <option value="Check">Company Check</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-bold">Reference / Transaction Number</label>
                        <input type="text" name="payment_reference" class="form-control form-control-sm" placeholder="e.g. FT26090123456 or Check #4592">
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-bold">Payment Receipt / Proof Voucher (Optional)</label>
                        <input type="file" name="payment_attachment" class="form-control form-control-sm" accept=".pdf,.png,.jpg,.jpeg">
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-bold">Finance Notes</label>
                        <textarea name="payment_notes" rows="2" class="form-control form-control-sm" placeholder="Additional ledger / bank notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 px-3">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-success">
                        <i class="fa-solid fa-check me-1"></i>Confirm &amp; Post Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL: Finance Hold Batch --}}
<div class="modal fade" id="financeHoldModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-3 border-0 shadow">
            <form action="{{ route('manpower-approval.payments.hold', $batch->id) }}" method="POST">
                @csrf
                <div class="modal-header bg-warning text-dark py-2 px-3">
                    <h6 class="modal-title fw-bold">
                        <i class="fa-solid fa-pause me-1"></i>Put Batch on Hold
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-3">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Hold Reason <span class="text-danger">*</span></label>
                        <textarea name="hold_reason" rows="3" class="form-control form-control-sm" placeholder="Specify why payment is held (e.g. bank liquidity, audit inquiry)..." required minlength="5"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 px-3">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-warning">Confirm Hold</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@endsection
