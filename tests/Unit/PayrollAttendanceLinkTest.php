<?php

namespace Tests\Unit;

use App\Helpers\EthiopianCalendar;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Payroll;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PayrollAttendanceLinkTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DB::statement('PRAGMA foreign_keys = OFF;');
        Schema::dropIfExists('site_deployment_requests');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('attendance');
        Schema::dropIfExists('employees');

        Schema::create('employees', function ($table) {
            $table->id();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('full_name')->nullable();
            $table->string('employee_code')->nullable();
            $table->decimal('basic_salary', 15, 2)->default(0);
            $table->string('status')->default('active');
            $table->boolean('is_dead_file')->default(false);
            $table->date('date_of_joining')->nullable();
            $table->dateTime('probation_ends_at')->nullable();
            $table->boolean('is_approved_by_gm')->default(false);
            $table->string('gm_approval_status')->default('pending');
            $table->dateTime('gm_approved_at')->nullable();
            $table->unsignedBigInteger('gm_approved_by')->nullable();
            $table->text('gm_rejection_reason')->nullable();
            $table->dateTime('gm_rejected_at')->nullable();
            $table->unsignedBigInteger('gm_rejected_by')->nullable();
            $table->timestamps();
        });

        Schema::create('attendance', function ($table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('attendance_date');
            $table->time('morning_in')->nullable();
            $table->time('morning_out')->nullable();
            $table->time('afternoon_in')->nullable();
            $table->time('afternoon_out')->nullable();
            $table->string('check_in')->nullable();
            $table->string('check_out')->nullable();
            $table->decimal('hours_worked', 5, 2)->nullable();
            $table->integer('late_minutes')->default(0);
            $table->string('status')->nullable();
            $table->string('source')->default('manual');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('leave_requests', function ($table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('approved');
            $table->timestamps();
        });

        Schema::create('site_deployment_requests', function ($table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('approved');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF;');
        Schema::dropIfExists('site_deployment_requests');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('attendance');
        Schema::dropIfExists('employees');
        parent::tearDown();
    }

    /**
     * Test that Ethiopian attendance payroll period structure and attendance day formatting match.
     */
    public function test_ethiopian_payroll_period_structure_and_day_format(): void
    {
        $period = EthiopianCalendar::getPayrollPeriod(2018, 1);

        $this->assertEquals(2018, $period['eth_year']);
        $this->assertEquals(1, $period['eth_month']);
        $this->assertEquals('2018-1', $period['period_key']);
        $this->assertEquals('Meskerem (መስከረም) 2018', $period['full_label']);
        $this->assertEquals('Meskerem 2018 (26th-25th)', $period['label_en']);
        $this->assertNotEmpty($period['days']);

        $firstDay = $period['days'][0];
        $lastDay = end($period['days']);

        $this->assertArrayHasKey('display_label', $firstDay);
        $this->assertArrayHasKey('eth_label_am', $firstDay);
        $this->assertArrayHasKey('display_label', $lastDay);
        $this->assertArrayHasKey('eth_label_am', $lastDay);

        // Verify last day is 25 Meskerem
        $this->assertEquals(25, $lastDay['eth_day']);
        $this->assertEquals('25 Meskerem', $lastDay['display_label']);
        $this->assertEquals('መስከረም 25', $lastDay['eth_label_am']);
    }

    /**
     * Test available payroll periods contains active and selectable periods.
     */
    public function test_payroll_period_dropdown_options_available(): void
    {
        $periods = EthiopianCalendar::getAvailablePayrollPeriods(6, 1);
        $this->assertNotEmpty($periods);
        $this->assertGreaterThanOrEqual(6, count($periods));

        $first = $periods[0];
        $this->assertArrayHasKey('period_key', $first);
        $this->assertArrayHasKey('full_label', $first);
        $this->assertArrayHasKey('start_greg', $first);
        $this->assertArrayHasKey('end_greg', $first);
    }

    /**
     * Test calculateUnexcusedAbsences formats absent dates in Ethiopian attendance day format.
     */
    public function test_payroll_unexcused_absences_formats_in_attendance_day_format(): void
    {
        $employee = Employee::create([
            'first_name'      => 'Abebe',
            'last_name'       => 'Kebede',
            'full_name'       => 'Abebe Kebede',
            'employee_code'   => 'EMP-001',
            'basic_salary'    => 30000.00,
            'status'          => 'active',
            'date_of_joining' => '2024-01-01',
        ]);

        // Evaluate a 5-day window Monday to Friday (e.g. 2025-09-15 to 2025-09-19 = Meskerem 5 to 9, 2018 EC)
        $startDate = '2025-09-15';
        $endDate   = '2025-09-19';

        // Punch for Monday only
        Attendance::create([
            'employee_id'     => $employee->id,
            'attendance_date' => '2025-09-15',
            'morning_in'      => '08:20:00',
            'afternoon_out'   => '17:30:00',
            'status'          => 'present',
        ]);

        // Days 2025-09-16, 17, 18, 19 have no punches -> 4 unexcused absences
        $absenceInfo = Payroll::calculateUnexcusedAbsences($employee->id, 1, 2018, $endDate, $startDate);

        $this->assertEquals(4.0, $absenceInfo['days']);
        $this->assertCount(4, $absenceInfo['dates']);
        $this->assertCount(4, $absenceInfo['detailed_dates']);

        // Check that absent dates format uses Ethiopian attendance day format (e.g. contains 'Meskerem')
        $firstAbsent = $absenceInfo['dates'][0];
        $this->assertStringContainsString('Meskerem', $firstAbsent);
        $this->assertStringContainsString('Absent', $firstAbsent);

        $detailedItem = $absenceInfo['detailed_dates'][0];
        $this->assertEquals('2025-09-16', $detailedItem['date']);
        $this->assertStringContainsString('Meskerem', $detailedItem['eth_day']);
    }

    /**
     * Test that the 3 Late = 1 Absent penalty rule is calculated and labeled correctly.
     */
    public function test_payroll_unexcused_absences_calculates_3_to_1_late_penalty(): void
    {
        $employee = Employee::create([
            'first_name'      => 'Chala',
            'last_name'       => 'Tadesse',
            'full_name'       => 'Chala Tadesse',
            'employee_code'   => 'EMP-002',
            'basic_salary'    => 15000.00,
            'status'          => 'active',
            'date_of_joining' => '2024-01-01',
        ]);

        $startDate = '2025-09-15'; // Monday
        $endDate   = '2025-09-17'; // Wednesday

        // 3 consecutive late arrivals (after 08:40 AM cutoff)
        for ($i = 15; $i <= 17; $i++) {
            Attendance::create([
                'employee_id'     => $employee->id,
                'attendance_date' => "2025-09-{$i}",
                'morning_in'      => '08:55:00',
                'afternoon_out'   => '17:30:00',
                'late_minutes'    => 15,
                'status'          => 'late',
            ]);
        }

        $absenceInfo = Payroll::calculateUnexcusedAbsences($employee->id, 1, 2018, $endDate, $startDate);

        // 0 base absent days + 1 penalty day from (3 lates / 3) = 1.0 day
        $this->assertEquals(3, $absenceInfo['late_days']);
        $this->assertEquals(1, $absenceInfo['late_penalty_days']);
        $this->assertEquals(0.0, $absenceInfo['base_days']);
        $this->assertEquals(1.0, $absenceInfo['days']);

        $lastDate = end($absenceInfo['dates']);
        $this->assertStringContainsString('Late Penalty', $lastDate);
        $this->assertStringContainsString('3 Late = 1 Absent rule', $lastDate);
    }

    /**
     * Test that approved on-site deployments ('S') are never deducted from payroll.
     */
    public function test_payroll_unexcused_absences_ignores_site_deployments(): void
    {
        $employee = Employee::create([
            'first_name'      => 'Dawit',
            'last_name'       => 'Haile',
            'full_name'       => 'Dawit Haile',
            'employee_code'   => 'EMP-003',
            'basic_salary'    => 20000.00,
            'status'          => 'active',
            'date_of_joining' => '2024-01-01',
        ]);

        $startDate = '2025-09-15';
        $endDate   = '2025-09-16';

        // Employee was deployed on site (status 'S' or SiteDeploymentRequest)
        Attendance::create([
            'employee_id'     => $employee->id,
            'attendance_date' => '2025-09-15',
            'status'          => 'S',
            'notes'           => 'On-Site Deployment at Bole Project',
        ]);

        Attendance::create([
            'employee_id'     => $employee->id,
            'attendance_date' => '2025-09-16',
            'status'          => 'site',
            'notes'           => 'On-Site Construction Duty',
        ]);

        $absenceInfo = Payroll::calculateUnexcusedAbsences($employee->id, 1, 2018, $endDate, $startDate);

        $this->assertEquals(0.0, $absenceInfo['days'], 'On-site duty should NEVER be deducted from payroll');
        $this->assertEmpty($absenceInfo['dates']);
    }
}
