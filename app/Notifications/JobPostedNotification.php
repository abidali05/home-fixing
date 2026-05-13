<?php

namespace App\Notifications;

use App\Models\JobRequestModel;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class JobPostedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly JobRequestModel $job)
    {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'job_post',
            'title' => 'New Job Available',
            'message' => 'A new job is available in your category',
            'data' => [
                'job_id' => (int) $this->job->id,
                'category_id' => (int) $this->job->category_id,
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
