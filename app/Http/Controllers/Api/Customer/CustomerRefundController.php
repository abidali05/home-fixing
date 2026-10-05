<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Admin\SystemSettingModel;
use App\Models\BankAccount;
use App\Models\MarketplaceOrder;
use App\Models\Orders;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CustomerRefundController extends Controller
{
    /**
     * Standalone Customer Refund Request API for Cancelled Orders (Service Orders & Marketplace Orders)
     * POST /api/v1/customer/refunds/request
     * POST /api/v1/marketplace/refunds/request
     * POST /api/v1/orders/{order_id}/refund
     */
    public function requestRefund(Request $request, $order_id = null)
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $orderId = $order_id ?: ($request->input('order_id') ?: $request->input('marketplace_order_id'));

        $validator = Validator::make(['order_id' => $orderId], [
            'order_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed. Order ID is required.',
                'errors' => $validator->errors()
            ], 422);
        }

        return DB::transaction(function () use ($request, $user, $orderId) {
            $serviceOrder = Orders::where('id', $orderId)->lockForUpdate()->first();
            $marketplaceOrder = null;

            if (!$serviceOrder) {
                $marketplaceOrder = MarketplaceOrder::where('id', $orderId)->lockForUpdate()->first();
            }

            if (!$serviceOrder && !$marketplaceOrder) {
                return response()->json(['success' => false, 'message' => 'Order record not found.'], 404);
            }

            // 1. Check Customer Authorization
            $orderUserId = $serviceOrder ? $serviceOrder->user_id : $marketplaceOrder->user_id;
            if ((int) $orderUserId !== (int) $user->id && (int) $user->role !== 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to request a refund for this order.'
                ], 403);
            }

            // 2. Validate Order Status is Cancelled
            $orderStatus = strtolower($serviceOrder ? $serviceOrder->status : $marketplaceOrder->status);
            if (!in_array($orderStatus, ['cancelled', 'returned', 'failed'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Refunds can only be requested for cancelled or returned orders. Current order status: ' . ($serviceOrder ? $serviceOrder->status : $marketplaceOrder->status)
                ], 422);
            }

            // 3. Calculate Azhl System Fee & Net Customer Refund
            $settings = SystemSettingModel::first();
            $azhlPercentage = (float) ($settings->azhl_percentage ?? 10.00);

            if ($serviceOrder) {
                $payment = Payment::where('job_id', $serviceOrder->job_id)
                    ->orWhere('id', $serviceOrder->id)
                    ->where('status', 'captured')
                    ->latest()
                    ->first();

                $paidAmount = $payment ? (float) $payment->amount : ((float) ($serviceOrder->price ?? 0));
                $isPaid = $payment || (int) $serviceOrder->paid_to_system === 1;
            } else {
                $payment = Payment::where('marketplace_order_id', $marketplaceOrder->id)
                    ->where('status', 'captured')
                    ->latest()
                    ->first();

                $paidAmount = $payment ? (float) $payment->amount : ((float) ($marketplaceOrder->total_amount ?? 0));
                $isPaid = (bool) $payment || strtolower($marketplaceOrder->payment_status ?? '') === 'captured';
            }

            if (!$isPaid || $paidAmount <= 0) {
                if ($serviceOrder) {
                    $serviceOrder->refund_status = 'not_required';
                    $serviceOrder->save();
                }

                return response()->json([
                    'success' => true,
                    'message' => 'No captured payment found for this order. Refund is not required.',
                    'data' => [
                        'order_id' => (int) $orderId,
                        'order_no' => 'ORD-' . str_pad($orderId, 6, '0', STR_PAD_LEFT),
                        'order_status' => 'cancelled',
                        'refund' => [
                            'status' => 'not_required',
                            'amount' => 0.00
                        ]
                    ]
                ], 200);
            }

            $azhlFee = round($paidAmount * ($azhlPercentage / 100), 2);
            $refundAmount = max(0, $paidAmount - $azhlFee);

            // 4. Resolve Customer Bank Account
            $bankAccountId = $request->input('bank_account_id');
            if (!$bankAccountId) {
                $customerBank = BankAccount::where('user_id', $user->id)
                    ->where('account_type', 'customer')
                    ->orderByDesc('is_primary')
                    ->latest()
                    ->first();
                $bankAccountId = optional($customerBank)->id;
            }

            // 5. Create or Fetch Existing Refund Record (Idempotent)
            $refund = Refund::firstOrCreate(
                [
                    'order_id' => $serviceOrder ? $serviceOrder->id : null,
                    'marketplace_order_id' => $marketplaceOrder ? $marketplaceOrder->id : null,
                ],
                [
                    'refund_no' => 'REF-' . str_pad($orderId, 6, '0', STR_PAD_LEFT),
                    'payment_id' => optional($payment)->id,
                    'customer_id' => $user->id,
                    'bank_account_id' => $bankAccountId,
                    'amount' => $refundAmount,
                    'currency' => $payment ? ($payment->currency ?: 'SAR') : 'SAR',
                    'status' => 'requested',
                    'gateway' => 'bank_transfer',
                    'admin_notes' => $request->input('reason') ?: null,
                    'requested_at' => now()->setTimezone('Asia/Riyadh'),
                ]
            );

            // Update bank account if provided and refund was already created
            if ($bankAccountId && (int) $refund->bank_account_id !== (int) $bankAccountId) {
                $refund->bank_account_id = $bankAccountId;
                $refund->save();
            }

            if ($serviceOrder) {
                $serviceOrder->refund_status = $refund->status;
                $serviceOrder->refund_id = $refund->id;
                $serviceOrder->save();
            }

            return response()->json([
                'success' => true,
                'message' => "Refund request submitted successfully. Net refund ({$refundAmount} SAR) after Azhl fee ({$azhlFee} SAR).",
                'data' => [
                    'refund_id' => (int) $refund->id,
                    'refund_no' => $refund->refund_no,
                    'order_id' => (int) $orderId,
                    'order_no' => 'ORD-' . str_pad($orderId, 6, '0', STR_PAD_LEFT),
                    'paid_amount' => $paidAmount,
                    'azhl_fee' => $azhlFee,
                    'refund_amount' => (float) $refund->amount,
                    'currency' => $refund->currency,
                    'status' => $refund->status,
                    'bank_account_id' => $refund->bank_account_id,
                    'refund_reference' => $refund->bank_reference ?: $refund->gateway_refund_id,
                    'requested_at' => $refund->created_at ? $refund->created_at->setTimezone('Asia/Riyadh')->toIso8601String() : null,
                ]
            ], 200);
        });
    }

    /**
     * Get Customer Refund Requests List API
     * GET /api/v1/customer/refunds
     * GET /api/v1/marketplace/refunds
     */
    public function getRefunds(Request $request)
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $customerOrderIds = Orders::where('user_id', $user->id)->pluck('id')->toArray();
        $customerMarketplaceOrderIds = MarketplaceOrder::where('user_id', $user->id)->pluck('id')->toArray();

        $refunds = Refund::with(['order.job.category', 'marketplaceOrder', 'bankAccount'])
            ->where(function ($q) use ($user, $customerOrderIds, $customerMarketplaceOrderIds) {
                $q->where('customer_id', $user->id)
                    ->orWhereIn('order_id', $customerOrderIds)
                    ->orWhereIn('marketplace_order_id', $customerMarketplaceOrderIds);
            })
            ->orderByDesc('id')
            ->get();

        $formatted = $refunds->map(function ($r) {
            $order = $r->order;
            $mkOrder = $r->marketplaceOrder;
            $job = optional($order)->job;
            $orderTitle = optional($job)->title ?: (optional(optional($job)->category)->name ?: ($mkOrder ? 'Marketplace Order Refund' : 'Cancelled Order Refund'));
            $orderId = $r->order_id ?: ($r->marketplace_order_id ?: 0);
            $orderNo = $mkOrder && $mkOrder->order_number ? $mkOrder->order_number : ($orderId ? ('ORD-' . str_pad($orderId, 6, '0', STR_PAD_LEFT)) : 'N/A');
            $rawStatus = strtolower($r->status ?: 'requested');

            $status = $rawStatus;
            if (in_array($rawStatus, ['refunded', 'completed', 'paid'])) {
                $status = 'completed';
            } elseif ($rawStatus === 'accepted') {
                $status = 'accepted';
            } elseif (in_array($rawStatus, ['rejected', 'failed'])) {
                $status = 'rejected';
            } else {
                $status = 'requested';
            }

            return [
                'refund_id' => (int) $r->id,
                'refund_no' => $r->refund_no ?: ('REF-' . str_pad($r->id, 6, '0', STR_PAD_LEFT)),
                'order_id' => (int) $orderId,
                'order_no' => $orderNo,
                'order_title' => $orderTitle,
                'amount' => round((float) ($r->amount ?? 0), 2),
                'currency' => strtoupper($r->currency ?: 'SAR'),
                'status' => $status,
                'refund_reference' => $r->bank_reference ?: $r->gateway_refund_id,
                'bank_account' => $r->bankAccount,
                'requested_at' => $r->created_at ? $r->created_at->setTimezone('Asia/Riyadh')->toIso8601String() : null,
                'accepted_at' => in_array($status, ['accepted', 'completed']) && $r->updated_at ? $r->updated_at->setTimezone('Asia/Riyadh')->toIso8601String() : null,
                'completed_at' => $status === 'completed' ? ($r->refunded_at ? $r->refunded_at->setTimezone('Asia/Riyadh')->toIso8601String() : ($r->updated_at ? $r->updated_at->setTimezone('Asia/Riyadh')->toIso8601String() : null)) : null,
                'rejected_at' => $status === 'rejected' ? ($r->failed_at ? $r->failed_at->setTimezone('Asia/Riyadh')->toIso8601String() : ($r->updated_at ? $r->updated_at->setTimezone('Asia/Riyadh')->toIso8601String() : null)) : null,
                'rejection_reason' => $status === 'rejected' ? ($r->failure_reason ?: $r->admin_notes) : null,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Customer refund requests retrieved successfully.',
            'data' => [
                'refunds' => $formatted,
                'total' => $formatted->count(),
            ]
        ]);
    }

    /**
     * Get Customer Single Refund Request Details API
     * GET /api/v1/customer/refunds/{id}
     */
    public function showRefund(Request $request, $id)
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $customerOrderIds = Orders::where('user_id', $user->id)->pluck('id')->toArray();
        $customerMarketplaceOrderIds = MarketplaceOrder::where('user_id', $user->id)->pluck('id')->toArray();

        $refund = Refund::with(['order.job.category', 'marketplaceOrder', 'bankAccount'])
            ->where('id', $id)
            ->where(function ($q) use ($user, $customerOrderIds, $customerMarketplaceOrderIds) {
                $q->where('customer_id', $user->id)
                    ->orWhereIn('order_id', $customerOrderIds)
                    ->orWhereIn('marketplace_order_id', $customerMarketplaceOrderIds);
            })
            ->first();

        if (!$refund) {
            return response()->json(['success' => false, 'message' => 'Refund request not found.'], 404);
        }

        $order = $refund->order;
        $mkOrder = $refund->marketplaceOrder;
        $job = optional($order)->job;
        $orderTitle = optional($job)->title ?: (optional(optional($job)->category)->name ?: ($mkOrder ? 'Marketplace Order Refund' : 'Cancelled Order Refund'));
        $orderId = $refund->order_id ?: ($refund->marketplace_order_id ?: 0);
        $orderNo = $mkOrder && $mkOrder->order_number ? $mkOrder->order_number : ($orderId ? ('ORD-' . str_pad($orderId, 6, '0', STR_PAD_LEFT)) : 'N/A');
        $rawStatus = strtolower($refund->status ?: 'requested');

        $status = $rawStatus;
        if (in_array($rawStatus, ['refunded', 'completed', 'paid'])) {
            $status = 'completed';
        } elseif ($rawStatus === 'accepted') {
            $status = 'accepted';
        } elseif (in_array($rawStatus, ['rejected', 'failed'])) {
            $status = 'rejected';
        } else {
            $status = 'requested';
        }

        return response()->json([
            'success' => true,
            'message' => 'Refund request details retrieved successfully.',
            'data' => [
                'refund_id' => (int) $refund->id,
                'refund_no' => $refund->refund_no ?: ('REF-' . str_pad($refund->id, 6, '0', STR_PAD_LEFT)),
                'order_id' => (int) $orderId,
                'order_no' => $orderNo,
                'order_title' => $orderTitle,
                'amount' => round((float) ($refund->amount ?? 0), 2),
                'currency' => strtoupper($refund->currency ?: 'SAR'),
                'status' => $status,
                'refund_reference' => $refund->bank_reference ?: $refund->gateway_refund_id,
                'bank_account' => $refund->bankAccount,
                'requested_at' => $refund->created_at ? $refund->created_at->setTimezone('Asia/Riyadh')->toIso8601String() : null,
                'accepted_at' => in_array($status, ['accepted', 'completed']) && $refund->updated_at ? $refund->updated_at->setTimezone('Asia/Riyadh')->toIso8601String() : null,
                'completed_at' => $status === 'completed' ? ($refund->refunded_at ? $refund->refunded_at->setTimezone('Asia/Riyadh')->toIso8601String() : ($refund->updated_at ? $refund->updated_at->setTimezone('Asia/Riyadh')->toIso8601String() : null)) : null,
                'rejected_at' => $status === 'rejected' ? ($refund->failed_at ? $refund->failed_at->setTimezone('Asia/Riyadh')->toIso8601String() : ($refund->updated_at ? $refund->updated_at->setTimezone('Asia/Riyadh')->toIso8601String() : null)) : null,
                'rejection_reason' => $status === 'rejected' ? ($refund->failure_reason ?: $refund->admin_notes) : null,
            ]
        ]);
    }
}
