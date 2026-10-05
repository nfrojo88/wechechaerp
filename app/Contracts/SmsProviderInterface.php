<?php

namespace App\Contracts;

/**
 * SmsProviderInterface
 *
 * Defines the contract for SMS gateway adapters (AfroMessage, Africa's Talking, Twilio, Log).
 */
interface SmsProviderInterface
{
    /**
     * Send an SMS message to a phone number.
     *
     * @param string $to Phone number in E.164 format (+251...)
     * @param string $message Text message under 160 characters
     * @return array [
     *   'success' => bool,
     *   'message_id' => ?string,
     *   'error' => ?string,
     *   'raw_response' => mixed
     * ]
     */
    public function send(string $to, string $message): array;
}
