<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\User;
use App\Models\VehicleReminder;
use App\Models\VehicleReminderNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class VehicleReminderSmsService
{
    private SmsEthiopiaService $smsService;
    private ProcurementSmsService $procurementSms;

    public function __construct(
        ?SmsEthiopiaService $smsService = null,
        ?ProcurementSmsService $procurementSms = null
    ) {
        $this->smsService     = $smsService ?? app(SmsEthiopiaService::class);
        $this->procurementSms = $procurementSms ?? app(ProcurementSmsService::class);
    }

    /**
     * Send SMS reminders to General Service and GM based on reminder configuration.
     *
     * @param VehicleReminder $reminder
     * @param string|null $alertType
     * @return array
     */
    public function sendReminderSms(VehicleReminder $reminder, ?string $alertType = null): array
    {
        $reminder->loadMissing(['assetUnit', 'fixedAsset']);

        $message = $this->buildSmsMessage($reminder);
        $recipients = $this->resolveRecipients($reminder);

        $sentCount = 0;
        $failedCount = 0;
        $logs = [];

        foreach ($recipients as $recipient) {
            $phone = $recipient['phone'];
            $role  = $recipient['role'];
            $name  = $recipient['name'] ?? $role;

            try {
                $response = $this->smsService->sendMessage($phone, $message);
                $isSuccess = !empty($response['success']) || (is_array($response) && ($response['acknowledge'] ?? '') === 'success');

                VehicleReminderNotification::create([
                    'vehicle_reminder_id' => $reminder->id,
                    'channel'             => 'sms',
                    'alert_type'          => $alertType ?? 'manual_alert',
                    'recipient_phone'     => $phone,
                    'recipient_role'      => $role,
                    'sent'                => $isSuccess,
                    'sent_at'             => now(),
                    'message'             => $message,
                ]);

                if ($isSuccess) {
                    $sentCount++;
                    Log::info("VehicleReminder SMS sent successfully to {$phone} ({$role}) for vehicle reminder #{$reminder->id}");
                } else {
                    $failedCount++;
                    Log::warning("VehicleReminder SMS failed for {$phone} ({$role}): " . json_encode($response));
                }

                $logs[] = [
                    'phone'   => $phone,
                    'role'    => $role,
                    'name'    => $name,
                    'success' => $isSuccess,
                ];
            } catch (\Throwable $e) {
                $failedCount++;
                Log::error("VehicleReminder SMS exception for {$phone} ({$role}): " . $e->getMessage());

                VehicleReminderNotification::create([
                    'vehicle_reminder_id' => $reminder->id,
                    'channel'             => 'sms',
                    'alert_type'          => $alertType ?? 'manual_alert',
                    'recipient_phone'     => $phone,
                    'recipient_role'      => $role,
                    'sent'                => false,
                    'sent_at'             => now(),
                    'message'             => "Error: " . $e->getMessage(),
                ]);

                $logs[] = [
                    'phone'   => $phone,
                    'role'    => $role,
                    'name'    => $name,
                    'success' => false,
                    'error'   => $e->getMessage(),
                ];
            }
        }

        return [
            'sent'       => $sentCount,
            'failed'     => $failedCount,
            'total'      => count($recipients),
            'logs'       => $logs,
            'message'    => $message,
        ];
    }

    /**
     * Resolve all recipient phone numbers based on reminder settings.
     */
    public function resolveRecipients(VehicleReminder $reminder): array
    {
        $recipients = [];

        // 1. General Service Team / Officer
        if ($reminder->notify_general_service ?? true) {
            $gsPhones = $this->getGeneralServicePhones();
            foreach ($gsPhones as $p) {
                $recipients[] = [
                    'phone' => $p['phone'],
                    'role'  => 'General Service',
                    'name'  => $p['name'],
                ];
            }
        }

        // 2. General Manager (GM)
        if ($reminder->notify_gm ?? true) {
            $gmPhones = $this->getGeneralManagerPhones();
            foreach ($gmPhones as $p) {
                $recipients[] = [
                    'phone' => $p['phone'],
                    'role'  => 'General Manager (GM)',
                    'name'  => $p['name'],
                ];
            }
        }

        // 3. Custom Phone Number (e.g. driver or assigned officer)
        if (!empty($reminder->custom_sms_phone)) {
            $customNorm = $this->procurementSms->normalizePhone($reminder->custom_sms_phone);
            if ($customNorm) {
                $recipients[] = [
                    'phone' => $customNorm,
                    'role'  => 'Assigned / Custom',
                    'name'  => 'Custom Contact',
                ];
            }
        }

        // Deduplicate by phone number
        $unique = [];
        $result = [];
        foreach ($recipients as $r) {
            if (!isset($unique[$r['phone']])) {
                $unique[$r['phone']] = true;
                $result[] = $r;
            }
        }

        return $result;
    }

    /**
     * Build standard SMS message fitting standard SMS bounds.
     */
    public function buildSmsMessage(VehicleReminder $reminder): string
    {
        $unit = $reminder->assetUnit;
        $plate = $unit?->plate_number ?: ($unit?->unit_code ?: 'Vehicle');
        $vehicleName = $reminder->fixedAsset?->name ?: '';
        $shortName = mb_substr($vehicleName, 0, 20);

        if ($reminder->reminder_type === VehicleReminder::TYPE_BOLO) {
            $expiry = $reminder->bolo_expiry_date;
            $days = $reminder->days_until_expiry;
            $dateStr = $expiry ? $expiry->format('d/m/Y') : '—';

            if ($days !== null && $days < 0) {
                return "[Construct-Pro Alert] URGENT: Vehicle {$plate} ({$shortName}) Bolo inspection EXPIRED on {$dateStr}. Immediate renewal required. - GS & GM";
            } elseif ($days === 0) {
                return "[Construct-Pro Alert] URGENT: Vehicle {$plate} ({$shortName}) Bolo inspection EXPIRES TODAY ({$dateStr}). Please renew. - GS & GM";
            } else {
                return "[Construct-Pro Alert] Vehicle {$plate} ({$shortName}) Bolo inspection expires in {$days} days ({$dateStr}). Please arrange renewal. - GS & GM";
            }
        }

        if ($reminder->reminder_type === VehicleReminder::TYPE_SERVICE_KM) {
            $curr = number_format($reminder->current_odometer_km ?? 0);
            $next = number_format($reminder->next_service_km ?? 0);
            $kmLeft = $reminder->km_remaining;

            if ($kmLeft !== null && $kmLeft <= 0) {
                $over = number_format(abs($kmLeft));
                return "[Construct-Pro Alert] URGENT: Vehicle {$plate} ({$shortName}) service OVERDUE by {$over} KM! Current: {$curr} KM, target was {$next} KM. - GS & GM";
            } else {
                $leftStr = number_format($kmLeft ?? 0);
                return "[Construct-Pro Alert] Vehicle {$plate} ({$shortName}) service due in {$leftStr} KM (at {$next} KM). Current: {$curr} KM. - GS & GM";
            }
        }

        // Insurance (Third-Party or Comprehensive)
        $typeLabel = $reminder->reminder_type === VehicleReminder::TYPE_THIRD_PARTY_INS ? '3rd-Party Insurance' : 'Comprehensive Insurance';
        $company = $reminder->insurance_company ?: 'Insurance';
        $expiry = $reminder->insurance_expiry_date;
        $days = $reminder->days_until_expiry;
        $dateStr = $expiry ? $expiry->format('d/m/Y') : '—';

        if ($days !== null && $days < 0) {
            return "[Construct-Pro Alert] URGENT: Vehicle {$plate} {$typeLabel} ({$company}) EXPIRED on {$dateStr}. Immediate renewal required. - GS & GM";
        } elseif ($days === 0) {
            return "[Construct-Pro Alert] URGENT: Vehicle {$plate} {$typeLabel} ({$company}) EXPIRES TODAY ({$dateStr}). Please renew. - GS & GM";
        } else {
            return "[Construct-Pro Alert] Vehicle {$plate} {$typeLabel} ({$company}) expires in {$days} days ({$dateStr}). Please renew policy. - GS & GM";
        }
    }

    /**
     * Get phone numbers for General Service users and employees.
     */
    public function getGeneralServicePhones(): array
    {
        $phones = [];
        $gsRoles = ['general_service', 'general_services', 'General Service', 'general service'];

        try {
            // Users with Spatie roles
            $users = User::whereHas('roles', fn($q) => $q->whereIn('name', $gsRoles))
                ->with('employee')
                ->get();

            foreach ($users as $user) {
                $phone = $this->procurementSms->resolveUserPhone($user);
                if ($phone) {
                    $phones[] = ['phone' => $phone, 'name' => $user->name];
                }
            }

            // Employees with General Service in title
            $employees = Employee::whereNotNull('phone')
                ->where('phone', '!=', '')
                ->where(function ($q) {
                    $q->where('role_title', 'LIKE', '%General Service%')
                      ->orWhere('role_title', 'LIKE', '%General Services%')
                      ->orWhere('role_title', 'LIKE', '%Fleet%')
                      ->orWhere('role_title', 'LIKE', '%Transport%');
                })
                ->get();

            foreach ($employees as $emp) {
                $norm = $this->procurementSms->normalizePhone($emp->phone);
                if ($norm) {
                    $phones[] = ['phone' => $norm, 'name' => $emp->full_name];
                }
            }
        } catch (\Throwable $e) {
            Log::error("VehicleReminderSmsService GS phones error: " . $e->getMessage());
        }

        return $phones;
    }

    /**
     * Get phone numbers for General Manager (GM).
     */
    public function getGeneralManagerPhones(): array
    {
        $phones = [];
        $gmRoles = ['gm', 'General Manager', 'general_manager', 'global_admin', 'admin'];

        try {
            // Direct role search for GM
            $users = User::whereHas('roles', fn($q) => $q->whereIn('name', $gmRoles))
                ->with('employee')
                ->get();

            foreach ($users as $user) {
                $phone = $this->procurementSms->resolveUserPhone($user);
                if ($phone) {
                    $phones[] = ['phone' => $phone, 'name' => $user->name];
                }
            }

            // Employees with General Manager in role title
            $gmEmps = Employee::whereNotNull('phone')
                ->where('phone', '!=', '')
                ->where(function ($q) {
                    $q->where('role_title', 'LIKE', '%General Manager%')
                      ->orWhere('role_title', 'LIKE', '%Managing Director%')
                      ->orWhere('role_title', 'LIKE', '%GM%');
                })
                ->get();

            foreach ($gmEmps as $emp) {
                $norm = $this->procurementSms->normalizePhone($emp->phone);
                if ($norm) {
                    $phones[] = ['phone' => $norm, 'name' => $emp->full_name];
                }
            }

            // Fallback: check admin phone from config or User #1 if empty
            if (empty($phones)) {
                $adminPhones = $this->procurementSms->getGlobalAdminPhoneNumbers();
                foreach ($adminPhones as $ap) {
                    $phones[] = ['phone' => $ap, 'name' => 'General Manager (Admin)'];
                }
            }
        } catch (\Throwable $e) {
            Log::error("VehicleReminderSmsService GM phones error: " . $e->getMessage());
        }

        return $phones;
    }
}
