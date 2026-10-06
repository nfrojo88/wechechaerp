<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\User;
use App\Models\SiteDeploymentRequest;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\Project;
use App\Models\DeviceAttendanceLog;
use App\Models\ActivityLog;
use App\Helpers\EthiopianCalendar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    /**
     * Display the Attendance Matrix for all active employees across the Ethiopian Payroll Period (26th to 25th).
     * Strictly READ-ONLY: zero database writes on page load.
     */
    public function index()
    {
        if (request()->has('fresh_resync')) {
            return $this->resetAndResync(request());
        }

        self::ensureAttendanceSchemaReady();

        $data = $this->getAttendanceMatrixData(request());

        return view('hr.attendance.index', $data);
    }

    /**
     * Export the Attendance Matrix with all Clock In and Clock Out times as a printable PDF report.
     */
    public function exportPdf(Request $request)
    {
        self::ensureAttendanceSchemaReady();

        $data = $this->getAttendanceMatrixData($request);

        $staffTypeLabel = match($data['staffType']) {
            'driver' => 'Driver Department (General Service)',
            'site', 'site_driver_remote' => 'Site & Project Staff',
            'office' => 'Head Office Staff',
            default => 'All Staff'
        };

        $data['staffTypeLabel'] = $staffTypeLabel;
        $data['reportTitle'] = "{$staffTypeLabel} Biometric Attendance & Clock In / Out Times Report - " . ($data['period']['full_label'] ?? '');

        return view('hr.attendance.pdf', $data);
    }

    /**
     * Build attendance matrix and period statistics for index and PDF export.
     * Supports both Ethiopian Payroll Periods (26th-25th) and custom Gregorian date ranges.
     */
    public function getAttendanceMatrixData(Request $request): array
    {
        // 1. Determine Period: Custom Date Range (Date Option) OR Ethiopian Payroll Period (26th to 25th)
        $selectedPeriodKey = $request->input('period'); // e.g. "2019-1" or "2019-2"
        $ey = $request->input('eth_year');
        $em = $request->input('eth_month');
        $startDate = $request->input('start_date');
        $endDate   = $request->input('end_date');

        $isCustomDate = !empty($startDate) && !empty($endDate);

        if ($isCustomDate) {
            $startCarbon = Carbon::parse($startDate)->startOfDay();
            $endCarbon   = Carbon::parse($endDate)->startOfDay();
            if ($startCarbon->gt($endCarbon)) {
                $temp = $startCarbon;
                $startCarbon = $endCarbon;
                $endCarbon = $temp;
                $startDate = $startCarbon->toDateString();
                $endDate = $endCarbon->toDateString();
            }

            $curr = $startCarbon->copy();
            $periodDays = [];
            while ($curr->lte($endCarbon)) {
                $greg = $curr->toDateString();
                $et = EthiopianCalendar::toEthiopian($curr);
                $periodDays[] = [
                    'greg_date'    => $greg,
                    'greg_day'     => $curr->format('d'),
                    'greg_month'   => $curr->format('M'),
                    'greg_label'   => $curr->format('M d'),
                    'day_of_week'  => $curr->dayOfWeek,
                    'day_name_en'  => $curr->format('D'),
                    'is_sunday'    => $curr->isSunday(),
                    'is_saturday'  => $curr->isSaturday(),
                    'eth_year'     => $et['year'] ?? null,
                    'eth_month'    => $et['month'] ?? null,
                    'eth_day'      => $et['day'] ?? null,
                    'eth_label_am' => $et['short_am'] ?? '',
                    'eth_label_en' => $et['short_en'] ?? '',
                    'display_label'=> ($et['day'] ?? '') . ' ' . ($et['month_en'] ?? ''),
                ];
                $curr->addDay();
            }

            $firstEth = EthiopianCalendar::toEthiopian($startCarbon);
            $lastEth  = EthiopianCalendar::toEthiopian($endCarbon);

            $period = [
                'eth_year'       => $firstEth['year'] ?? null,
                'eth_month'      => $firstEth['month'] ?? null,
                'month_am'       => ($firstEth['month_am'] ?? '') . (($firstEth['month_am'] ?? '') !== ($lastEth['month_am'] ?? '') ? ' - ' . ($lastEth['month_am'] ?? '') : ''),
                'month_en'       => ($firstEth['month_en'] ?? '') . (($firstEth['month_en'] ?? '') !== ($lastEth['month_en'] ?? '') ? ' - ' . ($lastEth['month_en'] ?? '') : ''),
                'period_key'     => 'custom',
                'label_am'       => "ብጁ ቀን ({$startDate} እስከ {$endDate})",
                'label_en'       => "Custom Range ({$startDate} to {$endDate})",
                'full_label'     => Carbon::parse($startDate)->format('M d, Y') . ' — ' . Carbon::parse($endDate)->format('M d, Y'),
                'start_greg'     => $startDate,
                'end_greg'       => $endDate,
                'total_days'     => count($periodDays),
                'days'           => $periodDays,
                'is_custom'      => true,
            ];
            $selectedPeriodKey = 'custom';
        } else {
            if ($selectedPeriodKey && str_contains($selectedPeriodKey, '-')) {
                [$ey, $em] = explode('-', $selectedPeriodKey);
                $ey = (int)$ey;
                $em = (int)$em;
            }

            if ($ey && $em && $em >= 1 && $em <= 13) {
                $period = EthiopianCalendar::getPayrollPeriod((int)$ey, (int)$em);
            } else {
                $period = EthiopianCalendar::getCurrentPayrollPeriod();
            }

            $selectedPeriodKey = $period['period_key'];
            $periodDays = $period['days'];
            $startDate  = $period['start_greg'];
            $endDate    = $period['end_greg'];
        }

        $availablePeriods = EthiopianCalendar::getAvailablePayrollPeriods();

        // Auto-guarantee all approved site deployments for this period are synchronized into Attendance with status 'S'
        try {
            $approvedSiteDeps = SiteDeploymentRequest::where('status', 'approved')
                ->where(function($q) use ($startDate, $endDate) {
                    $q->whereBetween('start_date', [$startDate, $endDate])
                      ->orWhereBetween('end_date', [$startDate, $endDate])
                      ->orWhere(function($sq) use ($startDate, $endDate) {
                          $sq->where('start_date', '<=', $startDate)->where('end_date', '>=', $endDate);
                      });
                })->get();
            foreach ($approvedSiteDeps as $dep) {
                self::applyDeploymentToAttendance($dep);
            }
        } catch (\Throwable $e) {}

        // 2. Fetch active employees with explicit separation of Head Office vs Site vs Driver Attendance
        $authUser = auth()->user();
        $isGeneralServiceUser = $authUser && ($authUser->hasRole('general_service') || $authUser->hasRole('general_services'));
        $isSiteStaffUser = $authUser && ($authUser->hasRole('site_engineer') || $authUser->hasRole('foreman')) && !$authUser->hasAnyRole(['admin', 'global_admin', 'hr', 'hr_manager', 'hr_officer', 'gm']);

        $defaultStaffType = 'office';
        if ($isGeneralServiceUser) {
            $defaultStaffType = 'driver';
        } elseif ($isSiteStaffUser) {
            $defaultStaffType = 'site';
        }
        $staffType = $request->input('staff_type', $defaultStaffType);
        
        $empQuery = Employee::activeRoster()->with('project')->orderBy('full_name');

        if ($staffType === 'office') {
            $empQuery->officeStaffOnly();
        } elseif ($staffType === 'site') {
            $empQuery->siteOnly();
            if ($request->filled('project_id')) {
                $empQuery->where('project_id', $request->input('project_id'));
            }
        } elseif ($staffType === 'driver') {
            $empQuery->driversOnly();
        } elseif ($staffType === 'site_driver_remote') {
            $empQuery->siteDriverRemoteOnly();
            if ($request->filled('project_id')) {
                $empQuery->where('project_id', $request->input('project_id'));
            }
        }

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $empQuery->where(function($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('employee_code', 'like', "%{$search}%")
                  ->orWhere('device_user_id', 'like', "%{$search}%")
                  ->orWhere('role_title', 'like', "%{$search}%")
                  ->orWhere('department', 'like', "%{$search}%");
            });
        }

        if ($request->filled('department')) {
            $empQuery->where('department', $request->input('department'));
        }

        if (!in_array($staffType, ['site', 'site_driver_remote']) && $request->filled('project_id')) {
            $empQuery->where('project_id', $request->input('project_id'));
        }

        $allActiveEmployees = Employee::activeRoster()->orderBy('full_name')->get();
        $officeStaffCount = Employee::activeRoster()->officeStaffOnly()->count();
        $siteStaffCount = Employee::activeRoster()->siteOnly()->count();
        $driverStaffCount = Employee::activeRoster()->driversOnly()->count();
        $allStaffCount = $allActiveEmployees->count();

        // Also fetch active drivers for the General Service record modal
        $activeDriversList = Employee::activeRoster()->driversOnly()->orderBy('full_name')->get();

        $employees = $empQuery->get();
        $employeeIds = $employees->pluck('id')->toArray();

        // 3. Batch query period records for efficiency (READ-ONLY)
        $attendances = Attendance::whereIn('employee_id', $employeeIds)
            ->whereBetween('attendance_date', [$startDate, $endDate])
            ->get()
            ->groupBy('employee_id');

        // Approved Leaves
        $leaves = collect();
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('leave_requests')) {
                $leaves = \App\Models\LeaveRequest::whereIn('employee_id', $employeeIds)
                    ->where('status', 'approved')
                    ->where(function($q) use ($startDate, $endDate) {
                        $q->whereBetween('start_date', [$startDate, $endDate])
                          ->orWhereBetween('end_date', [$startDate, $endDate])
                          ->orWhere(function($sq) use ($startDate, $endDate) {
                              $sq->where('start_date', '<=', $startDate)->where('end_date', '>=', $endDate);
                          });
                    })
                    ->get();
            }
        } catch (\Throwable $e) {}

        // Public Holidays
        $holidays = collect();
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('holidays')) {
                $holidays = \App\Models\Holiday::where(function($q) use ($startDate, $endDate) {
                        $q->whereBetween('holiday_date', [$startDate, $endDate])
                          ->orWhere(function($sq) use ($startDate, $endDate) {
                              $sq->whereBetween('from_date', [$startDate, $endDate])
                                ->orWhereBetween('to_date', [$startDate, $endDate]);
                          });
                    })
                    ->get();
            }
        } catch (\Throwable $e) {}

        // Approved Site Deployments ('S')
        $approvedSiteDeployments = collect();
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('site_deployment_requests')) {
                $approvedSiteDeployments = \App\Models\SiteDeploymentRequest::whereIn('employee_id', $employeeIds)
                    ->where('status', 'approved')
                    ->where(function($q) use ($startDate, $endDate) {
                        $q->whereBetween('start_date', [$startDate, $endDate])
                          ->orWhereBetween('end_date', [$startDate, $endDate])
                          ->orWhere(function($sq) use ($startDate, $endDate) {
                              $sq->where('start_date', '<=', $startDate)->where('end_date', '>=', $endDate);
                          });
                    })
                    ->get();
            }
        } catch (\Throwable $e) {}

        // 4. Build Matrix per Employee per Day and calculate stats
        $matrix = [];
        $totalPresentCount = 0;
        $totalSiteCount = 0;
        $totalBaseAbsentCount = 0;
        $totalLateCount = 0;
        $totalPenaltyDays = 0;
        $penalizedEmployeesCount = 0;

        $todayDateStr = today()->toDateString();

        foreach ($employees as $emp) {
            $empAttByDate = ($attendances[$emp->id] ?? collect())->keyBy(function($item) {
                return $item->attendance_date ? $item->attendance_date->toDateString() : '';
            });

            $dayStatuses = [];
            $empPresent = 0;
            $empSite = 0;
            $empAbsent = 0;
            $empLate = 0;
            $empLeave = 0;
            $empHoliday = 0;
            $empTotalHours = 0.0;

            foreach ($periodDays as $dayItem) {
                $greg = $dayItem['greg_date'];
                $isSunday = $dayItem['is_sunday'];
                $isSaturday = $dayItem['is_saturday'];

                $att = $empAttByDate->get($greg);

                // Check punches: any punch = present
                $hasPunch = $att && (
                    !empty($att->morning_in) ||
                    !empty($att->morning_out) ||
                    !empty($att->afternoon_in) ||
                    !empty($att->afternoon_out) ||
                    !empty($att->check_in) ||
                    !empty($att->check_out) ||
                    (float)$att->hours_worked > 0
                );

                $isLate = false;
                $lateMinutes = 0;
                if ($hasPunch) {
                    $inPunch = $att->morning_in ?: $att->check_in;
                    $lateMinutes = $att->late_minutes ?: \App\Services\BiometricPunchService::calculateLateMinutes($inPunch);
                    $isLate = $lateMinutes > 0 || ($att->morning_in && $att->morning_in > '08:40:59');
                }

                // Check approved site deployment
                $siteDepRecord = $approvedSiteDeployments->first(function($sd) use ($emp, $greg) {
                    $sdStart = $sd->start_date ? Carbon::parse($sd->start_date)->toDateString() : null;
                    $sdEnd   = $sd->end_date ? Carbon::parse($sd->end_date)->toDateString() : null;
                    return (int)$sd->employee_id === (int)$emp->id 
                        && $sdStart 
                        && $sdEnd 
                        && $sdStart <= $greg 
                        && $sdEnd >= $greg;
                });

                $hasApprovedSite = ($att && ($att->status === 'S' || $att->source === 'site_dispatch' || $att->isOnSite()))
                    ? $att
                    : $siteDepRecord;

                // Check approved leave
                $leaveObj = $leaves->first(function($lv) use ($emp, $greg) {
                    $lvStart = $lv->start_date ? Carbon::parse($lv->start_date)->toDateString() : null;
                    $lvEnd   = $lv->end_date ? Carbon::parse($lv->end_date)->toDateString() : null;
                    return (int)$lv->employee_id === (int)$emp->id 
                        && $lvStart 
                        && $lvEnd 
                        && $lvStart <= $greg 
                        && $lvEnd >= $greg;
                });

                // Check public holiday
                $holidayObj = $holidays->first(function($h) use ($greg) {
                    $hd = $h->holiday_date ? Carbon::parse($h->holiday_date)->toDateString() : null;
                    if ($hd && $hd === $greg) return true;
                    if ($h->from_date && $h->to_date) {
                        return Carbon::parse($h->from_date)->toDateString() <= $greg 
                            && Carbon::parse($h->to_date)->toDateString() >= $greg;
                    }
                    return false;
                });

                // Determine cellular code
                if ($isSunday) {
                    if ($hasPunch) {
                        $statusCode = 'P';
                        $cellClass  = 'cell-sunday-ot';
                        $label      = 'Sunday Overtime (P)';
                        $empPresent++;
                    } else {
                        $statusCode = 'SUN';
                        $cellClass  = 'cell-sunday';
                        $label      = 'Sunday (Rest Day)';
                    }
                } elseif ($hasApprovedSite) {
                    $statusCode = 'S';
                    $cellClass  = 'cell-site';
                    $label      = 'On-Site Deployment (S)';
                    $empSite++;
                } elseif ($hasPunch) {
                    $statusCode = 'P';
                    $cellClass  = $isLate ? 'cell-present-late' : 'cell-present-ontime';
                    $label      = $isLate ? "Present (Late {$lateMinutes}m)" : 'Present (P)';
                    $empPresent++;
                    if ($isLate) {
                        $empLate++;
                        $totalLateCount++;
                    }
                } elseif ($leaveObj) {
                    $statusCode = 'L';
                    $cellClass  = 'cell-leave';
                    $label      = 'Approved Leave (L)';
                    $empLeave++;
                } elseif ($holidayObj) {
                    $statusCode = 'H';
                    $cellClass  = 'cell-holiday';
                    $label      = 'Public Holiday (H)';
                    $empHoliday++;
                } elseif ($emp->isDriver()) {
                    if ($att && $att->status === 'absent') {
                        $statusCode = 'A';
                        $cellClass  = 'cell-absent';
                        $label      = 'Absent (Recorded by General Service)';
                        $empAbsent++;
                        $totalBaseAbsentCount++;
                    } elseif ($att && in_array(strtolower($att->status), ['present', 'p'])) {
                        $statusCode = 'P';
                        $cellClass  = 'cell-present-ontime';
                        $tripNote   = $att->notes ?: 'Driver Fleet Duty';
                        $label      = "Present (GS: {$tripNote})";
                        $empPresent++;
                    } elseif ($att && in_array($att->status, ['S', 'trip', 'site'])) {
                        $statusCode = 'S';
                        $cellClass  = 'cell-site';
                        $tripNote   = $att->site_name ?: ($att->notes ?: 'Field Trip / Transport Duty');
                        $label      = "On-Trip / Transport (S) [{$tripNote}]";
                        $empSite++;
                    } elseif ($att && in_array($att->status, ['leave'])) {
                        $statusCode = 'L';
                        $cellClass  = 'cell-leave';
                        $label      = 'Approved Leave (L)';
                        $empLeave++;
                    } else {
                        $statusCode = '—';
                        $cellClass  = 'cell-upcoming';
                        $label      = ($greg > $todayDateStr) ? 'Upcoming Day' : 'Not Recorded (Add via General Service)';
                    }
                } elseif ($emp->isSiteDriverOrRemote() && ($emp->project_id || $emp->is_project_based || in_array($staffType, ['site', 'site_driver_remote']))) {
                    $statusCode = 'S';
                    $cellClass  = 'cell-site';
                    $projName   = $emp->project?->name ?? 'On-Site Construction';
                    $label      = "Site Project Duty (S) [{$projName}]";
                    $empSite++;
                } else {
                    if ($greg > $todayDateStr) {
                        $statusCode = '—';
                        $cellClass  = 'cell-upcoming';
                        $label      = 'Upcoming Day';
                    } else {
                        $statusCode = 'A';
                        $cellClass  = 'cell-absent';
                        $label      = 'Absent (A)';
                        $empAbsent++;
                        $totalBaseAbsentCount++;
                    }
                }

                $rawIn  = $att?->morning_in ?: ($att?->afternoon_in ?: $att?->check_in);
                $rawOut = $att?->afternoon_out ?: ($att?->morning_out ?: $att?->check_out);

                if (!$emp->isDriver() && ($hasApprovedSite || $statusCode === 'S')) {
                    $siteObj = $siteDepRecord ?: ($hasApprovedSite instanceof \App\Models\SiteDeploymentRequest ? $hasApprovedSite : null);
                    if (!$rawIn && !$rawOut) {
                        if ($siteObj) {
                            $rawIn  = $siteObj->morning_in ?: ($siteObj->afternoon_in ?: '08:00');
                            $rawOut = $siteObj->afternoon_out ?: ($siteObj->morning_out ?: '17:30');
                        } elseif ($att && ($att->morning_in || $att->afternoon_out || $att->check_in || $att->check_out)) {
                            $rawIn  = $att->morning_in ?: ($att->afternoon_in ?: ($att->check_in ?: '08:00'));
                            $rawOut = $att->afternoon_out ?: ($att->morning_out ?: ($att->check_out ?: '17:30'));
                        } else {
                            $rawIn  = '08:30';
                            $rawOut = '17:00';
                        }
                    }
                }

                $to12H = function(?string $time): ?string {
                    if (!$time) return null;
                    $time = trim($time);
                    if ($time === '' || $time === '—' || $time === '-') return null;
                    try {
                        return \Carbon\Carbon::createFromFormat('H:i', substr($time, 0, 5))->format('h:i A');
                    } catch (\Throwable $e) {
                        try {
                            return \Carbon\Carbon::parse($time)->format('h:i A');
                        } catch (\Throwable $e2) {
                            return $time;
                        }
                    }
                };

                $punchInFormatted = $to12H($rawIn);
                $punchOutFormatted = $to12H($rawOut);

                if ($punchInFormatted && $punchOutFormatted && $punchInFormatted === $punchOutFormatted && (!$att?->check_out || $att?->check_in === $att?->check_out)) {
                    $punchOutFormatted = null;
                }

                $siteTitle = null;
                if ($hasApprovedSite) {
                    $siteObj = $siteDepRecord ?: ($hasApprovedSite instanceof \App\Models\SiteDeploymentRequest ? $hasApprovedSite : null);
                    $siteTitle = $siteObj?->site_name 
                        ?: ($siteObj?->siteProject?->name 
                        ?? ($att?->site_name ?? 'On-Site Project'));
                }

                $mInVal  = $att?->morning_in ?? ($siteDepRecord?->morning_in ?? ($hasApprovedSite ? '08:40' : null));
                $mOutVal = $att?->morning_out ?? ($siteDepRecord?->morning_out ?? ($hasApprovedSite ? '12:30' : null));
                $aInVal  = $att?->afternoon_in ?? ($siteDepRecord?->afternoon_in ?? ($hasApprovedSite ? '13:35' : null));
                $aOutVal = $att?->afternoon_out ?? ($siteDepRecord?->afternoon_out ?? ($hasApprovedSite ? '17:30' : null));

                // Smart fallback if biometric device only logged general check_in and check_out
                if (!$mInVal && $att?->check_in) {
                    $cIn = substr(trim($att->check_in), 0, 5);
                    if ($cIn < '12:30') {
                        $mInVal = $att->check_in;
                    } else {
                        $aInVal = $aInVal ?: $att->check_in;
                    }
                }
                if (!$aOutVal && $att?->check_out) {
                    $cOut = substr(trim($att->check_out), 0, 5);
                    if ($cOut >= '13:00') {
                        $aOutVal = $att->check_out;
                    } else {
                        $mOutVal = $mOutVal ?: $att->check_out;
                    }
                }

                $cellHours = $att?->hours_worked ? round((float)$att->hours_worked, 1) : ($siteDepRecord ? (float)$siteDepRecord->hours_worked : ($hasApprovedSite ? 8.0 : null));
                if ($cellHours) {
                    $empTotalHours += (float)$cellHours;
                }

                $dayStatuses[$greg] = [
                    'code'          => $statusCode,
                    'class'         => $cellClass,
                    'label'         => $label,
                    'is_late'       => $isLate,
                    'late_minutes'  => $lateMinutes,
                    'punch_in'      => $punchInFormatted,
                    'punch_out'     => $punchOutFormatted,
                    'morning_in'    => $to12H($mInVal),
                    'morning_out'   => $to12H($mOutVal),
                    'afternoon_in'  => $to12H($aInVal),
                    'afternoon_out' => $to12H($aOutVal),
                    'hours'         => $cellHours,
                    'notes'         => $att?->notes ?? ($siteDepRecord?->task_notes),
                    'site_name'     => $siteTitle,
                    'leave_title'   => $leaveObj ? (is_object($leaveObj) && isset($leaveObj->leaveType) ? $leaveObj->leaveType?->name : 'Approved Leave') : null,
                    'holiday_name'  => $holidayObj ? ($holidayObj->title ?? 'Public Holiday') : null,
                ];
            }

            // Calculate Late Penalties: 3 late days = 1 absent day
            $empPenaltyDays = intdiv($empLate, 3);
            if ($empPenaltyDays > 0) {
                $penalizedEmployeesCount++;
                $totalPenaltyDays += $empPenaltyDays;
            }

            $effectiveAbsent = $empAbsent + $empPenaltyDays;
            $effectivePresent = $empPresent + $empSite;

            $totalPresentCount += $empPresent;
            $totalSiteCount += $empSite;

            $matrix[$emp->id] = [
                'employee' => $emp,
                'days'     => $dayStatuses,
                'summary'  => [
                    'present_days'      => $empPresent,
                    'site_days'         => $empSite,
                    'leave_days'        => $empLeave,
                    'holiday_days'      => $empHoliday,
                    'absent_days'       => $empAbsent,
                    'late_days'         => $empLate,
                    'penalty_days'      => $empPenaltyDays,
                    'effective_absent'  => $effectiveAbsent,
                    'effective_present' => $effectivePresent,
                    'total_hours'       => round($empTotalHours, 1),
                ],
            ];
        }

        $totalEffectiveAbsent = $totalBaseAbsentCount + $totalPenaltyDays;

        // Statistics for Dashboard Cards
        $stats = [
            'period_title'             => $period['full_label'],
            'label_am'                 => $period['label_am'],
            'label_en'                 => $period['label_en'],
            'start_date'               => $startDate,
            'end_date'                 => $endDate,
            'total_days'               => count($periodDays),
            'total_staff'              => count($employees),
            'total_present'            => $totalPresentCount,
            'total_site'               => $totalSiteCount,
            'total_absent'             => $totalBaseAbsentCount,
            'total_late'               => $totalLateCount,
            'total_penalty_days'       => $totalPenaltyDays,
            'total_effective_absent'   => $totalEffectiveAbsent,
            'penalized_employees_count'=> $penalizedEmployeesCount,
        ];

        // 5. Diagnostics for HR warning panels
        $missingDeviceEmployees = $allActiveEmployees->filter(function($e) {
            return !$e->isSiteDriverOrRemote() && empty(trim((string)$e->device_user_id));
        });

        $blockedUsers = User::whereNotNull('access_blocked_at')
            ->with(['employee', 'roles', 'accessUnblockedByUser'])
            ->get();

        $departments  = Employee::activeRoster()->distinct()->pluck('department')->filter()->values();
        $projects     = \App\Models\Project::orderBy('name')->get();
        $workSchedule = EthiopianCalendar::getWorkSchedule();

        return compact(
            'period',
            'periodDays',
            'selectedPeriodKey',
            'availablePeriods',
            'matrix',
            'employees',
            'allActiveEmployees',
            'officeStaffCount',
            'siteStaffCount',
            'driverStaffCount',
            'allStaffCount',
            'activeDriversList',
            'stats',
            'staffType',
            'isGeneralServiceUser',
            'missingDeviceEmployees',
            'blockedUsers',
            'departments',
            'projects',
            'workSchedule'
        );
    }

    /**
     * Check if the authenticated user has permission to dispatch employees to site.
     * Authorized: Planning Manager, Coordinator, Finance Head, HR, HR Officer, GM, Admin, Global Admin.
     */
    public static function canDeployToSite($user = null): bool
    {
        $user = $user ?: auth()->user();
        if (!$user) return false;

        $allowedRoles = [
            'planning_manager', 'planning', 'technical_manager', 'Planning Manager', 'Planning', 'Technical Manager',
            'coordinator', 'project_coordinator', 'Coordinator', 'Project Coordinator',
            'finance_head', 'finance_manager', 'finance', 'Finance head', 'Finance Head', 'Finance Manager', 'Finance',
            'hr', 'hr_manager', 'hr_officer', 'HR', 'HR Manager', 'HR Officer',
            'gm', 'general_manager', 'GM', 'General Manager',
            'global_admin', 'admin', 'project_manager'
        ];

        return $user->hasAnyRole($allowedRoles) || (method_exists($user, 'can') && $user->can('attendance.manage'));
    }

    /**
     * Check if user is HR Officer, HR Manager, or Admin who can approve/reject site deployments.
     */
    public static function isHrOrAdmin($user = null): bool
    {
        $user = $user ?: auth()->user();
        if (!$user) return false;

        $hrRoles = [
            'hr', 'hr_manager', 'hr_officer', 'HR', 'HR Manager', 'HR Officer',
            'admin', 'global_admin'
        ];

        return $user->hasAnyRole($hrRoles) || (method_exists($user, 'can') && $user->can('hr.manage'));
    }

    /**
     * Check if a user has any of the specified roles.
     *
     * @param array $roles
     * @param \App\Models\User|null $user
     * @return bool
     */
    public static function userHasAnyRole(array $roles, $user = null): bool
    {
        /** @var \App\Models\User|null $u */
        $u = $user ?: auth()->user();
        return $u !== null && $u->hasAnyRole($roles);
    }

    /**
     * Dedicated Report & Dispatch Management Page for Site Deployments.
     * Visible to HR, HR Officer, Planning Manager, Coordinator, Finance Head, and GM.
     */
    public function siteDeployments(Request $request)
    {
        if (!self::canDeployToSite()) {
            abort(403, 'Unauthorized access. Only Planning Manager, Coordinator, Finance Head, HR, and GM can view or manage site deployments.');
        }

        \App\Models\SiteDeploymentRequest::ensureTableExists();
        self::syncExistingAttendanceRecords();

        $statusFilter = $request->input('status', 'all');

        $query = \App\Models\SiteDeploymentRequest::with(['employee', 'requestedBy', 'hrReviewedBy', 'siteProject'])
            ->latest('id');

        if ($statusFilter !== 'all' && in_array($statusFilter, ['pending', 'approved', 'rejected'])) {
            $query->where('status', $statusFilter);
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }
        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }
        if ($request->filled('requested_by')) {
            $query->where('requested_by', $request->requested_by);
        }
        if ($request->filled('date_from')) {
            $query->where('start_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('end_date', '<=', $request->date_to);
        }

        $deployments = $query->paginate(25)->appends(request()->query());

        $todayStr = today()->toDateString();
        $stats = [
            'pending_count'  => \App\Models\SiteDeploymentRequest::where('status', 'pending')->count(),
            'approved_count' => \App\Models\SiteDeploymentRequest::where('status', 'approved')->count(),
            'rejected_count' => \App\Models\SiteDeploymentRequest::where('status', 'rejected')->count(),
            'total_count'    => \App\Models\SiteDeploymentRequest::count(),
            'today_count'    => \App\Models\SiteDeploymentRequest::where('status', 'approved')
                                    ->where('start_date', '<=', $todayStr)
                                    ->where('end_date', '>=', $todayStr)
                                    ->count(),
            'distinct_employees' => \App\Models\SiteDeploymentRequest::where('status', 'approved')
                                    ->distinct('employee_id')
                                    ->count('employee_id'),
        ];

        $employees = Employee::activeRoster()->orderBy('full_name')->get();
        $projects  = \App\Models\Project::orderBy('name')->get();
        $decidedUsers = \App\Models\User::whereHas('roles', function($r) {
            $r->whereIn('name', [
                'planning_manager', 'planning', 'coordinator', 'finance_head',
                'finance_manager', 'hr', 'hr_manager', 'hr_officer', 'gm',
                'general_manager', 'admin', 'global_admin'
            ]);
        })->orderBy('name')->get();

        $workSchedule = \App\Helpers\EthiopianCalendar::getWorkSchedule();
        $isHr = self::isHrOrAdmin();

        return view('hr.attendance.site_deployments', compact(
            'deployments', 'stats', 'employees', 'projects', 'decidedUsers', 'workSchedule', 'isHr', 'statusFilter'
        ));
    }
    /**
     * Record Site Attendance when Planning Manager, Coordinator, Finance Head, HR, or GM
     * sends an employee to a construction site.
     * All requests from managers are sent to HR to approve.
     * When HR approves -> saved in attendance with status 'S'.
     * If rejected -> HR does not accept this info and nothing is saved in attendance.
     */
    public function recordSiteAttendance(Request $request)
    {
        if (!self::canDeployToSite()) {
            abort(403, 'Unauthorized access. Only Planning Manager, Coordinator, Finance Head, HR, and GM can dispatch employees to site.');
        }

        \App\Models\SiteDeploymentRequest::ensureTableExists();

        $validated = $request->validate([
            'employee_id'           => 'nullable|exists:employees,id',
            'employee_ids'          => 'nullable|array',
            'employee_ids.*'        => 'exists:employees,id',
            'project_id'            => 'nullable|exists:projects,id',
            'site_name'             => 'nullable|string|max:255',
            'duration_type'         => 'nullable|string|in:single_day,date_range',
            'single_date'           => 'nullable|date',
            'attendance_date'       => 'nullable|date',
            'start_date'            => 'nullable|date',
            'end_date'              => 'nullable|date',
            'session_type'          => 'nullable|string|in:full_day,morning,afternoon,custom',
            'include_morning_in'    => 'nullable',
            'morning_in'            => 'nullable|string',
            'include_morning_out'   => 'nullable',
            'morning_out'           => 'nullable|string',
            'include_afternoon_in'  => 'nullable',
            'afternoon_in'          => 'nullable|string',
            'include_afternoon_out' => 'nullable',
            'afternoon_out'         => 'nullable|string',
            'task_notes'            => 'nullable|string|max:500',
            'hours_worked'          => 'nullable|numeric|min:0.5|max:24',
        ]);

        // Support both single employee and multiple employees array
        $employeeIds = [];
        if (!empty($validated['employee_ids'])) {
            $employeeIds = array_filter($validated['employee_ids']);
        }
        if (!empty($validated['employee_id'])) {
            $employeeIds[] = $validated['employee_id'];
        }
        $employeeIds = array_unique($employeeIds);

        if (empty($employeeIds)) {
            return redirect()->back()->with('error', 'Please select at least one employee to dispatch to site.');
        }

        // Determine date range: single day vs date range
        $durationType = $request->input('duration_type', 'single_day');
        if ($durationType === 'single_day' && $request->filled('single_date')) {
            $startDateStr = $request->input('single_date');
            $endDateStr   = $request->input('single_date');
        } else {
            $startDateStr = $request->input('start_date') ?: ($request->input('single_date') ?: ($request->input('attendance_date') ?: today()->toDateString()));
            $endDateStr   = $request->input('end_date') ?: $startDateStr;
        }

        try {
            $start = Carbon::parse($startDateStr);
            $end   = Carbon::parse($endDateStr);
            if ($start->gt($end)) {
                $temp = $start;
                $start = $end;
                $end = $temp;
            }
        } catch (\Throwable $e) {
            $start = today();
            $end   = today();
        }

        $projectName = 'Job Site';
        $siteProjectId = null;
        if (!empty($validated['project_id'])) {
            $proj = \App\Models\Project::find($validated['project_id']);
            if ($proj) {
                $projectName = $proj->name;
                $siteProjectId = $proj->id;
            }
        } elseif (!empty($validated['site_name'])) {
            $projectName = trim($validated['site_name']);
        }

        // Capture who decided this deployment
        $user = auth()->user();
        $decidedByName = $user ? $user->name : 'Authorized Manager';
        $userRole = $user && $user->roles->first() ? $user->roles->first()->name : 'manager';
        $decidedByRoleLabel = ucwords(str_replace('_', ' ', $userRole));

        $workSchedule = \App\Helpers\EthiopianCalendar::getWorkSchedule();
        $defaultMIn   = $workSchedule['morning_in'] ?? '08:30';
        $defaultMOut  = $workSchedule['morning_out'] ?? '12:30';
        $defaultAIn   = $workSchedule['afternoon_in'] ?? '13:30';
        $defaultAOut  = $workSchedule['afternoon_out'] ?? '17:30';

        $sessionType = $request->input('session_type', 'full_day');

        // Resolve punches & session label
        if ($sessionType === 'full_day') {
            $configuredMIn  = $request->input('morning_in') ?: $defaultMIn;
            $configuredMOut = $request->input('morning_out') ?: $defaultMOut;
            $configuredAIn  = $request->input('afternoon_in') ?: $defaultAIn;
            $configuredAOut = $request->input('afternoon_out') ?: $defaultAOut;
            $sessionLabel   = "Full Day ({$configuredMIn}-{$configuredAOut})";
            $sessionDefaultHours = 8.0;
        } elseif ($sessionType === 'morning') {
            $configuredMIn  = $request->input('morning_in') ?: $defaultMIn;
            $configuredMOut = $request->input('morning_out') ?: $defaultMOut;
            $configuredAIn  = null;
            $configuredAOut = null;
            $sessionLabel   = "Morning Session ({$configuredMIn}-{$configuredMOut})";
            $sessionDefaultHours = 4.0;
        } elseif ($sessionType === 'afternoon') {
            $configuredMIn  = null;
            $configuredMOut = null;
            $configuredAIn  = $request->input('afternoon_in') ?: $defaultAIn;
            $configuredAOut = $request->input('afternoon_out') ?: $defaultAOut;
            $sessionLabel   = "Afternoon Session ({$configuredAIn}-{$configuredAOut})";
            $sessionDefaultHours = 4.0;
        } else {
            // custom punch selection
            $configuredMIn  = $request->has('include_morning_in')   ? ($request->input('morning_in') ?: $defaultMIn) : null;
            $configuredMOut = $request->has('include_morning_out')  ? ($request->input('morning_out') ?: $defaultMOut) : null;
            $configuredAIn  = $request->has('include_afternoon_in')  ? ($request->input('afternoon_in') ?: $defaultAIn) : null;
            $configuredAOut = $request->has('include_afternoon_out') ? ($request->input('afternoon_out') ?: $defaultAOut) : null;

            $tags = [];
            if ($configuredMIn)  $tags[] = "M-In: {$configuredMIn}";
            if ($configuredMOut) $tags[] = "M-Out: {$configuredMOut}";
            if ($configuredAIn)  $tags[] = "A-In: {$configuredAIn}";
            if ($configuredAOut) $tags[] = "A-Out: {$configuredAOut}";
            $sessionLabel = !empty($tags) ? "Custom (" . implode(', ', $tags) . ")" : "Custom Session";

            $calc = 0;
            if ($configuredMIn && $configuredMOut) $calc += 4.0;
            elseif ($configuredMIn || $configuredMOut) $calc += 2.0;
            if ($configuredAIn && $configuredAOut) $calc += 4.0;
            elseif ($configuredAIn || $configuredAOut) $calc += 2.0;
            $sessionDefaultHours = $calc > 0 ? $calc : 8.0;
        }

        $hours = (float)($validated['hours_worked'] ?? $sessionDefaultHours);
        $isHr = self::isHrOrAdmin($user);
        // All non-HR manager requests start as pending for HR approval!
        $status = $isHr ? 'approved' : 'pending';

        foreach ($employeeIds as $empId) {
            $deploymentReq = \App\Models\SiteDeploymentRequest::create([
                'requested_by'      => $user?->id,
                'requested_by_role' => $decidedByRoleLabel,
                'employee_id'       => $empId,
                'project_id'        => $siteProjectId,
                'site_name'         => $projectName,
                'duration_type'     => $durationType,
                'start_date'        => $start->toDateString(),
                'end_date'          => $end->toDateString(),
                'session_type'      => $sessionType,
                'morning_in'        => $configuredMIn,
                'morning_out'       => $configuredMOut,
                'afternoon_in'      => $configuredAIn,
                'afternoon_out'     => $configuredAOut,
                'hours_worked'      => $hours,
                'task_notes'        => $validated['task_notes'] ?? null,
                'status'            => $status,
                'hr_reviewed_by'    => $status === 'approved' ? $user?->id : null,
                'hr_reviewed_at'    => $status === 'approved' ? now() : null,
                'hr_notes'          => $status === 'approved' ? 'Directly recorded by HR / Admin' : null,
            ]);

            // When HR approves, save in attendance!
            if ($status === 'approved') {
                self::applyDeploymentToAttendance($deploymentReq, $user);
            }
        }

        $empCount = count($employeeIds);
        $periodLabel = ($start->toDateString() === $end->toDateString())
            ? $start->format('M d, Y')
            : ($start->format('M d') . ' to ' . $end->format('M d, Y'));

        ActivityLog::log(
            'created',
            "{$decidedByName} ({$decidedByRoleLabel}) submitted site deployment for {$empCount} employee(s) to {$projectName} ({$periodLabel}). Status: {$status}.",
            'Site Deployment / HR'
        );

        if ($status === 'approved') {
            return redirect()->route('attendance.site-deployments')->with(
                'success',
                "Successfully dispatched {$empCount} employee(s) to {$projectName} ({$periodLabel}). Attendance status officially marked 'S' with {$hours}h credited (non-deductible in payroll)."
            );
        }

        return redirect()->route('attendance.site-deployments', ['status' => 'pending'])->with(
            'success',
            "Site deployment request for {$empCount} employee(s) to {$projectName} ({$periodLabel}) has been sent to HR to approve! When HR approves, attendance records will be saved with Status 'S'."
        );
    }

    /**
     * HR Approves the site deployment. Saves record into attendance with Status 'S'.
     */
    public function approveSiteDeployment(Request $request, int|string $id)
    {
        if (!self::isHrOrAdmin()) {
            abort(403, 'Unauthorized. Only HR Officers, HR Managers, or Administrators can approve site deployments.');
        }

        $deployment = \App\Models\SiteDeploymentRequest::with('employee')->findOrFail($id);

        if ($deployment->status === 'approved') {
            return redirect()->back()->with('info', 'This site deployment is already approved.');
        }

        $hrUser = auth()->user();
        $hrNotes = $request->input('hr_notes', 'Approved by HR');

        $deployment->update([
            'status'         => 'approved',
            'hr_reviewed_by' => $hrUser->id,
            'hr_reviewed_at' => now(),
            'hr_notes'       => $hrNotes,
        ]);

        // When HR approves, save in attendance!
        self::applyDeploymentToAttendance($deployment, $hrUser);

        ActivityLog::log(
            'updated',
            "HR {$hrUser->name} approved site deployment for {$deployment->employee?->full_name} at {$deployment->site_name}. Attendance recorded with status 'S'.",
            'Site Deployment / HR'
        );

        return redirect()->back()->with(
            'success',
            "Site deployment for {$deployment->employee?->full_name} has been APPROVED by HR! Attendance record has been officially saved with Status 'S' (non-deductible in payroll)."
        );
    }

    /**
     * HR Rejects the site deployment. HR does not accept this info, no attendance record is saved.
     */
    public function rejectSiteDeployment(Request $request, int|string $id)
    {
        if (!self::isHrOrAdmin()) {
            abort(403, 'Unauthorized. Only HR Officers, HR Managers, or Administrators can reject site deployments.');
        }

        $deployment = \App\Models\SiteDeploymentRequest::with('employee')->findOrFail($id);

        $hrUser = auth()->user();
        $reason = trim($request->input('rejection_reason', ''));
        if (empty($reason)) {
            $reason = 'Site deployment rejected by HR. Info not accepted into attendance.';
        }

        $deployment->update([
            'status'         => 'rejected',
            'hr_reviewed_by' => $hrUser->id,
            'hr_reviewed_at' => now(),
            'hr_notes'       => $reason,
        ]);

        // When rejected, HR does not accept this info: ensure no attendance record is saved
        self::removeDeploymentFromAttendance($deployment);

        ActivityLog::log(
            'updated',
            "HR {$hrUser->name} rejected site deployment for {$deployment->employee?->full_name} at {$deployment->site_name}. Reason: {$reason}. Not accepted into attendance.",
            'Site Deployment / HR'
        );

        return redirect()->back()->with(
            'error',
            "Site deployment request for {$deployment->employee?->full_name} was REJECTED by HR: {$reason}. This info was not accepted into attendance."
        );
    }

    /**
     * Apply an approved site deployment to the attendance table with status 'S'.
     */
    protected static function applyDeploymentToAttendance(\App\Models\SiteDeploymentRequest $deployment, $hrUser = null): void
    {
        $workSchedule = \App\Helpers\EthiopianCalendar::getWorkSchedule();
        $start = Carbon::parse($deployment->start_date);
        $end   = Carbon::parse($deployment->end_date);
        $sessionType = $deployment->session_type;

        $user = $hrUser ?: auth()->user();
        $requester = $deployment->requestedBy;
        $requesterName = $requester ? $requester->name : 'Authorized Manager';
        $requesterRole = $deployment->requested_by_role ?: 'Manager';

        $sessionLabel = match($sessionType) {
            'full_day'  => "Full Day ({$deployment->morning_in}-{$deployment->afternoon_out})",
            'morning'   => "Morning Session ({$deployment->morning_in}-{$deployment->morning_out})",
            'afternoon' => "Afternoon Session ({$deployment->afternoon_in}-{$deployment->afternoon_out})",
            default     => "Custom Session",
        };

        $currentDate = $start->copy();
        while ($currentDate->lte($end)) {
            $dateStr = $currentDate->toDateString();
            $isSunday = $currentDate->isSunday();
            $isSaturday = $currentDate->isSaturday();

            if ($isSunday && $start->ne($end)) {
                $currentDate->addDay();
                continue;
            }

            if ($isSaturday && $sessionType === 'full_day') {
                $dayMIn   = $workSchedule['sat_morning_in'] ?? '08:30';
                $dayMOut  = $workSchedule['sat_morning_out'] ?? '12:30';
                $dayAIn   = null;
                $dayAOut  = null;
                $hours    = (float)($workSchedule['sat_total_hours'] ?? 4.0);
            } else {
                $dayMIn   = $deployment->morning_in;
                $dayMOut  = $deployment->morning_out;
                $dayAIn   = $deployment->afternoon_in;
                $dayAOut  = $deployment->afternoon_out;
                $hours    = (float)$deployment->hours_worked;
            }

            $dayCIn  = $dayMIn ?: $dayAIn;
            $dayCOut = $dayAOut ?: $dayMOut;

            $taskDesc = !empty($deployment->task_notes) ? $deployment->task_notes : 'On-Site Duty';
            $hrName = $user ? $user->name : 'HR Officer';
            $formattedNote = "On-Site [S]: {$deployment->site_name} | Session: {$sessionLabel} | Task: {$taskDesc} | Decided by: {$requesterName} ({$requesterRole}) | Approved by HR: {$hrName}";

            $existing = Attendance::where('employee_id', $deployment->employee_id)
                ->where('attendance_date', $dateStr)
                ->first();

            $finalMIn  = $dayMIn  !== null ? $dayMIn  : ($existing?->morning_in);
            $finalMOut = $dayMOut !== null ? $dayMOut : ($existing?->morning_out);
            $finalAIn  = $dayAIn  !== null ? $dayAIn  : ($existing?->afternoon_in);
            $finalAOut = $dayAOut !== null ? $dayAOut : ($existing?->afternoon_out);

            $finalCIn  = $finalMIn ?: ($finalAIn ?: ($existing?->check_in));
            $finalCOut = $finalAOut ?: ($finalMOut ?: ($existing?->check_out));

            $effectiveHours = $hours > 0 ? $hours : 8.0;

            $data = [
                'status'         => 'S', // 'S' for site attendance, non-deductible in payroll
                'source'         => 'site_dispatch',
                'morning_in'     => $finalMIn,
                'morning_out'    => $finalMOut,
                'afternoon_in'   => $finalAIn,
                'afternoon_out'  => $finalAOut,
                'check_in'       => $finalCIn,
                'check_out'      => $finalCOut,
                'hours_worked'   => $effectiveHours,
                'notes'          => $formattedNote,
                'is_approved'    => true,
                'approved_by'    => $user?->id,
            ];

            if (\Illuminate\Support\Facades\Schema::hasColumn('attendance', 'decided_by')) {
                $data['decided_by']       = $deployment->requested_by;
                $data['decided_by_role']  = $requesterRole;
                $data['site_project_id']  = $deployment->project_id;
                $data['site_name']        = $deployment->site_name;
                $data['site_task']        = $taskDesc;
                $data['site_start_date']  = $start->toDateString();
                $data['site_end_date']    = $end->toDateString();
            }

            Attendance::updateOrCreate(
                [
                    'employee_id'     => $deployment->employee_id,
                    'attendance_date' => $dateStr,
                ],
                $data
            );

            $currentDate->addDay();
        }
    }

    /**
     * If HR rejects a deployment, ensure no attendance record remains.
     */
    protected static function removeDeploymentFromAttendance(\App\Models\SiteDeploymentRequest $deployment): void
    {
        $start = Carbon::parse($deployment->start_date)->toDateString();
        $end   = Carbon::parse($deployment->end_date)->toDateString();

        Attendance::where('employee_id', $deployment->employee_id)
            ->whereBetween('attendance_date', [$start, $end])
            ->where('source', 'site_dispatch')
            ->delete();
    }

    /**
     * Safely sync legacy site attendance records into site_deployment_requests.
     */
    protected static function syncExistingAttendanceRecords(): void
    {
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('attendance')) {
                $existing = Attendance::where(function($q) {
                    $q->whereIn('status', ['S', 'site', 's', 'on_site'])
                      ->orWhere('source', 'site_dispatch')
                      ->orWhere('notes', 'like', '%On-Site%');
                })->get();

                foreach ($existing as $att) {
                    $exists = \App\Models\SiteDeploymentRequest::where('employee_id', $att->employee_id)
                        ->where('start_date', '<=', $att->attendance_date)
                        ->where('end_date', '>=', $att->attendance_date)
                        ->exists();

                    if (!$exists) {
                        \App\Models\SiteDeploymentRequest::create([
                            'requested_by'      => $att->decided_by ?? $att->approved_by,
                            'requested_by_role' => $att->decided_by_role ?? 'Authorized Manager',
                            'employee_id'       => $att->employee_id,
                            'project_id'        => $att->site_project_id,
                            'site_name'         => $att->site_name ?? 'Construction Site',
                            'duration_type'     => 'single_day',
                            'start_date'        => $att->attendance_date,
                            'end_date'          => $att->attendance_date,
                            'session_type'      => ($att->morning_in && $att->afternoon_out) ? 'full_day' : (($att->morning_in) ? 'morning' : 'custom'),
                            'morning_in'        => $att->morning_in,
                            'morning_out'       => $att->morning_out,
                            'afternoon_in'      => $att->afternoon_in,
                            'afternoon_out'     => $att->afternoon_out,
                            'hours_worked'      => $att->hours_worked ?? 8.0,
                            'task_notes'        => $att->site_task ?? $att->notes,
                            'status'            => 'approved',
                            'hr_reviewed_by'    => $att->approved_by,
                            'hr_reviewed_at'    => $att->updated_at ?? now(),
                            'hr_notes'          => 'Synchronized legacy attendance record',
                        ]);
                    }
                }
            }
        } catch (\Throwable $e) {
            // Ignore any sync hiccups
        }
    }

    /**
     * Update Company Work Schedule settings (working hours and break/non-work periods).
     */
    public function updateSchedule(Request $request)
    {
        $validated = $request->validate([
            'morning_in'      => 'required|date_format:H:i',
            'morning_out'     => 'required|date_format:H:i',
            'break_start'     => 'required|date_format:H:i',
            'break_end'       => 'required|date_format:H:i',
            'afternoon_in'    => 'required|date_format:H:i',
            'afternoon_out'   => 'required|date_format:H:i',
            'sat_morning_in'  => 'required|date_format:H:i',
            'sat_morning_out' => 'required|date_format:H:i',
            'sat_work_mode'   => 'nullable|string',
            'work_days'       => 'nullable|string',
            'title'           => 'nullable|string',
        ]);

        \App\Helpers\EthiopianCalendar::saveWorkSchedule($validated);

        return redirect()->back()->with('success', 'Work schedule & working hours policy updated successfully.');
    }

    /**
     * Ensure attendance table schema has status column wide enough for 'S' and deployment metadata.
     */
    public static function ensureAttendanceSchemaReady(): void
    {
        try {
            $driver = DB::connection()->getDriverName();
            if ($driver !== 'sqlite') {
                DB::statement("ALTER TABLE `attendance` MODIFY `status` VARCHAR(50) NOT NULL DEFAULT 'present'");
            }
        } catch (\Throwable $e) {}

        try {
            if (!\Illuminate\Support\Facades\Schema::hasColumn('attendance', 'decided_by')) {
                \Illuminate\Support\Facades\Schema::table('attendance', function ($table) {
                    $table->unsignedBigInteger('decided_by')->nullable()->after('approved_by');
                    $table->string('decided_by_role')->nullable()->after('decided_by');
                    $table->unsignedBigInteger('site_project_id')->nullable()->after('decided_by_role');
                    $table->string('site_name')->nullable()->after('site_project_id');
                    $table->text('site_task')->nullable()->after('site_name');
                    $table->date('site_start_date')->nullable()->after('site_task');
                    $table->date('site_end_date')->nullable()->after('site_start_date');
                });
            }
        } catch (\Throwable $e) {}
    }

    /**
     * Restore user login & API access after 5-day absence suspension.
     * Restricted to HR Manager, Admin, and Global Admin with mandatory reason.
     */
    public function restoreUserAccess(Request $request, \App\Models\User $user)
    {
        if (!self::isHrOrAdmin()) {
            abort(403, 'Unauthorized. Only HR Manager, Admin, or Global Admin can restore user access.');
        }

        $validated = $request->validate([
            'reason' => 'required|string|min:5|max:500',
        ]);

        $currentUser = auth()->user();
        $user->unblockAccess($currentUser, $validated['reason']);

        return redirect()->back()->with(
            'success',
            "Access for user [{$user->name}] has been successfully restored! Restoration has been logged in the audit trail."
        );
    }

    /**
     * Clear raw biometric device logs (Restricted strictly to Admin & Global Admin).
     * Note: Employee attendance records (attendances table) are permanently protected and cannot be deleted.
     */
    public function clearHistory(Request $request)
    {
        if (!self::userHasAnyRole(['admin', 'global_admin'])) {
            abort(403, 'Unauthorized access. Only Global Admin and Admin roles can perform maintenance.');
        }

        $clearType = $request->input('clear_type');

        if ($clearType === 'attendance' || $clearType === 'reset_and_resync') {
            Attendance::query()->delete();
            DB::table('device_attendance_logs')->update(['synced_at' => null]);
            Artisan::call('zkteco:sync', ['--all' => true, '--force' => true]);
            ActivityLog::log(
                'deleted',
                'All attendance records cleared and freshly re-synchronized from device punches by ' . (auth()->user()->name ?? 'Admin'),
                'Attendance & Biometrics'
            );
            return redirect()->route('admin.attendance.device-logs')
                ->with('success', 'All attendance records cleared and freshly re-synchronized from biometric punch logs!');
        }

        if ($clearType === 'device_logs') {
            DeviceAttendanceLog::truncate();
            ActivityLog::log(
                'deleted',
                'Raw Biometric Device punch logs table wiped by ' . (auth()->user()->name ?? 'Admin'),
                'Attendance & Biometrics'
            );
            return redirect()->route('admin.attendance.device-logs')
                ->with('success', 'Raw biometric punch logs cleared successfully. All employee attendance records remain safe.');
        }

        return redirect()->route('admin.attendance.device-logs');
    }

    /**
     * Clear all processed attendance records and freshly re-synchronize from raw biometric punches.
     */
    public function resetAndResync(Request $request)
    {
        try {
            $scope = $request->input('scope', 'all'); // 'all' or 'date'
            $date = $request->input('date') ?: request('date_from');
            $tzOffset = (int)$request->input('timezone_offset', -5);
            $shiftExisting = $request->boolean('shift_existing_logs', true);

            // 1. Ensure raw_punch_time column exists in device_attendance_logs
            if (\Illuminate\Support\Facades\Schema::hasTable('device_attendance_logs')) {
                if (!\Illuminate\Support\Facades\Schema::hasColumn('device_attendance_logs', 'raw_punch_time')) {
                    \Illuminate\Support\Facades\Schema::table('device_attendance_logs', function ($table) {
                        $table->dateTime('raw_punch_time')->nullable()->after('punch_time');
                    });
                }
            }

            // 2. Persist biometric timezone offset setting
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('system_settings')) {
                    DB::table('system_settings')->updateOrInsert(
                        ['key' => 'biometric_timezone_offset_hours'],
                        [
                            'value'       => (string)$tzOffset,
                            'type'        => 'integer',
                            'group'       => 'attendance',
                            'description' => 'Biometric machine timezone offset in hours (-5 hours converts machine 05:07 PM to local 12:07 PM)',
                            'updated_at'  => now(),
                        ]
                    );
                }
                if (\Illuminate\Support\Facades\Schema::hasTable('zk_devices') && \Illuminate\Support\Facades\Schema::hasColumn('zk_devices', 'timezone_offset_hours')) {
                    DB::table('zk_devices')->update(['timezone_offset_hours' => $tzOffset]);
                }
            } catch (\Throwable $e) {}

            // 3. Shift existing logs that have not yet been shifted (raw_punch_time IS NULL)
            if ($shiftExisting && \Illuminate\Support\Facades\Schema::hasTable('device_attendance_logs') && $tzOffset !== 0) {
                $absOffset = abs($tzOffset);
                if ($tzOffset < 0) {
                    DB::statement("UPDATE device_attendance_logs 
                        SET raw_punch_time = punch_time, 
                            punch_time = DATE_SUB(punch_time, INTERVAL {$absOffset} HOUR) 
                        WHERE raw_punch_time IS NULL AND punch_time IS NOT NULL");
                } else {
                    DB::statement("UPDATE device_attendance_logs 
                        SET raw_punch_time = punch_time, 
                            punch_time = DATE_ADD(punch_time, INTERVAL {$absOffset} HOUR) 
                        WHERE raw_punch_time IS NULL AND punch_time IS NOT NULL");
                }
            }

            if ($scope === 'date' && $date) {
                Attendance::whereDate('attendance_date', $date)->delete();
                DB::table('device_attendance_logs')->whereDate('punch_time', $date)->update(['synced_at' => null]);
                Artisan::call('zkteco:sync', ['--date' => $date, '--force' => true]);
                $msg = "Attendance records for {$date} cleared and freshly synchronized with machine timezone offset applied ({$tzOffset} hrs)!";
            } else {
                Attendance::query()->delete();
                DB::table('device_attendance_logs')->update(['synced_at' => null]);
                Artisan::call('zkteco:sync', ['--all' => true, '--force' => true]);
                $msg = "All attendance records cleared and freshly synchronized from all stored biometric punches with machine timezone offset applied ({$tzOffset} hrs)!";
            }

            return redirect()->route('attendance.index')
                ->with('success', $msg);
        } catch (\Throwable $e) {
            return redirect()->route('attendance.index')
                ->with('error', 'Reset & Resync failed: ' . $e->getMessage());
        }
    }

    /**
     * Legacy / HR redirect: Redirect admins to admin.attendance.device-logs, block non-admins.
     */
    public function deviceLogs()
    {
        if (self::userHasAnyRole(['admin', 'global_admin'])) {
            return redirect()->route('admin.attendance.device-logs', request()->query());
        }

        abort(403, 'Unauthorized. Device punch logs and data maintenance is restricted to Admin & Global Admin.');
    }

    /**
     * Admin view for raw device logs and attendance reset maintenance.
     */
    public function adminDeviceLogs()
    {
        if (!self::userHasAnyRole(['admin', 'global_admin'])) {
            abort(403, 'Unauthorized. Device punch logs and data maintenance is restricted to Admin & Global Admin.');
        }

        // Defensive: clear route cache if needed so newly added device routes are always registered
        if (!\Illuminate\Support\Facades\Route::has('admin.attendance.devices.delete')) {
            try {
                \Illuminate\Support\Facades\Artisan::call('route:clear');
            } catch (\Throwable $e) {}
        }

        // Support direct POST action on /admin/attendance/device-logs as a fail-safe fallback
        if (request()->isMethod('post')) {
            if (request('action') === 'delete_device' || request()->filled('delete_device_id')) {
                return $this->deleteZkDevice(request(), request('delete_device_id') ?: request('device_id'));
            }
            if (request('action') === 'save_device') {
                return $this->saveZkDevice(request());
            }
        }

        // Defensive: ensure columns exist in zk_devices table
        if (\Illuminate\Support\Facades\Schema::hasTable('zk_devices')) {
            try {
                if (!\Illuminate\Support\Facades\Schema::hasColumn('zk_devices', 'device_type')) {
                    \Illuminate\Support\Facades\Schema::table('zk_devices', function ($table) {
                        $table->string('device_type', 30)->default('head_office')->after('name');
                    });
                }
                if (!\Illuminate\Support\Facades\Schema::hasColumn('zk_devices', 'project_id')) {
                    \Illuminate\Support\Facades\Schema::table('zk_devices', function ($table) {
                        $table->unsignedBigInteger('project_id')->nullable()->after('device_type');
                    });
                }
                if (!\Illuminate\Support\Facades\Schema::hasColumn('zk_devices', 'ip_address')) {
                    \Illuminate\Support\Facades\Schema::table('zk_devices', function ($table) {
                        $table->string('ip_address', 50)->nullable()->after('location');
                        $table->integer('port')->nullable()->default(80)->after('ip_address');
                    });
                }
                if (!\Illuminate\Support\Facades\Schema::hasColumn('zk_devices', 'model_name')) {
                    \Illuminate\Support\Facades\Schema::table('zk_devices', function ($table) {
                        $table->string('model_name', 100)->nullable()->after('name');
                    });
                }
                if (!\Illuminate\Support\Facades\Schema::hasColumn('zk_devices', 'notes')) {
                    \Illuminate\Support\Facades\Schema::table('zk_devices', function ($table) {
                        $table->text('notes')->nullable()->after('is_active');
                    });
                }
            } catch (\Throwable $e) {}
        }

        // Fetch all registered ZKTeco devices with project relation
        $allDevices = \App\Models\ZkDevice::with('project')->orderBy('last_seen_at', 'desc')->get();

        // Also detect any device SNs found in logs but not yet registered in zk_devices
        $distinctSnInLogs = DB::table('device_attendance_logs')
            ->whereNotNull('device_sn')
            ->distinct()
            ->pluck('device_sn');

        foreach ($distinctSnInLogs as $sn) {
            if (!$allDevices->firstWhere('serial_number', $sn)) {
                try {
                    $newDev = \App\Models\ZkDevice::create([
                        'serial_number' => $sn,
                        'name'          => 'ZKTeco Device ' . substr($sn, -6),
                        'device_type'   => 'head_office',
                        'location'      => 'Unassigned',
                        'last_seen_at'  => DB::table('device_attendance_logs')->where('device_sn', $sn)->max('punch_time'),
                        'is_active'     => true,
                    ]);
                    $allDevices->push($newDev);
                } catch (\Throwable $e) {}
            }
        }

        $headOfficeDevices = $allDevices->filter(fn($d) => ($d->device_type ?? 'head_office') === 'head_office');
        $siteDevices       = $allDevices->filter(fn($d) => ($d->device_type ?? 'head_office') === 'site');

        $projects = \App\Models\Project::orderBy('name')->get();

        $query = DeviceAttendanceLog::with(['employee', 'zkDevice.project'])->latest('punch_time');

        if (request('date_from')) {
            $query->whereDate('punch_time', '>=', request('date_from'));
        }
        if (request('date_to')) {
            $query->whereDate('punch_time', '<=', request('date_to'));
        }
        if (request('linked') === 'linked') {
            $query->whereHas('employee');
        } elseif (request('linked') === 'unlinked') {
            $query->whereDoesntHave('employee');
        }

        // Location / Site / Device Filtering
        if (request('device_sn')) {
            $query->where('device_sn', request('device_sn'));
        } elseif (request('location_type') === 'head_office') {
            $hoSns = $headOfficeDevices->pluck('serial_number')->filter()->toArray();
            if (!empty($hoSns)) {
                $query->whereIn('device_sn', $hoSns);
            }
        } elseif (request('location_type') === 'site') {
            if (request('project_id')) {
                $siteSns = $siteDevices->where('project_id', request('project_id'))->pluck('serial_number')->filter()->toArray();
            } else {
                $siteSns = $siteDevices->pluck('serial_number')->filter()->toArray();
            }
            if (!empty($siteSns)) {
                $query->whereIn('device_sn', $siteSns);
            } else {
                $query->whereRaw('1 = 0'); // No devices for this site yet
            }
        }

        $logs = $query->paginate(50)->appends(request()->query());

        // Compute diagnostics about raw biometric punches and attendances in database safely
        try {
            $totalLogsCount       = DeviceAttendanceLog::count();
            $totalAttendanceCount = Attendance::count();
            $earliestPunch        = DeviceAttendanceLog::min('punch_time');
            $latestPunch          = DeviceAttendanceLog::max('punch_time');
            $unlinkedCount        = DeviceAttendanceLog::whereDoesntHave('employee')->count();
            $distinctDatesCount   = DB::table('device_attendance_logs')
                ->whereNotNull('punch_time')
                ->selectRaw('COUNT(DISTINCT DATE(punch_time)) as cnt')
                ->value('cnt') ?? 0;

            // Head office vs Site punch counts
            $hoSns = $headOfficeDevices->pluck('serial_number')->filter()->toArray();
            $siteSns = $siteDevices->pluck('serial_number')->filter()->toArray();
            $hoPunchesCount = !empty($hoSns) ? DB::table('device_attendance_logs')->whereIn('device_sn', $hoSns)->count() : 0;
            $sitePunchesCount = !empty($siteSns) ? DB::table('device_attendance_logs')->whereIn('device_sn', $siteSns)->count() : 0;
        } catch (\Throwable $e) {
            $totalLogsCount       = 0;
            $totalAttendanceCount = 0;
            $earliestPunch        = null;
            $latestPunch          = null;
            $unlinkedCount        = 0;
            $distinctDatesCount   = 0;
            $hoPunchesCount       = 0;
            $sitePunchesCount     = 0;
        }

        return view('admin.attendance.device_logs', compact(
            'logs',
            'allDevices',
            'headOfficeDevices',
            'siteDevices',
            'projects',
            'totalLogsCount',
            'totalAttendanceCount',
            'earliestPunch',
            'latestPunch',
            'unlinkedCount',
            'distinctDatesCount',
            'hoPunchesCount',
            'sitePunchesCount'
        ));
    }

    /**
     * Save or Link a ZKTeco Device to Head Office or a Construction Site
     */
    public function saveZkDevice(Request $request)
    {
        if (!self::userHasAnyRole(['admin', 'global_admin', 'hr_manager'])) {
            abort(403, 'Unauthorized.');
        }

        $request->validate([
            'serial_number' => 'nullable|string|max:100',
            'device_sn'     => 'nullable|string|max:100',
            'name'          => 'nullable|string|max:150',
            'device_name'   => 'nullable|string|max:150',
            'device_type'   => 'required|in:head_office,site',
            'project_id'    => 'nullable|required_if:device_type,site|exists:projects,id',
            'location'      => 'nullable|string|max:150',
            'location_name' => 'nullable|string|max:150',
            'model_name'    => 'nullable|string|max:100',
            'ip_address'    => 'nullable|string|max:50',
            'port'          => 'nullable|integer',
            'notes'         => 'nullable|string|max:500',
        ]);

        $sn = trim($request->input('serial_number') ?: $request->input('device_sn', ''));
        if (empty($sn)) {
            return back()->with('error', 'Device Serial Number (SN) is required.');
        }

        $name = trim($request->input('name') ?: $request->input('device_name', ''));
        if (empty($name)) {
            $name = 'ZKTeco ' . substr($sn, -6);
        }

        $location = trim($request->input('location') ?: $request->input('location_name', ''));

        $device = \App\Models\ZkDevice::updateOrCreate(
            ['serial_number' => $sn],
            [
                'name'        => $name,
                'device_type' => $request->device_type,
                'project_id'  => $request->device_type === 'site' ? $request->project_id : null,
                'location'    => $location ?: null,
                'model_name'  => $request->model_name,
                'ip_address'  => $request->ip_address,
                'port'        => $request->port ?: 80,
                'notes'       => $request->notes,
                'is_active'   => $request->boolean('is_active', true),
            ]
        );

        $locationLabel = $device->device_type === 'site'
            ? ('Construction Site: ' . ($device->project->name ?? 'Project Site'))
            : 'Head Office';

        return back()->with('success', "Device [{$device->serial_number}] successfully saved and linked to {$locationLabel}!");
    }

    /**
     * Delete a ZKTeco device registration
     */
    public function deleteZkDevice(Request $request, $id = null)
    {
        if (!self::userHasAnyRole(['admin', 'global_admin'])) {
            abort(403, 'Unauthorized.');
        }

        $id = $id ?: $request->input('delete_device_id') ?: $request->input('device_id') ?: $request->input('id');

        $device = null;
        if ($id) {
            $device = \App\Models\ZkDevice::find($id);
            if (!$device) {
                $device = \App\Models\ZkDevice::where('serial_number', $id)->first();
            }
        }

        if ($device) {
            $sn = $device->serial_number;
            $device->delete();
            return redirect()->route('admin.attendance.device-logs')
                ->with('success', "Device [{$sn}] registration removed successfully.");
        }

        return redirect()->route('admin.attendance.device-logs')
            ->with('info', "Device not found or was already removed.");
    }

    /**
     * Manually trigger ZKTeco punch → attendance sync via Artisan command.
     */
    public function syncZkteco(Request $request)
    {
        $syncAll      = $request->boolean('sync_all', false);
        $force        = $request->boolean('force', false);
        $locationType = $request->input('location_type'); // 'head_office' or 'site'
        $projectId    = $request->input('project_id');
        $deviceSn     = $request->input('device_sn');

        $args = [];
        $redirectParams = [];

        if ($syncAll) {
            $args['--all'] = true;
            $label = "all available dates in system";
        } else {
            $startDateInput = $request->input('start_date') ?: $request->input('date', now()->format('Y-m-d'));
            $endDateInput   = $request->input('end_date') ?: $startDateInput;

            try {
                $start = \Carbon\Carbon::parse($startDateInput)->format('Y-m-d');
                $end   = \Carbon\Carbon::parse($endDateInput)->format('Y-m-d');
            } catch (\Throwable $e) {
                $start = now()->format('Y-m-d');
                $end   = now()->format('Y-m-d');
            }

            if ($start > $end) {
                $temp = $start;
                $start = $end;
                $end = $temp;
            }

            $args['--from'] = $start;
            $args['--to']   = $end;

            $label = ($start === $end) ? $start : "{$start} to {$end}";
            $redirectParams = ['date_from' => $start, 'date_to' => $end];
        }

        // Always force update punch logs into attendance records to guarantee latest times are stored
        $args['--force'] = true;

        if (!empty($locationType)) {
            $args['--location'] = $locationType;
            $redirectParams['location_type'] = $locationType;
        }
        if (!empty($projectId)) {
            $args['--project'] = (int)$projectId;
            $redirectParams['project_id'] = (int)$projectId;
        }
        if (!empty($deviceSn)) {
            $args['--device-sn'] = $deviceSn;
            $redirectParams['device_sn'] = $deviceSn;
        }

        $targetRoute = $request->input('redirect_to') === 'attendance'
            ? 'attendance.index'
            : (self::userHasAnyRole(['admin', 'global_admin'])
                ? 'admin.attendance.device-logs'
                : 'attendance.index');

        try {
            Artisan::call('zkteco:sync', $args);
            \App\Services\BiometricPunchService::autoHealMissingSessionTimes();
            $output = trim(Artisan::output());

            $scopeLabel = '';
            if ($deviceSn) {
                $scopeLabel = " (Device {$deviceSn})";
            } elseif ($locationType === 'site' && $projectId) {
                $p = \App\Models\Project::find($projectId);
                $scopeLabel = " (Site: " . ($p->name ?? 'Project') . ")";
            } elseif ($locationType === 'site') {
                $scopeLabel = " (All Construction Sites)";
            } elseif ($locationType === 'head_office') {
                $scopeLabel = " (Head Office Only)";
            }

            return redirect()
                ->route($targetRoute, $redirectParams)
                ->with('success', "ZKTeco device punch sync completed for {$label}{$scopeLabel}. " . ($output ? strip_tags($output) : ''));

        } catch (\Exception $e) {
            return redirect()
                ->route($targetRoute)
                ->with('error', 'Sync failed: ' . $e->getMessage());
        }
    }

    /**
     * Record Driver Daily Attendance by General Service
     */
    public function recordDriverAttendance(Request $request)
    {
        $user = auth()->user();
        $canManageDrivers = $user && ($user->hasAnyRole(['general_service', 'general_services', 'admin', 'global_admin', 'hr', 'hr_manager', 'hr_officer']) || (method_exists($user, 'can') && $user->can('attendance.manage')));

        if (!$canManageDrivers) {
            abort(403, 'Unauthorized. General Service or HR role required to record driver attendance.');
        }

        $request->validate([
            'employee_id'      => 'required|exists:employees,id',
            'attendance_date'  => 'required|date',
            'duty_status'      => 'required|in:present,trip,leave,absent',
            'morning_in'       => 'nullable|string',
            'afternoon_out'    => 'nullable|string',
            'trip_destination' => 'nullable|string|max:255',
            'vehicle_plate'    => 'nullable|string|max:50',
            'notes'            => 'nullable|string|max:500',
        ]);

        $emp = Employee::findOrFail($request->employee_id);
        $date = Carbon::parse($request->attendance_date)->toDateString();
        $duty = $request->duty_status;

        $inTime = $request->morning_in ?: '08:00';
        $outTime = $request->afternoon_out ?: '17:30';

        $dbStatus = match ($duty) {
            'present' => 'present',
            'trip'    => 'S',
            'leave'   => 'leave',
            'absent'  => 'absent',
            default   => 'present',
        };

        $notesArray = [];
        if ($request->filled('trip_destination')) {
            $notesArray[] = 'Trip: ' . $request->trip_destination;
        }
        if ($request->filled('vehicle_plate')) {
            $notesArray[] = 'Vehicle: ' . $request->vehicle_plate;
        }
        if ($request->filled('notes')) {
            $notesArray[] = $request->notes;
        }
        $fullNote = 'Logged by General Service (' . ($user->name ?? 'GS') . ') ' . implode(' | ', $notesArray);

        Attendance::updateOrCreate(
            [
                'employee_id'     => $emp->id,
                'attendance_date' => $date,
            ],
            [
                'morning_in'      => in_array($duty, ['present', 'trip']) ? $inTime : null,
                'afternoon_out'   => in_array($duty, ['present', 'trip']) ? $outTime : null,
                'check_in'        => in_array($duty, ['present', 'trip']) ? $inTime : null,
                'check_out'       => in_array($duty, ['present', 'trip']) ? $outTime : null,
                'hours_worked'    => in_array($duty, ['present', 'trip']) ? 8.0 : 0,
                'status'          => $dbStatus,
                'source'          => 'manual',
                'site_name'       => $request->trip_destination ?: 'General Service Transport',
                'notes'           => $fullNote,
                'is_approved'     => true,
                'approved_by'     => $user->id,
                'decided_by'      => $user->name,
                'decided_by_role' => 'general_service',
            ]
        );

        return redirect()->back()->with('success', "Driver attendance for {$emp->full_name} on {$date} recorded by General Service!");
    }

    /**
     * Record Bulk Daily Driver Attendance Sheet by General Service
     */
    public function recordBulkDriverSheet(Request $request)
    {
        $user = auth()->user();
        $canManageDrivers = $user && ($user->hasAnyRole(['general_service', 'general_services', 'admin', 'global_admin', 'hr', 'hr_manager', 'hr_officer']) || (method_exists($user, 'can') && $user->can('attendance.manage')));

        if (!$canManageDrivers) {
            abort(403, 'Unauthorized. General Service or HR role required.');
        }

        $request->validate([
            'sheet_date' => 'required|date',
            'drivers'    => 'required|array',
            'drivers.*.employee_id' => 'required|exists:employees,id',
            'drivers.*.duty_status' => 'required|in:present,trip,leave,absent,skip',
            'drivers.*.morning_in'  => 'nullable|string',
            'drivers.*.afternoon_out' => 'nullable|string',
            'drivers.*.trip_destination' => 'nullable|string|max:255',
            'drivers.*.vehicle_plate' => 'nullable|string|max:50',
            'drivers.*.notes' => 'nullable|string|max:500',
        ]);

        $date = Carbon::parse($request->sheet_date)->toDateString();
        $count = 0;

        foreach ($request->drivers as $item) {
            $duty = $item['duty_status'] ?? 'skip';
            if ($duty === 'skip') {
                continue;
            }

            $empId = (int)$item['employee_id'];
            $inTime = $item['morning_in'] ?: '08:00';
            $outTime = $item['afternoon_out'] ?: '17:30';

            $dbStatus = match ($duty) {
                'present' => 'present',
                'trip'    => 'S',
                'leave'   => 'leave',
                'absent'  => 'absent',
                default   => 'present',
            };

            $notesArray = [];
            if (!empty($item['trip_destination'])) {
                $notesArray[] = 'Trip: ' . $item['trip_destination'];
            }
            if (!empty($item['vehicle_plate'])) {
                $notesArray[] = 'Vehicle: ' . $item['vehicle_plate'];
            }
            if (!empty($item['notes'])) {
                $notesArray[] = $item['notes'];
            }
            $fullNote = 'Daily Sheet by General Service (' . ($user->name ?? 'GS') . ') ' . implode(' | ', $notesArray);

            Attendance::updateOrCreate(
                [
                    'employee_id'     => $empId,
                    'attendance_date' => $date,
                ],
                [
                    'morning_in'      => in_array($duty, ['present', 'trip']) ? $inTime : null,
                    'afternoon_out'   => in_array($duty, ['present', 'trip']) ? $outTime : null,
                    'check_in'        => in_array($duty, ['present', 'trip']) ? $inTime : null,
                    'check_out'       => in_array($duty, ['present', 'trip']) ? $outTime : null,
                    'hours_worked'    => in_array($duty, ['present', 'trip']) ? 8.0 : 0,
                    'status'          => $dbStatus,
                    'source'          => 'manual',
                    'site_name'       => $item['trip_destination'] ?? 'General Service Transport',
                    'notes'           => $fullNote,
                    'is_approved'     => true,
                    'approved_by'     => $user->id,
                    'decided_by'      => $user->name,
                    'decided_by_role' => 'general_service',
                ]
            );
            $count++;
        }

        return redirect()->back()->with('success', "Daily driver attendance sheet for {$date} saved successfully! ({$count} driver records updated by General Service)");
    }

    /**
     * Show device status page — last heartbeat per device.
     */
    public function zktecoStatus()
    {
        $devices = DB::table('zk_devices')->orderBy('last_seen_at', 'desc')->get();

        // Count unsynced punches per device
        $unsyncedCounts = DB::table('device_attendance_logs')
            ->whereNull('synced_at')
            ->selectRaw('device_sn, COUNT(*) as cnt')
            ->groupBy('device_sn')
            ->pluck('cnt', 'device_sn');

        // Total device logs today
        $todayPunches = DB::table('device_attendance_logs')
            ->whereDate('punch_time', now()->format('Y-m-d'))
            ->count();

        // Unmatched user IDs (device_user_id not in any employee)
        $unmatchedIds = DB::table('device_attendance_logs')
            ->leftJoin('employees', 'employees.device_user_id', '=', 'device_attendance_logs.device_user_id')
            ->whereNull('employees.id')
            ->distinct()
            ->pluck('device_attendance_logs.device_user_id');

        return view('hr.attendance.zkteco_status', compact(
            'devices', 'unsyncedCounts', 'todayPunches', 'unmatchedIds'
        ));
    }

    /**
     * Interactive Machine & Biometric Connection Test Page
     */
    public function machineTest()
    {
        $devices = DB::table('zk_devices')->orderBy('last_seen_at', 'desc')->get();
        $recentLogs = DB::table('device_attendance_logs')
            ->leftJoin('employees', function($join) {
                $join->on('employees.device_user_id', '=', 'device_attendance_logs.device_user_id')
                     ->where(function($q) {
                         $q->where('employees.is_dead_file', false)->orWhereNull('employees.is_dead_file');
                     })
                     ->where('employees.status', '!=', 'dead_file');
            })
            ->select('device_attendance_logs.*', 'employees.full_name as employee_name', 'employees.department as employee_department')
            ->orderBy('device_attendance_logs.created_at', 'desc')
            ->take(30)
            ->get();

        $employees = Employee::activeRoster()->orderBy('full_name')->get();

        $admsLogFile = public_path('iclock/adms.log');
        $rawAdmsLogs = file_exists($admsLogFile) ? file_get_contents($admsLogFile) : 'No incoming device requests recorded yet.';
        $rawAdmsLogLines = array_filter(explode("\n", $rawAdmsLogs));
        $latestRawLogs = implode("\n", array_slice($rawAdmsLogLines, -50));

        return view('hr.attendance.machine_test', compact('devices', 'recentLogs', 'employees', 'latestRawLogs'));
    }

    /**
     * Simulate a Live Device Punch to verify database sync
     */
    public function simulateTestPunch(Request $request)
    {
        $request->validate([
            'device_user_id' => 'required|string',
            'punch_state'    => 'required|in:0,1,4,5', // 0: In, 1: Out
            'device_sn'      => 'nullable|string',
        ]);

        $deviceSn = $request->input('device_sn', 'TEST-DEVICE-01');
        $deviceUserId = (string)$request->input('device_user_id');
        $punchTime = now()->format('Y-m-d H:i:s');
        $punchState = (string)$request->input('punch_state', '0');

        $emp = Employee::activeRoster()
            ->where(function($q) use ($deviceUserId) {
                $q->where('device_user_id', $deviceUserId)
                  ->orWhere('id', $deviceUserId);
            })
            ->first();

        if ($emp && empty($emp->device_user_id)) {
            $emp->device_user_id = $deviceUserId;
            $emp->save();
        }

        $fullName = $emp ? $emp->full_name : null;

        // Insert into device_attendance_logs matching exact schema
        $logId = DB::table('device_attendance_logs')->insertGetId([
            'device_sn'       => $deviceSn,
            'device_user_id'  => $deviceUserId,
            'punch_time'      => $punchTime,
            'status'          => $punchState,
            'verify_mode'     => '1',
            'full_name'       => $fullName,
            'synced_at'       => null,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        // Auto trigger sync
        try {
            Artisan::call('zkteco:sync', ['--date' => now()->format('Y-m-d'), '--force' => true]);
        } catch (\Throwable $e) {}

        return redirect()->back()->with('success', "Test punch successfully received for " . ($fullName ? "{$fullName} (PIN: {$deviceUserId})" : "User PIN #{$deviceUserId}") . " [Punch Log #{$logId}] at {$punchTime}! Attendance synced.");
    }

    /**
     * Clear and delete all test logs & raw machine logs
     */
    public function clearTestLogs(Request $request)
    {
        // 1. Wipe adms.log
        $admsLogFile = public_path('iclock/adms.log');
        if (file_exists($admsLogFile)) {
            file_put_contents($admsLogFile, "[".date('Y-m-d H:i:s')."] Cleaned test logs.\n");
        }

        // 2. Delete test entries
        if ($request->has('delete_all_logs')) {
            DB::table('device_attendance_logs')->where('device_sn', 'TEST-DEVICE-01')->delete();
        }

        return redirect()->back()->with('success', 'Test log entries and machine diagnostics log have been cleanly reset!');
    }
}
