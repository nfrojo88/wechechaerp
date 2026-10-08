<?php

namespace App\Console\Commands;

use App\Models\VehicleReminder;
use App\Models\VehicleReminderNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckVehicleReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vehicles:check-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check vehicle reminders for expiring Bolo, insurance policies, and due KM services';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting vehicle reminders check...');

        $today = Carbon::today();
        $reminders = VehicleReminder::with(['assetUnit', 'fixedAsset'])
            ->whereNotIn('status', [VehicleReminder::STATUS_RENEWED, VehicleReminder::STATUS_SERVICED])
            ->whereNull('deleted_at')
            ->get();

        $alertsCreated = 0;

        foreach ($reminders as $reminder) {
            $computedStatus = $reminder->computeStatus();

            // Update status if changed and not manually locked
            if ($reminder->status !== $computedStatus && in_array($computedStatus, [VehicleReminder::STATUS_DUE_SOON, VehicleReminder::STATUS_EXPIRED, VehicleReminder::STATUS_OVERDUE])) {
                $reminder->update(['status' => $computedStatus]);
            }

            // Check if alert notification should be recorded today
            $shouldAlert = false;
            $alertType = null;
            $message = null;

            if ($reminder->reminder_type === VehicleReminder::TYPE_SERVICE_KM) {
                $kmRemaining = $reminder->km_remaining;
                $threshold = $reminder->reminder_threshold_km ?? 500;

                if ($kmRemaining !== null && $kmRemaining <= 0) {
                    $shouldAlert = true;
                    $alertType = 'service_overdue';
                    $message = "Service overdue by " . abs($kmRemaining) . " KM for vehicle " . ($reminder->assetUnit?->plate_number ?: $reminder->assetUnit?->unit_code);
                } elseif ($kmRemaining !== null && $kmRemaining <= $threshold) {
                    $shouldAlert = true;
                    $alertType = 'service_due_soon';
                    $message = "Service due in {$kmRemaining} KM for vehicle " . ($reminder->assetUnit?->plate_number ?: $reminder->assetUnit?->unit_code);
                }
            } else {
                // Date-based
                $daysRemaining = $reminder->days_until_expiry;
                if ($daysRemaining !== null) {
                    if ($daysRemaining < 0) {
                        $shouldAlert = true;
                        $alertType = 'expired';
                        $message = "{$reminder->reminder_type_label} expired " . abs($daysRemaining) . " days ago for vehicle " . ($reminder->assetUnit?->plate_number ?: $reminder->assetUnit?->unit_code);
                    } elseif (in_array($daysRemaining, [30, 15, 7, 1, 0])) {
                        $shouldAlert = true;
                        $alertType = 'due_in_' . $daysRemaining . '_days';
                        $message = "{$reminder->reminder_type_label} due in {$daysRemaining} days for vehicle " . ($reminder->assetUnit?->plate_number ?: $reminder->assetUnit?->unit_code);
                    }
                }
            }

            if ($shouldAlert && $alertType) {
                // Avoid logging duplicate notifications on the same day
                $alreadyLoggedToday = VehicleReminderNotification::where('vehicle_reminder_id', $reminder->id)
                    ->where('alert_type', $alertType)
                    ->whereDate('created_at', $today)
                    ->exists();

                if (!$alreadyLoggedToday) {
                    VehicleReminderNotification::create([
                        'vehicle_reminder_id' => $reminder->id,
                        'channel'             => 'in_app',
                        'alert_type'          => $alertType,
                        'sent'                => true,
                        'sent_at'             => now(),
                        'message'             => $message,
                    ]);
                    $alertsCreated++;
                    $this->warn("Alert logged: {$message}");

                    // If SMS reminders enabled, send SMS to General Service and GM
                    if ($reminder->send_sms ?? true) {
                        try {
                            $smsService = app(\App\Services\VehicleReminderSmsService::class);
                            $smsResult = $smsService->sendReminderSms($reminder, $alertType);
                            $this->info("SMS sent for vehicle reminder #{$reminder->id}: {$smsResult['sent']} delivered, {$smsResult['failed']} failed.");
                        } catch (\Throwable $e) {
                            Log::error("Failed to send scheduled SMS for vehicle reminder #{$reminder->id}: " . $e->getMessage());
                            $this->error("SMS error for reminder #{$reminder->id}: " . $e->getMessage());
                        }
                    }
                }
            }
        }

        $this->info("Completed vehicle reminders check. {$alertsCreated} new alerts logged.");
        Log::info("vehicles:check-reminders finished. Processed {$reminders->count()} reminders, logged {$alertsCreated} alerts.");

        return Command::SUCCESS;
    }
}
