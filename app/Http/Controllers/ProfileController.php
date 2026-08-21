<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    /**
     * Show the profile view with full employee details.
     */
    public function edit()
    {
        $user = Auth::user();
        $employee = $user->employee;

        if ($employee) {
            $employee->load([
                'project',
                'education',
                'experience',
                'assets.product',
                'assignedFixedAssets.parentAsset',
                'contracts',
                'ratings',
                'gmApprovedBy',
                'gmRejectedBy',
            ]);
        }

        // Calculate total experience duration
        $totalExperienceYears = 0;
        $totalExperienceMonths = 0;
        if ($employee && $employee->experience) {
            foreach ($employee->experience as $exp) {
                if ($exp->start_date) {
                    $end = $exp->end_date ?? now();
                    $diffYears = $exp->start_date->diffInYears($end);
                    $diffMonths = $exp->start_date->diffInMonths($end) % 12;
                    $totalExperienceYears += $diffYears;
                    $totalExperienceMonths += $diffMonths;
                }
            }
            if ($totalExperienceMonths >= 12) {
                $totalExperienceYears += intdiv($totalExperienceMonths, 12);
                $totalExperienceMonths = $totalExperienceMonths % 12;
            }
        }

        // Load this employee's maintenance requests
        $maintenanceRequests = collect();
        if ($employee) {
            $maintenanceRequests = MaintenanceRequest::where('employee_id', $employee->id)
                ->latest()
                ->limit(10)
                ->get();
        }

        return view('profile.edit', compact('user', 'employee', 'totalExperienceYears', 'totalExperienceMonths', 'maintenanceRequests'));
    }

    /**
     * Update the user profile details.
     */
    public function update(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'phone' => 'nullable|string|max:30',
        ]);

        if ($request->filled('phone')) {
            if ($user->employee) {
                $user->employee->update(['phone' => $validated['phone']]);
            }
        }

        return redirect()->route('profile.edit')->with('success', 'Profile contact information updated successfully.');
    }

    /**
     * Update the user password.
     */
    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'password'         => ['required', 'string', Password::min(8)->mixedCase()->numbers(), 'confirmed'],
        ]);

        $user = Auth::user();

        if (!Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'The current password you entered is incorrect.'])->withInput();
        }

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()->route('profile.edit')->with('success', 'Your password has been changed successfully.');
    }
}
