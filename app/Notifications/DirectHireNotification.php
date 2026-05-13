<?php

namespace App\Notifications;

use App\Models\JobRequestModel;
use App\Models\User;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class DirectHireNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly JobRequestModel $job,
        private readonly User $requestingUser
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'direct_hire',
            'title' => 'Direct Hire Request',
            'message' => $this->requestingUser->name . ' wants to hire you',
            'data' => [
                'job_id' => (int) $this->job->id,
                'provider_id' => (int) $notifiable->id,
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
