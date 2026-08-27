<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $user = auth()->user();
            if (!$user) {
                abort(403, 'Access denied.');
            }

            $hasAccess = false;
            if (method_exists($user, 'hasAnyRole')) {
                $hasAccess = $user->hasAnyRole(['global_admin', 'admin', 'auditor', 'audit', 'internal_auditor', 'Finance head', 'finance_head']);
            }
            if (!$hasAccess && method_exists($user, 'can')) {
                $hasAccess = $user->can('audit.view') || $user->can('finance.audit.view') || $user->can('admin.audit.view');
            }

            if (!$hasAccess) {
                abort(403, 'Access denied. Auditor or Admin only.');
            }
            return $next($request);
        });
    }


    public function index(Request $request)
    {
        $query = ActivityLog::with('user')->latest();

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }
        if ($request->filled('module')) {
            $query->where('module', $request->module);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        if ($request->filled('search')) {
            $query->where('description', 'like', '%' . $request->search . '%');
        }

        $logs = $query->paginate(50)->withQueryString();
        $users = User::orderBy('name')->get(['id', 'name']);
        $actions = ActivityLog::select('action')->distinct()->pluck('action');
        $modules = ActivityLog::select('module')->whereNotNull('module')->distinct()->pluck('module');

        return view('admin.activity-logs.index', compact('logs', 'users', 'actions', 'modules'));
    }
}
