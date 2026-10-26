<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Orders;
use App\Models\Payment;
use App\Models\ProviderDuesLedger;
use App\Models\User;
use App\Services\Payment\CashPaymentService;
use App\Services\Payment\TapPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
     * 5.3 Provider Clears Due Balance via Online Payment (Tap)
     * POST /api/provider/dues/pay
     */
    public function payDues(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|gt:0',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors(), 'Validation failed.');
        }

        try {
            $user = auth('sanctum')->user();
            if (!$user) {
                return $this->error('Unauthorized.', 401);
            }

            if ((int) $user->role !== 1) {
                return $this->error('Only providers can pay platform dues.', 403);
            }

            $currentDue = (float) ($user->payment_due ?? 0.0);
            $amountToPay = (float) $request->amount;

            if ($currentDue <= 0) {
                return $this->error('You do not have any pending platform dues to pay.', 400);
            }

            if ($amountToPay > $currentDue) {
                $amountToPay = $currentDue;
            }

            // Create a Payment record for dues clearance
            $payment = Payment::create([
                'user_id' => $user->id,
                'provider_id' => $user->id,
                'amount' => $amountToPay,
                'currency' => 'SAR',
                'gateway' => 'tap',
                'status' => 'pending',
                'gateway_response' => json_encode([
                    'type' => 'provider_dues_clearance',
                    'original_due' => $currentDue,
                    'paying_amount' => $amountToPay,
                ]),
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

            return $this->success([
                'payment_id' => (string) $payment->id,
                'tap_charge_id' => $tapChargeId,
                'redirect_url' => $redirectUrl,
                'amount' => $amountToPay,
                'currency' => 'SAR',
                'current_due' => $currentDue,
            ], 'Dues payment initiated successfully.');
        } catch (\Exception $e) {
            Log::error('OrderPaymentMethodController payDues error: ' . $e->getMessage());
            return $this->error($e->getMessage() ?: 'Failed to initiate dues payment.', 500);
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
