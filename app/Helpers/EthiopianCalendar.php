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
}
