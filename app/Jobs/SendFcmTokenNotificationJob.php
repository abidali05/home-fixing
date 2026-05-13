<?php

namespace App\Jobs;

use App\Notifications\FcmTokenNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class SendFcmTokenNotificationJob implements ShouldQueue
{
    use Dispatchable, Queueable, SerializesModels;

    public function __construct(
        public array $tokens,
        public array $payload
    ) {}

    public function handle(Messaging $messaging): void
    {
        Log::info('Bulk FCM Job Started');

        foreach (array_chunk($this->tokens, 200) as $chunk) {

            foreach ($chunk as $token) {
                try {
                    $message = CloudMessage::withTarget('token', $token)
                        ->withNotification(Notification::create(
                            $this->payload['title'],
                            $this->payload['body']
                        ))
                        ->withData($this->payload['data']);

                    $messaging->send($message);

                } catch (\Throwable $e) {
                    Log::error('FCM failed', [
                        'token' => $token,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
    }
}
