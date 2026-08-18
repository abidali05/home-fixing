<?php

namespace App\Notifications\Channels;

use App\Services\FirebaseService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class FcmChannel
{
    public function __construct(private readonly FirebaseService $firebaseService)
    {
    }

    public function send(object $notifiable, Notification $notification): void
    {
        if (!method_exists($notification, 'toFcm')) {
            return;
        }

        $token = $notifiable->routeNotificationFor('fcm', $notification);
        if (empty($token)) {
            return;
        }

        $payload = $notification->toFcm($notifiable);

        try {
            $this->firebaseService->send(
                token: $token,
                title: (string) ($payload['title'] ?? ''),
                body: (string) ($payload['message'] ?? ''),
                data: (array) ($payload['data'] ?? [])
            );
        } catch (\Throwable $e) {
            Log::error('FCM notification failed', [
                'notification' => get_class($notification),
                'notifiable_id' => $notifiable->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

