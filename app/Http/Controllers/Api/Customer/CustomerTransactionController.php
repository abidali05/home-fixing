<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Orders;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Http\Request;

class CustomerTransactionController extends Controller
{
    /**
     * Customer Combined Paginated Transaction History API (Debit Spends & Credit Refunds)
     * GET /api/v1/customer/transactions?page=1&per_page=20&filter=all
     */
    public function transactionHistory(Request $request)
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $filter = strtolower($request->input('filter', 'all'));
        if (empty($filter) || $filter === 'null') {
            $filter = 'all';
        }

        // Fetch all customer orders & refunds for cross-referencing
        $customerOrderIds = Orders::where('user_id', $user->id)->pluck('id')->toArray();

        $allCustomerRefunds = Refund::where('customer_id', $user->id)
            ->orWhereIn('order_id', $customerOrderIds)
            ->get()
            ->keyBy('order_id');

        // 1. Calculate Financial Summary
        $capturedPaymentsSum = (float) Payment::where('user_id', $user->id)
            ->where('status', 'captured')
            ->sum('amount');

        if ($capturedPaymentsSum == 0) {
            $capturedPaymentsSum = (float) Orders::where('user_id', $user->id)
                ->where('paid_to_system', 1)
                ->sum('price');
        }

        $completedRefundsSum = (float) Refund::where(function($q) use ($user, $customerOrderIds) {
                $q->where('customer_id', $user->id)->orWhereIn('order_id', $customerOrderIds);
            })
            ->whereIn('status', ['refunded', 'completed', 'paid'])
            ->sum('amount');

        $pendingRefundsSum = (float) Refund::where(function($q) use ($user, $customerOrderIds) {
                $q->where('customer_id', $user->id)->orWhereIn('order_id', $customerOrderIds);
            })
            ->whereIn('status', ['requested', 'processing', 'pending', 'accepted'])
            ->sum('amount');

        $transactions = collect();
        $processedOrderIds = [];

        // 2. Debit Transactions (Customer Order Payments / Spends)
        if (in_array($filter, ['all', 'debit', 'payment', 'spend'])) {
            $payments = Payment::with(['job.category', 'marketplaceOrder'])
                ->where('user_id', $user->id)
                ->get();

            foreach ($payments as $p) {
                $job = $p->job;
                $mkOrder = $p->marketplaceOrder;
                $orderTitle = optional($job)->title ?: (optional(optional($job)->category)->name ?: ($mkOrder ? 'Marketplace Product Order' : 'Service Order'));
                $orderId = $job ? $job->id : ($mkOrder ? $mkOrder->id : $p->id);
                $orderNo = $mkOrder && $mkOrder->order_number ? $mkOrder->order_number : ('ORD-' . str_pad($orderId, 6, '0', STR_PAD_LEFT));

                $processedOrderIds[] = (int) $orderId;

                // Check if this order has an associated Refund Request
                $associatedRefund = $allCustomerRefunds->get($orderId);
                $refundData = null;

                if ($associatedRefund) {
                    $rawStatus = strtolower($associatedRefund->status ?: 'requested');
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

                    $refundData = [
                        'refund_id' => (int) $associatedRefund->id,
                        'refund_no' => $associatedRefund->refund_no ?: ('REF-' . str_pad($associatedRefund->id, 6, '0', STR_PAD_LEFT)),
                        'order_id' => (int) $orderId,
                        'order_no' => $orderNo,
                        'amount' => round((float) ($associatedRefund->amount ?? 0), 2),
                        'currency' => strtoupper($associatedRefund->currency ?: 'SAR'),
                        'status' => $status,
                        'refund_reference' => $associatedRefund->bank_reference ?: $associatedRefund->gateway_refund_id,
                        'requested_at' => $associatedRefund->created_at ? $associatedRefund->created_at->setTimezone('Asia/Riyadh')->toIso8601String() : null,
                        'accepted_at' => in_array($status, ['accepted', 'completed']) && $associatedRefund->updated_at ? $associatedRefund->updated_at->setTimezone('Asia/Riyadh')->toIso8601String() : null,
                        'completed_at' => $status === 'completed' ? ($associatedRefund->refunded_at ? $associatedRefund->refunded_at->setTimezone('Asia/Riyadh')->toIso8601String() : ($associatedRefund->updated_at ? $associatedRefund->updated_at->setTimezone('Asia/Riyadh')->toIso8601String() : null)) : null,
                        'rejected_at' => $status === 'rejected' ? ($associatedRefund->failed_at ? $associatedRefund->failed_at->setTimezone('Asia/Riyadh')->toIso8601String() : ($associatedRefund->updated_at ? $associatedRefund->updated_at->setTimezone('Asia/Riyadh')->toIso8601String() : null)) : null,
                        'rejection_reason' => $status === 'rejected' ? ($associatedRefund->failure_reason ?: $associatedRefund->admin_notes) : null,
                    ];
                }

                $transactions->push([
                    'id' => (int) $p->id,
                    'type' => 'debit',
                    'label' => 'Order Payment',
                    'amount' => round((float) ($p->amount ?? 0), 2),
                    'currency' => strtoupper($p->currency ?: 'SAR'),
                    'created_at' => $p->created_at ? $p->created_at->setTimezone('Asia/Riyadh')->toIso8601String() : null,
                    'payment' => [
                        'payment_id' => (int) $p->id,
                        'order_id' => (int) $orderId,
                        'order_no' => $orderNo,
                        'order_title' => $orderTitle,
                        'status' => strtolower($p->status ?: 'captured'),
                        'tap_charge_id' => $p->tap_charge_id ?: null,
                        'paid_at' => $p->created_at ? $p->created_at->setTimezone('Asia/Riyadh')->toIso8601String() : null,
                    ],
                    'refund' => $refundData,
                ]);
            }

            // Fallback for Customer Paid Orders not in Payment table
            $paidOrders = Orders::with(['job.category'])
                ->where('user_id', $user->id)
                ->where('paid_to_system', 1)
                ->get();

            foreach ($paidOrders as $ord) {
                if (in_array((int) $ord->id, $processedOrderIds)) {
                    continue;
                }

                $job = $ord->job;
                $orderTitle = optional($job)->title ?: (optional(optional($job)->category)->name ?: 'AC Repair Service');

                $associatedRefund = $allCustomerRefunds->get($ord->id);
                $refundData = null;

                if ($associatedRefund) {
                    $rawStatus = strtolower($associatedRefund->status ?: 'requested');
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

                    $refundData = [
                        'refund_id' => (int) $associatedRefund->id,
                        'refund_no' => $associatedRefund->refund_no ?: ('REF-' . str_pad($associatedRefund->id, 6, '0', STR_PAD_LEFT)),
                        'order_id' => (int) $ord->id,
                        'order_no' => 'ORD-' . str_pad($ord->id, 6, '0', STR_PAD_LEFT),
                        'amount' => round((float) ($associatedRefund->amount ?? 0), 2),
                        'currency' => strtoupper($associatedRefund->currency ?: 'SAR'),
                        'status' => $status,
                        'refund_reference' => $associatedRefund->bank_reference ?: $associatedRefund->gateway_refund_id,
                        'requested_at' => $associatedRefund->created_at ? $associatedRefund->created_at->setTimezone('Asia/Riyadh')->toIso8601String() : null,
                        'accepted_at' => in_array($status, ['accepted', 'completed']) && $associatedRefund->updated_at ? $associatedRefund->updated_at->setTimezone('Asia/Riyadh')->toIso8601String() : null,
                        'completed_at' => $status === 'completed' ? ($associatedRefund->refunded_at ? $associatedRefund->refunded_at->setTimezone('Asia/Riyadh')->toIso8601String() : ($associatedRefund->updated_at ? $associatedRefund->updated_at->setTimezone('Asia/Riyadh')->toIso8601String() : null)) : null,
                        'rejected_at' => $status === 'rejected' ? ($associatedRefund->failed_at ? $associatedRefund->failed_at->setTimezone('Asia/Riyadh')->toIso8601String() : ($associatedRefund->updated_at ? $associatedRefund->updated_at->setTimezone('Asia/Riyadh')->toIso8601String() : null)) : null,
                        'rejection_reason' => $status === 'rejected' ? ($associatedRefund->failure_reason ?: $associatedRefund->admin_notes) : null,
                    ];
                }

                $transactions->push([
                    'id' => (int) (700000 + $ord->id),
                    'type' => 'debit',
                    'label' => 'Order Payment',
                    'amount' => round((float) ($ord->price ?? 0), 2),
                    'currency' => 'SAR',
                    'created_at' => $ord->created_at ? $ord->created_at->setTimezone('Asia/Riyadh')->toIso8601String() : null,
                    'payment' => [
                        'payment_id' => (int) (700000 + $ord->id),
                        'order_id' => (int) $ord->id,
                        'order_no' => 'ORD-' . str_pad($ord->id, 6, '0', STR_PAD_LEFT),
                        'order_title' => $orderTitle,
                        'status' => strtolower($ord->status ?: 'completed'),
                        'tap_charge_id' => null,
                        'paid_at' => $ord->created_at ? $ord->created_at->setTimezone('Asia/Riyadh')->toIso8601String() : null,
                    ],
                    'refund' => $refundData,
                ]);
            }
        }

        // 3. Credit Transactions (Customer Refund Requests & Settled Refunds)
        if (in_array($filter, ['all', 'credit', 'refund'])) {
            foreach ($allCustomerRefunds as $refundItem) {
                $order = $refundItem->order;
                $job = optional($order)->job;
                $orderTitle = optional($job)->title ?: (optional(optional($job)->category)->name ?: 'Cancelled Order Refund');
                $rawStatus = strtolower($refundItem->status ?: 'requested');

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

                $transactions->push([
                    'id' => (int) (500000 + $refundItem->id),
                    'type' => 'credit',
                    'label' => 'Refund Credit',
                    'amount' => round((float) ($refundItem->amount ?? 0), 2),
                    'currency' => strtoupper($refundItem->currency ?: 'SAR'),
                    'created_at' => $refundItem->created_at ? $refundItem->created_at->setTimezone('Asia/Riyadh')->toIso8601String() : null,
                    'payment' => null,
                    'refund' => [
                        'refund_id' => (int) $refundItem->id,
                        'refund_no' => $refundItem->refund_no ?: ('REF-' . str_pad($refundItem->id, 6, '0', STR_PAD_LEFT)),
                        'order_id' => (int) ($refundItem->order_id ?: 0),
                        'order_no' => $refundItem->order_id ? ('ORD-' . str_pad($refundItem->order_id, 6, '0', STR_PAD_LEFT)) : 'N/A',
                        'order_title' => $orderTitle,
                        'status' => $status,
                        'refund_reference' => $refundItem->bank_reference ?: $refundItem->gateway_refund_id,
                        'requested_at' => $refundItem->created_at ? $refundItem->created_at->setTimezone('Asia/Riyadh')->toIso8601String() : null,
                        'accepted_at' => in_array($status, ['accepted', 'completed']) && $refundItem->updated_at ? $refundItem->updated_at->setTimezone('Asia/Riyadh')->toIso8601String() : null,
                        'completed_at' => $status === 'completed' ? ($refundItem->refunded_at ? $refundItem->refunded_at->setTimezone('Asia/Riyadh')->toIso8601String() : ($refundItem->updated_at ? $refundItem->updated_at->setTimezone('Asia/Riyadh')->toIso8601String() : null)) : null,
                        'rejected_at' => $status === 'rejected' ? ($refundItem->failed_at ? $refundItem->failed_at->setTimezone('Asia/Riyadh')->toIso8601String() : ($refundItem->updated_at ? $refundItem->updated_at->setTimezone('Asia/Riyadh')->toIso8601String() : null)) : null,
                        'rejection_reason' => $status === 'rejected' ? ($refundItem->failure_reason ?: $refundItem->admin_notes) : null,
                    ]
                ]);
            }
        }

        $sorted = $transactions->sortByDesc('created_at')->values();

        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 20);
        $total = $sorted->count();
        $lastPage = (int) ceil($total / $perPage) ?: 1;
        $offset = ($page - 1) * $perPage;

        $paginated = $sorted->slice($offset, $perPage)->values();

        return response()->json([
            'success' => true,
            'message' => 'Customer transactions retrieved successfully.',
            'data' => [
                'summary' => [
                    'total_debit' => round($capturedPaymentsSum, 2),
                    'total_credit' => round($completedRefundsSum, 2),
                    'pending_refunds' => round($pendingRefundsSum, 2),
                    'currency' => 'SAR',
                ],
                'transactions' => $paginated,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'last_page' => $lastPage,
                    'total' => $total,
                    'from' => $total > 0 ? $offset + 1 : 0,
                    'to' => min($offset + $perPage, $total),
                    'has_more' => $page < $lastPage,
                ]
            ]
        ]);
    }
}
