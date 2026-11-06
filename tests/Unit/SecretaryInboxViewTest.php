<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\Letter;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SecretaryInboxViewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF;');
        }

        Schema::dropIfExists('letter_sequences');
        Schema::create('letter_sequences', function ($table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->string('prefix', 20)->default('LTR');
            $table->unsignedInteger('current_number')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Test that the secretary inbox view renders without throwing:
     * "Attempt to read property 'name' on null"
     * when a letter has no addressedTo and no employee assigned.
     */
    public function test_secretary_inbox_renders_without_addressed_to_or_employee()
    {
        $secretary = new class([
            'id'    => 1,
            'name'  => 'Secretary User',
            'email' => 'secretary@wechecha.com',
        ]) extends User {
            public function hasRole($role, $guard = null): bool
            {
                return $role === 'secretary';
            }
            public function hasAnyRole(...$roles): bool
            {
                return true;
            }
        };

        Auth::login($secretary);

        // Letter with NO addressedTo and NO employee (newly submitted to secretary)
        $unassignedLetter = new Letter([
            'id'                   => 10,
            'subject'              => 'Request for Material Requisition Clearance',
            'specification'        => 'Please review and register this correspondence.',
            'status'               => Letter::STATUS_SENT,
            'letter_number'        => null,
            'category'             => null,
            'addressed_to_user_id' => null,
            'employee_id'          => null,
            'created_by'           => 2,
            'sender'               => 'Field Engineer',
            'confidentiality'      => 'normal',
            'priority'             => 'normal',
        ]);
        $unassignedLetter->id = 10;
        $unassignedLetter->setRelation('creator', new User(['name' => 'Field Engineer', 'email' => 'fe@wechecha.com']));
        $unassignedLetter->setRelation('addressedTo', null);
        $unassignedLetter->setRelation('employee', null);
        $unassignedLetter->setRelation('attachments', collect());
        $unassignedLetter->setRelation('registeredBy', null);
        $unassignedLetter->setRelation('salaryHistory', null);
        $unassignedLetter->setRelation('closer', null);

        // Letter with ONLY employee (e.g. HR salary increment)
        $employeeLetter = new Letter([
            'id'                   => 20,
            'subject'              => 'Salary Increment Letter',
            'specification'        => 'Salary adjustment approved.',
            'status'               => Letter::STATUS_SENT,
            'letter_number'        => null,
            'category'             => 'Salary Update / Increment Letter',
            'addressed_to_user_id' => null,
            'employee_id'          => 5,
            'created_by'           => 3,
            'sender'               => 'HR Officer',
            'confidentiality'      => 'normal',
            'priority'             => 'normal',
        ]);
        $employeeLetter->id = 20;
        $mockEmp = new Employee([
            'full_name'     => 'Abebe Bikila',
            'employee_code' => 'EMP-005',
            'department'    => 'Civil Engineering',
        ]);
        $mockEmp->id = 5;
        $employeeLetter->setRelation('creator', new User(['name' => 'HR Officer', 'email' => 'hr@wechecha.com']));
        $employeeLetter->setRelation('addressedTo', null);
        $employeeLetter->setRelation('employee', $mockEmp);
        $employeeLetter->setRelation('attachments', collect());
        $employeeLetter->setRelation('registeredBy', null);
        $employeeLetter->setRelation('salaryHistory', null);
        $employeeLetter->setRelation('closer', null);

        // Letter with addressedTo user assigned
        $assignedUser = new User(['name' => 'Project Manager', 'email' => 'pm@wechecha.com']);
        $assignedUser->id = 8;
        $assignedLetter = new Letter([
            'id'                   => 30,
            'subject'              => 'Site Inspection Report',
            'specification'        => 'Inspection results attached.',
            'status'               => Letter::STATUS_REGISTERED,
            'letter_number'        => 'LTR-2026-0001',
            'category'             => 'General',
            'addressed_to_user_id' => 8,
            'employee_id'          => null,
            'created_by'           => 2,
            'sender'               => 'Field Engineer',
            'confidentiality'      => 'normal',
            'priority'             => 'normal',
        ]);
        $assignedLetter->id = 30;
        $assignedLetter->setRelation('creator', new User(['name' => 'Field Engineer', 'email' => 'fe@wechecha.com']));
        $assignedLetter->setRelation('addressedTo', $assignedUser);
        $assignedLetter->setRelation('employee', null);
        $assignedLetter->setRelation('attachments', collect());
        $assignedLetter->setRelation('registeredBy', $secretary);
        $assignedLetter->setRelation('salaryHistory', null);
        $assignedLetter->setRelation('closer', null);

        $lettersList = collect([$unassignedLetter, $employeeLetter, $assignedLetter]);
        $paginator = new LengthAwarePaginator($lettersList, 3, 15, 1);

        $stats = [
            'sent'       => 2,
            'registered' => 1,
            'rejected'   => 0,
            'total'      => 3,
        ];
        $users = collect([$assignedUser]);
        $employees = collect([$mockEmp]);
        $categories = Letter::CATEGORIES;
        $nextSuggestedNumber = 'LTR-2026-0002';

        $renderedHtml = view('letters.secretary.inbox', [
            'letters'             => $paginator,
            'stats'               => $stats,
            'users'               => $users,
            'employees'           => $employees,
            'categories'          => $categories,
            'nextSuggestedNumber' => $nextSuggestedNumber,
        ])->render();

        $this->assertNotEmpty($renderedHtml);
        $this->assertStringContainsString('Secretary Inbox', $renderedHtml);
        $this->assertStringContainsString('Not assigned yet', $renderedHtml);
        $this->assertStringContainsString('Abebe Bikila', $renderedHtml);
        $this->assertStringContainsString('Project Manager', $renderedHtml);
    }
}
