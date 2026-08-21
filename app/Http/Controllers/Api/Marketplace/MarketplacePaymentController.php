<?php

namespace App\Http\Controllers\Api\Marketplace;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProcessChargeRequest;
use App\Models\Cart;
use App\Models\MarketplaceOrder;
use App\Models\Payment;
use App\Models\Product;
use App\Services\Payment\TapPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class MarketplacePaymentController extends Controller
{
    protected TapPaymentService $tapPaymentService;

    public function __construct(TapPaymentService $tapPaymentService)
    {
        $this->tapPaymentService = $tapPaymentService;
    }

    /**
     * Step 1: Initiate Payment from Cart before Order Creation
     * POST /api/v1/marketplace/cart/initiate-payment
     * POST /api/v1/marketplace/checkout/initiate-payment
     */
    public function initiateCartPayment(Request $request)
    {
        try {
            $user = auth('sanctum')->user();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            }

            $validator = Validator::make($request->all(), [
                'shipping_address' => 'nullable|string',
                'shipping_cost' => 'nullable|numeric|min:0',
                'tax_amount' => 'nullable|numeric|min:0',
                'notes' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
            }

            $cartItems = Cart::with('product')
                ->where('user_id', $user->id)
                ->get();

            if ($cartItems->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'Cart is empty.'], 400);
            }

            $invalidCartItems = $cartItems->filter(fn($ci) => !$ci->product);
            if ($invalidCartItems->isNotEmpty()) {
                Cart::whereIn('id', $invalidCartItems->pluck('id'))->delete();
                return response()->json(['success' => false, 'message' => 'Some cart items were invalid and have been removed. Please review your cart.'], 422);
            }

            $subtotal = (float) $cartItems->sum('total_price');
            $shippingCost = (float) ($request->input('shipping_cost') ?? 0);
            $taxAmount = (float) ($request->input('tax_amount') ?? 0);
            $totalAmount = max(0.1, round($subtotal + $shippingCost + $taxAmount, 2));

            $checkoutMetadata = [
                'shipping_address' => $request->input('shipping_address') ?: $user->address,
                'shipping_cost' => $shippingCost,
                'tax_amount' => $taxAmount,
                'subtotal' => $subtotal,
                'notes' => $request->input('notes'),
                'cart_items_count' => $cartItems->count(),
            ];

            // Create Payment session for Cart Checkout
            $payment = Payment::create([
                'user_id' => $user->id,
                'marketplace_order_id' => null, // Order created ONLY after payment capture
                'job_id' => null,
                'bid_id' => null,
                'provider_id' => null,
                'amount' => $totalAmount,
                'currency' => 'SAR',
                'gateway' => 'tap',
                'status' => 'pending',
                'gateway_response' => [
                    'checkout_metadata' => $checkoutMetadata
                ]
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Marketplace Cart payment session initiated successfully.',
                'data' => [
                    'payment_id' => (int) $payment->id,
                    'amount' => (float) $payment->amount,
                    'currency' => $payment->currency,
                    'subtotal' => $subtotal,
                    'shipping_cost' => $shippingCost,
                    'tax_amount' => $taxAmount,
                    'status' => $payment->status,
                    'cart_items_count' => $cartItems->count(),
                ]
            ], 200);

        } catch (\Throwable $e) {
            Log::error('MarketplacePaymentController: Error initiating cart payment - ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to initiate cart payment.'], 500);
        }
    }

    /**
     * Step 1 (Alternative): Initiate Payment for an existing Marketplace Order
     * POST /api/v1/marketplace/orders/{orderId}/initiate-payment
     */
    public function initiatePayment(Request $request, $orderId)
    {
        try {
            $user = auth('sanctum')->user();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            }

            $order = MarketplaceOrder::where('id', $orderId)
                ->where('user_id', $user->id)
                ->first();

            if (!$order) {
                return response()->json(['success' => false, 'message' => 'Marketplace Order not found.'], 404);
            }

            if (in_array(strtolower($order->status), ['completed', 'delivered', 'cancelled'])) {
                return response()->json(['success' => false, 'message' => 'This order is no longer available for payment.'], 400);
            }

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
                'success' => true,
                'message' => 'Marketplace Order payment session initiated.',
                'data' => [
                    'payment_id' => (int) $payment->id,
                    'marketplace_order_id' => (int) $order->id,
                    'order_number' => $order->order_number,
                    'amount' => (float) $payment->amount,
                    'currency' => $payment->currency,
                    'status' => $payment->status,
                ]
            ]);
        } catch (\Throwable $e) {
            Log::error('MarketplacePaymentController: Error initiating payment - ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to initiate payment.'], 500);
        }
    }

    /**
     * Step 2: Charge Marketplace Payment (Works WITH or WITHOUT token!)
     * POST /api/v1/marketplace/payments/charge
     */
    public function charge(ProcessChargeRequest $request)
    {
        try {
            $user = auth('sanctum')->user();
            $paymentId = $request->input('payment_id');

            // If token is missing, default to 'src_all' (Tap Checkout Webview with Mada/Visa/Mastercard/ApplePay)
            $token = $request->input('token') ?: 'src_all';

            $payment = Payment::where('id', $paymentId)
                ->where('user_id', $user->id)
                ->first();

            if (!$payment) {
                return response()->json(['success' => false, 'message' => 'Payment record not found.'], 404);
            }

            if ($payment->status === 'captured') {
                $order = $payment->marketplace_order_id ? MarketplaceOrder::find($payment->marketplace_order_id) : null;
                return response()->json([
                    'success' => true,
                    'message' => 'Payment is already captured and order created.',
                    'data' => [
                        'payment_id' => (int) $payment->id,
                        'marketplace_order_id' => $order ? (int) $order->id : null,
                        'order_number' => $order ? $order->order_number : null,
                        'status' => 'captured',
                        'tap_charge_id' => $payment->tap_charge_id,
                        'redirect_url' => null,
                    ]
                ], 200);
            }

            $order = $payment->marketplace_order_id ? MarketplaceOrder::find($payment->marketplace_order_id) : null;

            $payment->update(['status' => 'processing']);

            // Dispatch Tap Charge Request
            $chargeResponse = $this->tapPaymentService->createMarketplaceCharge($payment, $order, $token);

            $tapChargeId = $chargeResponse['id'] ?? null;
            $chargeStatus = strtoupper($chargeResponse['status'] ?? 'PENDING');
            $redirectUrl = $chargeResponse['transaction']['url'] ?? null;

            if ($tapChargeId) {
                $payment->update([
                    'tap_charge_id' => $tapChargeId,
                    'gateway_response' => array_merge($payment->gateway_response ?? [], ['charge' => $chargeResponse]),
                ]);
            }

            // 3DS Authentication / Tap Checkout Webview Required
            if (!empty($redirectUrl) && $chargeStatus !== 'CAPTURED') {
                return response()->json([
                    'success' => true,
                    'message' => '3DS Authentication required. Please complete authentication via the redirect URL.',
                    'data' => [
                        'payment_id' => (int) $payment->id,
                        'marketplace_order_id' => $order ? (int) $order->id : null,
                        'status' => strtolower($chargeStatus),
                        'tap_charge_id' => $tapChargeId,
                        'redirect_url' => $redirectUrl,
                    ]
                ], 200);
            }

            // If Payment is CAPTURED: Create Order from Cart & Clear Cart!
            if ($chargeStatus === 'CAPTURED') {
                $payment->update([
                    'status' => 'captured',
                    'tap_charge_id' => $tapChargeId,
                ]);

                if (!$payment->marketplace_order_id) {
                    $order = $this->tapPaymentService->convertCartToMarketplaceOrder($payment);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Payment processed successfully and Marketplace Order created.',
                    'data' => [
                        'payment_id' => (int) $payment->id,
                        'marketplace_order_id' => $order ? (int) $order->id : null,
                        'order_number' => $order ? $order->order_number : null,
                        'subtotal' => $order ? (float) $order->subtotal : (float) $payment->amount,
                        'total_amount' => $order ? (float) $order->total_amount : (float) $payment->amount,
                        'status' => 'captured',
                        'tap_charge_id' => $tapChargeId,
                        'redirect_url' => null,
                        'order' => $order ? $order->load('items') : null,
                    ]
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => $chargeResponse['response']['message'] ?? 'Payment failed or declined.',
                'data' => $chargeResponse
            ], 400);

        } catch (\Throwable $e) {
            Log::error('MarketplacePaymentController: Error processing charge - ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Payment processing failed: ' . $e->getMessage()], 500);
        }
    }
}
