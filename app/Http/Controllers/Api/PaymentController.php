<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProcessChargeRequest;
use App\Models\BidModel;
use App\Models\JobRequestModel;
use App\Models\Payment;
use App\Services\Payment\TapPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

            // 2. Validate Job is not already hired or completed
            if (in_array($job->status, ['hired', 'completed', 'cancelled'])) {
                return $this->error('This job is already hired or closed.', 400);
            }

            $bid = BidModel::where('id', $bidId)->where('job_id', $job->id)->first();
            if (!$bid) {
                return $this->error('Bid not found for this job.', 404);
            }

            // 3. Calculate total payable amount by customer (Bid Price + Customer App Fee)
            $settings = \App\Models\Admin\SystemSettingModel::first();
            $customerAppFee = (float) ($settings->customer_app_fee ?? 3.00);
            $customerTotal = (float) $bid->price + $customerAppFee;

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
                    'amount' => $customerTotal,
                    'currency' => 'SAR',
                    'gateway' => 'tap',
                    'status' => 'pending',
                ]);
            } else {
                // Ensure amount matches latest bid price + customer app fee
                $payment->update([
                    'amount' => $customerTotal,
                    'provider_id' => $bid->provider_id,
                ]);
            }

            Log::info("PaymentController: Payment initiated for job #{$job->id}, bid #{$bid->id}, payment ID #{$payment->id}");

            return $this->success([
                'payment_id' => $payment->id,
                'amount' => (float) $payment->amount,
                'currency' => $payment->currency,
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
}
