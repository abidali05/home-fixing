<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Illuminate\Foundation\Bus\Dispatchable;

class SendFcmNotification implements ShouldQueue
{
    use Dispatchable;

    protected array $tokens;
    protected array $notification;

    public function __construct(array $tokens, array $notification)
    {
        $this->tokens = $tokens;
        $this->notification = $notification;
    }

    public function sendPriceAlertNotification(array $fcmTokens, array $notificationData)
    {
        if (!empty($fcmTokens) || $fcmTokens != NULL ||  $fcmTokens != 'null') {
            $messaging = app('firebase.messaging');

            $notification = Notification::create()
                ->withTitle($notificationData['title'])
                ->withBody($notificationData['body']);

            $data = [
                'vehicle_id' => $notificationData['vehicle_id'] ?? null,
                'updated_at' => now()->toDateTimeString(),
            ];

            $message = CloudMessage::new()
                ->withNotification($notification)
                ->withData($data);
            if (!empty($fcmTokens) || $fcmTokens != NULL ||  $fcmTokens != 'null') {
                $messaging->sendMulticast($message, $fcmTokens);
            }
        }
    }

    public function sendAddwishlistNotification($fcmtoken, $notificationData)
    {
        if (!empty($fcmTokens)) {
            $messaging = app('firebase.messaging');

            $notification = Notification::create()
                ->withTitle($notificationData['title'])
                ->withBody($notificationData['body']);

            $data = [
                'vehicle_id' => $notificationData['vehicle_id'] ?? null,
                'updated_at' => now()->toDateTimeString(),
            ];

            $message = CloudMessage::new()
                ->withNotification($notification)
                ->withData($data);

            $messaging->sendMulticast($message, $fcmtoken);
        }
    }

    public function handle()
    {
        if (!empty($this->tokens)) {
            $messaging = app('firebase.messaging');

            $notification = Notification::create()
                ->withTitle($this->notification['title'])
                ->withBody($this->notification['body']);

            $data = [
                'vehicle_id' => $this->notification['vehicle_id'] ?? null,
                'updated_at' => now()->toDateTimeString(),
            ];

            $message = CloudMessage::new()
                ->withNotification($notification)
                ->withData($data);

            try {
                $messaging->sendMulticast($message, $this->tokens);
            } catch (\Exception $e) {
                Log::error('Firebase Notification Error: ' . $e->getMessage());
            }
        }
    }
}
