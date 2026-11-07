<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Employee;
use App\Models\Attendance;
use App\Http\Controllers\AttendanceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;

class AttendancePdfExportTest extends TestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        DB::statement('PRAGMA foreign_keys = OFF;');

        Schema::dropIfExists('site_deployment_requests');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('attendance');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('users');
        Schema::dropIfExists('projects');

        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_admin')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('projects', function ($table) {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('employees', function ($table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('full_name')->nullable();
            $table->string('employee_code')->nullable();
            $table->string('role_title')->nullable();
            $table->string('department')->nullable();
            $table->string('staff_type')->default('office');
            $table->string('employment_type')->nullable();
            $table->boolean('is_project_based')->default(false);
            $table->decimal('basic_salary', 15, 2)->default(0);
            $table->string('status')->default('active');
            $table->boolean('is_dead_file')->default(false);
            $table->boolean('is_guarantee_overdue')->default(false);
            $table->boolean('is_test_period_expired')->default(false);
            $table->date('date_of_joining')->nullable();
            $table->dateTime('probation_ends_at')->nullable();
            $table->boolean('is_approved_by_gm')->default(true);
            $table->string('gm_approval_status')->default('approved');
            $table->dateTime('gm_approved_at')->nullable();
            $table->unsignedBigInteger('gm_approved_by')->nullable();
            $table->text('gm_rejection_reason')->nullable();
            $table->dateTime('gm_rejected_at')->nullable();
            $table->unsignedBigInteger('gm_rejected_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('attendance', function ($table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('attendance_date');
            $table->time('morning_in')->nullable();
            $table->time('morning_out')->nullable();
            $table->time('afternoon_in')->nullable();
            $table->time('afternoon_out')->nullable();
            $table->string('status')->default('present');
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('site_deployment_requests', function ($table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->string('status')->default('pending');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();
        });

        Schema::create('leave_requests', function ($table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->string('status')->default('pending');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();
        });

        $this->user = User::create([
            'name'      => 'HR Admin',
            'email'     => 'hr@wechecha.et',
            'is_active' => true,
            'is_admin'  => true,
        ]);
        Auth::login($this->user);
    }

    public function test_attendance_export_pdf_renders_with_ethiopian_period_key_without_errors(): void
    {
        // Create an employee
        Employee::create([
            'first_name'    => 'Abebe',
            'last_name'     => 'Bikila',
            'full_name'     => 'Abebe Bikila',
            'employee_code' => 'EMP-001',
            'staff_type'    => 'office',
            'status'        => 'active',
            'is_dead_file'  => false,
            'is_approved_by_gm' => true,
        ]);

        $controller = app(AttendanceController::class);

        // Simulate request: period=2019-2&staff_type=office
        $request = Request::create('/attendance/export-pdf', 'GET', [
            'period'     => '2019-2',
            'staff_type' => 'office',
        ]);

        $viewResponse = $controller->exportPdf($request);
        $html = $viewResponse->render();

        $this->assertNotEmpty($html);
        $this->assertStringContainsString('Abebe Bikila', $html);
        $this->assertStringContainsString('2019-2', $html);
    }

    public function test_attendance_export_pdf_renders_with_custom_date_range(): void
    {
        $controller = app(AttendanceController::class);

        $request = Request::create('/attendance/export-pdf', 'GET', [
            'start_date' => '2026-10-01',
            'end_date'   => '2026-10-31',
            'staff_type' => 'office',
        ]);

        $viewResponse = $controller->exportPdf($request);
        $html = $viewResponse->render();

        $this->assertNotEmpty($html);
    }
}
