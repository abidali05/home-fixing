<?php

namespace App\Services\Job;

use App\Models\BidModel;
use App\Models\JobRequestModel;
use App\Models\Orders;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\BidAcceptedNotification;
use App\Notifications\BidRejectedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HireProviderService
{
    /**
     * Executes the provider hiring process upon successful payment capture.
     *
     * @param Payment $payment
     * @return bool
     */
    public function hireProvider(Payment $payment): bool
    {
        return DB::transaction(function () use ($payment) {
            $job = JobRequestModel::where('id', $payment->job_id)->lockForUpdate()->first();
            $bid = BidModel::where('id', $payment->bid_id)->lockForUpdate()->first();
            $customer = User::find($payment->user_id);
            $provider = User::find($payment->provider_id);

            if (!$job || !$bid || !$customer || !$provider) {
                Log::error('HireProviderService: Missing required models for payment ID #' . $payment->id);
                return false;
            }

            // Idempotency check: if job is already hired or bid is accepted
            if ($job->status === 'hired' || $bid->status === 'accepted') {
                Log::info("HireProviderService: Job #{$job->id} is already hired. Skipping duplicate hiring execution.");
                return true;
            }

            // 1. Update Job Status
            $job->status = 'quoted';
            $job->save();

            // 2. Accept Selected Bid
            $bid->status = 'accepted';
            $bid->save();

            // 3. Reject other pending bids for this job
            $otherBids = BidModel::where('job_id', $job->id)
                ->where('id', '!=', $bid->id)
                ->where('status', '!=', 'accepted')
                ->get();

            foreach ($otherBids as $otherBid) {
                $otherBid->status = 'rejected';
                $otherBid->save();

                $otherProvider = User::find($otherBid->provider_id);
                if ($otherProvider) {
                    try {
                        $otherProvider->notify(
                            (new BidRejectedNotification(
                                $job,
                                $customer,
                                'Better luck next time. Your offer was not accepted for this request.'
                            ))->afterCommit()
                        );
                    } catch (\Throwable $e) {
                        Log::error("Failed to send bid rejected notification to provider #{$otherBid->provider_id}: " . $e->getMessage());
                    }
                }
            }

            // 4. Update or Create Order in 'orders' table
            $order = Orders::where('job_id', $job->id)->first();
            if (!$order) {
                $order = new Orders();
                $order->job_id = $job->id;
                $order->source = 'bid';
                $order->address = $job->address ?? '';
                $order->details = $job->description ?? '';
            }

            $order->user_id = $customer->id;
            $order->provider_id = $provider->id;
            $order->price = $payment->bid ? (float) $payment->bid->price : (float) $payment->amount;
            $order->status = 'pending';
            $order->paid_to_system = 1;
            $order->save();

            // 5. Send Bid Accepted Notification to Hired Provider
            try {
                $provider->notify((new BidAcceptedNotification($job, $customer))->afterCommit());
            } catch (\Throwable $e) {
                Log::error("Failed to send bid accepted notification to provider #{$provider->id}: " . $e->getMessage());
            }

            Log::info("HireProviderService: Successfully hired provider #{$provider->id} for job #{$job->id} via payment #{$payment->id}.");

            return true;
        });
    }
}
