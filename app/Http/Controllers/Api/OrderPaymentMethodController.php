<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendFcmTokenNotificationJob;
use App\Models\Orders;
use App\Models\Payment;
use App\Models\ProviderCommissionRepayment;
use App\Models\ProviderDuesLedger;
use App\Models\User;
use App\Services\Payment\CashPaymentService;
use App\Services\Payment\TapPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class OrderPaymentMethodController extends Controller
{
    protected CashPaymentService $cashPaymentService;
    protected TapPaymentService $tapPaymentService;

    public function __construct(CashPaymentService $cashPaymentService, TapPaymentService $tapPaymentService)
    {
        $this->cashPaymentService = $cashPaymentService;
        $this->tapPaymentService = $tapPaymentService;
    }

    /**
     * 5.1 Customer Selects Payment Method (cash or card)
     * POST /api/orders/{id}/payment-method
     */
    public function selectPaymentMethod(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'payment_method' => 'required|in:cash,card',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors(), 'Validation failed.');
        }

        try {
            $user = auth('sanctum')->user();
            if (!$user) {
                return $this->error('Unauthorized.', 401);
            }

            $order = Orders::find($id);
            if (!$order) {
                return $this->notFound('Order not found.');
            }

            $result = $this->cashPaymentService->setPaymentMethod($order, $user, $request->payment_method);

            return $this->success($result, "Payment method updated to {$request->payment_method}.");
        } catch (\Exception $e) {
            $code = $e->getCode();
            $httpStatus = (is_int($code) && $code >= 400 && $code < 600) ? $code : 400;
            return $this->error($e->getMessage(), $httpStatus);
        }
    }

    /**
     * 5.2 Provider Confirms Cash Received
     * POST /api/orders/{id}/confirm-cash
     */
    public function confirmCash(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'collected_amount' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors(), 'Validation failed.');
        }

        try {
            $user = auth('sanctum')->user();
            if (!$user) {
                return $this->error('Unauthorized.', 401);
            }

            $order = Orders::find($id);
            if (!$order) {
                return $this->notFound('Order not found.');
            }

            $collectedAmount = $request->filled('collected_amount') ? (float) $request->collected_amount : null;
            $result = $this->cashPaymentService->confirmCashReceived($order, $user, $collectedAmount);

            return $this->success($result, 'Cash payment confirmed successfully.');
        } catch (\Exception $e) {
            $code = $e->getCode();
            $httpStatus = (is_int($code) && $code >= 400 && $code < 600) ? $code : 400;
            return $this->error($e->getMessage(), $httpStatus);
        }
    }

    /**
     * Get Provider Commission Status
     * GET /api/provider/commission-status
     */
    public function commissionStatus(Request $request): JsonResponse
    {
        try {
            $user = auth('sanctum')->user() ?? auth()->user();
            if (!$user) {
                return $this->error('Unauthorized.', 401);
            }

            $due = (float) ($user->payment_due ?? 0.0);
            $threshold = (float) ($user->max_due_threshold ?? 25.00);
            $isBlocked = ($due >= $threshold) || ((int) ($user->is_due_blocked ?? 0) === 1);

            $unpaidOrdersCount = ProviderDuesLedger::where('provider_id', $user->id)
                ->where('type', 'debit_cash_order')
                ->count();

            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => 'Provider commission status retrieved successfully.',
                'data' => [
                    'outstanding_commission' => (float) number_format($due, 2, '.', ''),
                    'threshold_limit' => (float) number_format($threshold, 2, '.', ''),
                    'is_blocked' => (bool) $isBlocked,
                    'currency' => 'SAR',
                    'unpaid_orders_count' => (int) $unpaidOrdersCount,
                    'payment_methods' => ['tap_card', 'apple_pay', 'bank_transfer'],
                ],
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Provider Initiates Dues Repayment (Card / Mada / Visa)
     * POST /api/provider/initiate-due-payment
     * POST /api/provider/dues/pay
     */
    public function initiateDuePayment(Request $request): JsonResponse
    {
        try {
            $user = auth('sanctum')->user() ?? auth()->user();
            if (!$user) {
                return $this->error('Unauthorized.', 401);
            }

            if ((int) $user->role !== 1) {
                return $this->error('Only providers can pay platform dues.', 403);
            }

            $currentDue = (float) ($user->payment_due ?? 0.0);
            if ($currentDue <= 0) {
                return response()->json([
                    'status' => false,
                    'message' => 'You do not have any pending platform dues to pay.',
                ], 400);
            }

            $amountToPay = $request->filled('amount') ? (float) $request->amount : $currentDue;
            if ($amountToPay <= 0) {
                $amountToPay = $currentDue;
            }
            if ($amountToPay > $currentDue) {
                $amountToPay = $currentDue;
            }

            $refCode = 'AZHL-DUE-' . date('YmdHis') . '-' . $user->id;

            // Create a Payment record for dues clearance
            $payment = Payment::create([
                'user_id' => $user->id,
                'provider_id' => $user->id,
                'amount' => $amountToPay,
                'currency' => 'SAR',
                'gateway' => 'tap',
                'status' => 'pending',
                'gateway_response' => [
                    'type' => 'commission_repayment',
                    'reference_code' => $refCode,
                    'original_due' => $currentDue,
                    'paying_amount' => $amountToPay,
                    'payment_method' => $request->input('payment_method', 'card'),
                ],
            ]);

            // Create Tap Charge
            $chargeResponse = $this->tapPaymentService->createCharge($payment, $request->input('token', 'src_all'));
            $tapChargeId = $chargeResponse['id'] ?? null;
            $redirectUrl = $chargeResponse['transaction']['url'] ?? null;

            if ($tapChargeId) {
                $payment->update([
                    'tap_charge_id' => $tapChargeId,
                    'status' => 'processing',
                ]);
            }

            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => 'Dues payment initiated successfully.',
                'data' => [
                    'charge_id' => $tapChargeId,
                    'payment_url' => $redirectUrl,
                    'redirect_url' => $redirectUrl,
                    'amount' => $amountToPay,
                    'currency' => 'SAR',
                    'payment_id' => (string) $payment->id,
                    'reference_code' => $refCode,
                    'current_due' => $currentDue,
                ],
            ], 200);
        } catch (\Throwable $e) {
            Log::error('OrderPaymentMethodController initiateDuePayment error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'Failed to initiate dues payment.',
            ], 500);
        }
    }

    /**
     * Backward-compatible alias for payDues
     * POST /api/provider/dues/pay
     */
    public function payDues(Request $request): JsonResponse
    {
        return $this->initiateDuePayment($request);
    }

    /**
     * Provider Clears Dues via Native Apple Pay
     * POST /api/provider/dues/apple-pay-charge
     */
    public function chargeApplePayDues(Request $request): JsonResponse
    {
        try {
            $user = auth('sanctum')->user() ?? auth()->user();
            if (!$user) {
                return $this->error('Unauthorized.', 401);
            }

            if ((int) $user->role !== 1) {
                return $this->error('Only providers can pay platform dues.', 403);
            }

            $currentDue = (float) ($user->payment_due ?? 0.0);
            if ($currentDue <= 0) {
                return response()->json([
                    'status' => false,
                    'message' => 'You do not have any pending platform dues to pay.',
                ], 400);
            }

            $amountToPay = $request->filled('amount') ? (float) $request->amount : $currentDue;
            if ($amountToPay <= 0) {
                $amountToPay = $currentDue;
            }
            if ($amountToPay > $currentDue) {
                $amountToPay = $currentDue;
            }

            $chargeData = null;
            $chargeId = null;
            $chargeStatus = null;

            if ($request->filled('charge_id')) {
                $chargeId = $request->input('charge_id');
                $chargeData = $this->tapPaymentService->retrieveCharge($chargeId);
                $chargeStatus = strtoupper($chargeData['status'] ?? 'UNKNOWN');
            } elseif ($request->filled('token')) {
                $token = $request->input('token');
                $secretKey = config('services.tap.secret_key');
                if (empty($secretKey)) {
                    throw new \RuntimeException('Tap Payments API Secret Key is not configured.');
                }

                $phoneDigits = preg_replace('/\D/', '', $user->phone ?? '500000000');
                if (strlen($phoneDigits) > 9) {
                    $phoneDigits = substr($phoneDigits, -9);
                }

                $refCode = 'AZHL-DUE-' . date('YmdHis') . '-' . $user->id;

                $payload = [
                    'amount' => $amountToPay,
                    'currency' => 'SAR',
                    'customer' => [
                        'first_name' => $user->name ?? 'Provider',
                        'email' => $user->email ?? 'provider@azhlksa.com',
                        'phone' => [
                            'country_code' => '966',
                            'number' => $phoneDigits,
                        ],
                    ],
                    'source' => [
                        'id' => $token,
                    ],
                    'description' => "Azhl Commission Dues Clearance #{$user->id}",
                    'statement_descriptor' => 'Azhal App Dues',
                    'metadata' => [
                        'provider_id' => (string) $user->id,
                        'user_id' => (string) $user->id,
                        'type' => 'commission_repayment',
                        'reference_code' => $refCode,
                    ],
                ];

                $response = Http::withHeaders([
                    'Authorization' => "Bearer {$secretKey}",
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])->post('https://api.tap.company/v2/charges', $payload);

                $chargeData = $response->json();

                if (!$response->successful() || !empty($chargeData['errors'])) {
                    $errorMessage = $chargeData['errors'][0]['description'] ?? 'Apple Pay charge failed via Tap.';
                    return response()->json([
                        'status' => false,
                        'message' => $errorMessage,
                        'charge_data' => $chargeData,
                    ], 400);
                }

                $chargeId = $chargeData['id'] ?? null;
                $chargeStatus = strtoupper($chargeData['status'] ?? 'UNKNOWN');
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'Either token or charge_id is required.',
                ], 422);
            }

            if ($chargeStatus !== 'CAPTURED') {
                return response()->json([
                    'status' => false,
                    'message' => "Payment not captured. Status: {$chargeStatus}",
                    'data' => [
                        'charge_id' => $chargeId,
                        'status' => strtolower((string) $chargeStatus),
                    ],
                ], 400);
            }

            // Atomic Settlement & Instant Unblock
            return DB::transaction(function () use ($user, $amountToPay, $chargeId, $chargeData) {
                $user = User::where('id', $user->id)->lockForUpdate()->first();
                $previousDue = (float) ($user->payment_due ?? 0.0);
                $newDue = max(0.0, round($previousDue - $amountToPay, 2));

                $user->payment_due = $newDue;
                $threshold = (float) ($user->max_due_threshold ?? 25.00);
                if ($newDue < $threshold) {
                    $user->is_due_blocked = 0;
                }
                $user->save();

                $refCode = $chargeData['metadata']['reference_code'] ?? ('AZHL-DUE-' . $user->id . '-' . time());

                // 1. Audit repayment
                ProviderCommissionRepayment::create([
                    'provider_id' => $user->id,
                    'tap_charge_id' => $chargeId,
                    'transaction_reference' => $refCode,
                    'amount_paid' => $amountToPay,
                    'payment_method' => 'apple_pay',
                    'status' => 'verified',
                    'verified_at' => now(),
                    'gateway_response' => $chargeData,
                ]);

                // 2. Audit ledger
                ProviderDuesLedger::create([
                    'provider_id' => $user->id,
                    'order_id' => null,
                    'type' => 'credit_online_payment',
                    'amount' => $amountToPay,
                    'previous_due' => $previousDue,
                    'current_due' => $newDue,
                    'notes' => "Cleared dues via Native Apple Pay (Charge {$chargeId}). Instant Auto-unblock.",
                ]);

                // 3. Create captured Payment record
                Payment::create([
                    'user_id' => $user->id,
                    'provider_id' => $user->id,
                    'amount' => $amountToPay,
                    'currency' => 'SAR',
                    'gateway' => 'tap',
                    'status' => 'captured',
                    'tap_charge_id' => $chargeId,
                    'gateway_response' => $chargeData,
                ]);

                // 4. Instant FCM Notification
                if (!empty($user->fcm_token)) {
                    try {
                        dispatch(new SendFcmTokenNotificationJob([$user->fcm_token], [
                            'title' => 'تم سداد المستحقات بنجاح | Dues Cleared',
                            'body' => "تم تسوية عمولة أزهل بقيمة {$amountToPay} ر.س بنجاح عبر Apple Pay وإلغاء حظر حسابك فوراً.",
                            'data' => [
                                'type' => 'commission_repayment_confirmed',
                                'charge_id' => (string) $chargeId,
                                'amount_paid' => (string) $amountToPay,
                                'new_due' => (string) $newDue,
                                'is_due_blocked' => (string) ($user->is_due_blocked ?? 0),
                            ],
                        ]));
                    } catch (\Throwable $e) {
                        Log::warning("chargeApplePayDues FCM dispatch error: " . $e->getMessage());
                    }
                }

                return response()->json([
                    'status' => true,
                    'code' => 200,
                    'message' => 'Dues settled successfully via Apple Pay. Account unblocked.',
                    'data' => [
                        'charge_id' => $chargeId,
                        'amount_paid' => $amountToPay,
                        'currency' => 'SAR',
                        'payment_due' => $newDue,
                        'is_due_blocked' => (int) ($user->is_due_blocked ?? 0),
                        'is_blocked' => (bool) (($newDue >= $threshold) || ((int) $user->is_due_blocked === 1)),
                    ],
                ], 200);
            });
        } catch (\Throwable $e) {
            Log::error('OrderPaymentMethodController chargeApplePayDues error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'status' => false,
                'message' => 'Failed to process Apple Pay dues payment: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Provider Dues History / Ledger Summary
     * GET /api/provider/dues/ledger
     */
    public function duesLedger(): JsonResponse
    {
        try {
            $user = auth('sanctum')->user();
            if (!$user) {
                return $this->error('Unauthorized.', 401);
            }

            $ledger = ProviderDuesLedger::where('provider_id', $user->id)
                ->latest()
                ->paginate(20);

            return $this->success([
                'payment_due' => (float) ($user->payment_due ?? 0.0),
                'max_due_threshold' => (float) ($user->max_due_threshold ?? 25.0),
                'is_blocked' => (float) ($user->payment_due ?? 0.0) >= (float) ($user->max_due_threshold ?? 25.0),
                'history' => $ledger,
            ], 'Provider dues ledger retrieved.');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
}
