<?php

namespace App\Http\Controllers\Api\Marketplace;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProcessChargeRequest;
use App\Models\MarketplaceOrder;
use App\Models\Payment;
use App\Services\Payment\TapPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MarketplacePaymentController extends Controller
{
    protected TapPaymentService $tapPaymentService;

    public function __construct(TapPaymentService $tapPaymentService)
    {
        $this->tapPaymentService = $tapPaymentService;
    }

    /**
     * Step 1: Initiate Payment for a Marketplace Order
     */
    public function initiatePayment(Request $request, $orderId)
    {
        try {
            $user = auth('sanctum')->user();
            if (!$user) {
                return response()->json(['status' => 401, 'message' => 'Unauthorized.'], 401);
            }

            $order = MarketplaceOrder::where('id', $orderId)
                ->where('user_id', $user->id)
                ->first();

            if (!$order) {
                return response()->json(['status' => 404, 'message' => 'Marketplace Order not found.'], 404);
            }

            if (in_array(strtolower($order->status), ['completed', 'delivered', 'cancelled'])) {
                return response()->json(['status' => 400, 'message' => 'This order is no longer available for payment.'], 400);
            }

            // Find or Create Payment Session
            $payment = Payment::firstOrCreate(
                [
                    'marketplace_order_id' => $order->id,
                    'status' => 'pending',
                ],
                [
                    'user_id' => $user->id,
                    'job_id' => null,
                    'bid_id' => null,
                    'provider_id' => null,
                    'amount' => (float) $order->total_amount,
                    'currency' => 'SAR',
                    'gateway' => 'tap',
                ]
            );

            return response()->json([
                'status' => 200,
                'message' => 'Marketplace Payment session initiated.',
                'data' => [
                    'payment_id' => $payment->id,
                    'marketplace_order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'amount' => (float) $payment->amount,
                    'currency' => $payment->currency,
                    'status' => $payment->status,
                ]
            ]);
        } catch (\Throwable $e) {
            Log::error('MarketplacePaymentController: Error initiating payment - ' . $e->getMessage());
            return response()->json(['status' => 500, 'message' => 'Failed to initiate payment.'], 500);
        }
    }

    /**
     * Step 2: Charge Marketplace Payment via Tap Checkout
     */
    public function charge(ProcessChargeRequest $request)
    {
        try {
            $user = auth('sanctum')->user();
            $paymentId = $request->input('payment_id');
            $token = $request->input('token', 'src_all') ?: 'src_all';

            $payment = Payment::where('id', $paymentId)
                ->where('user_id', $user->id)
                ->first();

            if (!$payment || !$payment->marketplace_order_id) {
                return response()->json(['status' => 404, 'message' => 'Payment record not found.'], 404);
            }

            if ($payment->status === 'captured') {
                return response()->json(['status' => 400, 'message' => 'This payment is already captured.'], 400);
            }

            $order = MarketplaceOrder::find($payment->marketplace_order_id);
            if (!$order) {
                return response()->json(['status' => 404, 'message' => 'Associated marketplace order not found.'], 404);
            }

            $payment->update(['status' => 'processing']);

            // Call Tap Payment Service for Marketplace Charge
            $chargeResponse = $this->tapPaymentService->createMarketplaceCharge($payment, $order, $token);

            $tapChargeId = $chargeResponse['id'] ?? null;
            $chargeStatus = strtoupper($chargeResponse['status'] ?? 'PENDING');
            $redirectUrl = $chargeResponse['transaction']['url'] ?? null;

            if ($tapChargeId) {
                $payment->update([
                    'tap_charge_id' => $tapChargeId,
                    'gateway_response' => $chargeResponse,
                ]);
            }

            if (!empty($redirectUrl) && $chargeStatus !== 'CAPTURED') {
                return response()->json([
                    'status' => 200,
                    'message' => '3DS Authentication required. Please complete authentication via the redirect URL.',
                    'data' => [
                        'payment_id' => $payment->id,
                        'marketplace_order_id' => $order->id,
                        'status' => strtolower($chargeStatus),
                        'tap_charge_id' => $tapChargeId,
                        'redirect_url' => $redirectUrl,
                    ]
                ]);
            }

            if ($chargeStatus === 'CAPTURED') {
                $this->tapPaymentService->verifyCharge($tapChargeId, $payment);

                return response()->json([
                    'status' => 200,
                    'message' => 'Payment processed successfully.',
                    'data' => [
                        'payment_id' => $payment->id,
                        'marketplace_order_id' => $order->id,
                        'status' => 'captured',
                        'tap_charge_id' => $tapChargeId,
                        'redirect_url' => null,
                    ]
                ]);
            }

            return response()->json([
                'status' => 400,
                'message' => $chargeResponse['response']['message'] ?? 'Payment failed or declined.',
                'data' => $chargeResponse
            ], 400);

        } catch (\Throwable $e) {
            Log::error('MarketplacePaymentController: Error processing charge - ' . $e->getMessage());
            return response()->json(['status' => 500, 'message' => 'Payment processing failed.'], 500);
        }
    }
}
