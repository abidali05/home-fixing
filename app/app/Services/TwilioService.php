<?php

namespace App\Services;

use Twilio\Rest\Client;

class TwilioService
{
    protected Client $twilio;

    public function __construct()
    {
        $this->twilio = new Client(
            config('services.twilio.sid'),
            config('services.twilio.token')
        );
    }

    public function sendOtp(string $phone, string $otp)
    {
        $appHash = config('services.twilio.app_hash');

        $messageBody = "Your Azhl verification code is {$otp}\n{$appHash}";

        // dd([
        //     'to' => $phone,
        //     'from' => config('services.twilio.phone_number'),
        //     'messaging_service_sid' => config('services.twilio.messaging_sid'),
        //     'body' => $messageBody,
        // ]);

        return $this->twilio->messages->create($phone, [
            'messagingServiceSid' => config('services.twilio.messaging_sid'),
            'body' => $messageBody,
        ]);
    }
}
