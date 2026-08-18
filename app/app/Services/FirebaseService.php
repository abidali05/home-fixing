<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class FirebaseService
{
    public function messaging(): Messaging
    {
        $serviceAccountPath = storage_path('app/firebase/service-account.json');

        if (!file_exists($serviceAccountPath)) {
            throw new \RuntimeException("Firebase service account file not found at: {$serviceAccountPath}");
        }

        return (new Factory)
            ->withServiceAccount($serviceAccountPath)
            ->createMessaging();
    }

    public function send(string $token, string $title, string $body, array $data = []): void
    {
        $message = CloudMessage::withTarget('token', $token)
            ->withNotification(Notification::create($title, $body))
            ->withData($this->stringifyData($data));

        $this->messaging()->send($message);
    }

    private function stringifyData(array $data): array
    {
        $normalized = [];
        foreach ($data as $key => $value) {
            $normalized[(string) $key] = is_scalar($value) ? (string) $value : json_encode($value);
        }

        return $normalized;
    }
}
