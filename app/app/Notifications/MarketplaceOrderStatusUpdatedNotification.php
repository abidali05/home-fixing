<?php

namespace App\Notifications;

use App\Models\MarketplaceOrder;
use App\Models\User;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class MarketplaceOrderStatusUpdatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly MarketplaceOrder $order,
        private readonly User $shop,
        private readonly string $status
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        if ((int) ($notifiable->role ?? -1) === 0) {
            return [FcmChannel::class, 'database'];
        }

        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $label = str_replace('_', ' ', $this->status);

        return [
            'type' => 'marketplace_order_status_updated',
            'title' => 'Order Status Updated',
            'message' => $this->shop->name . ' updated your marketplace order status to ' . $label . '.',
            'data' => [
                'order_id' => (int) $this->order->id,
                'order_number' => (string) $this->order->order_number,
                'shop_id' => (int) $this->shop->id,
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

