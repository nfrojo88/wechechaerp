<?php

namespace App\Services\Sms;

use App\Contracts\SmsProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * AfricasTalkingProvider
 *
 * SMS Adapter for Africa's Talking SMS API.
 */
class AfricasTalkingProvider implements SmsProviderInterface
{
    protected string $username;
    protected string $apiKey;
    protected ?string $shortcode;

    public function __construct(
        ?string $username = null,
        ?string $apiKey = null,
        ?string $shortcode = null
    ) {
        $this->username  = $username  ?: config('services.africastalking.username', env('AFRICASTALKING_USERNAME', 'sandbox'));
        $this->apiKey    = $apiKey    ?: config('services.africastalking.api_key', env('AFRICASTALKING_API_KEY', ''));
        $this->shortcode = $shortcode ?: config('services.africastalking.shortcode', env('AFRICASTALKING_SHORTCODE', ''));
    }

    public function send(string $to, string $message): array
    {
        $phone = $this->normalizePhone($to);

        if (empty($this->apiKey)) {
            return [
                'success'      => false,
                'message_id'   => null,
                'error'        => "Africa's Talking API key is missing",
                'raw_response' => null,
            ];
        }

        try {
            $url = 'https://api.africastalking.com/version1/messaging';
            $data = [
                'username' => $this->username,
                'to'       => $phone,
                'message'  => $message,
            ];
            if (!empty($this->shortcode)) {
                $data['from'] = $this->shortcode;
            }

            $response = Http::asForm()
                ->withHeaders([
                    'apiKey' => $this->apiKey,
                    'Accept' => 'application/json',
                ])
                ->timeout(15)
                ->post($url, $data);

            $body = $response->json();

            if ($response->status() === 201 && isset($body['SMSMessageData'])) {
                $recipients = $body['SMSMessageData']['Recipients'] ?? [];
                $first = $recipients[0] ?? [];
                $status = $first['status'] ?? 'Success';

                return [
                    'success'      => in_array(strtolower($status), ['success', 'sent']),
                    'message_id'   => $first['messageId'] ?? null,
                    'error'        => in_array(strtolower($status), ['success', 'sent']) ? null : $status,
                    'raw_response' => $body,
                ];
            }

            return [
                'success'      => false,
                'message_id'   => null,
                'error'        => 'HTTP ' . $response->status() . ': ' . $response->body(),
                'raw_response' => $body,
            ];
        } catch (\Throwable $e) {
            Log::error("AfricasTalkingProvider exception for {$phone}: " . $e->getMessage());
            return [
                'success'      => false,
                'message_id'   => null,
                'error'        => $e->getMessage(),
                'raw_response' => null,
            ];
        }
    }

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
