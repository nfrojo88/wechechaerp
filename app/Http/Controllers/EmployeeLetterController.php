<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeLetter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class EmployeeLetterController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->ensureLettersTableExists();
    }

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

    /**
     * Display a listing of official employee letters and history records.
     */
    public function index(Request $request)
    {
        $this->ensureLettersTableExists();
        $query = EmployeeLetter::with(['employee.project', 'issuer'])->latest('issued_date');

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->filled('letter_type')) {
            $query->where('letter_type', $request->letter_type);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('reference_number', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%")
                  ->orWhereHas('employee', function ($eq) use ($search) {
                      $eq->where('full_name', 'like', "%{$search}%")
                         ->orWhere('employee_code', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('issued_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('issued_date', '<=', $request->date_to);
        }

        $letters = $query->paginate(20)->withQueryString();

        // Calculate KPI Counts
        $kpi = [
            'total'          => EmployeeLetter::count(),
            'appreciation'   => EmployeeLetter::whereIn('letter_type', ['thanks_letter', 'appreciation', 'promotion'])->count(),
            'warnings'       => EmployeeLetter::whereIn('letter_type', ['first_warning', 'second_warning', 'show_cause'])->count(),
            'final_warnings' => EmployeeLetter::whereIn('letter_type', ['final_warning', 'suspension', 'termination'])->count(),
        ];

        $employees = Employee::orderBy('full_name')->get(['id', 'full_name', 'employee_code', 'department']);

        return view('hr.employee-letters.index', compact('letters', 'kpi', 'employees'));
    }

    /**
     * Show the form for issuing a new letter.
     */
    public function create(Request $request)
    {
        $employees = Employee::orderBy('full_name')->get();
        $selectedEmployeeId = $request->input('employee_id');
        $defaultType = $request->input('type', 'thanks_letter');

        return view('hr.employee-letters.create', compact('employees', 'selectedEmployeeId', 'defaultType'));
    }

    /**
     * Store a newly created letter in storage.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'employee_id'            => 'required|exists:employees,id',
                'letter_type'            => 'required|string|max:50',
                'title'                  => 'required|string|max:255',
                'content'                => 'required|string',
                'issued_date'            => 'required|date',
                'effective_date'         => 'nullable|date',
                'reference_number'       => 'nullable|string|max:100|unique:employee_letters,reference_number',
                'action_required'        => 'nullable|string',
                'acknowledgement_status' => 'nullable|string|in:pending,acknowledged,refused_to_sign',
                'attachment'             => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
            ], [
                'employee_id.required'   => 'Please select an employee to receive this official letter.',
                'employee_id.exists'     => 'The selected employee record does not exist.',
                'letter_type.required'   => 'Please choose a letter type.',
                'title.required'         => 'The letter subject/title is required.',
                'content.required'       => 'Letter content cannot be empty.',
                'issued_date.required'   => 'Date of issuance is required.',
            ]);

            if (empty($validated['reference_number'])) {
                $prefix = match ($validated['letter_type']) {
                    'thanks_letter', 'appreciation' => 'LTR-APPR',
                    'guarantee_letter'              => 'LTR-GUR',
                    'power_of_attorney'             => 'LTR-POA',
                    'application_letter'            => 'LTR-APPL',
                    'first_warning'                 => 'LTR-WARN1',
                    'second_warning'                => 'LTR-WARN2',
                    'final_warning'                 => 'LTR-FWN',
                    'show_cause'                    => 'LTR-SCQ',
                    'suspension'                    => 'LTR-SUSP',
                    'termination'                   => 'LTR-TERM',
                    default                         => 'LTR-GEN',
                };

                $seq = EmployeeLetter::whereDate('created_at', today())->count() + 1;
                do {
                    $candidate = $prefix . '-' . date('Ymd') . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
                    $seq++;
                } while (EmployeeLetter::where('reference_number', $candidate)->exists());

                $validated['reference_number'] = $candidate;
            }

            // Determine severity automatically
            $validated['severity'] = match ($validated['letter_type']) {
                'thanks_letter', 'appreciation', 'promotion', 'power_of_attorney' => 'positive',
                'first_warning', 'show_cause'                                    => 'warning',
                'second_warning', 'final_warning', 'suspension', 'termination'   => 'critical',
                default                                                          => 'info',
            };

            if ($request->hasFile('attachment')) {
                $path = $request->file('attachment')->store('employee_letters', 'public');
                $validated['attachment_path'] = $path;
            }

            $validated['issued_by'] = Auth::id();
            $validated['acknowledgement_status'] = $validated['acknowledgement_status'] ?? 'acknowledged';
            if ($validated['acknowledgement_status'] === 'acknowledged') {
                $validated['acknowledged_at'] = now();
            }

            $letter = EmployeeLetter::create($validated);

            return redirect()->route('employee-letters.show', $letter)->with('success', 'Official employee letter has been issued and recorded successfully.');
        } catch (\Illuminate\Validation\ValidationException $ve) {
            throw $ve;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Employee letter save failed: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            return back()->withInput()->with('error', 'Failed to save letter: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified letter.
     */
    public function show(EmployeeLetter $employeeLetter)
    {
        $employeeLetter->load(['employee.project', 'issuer']);
        return view('hr.employee-letters.show', compact('employeeLetter'));
    }

    /**
     * Printable view of the letter on official letterhead.
     */
    public function print(EmployeeLetter $employeeLetter)
    {
        $employeeLetter->load(['employee.project', 'issuer']);
        return view('hr.employee-letters.print', compact('employeeLetter'));
    }

    /**
     * Show the form for editing the letter.
     */
    public function edit(EmployeeLetter $employeeLetter)
    {
        $employees = Employee::orderBy('full_name')->get();
        return view('hr.employee-letters.edit', compact('employeeLetter', 'employees'));
    }

    /**
     * Update the letter.
     */
    public function update(Request $request, EmployeeLetter $employeeLetter)
    {
        $validated = $request->validate([
            'employee_id'            => 'required|exists:employees,id',
            'letter_type'            => 'required|string|max:50',
            'title'                  => 'required|string|max:255',
            'content'                => 'required|string',
            'issued_date'            => 'required|date',
            'effective_date'         => 'nullable|date',
            'reference_number'       => 'nullable|string|max:100|unique:employee_letters,reference_number,' . $employeeLetter->id,
            'action_required'        => 'nullable|string',
            'acknowledgement_status' => 'nullable|string|in:pending,acknowledged,refused_to_sign',
            'attachment'             => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
        ]);

        if ($request->hasFile('attachment')) {
            if ($employeeLetter->attachment_path && Storage::disk('public')->exists($employeeLetter->attachment_path)) {
                Storage::disk('public')->delete($employeeLetter->attachment_path);
            }
            $validated['attachment_path'] = $request->file('attachment')->store('employee_letters', 'public');
        }

        $employeeLetter->update($validated);

        return redirect()->route('employee-letters.show', $employeeLetter)->with('success', 'Employee letter updated successfully.');
    }

    /**
     * Remove the letter.
     */
    public function destroy(EmployeeLetter $employeeLetter)
    {
        if ($employeeLetter->attachment_path && Storage::disk('public')->exists($employeeLetter->attachment_path)) {
            Storage::disk('public')->delete($employeeLetter->attachment_path);
        }

        $employeeLetter->delete();

        return redirect()->route('employee-letters.index')->with('success', 'Employee letter record deleted successfully.');
    }
}
