@extends('layouts.app')

@section('title', 'Procurement SMS Handoff Settings & Notifications')

@section('content')
<div class="container-fluid py-3 px-md-4">

    {{-- HEADER --}}
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <h3 class="mb-0 fw-bold text-dark">
                    <i class="fa-solid fa-tower-broadcast text-primary me-2"></i>Procurement SMS Handoff Settings
                </h3>
                <span class="badge bg-primary text-white px-2.5 py-1.5 rounded-pill shadow-xs">
                    <i class="fa-solid fa-bolt me-1"></i>Instant Handoff Trigger
                </span>
                <span class="badge bg-success text-white px-2.5 py-1.5 rounded-pill shadow-xs">
                    <i class="fa-solid fa-shield-halved me-1"></i>Provider: {{ $activeProvider }}
                </span>
            </div>
            <p class="text-muted small mb-0 mt-1">
                Configure instant SMS alerts triggered automatically on every procurement stage transition from Site Requisition to 3-Way Match & Close.
            </p>
        </div>

        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#testSmsModal">
                <i class="fa-solid fa-paper-plane me-1"></i> Send Test SMS
            </button>
            <a href="{{ route('admin.procurement.sms-settings.index') }}" class="btn btn-light border shadow-sm">
                <i class="fa-solid fa-rotate me-1"></i> Refresh
            </a>
        </div>
    </div>

    {{-- METRIC CARDS --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3 p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold">Active Gateway</div>
                        <h5 class="fw-bold mb-0 text-primary mt-1">{{ $activeProvider }}</h5>
                    </div>
                    <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle">
                        <i class="fa-solid fa-signal fa-lg"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3 p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold">Total Delivered</div>
                        <h4 class="fw-bold mb-0 text-success mt-1">{{ number_format($totalSent) }}</h4>
                    </div>
                    <div class="bg-success bg-opacity-10 text-success p-3 rounded-circle">
                        <i class="fa-solid fa-check-double fa-lg"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3 p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold">Failed Dispatches</div>
                        <h4 class="fw-bold mb-0 text-danger mt-1">{{ number_format($totalFailed) }}</h4>
                    </div>
                    <div class="bg-danger bg-opacity-10 text-danger p-3 rounded-circle">
                        <i class="fa-solid fa-triangle-exclamation fa-lg"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3 p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold">Delivery Success Rate</div>
                        @php
                            $rate = $totalLogged > 0 ? round(($totalSent / $totalLogged) * 100, 1) : 100;
                        @endphp
                        <h4 class="fw-bold mb-0 text-info mt-1">{{ $rate }}%</h4>
                    </div>
                    <div class="bg-info bg-opacity-10 text-info p-3 rounded-circle">
                        <i class="fa-solid fa-chart-line fa-lg"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- MAIN CONTENT TABS --}}
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-header bg-white border-bottom pt-3 pb-0">
            <ul class="nav nav-tabs card-header-tabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active fw-semibold" data-bs-toggle="tab" href="#handoffsTab" role="tab">
                        <i class="fa-solid fa-list-check me-2"></i>Handoff Chain & Templates ({{ count($handoffs) }})
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link fw-semibold" data-bs-toggle="tab" href="#logsTab" role="tab">
                        <i class="fa-solid fa-clock-rotate-left me-2"></i>Live Notification Log
                    </a>
                </li>
            </ul>
        </div>

        <div class="card-body p-4">
            <div class="tab-content">

                {{-- TAB 1: HANDOFF MAPPING & TEMPLATES --}}
                <div class="tab-pane fade show active" id="handoffsTab" role="tabpanel">

                    <form action="{{ route('admin.procurement.sms-settings.update') }}" method="POST">
                        @csrf

                        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                            <div>
                                <h5 class="fw-bold mb-1">Procurement Lifecycle SMS Handoff Rules</h5>
                                <p class="text-muted small mb-0">
                                    Every action immediately sends an SMS to the assigned employee of the target role. Messages are kept under 160 characters. Emergency requests are auto-prefixed with <strong>URGENT:</strong>.
                                </p>
                            </div>
                            <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm">
                                <i class="fa-solid fa-floppy-disk me-1"></i> Save All Settings
                            </button>
                        </div>

                        {{-- PLACEHOLDER CHEAT SHEET --}}
                        <div class="alert alert-light border small mb-4 py-2 px-3">
                            <span class="fw-bold text-dark me-2"><i class="fa-solid fa-code me-1 text-primary"></i>Available Placeholders:</span>
                            <code class="me-2">{req_no}</code> Request ID &bull;
                            <code class="me-2">{sender_name}</code> Actor Name &bull;
                            <code class="me-2">{sender_role}</code> Actor Role &bull;
                            <code class="me-2">{action}</code> Action Name &bull;
                            <code class="me-2">{site}</code> Project/Site &bull;
                            <code class="me-2">{priority}</code> 'URGENT: ' or empty &bull;
                            <code class="me-2">{link}</code> Direct Web Link
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle border">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 70px;" class="text-center">Status</th>
                                        <th style="width: 280px;">Action & Stage</th>
                                        <th style="width: 220px;">Handoff Flow</th>
                                        <th>SMS Template (Editable)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($handoffs as $key => $h)
                                    <tr>
                                        {{-- Toggle --}}
                                        <td class="text-center align-top pt-3">
                                            <div class="form-check form-switch d-inline-block">
                                                <input class="form-check-input" type="checkbox" role="switch"
                                                       name="handoffs[{{ $key }}][is_enabled]" value="1"
                                                       {{ $h['is_enabled'] ? 'checked' : '' }} id="switch_{{ $key }}">
                                            </div>
                                        </td>

                                        {{-- Name & Description --}}
                                        <td class="align-top pt-3">
                                            <label for="switch_{{ $key }}" class="fw-bold text-dark d-block mb-1 cursor-pointer">
                                                {{ $h['name'] }}
                                            </label>
                                            <div class="text-muted small">{{ $h['description'] }}</div>
                                            <code class="text-secondary small">{{ $key }}</code>
                                        </td>

                                        {{-- Flow Roles --}}
                                        <td class="align-top pt-3">
                                            <div class="mb-1">
                                                <span class="badge bg-secondary text-white">{{ ucwords(str_replace('_', ' ', $h['sender_role'])) }}</span>
                                                <i class="fa-solid fa-arrow-right mx-1 text-muted small"></i>
                                                <span class="badge bg-primary text-white">{{ ucwords(str_replace('_', ' ', $h['target_roles_str'])) }}</span>
                                            </div>
                                            <small class="text-muted d-block">Target: Assigned employee per site/store</small>
                                        </td>

                                        {{-- Template Input --}}
                                        <td class="align-top">
                                            <div class="position-relative">
                                                <textarea name="handoffs[{{ $key }}][template]" rows="2"
                                                          class="form-control form-control-sm font-monospace template-input"
                                                          maxlength="300"
                                                          id="tpl_{{ $key }}">{{ $h['template'] }}</textarea>
                                                <div class="d-flex justify-content-between align-items-center mt-1">
                                                    <span class="text-muted small char-counter" id="cnt_{{ $key }}">
                                                        {{ mb_strlen($h['template']) }}/160 chars
                                                    </span>
                                                    <button type="button" class="btn btn-link btn-sm p-0 text-muted small text-decoration-none"
                                                            onclick="resetTemplate('{{ $key }}', '{{ addslashes($h['default_template']) }}')">
                                                        <i class="fa-solid fa-arrow-rotate-left me-1"></i>Reset Default
                                                    </button>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="text-end mt-3">
                            <button type="submit" class="btn btn-primary px-5 fw-bold shadow-sm">
                                <i class="fa-solid fa-floppy-disk me-1"></i> Save All Settings
                            </button>
                        </div>
                    </form>

                </div>

                {{-- TAB 2: LIVE NOTIFICATION LOG --}}
                <div class="tab-pane fade" id="logsTab" role="tabpanel">

                    {{-- Filters --}}
                    <form method="GET" action="{{ route('admin.procurement.sms-settings.index') }}" class="row g-2 mb-3">
                        <div class="col-md-3">
                            <input type="text" name="search" class="form-control form-control-sm"
                                   placeholder="Search phone, role, message..." value="{{ request('search') }}">
                        </div>
                        <div class="col-md-3">
                            <select name="status" class="form-select form-select-sm">
                                <option value="">All Statuses</option>
                                <option value="sent" {{ request('status') === 'sent' ? 'selected' : '' }}>Sent</option>
                                <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>Failed</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-secondary btn-sm me-1">
                                <i class="fa-solid fa-filter me-1"></i> Filter
                            </button>
                            <a href="{{ route('admin.procurement.sms-settings.index') }}" class="btn btn-light btn-sm border">
                                Reset
                            </a>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle border">
                            <thead class="table-light">
                                <tr>
                                    <th>Time</th>
                                    <th>Action</th>
                                    <th>Sender</th>
                                    <th>Recipient</th>
                                    <th>Phone</th>
                                    <th>Message Preview</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentLogs as $log)
                                <tr>
                                    <td class="text-nowrap small text-muted">
                                        {{ $log->created_at->format('d M, H:i:s') }}
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border">{{ $log->action }}</span>
                                    </td>
                                    <td class="small">
                                        {{ $log->sender?->name ?? 'System' }}
                                    </td>
                                    <td class="small">
                                        <div class="fw-semibold">{{ $log->recipient?->name ?? ($log->recipientEmployee?->full_name ?? 'Unassigned') }}</div>
                                        <small class="text-muted">{{ ucwords(str_replace('_', ' ', $log->role)) }}</small>
                                    </td>
                                    <td class="small text-nowrap font-monospace">
                                        {{ $log->phone }}
                                    </td>
                                    <td class="small" style="max-width: 320px;">
                                        <div class="text-truncate" title="{{ $log->message }}">
                                            {{ $log->message }}
                                        </div>
                                        @if($log->error)
                                        <div class="text-danger small mt-0.5">
                                            <i class="fa-solid fa-circle-exclamation me-1"></i>{{ $log->error }}
                                        </div>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if($log->status === 'sent')
                                            <span class="badge bg-success text-white">Sent</span>
                                        @else
                                            <span class="badge bg-danger text-white">Failed</span>
                                            @if($log->retries > 0)
                                                <small class="d-block text-muted">({{ $log->retries }} retries)</small>
                                            @endif
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">
                                        No SMS notification logs found yet.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3">
                        {{ $recentLogs->links() }}
                    </div>

                </div>

            </div>
        </div>
    </div>

</div>

{{-- MODAL: SEND TEST SMS --}}
<div class="modal fade" id="testSmsModal" tabindex="-1" aria-labelledby="testSmsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('admin.procurement.sms-settings.test-sms') }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="testSmsModalLabel">
                        <i class="fa-solid fa-paper-plane text-primary me-2"></i>Send Test SMS via {{ $activeProvider }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">
                        Immediately test your active SMS provider integration by dispatching a live test message to any phone number.
                    </p>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Recipient Phone Number</label>
                        <input type="text" name="test_phone" class="form-control"
                               placeholder="e.g. +251911123456 or 0911123456"
                               value="{{ auth()->user()?->phone ?: '+251911000000' }}" required>
                        <div class="form-text">Ethiopian format (09... or 07...) will automatically format to E.164 (+251...).</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Test Message Content</label>
                        <textarea name="test_message" rows="3" class="form-control font-monospace" maxlength="160" required>ConstructPro ERP: Test SMS handoff notification. Gateway is operational!</textarea>
                        <div class="form-text">Must be under 160 characters.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold">
                        <i class="fa-solid fa-paper-plane me-1"></i> Send Now
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function resetTemplate(key, defaultTpl) {
    const el = document.getElementById('tpl_' + key);
    if (el) {
        el.value = defaultTpl;
        updateCharCounter(el, key);
    }
}

function updateCharCounter(el, key) {
    const counter = document.getElementById('cnt_' + key);
    if (!counter) return;
    const len = el.value.length;
    counter.innerText = len + '/160 chars';
    if (len > 160) {
        counter.classList.add('text-danger', 'fw-bold');
        counter.classList.remove('text-muted');
    } else {
        counter.classList.remove('text-danger', 'fw-bold');
        counter.classList.add('text-muted');
    }
}

document.querySelectorAll('.template-input').forEach(input => {
    const key = input.id.replace('tpl_', '');
    input.addEventListener('input', () => updateCharCounter(input, key));
    updateCharCounter(input, key);
});
</script>
@endsection
