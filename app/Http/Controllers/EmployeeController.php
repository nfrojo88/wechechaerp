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
        }

        $employees = $query->paginate(20)->withQueryString();

        // Counts for tabs/badges
        $counts = [
            'all'      => Employee::count(),
            'approved' => Employee::where('is_approved_by_gm', true)->count(),
            'pending'  => Employee::where('is_approved_by_gm', false)->where(function($q) {
                            $q->whereNull('gm_approval_status')->orWhere('gm_approval_status', '!=', 'rejected');
                          })->count(),
            'rejected' => Employee::where('gm_approval_status', 'rejected')->count(),
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
            'date_of_joining'               => 'required|date',
            'basic_salary'                  => 'required|numeric|min:0',
            'transport_allowance'           => 'nullable|numeric|min:0',
            'house_allowance'               => 'nullable|numeric|min:0',
            'position_allowance'            => 'nullable|numeric|min:0',
            'contract_type'                 => 'nullable|string',
            'status'                        => 'required|in:active,suspended,terminated',
            'bank_name'                     => 'nullable|string|max:255',
            'account_number'                => 'nullable|string|max:50',
            'notes'                         => 'nullable|string',
            'guarantee_letter'              => 'nullable|file|mimes:pdf,jpeg,png,jpg,webp|max:15360',
            'fixed_asset_units'             => 'nullable|array',
            'fixed_asset_units.*'           => 'nullable|exists:fixed_asset_units,id',
            'education'                     => 'nullable|array',
            'education.*.degree_level'      => 'nullable|string',
            'education.*.field_of_study'    => 'nullable|string',
            'education.*.institution_name'  => 'nullable|string',
            'education.*.certificate_photo' => 'nullable|file|mimes:pdf,jpeg,png,jpg,webp|max:15360',
            'experience'                    => 'nullable|array',
            'experience.*.job_title'        => 'nullable|string',
            'experience.*.company_name'     => 'nullable|string',
            'experience.*.start_date'       => 'nullable|date',
            'experience.*.license_document' => 'nullable|file|mimes:pdf,jpeg,png,jpg,webp|max:15360',
            'device_user_id'                => 'nullable|string|max:100',
        ]);

        // Apply defaults
        $validated['employment_type']     = $validated['employment_type'] ?? 'permanent';
        $validated['status']              = $validated['status'] ?? 'active';
        $validated['basic_salary']        = $validated['basic_salary'] ?? 0;
        $validated['transport_allowance'] = $validated['transport_allowance'] ?? 0;
        $validated['house_allowance']     = $validated['house_allowance'] ?? 0;
        $validated['position_allowance']  = $validated['position_allowance'] ?? 0;

        // Handle guarantee letter upload
        if ($request->hasFile('guarantee_letter')) {
            $letterPath = \App\Services\FileUploadService::upload($request->file('guarantee_letter'), 'guarantee_letters');
            $validated['guarantee_letter'] = $letterPath;
            $validated['guarantee_letter_submitted_at'] = now();
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
            $employeeData = \Illuminate\Support\Arr::except($validated, ['fixed_asset_units', 'education', 'experience']);
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
                'employee_id'      => $employee->id,
                'job_title'        => !empty($experience['job_title'])    ? $experience['job_title']    : 'N/A',
                'company_name'     => !empty($experience['company_name']) ? $experience['company_name'] : 'N/A',
                'location'         => $experience['location']         ?? null,
                'start_date'       => $expStartDate,
                'end_date'         => $expEndDate,
                'is_current'       => $isCurrent,
                'responsibilities' => $experience['responsibilities'] ?? null,
                'reference_name'   => $experience['reference_name']   ?? null,
                'reference_phone'  => $experience['reference_phone']  ?? null,
                'license_document' => $licensePath,
                'license_number'   => $experience['license_number']   ?? null,
                'license_expiry'   => $expLicExpiry,
            ]);
        }
    }

    public function show(Employee $employee)
    {
        Gate::authorize('view', $employee);
        $employee->load([
            'project', 
            'payrolls' => fn($q) => $q->latest()->limit(12),
            'education',
            'experience',
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
        $employee->load([
            'project',
            'education',
            'experience',
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
            'guarantee_letter'     => 'nullable|file|mimes:pdf,jpeg,png,jpg|max:10240',
            'fixed_asset_units'    => 'nullable|array',
            'education'            => 'nullable|array',
            'experience'           => 'nullable|array',
        ]);

        // Handle guarantee letter upload if provided
        if ($request->hasFile('guarantee_letter')) {
            $letterPath = \App\Services\FileUploadService::upload($request->file('guarantee_letter'), 'guarantee_letters');
            $validated['guarantee_letter'] = $letterPath;
            $validated['guarantee_letter_submitted_at'] = now();
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
        $employeeData = \Illuminate\Support\Arr::except($validated, ['fixed_asset_units', 'education', 'experience']);
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

        // ── Sync Experience Records ─────────────────────────────────────────
        if ($request->has('experience') && is_array($request->experience)) {
            $submittedExpIds = [];
            foreach ($request->experience as $index => $expData) {
                if (empty($expData['job_title']) && empty($expData['company_name'])) {
                    continue;
                }

                $licenseDocPath = null;
                if ($request->hasFile("experience.{$index}.license_document")) {
                    $licenseDocPath = \App\Services\FileUploadService::upload($request->file("experience.{$index}.license_document"), 'employee_licenses');
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
                    'license_number'   => $expData['license_number']   ?? null,
                    'license_expiry'   => !empty($expData['license_expiry']) ? $expData['license_expiry'] : null,
                ];
                if ($licenseDocPath) {
                    $expPayload['license_document'] = $licenseDocPath;
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
}
