<?php

namespace App\Services\Payment;

use App\Models\Cart;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceOrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\Job\HireProviderService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TapPaymentService
{
    protected string $baseUrl = 'https://api.tap.company/v2';
    protected HireProviderService $hireProviderService;

    public function __construct(HireProviderService $hireProviderService)
    {
        $this->hireProviderService = $hireProviderService;
    }

    /**
     * Creates a charge using the Tap Payments API v2 with a client token.
     */
    public function createCharge(Payment $payment, string $token = 'src_all'): array
    {
        $secretKey = config('services.tap.secret_key');
        if (empty($secretKey)) {
            Log::error("TapPaymentService: TAP_SECRET_KEY is empty in config/services.php or server .env file.");
            throw new \RuntimeException('Tap Payments API Secret Key is not configured in server .env file.');
        }

        $webhookUrl = config('services.tap.webhook_url') ?: 'https://admin.azhlksa.com/api/v1/webhooks/tap';
        $redirectUrl = config('services.tap.redirect_url') ?: 'https://admin.azhlksa.com/tap/redirect';

        $user = $payment->user;
        $phoneDigits = preg_replace('/\D/', '', $user->phone ?? '500000000');
        if (strlen($phoneDigits) > 9) {
            $phoneDigits = substr($phoneDigits, -9);
        }

        $payload = [
            'amount' => (float) $payment->amount,
            'currency' => strtoupper($payment->currency ?: 'SAR'),
            'threeDSecure' => true,
            'save_card' => false,
            'description' => "Payment for Job #{$payment->job_id} (Bid #{$payment->bid_id})",
            'statement_descriptor' => "AZHL JOB {$payment->job_id}",
            'metadata' => [
                'payment_id' => (string) $payment->id,
                'job_id' => (string) $payment->job_id,
                'bid_id' => (string) $payment->bid_id,
                'user_id' => (string) $payment->user_id,
                'provider_id' => (string) $payment->provider_id,
            ],
            'reference' => [
                'transaction' => "PAY-{$payment->id}",
                'order' => "JOB-{$payment->job_id}",
            ],
            'receipt' => [
                'email' => true,
                'sms' => true,
            ],
            'customer' => [
                'first_name' => $user->name ?? 'Customer',
                'email' => $user->email ?? 'customer@azhl.com',
                'phone' => [
                    'country_code' => '966',
                    'number' => $phoneDigits ?: '500000000',
                ],
            ],
            'source' => [
                'id' => $token ?: 'src_all',
            ],
            'post' => [
                'url' => $webhookUrl,
            ],
            'redirect' => [
                'url' => $redirectUrl,
            ],
        ];

        Log::info("TapPaymentService: Dispatching charge request for payment #{$payment->id}", [
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'token' => $token,
        ]);

        $response = Http::withToken($secretKey)
            ->acceptJson()
            ->post("{$this->baseUrl}/charges", $payload);

        $responseData = $response->json() ?? [];

        Log::info("TapPaymentService: Charge API response for payment #{$payment->id}", [
            'http_code' => $response->status(),
            'response' => $responseData,
        ]);

        if ($response->failed()) {
            $errorMessage = $responseData['errors'][0]['description'] ?? ($responseData['message'] ?? 'Failed to communicate with Tap Payments.');
            Log::error("TapPaymentService: Charge API request failed for payment #{$payment->id}: {$errorMessage}");

            $payment->update([
                'status' => 'failed',
                'gateway_response' => $responseData,
            ]);

            throw new \RuntimeException($errorMessage, $response->status());
        }

        return $responseData;
    }

    /**
     * Creates a charge request on Tap Payments API for a Marketplace Order or Cart Checkout.
     */
    public function createMarketplaceCharge(Payment $payment, ?MarketplaceOrder $order = null, string $token = 'src_all'): array
    {
        $secretKey = config('services.tap.secret_key');
        if (empty($secretKey)) {
            throw new \RuntimeException('Tap Payments API Secret Key is not configured in server .env file.');
        }

        $webhookUrl = config('services.tap.webhook_url') ?: 'https://admin.azhlksa.com/api/v1/webhooks/tap';
        $redirectUrl = config('services.tap.redirect_url') ?: 'https://admin.azhlksa.com/tap/redirect';

        $user = $payment->user ?: User::find($payment->user_id);
        $phoneDigits = preg_replace('/\D/', '', $user->phone ?? '');
        if (strlen($phoneDigits) > 9) {
            $phoneDigits = substr($phoneDigits, -9);
        }

        $orderNumber = $order ? $order->order_number : ("CART-{$payment->id}");

        $payload = [
            'amount' => (float) $payment->amount,
            'currency' => strtoupper($payment->currency ?: 'SAR'),
            'threeDSecure' => true,
            'save_card' => false,
            'description' => $order ? "Payment for Marketplace Order #{$order->order_number}" : "Payment for Marketplace Cart Checkout #{$payment->id}",
            'statement_descriptor' => "AZHL MARKETPLACE",
            'metadata' => [
                'payment_id' => (string) $payment->id,
                'marketplace_order_id' => $order ? (string) $order->id : '',
                'user_id' => (string) $payment->user_id,
                'type' => 'marketplace_checkout',
            ],
            'reference' => [
                'transaction' => "MKT-{$payment->id}",
                'order' => $orderNumber,
            ],
            'receipt' => [
                'email' => true,
                'sms' => true,
            ],
            'customer' => [
                'first_name' => $user->name ?? 'Customer',
                'email' => $user->email ?? 'customer@azhl.com',
                'phone' => [
                    'country_code' => '966',
                    'number' => $phoneDigits ?: '500000000',
                ],
            ],
            'source' => [
                'id' => $token ?: 'src_all',
            ],
            'post' => [
                'url' => $webhookUrl,
            ],
            'redirect' => [
                'url' => $redirectUrl,
            ],
        ];

        Log::info("TapPaymentService: Initiating marketplace charge request for payment #{$payment->id}", ['payload' => $payload]);

        $response = Http::withToken($secretKey)
            ->acceptJson()
            ->post("{$this->baseUrl}/charges", $payload);

        $responseData = $response->json() ?? [];

        if ($response->failed()) {
            $errorMessage = $responseData['errors'][0]['description'] ?? ($responseData['message'] ?? 'Failed to communicate with Tap Payments.');
            $payment->update([
                'status' => 'failed',
                'gateway_response' => $responseData,
            ]);

            throw new \RuntimeException($errorMessage, $response->status());
        }

        return $responseData;
    }

    /**
     * Retrieves charge details directly from Tap Payments API.
     */
    public function retrieveCharge(string $chargeId): array
    {
        $secretKey = config('services.tap.secret_key');
        if (empty($secretKey)) {
            throw new \RuntimeException('Tap Payments API Secret Key is not configured in server .env file.');
        }

        $response = Http::withToken($secretKey)
            ->acceptJson()
            ->get("{$this->baseUrl}/charges/{$chargeId}");

        $responseData = $response->json() ?? [];

        if ($response->failed()) {
            Log::error("TapPaymentService: Failed to retrieve charge {$chargeId}: " . json_encode($responseData));
            throw new \RuntimeException("Unable to retrieve charge {$chargeId} from Tap Payments.");
        }

        return $responseData;
    }

    /**
     * Verifies a charge status and executes provider hiring or marketplace order creation if status is CAPTURED.
     */
    public function verifyCharge(string $chargeId, Payment $payment): bool
    {
        $chargeData = $this->retrieveCharge($chargeId);
        $status = strtoupper($chargeData['status'] ?? 'UNKNOWN');

        Log::info("TapPaymentService: Verifying charge {$chargeId} for payment #{$payment->id}. Status: {$status}");

        // Validate payload details match payment record
        $metaPaymentId = $chargeData['metadata']['payment_id'] ?? null;
        if ($metaPaymentId && (string) $metaPaymentId !== (string) $payment->id) {
            Log::warning("TapPaymentService: Charge {$chargeId} metadata payment_id mismatch! Expected: {$payment->id}, Got: {$metaPaymentId}");
            return false;
        }

        if ($status === 'CAPTURED') {
            $payment->update([
                'status' => 'captured',
                'tap_charge_id' => $chargeId,
                'gateway_response' => array_merge($payment->gateway_response ?? [], ['charge' => $chargeData]),
            ]);

            // If order already existed
            if ($payment->marketplace_order_id) {
                $order = MarketplaceOrder::find($payment->marketplace_order_id);
                if ($order) {
                    $order->update([
                        'status' => 'confirmed',
                        'payment_method' => 'tap',
                    ]);
                }
                return true;
            }

            // If payment was for Cart Checkout (Order not created yet), convert Cart to Order NOW!
            if (!$payment->job_id && !$payment->marketplace_order_id) {
                $createdOrder = $this->convertCartToMarketplaceOrder($payment);
                return (bool) $createdOrder;
            }

            // Hire Provider
            return $this->hireProviderService->hireProvider($payment);
        }

        if (in_array($status, ['FAILED', 'DECLINED', 'CANCELLED', 'EXPIRED', 'RESTRICTED'])) {
            $payment->update([
                'status' => 'failed',
                'tap_charge_id' => $chargeId,
                'gateway_response' => array_merge($payment->gateway_response ?? [], ['charge' => $chargeData]),
            ]);

            return false;
        }

        // Status is still processing or initiated
        $payment->update([
            'status' => 'processing',
            'tap_charge_id' => $chargeId,
            'gateway_response' => array_merge($payment->gateway_response ?? [], ['charge' => $chargeData]),
        ]);

        return false;
    }

    /**
     * Converts a customer's cart into a real MarketplaceOrder upon successful payment charge.
     */
    public function convertCartToMarketplaceOrder(Payment $payment): ?MarketplaceOrder
    {
        if ($payment->marketplace_order_id) {
            return MarketplaceOrder::find($payment->marketplace_order_id);
        }

        $userId = $payment->user_id;
        $user = User::find($userId);
        if (!$user) {
            return null;
        }

        $meta = is_array($payment->gateway_response) ? ($payment->gateway_response['checkout_metadata'] ?? []) : [];

        $cartItems = Cart::with('product')
            ->where('user_id', $userId)
            ->get();

        if ($cartItems->isEmpty() && empty($meta['cart_items'])) {
            Log::warning("convertCartToMarketplaceOrder: No cart items found for user {$userId}");
            return null;
        }

        return DB::transaction(function () use ($payment, $user, $cartItems, $meta) {
            $subtotal = (float) ($meta['subtotal'] ?? ($cartItems->isNotEmpty() ? $cartItems->sum('total_price') : $payment->amount));
            $shippingCost = (float) ($meta['shipping_cost'] ?? 0);
            $taxAmount = (float) ($meta['tax_amount'] ?? 0);
            $totalAmount = (float) ($payment->amount ?: ($subtotal + $shippingCost + $taxAmount));

            $order = MarketplaceOrder::create([
                'user_id' => $user->id,
                'order_number' => 'ORD-' . now()->format('ymd') . Str::upper(Str::random(4)),
                'shipping_address' => $meta['shipping_address'] ?? ($user->address ?? 'Default Address'),
                'subtotal' => $subtotal,
                'shipping_cost' => $shippingCost,
                'tax_amount' => $taxAmount,
                'discount_price' => 0,
                'total_amount' => $totalAmount,
                'payment_method' => 'tap',
                'notes' => $meta['notes'] ?? null,
                'status' => 'confirmed',
            ]);

            if ($cartItems->isNotEmpty()) {
                $productIds = $cartItems->pluck('product_id')->unique()->values();
                $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

                foreach ($cartItems as $cartItem) {
                    $product = $products->get($cartItem->product_id);
                    if ($product) {
                        MarketplaceOrderItem::create([
                            'marketplace_order_id' => $order->id,
                            'product_id' => $product->id,
                            'shop_id' => !empty($product->user_id) ? $product->user_id : null,
                            'product_name' => $product->product_name,
                            'quantity' => $cartItem->quantity,
                            'base_price' => $cartItem->base_price,
                            'total_price' => $cartItem->total_price,
                        ]);
                    }
                }

                // Clear customer cart after order is successfully placed & paid!
                Cart::where('user_id', $user->id)->delete();
            }

            $payment->update([
                'marketplace_order_id' => $order->id,
                'status' => 'captured',
            ]);

            Log::info("convertCartToMarketplaceOrder: Successfully created MarketplaceOrder #{$order->id} ({$order->order_number}) for payment #{$payment->id}");

            return $order;
        });
    }

    /**
     * Idempotently handles incoming Tap webhook notifications.
     */
    public function handleWebhook(array $payload): bool
    {
        $chargeId = $payload['id'] ?? null;
        $metaPaymentId = $payload['metadata']['payment_id'] ?? null;

        if (!$chargeId) {
            Log::warning("TapPaymentService: Webhook received without charge ID.");
            return false;
        }

        Log::info("TapPaymentService: Processing webhook for charge {$chargeId}", ['payload' => $payload]);

        $payment = null;
        if ($metaPaymentId) {
            $payment = Payment::find($metaPaymentId);
        }

        if (!$payment) {
            $payment = Payment::where('tap_charge_id', $chargeId)->first();
        }

        if (!$payment) {
            Log::error("TapPaymentService: Webhook received for unknown charge {$chargeId} / payment #{$metaPaymentId}");
            return false;
        }

        // Idempotency check: If already captured and order created, return true
        if ($payment->status === 'captured' && $payment->marketplace_order_id) {
            Log::info("TapPaymentService: Payment #{$payment->id} is already captured. Webhook execution skipped.");
            return true;
        }

        // Retrieve fresh charge details directly from Tap API for security verification
        return $this->verifyCharge($chargeId, $payment);
    }
}
