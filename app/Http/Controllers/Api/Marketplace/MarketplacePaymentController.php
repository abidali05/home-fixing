<?php

namespace App\Http\Controllers\Api\Marketplace;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProcessChargeRequest;
use App\Models\Cart;
use App\Models\MarketplaceOrder;
use App\Models\Payment;
use App\Services\Payment\TapPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
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

            $settings = \App\Models\Admin\SystemSettingModel::first();
            $marketplaceCustomerAppFee = (float) ($settings->marketplace_customer_app_fee ?? $settings->customer_app_fee ?? 3.00);

            $productsSubtotal = 0.0;
            $cartItemsData = [];

            foreach ($cartItems as $ci) {
                $price = 0.0;
                if ($ci->product) {
                    $price = (float) ($ci->product->sale_price ?: $ci->product->price);
                } else {
                    $price = (float) ($ci->base_price ?? 0.0);
                }
                $qty = (int) ($ci->quantity ?? 1);
                $sub = $price * $qty;
                $productsSubtotal += $sub;

                $cartItemsData[] = [
                    'product_id' => (int) $ci->product_id,
                    'shop_id' => optional($ci->product)->user_id,
                    'product_name' => optional($ci->product)->product_name ?? ('Product #' . $ci->product_id),
                    'quantity' => $qty,
                    'base_price' => $price,
                    'total_price' => $sub,
                ];
            }

            $shippingCost = (float) ($request->input('shipping_cost') ?? 0.0);
            $customerAppFee = $cartItems->isNotEmpty() && $productsSubtotal > 0 ? $marketplaceCustomerAppFee : 0.0;

            // Customer pays products_subtotal + customer_app_fee (plus shipping if any), without percentage VAT
            $totalAmount = max(0.1, round($productsSubtotal + $shippingCost + $customerAppFee, 2));

            $breakdown = [
                'products_subtotal' => number_format($productsSubtotal, 2, '.', ''),
                'marketplace_vat_percentage' => '0.00',
                'total_product_vat' => '0.00',
                'products_total_with_vat' => number_format($productsSubtotal, 2, '.', ''),
                'customer_app_fee' => number_format($customerAppFee, 2, '.', ''),
                'marketplace_customer_app_fee' => number_format($customerAppFee, 2, '.', ''),
                'subtotal' => number_format($productsSubtotal, 2, '.', ''),
                'shipping_cost' => number_format($shippingCost, 2, '.', ''),
                'total_payable_by_customer' => number_format($totalAmount, 2, '.', ''),
                'grand_total' => number_format($totalAmount, 2, '.', ''),
                'total_amount' => number_format($totalAmount, 2, '.', ''),
                'total' => number_format($totalAmount, 2, '.', ''),
                'currency' => strtoupper(optional($settings)->currency ?? 'SAR'),
            ];

            $checkoutMetadata = array_merge([
                'shipping_address' => $request->input('shipping_address') ?: $user->address,
                'shipping_cost' => $shippingCost,
                'tax_amount' => 0.00,
                'customer_app_fee' => $customerAppFee,
                'marketplace_customer_app_fee' => $customerAppFee,
                'subtotal' => $productsSubtotal,
                'notes' => $request->input('notes'),
                'cart_items_count' => $cartItems->count(),
                'cart_items' => $cartItemsData,
            ], $breakdown);

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
                'data' => array_merge([
                    'payment_id' => (int) $payment->id,
                    'amount' => number_format((float) $payment->amount, 2, '.', ''),
                    'currency' => $payment->currency,
                    'status' => $payment->status,
                    'cart_items_count' => $cartItems->count(),
                    'breakdown' => $breakdown,
                    'payment_breakdown' => $breakdown,
                    'summary' => $breakdown,
                ], $breakdown)
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
                if (!$order && !$payment->job_id) {
                    $order = $this->tapPaymentService->convertCartToMarketplaceOrder($payment);
                }
                return response()->json([
                    'success' => true,
                    'message' => 'Payment is already captured and order created.',
                    'data' => [
                        'payment_id' => (int) $payment->id,
                        'marketplace_order_id' => $order ? (int) $order->id : null,
                        'order_number' => $order ? $order->order_number : null,
                        'subtotal' => $order ? (float) $order->subtotal : (float) $payment->amount,
                        'total_amount' => $order ? (float) $order->total_amount : (float) $payment->amount,
                        'status' => 'captured',
                        'tap_charge_id' => $payment->tap_charge_id,
                        'redirect_url' => null,
                        'order' => $order ? $order->load('items') : null,
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
                if (!$payment->marketplace_order_id && !$payment->job_id) {
                    $order = $this->tapPaymentService->convertCartToMarketplaceOrder($payment);
                } else if ($payment->marketplace_order_id) {
                    $order = MarketplaceOrder::find($payment->marketplace_order_id);
                }

                $payment->update([
                    'status' => 'captured',
                    'tap_charge_id' => $tapChargeId,
                    'marketplace_order_id' => $order ? (int) $order->id : $payment->marketplace_order_id,
                ]);

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

    /**
     * Check Marketplace Payment Status & Fetch Created Order after Webview Payment Completion
     * GET /api/v1/marketplace/payments/{paymentId}/status
     */
    public function status(Request $request, $paymentId)
    {
        try {
            $user = auth('sanctum')->user();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            }

            $payment = Payment::where('id', $paymentId)
                ->where('user_id', $user->id)
                ->first();

            if (!$payment) {
                return response()->json(['success' => false, 'message' => 'Payment record not found.'], 404);
            }

            // Verify live status from Tap Payments if not yet captured
            if ($payment->tap_charge_id && $payment->status !== 'captured') {
                try {
                    $this->tapPaymentService->verifyCharge($payment->tap_charge_id, $payment);
                    $payment->refresh();
                } catch (\Throwable $e) {
                    Log::warning("MarketplacePaymentController status check exception: " . $e->getMessage());
                }
            }

            if ($payment->status === 'captured' && !$payment->marketplace_order_id && !$payment->job_id) {
                $order = $this->tapPaymentService->convertCartToMarketplaceOrder($payment);
            } else {
                $order = $payment->marketplace_order_id ? MarketplaceOrder::with('items')->find($payment->marketplace_order_id) : null;
            }

            return response()->json([
                'success' => true,
                'message' => 'Marketplace payment status retrieved successfully.',
                'data' => [
                    'payment_id' => (int) $payment->id,
                    'status' => strtolower($payment->status),
                    'amount' => (float) $payment->amount,
                    'currency' => $payment->currency ?: 'SAR',
                    'tap_charge_id' => $payment->tap_charge_id,
                    'marketplace_order_id' => $order ? (int) $order->id : null,
                    'order_number' => $order ? $order->order_number : null,
                    'order' => $order,
                ]
            ], 200);

        } catch (\Throwable $e) {
            Log::error('MarketplacePaymentController: Error checking payment status - ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to check payment status.'], 500);
        }
    }

    /**
     * Verify Tap Native SDK Payment for Marketplace Checkout
     * POST /api/v1/marketplace/checkout/verify-tap-payment
     * POST /api/v1/marketplace/payments/verify-tap-payment
     * POST /api/v1/marketplace/orders/{order}/verify-tap-payment
     *
     * @param Request $request
     * @param int|string|null $orderId
     * @return JsonResponse
     */
    public function verifyTapPayment(Request $request, $orderId = null): JsonResponse
    {
        try {
            $user = auth('sanctum')->user();
            if (!$user) {
                return response()->json([
                    'status' => 401,
                    'success' => false,
                    'message' => 'You must be logged in to verify payment.'
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'charge_id' => 'required|string',
                'payment_id' => 'nullable|integer',
                'order_id' => 'nullable',
                'amount' => 'nullable|numeric',
                'currency' => 'nullable|string',
                'payment_type' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 422,
                    'success' => false,
                    'message' => $validator->errors()->first(),
                    'errors' => $validator->errors(),
                ], 422);
            }

            $chargeId = trim((string) $request->input('charge_id'));
            $resolvedOrderId = $orderId ?: $request->input('order_id');

            // 1. If verifying an existing marketplace order
            if ($resolvedOrderId) {
                $order = MarketplaceOrder::where('id', $resolvedOrderId)
                    ->where('user_id', $user->id)
                    ->first();

                if (!$order) {
                    return response()->json([
                        'status' => 404,
                        'success' => false,
                        'message' => 'Marketplace order not found.'
                    ], 404);
                }

                // Idempotency check: already paid
                if (strtolower((string) $order->payment_status) === 'paid') {
                    return response()->json([
                        'status' => 200,
                        'success' => true,
                        'message' => 'Marketplace order is already paid.',
                        'data' => [
                            'marketplace_order_id' => (int) $order->id,
                            'order_number' => $order->order_number,
                            'status' => 'captured',
                            'payment_status' => 'paid',
                            'payment_method' => $order->payment_method ?: 'card',
                            'transaction_id' => $order->transaction_id ?? $chargeId,
                            'amount' => (float) $order->total_amount,
                            'currency' => 'SAR',
                            'order' => $order->load('items'),
                        ]
                    ], 200);
                }
            }

            // 2. Idempotency check: charge already processed in Payment records
            $existingPayment = Payment::where('tap_charge_id', $chargeId)
                ->where('status', 'captured')
                ->first();

            if ($existingPayment) {
                $order = $existingPayment->marketplace_order_id
                    ? MarketplaceOrder::with('items')->find($existingPayment->marketplace_order_id)
                    : null;

                return response()->json([
                    'status' => 200,
                    'success' => true,
                    'message' => 'Payment has already been verified and order confirmed.',
                    'data' => [
                        'payment_id' => (int) $existingPayment->id,
                        'marketplace_order_id' => $order ? (int) $order->id : null,
                        'order_number' => $order?->order_number,
                        'status' => 'captured',
                        'payment_status' => 'paid',
                        'payment_method' => $order?->payment_method ?: 'card',
                        'transaction_id' => $chargeId,
                        'amount' => (float) ($order ? $order->total_amount : $existingPayment->amount),
                        'currency' => 'SAR',
                        'order' => $order,
                    ]
                ], 200);
            }

            // 3. Server-to-server verification with Tap API
            try {
                $chargeData = $this->tapPaymentService->retrieveCharge($chargeId);
            } catch (\Exception $e) {
                Log::error("MarketplacePaymentController: Failed to retrieve charge {$chargeId}: " . $e->getMessage());
                return response()->json([
                    'status' => 400,
                    'success' => false,
                    'message' => 'Unable to verify payment with payment gateway: ' . $e->getMessage()
                ], 400);
            }

            $remoteStatus = strtoupper((string) ($chargeData['status'] ?? ''));
            if ($remoteStatus !== 'CAPTURED') {
                return response()->json([
                    'status' => 400,
                    'success' => false,
                    'message' => "Payment is not captured. Gateway status: {$remoteStatus}",
                    'gateway_status' => $remoteStatus
                ], 400);
            }

            $chargeCurrency = strtoupper((string) ($chargeData['currency'] ?? ''));
            if ($chargeCurrency !== 'SAR') {
                return response()->json([
                    'status' => 400,
                    'success' => false,
                    'message' => "Invalid payment currency '{$chargeCurrency}'. Expected SAR."
                ], 400);
            }

            $chargeAmount = (float) ($chargeData['amount'] ?? 0.0);

            // 4. Resolve Payment record
            $paymentId = $request->input('payment_id') ?: ($chargeData['metadata']['payment_id'] ?? null);
            $payment = null;

            if ($paymentId) {
                $payment = Payment::where('id', $paymentId)->where('user_id', $user->id)->first();
            }

            if (!$payment && !empty($resolvedOrderId)) {
                $payment = Payment::where('marketplace_order_id', $resolvedOrderId)->latest()->first();
            }

            if (!$payment) {
                $payment = Payment::where('user_id', $user->id)
                    ->where('status', 'pending')
                    ->whereNull('job_id')
                    ->latest()
                    ->first();
            }

            // 5. Finalize Order & Payment inside DB transaction
            $order = DB::transaction(function () use ($user, $payment, $chargeId, $chargeData, $chargeAmount, $resolvedOrderId) {
                if ($resolvedOrderId) {
                    $order = MarketplaceOrder::where('id', $resolvedOrderId)->where('user_id', $user->id)->first();
                } else {
                    $order = null;
                }

                if ($order) {
                    $order->payment_status = 'paid';
                    $order->payment_method = 'card';
                    if (Schema::hasColumn('marketplace_orders', 'transaction_id')) {
                        $order->transaction_id = $chargeId;
                    }
                    if (Schema::hasColumn('marketplace_orders', 'paid_at') && empty($order->paid_at)) {
                        $order->paid_at = now();
                    }
                    if (in_array(strtolower((string) $order->status), ['pending', 'new', 'waiting'])) {
                        $order->status = 'accept';
                    }
                    $order->save();

                    if ($payment) {
                        $payment->update([
                            'status' => 'captured',
                            'tap_charge_id' => $chargeId,
                            'marketplace_order_id' => $order->id,
                            'gateway_response' => array_merge(is_array($payment->gateway_response) ? $payment->gateway_response : [], ['charge' => $chargeData]),
                        ]);
                    } else {
                        Payment::create([
                            'user_id' => $user->id,
                            'marketplace_order_id' => $order->id,
                            'amount' => (float) $order->total_amount,
                            'currency' => 'SAR',
                            'gateway' => 'tap',
                            'tap_charge_id' => $chargeId,
                            'status' => 'captured',
                            'gateway_response' => ['charge' => $chargeData],
                        ]);
                    }
                } else {
                    // Cart Checkout: Convert Cart to Marketplace Order
                    if (!$payment) {
                        $payment = Payment::create([
                            'user_id' => $user->id,
                            'amount' => $chargeAmount,
                            'currency' => 'SAR',
                            'gateway' => 'tap',
                            'tap_charge_id' => $chargeId,
                            'status' => 'processing',
                        ]);
                    } else {
                        $payment->update([
                            'tap_charge_id' => $chargeId,
                            'status' => 'processing',
                        ]);
                    }

                    $order = $this->tapPaymentService->convertCartToMarketplaceOrder($payment);

                    if ($order) {
                        $order->payment_status = 'paid';
                        $order->payment_method = 'card';
                        if (Schema::hasColumn('marketplace_orders', 'transaction_id')) {
                            $order->transaction_id = $chargeId;
                        }
                        if (Schema::hasColumn('marketplace_orders', 'paid_at') && empty($order->paid_at)) {
                            $order->paid_at = now();
                        }
                        $order->save();
                    }

                    $payment->update([
                        'status' => 'captured',
                        'tap_charge_id' => $chargeId,
                        'marketplace_order_id' => $order?->id,
                        'gateway_response' => array_merge(is_array($payment->gateway_response) ? $payment->gateway_response : [], ['charge' => $chargeData]),
                    ]);
                }

                return $order;
            });

            return response()->json([
                'status' => 200,
                'success' => true,
                'message' => 'Payment verified successfully and Marketplace Order confirmed.',
                'data' => [
                    'payment_id' => $payment ? (int) $payment->id : null,
                    'marketplace_order_id' => $order ? (int) $order->id : null,
                    'order_number' => $order?->order_number,
                    'status' => 'captured',
                    'payment_status' => 'paid',
                    'payment_method' => 'card',
                    'amount' => (float) ($order ? $order->total_amount : $chargeAmount),
                    'currency' => 'SAR',
                    'transaction_id' => $chargeId,
                    'tap_charge_id' => $chargeId,
                    'order' => $order ? $order->load('items') : null,
                ]
            ], 200);

        } catch (\Throwable $e) {
            Log::error('MarketplacePaymentController: Error in verifyTapPayment - ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'status' => 500,
                'success' => false,
                'message' => 'Internal server error while verifying payment: ' . $e->getMessage()
            ], 500);
        }
    }
}
