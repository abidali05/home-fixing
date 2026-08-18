<?php

namespace App\Services\Payment;

use App\Models\Payment;
use App\Services\Job\HireProviderService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
     *
     * @param Payment $payment
     * @param string $token
     * @return array
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
     * Creates a charge request on Tap Payments API for a Marketplace Order.
     */
    public function createMarketplaceCharge(Payment $payment, \App\Models\MarketplaceOrder $order, string $token = 'src_all'): array
    {
        $secretKey = config('services.tap.secret_key');
        if (empty($secretKey)) {
            throw new \RuntimeException('Tap Payments API Secret Key is not configured in server .env file.');
        }

        $webhookUrl = config('services.tap.webhook_url') ?: 'https://admin.azhlksa.com/api/v1/webhooks/tap';
        $redirectUrl = config('services.tap.redirect_url') ?: 'https://admin.azhlksa.com/tap/redirect';

        $user = $payment->user ?: \App\Models\User::find($payment->user_id);
        $phoneDigits = preg_replace('/\D/', '', $user->phone ?? '');
        if (strlen($phoneDigits) > 9) {
            $phoneDigits = substr($phoneDigits, -9);
        }

        $payload = [
            'amount' => (float) $payment->amount,
            'currency' => strtoupper($payment->currency ?: 'SAR'),
            'threeDSecure' => true,
            'save_card' => false,
            'description' => "Payment for Marketplace Order #{$order->order_number}",
            'statement_descriptor' => "AZHL MARKETPLACE",
            'metadata' => [
                'payment_id' => (string) $payment->id,
                'marketplace_order_id' => (string) $order->id,
                'user_id' => (string) $payment->user_id,
            ],
            'reference' => [
                'transaction' => "MKT-{$payment->id}",
                'order' => $order->order_number,
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

        Log::info("TapPaymentService: Initiating marketplace charge request for payment #{$payment->id} / order #{$order->id}", ['payload' => $payload]);

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
     *
     * @param string $chargeId
     * @return array
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
     * Verifies a charge status and executes provider hiring if status is CAPTURED.
     *
     * @param string $chargeId
     * @param Payment $payment
     * @return bool
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
                'gateway_response' => $chargeData,
            ]);

            if ($payment->marketplace_order_id) {
                $order = \App\Models\MarketplaceOrder::find($payment->marketplace_order_id);
                if ($order) {
                    $order->update([
                        'status' => 'confirmed',
                        'payment_method' => 'tap',
                    ]);
                }
                return true;
            }

            // Hire Provider
            return $this->hireProviderService->hireProvider($payment);
        }

        if (in_array($status, ['FAILED', 'DECLINED', 'CANCELLED', 'EXPIRED', 'RESTRICTED'])) {
            $payment->update([
                'status' => 'failed',
                'tap_charge_id' => $chargeId,
                'gateway_response' => $chargeData,
            ]);

            return false;
        }

        // Status is still processing or initiated
        $payment->update([
            'status' => 'processing',
            'tap_charge_id' => $chargeId,
            'gateway_response' => $chargeData,
        ]);

        return false;
    }

    /**
     * Idempotently handles incoming Tap webhook notifications.
     *
     * @param array $payload
     * @return bool
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

        // Idempotency check: If already captured, log & return true
        if ($payment->status === 'captured') {
            Log::info("TapPaymentService: Payment #{$payment->id} is already captured. Webhook execution skipped.");
            return true;
        }

        // Always retrieve fresh charge details directly from Tap API for security verification
        return $this->verifyCharge($chargeId, $payment);
    }
}
