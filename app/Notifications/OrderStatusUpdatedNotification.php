<?php

namespace App\Notifications;

use App\Models\Orders;
use App\Models\User;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class OrderStatusUpdatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Orders $order,
        private readonly User $updatedBy,
        private readonly string $status
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        $recipientRole = (int) ($notifiable->role ?? -1);
        $actorRole = (int) ($this->updatedBy->role ?? -1);

        $isRoleAligned = ($actorRole === 1 && $recipientRole === 0)
            || ($actorRole === 0 && $recipientRole === 1);

        if ($isRoleAligned) {
            return [FcmChannel::class, 'database'];
        }

        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $label = str_replace('_', ' ', $this->status);
        $isCustomerDecision = (int) ($this->updatedBy->role ?? -1) === 0
            && in_array($this->status, ['completed', 'working'], true);

        $data = [
            'order_id' => (int) $this->order->id,
            'job_id' => (int) $this->order->job_id,
            'updated_by_id' => (int) $this->updatedBy->id,
            'updated_by_role' => (int) $this->updatedBy->role,
            'status' => $this->status,
        ];

        // if ($isCustomerDecision) {
            $data['customer'] = [
                'id' => (int) $this->updatedBy->id,
                'name' => (string) ($this->updatedBy->name ?? ''),
                'phone' => (string) ($this->updatedBy->phone ?? ''),
                'image' => (string) ($this->updatedBy->profile_image ?? ''),
            ];
        // }

        return [
            'type' => 'order_status_updated',
            'title' => 'Order Status Updated',
            'message' => $this->updatedBy->name . ' updated your order status to ' . $label . '.',
            'data' => $data,
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
