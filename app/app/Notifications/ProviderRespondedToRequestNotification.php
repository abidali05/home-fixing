<?php

namespace App\Notifications;

use App\Models\JobRequestModel;
use App\Models\User;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ProviderRespondedToRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly JobRequestModel $jobRequest,
        private readonly User $provider,
        private readonly string $status
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ((int) ($notifiable->role ?? -1) === 0) {
            $channels[] = FcmChannel::class;
        }

        return $channels;
    }

    public function toArray(object $notifiable): array
    {
        $isAccepted = $this->status === 'accepted';

        return [
            'type' => $isAccepted ? 'provider_request_accepted' : 'provider_request_rejected',
            'title' => $isAccepted ? 'Request Accepted' : 'Request Rejected',
            'message' => $this->provider->name . ($isAccepted
                ? ' accepted your job request.'
                : ' rejected your job request.'),
            'data' => [
                'job_id' => (int) $this->jobRequest->id,
                'provider_id' => (int) $this->provider->id,
                'status' => $this->status,
            ],
        ];
    }

    public function toFcm(object $notifiable): array
    {
        $array = $this->toArray($notifiable);

        return [
            'type' => $array['type'],
            'title' => $array['title'],
            'message' => $array['message'],
            'data' => [
                ...$array['data'],
                'type' => $array['type'],
            ],
        ];
    }
}
