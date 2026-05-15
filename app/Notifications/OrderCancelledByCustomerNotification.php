<?php

namespace App\Notifications;

use App\Models\Orders;
use App\Models\User;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class OrderCancelledByCustomerNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Orders $order,
        private readonly User $canceller
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        $recipientRole = (int) ($notifiable->role ?? -1);
        $cancellerRole = (int) ($this->canceller->role ?? -1);

        $isRoleAligned = ($cancellerRole === 0 && $recipientRole === 1)
            || ($cancellerRole === 1 && $recipientRole === 0);

        if ($isRoleAligned) {
            return ['database', FcmChannel::class];
        }

        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $cancelType = (int) ($this->canceller->role ?? -1) === 1
            ? 'order_cancelled_by_provider'
            : 'order_cancelled_by_customer';

        return [
            'type' => $cancelType,
            'title' => 'Order Cancelled',
            'message' => $this->canceller->name . ' cancelled the order.',
            'data' => [
                'order_id' => (int) $this->order->id,
                'job_id' => (int) $this->order->job_id,
                'cancelled_by_id' => (int) $this->canceller->id,
                'cancelled_by_role' => (int) ($this->canceller->role ?? -1),
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
