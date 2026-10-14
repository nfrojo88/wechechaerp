@extends('layouts.app')
@section('title', 'Payroll Records')

@section('content')
<div class="container-fluid px-4 py-3">

    {{-- ── Alerts ──────────────────────────────────────────────────────────── --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-start border-4 border-success shadow-xs mb-4" role="alert">
            <i class="fa-solid fa-circle-check text-success me-2 fs-5 align-middle"></i>
            <span>{{ session('success') }}</span>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-start border-4 border-danger shadow-xs mb-4" role="alert">
            <i class="fa-solid fa-circle-exclamation text-danger me-2 fs-5 align-middle"></i>
            <span>{{ session('error') }}</span>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show border-start border-4 border-danger shadow-xs mb-4" role="alert">
            <h6 class="fw-bold mb-1"><i class="fa-solid fa-triangle-exclamation me-1"></i> Please check the following errors:</h6>
            <ul class="mb-0 ps-3 small">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- ── Page Header ─────────────────────────────────────────────────────── --}}
    <div class="d-sm-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-0 text-gray-800 fw-bold">
                <i class="fa-solid fa-file-invoice-dollar text-success me-2"></i>Payroll Records
            </h1>
            <p class="text-muted small mb-0">Track monthly payroll runs and revise employee salaries with attached letters dispatched directly to the Secretary Inbox.</p>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <button type="button" class="btn btn-success shadow-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#updateSalaryModal" data-toggle="modal" data-target="#updateSalaryModal">
                <i class="fa-solid fa-file-signature text-white-50 me-1"></i> Update Employee Salary
            </button>
            <a href="{{ route('payrolls.create') }}" class="btn btn-primary shadow-sm fw-semibold">
                <i class="fas fa-plus text-white-50 me-1"></i> Generate Payroll
            </a>
        </div>
    </div>

    {{-- ── Payroll Entries Table ───────────────────────────────────────────── --}}
    <div class="card shadow mb-4 border-0 rounded-3 overflow-hidden">
        <div class="card-header bg-white py-3 d-flex flex-row align-items-center justify-content-between border-bottom">
            <h6 class="m-0 font-weight-bold text-primary">All Payroll Entries</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Employee</th>
                            <th>Period</th>
                            <th>Basic Salary</th>
                            <th>Net Pay</th>
                            <th>Status</th>
                            <th class="pe-3 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($payrolls as $payroll)
                            @php
                                $monthName = date('F', mktime(0, 0, 0, $payroll->month, 10));
                                $netPay = $payroll->basic_salary + $payroll->allowances + $payroll->overtime_pay - $payroll->deductions - $payroll->tax;
                            @endphp
                            <tr>
                                <td class="ps-3">
                                    <strong>{{ $payroll->employee->full_name ?? ($payroll->employee->first_name . ' ' . $payroll->employee->last_name) }}</strong><br>
                                    <small class="text-muted">{{ $payroll->employee->employee_code ?? $payroll->employee->employee_id }}</small>
                                </td>
                                <td>{{ $monthName }} {{ $payroll->year }}</td>
                                <td>Br {{ number_format($payroll->basic_salary, 2) }}</td>
                                <td><strong class="text-success">Br {{ number_format($netPay, 2) }}</strong></td>
                                <td>
                                    @if($payroll->status == 'pending')
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    @elseif($payroll->status == 'paid')
                                        <span class="badge bg-success">Paid on {{ \Carbon\Carbon::parse($payroll->paid_at)->format('M d, Y') }}</span>
                                    @else
                                        <span class="badge bg-secondary">{{ ucfirst($payroll->status) }}</span>
                                    @endif
                                </td>
                                <td class="pe-3 text-end">
                                    <a href="{{ route('payrolls.show', $payroll) }}" class="btn btn-sm btn-info text-white">
                                        <i class="fas fa-eye me-1"></i> View Slip
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-receipt fa-2x mb-2 d-block opacity-50"></i>
                                    No payroll records found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($payrolls->hasPages())
        <div class="card-footer bg-white border-top">
            {{ $payrolls->links() }}
        </div>
        @endif
    </div>
</div>

{{-- ── Update Employee Salary with Attached Letter Modal ── --}}
<div class="modal fade" id="updateSalaryModal" tabindex="-1" aria-labelledby="updateSalaryModalLabel" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form action="{{ route('employees.salary.update-with-letter') }}" method="POST" enctype="multipart/form-data" id="salaryUpdateForm">
                @csrf
                <div class="modal-header bg-success text-white py-3 px-4">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-white bg-opacity-25 text-white p-2 rounded-3 fs-5">
                            <i class="fa-solid fa-file-invoice-dollar"></i>
                        </span>
                        <div>
                            <h5 class="modal-title fw-bold mb-0 text-white" id="updateSalaryModalLabel">
                                Update Employee Salary &amp; Issue Official Letter
                            </h5>
                            <small class="text-white-50">Updates salary records and dispatches the signed letter directly to the Secretary Inbox</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" data-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4 bg-light">
                    {{-- 1. Employee Selection --}}
                    <div class="card border-0 shadow-xs rounded-3 p-3 mb-3 bg-white">
                        <label class="form-label fw-bold text-dark mb-1">
                            Select Employee <span class="text-danger">*</span>
                        </label>
                        <select name="employee_id" id="modalEmployeeSelect" class="form-select form-select-lg border-2 fw-semibold" required onchange="onPayrollEmployeeSelectChange(this)">
                            <option value="">-- Choose Employee from List --</option>
                            @if(isset($employees))
                                @foreach($employees as $emp)
                                    <option value="{{ $emp->id }}"
                                        data-basic="{{ (float) $emp->basic_salary }}"
                                        data-transport="{{ (float) $emp->transport_allowance }}"
                                        data-house="{{ (float) $emp->house_allowance }}"
                                        data-position="{{ (float) $emp->position_allowance }}"
                                        data-dept="{{ $emp->department }}"
                                        data-role="{{ $emp->role_title }}"
                                        data-code="{{ $emp->employee_code }}">
                                        {{ $emp->full_name }} ({{ $emp->employee_code }}) &bull; Current Base: Br {{ number_format($emp->basic_salary, 2) }}
                                    </option>
                                @endforeach
                            @endif
                        </select>
                        <div class="text-muted small mt-1">Select the employee whose salary and allowances are being revised.</div>
                    </div>

                    {{-- Current Salary Overview Card (Dynamic) --}}
                    <div id="currentSalaryPreviewCard" class="card border border-2 border-info-subtle bg-info-subtle p-3 rounded-3 mb-3" style="display: none;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-bold text-dark mb-0"><i class="fa-solid fa-circle-info text-info me-1"></i>Current Compensation Structure</h6>
                            <span class="badge bg-white text-info border border-info fw-bold px-2.5 py-1" id="previewEmployeeCode">EMP</span>
                        </div>
                        <div class="row g-2 text-center">
                            <div class="col-6 col-md-3">
                                <div class="bg-white p-2 rounded border">
                                    <small class="text-muted d-block" style="font-size: 0.72rem;">Base Salary</small>
                                    <strong class="text-dark" id="previewBaseSalary">Br 0.00</strong>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="bg-white p-2 rounded border">
                                    <small class="text-muted d-block" style="font-size: 0.72rem;">Transport</small>
                                    <strong class="text-dark" id="previewTransport">Br 0.00</strong>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="bg-white p-2 rounded border">
                                    <small class="text-muted d-block" style="font-size: 0.72rem;">House</small>
                                    <strong class="text-dark" id="previewHouse">Br 0.00</strong>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="bg-white p-2 rounded border">
                                    <small class="text-muted d-block" style="font-size: 0.72rem;">Position</small>
                                    <strong class="text-dark" id="previewPosition">Br 0.00</strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- 2. New Salary & Allowances Inputs --}}
                    <div class="card border-0 shadow-xs rounded-3 p-3 mb-3 bg-white">
                        <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-pen-to-square text-success me-1"></i>New Salary &amp; Allowances</h6>
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-bold small text-dark mb-1">
                                    New Basic Salary (ETB) <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light fw-bold">Br</span>
                                    <input type="number" step="0.01" min="0" name="new_basic_salary" id="newBasicSalaryInput" class="form-control fw-bold fs-5 text-success" required placeholder="0.00" oninput="calculateSalaryDiff()">
                                </div>
                                <div class="small fw-semibold mt-1" id="salaryDiffBadge"></div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label fw-bold small text-dark mb-1">
                                    Effective Date <span class="text-danger">*</span>
                                </label>
                                <input type="date" name="effective_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                                <div class="text-muted small" style="font-size: 0.72rem;">Official start date for the updated salary.</div>
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label small text-muted mb-1">Transport Allowance</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-light">Br</span>
                                    <input type="number" step="0.01" min="0" name="new_transport_allowance" id="newTransportInput" class="form-control" placeholder="0.00">
                                </div>
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label small text-muted mb-1">House Allowance</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-light">Br</span>
                                    <input type="number" step="0.01" min="0" name="new_house_allowance" id="newHouseInput" class="form-control" placeholder="0.00">
                                </div>
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label small text-muted mb-1">Position Allowance</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-light">Br</span>
                                    <input type="number" step="0.01" min="0" name="new_position_allowance" id="newPositionInput" class="form-control" placeholder="0.00">
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- 3. Adjustment Reason & Letter Attachment --}}
                    <div class="card border-0 shadow-xs rounded-3 p-3 mb-3 bg-white">
                        <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-file-arrow-up text-primary me-1"></i>Official Letter &amp; Justification</h6>
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-bold small text-dark mb-1">
                                    Change Reason / Category <span class="text-danger">*</span>
                                </label>
                                <select name="reason" class="form-select" required>
                                    <option value="Annual Merit Increment">Annual Merit Increment</option>
                                    <option value="Promotion & Role Adjustment">Promotion &amp; Role Adjustment</option>
                                    <option value="Performance Bonus / Merit">Performance Bonus / Merit</option>
                                    <option value="Cost of Living Adjustment (COLA)">Cost of Living Adjustment (COLA)</option>
                                    <option value="Market Rate Adjustment">Market Rate Adjustment</option>
                                    <option value="Probation Confirmation">Probation Confirmation</option>
                                    <option value="Contract Renewal Increment">Contract Renewal Increment</option>
                                    <option value="Other Salary Revision">Other Salary Revision</option>
                                </select>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label fw-bold small text-dark mb-1">
                                    Attach Official Signed Letter <span class="text-danger">*</span>
                                </label>
                                <input type="file" name="letter_file" class="form-control" accept=".pdf,.png,.jpg,.jpeg,.doc,.docx" required>
                                <div class="text-muted small" style="font-size: 0.72rem;">Upload signed salary letter (PDF, PNG, JPG, Word max 10MB).</div>
                            </div>

                            <div class="col-12">
                                <label class="form-label small text-muted mb-1">Additional Notes / Letter Specifications</label>
                                <textarea name="notes" class="form-control" rows="2" placeholder="Provide any additional conditions, remarks, or reference details for the official letter..."></textarea>
                            </div>
                        </div>
                    </div>

                    {{-- Notice banner explaining workflow --}}
                    <div class="p-3 bg-white rounded-3 border-start border-4 border-warning small shadow-xs">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fa-solid fa-route text-warning fs-5"></i>
                            <div>
                                <strong>Automated Secretary Dispatch Workflow:</strong>
                                <span class="text-muted d-block">Upon submission, the employee's salary is updated, and the attached document is sent as an official letter to the <strong>Secretary Inbox</strong>. The Secretary assigns the sequential reference number, verifies the employee, and officially registers it to archive it permanently in the employee's history.</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-white border-top py-3 px-4">
                    <button type="button" class="btn btn-light border px-3" data-bs-dismiss="modal" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success fw-bold px-4 py-2 shadow-xs">
                        <i class="fa-solid fa-paper-plane me-1"></i> Update Salary &amp; Send Letter to Secretary
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    let currentSelectedBaseSalary = 0;

    function onPayrollEmployeeSelectChange(selectElem) {
        const option = selectElem.options[selectElem.selectedIndex];
        if (!option || !option.value) {
            document.getElementById('currentSalaryPreviewCard').style.display = 'none';
            document.getElementById('salaryDiffBadge').innerHTML = '';
            currentSelectedBaseSalary = 0;
            return;
        }

        currentSelectedBaseSalary = parseFloat(option.dataset.basic) || 0;
        const transport = parseFloat(option.dataset.transport) || 0;
        const house = parseFloat(option.dataset.house) || 0;
        const position = parseFloat(option.dataset.position) || 0;
        const code = option.dataset.code || 'EMP';

        document.getElementById('previewBaseSalary').innerText = 'Br ' + currentSelectedBaseSalary.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('previewTransport').innerText = 'Br ' + transport.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('previewHouse').innerText = 'Br ' + house.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('previewPosition').innerText = 'Br ' + position.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('previewEmployeeCode').innerText = code;

        // Auto-fill new inputs with current values as starting defaults if empty
        const newBasicInput = document.getElementById('newBasicSalaryInput');
        if (!newBasicInput.value || parseFloat(newBasicInput.value) === 0) {
            newBasicInput.value = currentSelectedBaseSalary.toFixed(2);
        }
        document.getElementById('newTransportInput').value = transport.toFixed(2);
        document.getElementById('newHouseInput').value = house.toFixed(2);
        document.getElementById('newPositionInput').value = position.toFixed(2);

        document.getElementById('currentSalaryPreviewCard').style.display = 'block';
        calculateSalaryDiff();
    }

    function calculateSalaryDiff() {
        const newBasic = parseFloat(document.getElementById('newBasicSalaryInput').value) || 0;
        const badge = document.getElementById('salaryDiffBadge');

        if (currentSelectedBaseSalary <= 0 || newBasic <= 0) {
            badge.innerHTML = '';
            return;
        }

        const diff = newBasic - currentSelectedBaseSalary;
        if (diff > 0) {
            badge.innerHTML = '<span class="text-success"><i class="fa-solid fa-arrow-up me-1"></i>Increase of Br ' + diff.toLocaleString('en-US', { minimumFractionDigits: 2 }) + ' (+' + ((diff / currentSelectedBaseSalary) * 100).toFixed(1) + '%)</span>';
        } else if (diff < 0) {
            badge.innerHTML = '<span class="text-danger"><i class="fa-solid fa-arrow-down me-1"></i>Decrease of Br ' + Math.abs(diff).toLocaleString('en-US', { minimumFractionDigits: 2 }) + ' (' + ((diff / currentSelectedBaseSalary) * 100).toFixed(1) + '%)</span>';
        } else {
            badge.innerHTML = '<span class="text-muted"><i class="fa-solid fa-equals me-1"></i>No base salary change</span>';
        }
    }
</script>
@endpush
@endsection
