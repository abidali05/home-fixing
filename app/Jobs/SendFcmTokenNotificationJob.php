<?php

namespace App\Jobs;

use App\Services\FirebaseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class SendFcmTokenNotificationJob implements ShouldQueue
{
    use Dispatchable, Queueable, SerializesModels;

    public function __construct(
        public array $tokens,
        public array $payload
    ) {
        $this->onQueue('notifications');
    }

    public function handle(FirebaseService $firebaseService): void
    {
        Log::info('Bulk FCM Job Started');

        if (empty($this->tokens)) {
            return;
        }

        $messaging = $firebaseService->messaging();

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
