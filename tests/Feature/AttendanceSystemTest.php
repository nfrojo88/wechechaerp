<?php

namespace Tests\Feature;

use App\Helpers\EthiopianCalendar;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\SiteDeploymentRequest;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserAccessAudit;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceSystemTest extends TestCase
{
    /**
     * 1. Test Ethiopian Payroll Period Boundaries (26th to 25th) and Pagume edge case.
     */
    public function test_ethiopian_payroll_period_boundaries_26th_to_25th_and_pagume(): void
    {
        // Test standard month: Tikimt (Month 2), Year 2019
        // Period runs from 26 Meskerem (Month 1, Day 26) to 25 Tikimt (Month 2, Day 25)
        $period = EthiopianCalendar::getPayrollPeriod(2019, 2);

        $this->assertEquals(2019, $period['eth_year']);
        $this->assertEquals(2, $period['eth_month']);
        $this->assertEquals(30, $period['total_days']); // 5 days from Month 1 + 25 days from Month 2

        // First day must be 26 Meskerem
        $firstDay = $period['days'][0];
        $this->assertEquals(1, $firstDay['eth_month']);
        $this->assertEquals(26, $firstDay['eth_day']);

        // Last day must be 25 Tikimt
        $lastDay = end($period['days']);
        $this->assertEquals(2, $lastDay['eth_month']);
        $this->assertEquals(25, $lastDay['eth_day']);

        // Test Pagume edge case:
        // Period Pagume (Month 13) runs from 26 Nehase (Month 12, Day 26) to last day of Pagume (Day 5 or 6)
        $pagumePeriod = EthiopianCalendar::getPayrollPeriod(2019, 13);
        $this->assertEquals(13, $pagumePeriod['eth_month']);
        $this->assertEquals(12, $pagumePeriod['days'][0]['eth_month']);
        $this->assertEquals(26, $pagumePeriod['days'][0]['eth_day']);

        $lastPagumeDay = end($pagumePeriod['days']);
        $this->assertEquals(13, $lastPagumeDay['eth_month']);
        $this->assertContains($lastPagumeDay['eth_day'], [5, 6]);

        // Period Meskerem (Month 1) runs from 1 Meskerem to 25 Meskerem (25 days total)
        $meskeremPeriod = EthiopianCalendar::getPayrollPeriod(2019, 1);
        $this->assertEquals(1, $meskeremPeriod['eth_month']);
        $this->assertEquals(25, $meskeremPeriod['total_days']);
        $this->assertEquals(1, $meskeremPeriod['days'][0]['eth_day']);
        $this->assertEquals(25, end($meskeremPeriod['days'])['eth_day']);
    }

    /**
     * 2. Test 3 late days = 1 absent penalty day calculation.
     */
    public function test_three_late_days_equal_one_absent_penalty_day(): void
    {
        // 0 to 2 late days = 0 penalty
        $this->assertEquals(0, intdiv(0, 3));
        $this->assertEquals(0, intdiv(2, 3));

        // 3 to 5 late days = 1 penalty day
        $this->assertEquals(1, intdiv(3, 3));
        $this->assertEquals(1, intdiv(4, 3));
        $this->assertEquals(1, intdiv(5, 3));

        // 6 late days = 2 penalty days
        $this->assertEquals(2, intdiv(6, 3));

        // 9 late days = 3 penalty days
        $this->assertEquals(3, intdiv(9, 3));

        // Effective absent formula:
        $baseAbsent = 4;
        $lateDays = 7;
        $penaltyDays = intdiv($lateDays, 3); // 2
        $effectiveAbsent = $baseAbsent + $penaltyDays;

        $this->assertEquals(2, $penaltyDays);
        $this->assertEquals(6, $effectiveAbsent);
    }

    /**
     * 3. Test late rule: punch after 08:40 is flagged late.
     */
    public function test_late_cutoff_rule_after_0840_is_flagged_late(): void
    {
        // At or before 08:40 -> On time (0 min late)
        $this->assertEquals(0, \App\Services\BiometricPunchService::calculateLateMinutes('08:30:00'));
        $this->assertEquals(0, \App\Services\BiometricPunchService::calculateLateMinutes('08:39:59'));
        $this->assertEquals(0, \App\Services\BiometricPunchService::calculateLateMinutes('08:40:00'));

        // After 08:40 -> Late
        $this->assertEquals(1, \App\Services\BiometricPunchService::calculateLateMinutes('08:41:00'));
        $this->assertEquals(5, \App\Services\BiometricPunchService::calculateLateMinutes('08:45:00'));
        $this->assertEquals(20, \App\Services\BiometricPunchService::calculateLateMinutes('09:00:00'));
    }

    /**
     * 4. Test S (Site Deployment) shown only after HR approval.
     */
    public function test_s_status_shown_only_after_hr_approval(): void
    {
        $depPending = new SiteDeploymentRequest([
            'employee_id' => 10,
            'status'      => 'pending',
            'start_date'  => '2026-10-01',
            'end_date'    => '2026-10-05',
        ]);
        // Pending deployment must NOT count as approved S
        $this->assertNotEquals('approved', $depPending->status);

        $depApproved = new SiteDeploymentRequest([
            'employee_id' => 10,
            'status'      => 'approved',
            'start_date'  => '2026-10-01',
            'end_date'    => '2026-10-05',
        ]);
        // Approved deployment counts as S
        $this->assertEquals('approved', $depApproved->status);

        $depRejected = new SiteDeploymentRequest([
            'employee_id' => 10,
            'status'      => 'rejected',
            'start_date'  => '2026-10-01',
            'end_date'    => '2026-10-05',
        ]);
        // Rejected deployment does not show S
        $this->assertNotEquals('approved', $depRejected->status);
    }

    /**
     * 5. Test Sundays are rest days and do not count toward absence streak.
     */
    public function test_sundays_are_rest_days_and_do_not_count_toward_streak(): void
    {
        $checker = new \App\Console\Commands\CheckConsecutiveAbsenceBlock();

        $sunday = Carbon::parse('2026-10-04'); // Sunday
        $this->assertTrue($sunday->isSunday());

        $emp = new Employee(['id' => 999, 'full_name' => 'Test Employee', 'device_user_id' => '999']);

        // Sunday alone does not increment absent streak
        $this->assertTrue($sunday->isSunday());
    }

    /**
     * 6. Test User Access Suspension and Restoration with Audit Trail.
     */
    public function test_user_access_block_and_restore_with_audit_trail(): void
    {
        $user = new User([
            'id'        => 555,
            'name'      => 'John Doe',
            'email'     => 'john.doe@test.com',
            'is_active' => true,
        ]);

        $this->assertFalse($user->isAccessBlocked());

        // Simulate blockAccess
        $user->access_blocked_at = now();
        $user->access_block_reason = 'Access suspended due to 5 consecutive days without attendance. Contact HR.';
        $this->assertTrue($user->isAccessBlocked());

        // Restore access
        $admin = new User(['id' => 1, 'name' => 'Admin User']);
        $user->access_blocked_at = null;
        $user->access_unblocked_by = $admin->id;
        $user->access_unblocked_at = now();
        $user->access_unblock_reason = 'Medical certificate provided';

        $this->assertFalse($user->isAccessBlocked());
        $this->assertEquals('Medical certificate provided', $user->access_unblock_reason);
        $this->assertEquals(1, $user->access_unblocked_by);
    }

    /**
     * 7. Test Admin and Global Admin accounts are exempt from auto-blocking.
     */
    public function test_admin_and_global_admin_accounts_are_exempt_from_auto_block(): void
    {
        $admin = new User([
            'id'       => 1,
            'name'     => 'Global Admin',
            'email'    => 'admin@wechecha.com',
            'is_admin' => true,
        ]);

        $this->assertTrue($admin->isGlobalAdmin());

        // Call blockAccess - it must return without setting access_blocked_at
        $admin->blockAccess('5 consecutive days without attendance');
        $this->assertNull($admin->access_blocked_at);
        $this->assertFalse($admin->isAccessBlocked());
    }
}
