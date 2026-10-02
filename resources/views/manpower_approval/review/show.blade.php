@extends('layouts.app')
@section('title', 'Review Manpower Sheet ' . $sheet->sheet_number)
@section('content')
<div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 pb-3 border-bottom gap-2">
        <div>
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('manpower-approval.review.inbox') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="fa-solid fa-arrow-left"></i>
                </a>
                <h4 class="fw-bold text-dark mb-0">
                    <i class="fa-solid fa-clipboard-check text-primary me-2"></i>Review Sheet: <span class="font-monospace text-primary">{{ $sheet->sheet_number }}</span>
                </h4>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace px-2 py-1">
                    {{ $sheet->status }}
                </span>
            </div>
            <p class="text-muted small mb-0 mt-1">
                Project: <strong>{{ $sheet->project?->name }}</strong> &bull; Date: <strong>{{ $sheet->date->format('M d, Y (l)') }}</strong> &bull; Submitted by: <strong>{{ $sheet->siteEngineer?->name }}</strong>
            </p>
        </div>

        {{-- Review Action Buttons --}}
        @if($canAct && !in_array($sheet->status, ['Paid', 'In Weekly Batch', 'GM Approved']))
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-outline-danger shadow-xs" data-bs-toggle="modal" data-bs-target="#rejectSheetModal">
                <i class="fa-solid fa-rotate-left me-1"></i>Return / Reject
            </button>
            <button type="button" class="btn btn-sm btn-success shadow-xs" onclick="document.getElementById('approveSheetForm').submit()">
                <i class="fa-solid fa-check me-1"></i>Approve &amp; Forward
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

    {{-- Rejection Context if Rejected --}}
    @if($sheet->status === 'Rejected')
    <div class="alert alert-danger border-0 shadow-xs rounded-3 p-3 mb-3">
        <strong class="d-block text-danger mb-1"><i class="fa-solid fa-circle-exclamation me-1"></i>Returned by {{ ucwords(str_replace('_', ' ', $sheet->rejected_by_stage)) }}:</strong>
        <div class="small text-dark"><strong>Reason:</strong> <span class="badge bg-danger">{{ $sheet->rejection_reason }}</span></div>
        <div class="small text-muted mt-1">{{ $sheet->rejection_comment }}</div>
    </div>
    @endif

    <form action="{{ route('manpower-approval.review.process', $sheet->id) }}" method="POST" id="approveSheetForm">
        @csrf
        <input type="hidden" name="decision" value="approve">

        {{-- Summary Cards --}}
        <div class="row g-2 mb-3">
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-xs rounded-3 bg-white p-3 h-100">
                    <span class="text-muted small text-uppercase fw-semibold" style="font-size: 0.68rem;">Headcount</span>
                    <div class="fs-4 fw-bold font-monospace text-dark">{{ $sheet->total_headcount }}</div>
                    <span class="text-muted small" style="font-size: 0.72rem;">Rostered Workers</span>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-xs rounded-3 bg-white p-3 h-100">
                    <span class="text-muted small text-uppercase fw-semibold" style="font-size: 0.68rem;">Total Hours</span>
                    <div class="fs-4 fw-bold font-monospace text-dark">{{ number_format($sheet->total_regular_hours + $sheet->total_overtime_hours, 1) }}h</div>
                    <span class="text-muted small" style="font-size: 0.72rem;">Reg: {{ $sheet->total_regular_hours }}h &bull; OT: {{ $sheet->total_overtime_hours }}h</span>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-xs rounded-3 bg-white p-3 h-100">
                    <span class="text-muted small text-uppercase fw-semibold" style="font-size: 0.68rem;">Gross Amount</span>
                    <div class="fs-4 fw-bold font-monospace text-primary">ETB {{ number_format($sheet->total_amount, 2) }}</div>
                    <span class="text-muted small" style="font-size: 0.72rem;">Calculated at worker daily rate</span>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-xs rounded-3 bg-white p-3 h-100">
                    <span class="text-muted small text-uppercase fw-semibold" style="font-size: 0.68rem;">Approved Payable</span>
                    <div class="fs-4 fw-bold font-monospace text-success">ETB {{ number_format($sheet->effective_total_amount, 2) }}</div>
                    @if($sheet->total_adjusted_amount !== null && $sheet->total_adjusted_amount != $sheet->total_amount)
                    <span class="text-danger small" style="font-size: 0.72rem;">Diff: ETB {{ number_format($sheet->total_adjusted_amount - $sheet->total_amount, 2) }}</span>
                    @else
                    <span class="text-muted small" style="font-size: 0.72rem;">No deductions applied</span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Worker Lines Table with Machine Punches Comparison --}}
        <div class="card border-0 shadow-xs rounded-3 bg-white mb-4">
            <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
                <strong class="text-dark small"><i class="fa-solid fa-list-check text-primary me-1"></i>Roster Verification &amp; Partial Amount Adjustment</strong>
                <small class="text-muted">You can adjust individual line payable amounts before approving.</small>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0 small text-center">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 40px;">#</th>
                                <th class="text-start" style="min-width: 170px;">Worker Name &amp; Code</th>
                                <th>Trade</th>
                                <th style="width: 100px;">Check In</th>
                                <th style="width: 100px;">Check Out</th>
                                <th style="width: 70px;">Hours</th>
                                <th style="width: 70px;">OT</th>
                                <th style="width: 100px;">Daily Rate</th>
                                <th style="width: 110px;">Claimed Amount</th>
                                <th style="width: 130px;" class="bg-success-subtle text-success">Approved Amount</th>
                                <th style="min-width: 120px;">Biometric Punch Log</th>
                                <th class="text-start" style="min-width: 140px;">Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($sheet->lines as $idx => $line)
                            @php
                                $w = $line->worker;
                                $punches = $rawPunches->get($w?->phone) ?? $deviceLogs->get($w?->phone) ?? collect();
                            @endphp
                            <tr>
                                <td class="font-monospace text-muted">{{ $idx + 1 }}</td>
                                <td class="text-start">
                                    <strong class="text-dark d-block">{{ $w?->name ?? 'Worker' }}</strong>
                                    <span class="font-monospace text-primary" style="font-size: 0.68rem;">{{ $w?->worker_code }}</span>
                                    @if($w?->phone)
                                    <span class="text-muted" style="font-size: 0.68rem;">&bull; Tel: {{ $w->phone }}</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">{{ $w?->trade ?: 'Laborer' }}</span>
                                </td>
                                <td class="font-monospace">{{ $line->check_in ? substr($line->check_in, 0, 5) : '—' }}</td>
                                <td class="font-monospace">{{ $line->check_out ? substr($line->check_out, 0, 5) : '—' }}</td>
                                <td class="font-monospace">{{ (float)$line->regular_hours }}h</td>
                                <td class="font-monospace">{{ (float)$line->overtime_hours > 0 ? (float)$line->overtime_hours . 'h' : '—' }}</td>
                                <td class="font-monospace">ETB {{ number_format($line->daily_rate, 2) }}</td>
                                <td class="font-monospace fw-bold text-dark">ETB {{ number_format($line->amount, 2) }}</td>
                                
                                {{-- Partial Amount Input --}}
                                <td class="bg-success-subtle p-1">
                                    @if($canAct)
                                    <input type="number" step="0.01" min="0" name="line_adjustments[{{ $line->id }}]" class="form-control form-control-sm text-center font-monospace fw-bold text-success" value="{{ $line->adjusted_amount !== null ? (float)$line->adjusted_amount : (float)$line->amount }}">
                                    @else
                                    <span class="font-monospace fw-bold text-success">ETB {{ number_format($line->effective_amount, 2) }}</span>
                                    @endif
                                </td>

                                {{-- Biometric Punch Comparison --}}
                                <td>
                                    @if($line->attendance_status === 'present')
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                                            <i class="fa-solid fa-fingerprint me-1"></i>Present
                                        </span>
                                    @elseif($line->attendance_status === 'absent')
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                            <i class="fa-solid fa-user-xmark me-1"></i>Absent
                                        </span>
                                    @else
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle">
                                            Unrostered Punch
                                        </span>
                                    @endif
                                </td>
                                <td class="text-start text-muted" style="font-size: 0.72rem;">
                                    {{ $line->remark ?: '—' }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light fw-bold font-monospace">
                            <tr>
                                <td colspan="5" class="text-end">TOTALS:</td>
                                <td>{{ (float)$sheet->total_regular_hours }}h</td>
                                <td>{{ (float)$sheet->total_overtime_hours }}h</td>
                                <td>—</td>
                                <td>ETB {{ number_format($sheet->total_amount, 2) }}</td>
                                <td class="text-success fs-6">ETB {{ number_format($sheet->effective_total_amount, 2) }}</td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        {{-- Site Notes & Audit Trail --}}
        <div class="row g-3 mb-4">
            <div class="col-lg-5">
                <div class="card border-0 shadow-xs rounded-3 bg-white h-100">
                    <div class="card-header bg-white py-2 px-3 border-bottom">
                        <strong class="text-dark small"><i class="fa-solid fa-message text-primary me-1"></i>Site Engineer Notes</strong>
                    </div>
                    <div class="card-body p-3 small text-muted">
                        {{ $sheet->notes ?: 'No additional notes provided by site engineer.' }}
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card border-0 shadow-xs rounded-3 bg-white h-100">
                    <div class="card-header bg-white py-2 px-3 border-bottom">
                        <strong class="text-dark small"><i class="fa-solid fa-clock-rotate-left text-primary me-1"></i>Multi-Level Approval Audit Trail</strong>
                    </div>
                    <div class="card-body p-3">
                        <ul class="list-unstyled mb-0 small">
                            @forelse($sheet->approvalLogs as $log)
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
                            <li class="text-muted py-2 text-center">No approval logs recorded yet.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

{{-- MODAL: Reject / Return Sheet --}}
<div class="modal fade" id="rejectSheetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-3 border-0 shadow">
            <form action="{{ route('manpower-approval.review.process', $sheet->id) }}" method="POST">
                @csrf
                <input type="hidden" name="decision" value="reject">

                <div class="modal-header bg-danger text-white py-2 px-3">
                    <h6 class="modal-title fw-bold">
                        <i class="fa-solid fa-rotate-left me-1"></i>Return Sheet to Site Engineer
                    </h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-3">
                    <div class="alert alert-warning py-2 small mb-3">
                        <i class="fa-solid fa-circle-info me-1"></i>
                        Returning this sheet will send it back to <strong>{{ $sheet->siteEngineer?->name }}</strong> with the rejection reason and any adjusted line amounts highlighted for correction.
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Rejection Reason <span class="text-danger">*</span></label>
                        <select name="reason" class="form-select form-select-sm" required>
                            <option value="">Select Reason...</option>
                            <option value="Wrong headcount">Wrong headcount</option>
                            <option value="Wrong rate">Wrong rate</option>
                            <option value="Not matching attendance">Not matching attendance</option>
                            <option value="Duplicate">Duplicate submission</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Reviewer Feedback / Comments <span class="text-danger">*</span></label>
                        <textarea name="comment" rows="3" class="form-control form-control-sm" placeholder="Specify exactly what needs to be corrected by the site engineer..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 px-3">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-danger">
                        <i class="fa-solid fa-rotate-left me-1"></i>Confirm &amp; Return Sheet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
