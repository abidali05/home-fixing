<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProcessChargeRequest;
use App\Models\BidModel;
use App\Models\JobRequestModel;
use App\Models\Orders;
use App\Models\Payment;
use App\Services\Job\HireProviderService;
use App\Services\Payment\TapPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    protected TapPaymentService $tapPaymentService;

    public function __construct(TapPaymentService $tapPaymentService)
    {
        $this->tapPaymentService = $tapPaymentService;
    }

    /**
     * API #1: Initiate Payment
     * POST /api/jobs/{job}/bids/{bid}/initiate-payment
     *
     * @param Request $request
     * @param int|string $jobId
     * @param int|string $bidId
     * @return JsonResponse
     */
    public function initiatePayment(Request $request, $jobId, $bidId): JsonResponse
    {
        try {
            $user = auth('sanctum')->user();
            if (!$user) {
                return $this->error('Unauthorized.', 401);
            }

            $job = JobRequestModel::find($jobId);
            if (!$job) {
                return $this->error('Job request not found.', 404);
            }

            // 1. Validate Job belongs to authenticated customer
            if ((int) $job->user_id !== (int) $user->id) {
                return $this->error('You are not authorized to initiate payment for this job.', 403);
            }

            // 2. Validate Job is not cancelled
            if ($job->status === 'cancelled') {
                return $this->error('This job is cancelled.', 400);
            }

            $order = Orders::where('job_id', $job->id)->latest()->first();
            if ($order && (int) $order->paid_to_system === 1) {
                return $this->error('Payment has already been made for this order.', 400);
            }

            $capturedPayment = Payment::where('job_id', $job->id)
                ->where('status', 'captured')
                ->first();
            if ($capturedPayment) {
                return $this->error('Payment has already been captured for this job.', 400);
            }

            $bid = BidModel::where('id', $bidId)->where('job_id', $job->id)->first();
            if (!$bid) {
                return $this->error('Bid not found for this job.', 404);
            }

            // 3. Calculate total payable amount by customer (Base Price + Accepted Extra + Customer App Fee 3 SAR)
            $settings = \App\Models\Admin\SystemSettingModel::first();
            $customerAppFee = ($settings && $settings->customer_app_fee !== null) ? (float) $settings->customer_app_fee : 0.00;

            $bidPrice = (float) ($bid->price ?? 0);
            $extraAmount = 0.00;
            $extraAmountReason = null;
            $extraAmountStatus = 'none';
            $acceptedExtra = 0.00;

            if ($order) {
                $extraAmount = (float) ($order->extra_amount ?? 0);
                $extraAmountReason = $order->extra_amount_reason;
                $extraAmountStatus = (string) ($order->extra_amount_status ?: 'none');
                $acceptedExtra = $extraAmountStatus === 'accepted' ? $extraAmount : 0.00;
                $finalBase = (float) ($order->final_base_price ?: ($bidPrice + $acceptedExtra));
            } else {
                $finalBase = $bidPrice;
            }

            $totalPayableByCustomer = round($finalBase + $customerAppFee, 2);

            $payment = Payment::where('job_id', $job->id)
                ->where('bid_id', $bid->id)
                ->where('user_id', $user->id)
                ->whereIn('status', ['pending', 'processing'])
                ->latest()
                ->first();

            if (!$payment) {
                $payment = Payment::create([
                    'user_id' => $user->id,
                    'job_id' => $job->id,
                    'bid_id' => $bid->id,
                    'provider_id' => $bid->provider_id,
                    'amount' => $totalPayableByCustomer,
                    'currency' => 'SAR',
                    'gateway' => 'tap',
                    'status' => 'pending',
                ]);
            } else {
                $payment->update([
                    'amount' => $totalPayableByCustomer,
                    'provider_id' => $bid->provider_id,
                ]);
            }

            // Check and resolve Tap Marketplace Auto-Split destination for provider
            $splitDetails = null;
            try {
                $splitDetails = $this->tapPaymentService->getProviderSplitDetails($payment, $bid->provider, (float) $finalBase);
                if ($splitDetails) {
                    $payment->update([
                        'tap_destination_id' => $splitDetails['destination_id'],
                        'tap_split_amount' => $splitDetails['provider_share'],
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning("PaymentController: Could not resolve provider split details: " . $e->getMessage());
            }

            Log::info("PaymentController: Payment initiated for job #{$job->id}, bid #{$bid->id}, payment ID #{$payment->id}");

            $breakdown = [
                'bid_price' => number_format($bidPrice, 2, '.', ''),
                'repair_price' => number_format($bidPrice, 2, '.', ''),
                'extra_amount' => number_format($extraAmount, 2, '.', ''),
                'extra_amount_reason' => $extraAmountReason,
                'extra_amount_status' => $extraAmountStatus,
                'accepted_extra' => number_format($acceptedExtra, 2, '.', ''),
                'final_base_price' => number_format($finalBase, 2, '.', ''),
                'customer_app_fee' => number_format($customerAppFee, 2, '.', ''),
                'subtotal' => number_format($totalPayableByCustomer, 2, '.', ''),
                'total_payable_by_customer' => number_format($totalPayableByCustomer, 2, '.', ''),
                'total_amount' => number_format($totalPayableByCustomer, 2, '.', ''),
                'total' => number_format($totalPayableByCustomer, 2, '.', ''),
            ];

            return $this->success([
                'payment_id' => $payment->id,
                'amount' => (float) number_format($payment->amount, 2, '.', ''),
                'currency' => $payment->currency,
                'destinations' => $splitDetails['destinations'] ?? null,
                'destination_id' => $splitDetails['destination_id'] ?? null,
                'provider_share' => $splitDetails['provider_share'] ?? null,
                'azhl_commission' => $splitDetails['azhl_commission'] ?? null,
                'bid_price' => number_format($bidPrice, 2, '.', ''),
                'extra_amount' => number_format($extraAmount, 2, '.', ''),
                'extra_amount_reason' => $extraAmountReason,
                'extra_amount_status' => $extraAmountStatus,
                'total_amount' => number_format($totalPayableByCustomer, 2, '.', ''),
                'customer_app_fee' => number_format($customerAppFee, 2, '.', ''),
                'total_payable_by_customer' => number_format($totalPayableByCustomer, 2, '.', ''),
                'payment_breakdown' => $breakdown,
            ], 'Payment initiated successfully.');

        } catch (\Throwable $e) {
            Log::error('Error in initiatePayment: ' . $e->getMessage(), ['exception' => $e]);
            return $this->error('Failed to initiate payment. ' . $e->getMessage(), 500);
        }
    }

    /**
     * API #2: Process Tap Charge
     * POST /api/payments/charge
     *
     * @param ProcessChargeRequest $request
     * @return JsonResponse
     */
    public function charge(ProcessChargeRequest $request): JsonResponse
    {
        try {
            $user = auth('sanctum')->user();
            $paymentId = $request->input('payment_id');
            $token = $request->input('token', 'src_all') ?: 'src_all';

            $payment = Payment::with(['job', 'bid', 'user', 'provider'])->find($paymentId);

            if (!$payment) {
                return $this->error('Payment record not found.', 404);
            }

            // 1. Validate payment belongs to authenticated user
            if ((int) $payment->user_id !== (int) $user->id) {
                return $this->error('Unauthorized access to this payment.', 403);
            }

            // 2. Check if payment is already captured
            if ($payment->status === 'captured') {
                return $this->success([
                    'payment_id' => $payment->id,
                    'status' => 'captured',
                    'tap_charge_id' => $payment->tap_charge_id,
                    'redirect_url' => null,
                    'is_hired' => true,
                ], 'Payment already captured and provider hired.');
            }

            // 3. Validate job availability
            if ($payment->job && in_array($payment->job->status, ['hired', 'completed', 'cancelled'])) {
                return $this->error('This job is already hired or unavailable.', 400);
            }

            // 4. Update payment status to processing
            $payment->update(['status' => 'processing']);

            // 5. Call Tap Charge API
            $chargeResponse = $this->tapPaymentService->createCharge($payment, $token);

            $tapChargeId = $chargeResponse['id'] ?? null;
            $chargeStatus = strtoupper($chargeResponse['status'] ?? 'PENDING');
            $redirectUrl = $chargeResponse['transaction']['url'] ?? null;

            // 6. Always update tap_charge_id immediately so webhooks/redirects match
            if ($tapChargeId) {
                $payment->update([
                    'tap_charge_id' => $tapChargeId,
                    'gateway_response' => $chargeResponse,
                ]);
            }

            // 7. Handle 3DS Redirect if required by bank
            if (!empty($redirectUrl) && $chargeStatus !== 'CAPTURED') {
                return $this->success([
                    'payment_id' => $payment->id,
                    'status' => strtolower($chargeStatus),
                    'tap_charge_id' => $tapChargeId,
                    'redirect_url' => $redirectUrl,
                    'is_hired' => false,
                ], '3DS Authentication required. Please complete authentication via the redirect URL.');
            }

            // 7. If status is CAPTURED immediately
            if ($chargeStatus === 'CAPTURED') {
                $isHired = $this->tapPaymentService->verifyCharge($tapChargeId, $payment);

                return $this->success([
                    'payment_id' => $payment->id,
                    'status' => 'captured',
                    'tap_charge_id' => $tapChargeId,
                    'redirect_url' => null,
                    'is_hired' => $isHired,
                ], 'Payment processed successfully and provider hired.');
            }

            // 8. If charge failed or declined
            if (in_array($chargeStatus, ['FAILED', 'DECLINED', 'CANCELLED'])) {
                $payment->update([
                    'status' => 'failed',
                    'tap_charge_id' => $tapChargeId,
                    'gateway_response' => $chargeResponse,
                ]);

                return $this->error('Payment failed. ' . ($chargeResponse['response']['message'] ?? 'Transaction was declined.'), 400, [
                    'payment_id' => $payment->id,
                    'status' => 'failed',
                    'tap_charge_id' => $tapChargeId,
                ]);
            }

            return $this->success([
                'payment_id' => $payment->id,
                'status' => strtolower($chargeStatus),
                'tap_charge_id' => $tapChargeId,
                'redirect_url' => $redirectUrl,
                'is_hired' => false,
            ], 'Payment charge initiated.');

        } catch (\Throwable $e) {
            Log::error('Error in charge API: ' . $e->getMessage(), ['exception' => $e]);
            return $this->error($e->getMessage() ?: 'Failed to process payment charge.', 500);
        }
    }

    /**
     * API #3: Check Payment Status
     * GET /api/payments/{payment}/status
     *
     * @param int|string $paymentId
     * @return JsonResponse
     */
    public function status($paymentId): JsonResponse
    {
        try {
            $user = auth('sanctum')->user();
            if (!$user) {
                return $this->error('Unauthorized.', 401);
            }

            $payment = Payment::with('job')->find($paymentId);

            if (!$payment) {
                return $this->error('Payment record not found.', 404);
            }

            // Validate ownership (customer or provider)
            if ((int) $payment->user_id !== (int) $user->id && (int) $payment->provider_id !== (int) $user->id) {
                return $this->error('Unauthorized access to payment status.', 403);
            }

            return $this->success([
                'payment_id' => $payment->id,
                'status' => $payment->status,
                'tap_charge_id' => $payment->tap_charge_id,
                'amount' => (float) $payment->amount,
                'currency' => $payment->currency,
                'job_id' => $payment->job_id,
                'job_status' => $payment->job ? $payment->job->status : null,
            ], 'Payment status retrieved successfully.');

        } catch (\Throwable $e) {
            Log::error('Error in payment status API: ' . $e->getMessage());
            return $this->error('Failed to retrieve payment status.', 500);
        }
    }

    /**
     * API #5: Verify Tap Native SDK Payment
     * POST /api/jobs/{jobId}/bids/{bidId}/verify-tap-payment
     *
     * @param Request $request
     * @param int|string $jobId
     * @param int|string $bidId
     * @return JsonResponse
     */
    public function verifyTapPayment(Request $request, $jobId, $bidId): JsonResponse
    {
        try {
            $user = auth('sanctum')->user();
            if (!$user) {
                return $this->error('Unauthorized.', 401);
            }

            $chargeId = $request->input('charge_id') ?? $request->input('chargeId') ?? $request->input('id') ?? $request->input('tap_id');
            if (empty($chargeId) || !is_string($chargeId)) {
                return $this->error('The charge_id field is required.', 422);
            }

            // 1. Fetch & Validate Job & Bid
            $job = JobRequestModel::find($jobId);
            if (!$job) {
                return $this->error('Job request not found.', 404);
            }

            if ((int) $job->user_id !== (int) $user->id) {
                return $this->error('You are not authorized to verify payment for this job.', 403);
            }

            $bid = BidModel::where('id', $bidId)->where('job_id', $job->id)->first();
            if (!$bid) {
                return $this->error('Bid not found for this job.', 404);
            }

            // 2. Idempotency Check: if order is already marked paid, return success immediately
            $order = Orders::where('job_id', $job->id)->first();
            if ($order && ($order->payment_status === 'paid' || (int) $order->paid_to_system === 1)) {
                return $this->success([
                    'order_id' => $order->id,
                    'job_id' => (int) $job->id,
                    'bid_id' => (int) $bid->id,
                    'payment_status' => 'paid',
                    'payment_method' => $order->payment_method ?: 'card',
                    'transaction_id' => $order->transaction_id ?: $chargeId,
                    'amount' => (float) ($order->total_amount ?: $order->price),
                ], 'Order is already marked as paid.');
            }

            // 3. Server-to-Server Verification directly with Tap Payments API
            try {
                $chargeData = $this->tapPaymentService->retrieveCharge($chargeId);
            } catch (\Throwable $e) {
                Log::error("verifyTapPayment: Error retrieving charge {$chargeId}: " . $e->getMessage());
                return $this->error('Failed to verify payment with Tap Gateway. ' . $e->getMessage(), 400);
            }

            $chargeStatus = strtoupper($chargeData['status'] ?? 'UNKNOWN');
            $chargedAmount = (float) ($chargeData['amount'] ?? 0);
            $chargedCurrency = strtoupper($chargeData['currency'] ?? '');

            // 4. Security Assertions
            if ($chargeStatus !== 'CAPTURED') {
                return $this->error("Payment not captured. Status: {$chargeStatus}", 400, [
                    'charge_id' => $chargeId,
                    'status' => strtolower($chargeStatus),
                ]);
            }

            if ($chargedCurrency !== 'SAR') {
                return $this->error("Payment currency mismatch. Expected SAR, got {$chargedCurrency}.", 400);
            }

            // Expected amount check (with tolerance)
            $settings = \App\Models\Admin\SystemSettingModel::first();
            $customerAppFee = ($settings && $settings->customer_app_fee !== null) ? (float) $settings->customer_app_fee : 0.00;
            $bidPrice = (float) ($bid->price ?? 0);
            $extraAmount = $order ? (float) ($order->extra_amount ?? 0) : 0.00;
            $acceptedExtra = ($order && $order->extra_amount_status === 'accepted') ? $extraAmount : 0.00;
            $finalBase = $order ? (float) ($order->final_base_price ?: ($bidPrice + $acceptedExtra)) : $bidPrice;
            $expectedAmount = round($finalBase + $customerAppFee, 2);

            if (abs($chargedAmount - $expectedAmount) > 0.50 && abs($chargedAmount - $bidPrice) > 0.50) {
                Log::warning("verifyTapPayment: Amount mismatch for job #{$job->id}. Charged: {$chargedAmount}, Expected: {$expectedAmount} or {$bidPrice}");
                return $this->error('Payment amount mismatch.', 400, [
                    'charged_amount' => $chargedAmount,
                    'expected_amount' => $expectedAmount,
                ]);
            }

            // 5. Execute DB updates atomically
            return DB::transaction(function () use ($job, $bid, $user, $chargeId, $chargeData, $chargedAmount, $chargedCurrency) {
                $payment = Payment::where('job_id', $job->id)
                    ->where('bid_id', $bid->id)
                    ->latest()
                    ->first();

                if (!$payment) {
                    $payment = Payment::create([
                        'user_id' => $user->id,
                        'job_id' => $job->id,
                        'bid_id' => $bid->id,
                        'provider_id' => $bid->provider_id,
                        'amount' => $chargedAmount,
                        'currency' => $chargedCurrency,
                        'gateway' => 'tap',
                        'status' => 'captured',
                        'tap_charge_id' => $chargeId,
                        'gateway_response' => ['charge' => $chargeData],
                    ]);
                } else {
                    $payment->update([
                        'amount' => $chargedAmount,
                        'status' => 'captured',
                        'tap_charge_id' => $chargeId,
                        'gateway_response' => array_merge($payment->gateway_response ?? [], ['charge' => $chargeData]),
                    ]);
                }

                // If charge contains split destinations, record them on payment
                $destList = $chargeData['destinations']['destination'] ?? null;
                if (!empty($destList) && is_array($destList)) {
                    $firstDest = $destList[0] ?? null;
                    if ($firstDest && !empty($firstDest['id'])) {
                        $payment->update([
                            'tap_destination_id' => $firstDest['id'],
                            'tap_split_amount' => (float) ($firstDest['amount'] ?? 0),
                        ]);
                    }
                }

                // Execute provider hiring
                $hireProviderService = app(HireProviderService::class);
                $hireProviderService->hireProvider($payment);

                // Ensure order record is marked paid & synced
                $order = Orders::where('job_id', $job->id)->first();
                if ($order) {
                    $order->payment_status = 'paid';
                    $order->payment_method = 'card';
                    $order->paid_to_system = 1;
                    $order->transaction_id = $chargeId;
                    if (empty($order->paid_at)) {
                        $order->paid_at = now();
                    }
                    $order->calculateAndSyncFinancials(false);
                    $order->save();
                }

                Log::info("verifyTapPayment: Payment {$chargeId} successfully verified for job #{$job->id}, order #{$order?->id}");

                return $this->success([
                    'order_id' => $order ? (int) $order->id : null,
                    'job_id' => (int) $job->id,
                    'bid_id' => (int) $bid->id,
                    'payment_status' => 'paid',
                    'payment_method' => 'card',
                    'transaction_id' => $chargeId,
                    'amount' => (float) number_format($chargedAmount, 2, '.', ''),
                    'currency' => $chargedCurrency,
                ], 'Payment verified successfully and order finalized.');
            });

        } catch (\Throwable $e) {
            Log::error('Error in verifyTapPayment: ' . $e->getMessage(), ['exception' => $e]);
            return $this->error('Failed to verify payment: ' . $e->getMessage(), 500);
        }
    }
}
