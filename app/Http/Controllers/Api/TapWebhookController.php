<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payment\TapPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TapWebhookController extends Controller
{
    protected TapPaymentService $tapPaymentService;

    public function __construct(TapPaymentService $tapPaymentService)
    {
        $this->tapPaymentService = $tapPaymentService;
    }

    /**
     * API #4: Handle Tap Payments Webhook
     * POST /api/webhooks/tap
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        try {
            $payload = $request->all();
            Log::info('TapWebhookController: Webhook received', ['payload' => $payload]);

            if (empty($payload)) {
                return response()->json(['status' => false, 'message' => 'Empty payload'], 400);
            }

            $processed = $this->tapPaymentService->handleWebhook($payload);

            return response()->json([
                'status' => true,
                'message' => $processed ? 'Webhook processed successfully' : 'Webhook received but not actionable',
            ], 200);

        } catch (\Throwable $e) {
            Log::error('TapWebhookController: Webhook processing error - ' . $e->getMessage(), [
                'exception' => $e
            ]);
            // Return HTTP 200 so Tap doesn't infinitely retry failed internal scripts
            return response()->json([
                'status' => false,
                'message' => 'Webhook received with processing note: ' . $e->getMessage(),
            ], 200);
        }
    }

    /**
     * API #5: Handle 3DS Redirect Return URL
     * GET /tap/redirect
     *
     * @param Request $request
     * @return mixed
     */
    public function handleRedirect(Request $request)
    {
        try {
            $tapId = $request->query('tap_id') ?: $request->query('charge_id');
            Log::info("TapWebhookController: Redirect received for charge {$tapId}", $request->all());

            if (!$tapId) {
                return response()->html("
                    <html>
                    <body style='font-family:sans-serif; text-align:center; padding-top:50px;'>
                        <h2 style='color:#e74c3c;'>Invalid Redirect Request</h2>
                        <p>No Tap transaction reference was provided.</p>
                    </body>
                    </html>
                ");
            }

            $payment = Payment::where('tap_charge_id', $tapId)->first();

            if (!$payment) {
                try {
                    $chargeData = $this->tapPaymentService->retrieveCharge($tapId);
                    $metaPaymentId = $chargeData['metadata']['payment_id'] ?? null;
                    if ($metaPaymentId) {
                        $payment = Payment::find($metaPaymentId);
                    }
                } catch (\Throwable $e) {
                    Log::warning("TapWebhookController: Unable to fetch charge {$tapId} during redirect fallback: " . $e->getMessage());
                }
            }

            if ($payment) {
                try {
                    $this->tapPaymentService->verifyCharge($tapId, $payment);
                    $payment->refresh();
                } catch (\Throwable $e) {
                    Log::warning("TapWebhookController: verifyCharge exception during redirect: " . $e->getMessage());
                    $payment->refresh();
                }
            }

            $isSuccess = $payment && in_array(strtolower($payment->status), ['captured', 'paid']);
            $statusText = $isSuccess ? 'Payment Successful!' : 'Payment Pending or Failed';
            $bgColor = $isSuccess ? '#27ae60' : '#e74c3c';
            $subText = $isSuccess 
                ? 'Your payment was processed successfully and the service provider has been hired.' 
                : 'Payment verification failed or was declined. You can close this screen and return to the Azhl app to try again.';

            return response()->make("
                <!DOCTYPE html>
                <html>
                <head>
                    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                    <title>Azhl Payment Result</title>
                    <style>
                        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background: #f8f9fa; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
                        .card { background: #ffffff; padding: 40px; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); text-align: center; max-width: 400px; width: 90%; }
                        .icon { font-size: 50px; color: {$bgColor}; margin-bottom: 20px; }
                        h2 { color: #2c3e50; margin-bottom: 10px; font-size: 24px; }
                        p { color: #7f8c8d; font-size: 15px; line-height: 1.5; margin-bottom: 30px; }
                        .btn { background: #4F2396; color: #ffffff; text-decoration: none; padding: 12px 28px; border-radius: 30px; font-weight: bold; display: inline-block; }
                    </style>
                </head>
                <body>
                    <div class='card'>
                        <div class='icon'>" . ($isSuccess ? '✓' : '✕') . "</div>
                        <h2>{$statusText}</h2>
                        <p>{$subText}</p>
                        <a href='javascript:void(0);' onclick='window.close();' class='btn'>Return to App</a>
                    </div>
                </body>
                </html>
            ", 200, ['Content-Type' => 'text/html']);

        } catch (\Throwable $e) {
            Log::error('TapWebhookController: Redirect handling error - ' . $e->getMessage());
            return response()->make("
                <html>
                <body style='font-family:sans-serif; text-align:center; padding-top:50px;'>
                    <h2 style='color:#e74c3c;'>Error Processing Redirect</h2>
                    <p>An unexpected error occurred while verifying payment status.</p>
                </body>
                </html>
            ", 500, ['Content-Type' => 'text/html']);
        }
    }
}
