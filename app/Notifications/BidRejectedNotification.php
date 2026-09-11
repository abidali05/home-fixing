<?php

namespace App\Notifications;

use App\Models\JobRequestModel;
use App\Models\User;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class BidRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly JobRequestModel $job,
        private readonly User $customer,
        private readonly ?string $customMessage = null
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        if ((int) ($notifiable->role ?? -1) === 1) {
            return [FcmChannel::class, 'database'];
        }

        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'bid_rejected',
            'title' => 'Bid Rejected',
            'message' => $this->customMessage ?? ($this->customer->name . ' rejected your bid request.'),
            'data' => [
                'job_id' => (int) $this->job->id,
                'customer_id' => (int) $this->customer->id,
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
