<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\Admin\ServiceCategoryModel;
use App\Models\Admin\SystemSettingModel;
use App\Models\JobRequestModel;
use App\Models\MarketplaceOrder;
use App\Models\Orders;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Reviews;
use App\Models\User;
use App\Notifications\ProviderFeedbackReceivedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

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

    /**
     * Service Order Receipt API
     * GET /api/v1/orders/{id}/receipt
     */
    public function getReceipt($id)
    {
        try {
            $user = auth('sanctum')->user();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            }

            $order = Orders::with(['job.category', 'provider', 'user'])->find($id);

            if (!$order) {
                return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
            }

            // Authorization check (Customer or Provider or Admin)
            if ((int) $order->user_id !== (int) $user->id && (int) $order->provider_id !== (int) $user->id && (int) $user->role !== 0) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access to this order receipt.'], 403);
            }

            $payment = Payment::where('job_id', $order->job_id)
                ->orWhere('id', $order->id)
                ->where('status', 'captured')
                ->latest()
                ->first();

            $settings = SystemSettingModel::first();
            $customerAppFee = (float) ($settings->customer_app_fee ?? 3.00);
            $azhlPercentage = (float) ($settings->azhl_percentage ?? 10.00);
            $gatewayFeePct = (float) ($settings->payment_gateway_fee_percentage ?? 2.50);
            $gatewayFixedFee = (float) ($settings->payment_gateway_fixed_fee ?? 1.00);
            $gatewayVatPct = (float) ($settings->payment_gateway_vat_percentage ?? 15.00);

            $job = $order->job;
            $categoryName = optional(optional($job)->category)->name ?: 'General Service';
            $orderTitle = optional($job)->title ?: $categoryName;

            $repairPrice = (float) ($order->price ?? 0);
            if (!empty($order->job_id)) {
                $acceptedBid = \App\Models\BidModel::where('job_id', $order->job_id)->whereIn('status', ['accepted', 'completed', 'hired', 'cancelled'])->first();
                if ($acceptedBid && (float) $acceptedBid->price > 0) {
                    $repairPrice = (float) $acceptedBid->price;
                }
            }

            if ($repairPrice > 103) {
                $approxSubtotal = ($repairPrice - $gatewayFixedFee * (1 + $gatewayVatPct / 100)) / (1 + ($gatewayFeePct / 100) * (1 + $gatewayVatPct / 100));
                $estimatedRepair = max(0, $approxSubtotal - $customerAppFee);
                $repairPrice = abs($estimatedRepair - round($estimatedRepair)) < 0.1 ? (float) round($estimatedRepair) : (float) round($estimatedRepair, 2);
            }

            $subtotal = $repairPrice + $customerAppFee;
            $gatewaySubtotal = ($subtotal * ($gatewayFeePct / 100)) + $gatewayFixedFee;
            $gatewayVat = $gatewaySubtotal * ($gatewayVatPct / 100);
            $totalGatewayFee = $gatewaySubtotal + $gatewayVat;
            $totalPayableByCustomer = $payment ? (float) $payment->amount : ($repairPrice + $customerAppFee + $totalGatewayFee);
            $azhlFee = $repairPrice * ($azhlPercentage / 100);
            $netProviderEarning = max(0, $repairPrice - $azhlFee);

            $receiptData = [
                'receipt_no' => 'SRV-' . str_pad($order->id, 6, '0', STR_PAD_LEFT),
                'order_id' => (int) $order->id,
                'order_no' => 'ORD-' . str_pad($order->id, 6, '0', STR_PAD_LEFT),
                'service_title' => $orderTitle,
                'category_name' => $categoryName,
                'customer' => [
                    'id' => (int) $order->user_id,
                    'name' => optional($order->user)->name ?? 'Customer',
                    'email' => optional($order->user)->email ?? '',
                    'phone' => optional($order->user)->phone ?? '',
                ],
                'provider' => [
                    'id' => (int) ($order->provider_id ?: 0),
                    'name' => optional($order->provider)->name ?? 'Provider',
                    'phone' => optional($order->provider)->phone ?? '',
                ],
                'financial_breakdown' => [
                    'repair_price' => number_format($repairPrice, 2, '.', ''),
                    'customer_app_fee' => number_format($customerAppFee, 2, '.', ''),
                    'subtotal' => number_format($subtotal, 2, '.', ''),
                    'gateway_fee_percentage' => number_format($gatewayFeePct, 2, '.', ''),
                    'gateway_fixed_fee' => number_format($gatewayFixedFee, 2, '.', ''),
                    'gateway_vat' => number_format($gatewayVat, 2, '.', ''),
                    'total_gateway_fee' => number_format($totalGatewayFee, 2, '.', ''),
                    'total_paid_by_customer' => number_format($totalPayableByCustomer, 2, '.', ''),
                    'azhl_provider_commission' => number_format($azhlFee, 2, '.', ''),
                    'net_provider_earning' => number_format($netProviderEarning, 2, '.', ''),
                ],
                'amount' => number_format($totalPayableByCustomer, 2, '.', ''),
                'repair_price' => number_format($repairPrice, 2, '.', ''),
                'customer_app_fee' => number_format($customerAppFee, 2, '.', ''),
                'gateway_fee' => number_format($totalGatewayFee, 2, '.', ''),
                'azhl_system_fee' => number_format($azhlFee, 2, '.', ''),
                'provider_earning' => number_format($netProviderEarning, 2, '.', ''),
                'currency' => strtoupper($payment ? ($payment->currency ?: 'SAR') : 'SAR'),
                'order_status' => strtolower($order->status ?: 'completed'),
                'payment_status' => $payment ? $payment->status : ((int) $order->paid_to_system === 1 ? 'captured' : 'pending'),
                'payment_gateway' => $payment ? ($payment->gateway ?: 'tap') : 'tap',
                'tap_charge_id' => $payment ? $payment->tap_charge_id : null,
                'paid_at' => $payment && $payment->created_at ? $payment->created_at->setTimezone('Asia/Riyadh')->toIso8601String() : ($order->created_at ? $order->created_at->setTimezone('Asia/Riyadh')->toIso8601String() : null),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Service order receipt retrieved successfully.',
                'data' => $receiptData
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Error in getReceipt: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load order receipt: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Marketplace Product Order Receipt API
     * GET /api/v1/marketplace/orders/{id}/receipt
     */
    public function getMarketplaceReceipt($id)
    {
        try {
            $user = auth('sanctum')->user();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            }

            $order = MarketplaceOrder::with(['items.product', 'customer'])
                ->where('id', $id)
                ->orWhere('order_number', $id)
                ->first();

            if (!$order) {
                return response()->json(['success' => false, 'message' => 'Marketplace Order not found.'], 404);
            }

            // Authorization check: Customer who placed order, Seller who owns items in this order, or Admin
            $isCustomer = (int) $order->user_id === (int) $user->id;
            $isSeller = $order->items->contains(function ($item) use ($user) {
                return (int) optional($item->product)->user_id === (int) $user->id;
            });
            $isAdmin = in_array((string) $user->role, ['admin', 'superadmin']) || \Illuminate\Support\Facades\Auth::guard('admin')->check();

            if (!$isCustomer && !$isSeller && !$isAdmin) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access to this marketplace order receipt.'], 403);
            }

            $payment = Payment::where('marketplace_order_id', $order->id)
                ->where('status', 'captured')
                ->latest()
                ->first();

            $receiptData = [
                'receipt_no' => 'MKT-' . str_pad($order->id, 6, '0', STR_PAD_LEFT),
                'order_id' => (int) $order->id,
                'order_number' => $order->order_number ?: ('ORD-' . str_pad($order->id, 6, '0', STR_PAD_LEFT)),
                'customer' => [
                    'id' => (int) $order->user_id,
                    'name' => optional($order->customer)->name ?? $user->name,
                    'email' => optional($order->customer)->email ?? $user->email,
                    'phone' => optional($order->customer)->phone ?? $user->phone,
                ],
                'shipping_address' => $order->shipping_address ?: $user->address,
                'subtotal' => round((float) ($order->subtotal ?? 0), 2),
                'shipping_cost' => round((float) ($order->shipping_cost ?? 0), 2),
                'tax_amount' => round((float) ($order->tax_amount ?? 0), 2),
                'discount_price' => round((float) ($order->discount_price ?? 0), 2),
                'total_amount' => round((float) ($order->total_amount ?? 0), 2),
                'currency' => 'SAR',
                'payment_method' => $order->payment_method ?: 'tap',
                'payment_status' => $payment ? $payment->status : 'captured',
                'tap_charge_id' => optional($payment)->tap_charge_id,
                'items' => $order->items->map(function ($item) {
                    return [
                        'item_id' => (int) $item->id,
                        'product_id' => (int) $item->product_id,
                        'product_name' => $item->product_name ?: optional($item->product)->product_name,
                        'quantity' => (int) $item->quantity,
                        'base_price' => round((float) $item->base_price, 2),
                        'total_price' => round((float) $item->total_price, 2),
                    ];
                }),
                'paid_at' => $payment && $payment->created_at ? $payment->created_at->setTimezone('Asia/Riyadh')->toIso8601String() : ($order->created_at ? $order->created_at->setTimezone('Asia/Riyadh')->toIso8601String() : null),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Marketplace order receipt retrieved successfully.',
                'data' => $receiptData
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Error in getMarketplaceReceipt: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load marketplace receipt: ' . $e->getMessage()], 500);
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
                if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'rating')) {
                    $reviews = Reviews::where('provider_id', $request->provider_id)->get();
                    $avgRating = round($reviews->avg('rating'), 1);

                    $provider->rating = $avgRating;
                    $provider->save();
                }

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
            return $this->error('Failed to submit feedback: ' . $e->getMessage(), 500);
        }
    }
}
