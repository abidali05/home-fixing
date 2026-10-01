<?php

namespace App\Notifications;

use App\Models\Orders;
use App\Models\User;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ExtraPaymentRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Orders $order,
        private readonly User $provider,
        private readonly float $extraAmount,
        private readonly ?string $reason
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return [FcmChannel::class, 'database'];
    }

    public function toArray(object $notifiable): array
    {
        $amountFormatted = number_format($this->extraAmount, 2, '.', '');

        return [
            'type' => 'EXTRA_PAYMENT_REQUEST',
            'title' => 'Extra Work Approval Request',
            'message' => 'Provider requested SAR ' . $amountFormatted . ' for additional work.',
            'data' => [
                'type' => 'EXTRA_PAYMENT_REQUEST',
                'order_id' => (string) $this->order->id,
                'extra_amount' => $amountFormatted,
                'extra_amount_reason' => (string) ($this->reason ?? ''),
                'provider_id' => (int) $this->provider->id,
                'provider_name' => (string) ($this->provider->name ?? 'Provider'),
            ],
        ];
    }

    public function toFcm(object $notifiable): array
    {
        $array = $this->toArray($notifiable);

        return [
            'type' => 'EXTRA_PAYMENT_REQUEST',
            'title' => $array['title'],
            'message' => $array['message'],
            'data' => [
                'type' => 'EXTRA_PAYMENT_REQUEST',
                'order_id' => (string) $this->order->id,
                'extra_amount' => number_format($this->extraAmount, 2, '.', ''),
                'extra_amount_reason' => (string) ($this->reason ?? ''),
            ],
        ];
    }
}
