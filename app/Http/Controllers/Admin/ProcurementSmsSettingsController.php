<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Contracts\SmsProviderInterface;
use App\Models\ProcurementSmsSetting;
use App\Models\NotificationLog;
use App\Models\User;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ProcurementSmsSettingsController extends Controller
{
    public function __construct(
        protected SmsProviderInterface $smsProvider
    ) {
        $this->middleware('auth');
    }

    /**
     * Display the SMS Handoff Settings Dashboard.
     */
    public function index(Request $request)
    {
        $this->ensureAuthorized();

        $configHandoffs = config('procurement_handoffs.handoffs', []);
        $dbSettings = ProcurementSmsSetting::all()->keyBy('handoff_key');
        $statusMap = \App\Services\ProcurementStatusOwnerService::getStatusMap();

        // Merge config definitions with DB overrides and status map
        $handoffs = [];
        foreach ($configHandoffs as $key => $conf) {
            $db = $dbSettings->get($key);
            $handoffs[$key] = [
                'key'              => $key,
                'name'             => $db->name ?? ($conf['name'] ?? $key),
                'sender_role'      => $db->sender_role ?? ($conf['sender_role'] ?? 'user'),
                'target_roles'     => $conf['target_roles'] ?? ['user'],
                'target_roles_str' => implode(', ', (array)($conf['target_roles'] ?? ['user'])),
                'is_enabled'       => $db ? (bool)$db->is_enabled : true,
                'template'         => $db->template ?? ($conf['default_template'] ?? ''),
                'default_template' => $conf['default_template'] ?? '',
                'description'      => $db->description ?? ($conf['description'] ?? ''),
            ];
        }

        // Include any additional transitions from status map
        foreach ($statusMap as $mapKey => $mapItem) {
            $hKey = $mapItem['handoff_key'];
            if (!isset($handoffs[$hKey])) {
                $db = $dbSettings->get($hKey);
                $handoffs[$hKey] = [
                    'key'              => $hKey,
                    'name'             => $db->name ?? $mapItem['name'],
                    'sender_role'      => $db->sender_role ?? 'system',
                    'target_roles'     => [$mapItem['owner_role']],
                    'target_roles_str' => ucwords(str_replace('_', ' ', $mapItem['owner_role'])),
                    'is_enabled'       => $db ? (bool)$db->is_enabled : true,
                    'template'         => $db->template ?? $mapItem['default_template'],
                    'default_template' => $mapItem['default_template'],
                    'description'      => $db->description ?? "Triggered on {$mapItem['status_key']}",
                ];
            }
        }

        // Recent SMS logs
        $logsQuery = NotificationLog::with(['sender', 'recipient', 'recipientEmployee'])->latest();

        if ($request->filled('status')) {
            $logsQuery->where('status', $request->status);
        }
        if ($request->filled('action')) {
            $logsQuery->where('action', $request->action);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $logsQuery->where(function ($q) use ($s) {
                $q->where('phone', 'like', "%{$s}%")
                  ->orWhere('role', 'like', "%{$s}%")
                  ->orWhere('message', 'like', "%{$s}%");
            });
        }

        $recentLogs = $logsQuery->paginate(20)->withQueryString();

        // Failed SMS logs for admin inspection
        $failedLogs = NotificationLog::with(['sender', 'recipient', 'recipientEmployee'])
            ->where('status', 'failed')
            ->latest()
            ->paginate(20, ['*'], 'failed_page')
            ->withQueryString();

        // Statistics
        $totalSent   = NotificationLog::where('status', 'sent')->count();
        $totalFailed = NotificationLog::where('status', 'failed')->count();
        $totalLogged = NotificationLog::count();
        $activeProvider = class_basename($this->smsProvider);
        $isGloballyEnabled = config('procurement_handoffs.enabled', true);

        return view('admin.procurement.sms-settings', compact(
            'handoffs',
            'recentLogs',
            'failedLogs',
            'totalSent',
            'totalFailed',
            'totalLogged',
            'activeProvider',
            'isGloballyEnabled'
        ));
    }

    /**
     * Save settings for one or multiple handoffs.
     */
    public function update(Request $request)
    {
        $this->ensureAuthorized();

        $validated = $request->validate([
            'handoffs' => 'required|array',
            'handoffs.*.is_enabled' => 'nullable|boolean',
            'handoffs.*.template'   => 'required|string|max:300',
        ]);

        $configHandoffs = config('procurement_handoffs.handoffs', []);

        foreach ($validated['handoffs'] as $key => $data) {
            $conf = $configHandoffs[$key] ?? [];
            ProcurementSmsSetting::updateOrCreate(
                ['handoff_key' => $key],
                [
                    'name'        => $conf['name'] ?? $key,
                    'sender_role' => $conf['sender_role'] ?? 'user',
                    'target_role' => implode(',', (array)($conf['target_roles'] ?? ['user'])),
                    'is_enabled'  => !empty($data['is_enabled']),
                    'template'    => trim($data['template']),
                    'description' => $conf['description'] ?? null,
                ]
            );
        }

        return redirect()->route('admin.procurement.sms-settings.index')
            ->with('success', 'Procurement SMS handoff settings and templates updated successfully.');
    }

    /**
     * Send an instant test SMS to verify provider connectivity and phone reception.
     */
    public function testSms(Request $request)
    {
        $this->ensureAuthorized();

        $validated = $request->validate([
            'test_phone'   => 'required|string|min:9|max:20',
            'test_message' => 'required|string|max:160',
        ]);

        $phone = $validated['test_phone'];
        $message = trim($validated['test_message']);

        // Send via provider
        $result = $this->smsProvider->send($phone, $message);

        // Record in notification log
        NotificationLog::create([
            'request_type'      => 'test',
            'request_id'        => null,
            'action'            => 'admin_test_sms',
            'sender_user_id'    => Auth::id(),
            'role'              => 'admin_test',
            'phone'             => $phone,
            'message'           => $message,
            'status'            => !empty($result['success']) ? 'sent' : 'failed',
            'error'             => $result['error'] ?? null,
            'retries'           => 0,
        ]);

        if (!empty($result['success'])) {
            $msgId = $result['message_id'] ? " (ID: {$result['message_id']})" : '';
            return back()->with('success', "Test SMS successfully sent to {$phone}!{$msgId}");
        } else {
            $err = $result['error'] ?? 'Unknown gateway error';
            return back()->with('error', "Failed to send test SMS to {$phone}: {$err}");
        }
    }

    /**
     * Retry a failed SMS dispatch.
     */
    public function retry(Request $request, $id)
    {
        $this->ensureAuthorized();

        $log = NotificationLog::findOrFail($id);

        if (empty($log->phone) || $log->phone === 'N/A') {
            return back()->with('error', 'Cannot retry: Recipient has no valid phone number configured.');
        }

        try {
            $result = $this->smsProvider->send($log->phone, $log->message);
            $log->retries = ($log->retries ?? 0) + 1;

            if (!empty($result['success'])) {
                $log->status = 'sent';
                $log->error = null;
                $log->save();

                // Also update procurement_sms_logs if exists
                if ($log->request_id) {
                    \Illuminate\Support\Facades\DB::table('procurement_sms_logs')
                        ->where(function($q) use ($log) {
                            $q->where('purchase_request_id', $log->request_id)
                              ->orWhere('material_request_id', $log->request_id);
                        })
                        ->where('recipient_phone', $log->phone)
                        ->where('status', 'failed')
                        ->update(['status' => 'sent', 'error_message' => null]);
                }

                return back()->with('success', "SMS to {$log->phone} successfully resent!");
            } else {
                $log->error = $result['error'] ?? 'Gateway retry failed';
                $log->save();
                return back()->with('error', "SMS retry to {$log->phone} failed: {$log->error}");
            }
        } catch (\Throwable $e) {
            $log->error = $e->getMessage();
            $log->retries = ($log->retries ?? 0) + 1;
            $log->save();
            return back()->with('error', "SMS retry exception: {$e->getMessage()}");
        }
    }

    /**
     * Check authorization: Global Admin, Admin, or Purchase Manager.
     */
    protected function ensureAuthorized(): void
    {
        $user = Auth::user();
        if (!$user || !$user->hasAnyRole(['admin', 'global_admin', 'purchase_manager', 'procurement_manager'])) {
            abort(403, 'Unauthorized access to Procurement SMS settings.');
        }
    }
}
