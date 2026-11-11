<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Employee;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

class EmployeeProfileSyncUnitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);

        if (Schema::hasTable('projects') && !Schema::hasColumn('projects', 'deleted_at')) {
            Schema::table('projects', function ($t) {
                $t->softDeletes();
            });
        }

        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('users');

        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('roles', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name')->default('web');
            $table->timestamps();
        });

        Schema::create('model_has_roles', function ($table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['role_id', 'model_id', 'model_type']);
        });

        Schema::create('employees', function ($table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->string('employee_code')->unique();
            $table->string('full_name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('role_title')->nullable();
            $table->string('department')->nullable();
            $table->string('employment_type')->default('permanent');
            $table->string('contract_type')->nullable();
            $table->date('contract_end_date')->nullable();
            $table->string('contract_duration_type')->default('fixed_date');
            $table->boolean('is_project_based')->default(false);
            $table->date('date_of_joining')->nullable();
            $table->decimal('basic_salary', 15, 2)->default(0);
            $table->decimal('transport_allowance', 15, 2)->default(0);
            $table->decimal('house_allowance', 15, 2)->default(0);
            $table->decimal('position_allowance', 15, 2)->default(0);
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->boolean('is_dead_file')->default(false);
            $table->boolean('is_approved_by_gm')->default(true);
            $table->string('gm_approval_status')->default('approved');
            $table->dateTime('gm_approved_at')->nullable();
            $table->unsignedBigInteger('gm_approved_by')->nullable();
            $table->text('gm_rejection_reason')->nullable();
            $table->dateTime('gm_rejected_at')->nullable();
            $table->unsignedBigInteger('gm_rejected_by')->nullable();
            $table->boolean('probation_completed')->default(false);
            $table->date('probation_ends_at')->nullable();
            $table->string('device_user_id')->nullable();
            $table->string('tin_number')->nullable();
            $table->string('national_id_number')->nullable();
            $table->string('national_id_card')->nullable();
            $table->string('guarantee_letter')->nullable();
            $table->string('guarantee_letter_2')->nullable();
            $table->string('guarantor_name')->nullable();
            $table->string('guarantor_id_number')->nullable();
            $table->string('guarantor_phone')->nullable();
            $table->string('guarantor_id_card')->nullable();
            $table->string('guarantor_2_name')->nullable();
            $table->string('guarantor_2_id_number')->nullable();
            $table->string('guarantor_2_phone')->nullable();
            $table->string('guarantor_2_id_card')->nullable();
            $table->string('profile_picture')->nullable();
            $table->string('registration_letter')->nullable();
            $table->text('registration_letters')->nullable();
            $table->string('asset_handover_document')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function test_employee_update_syncs_to_linked_user_name_and_email_and_roles()
    {
        $testRole = Role::create(['name' => 'hr_officer', 'guard_name' => 'web']);

        $user = User::create([
            'name'      => 'Original Name',
            'email'     => 'orig.emp.' . uniqid() . '@example.com',
            'password'  => bcrypt('password'),
            'is_active' => true,
        ]);

        $employee = Employee::create([
            'user_id'         => $user->id,
            'employee_code'   => 'EMP' . rand(10000, 99999),
            'full_name'       => 'Original Name',
            'email'           => $user->email,
            'phone'           => '0911000000',
            'employment_type' => 'permanent',
            'date_of_joining' => '2025-01-01',
            'basic_salary'    => 15000.00,
            'status'          => 'active',
        ]);

        $newEmail = 'updated.emp.' . uniqid() . '@example.com';
        $request = Request::create(route('employees.update', $employee), 'PUT', [
            'employee_code'        => $employee->employee_code,
            'full_name'            => 'Updated Full Name',
            'phone'                => '0922112233',
            'email'                => $newEmail,
            'role_title'           => 'Senior Engineer',
            'department'           => 'Engineering',
            'employment_type'      => 'permanent',
            'date_of_joining'      => '2025-01-01',
            'basic_salary'         => 20000.00,
            'status'               => 'active',
            'roles'                => [$testRole->name],
            'roles_submitted'      => '1',
            'probation_completed'  => '1',
        ]);

        Auth::login($user);
        $controller = app(EmployeeController::class);
        $response = $controller->update($request, $employee);

        $employee->refresh();
        $user->refresh();

        $this->assertEquals('Updated Full Name', $employee->full_name);
        $this->assertEquals($newEmail, $employee->email);
        $this->assertEquals('0922112233', $employee->phone);
        $this->assertTrue((bool)$employee->probation_completed);

        // Verify linked User account was synchronized
        $this->assertEquals('Updated Full Name', $user->name);
        $this->assertEquals($newEmail, $user->email);
        $this->assertTrue($user->hasRole('hr_officer'));
        $this->assertEquals($user->id, $employee->user_id);
    }

    public function test_employee_update_auto_links_user_and_creates_account_if_missing()
    {
        $uniqueCode = 'EMP' . rand(10000, 99999);
        $email = 'autolink.' . uniqid() . '@example.com';

        $employee = Employee::create([
            'user_id'         => null,
            'employee_code'   => $uniqueCode,
            'full_name'       => 'No User Employee',
            'email'           => $email,
            'phone'           => '0933445566',
            'employment_type' => 'permanent',
            'date_of_joining' => '2025-02-01',
            'basic_salary'    => 12000.00,
            'status'          => 'active',
        ]);

        $adminUser = User::create([
            'name'      => 'Admin User',
            'email'     => 'admin.' . uniqid() . '@example.com',
            'password'  => bcrypt('password'),
            'is_active' => true,
        ]);
        Auth::login($adminUser);

        $request = Request::create(route('employees.update', $employee), 'PUT', [
            'employee_code'        => $employee->employee_code,
            'full_name'            => 'Linked Employee Name',
            'phone'                => '0933445566',
            'email'                => $email,
            'employment_type'      => 'permanent',
            'date_of_joining'      => '2025-02-01',
            'basic_salary'         => 12000.00,
            'status'               => 'active',
        ]);

        $controller = app(EmployeeController::class);
        $controller->update($request, $employee);

        $employee->refresh();
        $this->assertNotNull($employee->user_id);

        $createdUser = User::find($employee->user_id);
        $this->assertNotNull($createdUser);
        $this->assertEquals('Linked Employee Name', $createdUser->name);
        $this->assertEquals($email, $createdUser->email);
    }

    public function test_profile_controller_edit_auto_links_employee_by_email_and_syncs_user_name()
    {
        $email = 'profile.sync.' . uniqid() . '@example.com';

        $user = User::create([
            'name'      => 'Old User Name',
            'email'     => $email,
            'password'  => bcrypt('password'),
            'is_active' => true,
        ]);

        $employee = Employee::create([
            'user_id'         => null, // unlinked
            'employee_code'   => 'EMP' . rand(10000, 99999),
            'full_name'       => 'Updated Employee Official Name',
            'email'           => $email,
            'phone'           => '0911554433',
            'employment_type' => 'permanent',
            'date_of_joining' => '2025-03-01',
            'basic_salary'    => 18000.00,
            'status'          => 'active',
        ]);

        Auth::login($user);
        $controller = app(ProfileController::class);
        $view = $controller->edit();

        $employee->refresh();
        $user->refresh();

        // Auto-linked user_id and synced user.name
        $this->assertEquals($user->id, $employee->user_id);
        $this->assertEquals('Updated Employee Official Name', $user->name);
        $this->assertEquals('profile.edit', $view->name());
        $this->assertEquals('Updated Employee Official Name', $view->getData()['employee']->full_name);
    }

    public function test_profile_controller_update_updates_phone_and_email_on_both_user_and_employee()
    {
        $origEmail = 'orig.profile.' . uniqid() . '@example.com';
        $user = User::create([
            'name'      => 'Self Service User',
            'email'     => $origEmail,
            'password'  => bcrypt('password'),
            'is_active' => true,
        ]);

        $employee = Employee::create([
            'user_id'         => $user->id,
            'employee_code'   => 'EMP' . rand(10000, 99999),
            'full_name'       => 'Self Service User',
            'email'           => $origEmail,
            'phone'           => '0911000000',
            'employment_type' => 'permanent',
            'date_of_joining' => '2025-01-01',
            'basic_salary'    => 14000.00,
            'status'          => 'active',
        ]);

        Auth::login($user);
        $newEmail = 'new.profile.' . uniqid() . '@example.com';
        $request = Request::create(route('profile.update'), 'PUT', [
            'phone' => '0999887766',
            'email' => $newEmail,
        ]);

        $controller = app(ProfileController::class);
        $response = $controller->update($request);

        $employee->refresh();
        $user->refresh();

        $this->assertEquals('0999887766', $employee->phone);
        $this->assertEquals($newEmail, $employee->email);
        $this->assertEquals($newEmail, $user->email);
    }
}
