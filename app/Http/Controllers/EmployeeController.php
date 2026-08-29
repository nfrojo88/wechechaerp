<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Employee::class);

        $statusFilter = $request->get('approval_status', 'all');
        $query = Employee::with(['project', 'gmApprovedBy', 'gmRejectedBy'])->latest();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('employee_code', 'like', "%{$search}%")
                  ->orWhere('department', 'like', "%{$search}%")
                  ->orWhere('role_title', 'like', "%{$search}%");
            });
        }

        if ($request->filled('department')) {
            $query->where('department', $request->department);
        }

        if ($statusFilter === 'approved') {
            $query->where('is_approved_by_gm', true);
        } elseif ($statusFilter === 'pending') {
            $query->where('is_approved_by_gm', false)
                  ->where(function($q) {
                      $q->whereNull('gm_approval_status')->orWhere('gm_approval_status', '!=', 'rejected');
                  });
        } elseif ($statusFilter === 'rejected') {
            $query->where('gm_approval_status', 'rejected');
        } elseif ($request->get('probation_alert') == '1' || $statusFilter === 'probation_alert') {
            $query->where('status', 'active')
                  ->where('probation_completed', false)
                  ->whereNotNull('date_of_joining')
                  ->where(function($q) {
                      $q->whereNull('guarantee_letter')
                        ->orWhere('guarantee_letter', '')
                        ->orWhereNull('tin_number')
                        ->orWhere('tin_number', '');
                  })
                  ->where('date_of_joining', '<=', now()->subDays(20));
        }

        $employees = $query->paginate(20)->withQueryString();

        // Counts for tabs/badges
        $counts = [
            'all'             => Employee::count(),
            'approved'        => Employee::where('is_approved_by_gm', true)->count(),
            'pending'         => Employee::where('is_approved_by_gm', false)->where(function($q) {
                                  $q->whereNull('gm_approval_status')->orWhere('gm_approval_status', '!=', 'rejected');
                                })->count(),
            'rejected'        => Employee::where('gm_approval_status', 'rejected')->count(),
            'probation_alert' => Employee::where('status', 'active')
                                  ->where('probation_completed', false)
                                  ->whereNotNull('date_of_joining')
                                  ->where(function($q) {
                                      $q->whereNull('guarantee_letter')
                                        ->orWhere('guarantee_letter', '')
                                        ->orWhereNull('tin_number')
                                        ->orWhere('tin_number', '');
                                  })
                                  ->where('date_of_joining', '<=', now()->subDays(20))
                                  ->count(),
            'history'         => Employee::whereIn('status', ['terminated', 'suspended'])->orWhereNotNull('lock_reason')->count(),
        ];

        $departments = \App\Models\Department::where('is_active', true)->pluck('name');

        return view('hr.employees.index', compact('employees', 'counts', 'departments'));
    }

    public function pendingApproval(Request $request)
    {
        $query = Employee::where('is_approved_by_gm', false)
            ->where(function($q) {
                $q->whereNull('gm_approval_status')->orWhere('gm_approval_status', '!=', 'rejected');
            })
            ->with(['project']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('employee_code', 'like', "%{$search}%")
                  ->orWhere('role_title', 'like', "%{$search}%");
            });
        }

        if ($request->filled('department')) {
            $query->where('department', $request->department);
        }

        $pendingEmployees = $query->latest()->paginate(25)->withQueryString();
        $departments = Employee::whereNotNull('department')->distinct()->pluck('department');

        return view('hr.employees.pending_approval', compact('pendingEmployees', 'departments'));
    }

    public function approve(Request $request, Employee $employee)
    {
        $employee->update([
            'is_approved_by_gm'   => true,
            'gm_approval_status'  => 'approved',
            'gm_approved_at'      => now(),
            'gm_approved_by'      => auth()->id(),
            'gm_rejection_reason' => null,
        ]);

        return back()->with('success', "Employee {$employee->full_name} ({$employee->employee_code}) has been approved by GM successfully!");
    }

    public function reject(Request $request, Employee $employee)
    {
        $request->validate([
            'rejection_reason' => 'required|string|min:3|max:1000',
        ]);

        $employee->update([
            'is_approved_by_gm'   => false,
            'gm_approval_status'  => 'rejected',
            'gm_rejection_reason' => $request->rejection_reason,
            'gm_rejected_at'      => now(),
            'gm_rejected_by'      => auth()->id(),
        ]);

        return back()->with('success', "Employee {$employee->full_name} ({$employee->employee_code}) was rejected and sent back to HR Officer for correction.");
    }

    public function bulkApprove(Request $request)
    {
        $request->validate([
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'exists:employees,id',
        ]);

        $count = Employee::whereIn('id', $request->employee_ids)->update([
            'is_approved_by_gm'   => true,
            'gm_approval_status'  => 'approved',
            'gm_approved_at'      => now(),
            'gm_approved_by'      => auth()->id(),
            'gm_rejection_reason' => null,
        ]);

        return back()->with('success', "{$count} employee(s) approved by GM successfully!");
    }

    public function bulkReject(Request $request)
    {
        $request->validate([
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'exists:employees,id',
            'rejection_reason' => 'required|string|min:3|max:1000',
        ]);

        $count = Employee::whereIn('id', $request->employee_ids)->update([
            'is_approved_by_gm'   => false,
            'gm_approval_status'  => 'rejected',
            'gm_rejection_reason' => $request->rejection_reason,
            'gm_rejected_at'      => now(),
            'gm_rejected_by'      => auth()->id(),
        ]);

        return back()->with('success', "{$count} employee(s) rejected and returned to HR Officer with correction reason.");
    }

    public function create()
    {
        Gate::authorize('create', Employee::class);
        $projects = Project::where('status', '!=', 'cancelled')->select('id', 'name', 'status')->get();
        $departments = \App\Models\Department::where('is_active', true)->select('id', 'name')->get();
        
        // Available Centralized Fixed Asset Units (In Store) - select light columns only
        $fixedAssetUnits = \App\Models\FixedAssetUnit::select('id', 'fixed_asset_id', 'unit_code', 'status', 'condition', 'brand', 'model', 'serial_number', 'plate_number')
            ->with(['parentAsset' => fn($q) => $q->select('id', 'name', 'category')])
            ->where('status', \App\Models\FixedAssetUnit::STATUS_IN_STORE)
            ->orderBy('unit_code')
            ->get();

        $fixedAssetsJson = $fixedAssetUnits->map(fn($u) => [
            'id'           => $u->id,
            'unit_code'    => $u->unit_code,
            'name'         => $u->parentAsset->name ?? 'Asset',
            'category'     => $u->parentAsset->category ?? 'General',
            'plate_number' => $u->plate_number,
            'serial_number'=> $u->serial_number,
            'brand'        => $u->brand,
            'model'        => $u->model,
        ])->values();

        return view('hr.employees.create', compact('projects', 'departments', 'fixedAssetUnits', 'fixedAssetsJson'));
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Employee::class);

        // Sanitize empty education rows
        if ($request->has('education') && is_array($request->education)) {
            $filteredEdu = array_filter($request->education, function($edu) {
                return !empty($edu['degree_level']) || !empty($edu['field_of_study']) || !empty($edu['institution_name']);
            });
            $request->merge(['education' => empty($filteredEdu) ? null : array_values($filteredEdu)]);
        }

        // Sanitize empty experience rows
        if ($request->has('experience') && is_array($request->experience)) {
            $filteredExp = array_filter($request->experience, function($exp) {
                return !empty($exp['job_title']) || !empty($exp['company_name']) || !empty($exp['start_date']);
            });
            $request->merge(['experience' => empty($filteredExp) ? null : array_values($filteredExp)]);
        }

        // Sanitize empty license rows
        if ($request->has('licenses') && is_array($request->licenses)) {
            $filteredLic = array_filter($request->licenses, function($lic) {
                return !empty($lic['license_name']) || !empty($lic['license_number']) || !empty($lic['issuing_organization']);
            });
            $request->merge(['licenses' => empty($filteredLic) ? null : array_values($filteredLic)]);
        }

        $this->ensureLicenseTableExists();

        // Validate all fields
        $validated = $request->validate([
            'employee_code'                 => 'required|string|unique:employees,employee_code',
            'full_name'                     => 'required|string|max:255',
            'phone'                         => 'required|string|max:20',
            'email'                         => 'nullable|email|max:255',
            'role_title'                    => 'nullable|string|max:255',
            'department'                    => 'required|string|max:100',
            'project_id'                    => 'nullable|exists:projects,id',
            'site_assignment'               => 'nullable|string|max:100',
            'employment_type'               => 'required|in:permanent,contract,daily',
            'contract_type'                 => 'nullable|string',
            'contract_end_date'             => 'nullable|date',
            'contract_duration_type'        => 'nullable|in:fixed_date,until_project_completion',
            'is_project_based'              => 'nullable|boolean',
            'tin_number'                    => 'nullable|string|max:50',
            'probation_completed'           => 'nullable|boolean',
            'date_of_joining'               => 'required|date',
            'basic_salary'                  => 'required|numeric|min:0',
            'transport_allowance'           => 'nullable|numeric|min:0',
            'house_allowance'               => 'nullable|numeric|min:0',
            'position_allowance'            => 'nullable|numeric|min:0',
            'status'                        => 'required|in:active,suspended,terminated',
            'bank_name'                     => 'nullable|string|max:255',
            'account_number'                => 'nullable|string|max:50',
            'notes'                         => 'nullable|string',
            'guarantee_letter'              => 'nullable|file|mimes:pdf,jpeg,png,jpg,webp|max:15360',
            'national_id_number'            => 'nullable|string|max:100',
            'national_id_card'              => 'nullable|file|mimes:pdf,jpeg,png,jpg,webp|max:10240',
            'asset_handover_document'       => 'nullable|file|mimes:pdf,jpeg,png,jpg,webp|max:10240',
            'profile_picture'               => 'nullable|file|mimes:jpeg,png,jpg,webp|max:5120',
            'registration_letter'           => 'nullable|file|mimes:pdf,jpeg,png,jpg,webp|max:15360',
            'fixed_asset_units'             => 'nullable|array',
            'fixed_asset_units.*'           => 'nullable|exists:fixed_asset_units,id',
            'education'                     => 'nullable|array',
            'education.*.degree_level'      => 'nullable|string',
            'education.*.field_of_study'    => 'nullable|string',
            'education.*.institution_name'  => 'nullable|string',
            'education.*.certificate_photo' => 'nullable|file|mimes:pdf,jpeg,png,jpg,webp|max:15360',
            'experience'                     => 'nullable|array',
            'experience.*.job_title'         => 'nullable|string',
            'experience.*.company_name'      => 'nullable|string',
            'experience.*.start_date'        => 'nullable|date',
            'experience.*.experience_letter' => 'nullable|file|mimes:pdf,jpeg,png,jpg,webp|max:15360',
            'licenses'                       => 'nullable|array',
            'licenses.*.license_name'        => 'nullable|string|max:255',
            'licenses.*.issuing_organization'=> 'nullable|string|max:255',
            'licenses.*.license_number'      => 'nullable|string|max:100',
            'licenses.*.issue_date'          => 'nullable|date',
            'licenses.*.expiry_date'         => 'nullable|date',
            'licenses.*.license_document'    => 'nullable|file|mimes:pdf,jpeg,png,jpg,webp|max:15360',
            'device_user_id'                => 'nullable|string|max:100',
            'guarantor_name'                => 'nullable|string|max:255',
            'guarantor_id_number'           => 'nullable|string|max:100',
            'guarantor_id_card'             => 'nullable|file|mimes:pdf,jpeg,png,jpg,webp|max:15360',
            'guarantor_phone'               => 'nullable|string|max:50',
            'guarantee_letter'              => 'nullable|file|mimes:pdf,jpeg,png,jpg,webp|max:15360',
            'guarantor_2_name'              => 'nullable|string|max:255',
            'guarantor_2_id_number'         => 'nullable|string|max:100',
            'guarantor_2_id_card'           => 'nullable|file|mimes:pdf,jpeg,png,jpg,webp|max:15360',
            'guarantor_2_phone'             => 'nullable|string|max:50',
            'guarantee_letter_2'            => 'nullable|file|mimes:pdf,jpeg,png,jpg,webp|max:15360',
            'registration_letter'           => 'nullable|file|mimes:pdf,jpeg,png,jpg,webp|max:15360',
            'registration_letters'          => 'nullable|array',
            'registration_letters.*'        => 'nullable|file|mimes:pdf,jpeg,png,jpg,webp|max:15360',
        ]);

        // Auto-heal missing database columns if necessary
        $this->ensureGuarantorAndRegistrationColumnsExist();

        // Apply defaults and project-based contract duration rules
        $validated['employment_type']     = $validated['employment_type'] ?? 'permanent';
        $validated['status']              = $validated['status'] ?? 'active';
        $validated['basic_salary']        = $validated['basic_salary'] ?? 0;
        $validated['transport_allowance'] = $validated['transport_allowance'] ?? 0;
        $validated['house_allowance']     = $validated['house_allowance'] ?? 0;
        $validated['position_allowance']  = $validated['position_allowance'] ?? 0;

        if (($validated['contract_duration_type'] ?? '') === 'until_project_completion' || $request->boolean('is_project_based')) {
            $validated['contract_duration_type'] = 'until_project_completion';
            $validated['is_project_based'] = true;
            if (!empty($validated['project_id'])) {
                $targetProj = \App\Models\Project::find($validated['project_id']);
                if ($targetProj && in_array(strtolower((string)$targetProj->status), ['completed', 'finished', 'closed', 'cancelled', 'handover', 'archived'])) {
                    $validated['status'] = 'locked';
                    $validated['lock_reason'] = "Project Finished: {$targetProj->name} ({$targetProj->code})";
                }
            }
        } else {
            $validated['contract_duration_type'] = $validated['contract_duration_type'] ?? 'fixed_date';
            $validated['is_project_based'] = false;
        }


        // Handle guarantee letter upload (Guarantor 1)
        if ($request->hasFile('guarantee_letter')) {
            $letterPath = \App\Services\FileUploadService::upload($request->file('guarantee_letter'), 'guarantee_letters');
            $validated['guarantee_letter'] = $letterPath;
            $validated['guarantee_letter_submitted_at'] = now();
        }

        // Handle second guarantee letter upload (Guarantor 2)
        if ($request->hasFile('guarantee_letter_2')) {
            $letter2Path = \App\Services\FileUploadService::upload($request->file('guarantee_letter_2'), 'guarantee_letters');
            $validated['guarantee_letter_2'] = $letter2Path;
        }

        // Handle Guarantor 1 National ID card upload
        if ($request->hasFile('guarantor_id_card')) {
            $validated['guarantor_id_card'] = \App\Services\FileUploadService::upload($request->file('guarantor_id_card'), 'employee_guarantor_ids');
        }

        // Handle Guarantor 2 National ID card upload
        if ($request->hasFile('guarantor_2_id_card')) {
            $validated['guarantor_2_id_card'] = \App\Services\FileUploadService::upload($request->file('guarantor_2_id_card'), 'employee_guarantor_ids');
        }

        // Handle National ID card upload
        if ($request->hasFile('national_id_card')) {
            $validated['national_id_card'] = \App\Services\FileUploadService::upload($request->file('national_id_card'), 'employee_national_ids');
        }

        // Handle Asset Handover document upload
        if ($request->hasFile('asset_handover_document')) {
            $validated['asset_handover_document'] = \App\Services\FileUploadService::upload($request->file('asset_handover_document'), 'employee_asset_handovers');
        }

        // Handle Profile Picture upload
        if ($request->hasFile('profile_picture')) {
            $validated['profile_picture'] = \App\Services\FileUploadService::upload($request->file('profile_picture'), 'employee_profile_pictures');
        }

        // Handle Registration Letter(s) upload (Multiple pictures / files support)
        $regLetterPaths = [];
        if ($request->hasFile('registration_letters')) {
            foreach ($request->file('registration_letters') as $regFile) {
                if ($regFile) {
                    $regLetterPaths[] = \App\Services\FileUploadService::upload($regFile, 'employee_registration_letters');
                }
            }
        }
        if ($request->hasFile('registration_letter')) {
            $singlePath = \App\Services\FileUploadService::upload($request->file('registration_letter'), 'employee_registration_letters');
            if (empty($regLetterPaths)) {
                $regLetterPaths[] = $singlePath;
            }
        }
        if (!empty($regLetterPaths)) {
            $validated['registration_letters'] = $regLetterPaths;
            $validated['registration_letter'] = $regLetterPaths[0];
        }

        // Create or find User account
        $userEmail = $validated['email'] ?? strtolower($validated['employee_code']) . '@construct-pro.com';
        
        try {
            $user = \App\Models\User::firstOrCreate(
                ['email' => $userEmail],
                [
                    'name'      => $validated['full_name'],
                    'password'  => \Illuminate\Support\Facades\Hash::make('password'),
                    'is_active' => true,
                ]
            );
            
            $validated['user_id'] = $user->id;

            // Strip nested arrays before Eloquent creation
            $employeeData = \Illuminate\Support\Arr::except($validated, ['fixed_asset_units', 'education', 'experience', 'licenses']);
            $employee = Employee::create($employeeData);

            // Attach Centralized Fixed Asset Units
            $fixedAssetUnitIds = array_filter($request->input('fixed_asset_units', []));
            if (!empty($fixedAssetUnitIds)) {
                foreach ($fixedAssetUnitIds as $unitId) {
                    if (!empty($unitId)) {
                        $unit = \App\Models\FixedAssetUnit::find($unitId);
                        if ($unit && $unit->isAvailable()) {
                            $unit->assignToEmployee($employee->id, auth()->id(), 'Assigned during employee registration');
                        }
                    }
                }
            }

            // Save Education records with file uploads
            if ($request->has('education') && is_array($request->education)) {
                $this->saveEducationRecords($employee, $request->education);
            }

            // Save Experience records with file uploads
            if ($request->has('experience') && is_array($request->experience)) {
                $this->saveExperienceRecords($employee, $request->experience);
            }

            // Save Dedicated Professional License records with file uploads
            if ($request->has('licenses') && is_array($request->licenses)) {
                $this->saveLicenseRecords($employee, $request->licenses);
            }

            // Clear any lingering session cache
            session()->forget(['employee_data', 'employee_education_files', 'employee_experience_files']);

            // Send Welcome SMS Notification if phone number is provided
            if (!empty($employee->phone)) {
                try {
                    $smsService = app(\App\Services\SmsEthiopiaService::class);
                    $message = "Welcome {$employee->full_name}! You have been successfully registered in the Construct-Pro ERP system. Your Employee Code is: {$employee->employee_code}.";
                    $smsService->sendNotification($employee->phone, $message);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Failed to send welcome SMS: ' . $e->getMessage());
                }
            }

            // Send SMS Notification to GM for approval
            try {
                $gmUsers = \App\Models\User::role('gm')->get();
                $smsService = app(\App\Services\SmsEthiopiaService::class);
                $gmMessage = "New Employee {$employee->full_name} ({$employee->employee_code}) registered. Please login to approve.";
                foreach ($gmUsers as $gm) {
                    if ($gm->phone) {
                        $smsService->sendNotification($gm->phone, $gmMessage);
                    }
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to send GM SMS: ' . $e->getMessage());
            }

            return redirect()->route('employees.show', $employee)
                ->with('success', "Registration successful! Employee \"{$employee->full_name}\" ({$employee->employee_code}) has been registered and submitted for GM approval.");
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Employee registration failed: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return back()->withInput()->withErrors(['error' => 'Registration failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Save education records with file uploads
     */
    private function saveEducationRecords(Employee $employee, array $educationData): void
    {
        foreach ($educationData as $index => $education) {
            if (empty($education['degree_level']) && empty($education['field_of_study']) && empty($education['institution_name'])) {
                continue;
            }

            $certificatePath = null;
            if (request()->hasFile("education.{$index}.certificate_photo")) {
                $file = request()->file("education.{$index}.certificate_photo");
                $certificatePath = \App\Services\FileUploadService::upload($file, 'employee_certificates');
            }

            $startDate = !empty($education['start_date']) ? $education['start_date'] : null;
            $endDate   = !empty($education['end_date'])   ? $education['end_date']   : null;

            \App\Models\EmployeeEducation::create([
                'employee_id'       => $employee->id,
                'degree_level'      => !empty($education['degree_level'])     ? $education['degree_level']     : 'Other',
                'field_of_study'    => !empty($education['field_of_study'])   ? $education['field_of_study']   : 'General',
                'institution_name'  => !empty($education['institution_name']) ? $education['institution_name'] : 'N/A',
                'location'          => $education['location']    ?? null,
                'start_date'        => $startDate,
                'end_date'          => $endDate,
                'grade_gpa'         => $education['grade_gpa']   ?? null,
                'description'       => $education['description'] ?? null,
                'certificate_photo' => $certificatePath,
                'is_verified'       => false,
            ]);
        }
    }

    /**
     * Save experience records with file uploads
     */
    private function saveExperienceRecords(Employee $employee, array $experienceData): void
    {
        foreach ($experienceData as $index => $experience) {
            if (empty($experience['job_title']) && empty($experience['company_name'])) {
                continue;
            }

            $letterPath = null;
            if (request()->hasFile("experience.{$index}.experience_letter")) {
                $file = request()->file("experience.{$index}.experience_letter");
                $letterPath = \App\Services\FileUploadService::upload($file, 'employee_experiences');
            }

            $licensePath = null;
            if (request()->hasFile("experience.{$index}.license_document")) {
                $file = request()->file("experience.{$index}.license_document");
                $licensePath = \App\Services\FileUploadService::upload($file, 'employee_licenses');
            }

            $isCurrent = isset($experience['is_current']) && $experience['is_current'] == '1';

            $expStartDate   = !empty($experience['start_date'])    ? $experience['start_date']    : null;
            $expEndDate     = $isCurrent ? null : (!empty($experience['end_date']) ? $experience['end_date'] : null);
            $expLicExpiry   = !empty($experience['license_expiry']) ? $experience['license_expiry'] : null;

            \App\Models\EmployeeExperience::create([
                'employee_id'       => $employee->id,
                'job_title'         => !empty($experience['job_title'])    ? $experience['job_title']    : 'N/A',
                'company_name'      => !empty($experience['company_name']) ? $experience['company_name'] : 'N/A',
                'location'          => $experience['location']         ?? null,
                'start_date'        => $expStartDate,
                'end_date'          => $expEndDate,
                'is_current'        => $isCurrent,
                'responsibilities'  => $experience['responsibilities'] ?? null,
                'experience_letter' => $letterPath,
                'reference_name'    => $experience['reference_name']   ?? null,
                'reference_phone'   => $experience['reference_phone']  ?? null,
            ]);
        }
    }

    /**
     * Save dedicated professional license records with file uploads
     */
    private function saveLicenseRecords(Employee $employee, array $licenseData): void
    {
        $this->ensureLicenseTableExists();

        foreach ($licenseData as $index => $lic) {
            if (empty($lic['license_name']) && empty($lic['license_number'])) {
                continue;
            }

            $docPath = null;
            if (request()->hasFile("licenses.{$index}.license_document")) {
                $file = request()->file("licenses.{$index}.license_document");
                $docPath = \App\Services\FileUploadService::upload($file, 'employee_licenses');
            }

            \App\Models\EmployeeLicense::create([
                'employee_id'          => $employee->id,
                'license_name'         => !empty($lic['license_name']) ? $lic['license_name'] : 'Professional License',
                'issuing_organization' => $lic['issuing_organization'] ?? null,
                'license_number'       => $lic['license_number'] ?? null,
                'issue_date'           => !empty($lic['issue_date']) ? $lic['issue_date'] : null,
                'expiry_date'          => !empty($lic['expiry_date']) ? $lic['expiry_date'] : null,
                'license_document'     => $docPath,
                'status'               => $lic['status'] ?? 'active',
                'notes'                => $lic['notes'] ?? null,
            ]);
        }
    }

    /**
     * Auto-heal ensure employee_licenses table exists
     */
    private function ensureLicenseTableExists(): void
    {
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('employee_licenses')) {
                \Illuminate\Support\Facades\Schema::create('employee_licenses', function (\Illuminate\Database\Schema\Blueprint $table) {
                    $table->id();
                    $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
                    $table->string('license_name');
                    $table->string('issuing_organization')->nullable();
                    $table->string('license_number')->nullable();
                    $table->date('issue_date')->nullable();
                    $table->date('expiry_date')->nullable();
                    $table->string('license_document')->nullable();
                    $table->string('status')->default('active');
                    $table->text('notes')->nullable();
                    $table->timestamps();
                });
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Employee license table auto-heal: ' . $e->getMessage());
        }
    }

    /**
     * Auto-heal ensure employee_letters table exists
     */
    private function ensureLettersTableExists(): void
    {
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('employee_letters')) {
                \Illuminate\Support\Facades\Schema::create('employee_letters', function (\Illuminate\Database\Schema\Blueprint $table) {
                    $table->id();
                    $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
                    $table->string('reference_number')->nullable()->unique();
                    $table->string('letter_type');
                    $table->string('title');
                    $table->text('content');
                    $table->string('severity')->default('info');
                    $table->date('issued_date');
                    $table->foreignId('issued_by')->nullable()->constrained('users')->onDelete('set null');
                    $table->string('attachment_path')->nullable();
                    $table->date('effective_date')->nullable();
                    $table->text('action_required')->nullable();
                    $table->string('acknowledgement_status')->default('acknowledged');
                    $table->timestamp('acknowledged_at')->nullable();
                    $table->timestamps();
                });
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Employee letters table auto-heal: ' . $e->getMessage());
        }
    }

    public function show(Employee $employee)
    {
        Gate::authorize('view', $employee);
        $this->ensureLicenseTableExists();
        $this->ensureLettersTableExists();
        $employee->load([
            'project', 
            'payrolls' => fn($q) => $q->latest()->limit(12),
            'education',
            'experience',
            'licenses',
            'letters.issuer',
            'activeAssets.product',
            'assignedFixedAssets.parentAsset',
            'fixedAssetAssignments.unit.parentAsset',
            'fixedAssetAssignments.assigner',
            'fixedAssetAssignments.receiver'
        ]);
        return view('hr.employees.show', compact('employee'));
    }

    public function edit(Employee $employee)
    {
        Gate::authorize('update', $employee);
        $this->ensureLicenseTableExists();
        $employee->load([
            'project',
            'education',
            'experience',
            'licenses',
            'assignedFixedAssets' => fn($q) => $q->select('id', 'fixed_asset_id', 'unit_code', 'status', 'condition', 'brand', 'model', 'serial_number', 'plate_number', 'assigned_to_employee_id'),
            'assignedFixedAssets.parentAsset' => fn($q) => $q->select('id', 'name', 'category'),
        ]);
        $projects     = Project::where('status', '!=', 'cancelled')->select('id', 'name', 'status')->get();
        $departments  = \App\Models\Department::where('is_active', true)->select('id', 'name')->get();
        
        // Available Centralized Fixed Asset Units (In Store OR currently assigned to this employee)
        $fixedAssetUnits = \App\Models\FixedAssetUnit::select('id', 'fixed_asset_id', 'unit_code', 'status', 'condition', 'brand', 'model', 'serial_number', 'plate_number', 'assigned_to_employee_id')
            ->with(['parentAsset' => fn($q) => $q->select('id', 'name', 'category')])
            ->where(function($q) use ($employee) {
                $q->where('status', \App\Models\FixedAssetUnit::STATUS_IN_STORE)
                  ->orWhere('assigned_to_employee_id', $employee->id);
            })
            ->orderBy('unit_code')
            ->get();

        $fixedAssetsJson = $fixedAssetUnits->map(fn($u) => [
            'id'           => $u->id,
            'unit_code'    => $u->unit_code,
            'name'         => $u->parentAsset->name ?? 'Asset',
            'category'     => $u->parentAsset->category ?? 'General',
            'plate_number' => $u->plate_number,
            'serial_number'=> $u->serial_number,
            'brand'        => $u->brand,
            'model'        => $u->model,
            'assigned_to'  => $u->assigned_to_employee_id,
        ])->values();

        return view('hr.employees.edit', compact('employee', 'projects', 'departments', 'fixedAssetUnits', 'fixedAssetsJson'));
    }

    public function update(Request $request, Employee $employee)
    {
        Gate::authorize('update', $employee);
        $this->ensureLicenseTableExists();

        $validated = $request->validate([
            'employee_code'        => 'required|string|unique:employees,employee_code,'.$employee->id,
            'full_name'            => 'required|string|max:255',
            'phone'                => 'nullable|string|max:20',
            'email'                => 'nullable|email|max:255',
            'role_title'           => 'nullable|string|max:255',
            'department'           => 'nullable|string|max:100',
            'project_id'           => 'nullable|exists:projects,id',
            'site_assignment'      => 'nullable|string|max:100',
            'employment_type'      => 'required|in:permanent,contract,daily',
            'contract_type'        => 'nullable|string',
            'contract_end_date'    => 'nullable|date',
            'contract_duration_type' => 'nullable|in:fixed_date,until_project_completion',
            'is_project_based'     => 'nullable|boolean',
            'tin_number'           => 'nullable|string|max:50',
            'probation_completed'  => 'nullable|boolean',
            'date_of_joining'      => 'required|date',

            'basic_salary'         => 'required|numeric|min:0',
            'transport_allowance'  => 'nullable|numeric|min:0',
            'house_allowance'      => 'nullable|numeric|min:0',
            'position_allowance'   => 'nullable|numeric|min:0',
            'bank_name'            => 'nullable|string|max:255',
            'account_number'       => 'nullable|string|max:100',
            'status'               => 'required|in:active,suspended,terminated',
            'notes'                => 'nullable|string',
            'device_user_id'       => 'nullable|string|max:100',
            'guarantee_letter'     => 'nullable|file|mimes:pdf,jpeg,png,jpg,webp|max:15360',
            'guarantor_name'       => 'nullable|string|max:255',
            'guarantor_id_number'  => 'nullable|string|max:100',
            'guarantor_id_card'    => 'nullable|file|mimes:pdf,jpeg,png,jpg,webp|max:15360',
            'guarantor_phone'      => 'nullable|string|max:50',
            'guarantee_letter_2'   => 'nullable|file|mimes:pdf,jpeg,png,jpg,webp|max:15360',
            'guarantor_2_name'     => 'nullable|string|max:255',
            'guarantor_2_id_number'=> 'nullable|string|max:100',
            'guarantor_2_id_card'  => 'nullable|file|mimes:pdf,jpeg,png,jpg,webp|max:15360',
            'guarantor_2_phone'    => 'nullable|string|max:50',
            'national_id_number'   => 'nullable|string|max:100',
            'national_id_card'     => 'nullable|file|mimes:pdf,jpeg,png,jpg,webp|max:10240',
            'asset_handover_document' => 'nullable|file|mimes:pdf,jpeg,png,jpg,webp|max:10240',
            'profile_picture'      => 'nullable|file|mimes:jpeg,png,jpg,webp|max:5120',
            'registration_letter'  => 'nullable|file|mimes:pdf,jpeg,png,jpg,webp|max:15360',
            'registration_letters' => 'nullable|array',
            'registration_letters.*' => 'nullable|file|mimes:pdf,jpeg,png,jpg,webp|max:15360',
            'fixed_asset_units'    => 'nullable|array',
            'education'            => 'nullable|array',
            'experience'           => 'nullable|array',
            'licenses'             => 'nullable|array',
        ]);

        // Auto-heal missing database columns if necessary
        $this->ensureGuarantorAndRegistrationColumnsExist();

        // Handle guarantee letter upload if provided (Guarantor 1)
        if ($request->hasFile('guarantee_letter')) {
            $letterPath = \App\Services\FileUploadService::upload($request->file('guarantee_letter'), 'guarantee_letters');
            $validated['guarantee_letter'] = $letterPath;
            $validated['guarantee_letter_submitted_at'] = now();
        }

        // Handle second guarantee letter upload if provided (Guarantor 2)
        if ($request->hasFile('guarantee_letter_2')) {
            $letter2Path = \App\Services\FileUploadService::upload($request->file('guarantee_letter_2'), 'guarantee_letters');
            $validated['guarantee_letter_2'] = $letter2Path;
        }

        // Handle Guarantor 1 National ID card upload (replace if new)
        if ($request->hasFile('guarantor_id_card')) {
            $validated['guarantor_id_card'] = \App\Services\FileUploadService::upload($request->file('guarantor_id_card'), 'employee_guarantor_ids');
        }

        // Handle Guarantor 2 National ID card upload (replace if new)
        if ($request->hasFile('guarantor_2_id_card')) {
            $validated['guarantor_2_id_card'] = \App\Services\FileUploadService::upload($request->file('guarantor_2_id_card'), 'employee_guarantor_ids');
        }

        // Handle National ID card upload (replace if new)
        if ($request->hasFile('national_id_card')) {
            $validated['national_id_card'] = \App\Services\FileUploadService::upload($request->file('national_id_card'), 'employee_national_ids');
        }

        // Handle Asset Handover document upload (replace if new)
        if ($request->hasFile('asset_handover_document')) {
            $validated['asset_handover_document'] = \App\Services\FileUploadService::upload($request->file('asset_handover_document'), 'employee_asset_handovers');
        }

        // Handle Profile Picture upload (replace if new)
        if ($request->hasFile('profile_picture')) {
            $validated['profile_picture'] = \App\Services\FileUploadService::upload($request->file('profile_picture'), 'employee_profile_pictures');
        }

        // Handle Registration Letter(s) upload (Multiple pictures / files support)
        if ($request->hasFile('registration_letters')) {
            $newRegPaths = [];
            foreach ($request->file('registration_letters') as $regFile) {
                if ($regFile) {
                    $newRegPaths[] = \App\Services\FileUploadService::upload($regFile, 'employee_registration_letters');
                }
            }
            if (!empty($newRegPaths)) {
                $validated['registration_letters'] = $newRegPaths;
                $validated['registration_letter'] = $newRegPaths[0];
            }
        } elseif ($request->hasFile('registration_letter')) {
            $singlePath = \App\Services\FileUploadService::upload($request->file('registration_letter'), 'employee_registration_letters');
            $validated['registration_letter'] = $singlePath;
            $validated['registration_letters'] = [$singlePath];
        }

        // Handle contract duration type & project based lock
        if (($validated['contract_duration_type'] ?? '') === 'until_project_completion' || $request->boolean('is_project_based')) {
            $validated['contract_duration_type'] = 'until_project_completion';
            $validated['is_project_based'] = true;
            if (!empty($validated['project_id'])) {
                $targetProj = \App\Models\Project::find($validated['project_id']);
                if ($targetProj && in_array(strtolower((string)$targetProj->status), ['completed', 'finished', 'closed', 'cancelled', 'handover', 'archived'])) {
                    $validated['status'] = 'locked';
                    $validated['lock_reason'] = "Project Finished: {$targetProj->name} ({$targetProj->code})";
                }
            }
        } else {
            $validated['contract_duration_type'] = $validated['contract_duration_type'] ?? 'fixed_date';
            $validated['is_project_based'] = false;
        }

        // If employee was rejected by GM, resubmitting clears rejection and queues for GM review
        if ($employee->gm_approval_status === 'rejected') {
            $validated['gm_approval_status'] = 'pending';
            $validated['is_approved_by_gm'] = false;
            $validated['gm_rejection_reason'] = null;
        }

        // Check if status changed to terminated
        $wasTerminated = $employee->status !== 'terminated' && $validated['status'] === 'terminated';

        // Strip non-model attributes
        $employeeData = \Illuminate\Support\Arr::except($validated, ['fixed_asset_units', 'education', 'experience', 'licenses']);
        $employee->update($employeeData);


        // ── Sync Fixed Asset Units ──────────────────────────────────────────
        $currentAssignedIds = $employee->assignedFixedAssets()->pluck('id')->toArray();
        $newSelectedIds = array_filter(array_map('intval', $request->input('fixed_asset_units', [])));

        // Unassign units that were removed
        $toUnassign = array_diff($currentAssignedIds, $newSelectedIds);
        foreach ($toUnassign as $uId) {
            $unit = \App\Models\FixedAssetUnit::find($uId);
            if ($unit && $unit->assigned_to_employee_id == $employee->id) {
                $unit->returnToStore(auth()->id(), 'Unassigned during employee profile update');
            }
        }

        // Assign newly selected units
        $toAssign = array_diff($newSelectedIds, $currentAssignedIds);
        foreach ($toAssign as $uId) {
            $unit = \App\Models\FixedAssetUnit::find($uId);
            if ($unit && ($unit->isAvailable() || $unit->assigned_to_employee_id == $employee->id)) {
                $unit->assignToEmployee($employee->id, auth()->id(), 'Assigned during employee profile update');
            }
        }

        // ── Sync Education Records ──────────────────────────────────────────
        if ($request->has('education') && is_array($request->education)) {
            $submittedEduIds = [];
            foreach ($request->education as $index => $eduData) {
                if (empty($eduData['degree_level']) && empty($eduData['field_of_study']) && empty($eduData['institution_name'])) {
                    continue;
                }

                $certPath = null;
                if ($request->hasFile("education.{$index}.certificate_photo")) {
                    $certPath = \App\Services\FileUploadService::upload($request->file("education.{$index}.certificate_photo"), 'employee_certificates');
                }

                $eduPayload = [
                    'employee_id'      => $employee->id,
                    'degree_level'     => !empty($eduData['degree_level'])     ? $eduData['degree_level']     : 'Other',
                    'field_of_study'   => !empty($eduData['field_of_study'])   ? $eduData['field_of_study']   : 'General',
                    'institution_name' => !empty($eduData['institution_name']) ? $eduData['institution_name'] : 'N/A',
                    'location'         => $eduData['location']    ?? null,
                    'start_date'       => !empty($eduData['start_date']) ? $eduData['start_date'] : null,
                    'end_date'         => !empty($eduData['end_date'])   ? $eduData['end_date']   : null,
                    'grade_gpa'        => $eduData['grade_gpa']   ?? null,
                    'description'      => $eduData['description'] ?? null,
                ];
                if ($certPath) {
                    $eduPayload['certificate_photo'] = $certPath;
                }

                if (!empty($eduData['id'])) {
                    $eduRecord = \App\Models\EmployeeEducation::where('employee_id', $employee->id)->find($eduData['id']);
                    if ($eduRecord) {
                        $eduRecord->update($eduPayload);
                        $submittedEduIds[] = $eduRecord->id;
                        continue;
                    }
                }

                $newEdu = \App\Models\EmployeeEducation::create($eduPayload);
                $submittedEduIds[] = $newEdu->id;
            }

            // Remove education records deleted in UI
            if (!empty($submittedEduIds)) {
                \App\Models\EmployeeEducation::where('employee_id', $employee->id)->whereNotIn('id', $submittedEduIds)->delete();
            }
        }

        // ── Sync Experience Records (Clean: Job experience only) ─────────────
        if ($request->has('experience') && is_array($request->experience)) {
            $submittedExpIds = [];
            foreach ($request->experience as $index => $expData) {
                if (empty($expData['job_title']) && empty($expData['company_name'])) {
                    continue;
                }

                $letterDocPath = null;
                if ($request->hasFile("experience.{$index}.experience_letter")) {
                    $letterDocPath = \App\Services\FileUploadService::upload($request->file("experience.{$index}.experience_letter"), 'employee_experiences');
                }

                $isCurrent = isset($expData['is_current']) && $expData['is_current'] == '1';

                $expPayload = [
                    'employee_id'      => $employee->id,
                    'job_title'        => !empty($expData['job_title'])    ? $expData['job_title']    : 'N/A',
                    'company_name'     => !empty($expData['company_name']) ? $expData['company_name'] : 'N/A',
                    'location'         => $expData['location']         ?? null,
                    'start_date'       => !empty($expData['start_date'])    ? $expData['start_date']    : null,
                    'end_date'         => $isCurrent ? null : (!empty($expData['end_date']) ? $expData['end_date'] : null),
                    'is_current'       => $isCurrent,
                    'responsibilities' => $expData['responsibilities'] ?? null,
                    'reference_name'   => $expData['reference_name']   ?? null,
                    'reference_phone'  => $expData['reference_phone']  ?? null,
                ];
                if ($letterDocPath) {
                    $expPayload['experience_letter'] = $letterDocPath;
                }

                if (!empty($expData['id'])) {
                    $expRecord = \App\Models\EmployeeExperience::where('employee_id', $employee->id)->find($expData['id']);
                    if ($expRecord) {
                        $expRecord->update($expPayload);
                        $submittedExpIds[] = $expRecord->id;
                        continue;
                    }
                }

                $newExp = \App\Models\EmployeeExperience::create($expPayload);
                $submittedExpIds[] = $newExp->id;
            }

            // Remove experience records deleted in UI
            if (!empty($submittedExpIds)) {
                \App\Models\EmployeeExperience::where('employee_id', $employee->id)->whereNotIn('id', $submittedExpIds)->delete();
            }
        }

        // ── Sync Professional Licenses Records (Dedicated) ──────────────────
        if ($request->has('licenses') && is_array($request->licenses)) {
            $submittedLicIds = [];
            foreach ($request->licenses as $index => $licData) {
                if (empty($licData['license_name']) && empty($licData['license_number']) && empty($licData['issuing_organization'])) {
                    continue;
                }

                $licenseDocPath = null;
                if ($request->hasFile("licenses.{$index}.license_document")) {
                    $licenseDocPath = \App\Services\FileUploadService::upload($request->file("licenses.{$index}.license_document"), 'employee_licenses');
                }

                $licPayload = [
                    'employee_id'          => $employee->id,
                    'license_name'         => !empty($licData['license_name']) ? $licData['license_name'] : 'Professional License',
                    'issuing_organization' => $licData['issuing_organization'] ?? null,
                    'license_number'       => $licData['license_number'] ?? null,
                    'issue_date'           => !empty($licData['issue_date']) ? $licData['issue_date'] : null,
                    'expiry_date'          => !empty($licData['expiry_date']) ? $licData['expiry_date'] : null,
                    'status'               => $licData['status'] ?? 'active',
                    'notes'                => $licData['notes'] ?? null,
                ];
                if ($licenseDocPath) {
                    $licPayload['license_document'] = $licenseDocPath;
                }

                if (!empty($licData['id'])) {
                    $licRecord = \App\Models\EmployeeLicense::where('employee_id', $employee->id)->find($licData['id']);
                    if ($licRecord) {
                        $licRecord->update($licPayload);
                        $submittedLicIds[] = $licRecord->id;
                        continue;
                    }
                }

                $newLic = \App\Models\EmployeeLicense::create($licPayload);
                $submittedLicIds[] = $newLic->id;
            }

            // Remove license records deleted in UI
            if (!empty($submittedLicIds)) {
                \App\Models\EmployeeLicense::where('employee_id', $employee->id)->whereNotIn('id', $submittedLicIds)->delete();
            }
        }

        if ($wasTerminated) {
            // Flag all currently assigned assets for return approval
            \App\Models\EmployeeAsset::where('employee_id', $employee->id)
                ->whereIn('status', ['assigned', 'in_use'])
                ->update(['return_status' => 'pending_approval']);
        }

        if ($employee->wasChanged('phone') && !empty($employee->phone)) {
            try {
                $smsService = app(\App\Services\SmsEthiopiaService::class);
                $message = "Hello {$employee->full_name}, your phone number has been updated in your Construct-Pro ERP profile. If this wasn't you, please contact HR immediately.";
                $smsService->sendNotification($employee->phone, $message);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to send phone update SMS: ' . $e->getMessage());
            }
        }

        return redirect()->route('employees.show', $employee)
            ->with('success', 'Employee profile updated successfully!');
    }

    /**
     * Quick direct upload for Guarantor ID Cards, Guarantee Letters, and National ID
     */
    public function uploadGuarantorDocument(Request $request, Employee $employee)
    {
        Gate::authorize('update', $employee);
        $this->ensureGuarantorAndRegistrationColumnsExist();

        $validated = $request->validate([
            'doc_type'              => 'required|in:guarantor_id_card,guarantee_letter,guarantor_2_id_card,guarantee_letter_2,national_id_card',
            'document'              => 'required|file|mimes:pdf,jpeg,png,jpg,webp|max:15360',
            'guarantor_name'        => 'nullable|string|max:255',
            'guarantor_id_number'   => 'nullable|string|max:100',
            'guarantor_phone'       => 'nullable|string|max:50',
            'guarantor_2_name'      => 'nullable|string|max:255',
            'guarantor_2_id_number' => 'nullable|string|max:100',
            'guarantor_2_phone'     => 'nullable|string|max:50',
        ]);

        $folder = match ($validated['doc_type']) {
            'guarantor_id_card', 'guarantor_2_id_card' => 'employee_guarantor_ids',
            'guarantee_letter', 'guarantee_letter_2'   => 'guarantee_letters',
            'national_id_card'                         => 'employee_national_ids',
        };

        $path = \App\Services\FileUploadService::upload($request->file('document'), $folder);
        $updateData = [$validated['doc_type'] => $path];

        if ($validated['doc_type'] === 'guarantee_letter') {
            $updateData['guarantee_letter_submitted_at'] = now();
        }

        foreach (['guarantor_name', 'guarantor_id_number', 'guarantor_phone', 'guarantor_2_name', 'guarantor_2_id_number', 'guarantor_2_phone'] as $field) {
            if ($request->filled($field)) {
                $updateData[$field] = $request->input($field);
            }
        }

        $employee->update($updateData);

        return back()->with('success', 'Guarantor document uploaded and recorded successfully!');
    }

    /**
     * Upload guarantee letter for employee
     */
    public function uploadGuaranteeLetter(Request $request, Employee $employee)
    {
        Gate::authorize('update', $employee);

        $request->validate([
            'guarantee_letter' => 'required|file|mimes:pdf,jpeg,png,jpg|max:10240',
        ]);

        // Upload the file to Cloudinary
        $cloudinary = app(\App\Services\CloudinaryService::class);
        $path = $cloudinary->upload($request->file('guarantee_letter'), 'guarantee_letters');

        // Update employee record
        $employee->update([
            'guarantee_letter' => $path,
            'guarantee_letter_submitted_at' => now(),
        ]);

        return redirect()->route('employees.show', $employee)
            ->with('success', 'Guarantee letter uploaded successfully!');
    }

    /**
     * One-time initialization and allocation of annual leave balance (16 days/year standard).
     */
    public function initializeLeaveBalance(Request $request, Employee $employee)
    {
        Gate::authorize('update', $employee);

        $request->validate([
            'year'       => 'nullable|integer|min:2020|max:2050',
            'total_days' => 'nullable|numeric|min:1|max:60',
        ]);

        $year = $request->input('year', date('Y'));
        $totalDays = (float) $request->input('total_days', 16.0);

        // Ensure LeaveType "Annual Leave" exists
        $leaveType = \App\Models\LeaveType::firstOrCreate(
            ['code' => 'ANNUAL'],
            [
                'name' => 'Annual Leave',
                'days_allowed' => 16,
                'is_paid' => true,
                'requires_documentation' => false,
                'description' => 'Standard statutory annual leave (16 working days/year at 1.33 days/month rate)',
                'is_active' => true,
            ]
        );

        // Calculate already taken/approved leave in current year
        $usedDays = \App\Models\LeaveRequest::where('employee_id', $employee->id)
            ->where('leave_type_id', $leaveType->id)
            ->whereYear('start_date', $year)
            ->where('status', 'approved')
            ->get()
            ->sum(function ($req) {
                return (float) ($req->days_requested ?? 1);
            });

        $remainingDays = max(0, $totalDays - $usedDays);

        // Create or update LeaveBalance
        $balance = \App\Models\LeaveBalance::updateOrCreate(
            [
                'employee_id'   => $employee->id,
                'leave_type_id' => $leaveType->id,
                'year'          => $year,
            ],
            [
                'total_days'     => $totalDays,
                'used_days'      => $usedDays,
                'remaining_days' => $remainingDays,
            ]
        );

        return back()->with('success', "Annual leave balance for {$year} initialized successfully! Allocated: {$totalDays} days (1.33 days/month rate). Remaining: {$remainingDays} days.");
    }

    /**
     * Record a direct leave deduction from employee profile.
     */
    public function recordLeaveDeduction(Request $request, Employee $employee)
    {
        Gate::authorize('update', $employee);

        $validated = $request->validate([
            'days'       => 'required|numeric|min:0.5|max:30',
            'start_date' => 'required|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
            'reason'     => 'required|string|max:500',
        ]);

        $year = \Carbon\Carbon::parse($validated['start_date'])->year;
        $leaveType = \App\Models\LeaveType::firstOrCreate(
            ['code' => 'ANNUAL'],
            [
                'name' => 'Annual Leave',
                'days_allowed' => 16,
                'is_paid' => true,
                'is_active' => true,
            ]
        );

        $balance = \App\Models\LeaveBalance::firstOrCreate(
            [
                'employee_id'   => $employee->id,
                'leave_type_id' => $leaveType->id,
                'year'          => $year,
            ],
            [
                'total_days'     => 16.0,
                'used_days'      => 0,
                'remaining_days' => 16.0,
            ]
        );

        // Deduct from balance
        $balance->updateBalance((float) $validated['days']);

        // Create approved LeaveRequest record for auditing
        \App\Models\LeaveRequest::create([
            'employee_id'   => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date'    => $validated['start_date'],
            'end_date'      => $validated['end_date'] ?? $validated['start_date'],
            'reason'        => $validated['reason'],
            'status'        => 'approved',
            'approved_by'   => auth()->id(),
            'approved_at'   => now(),
        ]);

        return back()->with('success', "Deducted {$validated['days']} leave days. Remaining available balance: {$balance->remaining_days} days.");
    }

    /**
     * Link / Update Biometric Device User ID for an employee and auto-sync punches.
     */
    public function updateDeviceId(Request $request, Employee $employee)
    {
        $request->validate([
            'device_user_id' => 'nullable|string|max:50',
        ]);

        $deviceId = $request->filled('device_user_id') ? trim($request->device_user_id) : null;
        $employee->update(['device_user_id' => $deviceId]);

        $syncedCount = 0;
        if ($deviceId) {
            $syncedCount = $this->syncPunchesForEmployee($employee);
        }

        return back()->with('success', "Biometric Device ID updated successfully for {$employee->full_name}." . ($syncedCount > 0 ? " Synced {$syncedCount} punch records." : ""));
    }

    /**
     * On-demand manual sync of all device punch logs for this employee.
     */
    public function syncDeviceAttendance(Request $request, Employee $employee)
    {
        if (empty($employee->device_user_id)) {
            return back()->with('error', "Cannot sync: No Biometric Device ID linked for {$employee->full_name}. Please link a Device ID first.");
        }

        $synced = $this->syncPunchesForEmployee($employee);

        return back()->with('success', "Biometric attendance sync complete for {$employee->full_name}. Synced/Updated {$synced} day(s) of attendance.");
    }

    /**
     * Helper to sync all punch logs for a single employee into the attendance table.
     */
    private function syncPunchesForEmployee(Employee $employee): int
    {
        $deviceId = trim((string) $employee->device_user_id);
        if (empty($deviceId)) return 0;

        $punches = DB::table('device_attendance_logs')
            ->where(function ($q) use ($deviceId, $employee) {
                $q->where('device_user_id', $deviceId)
                  ->orWhere('device_user_id', ltrim($deviceId, '0'))
                  ->orWhere('device_user_id', $employee->employee_code);
            })
            ->whereNotNull('punch_time')
            ->orderBy('punch_time', 'asc')
            ->get();

        if ($punches->isEmpty()) return 0;

        // Group punches by date (Y-m-d)
        $byDate = $punches->groupBy(function ($item) {
            return substr($item->punch_time, 0, 10);
        });

        $count = 0;
        foreach ($byDate as $date => $dayPunches) {
            $firstPunch = $dayPunches->first()->punch_time;
            $lastPunch  = $dayPunches->last()->punch_time;
            $sn         = $dayPunches->first()->device_sn ?: 'AF6P230860018';

            $checkInTime  = substr($firstPunch, 11, 8);
            $checkOutTime = ($lastPunch !== $firstPunch) ? substr($lastPunch, 11, 8) : null;

            $hoursWorked = null;
            if ($checkInTime && $checkOutTime) {
                $inSecs  = strtotime("{$date} {$checkInTime}");
                $outSecs = strtotime("{$date} {$checkOutTime}");
                $hoursWorked = $outSecs > $inSecs ? round(($outSecs - $inSecs) / 3600, 2) : null;
            }

            $existing = DB::table('attendance')
                ->where('employee_id', $employee->id)
                ->where('attendance_date', $date)
                ->first();

            if (!$existing) {
                DB::table('attendance')->insert([
                    'employee_id'         => $employee->id,
                    'attendance_date'     => $date,
                    'check_in'            => $checkInTime,
                    'check_out'           => $checkOutTime,
                    'hours_worked'        => $hoursWorked,
                    'status'              => 'present',
                    'source'              => 'biometric',
                    'biometric_device_id' => $sn,
                    'is_approved'         => true,
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]);
            } else {
                DB::table('attendance')
                    ->where('employee_id', $employee->id)
                    ->where('attendance_date', $date)
                    ->update([
                        'check_in'            => $existing->check_in ?: $checkInTime,
                        'check_out'           => $checkOutTime ?: $existing->check_out,
                        'hours_worked'        => $hoursWorked ?: $existing->hours_worked,
                        'source'              => 'biometric',
                        'biometric_device_id' => $sn ?: ($existing->biometric_device_id ?? 'AF6P230860018'),
                        'updated_at'          => now(),
                    ]);
            }

            // Mark logs as synced
            DB::table('device_attendance_logs')
                ->whereIn('id', $dayPunches->pluck('id'))
                ->update(['synced_at' => now()]);

            $count++;
        }

        return $count;
    }

    /**
     * Remove the specified employee from storage.
     */
    public function destroy(Employee $employee)
    {
        Gate::authorize('delete', $employee);
        
        $name = $employee->full_name;
        $code = $employee->employee_code;

        // If employee has a linked user account without global system roles, remove or decouple
        if ($employee->user_id) {
            $user = \App\Models\User::find($employee->user_id);
            if ($user && $user->roles()->count() === 0) {
                $user->delete();
            }
        }

        $employee->delete();
        
        return redirect()->route('employees.index')
            ->with('success', "Employee {$name} ({$code}) has been deleted successfully. If this employee is registered or added again later, fresh GM approval will be strictly required.");
    }
    /**
     * Display terminated / locked employee history.
     */
    public function history(Request $request)
    {
        Gate::authorize('viewAny', Employee::class);
        $this->ensureGuarantorAndRegistrationColumnsExist();
        $this->lockExpiredProbations();

        $query = Employee::with(['project', 'user'])
            ->where(function ($q) {
                $q->whereIn('status', ['terminated', 'suspended'])
                  ->orWhereNotNull('lock_reason');
            });

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('employee_code', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('department', 'like', "%{$search}%")
                  ->orWhere('tin_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('department')) {
            $query->where('department', $request->input('department'));
        }

        if ($request->filled('employment_type')) {
            $query->where('employment_type', $request->input('employment_type'));
        }

        $employees = $query->latest('updated_at')->paginate(20)->withQueryString();
        $departments = Employee::distinct()->whereNotNull('department')->pluck('department');

        return view('hr.employees.history', compact('employees', 'departments'));
    }

    /**
     * Renew or reactivate employee transition to Permanent or Contract.
     */
    public function renew(Request $request, Employee $employee)
    {
        Gate::authorize('update', $employee);

        $validated = $request->validate([
            'employment_type'   => 'required|in:permanent,contract,daily',
            'contract_end_date' => 'nullable|date',
            'tin_number'        => 'nullable|string|max:50',
            'guarantee_letter'  => $employee->guarantee_letter ? 'nullable|file|mimes:pdf,jpeg,png,jpg,webp|max:15360' : 'required|file|mimes:pdf,jpeg,png,jpg,webp|max:15360',
            'probation_completed' => 'nullable|boolean',
        ]);

        if ($request->hasFile('guarantee_letter')) {
            $validated['guarantee_letter'] = \App\Services\FileUploadService::upload($request->file('guarantee_letter'), 'guarantee_letters');
            $validated['guarantee_letter_submitted_at'] = now();
        }

        $validated['status'] = 'active';
        $validated['lock_reason'] = null;
        $validated['probation_completed'] = true; // explicitly marked completed/renewed

        $employee->update($validated);

        return redirect()->route('employees.show', $employee)
            ->with('success', "Employee {$employee->full_name} has been successfully renewed / activated!");
    }

    /**
     * System auto-lock routine for expired 45-day test periods.
     */
    public function lockExpiredProbations()
    {
        try {
            $now = now();
            // Find active employees whose 45 days test period has passed without guarantee letter or TIN
            $expiredEmployees = Employee::where('status', 'active')
                ->where('probation_completed', false)
                ->whereNotNull('date_of_joining')
                ->get();

            foreach ($expiredEmployees as $emp) {
                if ($emp->is_test_period_expired) {
                    $emp->update([
                        'status'      => 'terminated',
                        'lock_reason' => '45-Day Test Period Expired: Missing Guarantee Letter',
                    ]);
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Lock expired probations error: ' . $e->getMessage());
        }
    }

    /**
     * Ensure second guarantor, registration letters, probation, and contract columns exist in employees table.
     */
    private function ensureGuarantorAndRegistrationColumnsExist()
    {
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('employees')) {
                \Illuminate\Support\Facades\Schema::table('employees', function (\Illuminate\Database\Schema\Blueprint $table) {
                    if (!\Illuminate\Support\Facades\Schema::hasColumn('employees', 'guarantee_letter_2')) {
                        $table->string('guarantee_letter_2')->nullable();
                    }
                    if (!\Illuminate\Support\Facades\Schema::hasColumn('employees', 'guarantor_2_name')) {
                        $table->string('guarantor_2_name')->nullable();
                    }
                    if (!\Illuminate\Support\Facades\Schema::hasColumn('employees', 'guarantor_2_id_number')) {
                        $table->string('guarantor_2_id_number')->nullable();
                    }
                    if (!\Illuminate\Support\Facades\Schema::hasColumn('employees', 'guarantor_2_id_card')) {
                        $table->string('guarantor_2_id_card')->nullable();
                    }
                    if (!\Illuminate\Support\Facades\Schema::hasColumn('employees', 'guarantor_2_phone')) {
                        $table->string('guarantor_2_phone')->nullable();
                    }
                    if (!\Illuminate\Support\Facades\Schema::hasColumn('employees', 'registration_letters')) {
                        $table->text('registration_letters')->nullable();
                    }
                    if (!\Illuminate\Support\Facades\Schema::hasColumn('employees', 'contract_end_date')) {
                        $table->date('contract_end_date')->nullable();
                    }
                    if (!\Illuminate\Support\Facades\Schema::hasColumn('employees', 'contract_duration_type')) {
                        $table->string('contract_duration_type', 40)->default('fixed_date');
                    }
                    if (!\Illuminate\Support\Facades\Schema::hasColumn('employees', 'is_project_based')) {
                        $table->boolean('is_project_based')->default(false);
                    }
                    if (!\Illuminate\Support\Facades\Schema::hasColumn('employees', 'tin_number')) {
                        $table->string('tin_number', 50)->nullable();
                    }

                    if (!\Illuminate\Support\Facades\Schema::hasColumn('employees', 'probation_ends_at')) {
                        $table->date('probation_ends_at')->nullable();
                    }
                    if (!\Illuminate\Support\Facades\Schema::hasColumn('employees', 'probation_completed')) {
                        $table->boolean('probation_completed')->default(false);
                    }
                    if (!\Illuminate\Support\Facades\Schema::hasColumn('employees', 'lock_reason')) {
                        $table->string('lock_reason')->nullable();
                    }
                });
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Employee table columns auto-heal error: ' . $e->getMessage());
        }
    }
}



