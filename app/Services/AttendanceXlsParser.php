<?php

namespace App\Services;

/**
 * Attendance XLS/CSV Import has been completely removed per company policy.
 * All attendance must originate exclusively from biometric devices (ZKTeco) or approved site deployments.
 * @deprecated
 */
class AttendanceXlsParser
{
    public function parse(string $filePath): array
    {
        throw new \BadMethodCallException('Attendance file import has been permanently removed. Attendance must come directly from biometric machines or approved site dispatches.');
    }
}
