<?php

namespace App\Notifications;

use App\Models\JobRequestModel;
use App\Models\User;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class BidReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly JobRequestModel $job,
        private readonly User $provider
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
        return [
            'type' => 'bid_received',
            'title' => 'New Bid Received',
            'message' => $this->provider->name . ' has submitted a bid for your job request.',
            'data' => [
                'job_id' => (int) $this->job->id,
                'provider_id' => (int) $this->provider->id,
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
