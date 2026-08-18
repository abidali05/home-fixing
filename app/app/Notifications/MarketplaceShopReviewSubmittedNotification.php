<?php

namespace App\Notifications;

use App\Models\MarketplaceOrder;
use App\Models\User;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class MarketplaceShopReviewSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly MarketplaceOrder $order,
        private readonly User $customer,
        private readonly float $rating
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
            'type' => 'marketplace_shop_review_submitted',
            'title' => 'New Shop Review',
            'message' => $this->customer->name . ' added a ' . $this->rating . '-star review on your shop.',
            'data' => [
                'marketplace_order_id' => (int) $this->order->id,
                'customer_id' => (int) $this->customer->id,
                'rating' => $this->rating,
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

