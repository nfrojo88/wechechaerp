@php
    $attStats   = $employee->getMonthlyAttendanceStats();
    $todayPunch = $employee->today_punch;

    // Rate color schemes
    if ($attStats['rate'] >= 85) {
        $attRateClass = 'text-success';
        $attRateBg    = 'bg-success';
    } elseif ($attStats['rate'] >= 70) {
        $attRateClass = 'text-warning';
        $attRateBg    = 'bg-warning';
    } else {
        $attRateClass = 'text-danger';
        $attRateBg    = 'bg-danger';
    }
@endphp

<div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden" style="border: 1px solid #e2e8f0 !important;">
    {{-- Card Header --}}
    <div class="card-header bg-white py-3 px-3 px-md-4 border-bottom d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle bg-primary bg-opacity-10 p-2 text-primary d-flex align-items-center justify-content-center" style="width:34px; height:34px;">
                <i class="fa-solid fa-clock-rotate-left"></i>
            </div>
            <div>
                <h6 class="fw-bold mb-0 text-dark" style="font-size:0.92rem;">Attendance Overview</h6>
                <small class="text-muted" style="font-size:0.72rem;">Individual Attendance Record</small>
            </div>
        </div>
        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 rounded-pill px-2.5 py-1 font-monospace" style="font-size:0.75rem;">
            <i class="fa-regular fa-calendar me-1"></i>{{ $attStats['month_label'] }}
        </span>
    </div>

    <div class="card-body p-3 p-md-4">
        {{-- Today's Status Banner --}}
        <div class="rounded-3 p-3 mb-3 border" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-muted fw-semibold small text-uppercase" style="font-size:0.7rem; letter-spacing:0.04em;">
                    <i class="fa-solid fa-calendar-day me-1 text-primary"></i>Today ({{ now()->format('D, d M') }})
                </span>
                @if($todayPunch)
                    @php
                        $st = strtolower($todayPunch['status'] ?? 'present');
                        $badgeMap = [
                            'present'  => ['bg' => 'bg-success-subtle text-success border border-success-subtle', 'label' => 'Present', 'icon' => 'circle-check'],
                            'absent'   => ['bg' => 'bg-danger-subtle text-danger border border-danger-subtle', 'label' => 'Absent', 'icon' => 'circle-xmark'],
                            'half_day' => ['bg' => 'bg-warning-subtle text-warning border border-warning-subtle', 'label' => 'Half Day', 'icon' => 'clock'],
                            'leave'    => ['bg' => 'bg-info-subtle text-info border border-info-subtle', 'label' => 'On Leave', 'icon' => 'plane-departure'],
                        ];
                        $stBadge = $badgeMap[$st] ?? ['bg' => 'bg-secondary-subtle text-secondary border', 'label' => ucfirst($st), 'icon' => 'circle-info'];
                    @endphp
                    <span class="badge {{ $stBadge['bg'] }} px-2 py-1 rounded-pill" style="font-size:0.72rem;">
                        <i class="fa-solid fa-{{ $stBadge['icon'] }} me-1"></i>{{ $stBadge['label'] }}
                    </span>
                @else
                    <span class="badge bg-light text-muted border px-2 py-1 rounded-pill" style="font-size:0.72rem;">
                        <i class="fa-regular fa-clock me-1"></i>Not Clocked In
                    </span>
                @endif
            </div>

            @if($todayPunch)
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 pt-1 border-top border-light">
                    <div class="d-flex align-items-center gap-1.5 small">
                        <span class="text-muted" style="font-size:0.75rem;"><i class="fa-solid fa-arrow-right-to-bracket text-success me-1"></i>In:</span>
                        <strong class="text-dark font-monospace" style="font-size:0.85rem;">{{ $todayPunch['check_in'] ?? '—' }}</strong>
                    </div>
                    <div class="d-flex align-items-center gap-1.5 small">
                        <span class="text-muted" style="font-size:0.75rem;"><i class="fa-solid fa-arrow-right-from-bracket text-primary me-1"></i>Out:</span>
                        <strong class="text-dark font-monospace" style="font-size:0.85rem;">{{ $todayPunch['check_out'] ?? 'Active' }}</strong>
                    </div>
                    @if(!empty($todayPunch['hours_worked']))
                        <span class="badge bg-white text-dark border font-monospace shadow-xs px-2 py-1" style="font-size:0.75rem;">
                            {{ $todayPunch['hours_worked'] }} hrs
                        </span>
                    @endif
                </div>
            @else
                <div class="text-muted small fst-italic" style="font-size:0.78rem;">
                    No clock-in recorded yet for today.
                </div>
            @endif
        </div>

        {{-- Monthly Attendance Rate Bar --}}
        <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center mb-1.5 small">
                <span class="text-muted fw-semibold" style="font-size:0.8rem;">
                    <i class="fa-solid fa-chart-simple text-primary me-1"></i>Monthly Attendance Rate
                </span>
                <span class="fw-bold font-monospace {{ $attRateClass }}" style="font-size:0.95rem;">
                    {{ number_format($attStats['rate'], 1) }}%
                </span>
            </div>
            <div class="progress rounded-pill" style="height: 8px; background-color: #e2e8f0;">
                <div class="progress-bar {{ $attRateBg }}" role="progressbar"
                     style="width: {{ min(100, $attStats['rate']) }}%;"
                     aria-valuenow="{{ $attStats['rate'] }}" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
            <div class="d-flex justify-content-between mt-1 text-muted" style="font-size:0.72rem;">
                <span>{{ $attStats['present'] }} present / {{ $attStats['total_records'] }} logged days</span>
                <span>Target: 95%</span>
            </div>
        </div>

        {{-- 4 Mini Stat Tiles in 2x2 Grid --}}
        <div class="row g-2 mb-3">
            <div class="col-6">
                <div class="p-2.5 rounded-3 text-center border" style="background: #f0fdf4; border-color: #bbf7d0 !important;">
                    <span class="d-block text-muted fw-semibold" style="font-size: 0.72rem;">Present</span>
                    <h5 class="fw-bold mb-0 text-success font-monospace" style="font-size: 1.15rem;">
                        {{ $attStats['present'] }} <span class="fw-normal text-muted" style="font-size: 0.7rem;">days</span>
                    </h5>
                </div>
            </div>
            <div class="col-6">
                <div class="p-2.5 rounded-3 text-center border" style="background: #fef2f2; border-color: #fecaca !important;">
                    <span class="d-block text-muted fw-semibold" style="font-size: 0.72rem;">Absent</span>
                    <h5 class="fw-bold mb-0 text-danger font-monospace" style="font-size: 1.15rem;">
                        {{ $attStats['absent'] }} <span class="fw-normal text-muted" style="font-size: 0.7rem;">days</span>
                    </h5>
                </div>
            </div>
            <div class="col-6">
                <div class="p-2.5 rounded-3 text-center border" style="background: #fffbeb; border-color: #fde68a !important;">
                    <span class="d-block text-muted fw-semibold" style="font-size: 0.72rem;">Half Day / Late</span>
                    <h5 class="fw-bold mb-0 font-monospace" style="color: #d97706 !important; font-size: 1.15rem;">
                        {{ $attStats['half_day'] }} <span class="fw-normal text-muted" style="font-size: 0.7rem;">days</span>
                    </h5>
                </div>
            </div>
            <div class="col-6">
                <div class="p-2.5 rounded-3 text-center border" style="background: #eff6ff; border-color: #bfdbfe !important;">
                    <span class="d-block text-muted fw-semibold" style="font-size: 0.72rem;">Hours Worked</span>
                    <h5 class="fw-bold mb-0 text-primary font-monospace" style="font-size: 1.15rem;">
                        {{ number_format($attStats['hours_worked'], 1) }} <span class="fw-normal text-muted" style="font-size: 0.7rem;">hrs</span>
                    </h5>
                </div>
            </div>
        </div>

        {{-- Biometric Status & Quick Links --}}
        <div class="pt-2 border-top d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                @if(!empty($employee->device_user_id))
                    <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle rounded-pill px-2.5 py-1" style="font-size: 0.72rem;" title="Biometric device linked for auto-sync">
                        <i class="fa-solid fa-fingerprint me-1"></i>Device ID: <strong class="font-monospace">{{ $employee->device_user_id }}</strong>
                    </span>
                @else
                    <span class="badge bg-light text-muted border rounded-pill px-2.5 py-1" style="font-size: 0.72rem;">
                        <i class="fa-solid fa-keyboard me-1"></i>Manual Punch
                    </span>
                @endif
            </div>

            <div class="d-flex align-items-center gap-1.5">
                @if(request()->routeIs('employees.show'))
                    <a href="#attendance-history" class="btn btn-xs btn-outline-primary rounded-pill px-2.5 py-1 fw-semibold" style="font-size:0.75rem;">
                        <i class="fa-solid fa-list me-1"></i>Full History
                    </a>
                @elseif(\Illuminate\Support\Facades\Route::has('employee.attendance') && auth()->user() && auth()->user()->employee && auth()->user()->employee->id === $employee->id)
                    <a href="{{ route('employee.attendance') }}" class="btn btn-xs btn-outline-primary rounded-pill px-2.5 py-1 fw-semibold" style="font-size:0.75rem;">
                        <i class="fa-solid fa-arrow-up-right-from-square me-1"></i>My Attendance
                    </a>
                @elseif(\Illuminate\Support\Facades\Route::has('attendance.index'))
                    <a href="{{ route('attendance.index', ['employee' => $employee->employee_code]) }}" class="btn btn-xs btn-outline-primary rounded-pill px-2.5 py-1 fw-semibold" style="font-size:0.75rem;">
                        <i class="fa-solid fa-arrow-up-right-from-square me-1"></i>Full Logs
                    </a>
                @endif
            </div>
        </div>
    </div>
</div>
