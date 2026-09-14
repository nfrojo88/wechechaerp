@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="page-header mb-4">
        <div class="row align-items-center">
            <div class="col">
                <h2 class="page-title">My Attendance</h2>
            </div>
            <div class="col-auto">
                <a href="{{ route('employee.dashboard') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="fa-solid fa-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="mb-0 fw-bold text-dark">
                        <i class="fa-solid fa-calendar-check me-2 text-primary"></i>Attendance Records
                    </h5>
                    <form method="GET" action="{{ route('employee.attendance') }}" class="d-flex align-items-center gap-2">
                        <select name="month" class="form-select form-select-sm" onchange="this.form.submit()">
                            @for ($m = 1; $m <= 12; $m++)
                                <option value="{{ $m }}" {{ request('month', \Carbon\Carbon::now()->month) == $m ? 'selected' : '' }}>
                                    {{ \Carbon\Carbon::create(null, $m, 1)->format('F') }}
                                </option>
                            @endfor
                        </select>
                        <select name="year" class="form-select form-select-sm" onchange="this.form.submit()">
                            @for ($y = \Carbon\Carbon::now()->year; $y >= \Carbon\Carbon::now()->year - 2; $y--)
                                <option value="{{ $y }}" {{ request('year', \Carbon\Carbon::now()->year) == $y ? 'selected' : '' }}>
                                    {{ $y }}
                                </option>
                            @endfor
                        </select>
                    </form>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th rowspan="2" class="align-middle text-nowrap px-3 py-2 text-muted fw-bold">DATE</th>
                                    <th rowspan="2" class="align-middle text-nowrap py-2 text-muted fw-bold">DAY</th>
                                    <th rowspan="2" class="align-middle text-center py-2 text-muted fw-bold">STATUS</th>
                                    <th colspan="2" class="text-center text-primary border-bottom-0 pb-1 pt-2 fw-bold" style="background-color: rgba(13, 110, 253, 0.03);">
                                        <i class="fa-solid fa-sun text-warning me-1"></i> Morning Session
                                    </th>
                                    <th colspan="2" class="text-center text-primary border-bottom-0 pb-1 pt-2 fw-bold" style="background-color: rgba(255, 193, 7, 0.05);">
                                        <i class="fa-solid fa-cloud-sun text-warning me-1"></i> Afternoon Session
                                    </th>
                                    <th rowspan="2" class="align-middle text-center py-2 text-muted fw-bold">DURATION</th>
                                    <th rowspan="2" class="align-middle py-2 text-muted fw-bold">NOTES</th>
                                </tr>
                                <tr>
                                    <th class="text-center small text-muted border-top-0 pt-0 pb-2" style="background-color: rgba(13, 110, 253, 0.03); min-width: 110px;">Check-In</th>
                                    <th class="text-center small text-muted border-top-0 pt-0 pb-2" style="background-color: rgba(13, 110, 253, 0.03); min-width: 110px;">Check-Out</th>
                                    <th class="text-center small text-muted border-top-0 pt-0 pb-2" style="background-color: rgba(255, 193, 7, 0.05); min-width: 110px;">Check-In</th>
                                    <th class="text-center small text-muted border-top-0 pt-0 pb-2" style="background-color: rgba(255, 193, 7, 0.05); min-width: 110px;">Check-Out</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($attendance as $record)
                                <tr>
                                    <td class="text-nowrap fw-semibold text-dark px-3">
                                        {{ $record->attendance_date ? $record->attendance_date->format('M d, Y') : '—' }}
                                    </td>
                                    <td class="text-muted text-nowrap">
                                        {{ $record->attendance_date ? $record->attendance_date->format('l') : '—' }}
                                    </td>
                                    <td class="text-center text-nowrap">
                                        @php
                                            $status = strtolower($record->status ?? '');
                                            $statusNorm = str_replace('-', '_', $status);
                                        @endphp
                                        @if ($statusNorm === 'present')
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">Present</span>
                                        @elseif ($statusNorm === 'absent')
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">Absent</span>
                                        @elseif ($statusNorm === 'leave')
                                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1">Leave</span>
                                        @elseif ($statusNorm === 'half_day')
                                            <span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-1">Half Day</span>
                                        @elseif ($status)
                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1">{{ ucfirst(str_replace('_', ' ', $status)) }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>

                                    {{-- Morning Check-In --}}
                                    <td class="text-center text-nowrap" style="background-color: rgba(13, 110, 253, 0.015);">
                                        @php
                                            $mIn = $record->morning_in ?? ($record->check_in && \Carbon\Carbon::parse($record->check_in)->hour < 12 ? $record->check_in : null);
                                        @endphp
                                        @if($mIn)
                                            <span class="badge bg-success-subtle text-success border border-success-subtle font-monospace px-2 py-1" title="Morning Check-In">
                                                <i class="fa-solid fa-arrow-right me-1 small"></i>{{ \Carbon\Carbon::parse($mIn)->format('h:i A') }}
                                            </span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>

                                    {{-- Morning Check-Out --}}
                                    <td class="text-center text-nowrap" style="background-color: rgba(13, 110, 253, 0.015);">
                                        @if($record->morning_out)
                                            <span class="badge bg-info-subtle text-info border border-info-subtle font-monospace px-2 py-1" title="Morning Check-Out">
                                                <i class="fa-solid fa-arrow-left me-1 small"></i>{{ \Carbon\Carbon::parse($record->morning_out)->format('h:i A') }}
                                            </span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>

                                    {{-- Afternoon Check-In --}}
                                    <td class="text-center text-nowrap" style="background-color: rgba(255, 193, 7, 0.025);">
                                        @if($record->afternoon_in)
                                            <span class="badge bg-success-subtle text-success border border-success-subtle font-monospace px-2 py-1" title="Afternoon Check-In">
                                                <i class="fa-solid fa-arrow-right me-1 small"></i>{{ \Carbon\Carbon::parse($record->afternoon_in)->format('h:i A') }}
                                            </span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>

                                    {{-- Afternoon Check-Out --}}
                                    <td class="text-center text-nowrap" style="background-color: rgba(255, 193, 7, 0.025);">
                                        @php
                                            $aOut = $record->afternoon_out ?? ($record->check_out && \Carbon\Carbon::parse($record->check_out)->hour >= 12 ? $record->check_out : null);
                                        @endphp
                                        @if($aOut)
                                            <span class="badge bg-info-subtle text-info border border-info-subtle font-monospace px-2 py-1" title="Afternoon Check-Out">
                                                <i class="fa-solid fa-arrow-left me-1 small"></i>{{ \Carbon\Carbon::parse($aOut)->format('h:i A') }}
                                            </span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>

                                    {{-- Duration / Hours --}}
                                    <td class="text-center text-nowrap">
                                        @if($record->hours_worked && (float)$record->hours_worked > 0)
                                            <span class="fw-bold text-dark">{{ number_format($record->hours_worked, 1) }} hrs</span>
                                        @elseif($record->check_in && $record->check_out)
                                            @php
                                                try {
                                                    $ci = \Carbon\Carbon::parse($record->check_in);
                                                    $co = \Carbon\Carbon::parse($record->check_out);
                                                    $diff = $ci->diff($co)->format('%h:%I');
                                                } catch (\Exception $e) {
                                                    $diff = null;
                                                }
                                            @endphp
                                            {{ $diff ? $diff . ' hrs' : '—' }}
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>

                                    {{-- Notes --}}
                                    <td>
                                        @if($record->notes)
                                            @if(str_contains(strtolower($record->notes), 'late'))
                                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1">
                                                    <i class="fa-solid fa-clock me-1"></i>{{ $record->notes }}
                                                </span>
                                            @else
                                                <span class="text-secondary small">{{ $record->notes }}</span>
                                            @endif
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">
                                        <i class="fa-solid fa-calendar-times fa-2x mb-2 text-muted d-block opacity-50"></i>
                                        No attendance records found for this month
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if ($attendance->hasPages())
                    <div class="p-3 border-top">
                        {{ $attendance->links() }}
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Statistics -->
    <div class="row">
        <div class="col-md-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold text-dark">
                        <i class="fa-solid fa-chart-pie me-2 text-primary"></i>Monthly Summary
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-3">
                            <h6 class="text-muted small text-uppercase fw-semibold mb-1">Total Present Days</h6>
                            <h3 class="text-success fw-bold mb-0">{{ $attendance->where('status', 'present')->count() }}</h3>
                        </div>
                        <div class="col-md-3">
                            <h6 class="text-muted small text-uppercase fw-semibold mb-1">Total Absent Days</h6>
                            <h3 class="text-danger fw-bold mb-0">{{ $attendance->where('status', 'absent')->count() }}</h3>
                        </div>
                        <div class="col-md-3">
                            <h6 class="text-muted small text-uppercase fw-semibold mb-1">Total Leave Days</h6>
                            <h3 class="text-warning fw-bold mb-0">{{ $attendance->where('status', 'leave')->count() }}</h3>
                        </div>
                        <div class="col-md-3">
                            <h6 class="text-muted small text-uppercase fw-semibold mb-1">Total Half Days</h6>
                            <h3 class="text-info fw-bold mb-0">{{ $attendance->filter(fn($r) => in_array($r->status, ['half-day', 'half_day']))->count() }}</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
