<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Admin\SystemSettingModel;
use App\Models\BankAccount;
use App\Models\Orders;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CustomerRefundController extends Controller
{
    /**
     * Standalone Customer Refund Request API for Cancelled Orders
     * POST /api/v1/customer/refunds/request
     * POST /api/v1/orders/{order_id}/refund
     * POST /api/v1/refunds/request
     */
    public function requestRefund(Request $request, $order_id = null)
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $orderId = $order_id ?: $request->input('order_id');

        $validator = Validator::make(array_merge($request->all(), ['order_id' => $orderId]), [
            'order_id' => 'required|exists:orders,id',
            'bank_account_id' => 'nullable|exists:bank_accounts,id',
            'reason' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 422);
        }

        return DB::transaction(function () use ($request, $user, $orderId) {
            $order = Orders::where('id', $orderId)->lockForUpdate()->first();

            if (!$order) {
                return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
            }

            // 1. Check Customer Authorization
            if ((int) $order->user_id !== (int) $user->id && (int) $user->role !== 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to request a refund for this order.'
                ], 403);
            }

            // 2. Validate Order Status is Cancelled
            $status = strtolower($order->status);
            if ($status !== 'cancelled') {
                return response()->json([
                    'success' => false,
                    'message' => 'Refunds can only be requested for cancelled orders. Current order status: ' . $order->status
                ], 422);
            }

            // 3. Calculate Azhl System Fee & Net Customer Refund
            $settings = SystemSettingModel::first();
            $azhlFee = (float) ($settings->azhl_fee ?? 5.00);

            $payment = Payment::where('job_id', $order->job_id)
                ->orWhere('id', $order->id)
                ->where('status', 'captured')
                ->latest()
                ->first();

            $paidAmount = $payment ? (float) $payment->amount : ((float) ($order->price ?? 0));
            $isPaid = $payment || (int) $order->paid_to_system === 1;

            if (!$isPaid || $paidAmount <= 0) {
                $order->refund_status = 'not_required';
                $order->save();

                return response()->json([
                    'success' => true,
                    'message' => 'No captured payment found for this order. Refund is not required.',
                    'data' => [
                        'order_id' => (int) $order->id,
                        'order_no' => 'ORD-' . str_pad($order->id, 6, '0', STR_PAD_LEFT),
                        'order_status' => 'cancelled',
                        'refund' => [
                            'status' => 'not_required',
                            'amount' => 0.00
                        ]
                    ]
                ], 200);
            }

            $refundAmount = max(0, $paidAmount - $azhlFee);

            // 4. Resolve Bank Account
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
                ['order_id' => $order->id],
                [
                    'refund_no' => 'REF-' . str_pad($order->id, 6, '0', STR_PAD_LEFT),
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

            $order->refund_status = $refund->status;
            $order->refund_id = $refund->id;
            $order->save();

            return response()->json([
                'success' => true,
                'message' => "Refund request submitted successfully. Net refund ({$refundAmount} SAR) after Azhl fee ({$azhlFee} SAR).",
                'data' => [
                    'refund_id' => (int) $refund->id,
                    'refund_no' => $refund->refund_no,
                    'order_id' => (int) $order->id,
                    'order_no' => 'ORD-' . str_pad($order->id, 6, '0', STR_PAD_LEFT),
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
     * GET /api/v1/refunds
     */
    public function getRefunds(Request $request)
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $customerOrderIds = Orders::where('user_id', $user->id)->pluck('id')->toArray();

        $refunds = Refund::with(['order.job.category', 'bankAccount'])
            ->where(function ($q) use ($user, $customerOrderIds) {
                $q->where('customer_id', $user->id)->orWhereIn('order_id', $customerOrderIds);
            })
            ->orderByDesc('id')
            ->get();

        $formatted = $refunds->map(function ($r) {
            $order = $r->order;
            $job = optional($order)->job;
            $orderTitle = optional($job)->title ?: (optional(optional($job)->category)->name ?: 'Cancelled Order Refund');
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
                'order_id' => (int) ($r->order_id ?: 0),
                'order_no' => $r->order_id ? ('ORD-' . str_pad($r->order_id, 6, '0', STR_PAD_LEFT)) : 'N/A',
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

        $refund = Refund::with(['order.job.category', 'bankAccount'])
            ->where('id', $id)
            ->where(function ($q) use ($user, $customerOrderIds) {
                $q->where('customer_id', $user->id)->orWhereIn('order_id', $customerOrderIds);
            })
            ->first();

        if (!$refund) {
            return response()->json(['success' => false, 'message' => 'Refund request not found.'], 404);
        }

        $order = $refund->order;
        $job = optional($order)->job;
        $orderTitle = optional($job)->title ?: (optional(optional($job)->category)->name ?: 'Cancelled Order Refund');
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
                'order_id' => (int) ($refund->order_id ?: 0),
                'order_no' => $refund->order_id ? ('ORD-' . str_pad($refund->order_id, 6, '0', STR_PAD_LEFT)) : 'N/A',
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
