<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\EmployeeRatingController;
use App\Http\Controllers\Admin\RoleAssignmentController;
use App\Http\Controllers\Admin\AdminTicketController;
use App\Http\Controllers\Admin\GeneralServiceController;
use App\Http\Controllers\SupportTicketController;
use App\Http\Controllers\MaintenanceRequestController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
});

// Media & Uploads Stream Routes (Universal fallback to prevent 404s on images/documents)
Route::get('/uploads/{path}', [App\Http\Controllers\FileStreamController::class, 'streamUpload'])->where('path', '.*')->name('media.upload');
Route::get('/storage/{path}', [App\Http\Controllers\FileStreamController::class, 'streamStorage'])->where('path', '.*')->name('media.storage');

// Git pull deployment route (triggers server-side git pull from GitHub)
Route::get('/deploy-from-github', function () {
    $output = [];
    $return = 0;

    // Run git pull with auto-discard of local server changes
    exec('cd ' . base_path() . ' && git fetch origin main 2>&1 && git reset --hard origin/main 2>&1', $output, $return);
    $pullResult = implode("\n", $output);

    // Clear caches
    $cacheOutput = [];
    exec('cd ' . base_path() . ' && php artisan config:clear 2>&1 && php artisan route:clear 2>&1 && php artisan view:clear 2>&1', $cacheOutput);
    $cacheResult = implode("\n", $cacheOutput);

    $color = ($return === 0) ? 'green' : 'red';
    $icon  = ($return === 0) ? '✅' : '❌';

    return "<h2 style='font-family:sans-serif;color:{$color}'>{$icon} Git Pull Result (exit: {$return})</h2>"
         . "<pre style='background:#f1f5f9;padding:16px;border-radius:8px'>" . htmlspecialchars($pullResult) . "</pre>"
         . "<h3 style='font-family:sans-serif'>Cache Clear Output:</h3>"
         . "<pre style='background:#f1f5f9;padding:16px;border-radius:8px'>" . htmlspecialchars($cacheResult) . "</pre>"
         . "<p><a href='/run-migrations' style='color:blue;'>→ Run Migrations</a> | "
         . "<a href='/fix-storage-link' style='color:green;'>→ Fix Storage Link (Fix Image 404)</a></p>";
});

// One-click migration route for all pending migrations
Route::get('/run-migrations', function () {
    try {
        // Run ALL pending migrations with --force (for production)
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        $output = \Illuminate\Support\Facades\Artisan::output();

        // Check current columns in employees table
        $columns = \Illuminate\Support\Facades\Schema::getColumnListing('employees');
        $gmCols = array_filter($columns, fn($c) => str_contains($c, 'gm_'));

        return "<div style='font-family:sans-serif;max-width:900px;margin:40px auto;padding:0;border-radius:14px;overflow:hidden;box-shadow:0 8px 24px rgba(0,0,0,0.12);'>"
             . "<div style='background:linear-gradient(135deg,#065f46,#10b981);padding:28px 32px;color:#fff;'>"
             . "<h2 style='margin:0;font-size:22px;'>✅ Migrations Executed Successfully</h2>"
             . "<p style='margin:6px 0 0;opacity:.85;'>All pending migrations have been applied to the live database.</p>"
             . "</div>"
             . "<div style='padding:28px 32px;background:#fff;'>"
             . "<h3 style='color:#374151;font-size:15px;margin-top:0;'>📋 Migration Output:</h3>"
             . "<pre style='background:#f8fafc;border:1px solid #e2e8f0;padding:16px;border-radius:8px;overflow-x:auto;color:#1e293b;font-size:13px;line-height:1.6;'>"
             . (trim($output) !== '' ? htmlspecialchars($output) : '  Nothing to migrate — all migrations already applied.')
             . "</pre>"
             . "<h3 style='color:#374151;font-size:15px;'>🗄️ GM Rejection Columns in <code>employees</code> table:</h3>"
             . "<pre style='background:#f0fdf4;border:1px solid #bbf7d0;padding:16px;border-radius:8px;color:#15803d;font-size:13px;'>"
             . (count($gmCols) > 0
                ? '  ✅ Found: ' . implode(', ', $gmCols)
                : '  ❌ NOT FOUND — migration may not have run correctly. See error above.')
             . "</pre>"
             . "<div style='margin-top:24px;display:flex;gap:12px;flex-wrap:wrap;'>"
             . "<a href='/dashboard' style='background:#2563eb;color:#fff;padding:10px 20px;text-decoration:none;border-radius:6px;font-weight:bold;font-size:14px;'>🏠 Go to Dashboard</a>"
             . "<a href='/run-migrations' style='background:#10b981;color:#fff;padding:10px 20px;text-decoration:none;border-radius:6px;font-weight:bold;font-size:14px;'>🔄 Run Again</a>"
             . "<a href='/deploy-from-github' style='background:#7c3aed;color:#fff;padding:10px 20px;text-decoration:none;border-radius:6px;font-weight:bold;font-size:14px;'>🚀 Git Pull + Deploy</a>"
             . "</div>"
             . "</div></div>";
    } catch (\Exception $e) {
        return "<div style='font-family:sans-serif;max-width:900px;margin:40px auto;border-radius:14px;overflow:hidden;box-shadow:0 8px 24px rgba(0,0,0,0.12);'>"
             . "<div style='background:linear-gradient(135deg,#991b1b,#ef4444);padding:28px 32px;color:#fff;'>"
             . "<h2 style='margin:0;'>❌ Migration Failed</h2>"
             . "<p style='margin:6px 0 0;opacity:.85;'>An error occurred while running migrations.</p>"
             . "</div>"
             . "<div style='padding:28px 32px;background:#fff;'>"
             . "<pre style='background:#fef2f2;border:1px solid #fecaca;padding:16px;border-radius:8px;color:#991b1b;font-size:13px;overflow-x:auto;'>" . htmlspecialchars($e->getMessage()) . "</pre>"
             . "<a href='/run-migrations' style='display:inline-block;margin-top:16px;background:#ef4444;color:#fff;padding:10px 20px;text-decoration:none;border-radius:6px;font-weight:bold;'>Try Again</a>"
             . "</div></div>";
    }
});

Route::get('/migrate', function() {
    return redirect('/run-migrations');
});

// Restore employee credentials & lock for GM approval
Route::get('/reset-employees-for-gm-approval', function () {
    try {
        // Exempt list: General Admin and HR Officer
        $exemptCodes  = ['EMP-01', 'EMP-25', 'EMP-1', 'EMP-001', 'EMP-025'];
        $exemptEmails = ['wondeseyum573@gmail.com', 'natnael@wechecha.com'];

        $determineRole = function($dept, $title) {
            $str = strtolower(($dept ?? '') . ' ' . ($title ?? ''));
            if (str_contains($str, 'admin') || str_contains($str, 'system')) return 'admin';
            if (str_contains($str, 'general manager') || str_contains($str, 'gm')) return 'gm';
            if (str_contains($str, 'site') || str_contains($str, 'engineer')) return 'site_engineer';
            if (str_contains($str, 'foreman')) return 'foreman';
            if (str_contains($str, 'store manager') || str_contains($str, 'store-manager')) return 'store_manager';
            if (str_contains($str, 'store') || str_contains($str, 'warehouse')) return 'store_keeper';
            if (str_contains($str, 'finance') || str_contains($str, 'account')) return 'finance';
            if (str_contains($str, 'purchase') || str_contains($str, 'procurement')) return 'purchase';
            if (str_contains($str, 'hr') || str_contains($str, 'human')) return 'hr';
            if (str_contains($str, 'planning')) return 'planning';
            if (str_contains($str, 'coord')) return 'coordinator';
            if (str_contains($str, 'contract')) return 'contract_admin';
            if (str_contains($str, 'audit')) return 'audit_team';
            if (str_contains($str, 'secret')) return 'secretary';
            return 'site_engineer';
        };

        $allEmployees = \App\Models\Employee::all();
        $exempted = [];
        $lockedList = [];
        $usersRestoredCount = 0;

        foreach ($allEmployees as $emp) {
            $code  = strtoupper(trim($emp->employee_code ?? ''));
            $email = strtolower(trim($emp->email ?? ''));

            $isExempt = in_array($code, $exemptCodes) || in_array($email, $exemptEmails);

            // 1. Locate or restore the User login account
            $user = null;
            if ($emp->user_id) {
                $user = \App\Models\User::find($emp->user_id);
            }

            if (!$user && !empty($emp->email)) {
                $user = \App\Models\User::where('email', trim($emp->email))->first();
            }

            if (!$user && !empty($emp->phone)) {
                $cleanDigits = preg_replace('/[^\d]/', '', $emp->phone);
                if (!empty($cleanDigits) && \Illuminate\Support\Facades\Schema::hasColumn('users', 'phone')) {
                    $user = \App\Models\User::where('phone', 'like', "%{$cleanDigits}%")->first();
                }
            }

            if (!$user) {
                $cleanCode = strtolower(str_replace(['-', ' ', '_'], '', $emp->employee_code ?: ('emp' . $emp->id)));
                $generatedEmail = !empty($emp->email) ? trim($emp->email) : ($cleanCode . '@wechecha.com');

                $user = \App\Models\User::where('email', $generatedEmail)->first();
                if (!$user) {
                    $user = \App\Models\User::create([
                        'name'     => $emp->full_name ?: ('Employee ' . $emp->employee_code),
                        'email'    => $generatedEmail,
                        'password' => \Illuminate\Support\Facades\Hash::make('password'),
                        'phone'    => $emp->phone ?? null,
                    ]);
                    $usersRestoredCount++;
                }
            }

            if ($user && ($user->hasAnyRole(['admin', 'global_admin', 'gm']) || in_array(strtolower($user->email), $exemptEmails))) {
                $isExempt = true;
            }

            // Assign system role to user if none exists
            if ($user && $user->roles()->count() === 0) {
                $roleName = $determineRole($emp->department, $emp->role_title);
                try {
                    $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => $roleName]);
                    $user->assignRole($role);
                } catch (\Throwable $e) {}
            }

            // Link employee to user
            $emp->user_id = $user->id;

            if ($isExempt) {
                // Ensure admin accounts remain approved and active
                $emp->is_approved_by_gm   = true;
                $emp->gm_approval_status  = 'approved';
                $emp->gm_approved_at      = $emp->gm_approved_at ?? now();
                $emp->save();

                $exempted[] = [
                    'code'  => $emp->employee_code,
                    'name'  => $emp->full_name,
                    'role'  => $emp->role_title ?: $emp->department,
                    'email' => $user->email,
                ];
            } else {
                // Lock employee for GM approval while preserving login account
                $emp->is_approved_by_gm   = false;
                $emp->gm_approval_status  = 'pending';
                $emp->gm_approved_at      = null;
                $emp->gm_approved_by      = null;
                $emp->gm_rejection_reason = null;
                $emp->gm_rejected_at      = null;
                $emp->gm_rejected_by      = null;
                $emp->save();

                $lockedList[] = [
                    'code'   => $emp->employee_code,
                    'name'   => $emp->full_name,
                    'dept'   => $emp->department,
                    'role'   => $emp->role_title ?: 'Employee',
                    'email'  => $user->email,
                    'phone'  => $emp->phone ?: 'N/A',
                ];
            }
        }

        // Generate response HTML
        $exemptRows = '';
        foreach ($exempted as $ex) {
            $exemptRows .= "<tr style='border-bottom:1px solid #e2e8f0;background:#f0fdf4;'>
                <td style='padding:10px 14px;font-weight:bold;color:#15803d;'>{$ex['code']}</td>
                <td style='padding:10px 14px;font-weight:bold;color:#0f172a;'>{$ex['name']}</td>
                <td style='padding:10px 14px;color:#475569;'>{$ex['role']}</td>
                <td style='padding:10px 14px;color:#0369a1;'><code>{$ex['email']}</code></td>
                <td style='padding:10px 14px;color:#15803d;'><span style='background:#dcfce7;color:#166534;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:bold;'>🛡️ Admin Approved</span></td>
            </tr>";
        }

        $lockedRows = '';
        foreach ($lockedList as $lk) {
            $lockedRows .= "<tr style='border-bottom:1px solid #f1f5f9;'>
                <td style='padding:10px 14px;font-family:monospace;font-weight:bold;color:#0f172a;'>{$lk['code']}</td>
                <td style='padding:10px 14px;font-weight:600;color:#1e293b;'>{$lk['name']}</td>
                <td style='padding:10px 14px;color:#64748b;'>{$lk['dept']} / {$lk['role']}</td>
                <td style='padding:10px 14px;color:#0369a1;'><code>{$lk['email']}</code></td>
                <td style='padding:10px 14px;color:#b45309;'><span style='background:#fef3c7;color:#92400e;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:bold;'>⏳ Locked (Awaiting GM Approval)</span></td>
            </tr>";
        }

        $exemptCount = count($exempted);
        $lockedCount = count($lockedList);

        return "<div style='font-family:sans-serif;max-width:980px;margin:40px auto;border-radius:16px;overflow:hidden;box-shadow:0 12px 36px rgba(0,0,0,0.12);background:#fff;'>"
             . "<div style='background:linear-gradient(135deg,#0f172a,#1e293b);padding:32px;color:#fff;'>"
             . "<div style='display:flex;align-items:center;gap:12px;'>"
             . "<div style='background:rgba(245,158,11,0.2);color:#f59e0b;padding:12px;border-radius:12px;font-size:24px;'>🔑</div>"
             . "<div>"
             . "<h2 style='margin:0;font-size:22px;color:#fff;'>Employee Credentials Restored & Locked for GM Approval</h2>"
             . "<p style='margin:6px 0 0;color:#94a3b8;font-size:14px;'>All employee login accounts have been linked and preserved with password: <code>password</code>. Employees remain <strong>Locked for GM Approval</strong>.</p>"
             . "</div>"
             . "</div>"
             . "</div>"
             . "<div style='padding:32px;'>"
             . "<div style='display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:16px;margin-bottom:24px;'>"
             . "<div style='background:#fef3c7;border:1px solid #fde68a;padding:16px;border-radius:10px;text-align:center;'><div style='font-size:24px;font-weight:bold;color:#92400e;'>{$lockedCount}</div><small style='color:#92400e;font-weight:600;'>Employees Locked for GM Approval</small></div>"
             . "<div style='background:#f0fdf4;border:1px solid #bbf7d0;padding:16px;border-radius:10px;text-align:center;'><div style='font-size:24px;font-weight:bold;color:#15803d;'>{$exemptCount}</div><small style='color:#15803d;font-weight:600;'>Admin Accounts Approved</small></div>"
             . "<div style='background:#f0f9ff;border:1px solid #bae6fd;padding:16px;border-radius:10px;text-align:center;'><div style='font-size:24px;font-weight:bold;color:#0284c7;'>{$usersRestoredCount}</div><small style='color:#0284c7;font-weight:600;'>Login Accounts Restored</small></div>"
             . "</div>"
             . "<h4 style='color:#0f172a;margin-bottom:8px;'>🛡️ Administrator Accounts:</h4>"
             . "<table style='width:100%;border-collapse:collapse;margin-bottom:24px;font-size:13px;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;'>"
             . "<thead><tr style='background:#f8fafc;text-align:left;'><th style='padding:10px 14px;'>Code</th><th style='padding:10px 14px;'>Name</th><th style='padding:10px 14px;'>Role</th><th style='padding:10px 14px;'>Login Email</th><th style='padding:10px 14px;'>Status</th></tr></thead>"
             . "<tbody>{$exemptRows}</tbody>"
             . "</table>"
             . "<h4 style='color:#0f172a;margin-bottom:8px;'>⏳ Locked Employees with Restored Login Credentials:</h4>"
             . "<table style='width:100%;border-collapse:collapse;margin-bottom:28px;font-size:13px;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;'>"
             . "<thead><tr style='background:#f8fafc;text-align:left;'><th style='padding:10px 14px;'>Code</th><th style='padding:10px 14px;'>Name</th><th style='padding:10px 14px;'>Department / Role</th><th style='padding:10px 14px;'>Login Email</th><th style='padding:10px 14px;'>Status</th></tr></thead>"
             . "<tbody>{$lockedRows}</tbody>"
             . "</table>"
             . "<div style='display:flex;gap:12px;flex-wrap:wrap;'>"
             . "<a href='/employees' style='background:#2563eb;color:#fff;padding:12px 24px;text-decoration:none;border-radius:8px;font-weight:bold;font-size:14px;'>👥 View Employee List</a>"
             . "<a href='/login' style='background:#10b981;color:#fff;padding:12px 24px;text-decoration:none;border-radius:8px;font-weight:bold;font-size:14px;'>🔑 Go to Login</a>"
             . "<a href='/dashboard' style='background:#0f172a;color:#fff;padding:12px 24px;text-decoration:none;border-radius:8px;font-weight:bold;font-size:14px;'>🏠 Dashboard</a>"
             . "</div>"
             . "</div>"
             . "</div>";

    } catch (\Exception $e) {
        return "<div style='font-family:sans-serif;max-width:800px;margin:40px auto;padding:24px;border-radius:12px;background:#fee2e2;color:#991b1b;border:1px solid #fecaca;'>"
             . "<h3 style='margin-top:0;'>❌ Error during employee credential restore:</h3>"
             . "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>"
             . "</div>";
    }
});

// Run ONLY the GM rejection migration (safe - skips if columns exist)
Route::get('/run-gm-migration', function () {
    try {
        \Illuminate\Support\Facades\Artisan::call('migrate', [
            '--path'  => 'database/migrations/2026_08_19_000001_add_gm_rejection_fields_to_employees_table.php',
            '--force' => true,
        ]);
        $output = \Illuminate\Support\Facades\Artisan::output();

        // Verify columns exist
        $checks = [
            'gm_approval_status'  => \Illuminate\Support\Facades\Schema::hasColumn('employees', 'gm_approval_status'),
            'gm_rejection_reason' => \Illuminate\Support\Facades\Schema::hasColumn('employees', 'gm_rejection_reason'),
            'gm_rejected_at'      => \Illuminate\Support\Facades\Schema::hasColumn('employees', 'gm_rejected_at'),
            'gm_rejected_by'      => \Illuminate\Support\Facades\Schema::hasColumn('employees', 'gm_rejected_by'),
        ];

        $rows = '';
        foreach ($checks as $col => $exists) {
            $icon  = $exists ? '✅' : '❌';
            $color = $exists ? '#15803d' : '#dc2626';
            $rows .= "<tr><td style='padding:8px 12px;font-family:monospace;color:#374151;'>{$col}</td><td style='padding:8px 12px;color:{$color};font-weight:bold;'>{$icon} " . ($exists ? 'EXISTS in DB' : 'MISSING!') . "</td></tr>";
        }

        $allOk = array_sum($checks) === count($checks);
        $headerBg = $allOk ? 'linear-gradient(135deg,#065f46,#10b981)' : 'linear-gradient(135deg,#92400e,#f59e0b)';
        $headerTitle = $allOk ? '✅ GM Rejection Migration Applied!' : '⚠️ Partial Migration — Some Columns Missing';

        return "<div style='font-family:sans-serif;max-width:800px;margin:40px auto;border-radius:14px;overflow:hidden;box-shadow:0 8px 24px rgba(0,0,0,0.12);'>"
             . "<div style='background:{$headerBg};padding:28px 32px;color:#fff;'>"
             . "<h2 style='margin:0;font-size:20px;'>{$headerTitle}</h2>"
             . "<p style='margin:6px 0 0;opacity:.85;'>Migration: <code>2026_08_19_000001_add_gm_rejection_fields_to_employees_table</code></p>"
             . "</div>"
             . "<div style='padding:28px 32px;background:#fff;'>"
             . "<h3 style='color:#374151;font-size:15px;margin-top:0;'>📋 Output:</h3>"
             . "<pre style='background:#f8fafc;border:1px solid #e2e8f0;padding:14px;border-radius:8px;font-size:13px;color:#1e293b;'>"
             . (trim($output) !== '' ? htmlspecialchars($output) : '  Nothing to migrate — columns already exist in the database.')
             . "</pre>"
             . "<h3 style='color:#374151;font-size:15px;'>🗄️ Column Verification:</h3>"
             . "<table style='width:100%;border-collapse:collapse;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;'>"
             . "<thead><tr style='background:#f9fafb;'><th style='padding:10px 12px;text-align:left;color:#6b7280;font-size:13px;'>Column Name</th><th style='padding:10px 12px;text-align:left;color:#6b7280;font-size:13px;'>Status</th></tr></thead>"
             . "<tbody>{$rows}</tbody>"
             . "</table>"
             . "<div style='margin-top:24px;display:flex;gap:12px;flex-wrap:wrap;'>"
             . "<a href='/run-migrations' style='background:#2563eb;color:#fff;padding:10px 20px;text-decoration:none;border-radius:6px;font-weight:bold;font-size:14px;'>🔄 Run All Pending Migrations</a>"
             . "<a href='/employees' style='background:#10b981;color:#fff;padding:10px 20px;text-decoration:none;border-radius:6px;font-weight:bold;font-size:14px;'>👥 Go to Employees</a>"
             . "</div>"
             . "</div></div>";
    } catch (\Exception $e) {
        return "<div style='font-family:sans-serif;max-width:800px;margin:40px auto;border-radius:14px;overflow:hidden;box-shadow:0 8px 24px rgba(0,0,0,0.12);'>"
             . "<div style='background:#991b1b;padding:28px 32px;color:#fff;'><h2 style='margin:0;'>❌ Migration Error</h2></div>"
             . "<div style='padding:28px;background:#fff;'>"
             . "<pre style='background:#fef2f2;border:1px solid #fecaca;padding:16px;border-radius:8px;color:#991b1b;'>" . htmlspecialchars($e->getMessage()) . "</pre>"
             . "</div></div>";
    }
});

// Run Expense Requests table migration (one-click)
Route::get('/run-expense-migration', function () {
    try {
        \Illuminate\Support\Facades\Artisan::call('migrate', [
            '--path'  => 'database/migrations/2026_08_17_000001_create_expense_requests_table.php',
            '--force' => true,
        ]);
        $output = \Illuminate\Support\Facades\Artisan::output();

        $exists = \Illuminate\Support\Facades\Schema::hasTable('expense_requests');
        $columns = $exists ? \Illuminate\Support\Facades\Schema::getColumnListing('expense_requests') : [];

        return "<div style='font-family:sans-serif;max-width:800px;margin:40px auto;border-radius:14px;overflow:hidden;box-shadow:0 8px 24px rgba(0,0,0,0.12);background:#fff;'>"
             . "<div style='background:linear-gradient(135deg,#065f46,#10b981);padding:24px 32px;color:#fff;'>"
             . "<h2 style='margin:0;font-size:20px;'>✅ Expense Requests Migration</h2>"
             . "<p style='margin:4px 0 0;opacity:0.9;'>Table: <code>expense_requests</code></p>"
             . "</div>"
             . "<div style='padding:28px 32px;'>"
             . "<p>Status: " . ($exists ? "<span style='background:#dcfce7;color:#166534;padding:4px 10px;border-radius:12px;font-weight:bold;'>EXISTS (" . count($columns) . " Columns)</span>" : "<span style='background:#fee2e2;color:#991b1b;padding:4px 10px;border-radius:12px;font-weight:bold;'>NOT FOUND</span>") . "</p>"
             . "<pre style='background:#f8fafc;border:1px solid #e2e8f0;padding:12px;border-radius:8px;font-size:13px;'>" . (trim($output) !== '' ? htmlspecialchars($output) : 'Nothing to migrate — table already exists.') . "</pre>"
             . "<div style='margin-top:20px;display:flex;gap:12px;'>"
             . "<a href='/expense-requests' style='background:#2563eb;color:#fff;padding:10px 20px;text-decoration:none;border-radius:6px;font-weight:bold;'>💵 Go to Expense Requests</a>"
             . "<a href='/dashboard' style='background:#0f172a;color:#fff;padding:10px 20px;text-decoration:none;border-radius:6px;font-weight:bold;'>🏠 Dashboard</a>"
             . "</div>"
             . "</div></div>";
    } catch (\Exception $e) {
        return "<div style='font-family:sans-serif;max-width:800px;margin:40px auto;padding:24px;border-radius:12px;background:#fee2e2;color:#991b1b;'>"
             . "<h3>❌ Migration Error:</h3>"
             . "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>"
             . "</div>";
    }
});

// Instant Sync: Match Fixed Assets system with real Store Inventory On-Hand & Unit Costs
Route::get('/sync-fixed-assets-inventory', function () {
    try {
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
        \Illuminate\Support\Facades\DB::table('fixed_asset_assignments')->truncate();
        \Illuminate\Support\Facades\DB::table('fixed_asset_units')->truncate();
        \Illuminate\Support\Facades\DB::table('fixed_assets')->truncate();
        \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();

        $existingProducts = \Illuminate\Support\Facades\DB::table('products')
            ->where('category', 'Fixed Asset')
            ->get();

        $synced = [];
        $usedPrefixes = [];
        $usedUnitCodes = [];

        foreach ($existingProducts as $prod) {
            $totalOnHand = (int) round(\Illuminate\Support\Facades\DB::table('inventory')->where('product_id', $prod->id)->sum('quantity_on_hand'));
            $totalAvailable = (int) round(\Illuminate\Support\Facades\DB::table('inventory')->where('product_id', $prod->id)->selectRaw('SUM(quantity_on_hand - COALESCE(quantity_reserved, 0)) as avail')->value('avail') ?? 0);
            $qty = $totalAvailable > 0 ? $totalAvailable : ($totalOnHand > 0 ? $totalOnHand : 0);

            // Only sync products with actual inventory on hand
            if ($qty <= 0 && $totalOnHand <= 0) {
                continue;
            }

            $invCost = (float) \Illuminate\Support\Facades\DB::table('inventory')
                ->where('product_id', $prod->id)
                ->where('unit_cost', '>', 0)
                ->value('unit_cost');

            $matPrice = (float) \Illuminate\Support\Facades\DB::table('material_prices')
                ->where('product_id', $prod->id)
                ->orderByDesc('effective_date')
                ->value('price');

            $prodCost = (float) ($prod->current_cost ?? $prod->standard_cost ?? $prod->unit_price ?? $prod->selling_price ?? 0);
            $unitCost = $invCost > 0 ? $invCost : ($matPrice > 0 ? $matPrice : $prodCost);

            $storeId = \Illuminate\Support\Facades\DB::table('inventory')->where('product_id', $prod->id)->where('quantity_on_hand', '>', 0)->value('store_id');

            // Clean code prefix
            $cleanName = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $prod->name));
            $basePrefix = substr($cleanName, 0, 4) ?: 'AST';

            $prefix = $basePrefix;
            $counter = 1;
            while (isset($usedPrefixes[$prefix])) {
                $counter++;
                $prefix = substr($basePrefix, 0, 3) . $counter;
            }
            $usedPrefixes[$prefix] = true;

            $prodCategory = $prod->sub_category ?? $prod->category ?? 'Computer & IT';

            $fixedAssetId = \Illuminate\Support\Facades\DB::table('fixed_assets')->insertGetId([
                'name'           => $prod->name,
                'category'       => $prodCategory,
                'code_prefix'    => $prefix,
                'total_quantity' => $qty,
                'unit_cost'      => $unitCost,
                'store_id'       => $storeId,
                'description'    => 'Synced with Inventory On-Hand Stock (Product SKU: ' . ($prod->sku ?? $prod->code ?? '') . ')',
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            for ($i = 1; $i <= $qty; $i++) {
                $seq = $i;
                $unitCode = "{$prefix}-{$seq}";
                while (isset($usedUnitCodes[$unitCode])) {
                    $seq++;
                    $unitCode = "{$prefix}-{$seq}";
                }
                $usedUnitCodes[$unitCode] = true;

                \Illuminate\Support\Facades\DB::table('fixed_asset_units')->insert([
                    'fixed_asset_id'  => $fixedAssetId,
                    'unit_code'       => $unitCode,
                    'sequence_number' => $seq,
                    'status'          => 'in_store',
                    'condition'       => $prod->equipment_condition ?: 'good',
                    'current_location'=> $prod->current_location ?: 'Main Store',
                    'purchase_price'  => $unitCost,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            }

            $synced[] = "<strong>{$prod->name}</strong>: {$qty} units (Prefix: <code>{$prefix}</code>, Unit Cost: Br " . number_format($unitCost, 2) . ", Total: Br " . number_format($qty * $unitCost, 2) . ")";
        }

        \Illuminate\Support\Facades\Cache::forget('sidebar_fixed_asset_units_count');

        $html = "<div style='font-family:sans-serif;max-width:800px;margin:40px auto;padding:24px;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,0.1);background:#fff;border-top:6px solid #10b981;'>";
        $html .= "<h2 style='color:#065f46;margin-top:0;'>✅ Fixed Assets Synced with Store Inventory!</h2>";
        $html .= "<p style='color:#374151;'>All Fixed Assets and unit codes have been perfectly synchronized to match your live <strong>All Inventory On-Hand quantities and unit costs</strong>.</p>";
        $html .= "<ul style='background:#f3f4f6;padding:20px 30px;border-radius:8px;line-height:1.8;color:#1f2937;'>";
        foreach ($synced as $item) {
            $html .= "<li>{$item}</li>";
        }
        $html .= "</ul>";
        $html .= "<div style='margin-top:24px;'><a href='/store-manager/fixed-assets' style='background:#2563eb;color:#fff;padding:10px 20px;text-decoration:none;border-radius:6px;font-weight:bold;'>→ View Fixed Assets Management</a></div>";
        $html .= "</div>";

        return $html;
    } catch (\Throwable $e) {
        return "<div style='font-family:sans-serif;max-width:800px;margin:40px auto;padding:24px;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,0.1);background:#fff;border-top:6px solid #ef4444;'>"
             . "<h2 style='color:#991b1b;margin-top:0;'>❌ Sync Error</h2>"
             . "<pre style='background:#fee2e2;padding:16px;border-radius:8px;color:#991b1b;'>" . htmlspecialchars($e->getMessage()) . "</pre>"
             . "</div>";
    }
});

// Fix code_prefix mismatch: reads unit codes and aligns parent fixed_asset prefix
Route::get('/fix-fixed-assets-prefix', function () {
    try {
        $fixedAssets = \Illuminate\Support\Facades\DB::table('fixed_assets')->get();
        $fixed = [];
        $skipped = [];

        foreach ($fixedAssets as $asset) {
            // Get first unit code for this asset
            $firstUnit = \Illuminate\Support\Facades\DB::table('fixed_asset_units')
                ->where('fixed_asset_id', $asset->id)
                ->orderBy('sequence_number')
                ->value('unit_code');

            if (!$firstUnit) {
                $skipped[] = "<strong>{$asset->name}</strong>: no units found, skipped.";
                continue;
            }

            // Extract prefix from unit code (everything before the last dash-number)
            // e.g. "COMP-1" → "COMP", "COMP-24" → "COMP"
            $parts = explode('-', $firstUnit);
            array_pop($parts); // remove the trailing number
            $derivedPrefix = strtoupper(implode('-', $parts));

            if ($derivedPrefix === strtoupper($asset->code_prefix)) {
                $skipped[] = "<strong>{$asset->name}</strong>: already correct (<code>{$asset->code_prefix}</code>)";
                continue;
            }

            // Update the parent fixed_asset record
            \Illuminate\Support\Facades\DB::table('fixed_assets')
                ->where('id', $asset->id)
                ->update([
                    'code_prefix' => $derivedPrefix,
                    'updated_at'  => now(),
                ]);

            $fixed[] = "<strong>{$asset->name}</strong>: <del style='color:#ef4444'>{$asset->code_prefix}</del> → <code style='color:#059669'>{$derivedPrefix}</code>";
        }

        \Illuminate\Support\Facades\Cache::forget('sidebar_fixed_asset_units_count');

        $html  = "<div style='font-family:sans-serif;max-width:800px;margin:40px auto;padding:24px;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,0.1);background:#fff;border-top:6px solid #10b981;'>";
        $html .= "<h2 style='color:#065f46;margin-top:0;'>✅ Fixed Asset Prefixes Corrected!</h2>";
        if ($fixed) {
            $html .= "<h4>Updated:</h4><ul style='background:#f0fdf4;padding:16px 24px;border-radius:8px;line-height:2;'>";
            foreach ($fixed as $f) $html .= "<li>{$f}</li>";
            $html .= "</ul>";
        }
        if ($skipped) {
            $html .= "<h4>Skipped (already correct):</h4><ul style='background:#f3f4f6;padding:16px 24px;border-radius:8px;line-height:2;color:#6b7280;'>";
            foreach ($skipped as $s) $html .= "<li>{$s}</li>";
            $html .= "</ul>";
        }
        $html .= "<div style='margin-top:24px;'><a href='/public/index.php/store-manager/fixed-assets' style='background:#2563eb;color:#fff;padding:10px 20px;text-decoration:none;border-radius:6px;font-weight:bold;'>→ View Fixed Assets</a></div>";
        $html .= "</div>";

        return $html;
    } catch (\Throwable $e) {
        return "<div style='font-family:sans-serif;max-width:800px;margin:40px auto;padding:24px;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,0.1);background:#fff;border-top:6px solid #ef4444;'>"
             . "<h2 style='color:#991b1b;margin-top:0;'>❌ Prefix Fix Error</h2>"
             . "<pre style='background:#fee2e2;padding:16px;border-radius:8px;color:#991b1b;'>" . htmlspecialchars($e->getMessage()) . "</pre>"
             . "</div>";
    }
});

// Fix storage symlink + migrate in one click (fixes 404 on uploaded images)
Route::get('/fix-storage-link', function () {
    $style = 'font-family:sans-serif;max-width:900px;margin:40px auto;padding:28px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,0.12);background:#fff;';
    $html  = "<div style='{$style}'>";
    $html .= "<h2 style='color:#1e40af;margin-top:0;'>🔧 Storage & Migration Fix Tool</h2>";

    // Step 1: Run storage:link
    try {
        \Illuminate\Support\Facades\Artisan::call('storage:link', ['--force' => true]);
        $linkOut = \Illuminate\Support\Facades\Artisan::output();
        $html .= "<div style='background:#d1fae5;border-left:5px solid #10b981;padding:12px 16px;border-radius:6px;margin-bottom:16px;'>";
        $html .= "<strong style='color:#065f46;'>✅ Step 1: Storage Symlink Created!</strong><br>";
        $html .= "<code style='font-size:13px;color:#065f46;'>" . htmlspecialchars($linkOut) . "</code>";
        $html .= "<br><small>Your uploaded images are now publicly accessible at <code>/storage/...</code></small></div>";
    } catch (\Exception $e) {
        // If it fails, try creating symlink manually via PHP
        $pubStorage = public_path('storage');
        $appStorage = storage_path('app/public');
        if (!file_exists($pubStorage)) {
            try {
                symlink($appStorage, $pubStorage);
                $html .= "<div style='background:#d1fae5;border-left:5px solid #10b981;padding:12px 16px;border-radius:6px;margin-bottom:16px;'>";
                $html .= "<strong style='color:#065f46;'>✅ Step 1: Symlink Created via PHP!</strong></div>";
            } catch (\Exception $e2) {
                $html .= "<div style='background:#fee2e2;border-left:5px solid #ef4444;padding:12px 16px;border-radius:6px;margin-bottom:16px;'>";
                $html .= "<strong style='color:#991b1b;'>⚠️ Step 1: Symlink may need manual creation on server.</strong><br>";
                $html .= "<code>" . htmlspecialchars($e2->getMessage()) . "</code></div>";
            }
        } else {
            $html .= "<div style='background:#d1fae5;border-left:5px solid #10b981;padding:12px 16px;border-radius:6px;margin-bottom:16px;'>";
            $html .= "<strong style='color:#065f46;'>✅ Step 1: Storage link already exists.</strong></div>";
        }
    }

    // Step 2: Run migrations
    try {
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        $migOut = \Illuminate\Support\Facades\Artisan::output();
        $html .= "<div style='background:#dbeafe;border-left:5px solid #3b82f6;padding:12px 16px;border-radius:6px;margin-bottom:16px;'>";
        $html .= "<strong style='color:#1e3a8a;'>✅ Step 2: Database Migrations Applied!</strong>";
        $html .= "<pre style='background:#eff6ff;padding:10px;border-radius:4px;font-size:12px;margin-top:8px;overflow-x:auto;'>" . htmlspecialchars($migOut) . "</pre></div>";
    } catch (\Exception $e) {
        $html .= "<div style='background:#fee2e2;border-left:5px solid #ef4444;padding:12px 16px;border-radius:6px;margin-bottom:16px;'>";
        $html .= "<strong style='color:#991b1b;'>❌ Step 2: Migration Error</strong><br>";
        $html .= "<code>" . htmlspecialchars($e->getMessage()) . "</code></div>";
    }

    // Step 3: Clear all caches
    \Illuminate\Support\Facades\Artisan::call('config:clear');
    \Illuminate\Support\Facades\Artisan::call('route:clear');
    \Illuminate\Support\Facades\Artisan::call('view:clear');
    $html .= "<div style='background:#f3f4f6;border-left:5px solid #6b7280;padding:12px 16px;border-radius:6px;margin-bottom:16px;'>";
    $html .= "<strong>✅ Step 3: Config, Route & View caches cleared.</strong></div>";

    // Check storage link status
    $pubStorage = public_path('storage');
    $isLinked = file_exists($pubStorage) && (is_link($pubStorage) || is_dir($pubStorage));
    $html .= "<div style='background:#ecfdf5;border-left:5px solid #10b981;padding:12px 16px;border-radius:6px;margin-bottom:16px;'>";
    $html .= "<strong>📂 Storage Link Status:</strong> " . ($isLinked ? '✅ public/storage is accessible' : '⚠️ public/storage not found') . "<br>";
    $html .= "<small>Path: <code>{$pubStorage}</code></small></div>";

    $html .= "<div style='margin-top:20px;display:flex;gap:12px;flex-wrap:wrap;'>";
    $html .= "<a href='/' style='background:#2563eb;color:#fff;padding:10px 20px;text-decoration:none;border-radius:6px;font-weight:bold;'>🏠 Go to Home</a>";
    $html .= "<a href='/letters' style='background:#10b981;color:#fff;padding:10px 20px;text-decoration:none;border-radius:6px;font-weight:bold;'>📧 Go to Letters</a>";
    $html .= "<a href='/daily-reports' style='background:#f59e0b;color:#fff;padding:10px 20px;text-decoration:none;border-radius:6px;font-weight:bold;'>📋 Go to Daily Reports</a>";
    $html .= "</div>";
    $html .= "</div>";

    return $html;
});

Route::get('/migrate-material-prices', function () {
    return redirect('/run-migrations');
});

// Quick Web Tool: Unlock Employee by Code (e.g., EMP-04)
Route::get('/unlock-employee/{code?}', function ($code = 'EMP-04') {
    $searchCode = trim($code);
    $cleanNum = preg_replace('/[^\d]/', '', $searchCode);

    $employees = \App\Models\Employee::where('employee_code', 'LIKE', "%{$searchCode}%")
        ->orWhere('employee_code', 'LIKE', "%04%")
        ->orWhere('employee_code', 'LIKE', "%{$cleanNum}%")
        ->get();

    if ($employees->isEmpty()) {
        // Fallback: get all unapproved employees
        $employees = \App\Models\Employee::where('is_approved_by_gm', false)
            ->orWhereNull('is_approved_by_gm')
            ->get();
    }

    $unlocked = [];
    foreach ($employees as $emp) {
        $emp->update([
            'is_approved_by_gm' => true,
            'gm_approved_at'    => now(),
            'status'            => 'active',
        ]);
        $unlocked[] = "ID: {$emp->id} | Code: <strong>{$emp->employee_code}</strong> | Name: <strong>{$emp->full_name}</strong> | User ID: " . ($emp->user_id ?? 'None');
    }

    $style = 'font-family:sans-serif;max-width:800px;margin:40px auto;padding:24px;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,0.1);background:#fff;border-top:6px solid #10b981;';
    $html  = "<div style='{$style}'>";
    $html .= "<h2 style='color:#065f46;margin-top:0;'>🔓 Employee Unlocked Successfully!</h2>";

    if (count($unlocked) > 0) {
        $html .= "<p style='color:#374151;'>The following employee account(s) have been unlocked and approved for full dashboard access:</p>";
        $html .= "<ul style='background:#f3f4f6;padding:16px 24px;border-radius:8px;line-height:1.8;'>";
        foreach ($unlocked as $info) {
            $html .= "<li>{$info}</li>";
        }
        $html .= "</ul>";
    } else {
        $html .= "<p style='color:#6b7280;'>No matching locked employee found. All employees are already unlocked and approved.</p>";
    }

    $html .= "<div style='margin-top:20px;'><a href='/login' style='background:#2563eb;color:#fff;padding:10px 20px;text-decoration:none;border-radius:6px;font-weight:bold;'>Go to Login Page</a></div>";
    $html .= "</div>";

    return $html;
});

// Quick Web Tool: Unlock ALL Employees
Route::get('/unlock-all-employees', function () {
    $count = \App\Models\Employee::query()->update([
        'is_approved_by_gm' => true,
        'gm_approved_at'    => now(),
        'status'            => 'active',
    ]);

    return "<div style='font-family:sans-serif;max-width:800px;margin:40px auto;padding:24px;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,0.1);background:#fff;border-top:6px solid #10b981;'>"
         . "<h2 style='color:#065f46;margin-top:0;'>🔓 All Employee Accounts Unlocked!</h2>"
         . "<p style='color:#374151;'>Total employee accounts updated & approved: <strong>{$count}</strong></p>"
         . "<div style='margin-top:20px;'><a href='/login' style='background:#2563eb;color:#fff;padding:10px 20px;text-decoration:none;border-radius:6px;font-weight:bold;'>Go to Login Page</a></div>"
         . "</div>";
});

// Diagnostic route to reveal store-manager error
Route::get('/debug-store-manager-error', function () {
    try {
        $user = \App\Models\User::whereHas('roles', fn($q) => $q->where('name', 'store_manager'))->with('employee')->first();
        
        $results = [];

        // 1. Check DB connection
        \Illuminate\Support\Facades\DB::connection()->getPdo();
        $results[] = '✅ DB connected to: ' . \Illuminate\Support\Facades\DB::connection()->getDatabaseName();

        // 2. Check store_manager user
        $results[] = $user ? "✅ Found store_manager user: {$user->name} (id={$user->id})" : "❌ No user with role 'store_manager' found";

        // 3. Check employee record
        if ($user) {
            $emp = $user->employee;
            $results[] = $emp ? "✅ Employee record found: date_of_joining=" . ($emp->date_of_joining ?? 'NULL') : "⚠️ No employee record for this user";
        }

        // 4. Check tables exist
        $tables = ['inventory', 'stores', 'products', 'transfers', 'inventory_movements', 'delivery_receipts', 'material_requests'];
        foreach ($tables as $t) {
            $exists = \Illuminate\Support\Facades\Schema::hasTable($t);
            $results[] = ($exists ? "✅" : "❌") . " Table '{$t}' " . ($exists ? "exists" : "MISSING");
        }

        // 5. Check columns on stores
        $hasCols = [
            'stores' => ['is_active'],
            'products' => ['is_active'],
            'delivery_receipts' => ['received_date', 'status'],
        ];
        foreach ($hasCols as $tbl => $cols) {
            foreach ($cols as $col) {
                $ok = \Illuminate\Support\Facades\Schema::hasColumn($tbl, $col);
                $results[] = ($ok ? "✅" : "❌") . " Column '{$tbl}.{$col}' " . ($ok ? "exists" : "MISSING");
            }
        }

        // 6. Try the dashboard query
        try {
            $cnt = \App\Models\Inventory::count();
            $results[] = "✅ Inventory count: $cnt";
        } catch (\Throwable $e) {
            $results[] = "❌ Inventory query: " . $e->getMessage();
        }

        try {
            $cnt = \App\Models\Store::where('is_active', true)->count();
            $results[] = "✅ Active stores: $cnt";
        } catch (\Throwable $e) {
            $results[] = "❌ Store query: " . $e->getMessage();
        }

        // 7. Try loading the view
        try {
            $view = view('store-manager.dashboard', [
                'kpi' => ['total_items'=>0,'total_value'=>0,'low_stock_items'=>0,'pending_transfers'=>0,'received_today'=>0,'pending_requests'=>0],
                'inventoryValueByStore' => collect(),
                'todayAdjustmentValue' => 0,
                'monthlyReceiptsValue' => 0,
                'lastMonthReceiptsValue' => 0,
                'topValueItems' => collect(),
                'allInventory' => collect(),
                'lowStock' => collect(),
                'lowStockItems' => collect(),
                'transfersToGeneralService' => collect(),
                'materialRequests' => collect(),
                'stores' => collect(),
            ])->render();
            $results[] = "✅ View rendered OK (" . strlen($view) . " bytes)";
        } catch (\Throwable $e) {
            $results[] = "❌ View render error: " . $e->getMessage() . "\n   in " . $e->getFile() . " line " . $e->getLine();
        }

        $html = '<style>body{font-family:monospace;padding:20px;} .ok{color:green;} .err{color:red;}</style>';
        $html .= '<h2>Store Manager Debug Report</h2><pre>';
        foreach ($results as $r) {
            $html .= htmlspecialchars($r) . "\n";
        }
        $html .= '</pre>';
        return $html;

    } catch (\Throwable $e) {
        return '<pre style="color:red;padding:20px;font-family:monospace;">CRITICAL ERROR: ' . htmlspecialchars($e->getMessage()) . "\n\n" . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    }
});

// ====== TEMPORARY: SMS Test Route - Remove after testing ======
Route::get('/test-sms/{phone}', function ($phone) {
    $smsService = new \App\Services\SmsEthiopiaService();
    $otp = \App\Services\SmsEthiopiaService::generateOTP();
    $result = $smsService->sendOTP($phone, $otp);

    return response()->json([
        'phone_tested' => $phone,
        'otp_generated' => $otp,
        'service_result' => $result,
    ]);
});
// ====== END TEMPORARY ======

// Temporary route to import products.sql
Route::get('/import-products-sql', function () {
    $sqlPath = base_path('products.sql');
    if (!file_exists($sqlPath)) {
        return "products.sql not found at " . $sqlPath;
    }
    
    $sql = file_get_contents($sqlPath);
    $start = strpos($sql, 'INSERT INTO `products`');
    
    if ($start !== false) {
        $insertQuery = substr($sql, $start);
        
        $end = strpos($insertQuery, 'ALTER TABLE');
        if ($end !== false) {
            $insertQuery = substr($insertQuery, 0, $end);
        }
        
        $insertQuery = str_replace('INSERT INTO `products`', 'INSERT IGNORE INTO `products`', $insertQuery);
        
        try {
            \Illuminate\Support\Facades\DB::unprepared($insertQuery);
            return "<h1>Success!</h1><p>Products imported successfully from SQL file.</p><a href='/store-manager/products'>Go back to Material Catalog</a>";
        } catch (\Exception $e) {
            return "<h1>Error</h1><p>" . $e->getMessage() . "</p>";
        }
    }
    
    return "No INSERT statement found in the file.";
});

// Temporary route to import employees.sql
Route::get('/import-employees-sql', function () {
    $sqlPath = base_path('employees.sql');
    if (!file_exists($sqlPath)) {
        return "employees.sql not found at " . $sqlPath;
    }
    
    $sql = file_get_contents($sqlPath);
    
    // Extract just the INSERT INTO statement
    $start = strpos($sql, 'INSERT INTO `employees`');
    if ($start === false) {
        return "No INSERT statement found in the file.";
    }
    
    $insertQuery = substr($sql, $start);
    $end = strpos($insertQuery, 'ALTER TABLE');
    if ($end !== false) {
        $insertQuery = substr($insertQuery, 0, $end);
    }
    
    $insertQuery = str_replace('INSERT INTO `employees`', 'INSERT INTO `employees_temp`', $insertQuery);
    
    try {
        \Illuminate\Support\Facades\Schema::dropIfExists('employees_temp');
        
        // Create the temp table using Laravel Schema to ensure it has a primary key natively
        \Illuminate\Support\Facades\Schema::create('employees_temp', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->id();
            $table->string('employee_id_number')->nullable();
            $table->string('user_id')->nullable();
            $table->text('full_name')->nullable();
            $table->text('department')->nullable();
            $table->text('designation')->nullable();
            $table->text('phone_number')->nullable();
            $table->text('base_salary')->nullable();
            $table->text('position')->nullable();
            $table->text('joining_date')->nullable();
            $table->text('salary')->nullable();
            $table->text('status')->nullable();
            $table->text('created_at')->nullable();
            $table->text('employment_date')->nullable();
            $table->text('educational_background')->nullable();
            $table->text('educational_file')->nullable();
            $table->text('experience_years')->nullable();
            $table->text('experience_file')->nullable();
            $table->text('application_letter_file')->nullable();
            $table->text('id_card_file')->nullable();
            $table->text('license_file')->nullable();
            $table->text('phone_number_2')->nullable();
            $table->text('guarantee_letter_file')->nullable();
            $table->text('contract_type')->nullable();
            $table->text('subcontractor_id')->nullable();
            $table->text('site_id')->nullable();
            $table->text('bank_info')->nullable();
            $table->text('rating')->nullable();
            $table->text('transport_allowance')->nullable();
            $table->text('house_allowance')->nullable();
            $table->text('position_allowance')->nullable();
        });
        
        // Run ONLY the insert statement
        \Illuminate\Support\Facades\DB::unprepared($insertQuery);
        
        $oldEmployees = \Illuminate\Support\Facades\DB::table('employees_temp')->get();
        $imported = 0;
        
        foreach($oldEmployees as $emp) {
            if (\App\Models\Employee::where('employee_code', $emp->employee_id_number)->exists()) {
                continue;
            }
            
            // Check if user_id actually exists in the users table
            $userId = $emp->user_id;
            if ($userId && !\App\Models\User::where('id', $userId)->exists()) {
                $userId = null; // Set to null if user doesn't exist to avoid foreign key errors
            }
            
            $bankName = null;
            $accountNumber = null;
            if ($emp->bank_info && $emp->bank_info !== 'null' && $emp->bank_info !== '[]') {
                $bankInfo = json_decode($emp->bank_info, true);
                if (is_array($bankInfo) && count($bankInfo) > 0) {
                    $bankName = $bankInfo[0]['bank_name'] ?? null;
                    $accountNumber = $bankInfo[0]['account_number'] ?? null;
                }
            }
            
            \App\Models\Employee::create([
                'employee_code'   => $emp->employee_id_number,
                'user_id'         => $userId,
                'full_name'       => $emp->full_name,
                'department'      => $emp->department,
                'role_title'      => $emp->designation ?? $emp->position,
                'phone'           => $emp->phone_number,
                'date_of_joining' => $emp->joining_date ?? $emp->employment_date ?? now(),
                'employment_type' => strtolower($emp->contract_type ?? 'permanent'),
                'status'          => $emp->status ?? 'active',
                'basic_salary'    => 0,
                'bank_name'       => $bankName,
                'account_number'  => $accountNumber,
                'guarantee_letter'=> $emp->guarantee_letter_file,
            ]);
            
            $imported++;
        }
        
        \Illuminate\Support\Facades\Schema::dropIfExists('employees_temp');
        
        return "<h1>Success!</h1><p>$imported employees properly mapped and imported into the new system.</p>";
        
    } catch (\Exception $e) {
        return "<h1>Error</h1><p>" . $e->getMessage() . "</p>";
    }
});

// Auth
Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('login', [LoginController::class, 'login']);
Route::post('logout', [LoginController::class, 'logout'])->name('logout');

// Registration Routes (Guest only)
Route::middleware('guest')->prefix('register')->name('register.')->group(function () {
    Route::get('/', [App\Http\Controllers\Auth\RegisterController::class, 'showRegistrationForm'])->name('index');
    Route::post('/send-otp', [App\Http\Controllers\Auth\RegisterController::class, 'sendOtp'])->name('send-otp');
    Route::get('/verify-otp', [App\Http\Controllers\Auth\RegisterController::class, 'showVerifyOtpForm'])->name('verify-otp');
    Route::post('/verify-otp', [App\Http\Controllers\Auth\RegisterController::class, 'verifyOtp']);
    Route::get('/create-password', [App\Http\Controllers\Auth\RegisterController::class, 'showCreatePasswordForm'])->name('create-password');
    Route::post('/create-password', [App\Http\Controllers\Auth\RegisterController::class, 'createPassword']);
    Route::post('/resend-otp', [App\Http\Controllers\Auth\RegisterController::class, 'resendOtp'])->name('resend-otp');
});

// Phone-Based Password Reset Routes
Route::prefix('password')->group(function () {
    Route::get('/reset', [App\Http\Controllers\Auth\PhonePasswordResetController::class, 'showForgotForm'])->name('password.request');
    Route::post('/reset', [App\Http\Controllers\Auth\PhonePasswordResetController::class, 'sendResetOtp'])->name('password.email'); // Kept name for compatibility
    Route::get('/verify', [App\Http\Controllers\Auth\PhonePasswordResetController::class, 'showVerifyOtpForm'])->name('password.verify');
    Route::post('/verify', [App\Http\Controllers\Auth\PhonePasswordResetController::class, 'verifyOtp']);
    Route::get('/update', [App\Http\Controllers\Auth\PhonePasswordResetController::class, 'showResetForm'])->name('password.reset');
    Route::post('/update', [App\Http\Controllers\Auth\PhonePasswordResetController::class, 'resetPassword'])->name('password.update');
    Route::post('/resend', [App\Http\Controllers\Auth\PhonePasswordResetController::class, 'resendOtp'])->name('password.resend');
});

// Direct Registration Route
Route::get('register', [App\Http\Controllers\Auth\RegisterController::class, 'showRegistrationForm'])->middleware('guest')->name('register');

// Protected routes
Route::middleware(['auth'])->group(function () {
    
    // Unassigned role fallback page
    // Unassigned role fallback page
    Route::get('/pending-role', function () {
        return view('auth.pending_role');
    })->name('pending-role');

    // User Profile Routes
    Route::get('/profile', [App\Http\Controllers\ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [App\Http\Controllers\ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [App\Http\Controllers\ProfileController::class, 'updatePassword'])->name('profile.password.update');


    
    // --- Admin Dashboard Enhancements ---
    Route::middleware('role:global_admin|admin')->group(function () {
        Route::get('/admin/activity-logs', [ActivityLogController::class, 'index'])->name('admin.activity-logs');
        
        Route::get('/admin/employee-ratings', [EmployeeRatingController::class, 'index'])->name('admin.employee-ratings.index');
        Route::post('/admin/employee-ratings', [EmployeeRatingController::class, 'store'])->name('admin.employee-ratings.store');
        
        Route::get('/admin/role-assignment', [RoleAssignmentController::class, 'index'])->name('admin.role-assignment.index');
        Route::post('/admin/role-assignment/{user}', [RoleAssignmentController::class, 'assign'])->name('admin.role-assignment.assign');
        Route::post('/admin/role-assignment/{user}/remove', [RoleAssignmentController::class, 'removeRole'])->name('admin.role-assignment.remove');
        
        Route::get('/admin/tickets', [AdminTicketController::class, 'index'])->name('admin.tickets.index');
        Route::get('/admin/tickets/{ticket}', [AdminTicketController::class, 'show'])->name('admin.tickets.show');
        Route::post('/admin/tickets/{ticket}/reply', [AdminTicketController::class, 'reply'])->name('admin.tickets.reply');
        Route::post('/admin/tickets/{ticket}/status', [AdminTicketController::class, 'updateStatus'])->name('admin.tickets.status');
        Route::post('/admin/tickets/{ticket}/assign', [AdminTicketController::class, 'assign'])->name('admin.tickets.assign');
    });

    // Support Tickets (All Employees)
    Route::get('/tickets', [SupportTicketController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/create', [SupportTicketController::class, 'create'])->name('tickets.create');
    Route::post('/tickets', [SupportTicketController::class, 'store'])->name('tickets.store');
    Route::get('/tickets/{ticket}', [SupportTicketController::class, 'show'])->name('tickets.show');
    Route::post('/tickets/{ticket}/reply', [SupportTicketController::class, 'reply'])->name('tickets.reply');

    // Maintenance Requests (Employee — report from profile page)
    Route::post('/maintenance-requests', [MaintenanceRequestController::class, 'store'])->name('maintenance.store');
    Route::get('/maintenance-requests/{maintenanceRequest}', [MaintenanceRequestController::class, 'show'])->name('maintenance.show');

    // General Service — Maintenance Management (Admin / Store Manager)
    Route::prefix('general-service')->name('general-service.')->group(function () {
        Route::get('/maintenance', [GeneralServiceController::class, 'index'])->name('maintenance.index');
        Route::get('/maintenance/{maintenanceRequest}', [GeneralServiceController::class, 'show'])->name('maintenance.show');
        Route::post('/maintenance/{maintenanceRequest}/status', [GeneralServiceController::class, 'updateStatus'])->name('maintenance.update-status');
    });
    // ------------------------------------
    // System Actions – GET so we can trigger it directly from a sidebar link
    Route::get('/system/run-migrations', function () {
        try {
            \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
            $output = \Illuminate\Support\Facades\Artisan::output();
            // Reseed roles & permissions in case new ones were added
            try {
                \Illuminate\Support\Facades\Artisan::call('db:seed', [
                    '--class' => 'RolesAndPermissionsSeeder',
                    '--force' => true,
                ]);
            } catch (\Throwable $seederErr) {
                // ignore if seeder class name differs
            }
            // Seed/sync products dump
            try {
                \Illuminate\Support\Facades\Artisan::call('db:seed', [
                    '--class' => 'ProductSeeder',
                    '--force' => true,
                ]);
                $output .= ' | Products dumped/synced.';
            } catch (\Throwable $pe) {
                $output .= ' | Product seed notice: ' . $pe->getMessage();
            }
            // Seed Employees from employees.sql if employees table is empty
            try {
                if (\App\Models\Employee::count() === 0) {
                    \Illuminate\Support\Facades\Artisan::call('db:seed', [
                        '--class' => 'Database\Seeders\EmployeesSqlSeeder',
                        '--force' => true,
                    ]);
                    $output .= ' | Employees imported from employees.sql.';
                }
            } catch (\Throwable $empErr) {
                $output .= ' | Employee import error: ' . $empErr->getMessage();
            }
            return redirect()->back()->with('success', 'Database migrated & synced! Output: ' . $output);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Migration failed: ' . $e->getMessage());
        }
    })->name('system.run-migrations');

    // Dedicated route to (re-)seed products
    Route::get('/system/seed-products', function () {
        try {
            \Illuminate\Support\Facades\Artisan::call('db:seed', [
                '--class' => 'ProductSeeder',
                '--force' => true,
            ]);
            return redirect()->route('products.index')->with('success', 'Products seeded successfully!');
        } catch (\Exception $e) {
            return redirect()->route('products.index')->with('error', 'Seeding failed: ' . $e->getMessage());
        }
    })->name('system.seed-products');

    // Also keep a POST alias for backward-compat form submissions
    Route::post('/system/run-migrations', function () {
        try {
            \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
            return back()->with('success', 'Migrations completed successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Migration failed: ' . $e->getMessage());
        }
    });

    // Dynamic Dashboard Redirect
    Route::get('/dashboard', function () {
        $loginController = new \App\Http\Controllers\Auth\LoginController();
        $method = new \ReflectionMethod($loginController, 'redirectTo');
        $method->setAccessible(true);
        $redirectUrl = $method->invoke($loginController);
        return redirect($redirectUrl);
    })->name('dashboard');

    // Role Tester
    Route::get('/dev/roles', [App\Http\Controllers\RoleTesterController::class, 'index'])->name('dev.roles');
    Route::post('/dev/roles/login', [App\Http\Controllers\RoleTesterController::class, 'loginAsRole'])->name('dev.roles.login');

    // ─── Dashboard placeholders (will be replaced per phase) ──────────────────
    Route::get('/dashboard/admin',          [App\Http\Controllers\DashboardController::class, 'admin'])->name('dashboard.admin');
    Route::get('/dashboard/gm',             [App\Http\Controllers\DashboardController::class, 'gm'])->name('dashboard.gm');
    Route::get('/dashboard/planning',       [App\Http\Controllers\DashboardController::class, 'planning'])->name('dashboard.planning');
    Route::get('/dashboard/coordinator',    [App\Http\Controllers\DashboardController::class, 'coordinator'])->name('dashboard.coordinator');
    
    // Coordinator Routes
    Route::get('/coordinator/forecast',     [App\Http\Controllers\CoordinatorController::class, 'forecastDemand'])->name('coordinator.forecast');
    Route::get('/dashboard/general-service', [App\Http\Controllers\DashboardController::class, 'generalService'])->name('dashboard.general_service');
    Route::get('/dashboard/site-engineer',  [App\Http\Controllers\DashboardController::class, 'siteEngineer'])->name('dashboard.site-engineer');
    Route::get('/dashboard/foreman',        [App\Http\Controllers\DashboardController::class, 'foreman'])->name('dashboard.foreman');
    Route::get('/dashboard/store-manager',  [App\Http\Controllers\DashboardController::class, 'storeManager'])->name('dashboard.store-manager');
    Route::get('/dashboard/hr',             [App\Http\Controllers\DashboardController::class, 'hr'])->name('dashboard.hr');
    Route::get('/dashboard/finance',        [App\Http\Controllers\DashboardController::class, 'finance'])->name('dashboard.finance');
    Route::get('/dashboard/purchase',       [App\Http\Controllers\DashboardController::class, 'purchase'])->name('dashboard.purchase');
    Route::get('/dashboard/contract-admin', [App\Http\Controllers\DashboardController::class, 'contractAdmin'])->name('dashboard.contract-admin');
    Route::get('/bidding',                  fn() => view('dashboard.admin'))->name('bidding.index');
    Route::get('/subcon',                   fn() => view('dashboard.admin'))->name('subcon.index');
    Route::get('/audit',                    fn() => view('dashboard.admin'))->name('audit.index');

    // ─── General Service Routes ────────────────────────────────────────────────
    Route::prefix('general-service')->name('general-service.')->group(function () {
        Route::get('/',                     [App\Http\Controllers\DashboardController::class, 'generalService'])->name('dashboard');
        Route::get('/maintenance',          [App\Http\Controllers\Admin\GeneralServiceController::class, 'index'])->name('maintenance.index');
        Route::get('/maintenance/{maintenanceRequest}', [App\Http\Controllers\Admin\GeneralServiceController::class, 'show'])->name('maintenance.show');
        Route::put('/maintenance/{maintenanceRequest}/status', [App\Http\Controllers\Admin\GeneralServiceController::class, 'updateStatus'])->name('maintenance.status');
        Route::post('/maintenance/{maintenanceRequest}/ask-money', [App\Http\Controllers\Admin\GeneralServiceController::class, 'askMoney'])->name('maintenance.ask-money');
    });

    // ─── Fixed Assets Route Alias ──────────────────────────────────────────────
    Route::get('/fixed-assets', fn() => redirect()->route('store-manager.fixed-assets.index'))->name('fixed-assets.index');

    // ─── Employee Maintenance Routes ───────────────────────────────────────────
    Route::post('/maintenance',             [App\Http\Controllers\MaintenanceRequestController::class, 'store'])->name('maintenance.store');
    Route::get('/maintenance/{maintenanceRequest}', [App\Http\Controllers\MaintenanceRequestController::class, 'show'])->name('maintenance.show');

    // ─── Phase 2 Core Masters ─────────────────────────────────────────────────

    // Users (admin only)
    Route::resource('users', UserController::class)->except(['show']);

    // Projects
    Route::resource('projects', ProjectController::class);

    // Stores
    Route::resource('stores', StoreController::class);

    // Asset Returns (Store Manager)
    Route::get('asset-returns', [App\Http\Controllers\AssetReturnController::class, 'index'])->name('asset-returns.index');
    Route::put('asset-returns/{id}/approve', [App\Http\Controllers\AssetReturnController::class, 'approve'])->name('asset-returns.approve');
    Route::put('asset-returns/{id}/reject', [App\Http\Controllers\AssetReturnController::class, 'reject'])->name('asset-returns.reject');

    // Products
    Route::resource('products', ProductController::class);

    // Inventory
    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::get('/', [InventoryController::class, 'index'])->name('index');
        Route::get('bulk-adjust', [InventoryController::class, 'showBulkAdjust'])->name('bulk-adjust');
        Route::post('bulk-adjust', [InventoryController::class, 'bulkAdjust'])->name('bulk-adjust.store');
        Route::post('save-single', [InventoryController::class, 'saveSingle'])->name('save-single');
        Route::get('{inventory}', [InventoryController::class, 'show'])->name('show');
        Route::post('{inventory}/adjust', [InventoryController::class, 'adjust'])->name('adjust');
        Route::get('{inventory}/movements', [InventoryController::class, 'movements'])->name('movements');
    });
    // ─── Phase 3 Planning ───────────────────────────────────────────────────
    
    // Schedules
    Route::resource('schedules', App\Http\Controllers\ScheduleController::class);
    Route::get('schedules/{schedule}/wbs',                     [App\Http\Controllers\ScheduleController::class, 'wbs'])->name('schedules.wbs');
    Route::post('schedules/{schedule}/tasks',                  [App\Http\Controllers\ScheduleController::class, 'storeTask'])->name('schedules.tasks.store');
    Route::put('schedules/{schedule}/tasks/{task}',            [App\Http\Controllers\ScheduleController::class, 'updateTask'])->name('schedules.tasks.update');
    Route::delete('schedules/{schedule}/tasks/{task}',         [App\Http\Controllers\ScheduleController::class, 'destroyTask'])->name('schedules.tasks.destroy');
    Route::post('schedules/{schedule}/baselines',              [App\Http\Controllers\ScheduleController::class, 'storeBaseline'])->name('schedules.baselines.store');
    Route::post('schedules/{schedule}/send-to-coordinator',    [App\Http\Controllers\ScheduleController::class, 'sendToCoordinator'])->name('schedules.send-to-coordinator');

    // BOQ
    Route::resource('boqs', App\Http\Controllers\BoqController::class);
    Route::post('boqs/{boq}/approve', [App\Http\Controllers\BoqController::class, 'approve'])->name('boqs.approve');
    
    // BOQ Items
    Route::post('boqs/{boq}/items', [App\Http\Controllers\BoqItemController::class, 'store'])->name('boq_items.store');
    Route::put('boq-items/{item}', [App\Http\Controllers\BoqItemController::class, 'update'])->name('boq_items.update');
    Route::delete('boq-items/{item}', [App\Http\Controllers\BoqItemController::class, 'destroy'])->name('boq_items.destroy');

    // Takeoff
    Route::get('takeoff', [App\Http\Controllers\TakeoffController::class, 'index'])->name('takeoff.index');
    Route::get('takeoff/create', [App\Http\Controllers\TakeoffController::class, 'create'])->name('takeoff.create');
    Route::post('takeoff', [App\Http\Controllers\TakeoffController::class, 'store'])->name('takeoff.store');
    Route::get('takeoff/{takeoff}', [App\Http\Controllers\TakeoffController::class, 'show'])->name('takeoff.show');
    Route::delete('takeoff/{takeoff}', [App\Http\Controllers\TakeoffController::class, 'destroy'])->name('takeoff.destroy');
    Route::post('takeoff/{takeoff}/sections', [App\Http\Controllers\TakeoffController::class, 'storeSection'])->name('takeoff.sections.store');
    Route::get('takeoff/{takeoff}/convert', [App\Http\Controllers\TakeoffController::class, 'convert'])->name('takeoff.convert');

    
    // Takeoff Edit Requests
    Route::post('takeoff/{takeoff}/request-edit', [App\Http\Controllers\TakeoffController::class, 'requestEdit'])->name('takeoff.request-edit');
    Route::post('takeoff-edit-requests/{editRequest}/approve', [App\Http\Controllers\TakeoffController::class, 'approveEdit'])->name('takeoff.approve-edit');
    Route::post('takeoff-edit-requests/{editRequest}/reject', [App\Http\Controllers\TakeoffController::class, 'rejectEdit'])->name('takeoff.reject-edit');
    Route::post('takeoff-edit-requests/{editRequest}/revoke', [App\Http\Controllers\TakeoffController::class, 'revokeEdit'])->name('takeoff.revoke-edit');

    // Rebar Diameter → Product Mapping (Settings)
    Route::get('settings/rebar-products', [App\Http\Controllers\RebarDiaProductController::class, 'index'])->name('rebar-products.index');
    Route::post('settings/rebar-products', [App\Http\Controllers\RebarDiaProductController::class, 'update'])->name('rebar-products.update');
    Route::post('settings/rebar-products/seed', [App\Http\Controllers\RebarDiaProductController::class, 'seed'])->name('rebar-products.seed');

    // Planning Manager: Assign Team
    Route::get('planning-manager/team-assignment', [App\Http\Controllers\ProjectTeamController::class, 'index'])->name('planning.team.index');
    Route::post('planning-manager/team-assignment/{project}', [App\Http\Controllers\ProjectTeamController::class, 'update'])->name('planning.team.update');

    // ─── Planning Workflow (5-stage approval chain) ────────────────────────────
    Route::prefix('plan-workflow')->name('plan-workflow.')->group(function () {
        // Show workflow status for a project
        Route::get('/projects/{project}', [App\Http\Controllers\PlanWorkflowController::class, 'show'])->name('show');
        // Budget check API (for JS live bar)
        Route::get('/projects/{project}/budget-check', [App\Http\Controllers\PlanWorkflowController::class, 'budgetCheck'])->name('budget-check');
        // GM: Supplement budget
        Route::post('/projects/{project}/supplement', [App\Http\Controllers\PlanWorkflowController::class, 'supplementBudget'])->name('supplement');
        // Planning team: submit
        Route::post('/projects/{project}/submit', [App\Http\Controllers\PlanWorkflowController::class, 'submit'])->name('submit');
        // Approve steps
        Route::post('/{workflow}/approve-planning',    [App\Http\Controllers\PlanWorkflowController::class, 'approvePlanning'])->name('approve-planning');
        Route::post('/{workflow}/approve-coordinator', [App\Http\Controllers\PlanWorkflowController::class, 'approveCoordinator'])->name('approve-coordinator');
        Route::post('/{workflow}/approve-technical',   [App\Http\Controllers\PlanWorkflowController::class, 'approveTechnical'])->name('approve-technical');
        Route::post('/{workflow}/approve-gm',          [App\Http\Controllers\PlanWorkflowController::class, 'approveGm'])->name('approve-gm');
        // Reject
        Route::post('/{workflow}/reject', [App\Http\Controllers\PlanWorkflowController::class, 'reject'])->name('reject');
    });
    Route::get('takeoff/{takeoff}/sections/{section}/boq-items', [App\Http\Controllers\TakeoffController::class, 'getSectionBoqItems'])->name('takeoff.sections.boq-items');
    Route::get('takeoff/{takeoff}/items/create', [App\Http\Controllers\TakeoffController::class, 'createItem'])->name('takeoff.items.create');
    Route::post('takeoff/{takeoff}/items', [App\Http\Controllers\TakeoffController::class, 'storeItem'])->name('takeoff.items.store');
    Route::delete('takeoff/{takeoff}/items/{item}', [App\Http\Controllers\TakeoffController::class, 'destroyItem'])->name('takeoff.items.destroy');
    Route::patch('takeoff/{takeoff}/items/{item}', [App\Http\Controllers\TakeoffController::class, 'updateItem'])->name('takeoff.items.update');
    Route::patch('takeoff/{takeoff}/items/{item}/toggle-header', [App\Http\Controllers\TakeoffController::class, 'toggleHeader'])->name('takeoff.items.toggle-header');
    Route::delete('takeoff/{takeoff}/sections/{section}', [App\Http\Controllers\TakeoffController::class, 'destroySection'])->name('takeoff.sections.destroy');
    Route::get('takeoff/{takeoff}/convert', [App\Http\Controllers\TakeoffController::class, 'convert'])->name('takeoff.convert');
    Route::post('takeoff/{takeoff}/process-conversion', [App\Http\Controllers\TakeoffController::class, 'processConversion'])->name('takeoff.process-conversion');
    Route::post('takeoff/{takeoff}/rebar-cut-optimize', [App\Http\Controllers\TakeoffController::class, 'rebarCutOptimize'])->name('takeoff.rebar-cut-optimize');
    Route::post('takeoff/{takeoff}/rebar-erp-convert', [App\Http\Controllers\TakeoffController::class, 'rebarConvertToErpPlan'])->name('takeoff.rebar-erp-convert');

    // ERP Plans
    Route::resource('erp-plans', App\Http\Controllers\ErpPlanController::class);

    // Standard Works (Conversion Ratios)
    Route::resource('standard-works', App\Http\Controllers\StandardWorkController::class);

    // Manpower Roles (predefined selectable list)
    Route::get('manpower-roles',              [App\Http\Controllers\ManpowerRoleController::class, 'index'])->name('manpower-roles.index');
    Route::post('manpower-roles',             [App\Http\Controllers\ManpowerRoleController::class, 'store'])->name('manpower-roles.store');
    Route::delete('manpower-roles/{manpowerRole}', [App\Http\Controllers\ManpowerRoleController::class, 'destroy'])->name('manpower-roles.destroy');



    // Weekly Dispatches
    Route::resource('weekly-dispatches', App\Http\Controllers\WeeklyDispatchController::class)->only(['index', 'show']);
    // ─── Phase 4 Procurement ────────────────────────────────────────────────

    Route::resource('suppliers', App\Http\Controllers\SupplierController::class);
    
    Route::resource('transfers', App\Http\Controllers\TransferController::class)->except(['edit', 'update', 'destroy']);
    Route::post('transfers/{transfer}/approve', [App\Http\Controllers\TransferController::class, 'approve'])->name('transfers.approve');
    Route::post('transfers/{transfer}/reject', [App\Http\Controllers\TransferController::class, 'reject'])->name('transfers.reject');
    Route::post('transfers/{transfer}/complete', [App\Http\Controllers\TransferController::class, 'complete'])->name('transfers.complete');

    // Procurement & Purchasing
    Route::resource('purchase-requests', App\Http\Controllers\PurchaseRequestController::class)->except(['edit', 'update', 'destroy']);
    Route::post('purchase-requests/{purchaseRequest}/submit', [App\Http\Controllers\PurchaseRequestController::class, 'submit'])->name('purchase-requests.submit');
    Route::post('purchase-requests/{purchaseRequest}/approve', [App\Http\Controllers\PurchaseRequestController::class, 'approve'])->name('purchase-requests.approve');
    Route::post('purchase-requests/{purchaseRequest}/reject', [App\Http\Controllers\PurchaseRequestController::class, 'reject'])->name('purchase-requests.reject');

    // ── Procurement Lifecycle Upgraded Routes ──────────────────────────────
    Route::get('procurement/my-queue', [App\Http\Controllers\ProcurementLifecycleController::class, 'myQueue'])->name('procurement.my-queue');

    Route::post('purchase-requests/{purchaseRequest}/send-to-pm', [App\Http\Controllers\PurchaseRequestController::class, 'sendToProcurementManager'])->name('purchase-requests.send-to-pm');
    Route::post('purchase-requests/{purchaseRequest}/selective-transfer', [App\Http\Controllers\PurchaseRequestController::class, 'selectiveTransfer'])->name('purchase-requests.selective-transfer');
    Route::post('purchase-requests/{purchaseRequest}/selective-send-to-pm', [App\Http\Controllers\PurchaseRequestController::class, 'selectiveSendToPm'])->name('purchase-requests.selective-send-to-pm');
    Route::post('purchase-requests/{purchaseRequest}/split-and-process', [App\Http\Controllers\PurchaseRequestController::class, 'splitAndProcess'])->name('purchase-requests.split-and-process');
    Route::post('purchase-requests/{purchaseRequest}/send-back-to-store', [App\Http\Controllers\PurchaseRequestController::class, 'sendBackToStoreManager'])->name('purchase-requests.send-back-to-store');
    Route::post('purchase-requests/{purchaseRequest}/send-to-proc-team', [App\Http\Controllers\PurchaseRequestController::class, 'sendToProcurementTeam'])->name('purchase-requests.send-to-proc-team');
    Route::post('purchase-requests/{purchaseRequest}/submit-direct-buy', [App\Http\Controllers\PurchaseRequestController::class, 'submitDirectBuy'])->name('purchase-requests.submit-direct-buy');
    Route::post('purchase-requests/{purchaseRequest}/submit-proformas', [App\Http\Controllers\PurchaseRequestController::class, 'submitProformas'])->name('purchase-requests.submit-proformas');
    Route::post('purchase-requests/{purchaseRequest}/add-marketing-variance', [App\Http\Controllers\PurchaseRequestController::class, 'addMarketingVariance'])->name('purchase-requests.add-marketing-variance');
    Route::post('purchase-requests/{purchaseRequest}/select-proformas', [App\Http\Controllers\PurchaseRequestController::class, 'selectProformas'])->name('purchase-requests.select-proformas');
    Route::post('purchase-requests/{purchaseRequest}/gm-decide', [App\Http\Controllers\PurchaseRequestController::class, 'gmDecide'])->name('purchase-requests.gm-decide');
    Route::post('purchase-requests/{purchaseRequest}/finance-credit-approve', [App\Http\Controllers\PurchaseRequestController::class, 'financeCreditApprove'])->name('purchase-requests.finance-credit-approve');
    Route::post('purchase-requests/{purchaseRequest}/assign-payment', [App\Http\Controllers\PurchaseRequestController::class, 'assignPayment'])->name('purchase-requests.assign-payment');
    Route::post('purchase-requests/{purchaseRequest}/execute-payment', [App\Http\Controllers\PurchaseRequestController::class, 'executePayment'])->name('purchase-requests.execute-payment');
    Route::post('purchase-requests/{purchaseRequest}/upload-receipt', [App\Http\Controllers\PurchaseRequestController::class, 'uploadReceipt'])->name('purchase-requests.upload-receipt');
    Route::post('purchase-requests/{purchaseRequest}/verify-receipt', [App\Http\Controllers\PurchaseRequestController::class, 'verifyReceipt'])->name('purchase-requests.verify-receipt');
    Route::post('purchase-requests/{purchaseRequest}/book-driver', [App\Http\Controllers\PurchaseRequestController::class, 'bookDriver'])->name('purchase-requests.book-driver');
    Route::post('purchase-requests/{purchaseRequest}/store-intake', [App\Http\Controllers\PurchaseRequestController::class, 'storeIntake'])->name('purchase-requests.store-intake');

    // Emergency & Standard MR Planning Approvals & Dispatch
    Route::post('material-requests/{materialRequest}/planning-approve', [App\Http\Controllers\MaterialRequestController::class, 'planningApprove'])->name('material-requests.planning-approve');
    Route::post('material-requests/{materialRequest}/planning-reject', [App\Http\Controllers\MaterialRequestController::class, 'planningReject'])->name('material-requests.planning-reject');
    Route::post('material-requests/{materialRequest}/coordinator-dispatch', [App\Http\Controllers\MaterialRequestController::class, 'coordinatorDispatch'])->name('material-requests.coordinator-dispatch');
    Route::post('material-requests/{materialRequest}/send-to-pr', [App\Http\Controllers\MaterialRequestController::class, 'sendToPr'])->name('material-requests.send-to-pr');
    Route::post('material-requests/{materialRequest}/create-transfer', [App\Http\Controllers\MaterialRequestController::class, 'createTransfer'])->name('material-requests.create-transfer');


    Route::get('price-intelligence', [App\Http\Controllers\ProcurementController::class, 'priceIntelligence'])->name('price-intelligence.index');
    Route::get('material-demand', [App\Http\Controllers\ProcurementController::class, 'materialDemand'])->name('material-demand.index');

    Route::resource('delivery-receipts', App\Http\Controllers\DeliveryReceiptController::class)->only(['index', 'create', 'store', 'show']);
    
    Route::resource('subcon-agreements', App\Http\Controllers\SubconAgreementController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('subcon-agreements/{subconAgreement}/approve', [App\Http\Controllers\SubconAgreementController::class, 'approve'])->name('subcon-agreements.approve');
    Route::post('subcon-agreements/{subconAgreement}/reject', [App\Http\Controllers\SubconAgreementController::class, 'reject'])->name('subcon-agreements.reject');
    Route::post('subcon-agreements/{subconAgreement}/activate', [App\Http\Controllers\SubconAgreementController::class, 'activate'])->name('subcon-agreements.activate');
    Route::get('subcon-agreements/{subconAgreement}/takeoff-items', [App\Http\Controllers\SubconAgreementController::class, 'getTakeoffItems'])->name('subcon-agreements.getTakeoffItems');
    Route::resource('ipcs', App\Http\Controllers\IpcRecordController::class)->only(['index', 'create', 'store', 'show']);

    // Material Requests
    Route::resource('material-requests', App\Http\Controllers\MaterialRequestController::class)
         ->except(['edit', 'update', 'destroy']);
    Route::post('material-requests/{materialRequest}/status',
        [App\Http\Controllers\MaterialRequestController::class, 'updateStatus'])
        ->name('material-requests.updateStatus');
        
    // Material Damage Reports
    Route::resource('material-damage-reports', App\Http\Controllers\MaterialDamageReportController::class)->only(['index', 'create', 'store', 'show']);

    // Tool Transactions
    Route::resource('tool-transactions', App\Http\Controllers\ToolTransactionController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('tool-transactions/{toolTransaction}/checkin', [App\Http\Controllers\ToolTransactionController::class, 'checkin'])->name('tool-transactions.checkin');

    // Material Request Items
    Route::post('material-requests/{materialRequest}/items',
        [App\Http\Controllers\MaterialRequestItemController::class, 'store'])
        ->name('mr-items.store');
    Route::delete('mr-items/{item}',
        [App\Http\Controllers\MaterialRequestItemController::class, 'destroy'])
        ->name('mr-items.destroy');

    // Purchase Orders
    Route::resource('purchase-orders', App\Http\Controllers\PurchaseOrderController::class)
         ->only(['index', 'create', 'store', 'show']);
    Route::post('purchase-orders/{purchaseOrder}/issue',
        [App\Http\Controllers\PurchaseOrderController::class, 'issue'])
        ->name('purchase-orders.issue');

    // Purchase Order Items
    Route::post('purchase-orders/{purchaseOrder}/items',
        [App\Http\Controllers\PurchaseOrderItemController::class, 'store'])
        ->name('po-items.store');
    Route::delete('po-items/{item}',
        [App\Http\Controllers\PurchaseOrderItemController::class, 'destroy'])
        ->name('po-items.destroy');

    // ─── Phase 6 HR ─────────────────────────────────────────────────────────

    Route::resource('departments', App\Http\Controllers\DepartmentController::class)->except(['show', 'destroy']);
    Route::resource('attendance', App\Http\Controllers\AttendanceController::class)->only(['index', 'create', 'store']);
    Route::post('attendance/quick-clock', [App\Http\Controllers\AttendanceController::class, 'quickClock'])->name('attendance.quickClock');
    Route::post('attendance/bulk', [App\Http\Controllers\AttendanceController::class, 'bulkStore'])->name('attendance.bulkStore');
    Route::get('attendance/device-logs', [App\Http\Controllers\AttendanceController::class, 'deviceLogs'])->name('attendance.deviceLogs');
    Route::post('attendance/zkteco-sync', [App\Http\Controllers\AttendanceController::class, 'syncZkteco'])->name('attendance.zkteco-sync');
    Route::get('attendance/zkteco-status', [App\Http\Controllers\AttendanceController::class, 'zktecoStatus'])->name('attendance.zkteco-status');

    Route::get('employees/pending-approval', [App\Http\Controllers\EmployeeController::class, 'pendingApproval'])->name('employees.pending-approval');
    Route::post('employees/bulk-approve', [App\Http\Controllers\EmployeeController::class, 'bulkApprove'])->name('employees.bulk-approve');
    Route::post('employees/bulk-reject', [App\Http\Controllers\EmployeeController::class, 'bulkReject'])->name('employees.bulk-reject');
    Route::put('employees/{employee}/approve', [App\Http\Controllers\EmployeeController::class, 'approve'])->name('employees.approve');
    Route::put('employees/{employee}/reject', [App\Http\Controllers\EmployeeController::class, 'reject'])->name('employees.reject');
    Route::get('employees/education/{education}/certificate', [App\Http\Controllers\FileStreamController::class, 'viewCertificate'])->name('employees.education.certificate');
    Route::get('employees/experience/{experience}/license', [App\Http\Controllers\FileStreamController::class, 'viewLicense'])->name('employees.experience.license');
    Route::get('employees/experience/{experience}/letter', [App\Http\Controllers\FileStreamController::class, 'viewExperienceLetter'])->name('employees.experience.letter');
    Route::get('employees/{employee}/guarantee-letter-file', [App\Http\Controllers\FileStreamController::class, 'viewGuaranteeLetter'])->name('employees.guarantee-letter.view');
    Route::resource('employees', App\Http\Controllers\EmployeeController::class);
    Route::post('employees/{employee}/upload-guarantee', [App\Http\Controllers\EmployeeController::class, 'uploadGuaranteeLetter'])->name('employees.upload-guarantee');
    Route::resource('contracts', App\Http\Controllers\EmployeeContractController::class)->only(['index', 'create', 'store', 'show']);
    Route::resource('manpower-requests', App\Http\Controllers\ManpowerRequestController::class)->only(['index', 'create', 'store', 'show']);

    // ─── Asset Management ────────────────────────────────────────────────────────
    Route::prefix('assets')->name('assets.')->group(function () {
        Route::get('dashboard', [App\Http\Controllers\AssetDashboardController::class, 'index'])->name('dashboard');
        Route::get('export', [App\Http\Controllers\AssetDashboardController::class, 'export'])->name('export');
        Route::get('by-status/{status}', [App\Http\Controllers\AssetDashboardController::class, 'byStatus'])->name('by-status');
        Route::get('by-employee/{employeeId}', [App\Http\Controllers\AssetDashboardController::class, 'byEmployee'])->name('by-employee');
        Route::get('by-department/{department}', [App\Http\Controllers\AssetDashboardController::class, 'byDepartment'])->name('by-department');
    });
    
    Route::prefix('employee-assets')->name('employee-assets.')->group(function () {
        Route::get('{employeeAsset}/return', [App\Http\Controllers\EmployeeAssetController::class, 'returnForm'])->name('return');
        Route::put('{employeeAsset}/return', [App\Http\Controllers\EmployeeAssetController::class, 'returnStore'])->name('return-store');
        Route::get('{employeeAsset}/damage', [App\Http\Controllers\EmployeeAssetController::class, 'damageForm'])->name('damage');
        Route::put('{employeeAsset}/damage', [App\Http\Controllers\EmployeeAssetController::class, 'damageStore'])->name('damage-store');
        Route::get('{employeeAsset}', [App\Http\Controllers\EmployeeAssetController::class, 'show'])->name('show');
    });
    
    // ─── HR Manager Dashboard ────────────────────────────────────────────────────
    Route::middleware('auth')->prefix('hr-manager')->name('hr-manager.')->group(function () {
        Route::get('dashboard', [App\Http\Controllers\HRManagerController::class, 'dashboard'])->name('dashboard');
        Route::get('employees', [App\Http\Controllers\HRManagerController::class, 'employees'])->name('employees');
        Route::get('statistics', [App\Http\Controllers\HRManagerController::class, 'getStatisticsApi'])->name('statistics');
        Route::get('approvals', [App\Http\Controllers\HRManagerController::class, 'getPendingApprovals'])->name('approvals');
    });

    // ─── Asset Reports ──────────────────────────────────────────────────────────
    Route::prefix('asset-reports')->name('asset-reports.')->group(function () {
        Route::get('utilization', [App\Http\Controllers\AssetReportController::class, 'utilization'])->name('utilization');
        Route::get('export-utilization', [App\Http\Controllers\AssetReportController::class, 'exportUtilization'])->name('export-utilization');
        Route::get('damage', [App\Http\Controllers\AssetReportController::class, 'damage'])->name('damage');
        Route::get('export-damage', [App\Http\Controllers\AssetReportController::class, 'exportDamage'])->name('export-damage');
        Route::get('employee-allocation', [App\Http\Controllers\AssetReportController::class, 'employeeAllocation'])->name('employee-allocation');
        Route::get('export-employee-allocation', [App\Http\Controllers\AssetReportController::class, 'exportEmployeeAllocation'])->name('export-employee-allocation');
    });

    // ─── Leave Management ────────────────────────────────────────────────────────
    Route::resource('leave-requests', App\Http\Controllers\LeaveRequestController::class)->only(['index', 'create', 'store', 'show']);
    Route::get('leave-requests/my', [App\Http\Controllers\LeaveRequestController::class, 'myRequests'])->name('leave-requests.my-requests');
    Route::post('leave-requests/{leaveRequest}/approve', [App\Http\Controllers\LeaveRequestController::class, 'approve'])->name('leave-requests.approve');
    Route::post('leave-requests/{leaveRequest}/reject', [App\Http\Controllers\LeaveRequestController::class, 'reject'])->name('leave-requests.reject');
    Route::post('leave-requests/bulk-approve', [App\Http\Controllers\LeaveRequestController::class, 'bulkApprove'])->name('leave-requests.bulkApprove');
    Route::get('leave-requests/balance/{employee}', [App\Http\Controllers\LeaveRequestController::class, 'getBalance'])->name('leave-requests.getBalance');
    Route::get('leave-requests/export', [App\Http\Controllers\LeaveRequestController::class, 'exportReport'])->name('leave-requests.export');

    // ─── Manpower Forecast ──────────────────────────────────────────────────────
    Route::resource('manpower-forecast', App\Http\Controllers\ManpowerForecastController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('manpower-forecast/{manpowerForecast}/assign', [App\Http\Controllers\ManpowerForecastController::class, 'assignEmployee'])->name('manpower-forecast.assignEmployee');
    
    // ─── Finance Dashboard ──────────────────────────────────────────────────────
    Route::get('/finance-dashboard', [App\Http\Controllers\DashboardController::class, 'finance'])->name('finance.dashboard');
    Route::get('/finance-dashboard/revenue-data', function() {
        $months = [];
        $incomeData = [];
        $expenseData = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthDate = \Carbon\Carbon::now()->subMonths($i);
            $months[] = $monthDate->format('M');
            $inc = (float) \Illuminate\Support\Facades\DB::table('payments')
                ->whereMonth('payment_date', $monthDate->month)
                ->whereYear('payment_date', $monthDate->year)
                ->sum('amount');
            $exp = (float) \Illuminate\Support\Facades\DB::table('expenses')
                ->whereMonth('expense_date', $monthDate->month)
                ->whereYear('expense_date', $monthDate->year)
                ->sum('amount');
            $incomeData[] = $inc;
            $expenseData[] = $exp;
        }
        return response()->json([
            'labels' => $months,
            'income' => $incomeData,
            'expenses' => $expenseData,
        ]);
    })->name('finance.dashboard.revenue-data');

    // ─── Assigned Accounts Portal ───────────────────────────────────────────────
    Route::get('/assigned-accounts', [App\Http\Controllers\AssignedAccountController::class, 'index'])->name('assigned-accounts.index');
    Route::get('/assigned-accounts/{id}', [App\Http\Controllers\AssignedAccountController::class, 'show'])->name('assigned-accounts.show');
    Route::post('/assigned-accounts/{id}/pay', [App\Http\Controllers\AssignedAccountController::class, 'pay'])->name('assigned-accounts.pay');

    Route::delete('manpower-assignment/{manpowerAssignment}', [App\Http\Controllers\ManpowerForecastController::class, 'removeAssignment'])->name('manpower-assignment.remove');
    Route::post('manpower-forecast/{manpowerForecast}/submit', [App\Http\Controllers\ManpowerForecastController::class, 'submit'])->name('manpower-forecast.submit');
    Route::post('manpower-forecast/{manpowerForecast}/approve', [App\Http\Controllers\ManpowerForecastController::class, 'approve'])->name('manpower-forecast.approve');
    Route::post('manpower-forecast/{manpowerForecast}/reject', [App\Http\Controllers\ManpowerForecastController::class, 'reject'])->name('manpower-forecast.reject');
    Route::get('manpower-forecast/export', [App\Http\Controllers\ManpowerForecastController::class, 'exportCSV'])->name('manpower-forecast.export');
    Route::get('resource-availability', [App\Http\Controllers\ManpowerForecastController::class, 'getResourceAvailability'])->name('resource-availability.get');

    // ─── Performance Dashboard ──────────────────────────────────────────────────
    Route::get('performance-dashboard', [App\Http\Controllers\PerformanceDashboardController::class, 'index'])->name('performance-dashboard.index');
    Route::get('performance-dashboard/create-review', [App\Http\Controllers\PerformanceDashboardController::class, 'createReview'])->name('performance-dashboard.create-review');
    Route::post('performance-dashboard/review', [App\Http\Controllers\PerformanceDashboardController::class, 'storeReview'])->name('performance-dashboard.store-review');
    Route::get('performance-dashboard/review/{performanceReview}', [App\Http\Controllers\PerformanceDashboardController::class, 'showReview'])->name('performance-dashboard.show-review');
    Route::post('performance-dashboard/review/{performanceReview}/submit', [App\Http\Controllers\PerformanceDashboardController::class, 'submitReview'])->name('performance-dashboard.submit-review');
    Route::post('performance-dashboard/review/{performanceReview}/approve', [App\Http\Controllers\PerformanceDashboardController::class, 'approveReview'])->name('performance-dashboard.approve-review');
    Route::post('performance-dashboard/review/{performanceReview}/reject', [App\Http\Controllers\PerformanceDashboardController::class, 'rejectReview'])->name('performance-dashboard.reject-review');
    Route::get('performance-dashboard/employee/{employee}', [App\Http\Controllers\PerformanceDashboardController::class, 'showEmployee'])->name('performance-dashboard.show-employee');
    Route::get('performance-dashboard/analytics', [App\Http\Controllers\PerformanceDashboardController::class, 'analytics'])->name('performance-dashboard.analytics');
    Route::get('performance-dashboard/export', [App\Http\Controllers\PerformanceDashboardController::class, 'exportReport'])->name('performance-dashboard.export');

    // ─── Enhanced Contract Management ────────────────────────────────────────────
    Route::get('contracts', [App\Http\Controllers\EmployeeContractManagementController::class, 'index'])->name('contracts.index');
    Route::get('contracts/create', [App\Http\Controllers\EmployeeContractManagementController::class, 'create'])->name('contracts.create');
    Route::post('contracts', [App\Http\Controllers\EmployeeContractManagementController::class, 'store'])->name('contracts.store');
    Route::get('contracts/{employeeContract}', [App\Http\Controllers\EmployeeContractManagementController::class, 'show'])->name('contracts.show');
    Route::post('contracts/{employeeContract}/submit', [App\Http\Controllers\EmployeeContractManagementController::class, 'submitForApproval'])->name('contracts.submit');
    Route::post('contract-approval/{contractApproval}/approve', [App\Http\Controllers\EmployeeContractManagementController::class, 'approve'])->name('contracts.approve');
    Route::post('contract-approval/{contractApproval}/reject', [App\Http\Controllers\EmployeeContractManagementController::class, 'reject'])->name('contracts.reject');
    Route::post('contracts/{employeeContract}/milestone', [App\Http\Controllers\EmployeeContractManagementController::class, 'addMilestone'])->name('contracts.milestone');
    Route::post('contracts/{employeeContract}/renewal', [App\Http\Controllers\EmployeeContractManagementController::class, 'requestRenewal'])->name('contracts.renewal-request');
    Route::post('contract-renewal/{contractRenewal}/approve', [App\Http\Controllers\EmployeeContractManagementController::class, 'approveRenewal'])->name('contracts.renewal-approve');
    Route::post('contracts/{employeeContract}/amendment', [App\Http\Controllers\EmployeeContractManagementController::class, 'requestAmendment'])->name('contracts.amendment-request');
    Route::post('contract-amendment/{contractAmendment}/approve', [App\Http\Controllers\EmployeeContractManagementController::class, 'approveAmendment'])->name('contracts.amendment-approve');
    Route::get('contracts/expiring/list', [App\Http\Controllers\EmployeeContractManagementController::class, 'expiringContracts'])->name('contracts.expiring');
    Route::get('contracts/export', [App\Http\Controllers\EmployeeContractManagementController::class, 'exportReport'])->name('contracts.export');

    // ─── Payroll Integration ────────────────────────────────────────────────────
    Route::get('payroll/dashboard', [App\Http\Controllers\PayrollIntegrationController::class, 'dashboard'])->name('payroll.dashboard');
    Route::get('payroll/employee/{employee}', [App\Http\Controllers\PayrollIntegrationController::class, 'employeePayroll'])->name('payroll.employee');
    Route::get('payroll/salary-structures', [App\Http\Controllers\PayrollIntegrationController::class, 'salaryStructures'])->name('payroll.salary-structures');
    Route::get('payroll/salary-structures/create', [App\Http\Controllers\PayrollIntegrationController::class, 'createSalaryStructure'])->name('payroll.salary-structure-create');
    Route::post('payroll/salary-structures', [App\Http\Controllers\PayrollIntegrationController::class, 'storeSalaryStructure'])->name('payroll.salary-structure-store');
    Route::get('payroll/advances', [App\Http\Controllers\PayrollIntegrationController::class, 'advances'])->name('payroll.advances');
    Route::post('payroll/advances/request', [App\Http\Controllers\PayrollIntegrationController::class, 'requestAdvance'])->name('payroll.advance-request');
    Route::post('payroll/advances/{employeeAdvance}/approve', [App\Http\Controllers\PayrollIntegrationController::class, 'approveAdvance'])->name('payroll.advance-approve');
    Route::post('payroll/advances/{employeeAdvance}/reject', [App\Http\Controllers\PayrollIntegrationController::class, 'rejectAdvance'])->name('payroll.advance-reject');
    Route::post('payroll/advances/{employeeAdvance}/disburse', [App\Http\Controllers\PayrollIntegrationController::class, 'disburseAdvance'])->name('payroll.advance-disburse');
    Route::get('payroll/monthly-status', [App\Http\Controllers\PayrollIntegrationController::class, 'monthlyStatus'])->name('payroll.monthly-status');
    Route::get('payroll/analytics', [App\Http\Controllers\PayrollIntegrationController::class, 'analytics'])->name('payroll.analytics');

    // ─── HR Reports ─────────────────────────────────────────────────────────────
    Route::get('reports/attendance', [App\Http\Controllers\HRReportsController::class, 'attendanceReport'])->name('reports.attendance');
    Route::get('reports/turnover', [App\Http\Controllers\HRReportsController::class, 'turnoverReport'])->name('reports.turnover');
    Route::get('reports/cost-analysis', [App\Http\Controllers\HRReportsController::class, 'costAnalysisReport'])->name('reports.cost-analysis');
    Route::get('reports/leave-analysis', [App\Http\Controllers\HRReportsController::class, 'leaveAnalysisReport'])->name('reports.leave-analysis');
    Route::get('reports/employee-cost', [App\Http\Controllers\HRReportsController::class, 'employeeCostReport'])->name('reports.employee-cost');
    Route::get('reports/attendance/export', [App\Http\Controllers\HRReportsController::class, 'exportAttendanceCSV'])->name('reports.attendance.export');

    // ─── Employee Self-Service Portal ───────────────────────────────────────────
    Route::get('employee/dashboard', [App\Http\Controllers\EmployeeSelfServiceController::class, 'dashboard'])->name('employee.dashboard');
    Route::get('employee/attendance', [App\Http\Controllers\EmployeeSelfServiceController::class, 'viewAttendance'])->name('employee.attendance');
    Route::get('employee/payroll', [App\Http\Controllers\EmployeeSelfServiceController::class, 'viewPayroll'])->name('employee.payroll');
    Route::get('employee/contract', [App\Http\Controllers\EmployeeSelfServiceController::class, 'viewContract'])->name('employee.contract');
    Route::get('employee/leave-history', [App\Http\Controllers\EmployeeSelfServiceController::class, 'viewLeaveHistory'])->name('employee.leave-history');
    Route::get('employee/performance', [App\Http\Controllers\EmployeeSelfServiceController::class, 'viewPerformance'])->name('employee.performance');
    Route::get('employee/achievements', [App\Http\Controllers\EmployeeSelfServiceController::class, 'viewAchievements'])->name('employee.achievements');
    Route::get('employee/leave-balance', [App\Http\Controllers\EmployeeSelfServiceController::class, 'viewLeaveBalance'])->name('employee.leave-balance');
    Route::post('employee/profile', [App\Http\Controllers\EmployeeSelfServiceController::class, 'updateProfile'])->name('employee.profile.update');
    Route::get('employee/payroll/{payroll}/download', [App\Http\Controllers\EmployeeSelfServiceController::class, 'downloadPayrollSlip'])->name('employee.payroll.download');
    Route::get('employee/contract/{contract}/download', [App\Http\Controllers\EmployeeSelfServiceController::class, 'downloadContract'])->name('employee.contract.download');

    Route::resource('payrolls',  App\Http\Controllers\PayrollController::class)->only(['index','create','store','show']);
    Route::post('payrolls/{payroll}/mark-paid',
        [App\Http\Controllers\PayrollController::class, 'markPaid'])
        ->name('payrolls.markPaid');

    // ─── Finance Head & GM Payroll Workflow ─────────────────────────────────
    Route::get('finance/payroll',               [App\Http\Controllers\FinancePayrollController::class, 'index'])->name('finance.payroll.index');
    Route::post('finance/payroll/generate',      [App\Http\Controllers\FinancePayrollController::class, 'generate'])->name('finance.payroll.generate');
    Route::post('finance/payroll/submit-gm',     [App\Http\Controllers\FinancePayrollController::class, 'submitToGM'])->name('finance.payroll.submit-gm');

    Route::get('finance/payroll/gm-approval',   [App\Http\Controllers\FinancePayrollController::class, 'gmIndex'])->name('finance.payroll.gm');
    Route::get('finance/payroll/gm-detail',     [App\Http\Controllers\FinancePayrollController::class, 'gmBatchDetail'])->name('finance.payroll.gm.detail');
    Route::post('finance/payroll/gm-approve',   [App\Http\Controllers\FinancePayrollController::class, 'gmApprove'])->name('finance.payroll.gm.approve');
    Route::post('finance/payroll/gm-reject',    [App\Http\Controllers\FinancePayrollController::class, 'gmReject'])->name('finance.payroll.gm.reject');

    // ─── Phase 5 Finance ────────────────────────────────────────────────────

    Route::resource('coa', App\Http\Controllers\ChartOfAccountController::class)->except(['show', 'destroy']);
    Route::resource('coa-transfers', App\Http\Controllers\CoaTransferController::class)->only(['index', 'create', 'store', 'show']);
    Route::resource('bank-accounts', App\Http\Controllers\BankAccountController::class)->except(['destroy']);
    
    Route::resource('income', App\Http\Controllers\IncomeController::class)->except(['edit', 'update', 'destroy']);
    Route::post('income/{income}/confirm', [App\Http\Controllers\IncomeController::class, 'confirm'])->name('income.confirm');

    Route::resource('journal-entries', App\Http\Controllers\JournalEntryController::class)->only(['index', 'create', 'store', 'show']);
    Route::resource('budgets', App\Http\Controllers\ProjectBudgetController::class)->except(['show', 'destroy']);
    Route::resource('emergency-funds', App\Http\Controllers\EmergencyFundController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('emergency-funds/{emergencyFund}/approve', [App\Http\Controllers\EmergencyFundController::class, 'approve'])->name('emergency-funds.approve');
    Route::post('emergency-funds/{emergencyFund}/reject', [App\Http\Controllers\EmergencyFundController::class, 'reject'])->name('emergency-funds.reject');

    Route::resource('schedules', App\Http\Controllers\ScheduleController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('schedules/{schedule}/approve', [App\Http\Controllers\ScheduleController::class, 'approve'])->name('schedules.approve');
    
    Route::get('dispatches/active-tasks', [App\Http\Controllers\DispatchController::class, 'getActiveTasks'])->name('dispatches.active-tasks');
    Route::resource('dispatches', App\Http\Controllers\DispatchController::class)->only(['index', 'create', 'store', 'show']);
    Route::get('expenses', [App\Http\Controllers\ApprovalHubController::class, 'index'])->name('expenses.index');
    Route::resource('expenses', App\Http\Controllers\ExpenseController::class)->only(['create','store','show']);
    Route::post('expenses/{expense}/approve', [App\Http\Controllers\ExpenseController::class, 'approve'])->name('expenses.approve');
    Route::post('expenses/{expense}/reject',
        [App\Http\Controllers\ExpenseController::class, 'reject'])->name('expenses.reject');

    // Central Approval Hub
    Route::get('approvals', [App\Http\Controllers\ApprovalHubController::class, 'index'])->name('approvals.index');

    // Reports Hub
    Route::get('finance/reports', [App\Http\Controllers\FinanceReportController::class, 'index'])->name('reports.index');
    Route::get('finance/reports/trial-balance', [App\Http\Controllers\FinanceReportController::class, 'trialBalance'])->name('reports.trial-balance');
    Route::get('finance/reports/income-statement', [App\Http\Controllers\FinanceReportController::class, 'incomeStatement'])->name('reports.income-statement');
    Route::get('finance/reports/balance-sheet', [App\Http\Controllers\FinanceReportController::class, 'balanceSheet'])->name('reports.balance-sheet');
    Route::get('finance/reports/cash-flow', [App\Http\Controllers\FinanceReportController::class, 'cashFlow'])->name('reports.cash-flow');
    Route::get('finance/reports/general-ledger', [App\Http\Controllers\FinanceReportController::class, 'generalLedger'])->name('reports.general-ledger');
    Route::get('finance/reports/expense-by-site', [App\Http\Controllers\FinanceReportController::class, 'expenseBySite'])->name('reports.expense-by-site');

    Route::resource('payments', App\Http\Controllers\PaymentController::class)->only(['index','create','store','show']);

    // ─── Phase 8 Operational ────────────────────────────────────────────────
    Route::resource('material-plans', App\Http\Controllers\MaterialPlanController::class)->only(['index', 'create', 'store', 'show']);
    
    Route::resource('material-usages', App\Http\Controllers\MaterialUsageController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('material-usages/{materialUsage}/confirm', [App\Http\Controllers\MaterialUsageController::class, 'confirm'])->name('material-usages.confirm');

    Route::resource('delivery-receipts', App\Http\Controllers\DeliveryReceiptController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('delivery-receipts/{deliveryReceipt}/receive', [App\Http\Controllers\DeliveryReceiptController::class, 'receive'])->name('delivery-receipts.receive');

    Route::resource('transfers', App\Http\Controllers\TransferController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('transfers/{transfer}/approve', [App\Http\Controllers\TransferController::class, 'approve'])->name('transfers.approve');
    
    // ─── Phase 7 Subcontractors ─────────────────────────────────────────────
    Route::resource('subcontractors', App\Http\Controllers\SubcontractorController::class)->only(['index', 'create', 'store', 'show']);
    Route::resource('ipcs', App\Http\Controllers\IpcController::class)->only(['index', 'create', 'store', 'show']);

    // ─── Client IPCs (Company → Client Payment Certificates) ─────────────────────
    Route::resource('client-ipcs', App\Http\Controllers\ClientIpcController::class)
         ->only(['index', 'create', 'store', 'show', 'edit', 'update']);
    Route::post('client-ipcs/{clientIpc}/submit',         [App\Http\Controllers\ClientIpcController::class, 'submit'])->name('client-ipcs.submit');
    Route::post('client-ipcs/{clientIpc}/approve',        [App\Http\Controllers\ClientIpcController::class, 'approve'])->name('client-ipcs.approve');
    Route::post('client-ipcs/{clientIpc}/record-payment', [App\Http\Controllers\ClientIpcController::class, 'recordPayment'])->name('client-ipcs.record-payment');
    Route::get('client-ipcs-boq-items',                   [App\Http\Controllers\ClientIpcController::class, 'boqItems'])->name('client-ipcs.boq-items');


    Route::resource('cut-optimizations', App\Http\Controllers\CutOptimizationController::class)->only(['index', 'create', 'store', 'show']);
    
    Route::resource('issues', App\Http\Controllers\IssueController::class)->only(['index', 'create', 'store', 'show']);
    
    Route::resource('waste', App\Http\Controllers\WasteController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('waste/{waste}/verify', [App\Http\Controllers\WasteController::class, 'verify'])->name('waste.verify');
    
    Route::get('weekly-manpower-report', [App\Http\Controllers\WeeklyManpowerReportController::class, 'index'])->name('weekly-manpower.index');
    Route::post('weekly-manpower-report/send-gm', [App\Http\Controllers\WeeklyManpowerReportController::class, 'sendToGM'])->name('weekly-manpower.sendGM');
    Route::get('weekly-manpower-report/export', [App\Http\Controllers\WeeklyManpowerReportController::class, 'exportCSV'])->name('weekly-manpower.export');
    Route::resource('daily-reports', App\Http\Controllers\DailyReportController::class)->only(['index', 'create', 'store', 'show']);
    Route::get('daily-reports/approval', [App\Http\Controllers\DailyReportController::class, 'approvalDashboard'])->name('daily-reports.approval');
    Route::post('daily-reports/{dailyReport}/approve', [App\Http\Controllers\DailyReportController::class, 'approve'])->name('daily-reports.approve');
    Route::post('daily-reports/{dailyReport}/reject', [App\Http\Controllers\DailyReportController::class, 'reject'])->name('daily-reports.reject');
    Route::post('daily-reports/bulk-approve', [App\Http\Controllers\DailyReportController::class, 'bulkApprove'])->name('daily-reports.bulkApprove');
    Route::get('daily-reports/stats/manpower', [App\Http\Controllers\DailyReportController::class, 'getManpowerStats'])->name('daily-reports.manpowerStats');
    Route::resource('weekly-reports', App\Http\Controllers\WeeklyReportController::class)->only(['index', 'create', 'store', 'show']);

    // ─── Phase 9 Communication ──────────────────────────────────────────────
    Route::resource('messages', App\Http\Controllers\MessageController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('messages/{message}/reply', [App\Http\Controllers\MessageController::class, 'reply'])->name('messages.reply');


    
    Route::get('settings', [App\Http\Controllers\SettingController::class, 'index'])->name('settings.index');
    Route::post('settings', [App\Http\Controllers\SettingController::class, 'update'])->name('settings.update');

    Route::get('audit-logs', [App\Http\Controllers\AuditController::class, 'index'])->name('audit.index');

    // ─── Store Manager Hub ─────────────────────────────────────────────────────
    Route::prefix('store-manager')->name('store-manager.')->group(function () {
        Route::get('/', fn() => redirect()->route('dashboard.store-manager'))->name('dashboard');
        
        // Slip Sequences (GRN/SIN Configuration)
        Route::resource('slip-sequences', App\Http\Controllers\SlipSequenceController::class);
        Route::post('slip-sequences/{slipSequence}/deactivate', [App\Http\Controllers\SlipSequenceController::class, 'deactivate'])->name('slip-sequences.deactivate');
        Route::post('slip-sequences/{slipSequence}/reactivate', [App\Http\Controllers\SlipSequenceController::class, 'reactivate'])->name('slip-sequences.reactivate');
        Route::post('slip-sequences/{slipSequence}/reset', [App\Http\Controllers\SlipSequenceController::class, 'reset'])->name('slip-sequences.reset');
        Route::get('api/slip-sequences/{storeId}/{slipType}', [App\Http\Controllers\SlipSequenceController::class, 'getNextSlip']);
        
        // Inventory - All stores
        Route::get('inventory/all', [App\Http\Controllers\StoreManagerController::class, 'allInventory'])->name('inventory.all');
        
        // Transfers
        Route::get('transfers', [App\Http\Controllers\StoreManagerController::class, 'transfersIndex'])->name('transfers.index');
        Route::get('transfers/create', [App\Http\Controllers\StoreManagerController::class, 'createTransfer'])->name('transfers.create');
        Route::post('transfers', [App\Http\Controllers\StoreManagerController::class, 'storeTransfer'])->name('transfers.store');
        Route::get('transfers/{transfer}', [App\Http\Controllers\StoreManagerController::class, 'showTransfer'])->name('transfers.show');
        
        // Material Requests from Coordinator
        Route::get('material-requests', [App\Http\Controllers\StoreManagerController::class, 'materialRequests'])->name('material-requests.index');
        Route::post('material-requests/{materialRequest}/process', [App\Http\Controllers\StoreManagerController::class, 'processMaterialRequest'])->name('material-requests.process');
        
        // Product Catalog
        Route::get('products', [App\Http\Controllers\StoreManagerController::class, 'productCatalog'])->name('products.index');
        Route::get('products/create', [App\Http\Controllers\StoreManagerController::class, 'createProduct'])->name('products.create');
        Route::post('products', [App\Http\Controllers\StoreManagerController::class, 'storeProduct'])->name('products.store');
        
        // Receive & Send Slips (Unified)
        Route::get('slips', [App\Http\Controllers\StoreManagerController::class, 'slipsIndex'])->name('slips.index');
        Route::get('slips/create', [App\Http\Controllers\StoreManagerController::class, 'createSlip'])->name('slips.create');
        Route::post('slips', [App\Http\Controllers\StoreManagerController::class, 'storeSlip'])->name('slips.store');
        Route::post('slips/{slip}/void', [App\Http\Controllers\StoreManagerController::class, 'voidSlip'])->name('slips.void');
        
        // Issued Materials
        Route::get('issued-materials', [App\Http\Controllers\StoreManagerController::class, 'issuedMaterials'])->name('issued.index');

        // Fixed Assets — Unit-specific routes MUST come before the resource route
        // to prevent {fixedAsset} wildcard from matching 'units' as an asset ID.
        Route::put('fixed-assets/units/{unit}', [App\Http\Controllers\FixedAssetController::class, 'updateUnit'])->name('fixed-assets.units.update');
        Route::delete('fixed-assets/units/{unit}', [App\Http\Controllers\FixedAssetController::class, 'destroyUnit'])->name('fixed-assets.units.destroy');
        Route::post('fixed-assets/units/{unit}/assign', [App\Http\Controllers\FixedAssetController::class, 'assignUnit'])->name('fixed-assets.units.assign');
        Route::post('fixed-assets/units/{unit}/return', [App\Http\Controllers\FixedAssetController::class, 'returnUnit'])->name('fixed-assets.units.return');

        // Fixed Assets (Centralized Unit Codes & Quantity Lock)
        Route::resource('fixed-assets', App\Http\Controllers\FixedAssetController::class);
        Route::post('fixed-assets/{fixedAsset}/extra-unit', [App\Http\Controllers\FixedAssetController::class, 'storeExtraUnit'])->name('fixed-assets.extra-unit');
    });

    // API / AJAX: Available Fixed Asset Units for HR assignment dropdown & return
    Route::get('api/fixed-assets/available', [App\Http\Controllers\FixedAssetController::class, 'availableUnitsAjax'])->name('fixed-assets.available-ajax');
    Route::post('hr/fixed-assets/{unit}/return', [App\Http\Controllers\FixedAssetController::class, 'returnUnit'])->name('hr.fixed-assets.return');

    // ─── Planning Manager Hub ───────────────────────────────────────────────────
    Route::prefix('planning-manager')->name('planning-manager.')->group(function () {
        Route::get('emergency-requests', [App\Http\Controllers\PlanningManagerController::class, 'emergencyRequests'])->name('emergency-requests');
        Route::post('emergency-requests/material/{materialRequest}/approve', [App\Http\Controllers\PlanningManagerController::class, 'approveMaterial'])->name('emergency-requests.material.approve');
        Route::post('emergency-requests/manpower/{manpowerRequest}/approve', [App\Http\Controllers\PlanningManagerController::class, 'approveManpower'])->name('emergency-requests.manpower.approve');


        Route::get('resource-report', [App\Http\Controllers\PlanningManagerController::class, 'resourceReport'])->name('resource-report');
        Route::get('weekly-plan-setup', [App\Http\Controllers\PlanningManagerController::class, 'weeklyPlanSetup'])->name('weekly-plan-setup');
        Route::post('weekly-plan-setup', [App\Http\Controllers\PlanningManagerController::class, 'storeWeeklyPlan'])->name('weekly-plan-setup.store');
    });

    // ─── Engineer Work Scheduling Module ────────────────────────────────────────
    Route::prefix('eng-schedule')->name('eng-schedule.')->group(function () {
        // Calendar feed & conflict check (before resource routes to avoid conflicts)
        Route::get('calendar-feed',     [App\Http\Controllers\EngScheduleController::class, 'calendarFeed'])->name('calendar-feed');
        Route::get('engineer-resources',[App\Http\Controllers\EngScheduleController::class, 'engineerResources'])->name('engineer-resources');
        Route::post('conflict-check',   [App\Http\Controllers\EngScheduleController::class, 'conflictCheck'])->name('conflict-check');

        // Engineer personal view
        Route::get('my',                [App\Http\Controllers\EngScheduleController::class, 'mySchedule'])->name('my');

        // Standard resource (index, create, store, show, edit, update, destroy)
        Route::resource('/', App\Http\Controllers\EngScheduleController::class)
             ->parameters(['' => 'engSchedule'])
             ->names([
                 'index'   => 'index',
                 'create'  => 'create',
                 'store'   => 'store',
                 'show'    => 'show',
                 'edit'    => 'edit',
                 'update'  => 'update',
                 'destroy' => 'destroy',
             ]);

        // Extra actions on a specific work order
        Route::patch('{engSchedule}/status',    [App\Http\Controllers\EngScheduleController::class, 'updateStatus'])->name('update-status');
        Route::patch('{engSchedule}/reschedule',[App\Http\Controllers\EngScheduleController::class, 'reschedule'])->name('reschedule');
        Route::post('{engSchedule}/comments',   [App\Http\Controllers\EngScheduleController::class, 'addComment'])->name('add-comment');
    });



    // ─── Marketing Module ───────────────────────────────────────────────────────
    Route::prefix('marketing')->name('marketing.')->group(function () {
        Route::get('dashboard', [App\Http\Controllers\MarketingController::class, 'dashboard'])->name('dashboard');
        
        // Prices
        Route::get('prices/create', [App\Http\Controllers\MarketingController::class, 'createPrice'])->name('prices.create');
        Route::post('prices/store', [App\Http\Controllers\MarketingController::class, 'storePrice'])->name('prices.store');
        Route::get('prices/history', [App\Http\Controllers\MarketingController::class, 'priceHistory'])->name('prices.history');

        // Quick-add equipment (Fixed Asset) from price update modal
        Route::post('equipment/store', [App\Http\Controllers\MarketingController::class, 'storeEquipment'])->name('equipment.store');

        // Reports
        Route::get('reports/inflation', [App\Http\Controllers\MarketingController::class, 'inflationReport'])->name('reports.inflation');
        Route::get('reports/planning-vs-actual', [App\Http\Controllers\MarketingController::class, 'planningVsActual'])->name('reports.planning-vs-actual');
    });

    // ─── "Ask Money" (Employee Expense Request) Module ─────────────────────────────
    Route::prefix('expense-requests')->name('expense-requests.')->group(function () {
        Route::get('/',                                 [App\Http\Controllers\ExpenseRequestController::class, 'index'])->name('index');
        Route::post('/',                                [App\Http\Controllers\ExpenseRequestController::class, 'store'])->name('store');
        Route::get('/history',                          [App\Http\Controllers\ExpenseRequestController::class, 'history'])->name('history');
        Route::get('/{expenseRequest}',                 [App\Http\Controllers\ExpenseRequestController::class, 'show'])->name('show');
        Route::get('/{expenseRequest}/attachment',      [App\Http\Controllers\ExpenseRequestController::class, 'viewAttachment'])->name('attachment');
        Route::post('/{expenseRequest}/hr-review',      [App\Http\Controllers\ExpenseRequestController::class, 'hrReview'])->name('hr-review');
        Route::post('/{expenseRequest}/gm-review',      [App\Http\Controllers\ExpenseRequestController::class, 'gmReview'])->name('gm-review');
        Route::post('/{expenseRequest}/finance-assign', [App\Http\Controllers\ExpenseRequestController::class, 'financeAssign'])->name('finance-assign');
        Route::post('/{expenseRequest}/mark-paid',      [App\Http\Controllers\ExpenseRequestController::class, 'markPaid'])->name('mark-paid');
    });

    // ─── Correspondence (Letter) Management System ──────────────────────────────────
    Route::prefix('letters')->name('letters.')->group(function () {
        Route::get('/dashboard',                         [App\Http\Controllers\LetterController::class, 'dashboard'])->name('dashboard');
        Route::get('/',                                  [App\Http\Controllers\LetterController::class, 'index'])->name('index');
        Route::get('/create',                            [App\Http\Controllers\LetterController::class, 'create'])->name('create');
        Route::post('/',                                 [App\Http\Controllers\LetterController::class, 'store'])->name('store');
        Route::get('/suggested-number',                  [App\Http\Controllers\LetterController::class, 'getSuggestedNumber'])->name('suggested-number');
        Route::get('/{letter}',                          [App\Http\Controllers\LetterController::class, 'show'])->name('show');
        Route::post('/{letter}/redirect',                [App\Http\Controllers\LetterController::class, 'redirectLetter'])->name('redirect');
        Route::post('/{letter}/close',                   [App\Http\Controllers\LetterController::class, 'closeLetter'])->name('close');
        Route::get('/attachments/{attachment}/preview',  [App\Http\Controllers\LetterController::class, 'previewAttachment'])->name('attachments.preview');
        Route::get('/attachments/{attachment}/download', [App\Http\Controllers\LetterController::class, 'downloadAttachment'])->name('attachments.download');
    });

    // ─── Admin Role Management ──────────────────────────────────────────────────────
    Route::prefix('admin/roles')->name('admin.roles.')->group(function () {
        Route::post('/',                [App\Http\Controllers\Admin\RoleAssignmentController::class, 'storeRole'])->name('store');
        Route::delete('/{role}',        [App\Http\Controllers\Admin\RoleAssignmentController::class, 'destroyRole'])->name('destroy');
    });
});

