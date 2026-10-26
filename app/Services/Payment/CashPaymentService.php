<?php

namespace App\Services\Payment;

use App\Jobs\SendFcmTokenNotificationJob;
use App\Models\Orders;
use App\Models\Payment;
use App\Models\ProviderDuesLedger;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CashPaymentService
{
    /**
     * Customer selects Cash or Card payment method
     */
    public function setPaymentMethod(Orders $order, User $customer, string $paymentMethod): array
    {
        if ((int) $order->user_id !== (int) $customer->id) {
            throw new \Exception('You are not authorized to update this order payment method.', 403);
        }

        if ((int) $order->paid_to_system === 1 || $order->payment_status === 'paid') {
            throw new \Exception('This order has already been paid.', 400);
        }

        $paymentMethod = strtolower($paymentMethod);
        if (!in_array($paymentMethod, ['cash', 'card'], true)) {
            throw new \Exception('Invalid payment method. Allowed: cash, card', 422);
        }

        $order->payment_method = $paymentMethod;
        if ($paymentMethod === 'cash') {
            $order->payment_status = 'cash_pending';
        } else {
            $order->payment_status = 'pending';
        }

        $order->save();

        // Calculate up-to-date financials
        $financials = $order->calculateAndSyncFinancials(true);
        $totalAmount = (float) $financials['customer_total'];

        // Send Push Notification to Provider if cash chosen
        if ($paymentMethod === 'cash' && $order->provider_id) {
            $provider = User::find($order->provider_id);
            if ($provider && !empty($provider->fcm_token)) {
                try {
                    dispatch(new SendFcmTokenNotificationJob([$provider->fcm_token], [
                        'title' => 'Customer selected Cash Payment',
                        'body' => "Please collect SAR {$totalAmount} in cash upon completing the service.",
                        'data' => [
                            'order_id' => (string) $order->id,
                            'type' => 'cash_payment_selected',
                            'total_amount' => (string) $totalAmount,
                        ],
                    ]));
                } catch (\Throwable $e) {
                    Log::error("CashPaymentService: Failed sending FCM to provider {$provider->id}: " . $e->getMessage());
                }
            }
        }

        return [
            'order_id' => (int) $order->id,
            'payment_method' => $order->payment_method,
            'payment_status' => $order->payment_status,
            'total_amount' => $totalAmount,
            'currency' => 'SAR',
        ];
    }

    /**
     * Provider confirms cash received from customer
     */
    public function confirmCashReceived(Orders $order, User $provider, ?float $collectedAmount = null): array
    {
        if ((int) $order->provider_id !== (int) $provider->id) {
            throw new \Exception('You are not authorized to confirm cash for this order.', 403);
        }

        if ($order->payment_method !== 'cash') {
            throw new \Exception('This order is not marked for Cash payment.', 400);
        }

        if ((int) $order->paid_to_system === 1 || $order->payment_status === 'paid') {
            throw new \Exception('Cash payment for this order has already been confirmed.', 400);
        }

        return DB::transaction(function () use ($order, $provider, $collectedAmount) {
            // Lock order and provider rows for concurrency safety
            $order = Orders::where('id', $order->id)->lockForUpdate()->first();
            $provider = User::where('id', $provider->id)->lockForUpdate()->first();

            $financials = $order->calculateAndSyncFinancials(true);
            $customerTotal = (float) $financials['customer_total'];
            $appServiceFee = (float) $financials['customer_app_fee'];
            $providerCommission = (float) $financials['azhl_fee'];
            $platformFeeDue = round($appServiceFee + $providerCommission, 2);

            $finalCollected = $collectedAmount !== null && $collectedAmount > 0
                ? round($collectedAmount, 2)
                : $customerTotal;

            // Mark order as paid
            $order->payment_status = 'paid';
            $order->paid_to_system = 1;
            $order->cash_collected_amount = $finalCollected;
            $order->cash_received_at = now();
            $order->save();

            // Record a Cash Payment entry in payments table for unified audit
            Payment::create([
                'user_id' => $order->user_id,
                'provider_id' => $provider->id,
                'job_id' => $order->job_id,
                'amount' => $finalCollected,
                'currency' => 'SAR',
                'gateway' => 'cash',
                'status' => 'captured',
                'gateway_response' => json_encode([
                    'type' => 'offline_cash',
                    'order_id' => $order->id,
                    'platform_cut' => $platformFeeDue,
                    'app_fee' => $appServiceFee,
                    'azhl_commission' => $providerCommission,
                    'confirmed_by_provider_id' => $provider->id,
                    'timestamp' => now()->toIso8601String(),
                ]),
            ]);

            // Calculate current wallet balance of provider (available for withdrawal / deduction)
            $stats = app(\App\Http\Controllers\Api\WithdrawalController::class)->calculateWalletBalance($provider->id, 'provider');
            $availableWallet = (float) ($stats['available_for_withdrawal'] ?? 0.0);

            $previousDue = (float) ($provider->payment_due ?? 0.0);
            $deductedFromWallet = 0.0;
            $addedToDue = 0.0;

            if ($platformFeeDue > 0) {
                if ($availableWallet >= $platformFeeDue) {
                    // Fully covered by available wallet balance
                    $deductedFromWallet = $platformFeeDue;
                    $addedToDue = 0.0;
                    $newDue = $previousDue;

                    ProviderDuesLedger::create([
                        'provider_id' => $provider->id,
                        'order_id' => $order->id,
                        'type' => 'credit_wallet_deduction',
                        'amount' => $deductedFromWallet,
                        'previous_due' => $previousDue,
                        'current_due' => $newDue,
                        'notes' => "Platform fee of {$platformFeeDue} SAR for Order #{$order->id} auto-covered by existing wallet balance",
                    ]);
                } else {
                    // Partially or not covered
                    $deductedFromWallet = max(0.0, $availableWallet);
                    $remainingDueToAdd = round($platformFeeDue - $deductedFromWallet, 2);
                    $addedToDue = $remainingDueToAdd;
                    $newDue = round($previousDue + $remainingDueToAdd, 2);

                    $provider->payment_due = $newDue;
                    $provider->save();

                    ProviderDuesLedger::create([
                        'provider_id' => $provider->id,
                        'order_id' => $order->id,
                        'type' => 'debit_cash_order',
                        'amount' => $remainingDueToAdd,
                        'previous_due' => $previousDue,
                        'current_due' => $newDue,
                        'notes' => "Platform fee of {$platformFeeDue} SAR for Order #{$order->id}. Deducted from wallet: {$deductedFromWallet} SAR, added to dues: {$remainingDueToAdd} SAR",
                    ]);
                }
            } else {
                $newDue = $previousDue;
            }

            // Send notification to customer
            $customer = User::find($order->user_id);
            if ($customer && !empty($customer->fcm_token)) {
                try {
                    dispatch(new SendFcmTokenNotificationJob([$customer->fcm_token], [
                        'title' => 'Payment Confirmed',
                        'body' => "The provider confirmed receiving SAR {$finalCollected} in cash. Thank you!",
                        'data' => [
                            'order_id' => (string) $order->id,
                            'type' => 'payment_confirmed',
                            'collected_amount' => (string) $finalCollected,
                        ],
                    ]));
                } catch (\Throwable $e) {
                    Log::error("CashPaymentService: Failed sending FCM to customer {$customer->id}: " . $e->getMessage());
                }
            }

            return [
                'order_id' => (int) $order->id,
                'payment_status' => 'paid',
                'collected_amount' => $finalCollected,
                'platform_fee_total' => $platformFeeDue,
                'deducted_from_wallet' => $deductedFromWallet,
                'added_to_dues' => $addedToDue,
                'current_payment_due' => (float) $newDue,
            ];
        });
    }

    /**
     * Check if provider is blocked from bidding/accepting orders due to exceeded dues
     */
    public static function checkProviderDueLimit(User $provider): void
    {
        $due = (float) ($provider->payment_due ?? 0.0);
        $threshold = (float) ($provider->max_due_threshold ?? 25.00);

        if ($due >= $threshold) {
            throw new \Exception("You have exceeded the maximum allowed unpaid cash commission threshold ({$due} SAR >= {$threshold} SAR). Please clear your pending platform dues before accepting new jobs or placing bids.", 403);
        }
    }
}
