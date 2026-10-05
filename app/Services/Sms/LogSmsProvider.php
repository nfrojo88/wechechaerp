<?php

namespace App\Services\Sms;

use App\Contracts\SmsProviderInterface;
use Illuminate\Support\Facades\Log;

/**
 * LogSmsProvider
 *
 * Simulates SMS sending by logging to Laravel's log file.
 * Used for development, local staging, or unit testing.
 */
class LogSmsProvider implements SmsProviderInterface
{
    /** @var array<int, array> */
    public static array $dispatchedMessages = [];

    public function send(string $to, string $message): array
    {
        $phone = preg_replace('/[^0-9+]/', '', $to);
        $messageId = 'sim-' . uniqid();

        Log::info("[LOG SMS] Sent to {$phone}: {$message}");

        self::$dispatchedMessages[] = [
            'to'         => $phone,
            'message'    => $message,
            'message_id' => $messageId,
            'time'       => now()->toIso8601String(),
        ];

        return [
            'success'      => true,
            'message_id'   => $messageId,
            'error'        => null,
            'raw_response' => ['simulated' => true],
        ];
    }
}
