<?php

namespace App\Helpers;

use App\Models\SystemSetting;

class EthiopianCalendar
{
    const MONTHS_AM = [
        1  => 'መስከረም',
        2  => 'ጥቅምት',
        3  => 'ኅዳር',
        4  => 'ታኅሣሥ',
        5  => 'ጥር',
        6  => 'የካቲት',
        7  => 'መጋቢት',
        8  => 'ሚያዝያ',
        9  => 'ግንቦት',
        10 => 'ሰኔ',
        11 => 'ሐምሌ',
        12 => 'ነሐሴ',
        13 => 'ጳጉሜ',
    ];

    const MONTHS_EN = [
        1  => 'Meskerem',
        2  => 'Tikimt',
        3  => 'Hidar',
        4  => 'Tahsas',
        5  => 'Tir',
        6  => 'Yakatit',
        7  => 'Megabit',
        8  => 'Miazia',
        9  => 'Ginbot',
        10 => 'Sene',
        11 => 'Hamle',
        12 => 'Nehase',
        13 => 'Pagume',
    ];

    /**
     * Convert Gregorian date to Ethiopian calendar details.
     *
     * @param \DateTimeInterface|string|null $date
     * @return array
     */
    public static function toEthiopian($date): array
    {
        if (!$date) {
            return [];
        }

        if ($date instanceof \DateTimeInterface) {
            $year  = (int)$date->format('Y');
            $month = (int)$date->format('m');
            $day   = (int)$date->format('d');
        } else {
            $ts = strtotime($date);
            if (!$ts) {
                return [];
            }
            $parts = explode('-', date('Y-m-d', $ts));
            $year  = (int)$parts[0];
            $month = (int)$parts[1];
            $day   = (int)$parts[2];
        }

        $a = intdiv(14 - $month, 12);
        $y = $year + 4800 - $a;
        $m = $month + 12 * $a - 3;
        $jdn = $day + intdiv(153 * $m + 2, 5) + 365 * $y + intdiv($y, 4) - intdiv($y, 100) + intdiv($y, 400) - 32045;

        $ethiopianEpoch = 1723856;
        $r = ($jdn - $ethiopianEpoch) % 1461;
        $n = ($r % 365) + 365 * intdiv($r, 1460);

        $ethYear  = 4 * intdiv($jdn - $ethiopianEpoch, 1461) + intdiv($r, 365) - intdiv($r, 1460);
        $ethMonth = intdiv($n, 30) + 1;
        $ethDay   = ($n % 30) + 1;

        $mAm = self::MONTHS_AM[$ethMonth] ?? '';
        $mEn = self::MONTHS_EN[$ethMonth] ?? '';

        return [
            'year'         => $ethYear,
            'month'        => $ethMonth,
            'day'          => $ethDay,
            'month_am'     => $mAm,
            'month_en'     => $mEn,
            'formatted_am' => "{$mAm} {$ethDay}, {$ethYear} ዓ.ም.",
            'formatted_en' => "{$mEn} {$ethDay}, {$ethYear} E.C.",
            'short_am'     => "{$mAm} {$ethDay}",
            'short_en'     => "{$mEn} {$ethDay}",
        ];
    }

    /**
     * Format a date into Ethiopian calendar string.
     */
    public static function format($date, string $lang = 'am'): string
    {
        $et = self::toEthiopian($date);
        if (empty($et)) {
            return '';
        }
        return $lang === 'en' ? $et['formatted_en'] : $et['formatted_am'];
    }

    /**
     * Convert standard 24h/12h time string (e.g. 08:30) to Ethiopian local time.
     * (Ethiopian daytime starts at 6:00 AM international = 12:00 sunrise).
     */
    public static function toEthiopianTime(?string $timeStr): string
    {
        if (!$timeStr) {
            return '';
        }

        $parts = explode(':', trim($timeStr));
        $hour  = (int)($parts[0] ?? 0);
        $min   = sprintf('%02d', (int)($parts[1] ?? 0));

        // Ethiopian hour is 6 hours behind international
        $ethHour = ($hour - 6 + 24) % 12;
        if ($ethHour === 0) {
            $ethHour = 12;
        }

        if ($hour >= 6 && $hour < 12) {
            $periodAm = 'ጠዋት';
            $periodEn = 'Morning';
        } elseif ($hour >= 12 && $hour < 13) {
            $periodAm = 'ቀትር';
            $periodEn = 'Midday';
        } elseif ($hour >= 13 && $hour < 18) {
            $periodAm = 'ከሰዓት';
            $periodEn = 'Afternoon';
        } elseif ($hour >= 18 && $hour < 24) {
            $periodAm = 'ምሽት';
            $periodEn = 'Night';
        } else {
            $periodAm = 'ሌሊት';
            $periodEn = 'Night';
        }

        return "{$periodAm} {$ethHour}:{$min} ({$ethHour}:{$min} {$periodEn} Local)";
    }

    /**
     * Get Company Work Schedule settings (working hours & break non-working time).
     */
    public static function getWorkSchedule(): array
    {
        $default = [
            // Monday – Friday (Full Day)
            'morning_in'        => '08:30',
            'morning_out'       => '12:30',
            'break_start'       => '12:30',
            'break_end'         => '13:30',
            'afternoon_in'      => '13:30',
            'afternoon_out'     => '17:30',
            'total_hours'       => 8.0,

            // Saturday (Morning Session Only)
            'sat_morning_in'    => '08:30',
            'sat_morning_out'   => '12:30',
            'sat_work_mode'     => 'morning_only',
            'sat_total_hours'   => 4.0,

            'work_days'         => 'Monday – Friday (Full Day) & Saturday (Morning Only)',
            'title'             => 'Standard Construction & Office Shift',
        ];

        try {
            $stored = SystemSetting::get('attendance_work_schedule', null);
            if (is_array($stored)) {
                return array_merge($default, $stored);
            }
        } catch (\Throwable $e) {}

        return $default;
    }

    /**
     * Save/Update Company Work Schedule settings.
     */
    public static function saveWorkSchedule(array $data): array
    {
        $current = self::getWorkSchedule();
        $updated = array_merge($current, $data);

        // Recalculate total hours for Monday-Friday and Saturday
        try {
            if (!empty($updated['morning_in']) && !empty($updated['morning_out'])) {
                $mIn  = \Carbon\Carbon::createFromFormat('H:i', $updated['morning_in']);
                $mOut = \Carbon\Carbon::createFromFormat('H:i', $updated['morning_out']);
                $mHours = max(0, $mOut->diffInMinutes($mIn) / 60);

                $aIn  = \Carbon\Carbon::createFromFormat('H:i', $updated['afternoon_in']);
                $aOut = \Carbon\Carbon::createFromFormat('H:i', $updated['afternoon_out']);
                $aHours = max(0, $aOut->diffInMinutes($aIn) / 60);

                $updated['total_hours'] = round($mHours + $aHours, 1);
            }

            if (!empty($updated['sat_morning_in']) && !empty($updated['sat_morning_out'])) {
                $satIn  = \Carbon\Carbon::createFromFormat('H:i', $updated['sat_morning_in']);
                $satOut = \Carbon\Carbon::createFromFormat('H:i', $updated['sat_morning_out']);
                $updated['sat_total_hours'] = round(max(0, $satOut->diffInMinutes($satIn) / 60), 1);
            } else {
                $updated['sat_total_hours'] = 4.0;
            }
        } catch (\Throwable $e) {}

        SystemSetting::set('attendance_work_schedule', $updated, 'json', 'hr_attendance', 'Company work hours and break policy');

        return $updated;
    }

    /**
     * Convert Ethiopian date (year, month, day) to Gregorian date string (Y-m-d).
     */
    public static function toGregorian(int $ethYear, int $ethMonth, int $ethDay): string
    {
        $ep = 1723856;
        $jdn = $ep + 365 * $ethYear + intdiv($ethYear, 4) + 30 * ($ethMonth - 1) + $ethDay - 1;

        $a = $jdn + 32044;
        $b = intdiv(4 * $a + 3, 146097);
        $c = $a - intdiv(146097 * $b, 4);
        $d = intdiv(4 * $c + 3, 1461);
        $e = $c - intdiv(1461 * $d, 4);
        $m = intdiv(5 * $e + 2, 153);
        $day = $e - intdiv(153 * $m + 2, 5) + 1;
        $month = $m + 3 - 12 * intdiv($m, 10);
        $year = 100 * $b + $d - 4800 + intdiv($m, 10);

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    /**
     * Get Ethiopian payroll period details (26th of previous Ethiopian month to 25th of current Ethiopian month).
     *
     * @param int $ethYear
     * @param int $ethMonth (1 = Meskerem ... 13 = Pagume)
     * @param string|null $pagumeRule ('separate_pagume' or 'combined_with_meskerem')
     * @return array
     */
    public static function getPayrollPeriod(int $ethYear, int $ethMonth, ?string $pagumeRule = null): array
    {
        $pagumeRule = $pagumeRule ?: (SystemSetting::get('payroll_period_pagume_rule', 'separate_pagume'));
        $days = [];

        if ($ethMonth === 1) { // Meskerem
            if ($pagumeRule === 'combined_with_meskerem') {
                // 26 Nehase through Pagume to 25 Meskerem
                $prevYear = $ethYear - 1;
                for ($d = 26; $d <= 30; $d++) {
                    $greg = self::toGregorian($prevYear, 12, $d);
                    $days[] = self::buildPeriodDayItem($prevYear, 12, $d, $greg);
                }
                $pagumeMax = ($prevYear % 4 === 3) ? 6 : 5;
                for ($d = 1; $d <= $pagumeMax; $d++) {
                    $greg = self::toGregorian($prevYear, 13, $d);
                    $days[] = self::buildPeriodDayItem($prevYear, 13, $d, $greg);
                }
                for ($d = 1; $d <= 25; $d++) {
                    $greg = self::toGregorian($ethYear, 1, $d);
                    $days[] = self::buildPeriodDayItem($ethYear, 1, $d, $greg);
                }
            } else {
                // Standard default: 1 Meskerem to 25 Meskerem
                for ($d = 1; $d <= 25; $d++) {
                    $greg = self::toGregorian($ethYear, 1, $d);
                    $days[] = self::buildPeriodDayItem($ethYear, 1, $d, $greg);
                }
            }
        } elseif ($ethMonth === 13) { // Pagume
            // 26 Nehase to last day of Pagume (5 or 6)
            for ($d = 26; $d <= 30; $d++) {
                $greg = self::toGregorian($ethYear, 12, $d);
                $days[] = self::buildPeriodDayItem($ethYear, 12, $d, $greg);
            }
            $pagumeMax = ($ethYear % 4 === 3) ? 6 : 5;
            for ($d = 1; $d <= $pagumeMax; $d++) {
                $greg = self::toGregorian($ethYear, 13, $d);
                $days[] = self::buildPeriodDayItem($ethYear, 13, $d, $greg);
            }
        } else { // Months 2 to 12 (Tikimt .. Nehase)
            $prevMonth = $ethMonth - 1;
            for ($d = 26; $d <= 30; $d++) {
                $greg = self::toGregorian($ethYear, $prevMonth, $d);
                $days[] = self::buildPeriodDayItem($ethYear, $prevMonth, $d, $greg);
            }
            for ($d = 1; $d <= 25; $d++) {
                $greg = self::toGregorian($ethYear, $ethMonth, $d);
                $days[] = self::buildPeriodDayItem($ethYear, $ethMonth, $d, $greg);
            }
        }

        $startDate = $days[0]['greg_date'] ?? null;
        $endDate   = end($days)['greg_date'] ?? null;

        $mAm = self::MONTHS_AM[$ethMonth] ?? '';
        $mEn = self::MONTHS_EN[$ethMonth] ?? '';

        return [
            'eth_year'       => $ethYear,
            'eth_month'      => $ethMonth,
            'month_am'       => $mAm,
            'month_en'       => $mEn,
            'period_key'     => "{$ethYear}-{$ethMonth}",
            'label_am'       => "{$mAm} {$ethYear} (26-25)",
            'label_en'       => "{$mEn} {$ethYear} (26th-25th)",
            'full_label'     => "{$mEn} ({$mAm}) {$ethYear}",
            'start_greg'     => $startDate,
            'end_greg'       => $endDate,
            'total_days'     => count($days),
            'days'           => $days,
        ];
    }

    /**
     * Helper to construct individual day descriptor in a payroll period.
     */
    private static function buildPeriodDayItem(int $ey, int $em, int $ed, string $greg): array
    {
        $c = \Carbon\Carbon::parse($greg);
        $mAm = self::MONTHS_AM[$em] ?? '';
        $mEn = self::MONTHS_EN[$em] ?? '';

        return [
            'greg_date'    => $greg,
            'greg_day'     => $c->format('d'),
            'greg_month'   => $c->format('M'),
            'greg_label'   => $c->format('M d'),
            'day_of_week'  => $c->dayOfWeek, // 0 = Sunday, 6 = Saturday
            'day_name_en'  => $c->format('D'),
            'is_sunday'    => $c->isSunday(),
            'is_saturday'  => $c->isSaturday(),
            'eth_year'     => $ey,
            'eth_month'    => $em,
            'eth_day'      => $ed,
            'eth_label_am' => "{$mAm} {$ed}",
            'eth_label_en' => "{$mEn} {$ed}",
            'display_label'=> "{$ed} {$mEn}",
        ];
    }

    /**
     * Detect current active Ethiopian payroll period based on a given date (default today).
     */
    public static function getCurrentPayrollPeriod(?string $date = null): array
    {
        $date = $date ?: today()->toDateString();
        $et = self::toEthiopian($date);

        if (empty($et)) {
            $c = \Carbon\Carbon::parse($date);
            $et = ['year' => 2019, 'month' => 1, 'day' => 1];
        }

        $y = (int)$et['year'];
        $m = (int)$et['month'];
        $d = (int)$et['day'];

        // If today is day >= 26:
        // By standard 26th-to-25th rule, day 26 of month m belongs to period (m + 1)!
        if ($d >= 26) {
            if ($m === 12) {
                // 26 Nehase belongs to Pagume (month 13)
                $targetMonth = 13;
                $targetYear  = $y;
            } elseif ($m === 13) {
                // Pagume days belong to Pagume period (unless Pagume is over, in which case 1 Meskerem starts)
                $targetMonth = 13;
                $targetYear  = $y;
            } else {
                $targetMonth = $m + 1;
                $targetYear  = $y;
            }
        } else {
            // Days 1..25 belong to month m's period
            $targetMonth = $m;
            $targetYear  = $y;
        }

        return self::getPayrollPeriod($targetYear, $targetMonth);
    }

    /**
     * Get list of available Ethiopian payroll periods for dropdown selection.
     */
    public static function getAvailablePayrollPeriods(int $pastCount = 12, int $futureCount = 2): array
    {
        $current = self::getCurrentPayrollPeriod();
        $periods = [];

        $curYear  = $current['eth_year'];
        $curMonth = $current['eth_month'];

        // Generate past periods
        $y = $curYear;
        $m = $curMonth;
        for ($i = 0; $i < $pastCount; $i++) {
            $periods[] = self::getPayrollPeriod($y, $m);
            $m--;
            if ($m < 1) {
                $m = 13;
                $y--;
            }
        }

        // Generate future periods
        $y = $curYear;
        $m = $curMonth;
        $futurePeriods = [];
        for ($i = 0; $i < $futureCount; $i++) {
            $m++;
            if ($m > 13) {
                $m = 1;
                $y++;
            }
            $futurePeriods[] = self::getPayrollPeriod($y, $m);
        }

        $all = array_merge(array_reverse($futurePeriods), $periods);
        return $all;
    }
}

