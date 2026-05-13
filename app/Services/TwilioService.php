<?php

namespace App\Services;

use Twilio\Rest\Client;

class TwilioService
{
    protected $twilio;

    public function __construct()
    {
        $this->twilio = new Client(
            config('services.twilio.sid'),
            config('services.twilio.token')
        );
    }

   public function sendOtp($phone, $otp)
{
   $this->twilio->messages->create($phone, [
    'messagingServiceSid' => config('services.twilio.messaging_sid'),
    'body' => "Your OTP code is: $otp"
]);

}

    public function verifyOtp($phone, $code)
    {
        return $this->twilio->verify->v2->services(config('services.twilio.verify_sid'))
            ->verificationChecks
            ->create([
                'to' => $phone,
                'code' => $code
            ]);
    }
}
