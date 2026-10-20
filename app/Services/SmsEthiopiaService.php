<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsEthiopiaService
{
    protected $token   = 'eyJhbGciOiJIUzI1NiJ9.eyJpZGVudGlmaWVyIjoiWllCUGZWaFJQckhaQjV1NVJtVGxWNnQ3R2VPVGRRbEIiLCJleHAiOjE5NDE5NzA2NTMsImlhdCI6MTc4NDIwNDI1MywianRpIjoiZDU0NzkxYWYtYmUzNS00NjQ5LTk5YTQtODNlZjlmZWEyNGY0In0.TrMmn3seSFFLsYPeJRRZ3kqU-SalvpsbCcKxFdYjfak';
    protected $sender  = 'WechachaPlc'; // Verified sender name - WHALE short code 9786
    protected $baseUrl = 'https://api.afromessage.com/api/send';

    /**
     * Send OTP SMS
     */
    public function sendOTP($phoneNumber, $otp)
    {
        $message = "Your Construct-Pro ERP verification code is: {$otp}. Valid for 10 minutes. Do not share this code.";
        return $this->sendMessage($phoneNumber, $message);
    }

    /**
     * Send a general notification SMS
     */
    public function sendNotification($phoneNumber, $message)
    {
        return $this->sendMessage($phoneNumber, $message);
    }

    /**
     * Core method to send message via AfroMessage API.
     *
     * AfroMessage API uses a GET request with Bearer token authentication.
     * Endpoint: GET https://api.afromessage.com/api/send
     * Params: to, message, sender (optional), from (optional identifier ID)
     */
    public function sendMessage($phoneNumber, $message)
    {
        try {
            $phone = $this->formatPhoneNumber($phoneNumber);

            Log::info("Attempting to send SMS via AfroMessage to {$phone}");

            $response = Http::withToken($this->token)
                ->timeout(5)
                ->get($this->baseUrl, [
                    'sender'  => $this->sender,
                    'to'      => $phone,
                    'message' => $message,
                ]);

            $responseBody = $response->json() ?? $response->body();

            if ($response->successful()) {
                // AfroMessage returns {"acknowledge":"success"} on success
                $ack = is_array($responseBody) ? ($responseBody['acknowledge'] ?? '') : '';

                if ($ack === 'success' || $response->status() === 200) {
                    Log::info("SMS sent successfully via AfroMessage to {$phone}", [
                        'response' => $responseBody
                    ]);
                    return ['success' => true, 'message' => 'SMS sent successfully', 'data' => $responseBody];
                }
            }

            Log::error("Failed to send SMS to {$phone} via AfroMessage", [
                'status'   => $response->status(),
                'response' => $responseBody,
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send SMS. Please try again.',
                'error'   => $responseBody,
            ];

        } catch (\Exception $e) {
            Log::error("Exception sending SMS via AfroMessage: " . $e->getMessage(), [
                'phone' => $phoneNumber,
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'success' => false,
                'message' => 'Error sending SMS: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Format phone number for AfroMessage.
     * AfroMessage expects the number in +251XXXXXXXXX format.
     * Accepts: +251911234567, 0911234567, 911234567
     */
    private function formatPhoneNumber($phone)
    {
        $phone = str_replace([' ', '-', '(', ')'], '', $phone);
        $phone = ltrim($phone, '+');

        // 0911... => 251911...
        if (substr($phone, 0, 1) === '0') {
            $phone = '251' . substr($phone, 1);
        }

        // 911... => 251911...
        if (substr($phone, 0, 3) !== '251') {
            $phone = '251' . $phone;
        }

        return '+' . $phone;
    }

    /**
     * Generate a 6-digit OTP code.
     */
    public static function generateOTP()
    {
        return rand(100000, 999999);
    }
}

