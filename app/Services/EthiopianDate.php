<?php

namespace App\Services;

use Carbon\Carbon;
use DateTimeInterface;

class EthiopianDate
{
    /**
     * Amharic Month Names (1 = Meskerem ... 13 = Pagume)
     */
    public const MONTHS_AM = [
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

    /**
     * English Month Names (1 = Meskerem ... 13 = Pagume)
     */
    public const MONTHS_EN = [
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
     * Check if an Ethiopian year is a leap year.
     * Ethiopian rule: Pagume has 6 days if (year + 1) % 4 == 0, otherwise 5.
     */
    public static function isLeapYear(int $year): bool
    {
        return (($year + 1) % 4) === 0;
    }

    /**
     * Number of days in an Ethiopian month.
     * Months 1-12 always have 30 days.
     * Month 13 (Pagume) has 5 days, or 6 days if (year + 1) % 4 == 0.
     */
    public static function daysInMonth(int $year, int $month): int
    {
        if ($month === 13) {
            return self::isLeapYear($year) ? 6 : 5;
        }

        return 30;
    }

    /**
     * Convert Gregorian date to Ethiopian calendar details.
     *
     * @param Carbon|DateTimeInterface|string|null $date
     * @return array{
     *     year: int,
     *     month: int,
     *     day: int,
     *     month_name: string,
     *     month_am: string,
     *     month_en: string,
     *     formatted: string,
     *     date_ec: string,
     *     formatted_en: string,
     *     formatted_am: string
     * }
     */
    public static function toEthiopian($date = null): array
    {
        if ($date === null) {
            $date = Carbon::now();
        }

        if ($date instanceof DateTimeInterface) {
            $year  = (int)$date->format('Y');
            $month = (int)$date->format('m');
            $day   = (int)$date->format('d');
        } else {
            $str = trim((string)$date);
            if (empty($str)) {
                $date = Carbon::now();
                $year  = (int)$date->format('Y');
                $month = (int)$date->format('m');
                $day   = (int)$date->format('d');
            } else {
                $c = Carbon::parse($str);
                $year  = (int)$c->format('Y');
                $month = (int)$c->format('m');
                $day   = (int)$c->format('d');
            }
        }

        // Gregorian to Julian Day Number
        $a = intdiv(14 - $month, 12);
        $y = $year + 4800 - $a;
        $m = $month + 12 * $a - 3;
        $jdn = $day + intdiv(153 * $m + 2, 5) + 365 * $y + intdiv($y, 4) - intdiv($y, 100) + intdiv($y, 400) - 32045;

        // Julian Day Number to Ethiopian Calendar
        $ethiopianEpoch = 1723856;
        $r = ($jdn - $ethiopianEpoch) % 1461;
        $n = ($r % 365) + 365 * intdiv($r, 1460);

        $ethYear  = 4 * intdiv($jdn - $ethiopianEpoch, 1461) + intdiv($r, 365) - intdiv($r, 1460);
        $ethMonth = intdiv($n, 30) + 1;
        $ethDay   = ($n % 30) + 1;

        $mEn = self::MONTHS_EN[$ethMonth] ?? 'Unknown';
        $mAm = self::MONTHS_AM[$ethMonth] ?? 'የማይታወቅ';

        $dayPadded   = sprintf('%02d', $ethDay);
        $monthPadded = sprintf('%02d', $ethMonth);
        $dateEc      = "{$dayPadded}/{$monthPadded}/{$ethYear}";

        return [
            'year'         => $ethYear,
            'month'        => $ethMonth,
            'day'          => $ethDay,
            'month_name'   => $mEn,
            'month_en'     => $mEn,
            'month_am'     => $mAm,
            'formatted'    => "{$dayPadded}/{$monthPadded}/{$ethYear} EC",
            'date_ec'      => $dateEc,
            'formatted_en' => "{$mEn} {$ethDay}, {$ethYear} E.C.",
            'formatted_am' => "{$mAm} {$ethDay}, {$ethYear} ዓ.ም.",
        ];
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
     * Convert Ethiopian date to Carbon Gregorian instance.
     */
    public static function toGregorianCarbon(int $ethYear, int $ethMonth, int $ethDay): Carbon
    {
        return Carbon::parse(self::toGregorian($ethYear, $ethMonth, $ethDay));
    }

    /**
     * Get Gregorian start and end date range for one complete Ethiopian month.
     * The period is strictly bounded: start of day 00:00:00 on day 1 to end of day 23:59:59 on the last day.
     *
     * @return array{
     *     start: Carbon,
     *     end: Carbon,
     *     start_date: string,
     *     end_date: string,
     *     year: int,
     *     month: int,
     *     days: int,
     *     month_en: string,
     *     month_am: string
     * }
     */
    public static function monthRange(int $ethYear, int $ethMonth): array
    {
        $daysInMonth = self::daysInMonth($ethYear, $ethMonth);

        $startDateStr = self::toGregorian($ethYear, $ethMonth, 1);
        $endDateStr   = self::toGregorian($ethYear, $ethMonth, $daysInMonth);

        $startCarbon = Carbon::parse($startDateStr)->startOfDay();
        $endCarbon   = Carbon::parse($endDateStr)->endOfDay();

        return [
            'start'      => $startCarbon,
            'end'        => $endCarbon,
            'start_date' => $startDateStr,
            'end_date'   => $endDateStr,
            'year'       => $ethYear,
            'month'      => $ethMonth,
            'days'       => $daysInMonth,
            'month_en'   => self::MONTHS_EN[$ethMonth] ?? 'Unknown',
            'month_am'   => self::MONTHS_AM[$ethMonth] ?? 'የማይታወቅ',
        ];
    }

    /**
     * Format a Gregorian date into Ethiopian Calendar string format (default: DD/MM/YYYY EC).
     */
    public static function formatDateEc($date, string $format = 'd/m/Y'): string
    {
        if (!$date) {
            return '';
        }

        $et = self::toEthiopian($date);
        $d = sprintf('%02d', $et['day']);
        $m = sprintf('%02d', $et['month']);
        $y = $et['year'];

        if ($format === 'd/m/Y EC' || $format === 'DD/MM/YYYY EC') {
            return "{$d}/{$m}/{$y} EC";
        }

        if ($format === 'd-m-Y') {
            return "{$d}-{$m}-{$y}";
        }

        if ($format === 'Y-m-d') {
            return "{$y}-{$m}-{$d}";
        }

        if ($format === 'words') {
            return $et['formatted_en'];
        }

        return "{$d}/{$m}/{$y}";
    }

    /**
     * Parse an Ethiopian date string (DD/MM/YYYY, DD-MM-YYYY, or YYYY-MM-DD EC)
     * and convert it into a Gregorian date string (YYYY-MM-DD) for database storage.
     */
    public static function parseEcToGregorian(?string $dateEc): ?string
    {
        if (empty($dateEc)) {
            return null;
        }

        $clean = trim($dateEc);
        $clean = preg_replace('/\s*(EC|E\.C\.|ዓ\.ም\.)\s*/i', '', $clean);

        // DD/MM/YYYY or DD-MM-YYYY
        if (preg_match('/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4})$/', $clean, $matches)) {
            $day   = (int)$matches[1];
            $month = (int)$matches[2];
            $year  = (int)$matches[3];

            if ($month >= 1 && $month <= 13) {
                $maxDays = self::daysInMonth($year, $month);
                $day = min($day, $maxDays);
                return self::toGregorian($year, $month, $day);
            }
        }

        // YYYY-MM-DD
        if (preg_match('/^(\d{4})[\/\-\.](\d{1,2})[\/\-\.](\d{1,2})$/', $clean, $matches)) {
            $year  = (int)$matches[1];
            $month = (int)$matches[2];
            $day   = (int)$matches[3];

            if ($month >= 1 && $month <= 13) {
                $maxDays = self::daysInMonth($year, $month);
                $day = min($day, $maxDays);
                return self::toGregorian($year, $month, $day);
            }
        }

        return null;
    }

    /**
     * Current Ethiopian year.
     */
    public static function currentYear(): int
    {
        return (int)self::toEthiopian(now())['year'];
    }

    /**
     * Current Ethiopian month (1..13).
     */
    public static function currentMonth(): int
    {
        return (int)self::toEthiopian(now())['month'];
    }

    /**
     * Get list of all 13 Ethiopian months for dropdown select options.
     */
    public static function getMonths(): array
    {
        $list = [];
        for ($m = 1; $m <= 13; $m++) {
            $list[$m] = [
                'id'       => $m,
                'name_en'  => self::MONTHS_EN[$m],
                'name_am'  => self::MONTHS_AM[$m],
                'label'    => "{$m}. " . self::MONTHS_EN[$m] . " (" . self::MONTHS_AM[$m] . ")",
            ];
        }
        return $list;
    }

    /**
     * Get list of selectable Ethiopian years (default: current year - 3 to current year + 2).
     */
    public static function getYears(int $span = 4): array
    {
        $cur = self::currentYear();
        $years = [];
        for ($y = $cur - $span; $y <= $cur + 2; $y++) {
            $years[] = $y;
        }
        return $years;
    }

    /**
     * Calculate Signed Net VAT:
     * Net = Output VAT - Input VAT (factoring in opening credit).
     *
     * Rules:
     * - Income (Output) > Purchase (Creditable Input): positive (+), red, "VAT payable to ERCA"
     * - Purchase (Creditable Input) > Income (Output): negative (-), green, "VAT credit (carry forward)"
     * - Equal: 0.00, neutral
     *
     * @param float $inputVat Creditable 15% VAT from paid expenses/OCR
     * @param float $outputVat 15% VAT from Company Income receipts
     * @param float $openingCredit Positive credit carried forward from previous month
     * @return array{
     *     output_vat: float,
     *     input_vat: float,
     *     opening_credit: float,
     *     total_creditable_input: float,
     *     net: float,
     *     signed_net_formatted: string,
     *     status: string,
     *     label: string,
     *     badge_class: string,
     *     text_class: string,
     *     is_payable: bool,
     *     is_credit: bool,
     *     credit_carry_forward: float,
     *     amount_payable: float
     * }
     */
    public static function calculateNetVat(float $inputVat, float $outputVat, float $openingCredit = 0.0): array
    {
        $inputVat      = round(max(0, $inputVat), 2);
        $outputVat     = round(max(0, $outputVat), 2);
        $openingCredit = round(max(0, $openingCredit), 2);

        $totalCreditable = round($inputVat + $openingCredit, 2);
        $net = round($outputVat - $totalCreditable, 2);

        // Determine status and presentation
        if ($net > 0.004) {
            // Output > Input: positive, red, "VAT payable to ERCA"
            $status = 'payable';
            $label = 'VAT payable to ERCA';
            $badgeClass = 'bg-danger text-white';
            $textClass = 'text-danger';
            $signedNetFormatted = '+' . number_format($net, 2, '.', ',');
            $isPayable = true;
            $isCredit = false;
            $creditCarryForward = 0.0;
            $amountPayable = round($net, 2);
        } elseif ($net < -0.004) {
            // Input > Output: negative, green, "VAT credit (carry forward)"
            $status = 'credit';
            $label = 'VAT credit (carry forward)';
            $badgeClass = 'bg-success text-white';
            $textClass = 'text-success';
            $signedNetFormatted = '-' . number_format(abs($net), 2, '.', ',');
            $isPayable = false;
            $isCredit = true;
            $creditCarryForward = round(abs($net), 2);
            $amountPayable = 0.0;
        } else {
            // Equal: 0.00, neutral
            $status = 'neutral';
            $label = 'Zero balance (settled)';
            $badgeClass = 'bg-secondary text-white';
            $textClass = 'text-secondary';
            $signedNetFormatted = '0.00';
            $isPayable = false;
            $isCredit = false;
            $creditCarryForward = 0.0;
            $amountPayable = 0.0;
        }

        return [
            'output_vat'             => $outputVat,
            'input_vat'              => $inputVat,
            'opening_credit'         => $openingCredit,
            'total_creditable_input' => $totalCreditable,
            'net'                    => $net,
            'signed_net_formatted'   => $signedNetFormatted,
            'status'                 => $status,
            'label'                  => $label,
            'badge_class'            => $badgeClass,
            'text_class'             => $textClass,
            'is_payable'             => $isPayable,
            'is_credit'              => $isCredit,
            'credit_carry_forward'   => $creditCarryForward,
            'amount_payable'         => $amountPayable,
            'payable_amount'         => $amountPayable,
        ];
    }
}
