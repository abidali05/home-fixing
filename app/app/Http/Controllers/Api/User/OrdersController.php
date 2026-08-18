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
                'open_orders' => ['open'],
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

    public function getReceipt($id)
    {
        try {
            $user = auth('sanctum')->user();
            if (!$user) {
                return $this->error('Unauthorized.', 401);
            }

            // Find order where user is either the customer or the provider
            $order = Orders::with(['job.category', 'provider', 'user'])
                ->where(function ($query) use ($user) {
                    $query->where('user_id', $user->id)
                          ->orWhere('provider_id', $user->id);
                })
                ->where('id', $id)
                ->first();

            if (!$order) {
                return $this->error('Order not found or unauthorized.', 404);
            }

            $setting = \App\Models\Admin\SystemSettingModel::first();

            $receiptData = [
                'receipt_id' => 'SRV-' . str_pad($order->id, 6, '0', STR_PAD_LEFT),
                'order_id' => $order->id,
                'system_name' => optional($setting)->system_name ?? 'Home Fixing',
                'order_date' => $order->created_at ? $order->created_at->toIso8601String() : null,
                'customer' => [
                    'id' => optional($order->user)->id,
                    'name' => optional($order->user)->name ?? 'Customer',
                    'phone' => optional($order->user)->phone,
                ],
                'provider' => [
                    'id' => optional($order->provider)->id,
                    'name' => optional($order->provider)->name ?? 'Provider',
                    'phone' => optional($order->provider)->phone,
                ],
                'job_details' => [
                    'category' => optional($order->job->category)->name ?? 'Service',
                    'description' => optional($order->job)->description,
                ],
                'amount' => (float) $order->price,
                'currency' => 'SAR',
                'status' => $order->status,
                'source' => $order->source,
            ];

            return $this->success($receiptData, 'Service receipt data loaded successfully.');
        } catch (\Throwable $e) {
            Log::error('Error in getReceipt: ' . $e->getMessage());
            return $this->error('Failed to load receipt data.', 500);
        }
    }

    public function getMarketplaceReceipt($id)
    {
        try {
            $user = auth('sanctum')->user();
            if (!$user) {
                return $this->error('Unauthorized.', 401);
            }

            // Find order with all relevant relations
            $order = \App\Models\MarketplaceOrder::with([
                'customer',
                'items.product.category',
                'items.shop.marketplaceProfile'
            ])
                ->where('id', $id)
                ->first();

            if (!$order) {
                return $this->error('Order not found.', 404);
            }

            // Verify authorization: either customer or shop owner of one of the items
            $isCustomer = $order->user_id == $user->id;
            $shopIds = $order->items->pluck('shop_id')->toArray();
            $isShopOwner = in_array($user->id, $shopIds);

            if (!$isCustomer && !$isShopOwner) {
                return $this->error('Unauthorized to view this receipt.', 403);
            }

            $setting = \App\Models\Admin\SystemSettingModel::first();

            // Format items with complete details
            $itemsData = [];
            $computedSubtotal = 0;
            foreach ($order->items as $item) {
                if ($isShopOwner && $item->shop_id != $user->id) {
                    continue;
                }

                $itemPrice = (float) $item->base_price;
                $itemQty = (int) $item->quantity;
                $itemSubtotal = (float) $item->total_price;
                $computedSubtotal += $itemSubtotal;

                $productData = null;
                if ($item->product) {
                    $bannerUrl = !empty($item->product->banner_image)
                        ? asset('storage/' . $item->product->banner_image)
                        : asset('assets/img/default.jpg');

                    $images = $item->product->product_images;
                    if (is_string($images)) {
                        $images = array_filter(explode(',', $images));
                    }

                    $formattedImages = collect($images ?: [])
                        ->map(function ($img) {
                            return asset('storage/' . $img);
                        })
                        ->values();

                    $productData = [
                        'id' => $item->product->id,
                        'product_name' => $item->product->product_name,
                        'product_description' => $item->product->product_description,
                        'banner_image' => $bannerUrl,
                        'product_images' => $formattedImages,
                        'price' => (float) $item->product->price,
                        'sale_price' => $item->product->sale_price ? (float) $item->product->sale_price : null,
                        'sku' => $item->product->sku,
                        'category' => optional($item->product->category)->name,
                    ];
                }

                $shopProfile = optional($item->shop)->marketplaceProfile;
                $shopData = [
                    'id' => optional($item->shop)->id,
                    'name' => optional($item->shop)->name,
                    'email' => optional($item->shop)->email,
                    'phone' => optional($item->shop)->phone,
                    'shop_title' => optional($shopProfile)->shop_title ?? optional($item->shop)->name ?? 'Shop',
                    'banner_image' => optional($shopProfile)->banner_image ? asset('storage/' . $shopProfile->banner_image) : null,
                    'shop_address' => optional($shopProfile)->address,
                ];

                $itemsData[] = [
                    'id' => $item->id,
                    'marketplace_order_id' => $item->marketplace_order_id,
                    'product_id' => $item->product_id,
                    'shop_id' => $item->shop_id,
                    'product_name' => $item->product_name ?? optional($item->product)->product_name ?? 'Product',
                    'product_title' => $item->product_name ?? optional($item->product)->product_name ?? 'Product',
                    'shop_title' => optional($shopProfile)->shop_title ?? optional($item->shop)->name ?? 'Shop',
                    'quantity' => $itemQty,
                    'base_price' => $itemPrice,
                    'price' => $itemPrice,
                    'total_price' => $itemSubtotal,
                    'subtotal' => $itemSubtotal,
                    'created_at' => $item->created_at ? $item->created_at->toIso8601String() : null,
                    'updated_at' => $item->updated_at ? $item->updated_at->toIso8601String() : null,
                    'product' => $productData,
                    'shop' => $shopData,
                ];
            }

            $deliveryCharges = (float) $order->shipping_cost;

            $customerData = null;
            if ($order->customer) {
                $customerData = [
                    'id' => $order->customer->id,
                    'name' => $order->customer->name,
                    'email' => $order->customer->email,
                    'phone' => $order->customer->phone,
                    'image' => $order->customer->image ? asset('storage/' . $order->customer->image) : null,
                ];
            }

            $receiptData = [
                'receipt_id' => 'MKT-' . str_pad($order->id, 6, '0', STR_PAD_LEFT),
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'system_name' => optional($setting)->system_name ?? 'Home Fixing',
                'system_logo' => optional($setting)->logo ? asset('uploads/system_settings/' . $setting->logo) : asset('assets/img/logo.png'),
                'order_date' => $order->created_at ? $order->created_at->toIso8601String() : null,
                'subtotal' => $computedSubtotal,
                'delivery_charges' => $isShopOwner ? 0 : $deliveryCharges,
                'discount' => (float) $order->discount_price,
                'tax' => (float) $order->tax_amount,
                'total_amount' => $isShopOwner ? $computedSubtotal : (float) $order->total_amount,
                'currency' => 'SAR',
                'payment_status' => $order->payment_status ?? 'pending',
                'payment_method' => $order->payment_method,
                'status' => $order->status,
                'notes' => $order->notes,

                // Order metadata & complete fields
                'order' => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'user_id' => $order->user_id,
                    'shipping_address' => $order->shipping_address,
                    'subtotal' => $isShopOwner ? $computedSubtotal : (float) $order->subtotal,
                    'shipping_cost' => $isShopOwner ? 0 : (float) $order->shipping_cost,
                    'tax_amount' => (float) $order->tax_amount,
                    'coupon_code' => $order->coupon_code,
                    'discount_price' => (float) $order->discount_price,
                    'total_amount' => $isShopOwner ? $computedSubtotal : (float) $order->total_amount,
                    'payment_method' => $order->payment_method,
                    'payment_status' => $order->payment_status ?? 'pending',
                    'notes' => $order->notes,
                    'delivery_response_reason' => $order->delivery_response_reason,
                    'status' => $order->status,
                    'created_at' => $order->created_at ? $order->created_at->toIso8601String() : null,
                    'updated_at' => $order->updated_at ? $order->updated_at->toIso8601String() : null,
                ],

                // Customer data
                'customer' => $customerData,

                // Items array with full product and shop data
                'items' => $itemsData,

                // Summary
                'summary' => [
                    'subtotal' => $computedSubtotal,
                    'delivery_charges' => $isShopOwner ? 0 : $deliveryCharges,
                    'discount' => (float) $order->discount_price,
                    'tax' => (float) $order->tax_amount,
                    'total_amount' => $isShopOwner ? $computedSubtotal : (float) $order->total_amount,
                ]
            ];

            return $this->success($receiptData, 'Marketplace receipt data loaded successfully.');
        } catch (\Throwable $e) {
            Log::error('Error in getMarketplaceReceipt: ' . $e->getMessage());
            return $this->error('Failed to load receipt data.', 500);
        }
    }
}
