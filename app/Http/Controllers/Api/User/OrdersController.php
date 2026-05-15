<?php

namespace App\Http\Controllers\Api\User;

use App\Models\Orders;
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
    public function my_orders()
    {
        try {
            $user = auth('sanctum')->user();

            $statuses = [
                'ongoing_orders' => ['arrived', 'on_the_way', 'working', 'provider_completed'],
                'completed_orders' => ['completed'],
                'scheduled_orders' => ['pending'],
                'cancelled_orders' => ['cancelled'],
            ];


            $data = [];

            foreach ($statuses as $key => $status) {
                $orders = Orders::with(['job.category', 'provider'])
                    ->where('user_id', $user->id)
                    ->whereIn('status', (array) $status)
                    ->orderBy('id','DESC')
                    ->get();

                foreach ($orders as $order) {
                    $category = $order->job->category ?? null;
                    if ($category) {
                        $category->path = $category->path
                            ? asset('uploads/service_category/' . $category->path)
                            : asset('assets/img/default.jpg');
                    }
                }

                $data[$key] = $orders;
            }

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
            return $this->validationError($validator->errors(), 'Validation failed.');
        }

        DB::beginTransaction();

        try {
            $customer = auth('sanctum')->user();
            if (!$customer) {
                return $this->error('Unauthorized.', 401);
            }
            if ((int) $customer->role !== 0) {
                return $this->error('Only customers can submit feedback.', 403);
            }

            $userId = $customer->id;
            $order = Orders::where('id', $request->order_id)
                ->where('user_id', $userId)
                ->where('provider_id', $request->provider_id)
                ->first();

            if (!$order) {
                return $this->error('Order not found for this customer/provider.', 404);
            }

            $existingReview = Reviews::where('order_id', $request->order_id)
                ->where('user_id', $userId)
                ->first();

            if ($existingReview) {
                return $this->error('You have already submitted a review for this order.', 409);
            }

            Reviews::create([
                'order_id' => $request->order_id,
                'provider_id' => $request->provider_id,
                'user_id' => $userId,
                'rating' => $request->rating,
                'review' => $request->review,
            ]);

            DB::commit();

            $provider = User::find($request->provider_id);
            if ($provider) {
                try {
                    $provider->notify((new ProviderFeedbackReceivedNotification($order, $customer, (float) $request->rating))->afterCommit());
                } catch (\Throwable $notificationException) {
                    Log::error('Failed to send provider feedback notification: ' . $notificationException->getMessage());
                }
            }

            return $this->success(null, 'Review submitted successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Feedback submission failed: ' . $e->getMessage());
            return $this->error('Failed to submit review.', 500);
        }
    }
}
