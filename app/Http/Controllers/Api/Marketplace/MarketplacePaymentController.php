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

            $settings = \App\Models\Admin\SystemSettingModel::first();
            $marketplaceVatPct = (float) ($settings->marketplace_vat_percentage ?? 15.00);
            $customerAppFee = 0.0; // Reverted: Customer App Fee is NOT charged on Marketplace
            $gatewayFeePct = (float) ($settings->payment_gateway_fee_percentage ?? 2.50);
            $gatewayFixedFee = (float) ($settings->payment_gateway_fixed_fee ?? 1.00);
            $gatewayVatPct = (float) ($settings->payment_gateway_vat_percentage ?? 15.00);

            $productsSubtotal = 0.0;
            $totalProductVat = 0.0;

            foreach ($cartItems as $ci) {
                $price = 0.0;
                if ($ci->product) {
                    $price = (float) ($ci->product->sale_price ?: $ci->product->price);
                }
                $qty = (int) ($ci->quantity ?? 1);
                $sub = $price * $qty;
                $vat = $sub * ($marketplaceVatPct / 100);
                $productsSubtotal += $sub;
                $totalProductVat += $vat;
            }

            $productsTotalWithVat = $productsSubtotal + $totalProductVat;
            $shippingCost = (float) ($request->input('shipping_cost') ?? 0.0);
            $appFeeToApply = 0.0;

            $baseSubtotal = $productsSubtotal;
            $gatewaySubtotal = ($baseSubtotal * ($gatewayFeePct / 100)) + $gatewayFixedFee;
            $gatewayVat = $gatewaySubtotal * ($gatewayVatPct / 100);
            $totalGatewayFee = $gatewaySubtotal + $gatewayVat;

            $totalAmount = max(0.1, round($productsTotalWithVat + $shippingCost + $totalGatewayFee, 2));

            $breakdown = [
                'products_subtotal' => number_format($productsSubtotal, 2, '.', ''),
                'marketplace_vat_percentage' => number_format($marketplaceVatPct, 2, '.', ''),
                'total_product_vat' => number_format($totalProductVat, 2, '.', ''),
                'products_total_with_vat' => number_format($productsTotalWithVat, 2, '.', ''),
                'customer_app_fee' => '0.00',
                'subtotal' => number_format($baseSubtotal, 2, '.', ''),
                'shipping_cost' => number_format($shippingCost, 2, '.', ''),
                'gateway_fee_percentage' => number_format($gatewayFeePct, 2, '.', ''),
                'gateway_fixed_fee' => number_format($gatewayFixedFee, 2, '.', ''),
                'fixed_transaction_fee' => number_format($gatewayFixedFee, 2, '.', ''),
                'payment_gateway_fixed_fee' => number_format($gatewayFixedFee, 2, '.', ''),
                'gateway_fee_subtotal' => number_format($gatewaySubtotal, 2, '.', ''),
                'gateway_vat_percentage' => number_format($gatewayVatPct, 2, '.', ''),
                'gateway_vat' => number_format($gatewayVat, 2, '.', ''),
                'total_gateway_fee' => number_format($totalGatewayFee, 2, '.', ''),
                'total_payable_by_customer' => number_format($totalAmount, 2, '.', ''),
                'grand_total' => number_format($totalAmount, 2, '.', ''),
                'total_amount' => number_format($totalAmount, 2, '.', ''),
                'currency' => strtoupper(optional($settings)->currency ?? 'SAR'),
            ];

            $checkoutMetadata = array_merge([
                'shipping_address' => $request->input('shipping_address') ?: $user->address,
                'shipping_cost' => $shippingCost,
                'tax_amount' => $totalProductVat,
                'subtotal' => $productsSubtotal,
                'notes' => $request->input('notes'),
                'cart_items_count' => $cartItems->count(),
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

            $order = $payment->marketplace_order_id ? MarketplaceOrder::with('items')->find($payment->marketplace_order_id) : null;

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
}
