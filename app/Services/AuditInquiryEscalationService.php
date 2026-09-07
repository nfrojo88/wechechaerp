<?php

namespace App\Services;

use App\Models\ExpenseRequest;
use App\Models\ProcurementReceipt;
use App\Models\User;
use App\Models\Employee;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Carbon\Carbon;

class AuditInquiryEscalationService
{
    protected SmsEthiopiaService $smsService;

    public function __construct(SmsEthiopiaService $smsService)
    {
        $this->smsService = $smsService;
        self::ensureSchema();
    }

    /**
     * Self-healing schema to guarantee escalation timestamp columns exist
     */
    public static function ensureSchema(): void
    {
        try {
            if (Schema::hasTable('expense_requests')) {
                if (!Schema::hasColumn('expense_requests', 'audit_escalated_3day_at')) {
                    Schema::table('expense_requests', function (Blueprint $table) {
                        $table->timestamp('audit_escalated_3day_at')->nullable()->after('audit_receipt_requested_at');
                        $table->timestamp('audit_escalated_5day_at')->nullable()->after('audit_escalated_3day_at');
                    });
                }
            }

            if (Schema::hasTable('procurement_receipts')) {
                if (!Schema::hasColumn('procurement_receipts', 'audit_escalated_3day_at')) {
                    Schema::table('procurement_receipts', function (Blueprint $table) {
                        $table->timestamp('audit_escalated_3day_at')->nullable();
                        $table->timestamp('audit_escalated_5day_at')->nullable();
                    });
                }
            }
        } catch (\Throwable $e) {
            Log::error("AuditInquiryEscalationService ensureSchema error: " . $e->getMessage());
        }
    }

    /**
     * Check all pending receipt inquiries and escalate based on age:
     * - > 3 days: SMS to Auditor & Finance Head
     * - > 5 days: Direct SMS to General Admin & GM
     *
     * @return array Summary of processed and escalated inquiries
     */
    public function checkAndEscalate(): array
    {
        self::ensureSchema();

        $now = now();
        $threeDaysAgo = $now->copy()->subDays(3);
        $fiveDaysAgo  = $now->copy()->subDays(5);

        $results = [
            'checked'        => 0,
            'escalated_3day' => 0,
            'escalated_5day' => 0,
            'sms_sent'       => 0,
            'sms_failed'     => 0,
            'details'        => [],
        ];

        // 1. Process Expense Requests with pending audit inquiries
        $pendingErs = ExpenseRequest::with(['user', 'employee', 'chartOfAccount.manager', 'coa.manager', 'bankAccount', 'auditReceiptRequestedBy'])
            ->where('audit_receipt_status', 'requested')
            ->whereNotNull('audit_receipt_requested_at')
            ->get();

        foreach ($pendingErs as $er) {
            $results['checked']++;
            $requestedAt = Carbon::parse($er->audit_receipt_requested_at);
            $ageDays = $requestedAt->diffInDays($now);
            $ref = $er->request_number ?: ('REQ-' . $er->id);
            $amount = number_format((float)($er->net_amount > 0 ? $er->net_amount : $er->amount), 2);
            $acct = $er->chartOfAccount?->name ?? ($er->coa?->name ?? ($er->bankAccount?->account_name ?? 'Petty Cash'));
            $custodian = $er->chartOfAccount?->manager?->name ?? ($er->coa?->manager?->name ?? null);
            $custodianLabel = $custodian ? " (Custodian: {$custodian})" : '';

            // 5-DAY ESCALATION: General Admin & GM
            if ($requestedAt->lte($fiveDaysAgo)) {
                if (empty($er->audit_escalated_5day_at)) {
                    $msg = "URGENT [Construct-Pro ERP]: Receipt inquiry for {$ref} (ETB {$amount}, {$acct}{$custodianLabel}) has been pending >5 DAYS without receipt. Attention required by GM & General Admin.";
                    $sendRes = $this->escalateToGmAndAdmin($msg, $er->id, 'expense_request', $ref);

                    $er->update([
                        'audit_escalated_5day_at' => $now,
                        'audit_escalated_3day_at' => $er->audit_escalated_3day_at ?: $now,
                    ]);

                    $results['escalated_5day']++;
                    $results['sms_sent']   += $sendRes['sent'];
                    $results['sms_failed'] += $sendRes['failed'];
                    $results['details'][] = [
                        'ref'    => $ref,
                        'tier'   => '5_day (GM & Admin)',
                        'age'    => $ageDays,
                        'sent'   => $sendRes['sent'],
                        'failed' => $sendRes['failed'],
                    ];
                    continue;
                }
            }

            // 3-DAY ESCALATION: Auditor & Finance Head
            if ($requestedAt->lte($threeDaysAgo)) {
                if (empty($er->audit_escalated_3day_at)) {
                    $msg = "[Construct-Pro ERP Audit Alert]: Receipt inquiry for {$ref} (ETB {$amount}, {$acct}{$custodianLabel}) has been pending >3 DAYS without receipt. Please follow up.";
                    $sendRes = $this->escalateToAuditorAndFinanceHead($msg, $er->id, 'expense_request', $ref, $er->audit_receipt_requested_by);

                    $er->update([
                        'audit_escalated_3day_at' => $now,
                    ]);

                    $results['escalated_3day']++;
                    $results['sms_sent']   += $sendRes['sent'];
                    $results['sms_failed'] += $sendRes['failed'];
                    $results['details'][] = [
                        'ref'    => $ref,
                        'tier'   => '3_day (Auditor & Finance Head)',
                        'age'    => $ageDays,
                        'sent'   => $sendRes['sent'],
                        'failed' => $sendRes['failed'],
                    ];
                }
            }
        }

        // 2. Process Purchase Requests where auditor requested receipt
        $pendingPrs = ProcurementReceipt::with(['purchaseRequest.payment.coaAccount.manager', 'purchaseRequest.requestedBy'])
            ->where('verification_status', 'receipt_requested')
            ->get();

        foreach ($pendingPrs as $rec) {
            $results['checked']++;
            $pr = $rec->purchaseRequest;
            if (!$pr) continue;

            $requestedAt = Carbon::parse($rec->updated_at ?? $rec->created_at);
            $ageDays = $requestedAt->diffInDays($now);
            $ref = 'PR #' . ($pr->pr_no ?? $pr->id);
            $amount = number_format((float)($pr->payment?->amount ?? $pr->direct_buy_amount ?? 0), 2);
            $acct = $pr->payment?->coaAccount?->name ?? 'Procurement Account';
            $custodian = $pr->payment?->coaAccount?->manager?->name ?? null;
            $custodianLabel = $custodian ? " (Assigned: {$custodian})" : '';

            // 5-DAY ESCALATION
            if ($requestedAt->lte($fiveDaysAgo)) {
                if (empty($rec->audit_escalated_5day_at)) {
                    $msg = "URGENT [Construct-Pro ERP]: Vendor receipt inquiry for {$ref} (ETB {$amount}, {$acct}{$custodianLabel}) has been pending >5 DAYS without receipt. Attention required by GM & General Admin.";
                    $sendRes = $this->escalateToGmAndAdmin($msg, $pr->id, 'purchase_request', $ref);

                    $rec->update([
                        'audit_escalated_5day_at' => $now,
                        'audit_escalated_3day_at' => $rec->audit_escalated_3day_at ?: $now,
                    ]);

                    $results['escalated_5day']++;
                    $results['sms_sent']   += $sendRes['sent'];
                    $results['sms_failed'] += $sendRes['failed'];
                    $results['details'][] = [
                        'ref'    => $ref,
                        'tier'   => '5_day (GM & Admin)',
                        'age'    => $ageDays,
                        'sent'   => $sendRes['sent'],
                        'failed' => $sendRes['failed'],
                    ];
                    continue;
                }
            }

            // 3-DAY ESCALATION
            if ($requestedAt->lte($threeDaysAgo)) {
                if (empty($rec->audit_escalated_3day_at)) {
                    $msg = "[Construct-Pro ERP Audit Alert]: Vendor receipt inquiry for {$ref} (ETB {$amount}, {$acct}{$custodianLabel}) has been pending >3 DAYS without receipt. Please follow up.";
                    $sendRes = $this->escalateToAuditorAndFinanceHead($msg, $pr->id, 'purchase_request', $ref, $rec->verified_by);

                    $rec->update([
                        'audit_escalated_3day_at' => $now,
                    ]);

                    $results['escalated_3day']++;
                    $results['sms_sent']   += $sendRes['sent'];
                    $results['sms_failed'] += $sendRes['failed'];
                    $results['details'][] = [
                        'ref'    => $ref,
                        'tier'   => '3_day (Auditor & Finance Head)',
                        'age'    => $ageDays,
                        'sent'   => $sendRes['sent'],
                        'failed' => $sendRes['failed'],
                    ];
                }
            }
        }

        return $results;
    }

    /**
     * Send Escalation to Auditor and Finance Head (3-Day Tier)
     */
    protected function escalateToAuditorAndFinanceHead(string $message, int $sourceId, string $sourceType, string $ref, ?int $inquiredByUserId = null): array
    {
        $sentCount = 0;
        $failedCount = 0;

        // 1. Auditor phones (both specific auditor who asked, and users with auditor/audit_team role)
        $auditorPhones = $this->resolvePhonesForRoles(['auditor', 'audit_team', 'audit'], $inquiredByUserId);

        // 2. Finance Head phones
        $financeHeadPhones = $this->resolvePhonesForRoles(['finance_head', 'finance_manager', 'cfo']);

        $allPhones = array_unique(array_merge($auditorPhones, $financeHeadPhones));

        foreach ($allPhones as $phone) {
            $roleLabel = in_array($phone, $auditorPhones) ? 'Auditor' : 'Finance Head';
            $success = $this->dispatchSms($phone, $roleLabel, $message, $sourceId, $sourceType, $ref);
            if ($success) {
                $sentCount++;
            } else {
                $failedCount++;
            }
        }

        ActivityLog::log(
            'audit_inquiry_escalation_3day',
            "3-Day escalation SMS dispatched for [{$ref}] to Auditor & Finance Head (" . count($allPhones) . " recipients)",
            'Audit & Compliance'
        );

        return ['sent' => $sentCount, 'failed' => $failedCount];
    }

    /**
     * Send Escalation directly to General Admin and GM (5-Day Tier)
     */
    protected function escalateToGmAndAdmin(string $message, int $sourceId, string $sourceType, string $ref): array
    {
        $sentCount = 0;
        $failedCount = 0;

        // 1. General Admin phones
        $adminPhones = $this->resolvePhonesForRoles(['global_admin', 'admin', 'super_admin']);

        // 2. GM phones
        $gmPhones = $this->resolvePhonesForRoles(['gm', 'general_manager']);

        $allPhones = array_unique(array_merge($adminPhones, $gmPhones));

        foreach ($allPhones as $phone) {
            $roleLabel = in_array($phone, $gmPhones) ? 'GM' : 'General Admin';
            $success = $this->dispatchSms($phone, $roleLabel, $message, $sourceId, $sourceType, $ref);
            if ($success) {
                $sentCount++;
            } else {
                $failedCount++;
            }
        }

        ActivityLog::log(
            'audit_inquiry_escalation_5day',
            "CRITICAL 5-Day escalation SMS dispatched for [{$ref}] directly to GM & General Admin (" . count($allPhones) . " recipients)",
            'Audit & Compliance'
        );

        return ['sent' => $sentCount, 'failed' => $failedCount];
    }

    /**
     * Dispatches SMS using AfroMessage (SmsEthiopiaService) and logs the outcome
     */
    public function dispatchSms(string $phone, string $recipientRole, string $message, ?int $sourceId = null, string $sourceType = 'expense_request', ?string $ref = null): bool
    {
        $formattedPhone = $this->normalizePhone($phone);
        if (empty($formattedPhone)) {
            Log::warning("AuditInquiryEscalation: No phone number provided for role [{$recipientRole}] on {$ref}");
            return false;
        }

        $res = $this->smsService->sendMessage($formattedPhone, $message);
        $isSuccess = is_array($res) && !empty($res['success']);
        $status = $isSuccess ? 'sent' : 'failed';
        $errorMessage = !$isSuccess ? (is_array($res) && isset($res['message']) ? $res['message'] : json_encode($res)) : null;

        // Log to procurement_sms_logs for audit trail
        try {
            DB::table('procurement_sms_logs')->insert([
                'purchase_request_id' => $sourceType === 'purchase_request' ? ($sourceId ?? 0) : 0,
                'recipient_phone'     => $formattedPhone,
                'recipient_role'      => $recipientRole . ' (' . ($ref ?: $sourceType) . ')',
                'message'             => $message,
                'status'              => $status,
                'error_message'       => $errorMessage,
                'sent_at'             => now(),
            ]);
        } catch (\Throwable $ex) {
            Log::error("AuditInquiryEscalation DB log error: " . $ex->getMessage());
        }

        return $isSuccess;
    }

    /**
     * Resolve phone numbers for a list of roles, plus an optional specific user ID.
     */
    public function resolvePhonesForRoles(array $roles, ?int $specificUserId = null): array
    {
        $phones = [];

        // 1. If specific user ID provided (e.g. the specific auditor who requested the receipt)
        if ($specificUserId) {
            $user = User::with('employee')->find($specificUserId);
            if ($user) {
                $phone = $this->resolveUserPhone($user);
                if ($phone) {
                    $phones[] = $phone;
                }
            }
        }

        // 2. Fetch all users having any of the specified roles
        try {
            $roleAliases = [
                'auditor'         => ['auditor', 'audit', 'audit_team', 'Auditor', 'Audit Team'],
                'audit_team'      => ['auditor', 'audit', 'audit_team', 'Auditor', 'Audit Team'],
                'finance_head'    => ['finance_head', 'Finance Head', 'finance_manager', 'Finance Manager', 'cfo', 'CFO'],
                'global_admin'    => ['global_admin', 'admin', 'Global Admin', 'Admin', 'super_admin'],
                'admin'           => ['global_admin', 'admin', 'Global Admin', 'Admin', 'super_admin'],
                'gm'              => ['gm', 'GM', 'general_manager', 'General Manager'],
            ];

            $searchRoles = [];
            foreach ($roles as $r) {
                $searchRoles = array_merge($searchRoles, $roleAliases[$r] ?? [$r]);
            }
            $searchRoles = array_unique($searchRoles);

            $users = User::whereHas('roles', function($q) use ($searchRoles) {
                $q->whereIn('name', $searchRoles);
            })->with('employee')->get();

            foreach ($users as $u) {
                $p = $this->resolveUserPhone($u);
                if ($p) {
                    $phones[] = $p;
                }
            }
        } catch (\Throwable $e) {
            Log::error("AuditInquiryEscalation resolvePhonesForRoles error: " . $e->getMessage());
        }

        // 3. Fallbacks from config / env if no phone resolved from DB
        if (empty($phones)) {
            foreach ($roles as $r) {
                $envKey = match ($r) {
                    'gm', 'general_manager' => 'GM_PHONE',
                    'global_admin', 'admin'  => 'ADMIN_PHONE',
                    'finance_head'          => 'FINANCE_HEAD_PHONE',
                    'auditor', 'audit_team' => 'AUDITOR_PHONE',
                    default                 => null,
                };
                if ($envKey) {
                    $envVal = env($envKey) ?: config("services.sms.{$r}_phone");
                    if ($envVal) {
                        $phones[] = $this->normalizePhone($envVal);
                    }
                }
            }

            // Universal fallback to admin phone if still empty
            if (empty($phones)) {
                $adminPhone = env('ADMIN_PHONE') ?: config('services.sms.admin_phone');
                if ($adminPhone) {
                    $phones[] = $this->normalizePhone($adminPhone);
                }
            }
        }

        return array_values(array_unique(array_filter($phones)));
    }

    /**
     * Resolve phone for a given User from Employee relations and email matching.
     */
    public function resolveUserPhone(User $user): ?string
    {
        // 1. Direct employee relation
        $phone = $user->employee?->phone;

        // 2. Query Employee table by user_id
        if (empty($phone)) {
            $phone = Employee::where('user_id', $user->id)
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->value('phone');
        }

        // 3. Match Employee table by email
        if (empty($phone) && !empty($user->email)) {
            $phone = Employee::where('email', $user->email)
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->value('phone');
        }

        // 4. Match Employee table by full_name
        if (empty($phone) && !empty($user->name)) {
            $phone = Employee::where('full_name', 'like', "%{$user->name}%")
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->value('phone');
        }

        return !empty($phone) ? $this->normalizePhone($phone) : null;
    }

    /**
     * Normalizes phone number into +251... format for AfroMessage.
     */
    public function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        if (str_starts_with($phone, '09') && strlen($phone) === 10) {
            $phone = '+251' . substr($phone, 1);
        }
        if (!str_starts_with($phone, '+')) {
            $phone = '+' . $phone;
        }
        return $phone;
    }
}
