<?php

namespace App\Notifications;

use App\Models\Orders;
use App\Models\User;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ExtraPaymentDecisionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Orders $order,
        private readonly User $customer,
        private readonly string $decision,
        private readonly float $extraAmount = 0.00
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
        $actionVerb = $this->decision === 'accepted' ? 'accepted' : 'rejected';
        $body = 'Customer has ' . $actionVerb . ' your extra charge of SAR ' . $amountFormatted . '.';

        return [
            'type' => 'EXTRA_PAYMENT_RESPONSE',
            'title' => 'Extra Charge Decision',
            'message' => $body,
            'data' => [
                'type' => 'EXTRA_PAYMENT_RESPONSE',
                'order_id' => (string) $this->order->id,
                'decision' => (string) $this->decision,
                'extra_amount' => $amountFormatted,
                'customer_id' => (int) $this->customer->id,
                'customer_name' => (string) ($this->customer->name ?? 'Customer'),
            ],
        ];
    }

    public function toFcm(object $notifiable): array
    {
        $array = $this->toArray($notifiable);

        return [
            'type' => 'EXTRA_PAYMENT_RESPONSE',
            'title' => $array['title'],
            'message' => $array['message'],
            'data' => [
                'type' => 'EXTRA_PAYMENT_RESPONSE',
                'order_id' => (string) $this->order->id,
                'decision' => (string) $this->decision,
            ],
        ];
    }
}
