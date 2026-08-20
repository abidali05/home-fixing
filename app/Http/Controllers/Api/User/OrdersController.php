<?php

namespace App\Http\Controllers\Api\User;

use App\Models\Orders;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Http\Request;
use App\Models\JobRequestModel;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Models\Admin\ServiceCategoryModel;
use App\Models\Reviews;
use App\Models\User;
use App\Notifications\ProviderFeedbackReceivedNotification;
use Illuminate\Support\Facades\DB;

class OrdersController extends Controller
{
    /**
     * Get Customer Orders by Status Categories with Pagination & Refund Status Tracking
     * GET /api/v1/my-orders?page=1&per_page=20&filter=all
     */
    public function my_orders(Request $request)
    {
        try {
            $user = auth('sanctum')->user();
            if (!$user) {
                return $this->error('Unauthenticated.', 401);
            }

            $page = (int) $request->input('page', 1);
            $perPage = (int) $request->input('per_page', 20);
            $filter = strtolower($request->input('filter', $request->input('status', 'all')));

            $statuses = [
                'ongoing_orders' => ['arrived', 'on_the_way', 'working', 'provider_completed'],
                'completed_orders' => ['completed'],
                'scheduled_orders' => ['pending'],
                'cancelled_orders' => ['cancelled'],
                'open_orders' => ['open'],
            ];

            // Fetch all customer refunds for fast mapping
            $customerOrderIds = Orders::where('user_id', $user->id)->pluck('id')->toArray();
            $customerRefunds = Refund::where('customer_id', $user->id)
                ->orWhereIn('order_id', $customerOrderIds)
                ->get()
                ->keyBy('order_id');

            $capturedJobIds = Payment::where('user_id', $user->id)
                ->where('status', 'captured')
                ->pluck('job_id')
                ->filter()
                ->toArray();

            $data = [];
            $totalCount = 0;

            foreach ($statuses as $key => $statusArray) {
                if ($filter !== 'all' && $filter !== $key) {
                    $data[$key] = [];
                    continue;
                }

                $query = Orders::with(['job.category', 'provider'])
                    ->where('user_id', $user->id)
                    ->whereIn('status', (array) $statusArray)
                    ->orderBy('id', 'DESC');

                $categoryTotal = $query->count();
                $totalCount += $categoryTotal;

                $orders = $query->skip(($page - 1) * $perPage)
                    ->take($perPage)
                    ->get();

                foreach ($orders as $order) {
                    $category = $order->job->category ?? null;
                    if ($category) {
                        $category->path = $category->path
                            ? asset('uploads/service_category/' . $category->path)
                            : asset('assets/img/default.jpg');
                    }

                    // For cancelled_orders: Attach refund status lifecycle details
                    if ($key === 'cancelled_orders' || strtolower($order->status) === 'cancelled') {
                        $refund = $customerRefunds->get($order->id);
                        $isPaid = (int) $order->paid_to_system === 1 || in_array($order->job_id, $capturedJobIds);

                        $refundData = null;
                        if ($refund) {
                            $rawStatus = strtolower($refund->status ?: 'requested');
                            $refundStatusStr = $rawStatus;
                            if (in_array($rawStatus, ['refunded', 'completed', 'paid'])) {
                                $refundStatusStr = 'completed';
                            } elseif ($rawStatus === 'accepted') {
                                $refundStatusStr = 'accepted';
                            } elseif (in_array($rawStatus, ['rejected', 'failed'])) {
                                $refundStatusStr = 'rejected';
                            } else {
                                $refundStatusStr = 'requested';
                            }

                            $refundData = [
                                'refund_id' => (int) $refund->id,
                                'refund_no' => $refund->refund_no ?: ('REF-' . str_pad($refund->id, 6, '0', STR_PAD_LEFT)),
                                'order_id' => (int) $order->id,
                                'amount' => round((float) ($refund->amount ?? 0), 2),
                                'currency' => strtoupper($refund->currency ?: 'SAR'),
                                'status' => $refundStatusStr, // requested, accepted, completed, rejected
                                'refund_reference' => $refund->bank_reference ?: $refund->gateway_refund_id,
                                'requested_at' => $refund->created_at ? $refund->created_at->setTimezone('Asia/Riyadh')->toIso8601String() : null,
                                'accepted_at' => in_array($refundStatusStr, ['accepted', 'completed']) && $refund->updated_at ? $refund->updated_at->setTimezone('Asia/Riyadh')->toIso8601String() : null,
                                'completed_at' => $refundStatusStr === 'completed' ? ($refund->refunded_at ? $refund->refunded_at->setTimezone('Asia/Riyadh')->toIso8601String() : ($refund->updated_at ? $refund->updated_at->setTimezone('Asia/Riyadh')->toIso8601String() : null)) : null,
                                'rejected_at' => $refundStatusStr === 'rejected' ? ($refund->failed_at ? $refund->failed_at->setTimezone('Asia/Riyadh')->toIso8601String() : ($refund->updated_at ? $refund->updated_at->setTimezone('Asia/Riyadh')->toIso8601String() : null)) : null,
                                'rejection_reason' => $refundStatusStr === 'rejected' ? ($refund->failure_reason ?: $refund->admin_notes) : null,
                            ];
                        }

                        $order->refund_status = $refundData ? $refundData['status'] : ($isPaid ? 'eligible' : 'not_required');
                        $order->can_request_refund = $isPaid && !$refund;
                        $order->refund = $refundData;
                    }
                }

                $data[$key] = $orders;
            }

            $lastPage = (int) ceil($totalCount / $perPage) ?: 1;

            $data['pagination'] = [
                'current_page' => $page,
                'per_page' => $perPage,
                'last_page' => $lastPage,
                'total' => $totalCount,
                'from' => $totalCount > 0 ? (($page - 1) * $perPage) + 1 : 0,
                'to' => min($page * $perPage, $totalCount),
                'has_more' => $page < $lastPage,
            ];

            return $this->success($data, 'My orders loaded successfully.');
        } catch (\Throwable $e) {
            Log::error('Error in my_orders: ' . $e->getMessage());
            return $this->error('Failed to load my orders.', 500);
        }
    }

    public function submit_feedback(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|exists:orders,id',
            'provider_id' => 'required|exists:users,id',
            'rating' => 'required|numeric|min:1|max:5',
            'review' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        try {
            DB::beginTransaction();

            $order = Orders::find($request->order_id);
            if (!$order) {
                return $this->error('Order not found', 404);
            }

            if ($order->status !== 'completed') {
                return $this->error('Feedback can only be submitted for completed orders', 400);
            }

            $existingReview = Reviews::where('order_id', $request->order_id)
                ->where('user_id', auth('sanctum')->id())
                ->first();

            if ($existingReview) {
                return $this->error('Feedback already submitted for this order', 400);
            }

            $review = Reviews::create([
                'order_id' => $request->order_id,
                'user_id' => auth('sanctum')->id(),
                'provider_id' => $request->provider_id,
                'rating' => $request->rating,
                'review' => $request->review,
            ]);

            $provider = User::find($request->provider_id);
            if ($provider) {
                $reviews = Reviews::where('provider_id', $request->provider_id)->get();
                $avgRating = round($reviews->avg('rating'), 1);

                $provider->rating = $avgRating;
                $provider->save();
            }

            if ($provider) {
                try {
                    $provider->notify(new ProviderFeedbackReceivedNotification($review));
                } catch (\Throwable $notificationException) {
                    Log::error('Failed to send provider feedback notification: ' . $notificationException->getMessage());
                }
            }

            DB::commit();

            return $this->success($review, 'Feedback submitted successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error submitting feedback: ' . $e->getMessage());
            return $this->error('Failed to submit feedback', 500);
        }
    }
}
