<?php

namespace App\Notifications;

use App\Models\MarketplaceOrder;
use App\Models\User;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class MarketplaceOrderReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly MarketplaceOrder $order,
        private readonly User $customer
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        if ((int) ($notifiable->role ?? -1) === 2) {
            return [FcmChannel::class, 'database'];
        }

        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'marketplace_order_received',
            'title' => 'New Order Received',
            'message' => 'You received an order from ' . $this->customer->name . '.',
            'data' => [
                'order_id' => (int) $this->order->id,
                'order_number' => (string) $this->order->order_number,
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
