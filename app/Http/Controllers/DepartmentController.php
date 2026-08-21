<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index()
    {
        $departments = Department::with(['head', 'employees'])->get();
        return view('hr.departments.index', compact('departments'));
    }

    public function create()
    {
        $employees = Employee::where('status', 'active')->orderBy('full_name')->get();
        return view('hr.departments.create', compact('employees'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'code'        => 'required|string|max:50|unique:departments',
            'head_id'     => 'nullable|exists:employees,id',
            'description' => 'nullable|string',
        ]);

        Department::create($data);
        return redirect()->route('departments.index')->with('success', 'Department created.');
    }

    public function edit(Department $department)
    {
        $employees = Employee::where('status', 'active')->orderBy('full_name')->get();
        return view('hr.departments.edit', compact('department', 'employees'));
    }

    public function update(Request $request, Department $department)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'code'        => 'required|string|max:50|unique:departments,code,' . $department->id,
            'head_id'     => 'nullable|exists:employees,id',
            'description' => 'nullable|string',
        ]);

        $data['is_active'] = $request->boolean('is_active');

        $department->update($data);
        return redirect()->route('departments.index')->with('success', 'Department updated successfully.');
    }
}
