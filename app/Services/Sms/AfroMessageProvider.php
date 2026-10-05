<?php

namespace App\Services\Sms;

use App\Contracts\SmsProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * AfroMessageProvider
 *
 * SMS Adapter for AfroMessage (Ethiopia verified SMS Gateway).
 * Uses GET https://api.afromessage.com/api/send with Bearer token authentication.
 */
class AfroMessageProvider implements SmsProviderInterface
{
    protected string $token;
    protected string $sender;
    protected string $baseUrl;

    public function __construct(
        ?string $token = null,
        ?string $sender = null,
        ?string $baseUrl = null
    ) {
        $this->token = $token
            ?: config('services.afromessage.token')
            ?: env('AFROMESSAGE_TOKEN', 'eyJhbGciOiJIUzI1NiJ9.eyJpZGVudGlmaWVyIjoiWllCUGZWaFJQckhaQjV1NVJtVGxWNnQ3R2VPVGRRbEIiLCJleHAiOjE5NDE5NzA2NTMsImlhdCI6MTc4NDIwNDI1MywianRpIjoiZDU0NzkxYWYtYmUzNS00NjQ5LTk5YTQtODNlZjlmZWEyNGY0In0.TrMmn3seSFFLsYPeJRRZ3kqU-SalvpsbCcKxFdYjfak');

        $this->sender = $sender
            ?: config('services.afromessage.sender')
            ?: env('AFROMESSAGE_SENDER', 'WechachaPlc');

        $this->baseUrl = $baseUrl
            ?: config('services.afromessage.url')
            ?: env('AFROMESSAGE_URL', 'https://api.afromessage.com/api/send');
    }

    /**
     * Send SMS via AfroMessage.
     *
     * @param string $to Phone number (e.g. +251911123456 or 0911123456)
     * @param string $message Text content
     * @return array
     */
    public function send(string $to, string $message): array
    {
        $phone = $this->normalizePhone($to);

        try {
            $response = Http::withToken($this->token)
                ->timeout(15)
                ->get($this->baseUrl, [
                    'sender'  => $this->sender,
                    'to'      => $phone,
                    'message' => $message,
                ]);

            $body = $response->json();
            $raw = is_array($body) ? $body : $response->body();

            if ($response->successful()) {
                $ack = is_array($body) ? ($body['acknowledge'] ?? '') : '';
                if ($ack === 'success' || $response->status() === 200) {
                    return [
                        'success'      => true,
                        'message_id'   => is_array($body) ? ($body['response']['id'] ?? ($body['id'] ?? null)) : null,
                        'error'        => null,
                        'raw_response' => $raw,
                    ];
                }
            }

            $errorMessage = is_array($body) && isset($body['error'])
                ? (is_array($body['error']) ? json_encode($body['error']) : (string)$body['error'])
                : ($response->body() ?: 'AfroMessage HTTP ' . $response->status());

            return [
                'success'      => false,
                'message_id'   => null,
                'error'        => $errorMessage,
                'raw_response' => $raw,
            ];
        } catch (\Throwable $e) {
            Log::error("AfroMessageProvider exception for {$phone}: " . $e->getMessage());
            return [
                'success'      => false,
                'message_id'   => null,
                'error'        => $e->getMessage(),
                'raw_response' => null,
            ];
        }
    }

    /**
     * Normalize to E.164 (+251...).
     */
    public function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        if ((str_starts_with($phone, '09') || str_starts_with($phone, '07')) && strlen($phone) === 10) {
            $phone = '+251' . substr($phone, 1);
        } elseif (str_starts_with($phone, '251') && strlen($phone) === 12) {
            $phone = '+' . $phone;
        } elseif (!str_starts_with($phone, '+')) {
            $phone = '+' . $phone;
        }
        return $phone;
    }
}
