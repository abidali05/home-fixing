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
     * Customer Combined Paginated Transaction History API (Spends & Refunds)
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

        // Summary Calculations
        $capturedPaymentsSum = (float) Payment::where('user_id', $user->id)
            ->where('status', 'captured')
            ->sum('amount');

        $completedRefundsSum = (float) Refund::where('customer_id', $user->id)
            ->whereIn('status', ['refunded', 'completed', 'paid'])
            ->sum('amount');

        $pendingRefundsSum = (float) Refund::where('customer_id', $user->id)
            ->whereIn('status', ['requested', 'processing', 'pending', 'accepted'])
            ->sum('amount');

        $transactions = collect();

        // 1. Debit Transactions (Customer Payments / Spends)
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
                        'status' => $p->status ?: 'captured',
                        'tap_charge_id' => $p->tap_charge_id ?: null,
                        'paid_at' => $p->created_at ? $p->created_at->setTimezone('Asia/Riyadh')->toIso8601String() : null,
                    ],
                    'refund' => null,
                ]);
            }
        }

        // 2. Refund Credit Transactions (Customer Refunds)
        if (in_array($filter, ['all', 'refund', 'credit'])) {
            $refunds = Refund::with(['order.job.category', 'bankAccount'])
                ->where('customer_id', $user->id)
                ->get();

            foreach ($refunds as $r) {
                $order = $r->order;
                $job = optional($order)->job;
                $orderTitle = optional($job)->title ?: (optional(optional($job)->category)->name ?: 'Cancelled Order Refund');
                $status = strtolower($r->status ?: 'requested');

                $transactions->push([
                    'id' => (int) (500000 + $r->id),
                    'type' => 'refund',
                    'label' => 'Order Refund',
                    'amount' => round((float) ($r->amount ?? 0), 2),
                    'currency' => strtoupper($r->currency ?: 'SAR'),
                    'created_at' => $r->created_at ? $r->created_at->setTimezone('Asia/Riyadh')->toIso8601String() : null,
                    'payment' => null,
                    'refund' => [
                        'refund_id' => (int) $r->id,
                        'refund_no' => $r->refund_no ?: ('REF-' . str_pad($r->id, 6, '0', STR_PAD_LEFT)),
                        'order_id' => (int) ($r->order_id ?: 0),
                        'order_no' => $r->order_id ? ('ORD-' . str_pad($r->order_id, 6, '0', STR_PAD_LEFT)) : 'N/A',
                        'order_title' => $orderTitle,
                        'status' => $status,
                        'refund_reference' => $r->bank_reference ?: $r->gateway_refund_id,
                        'requested_at' => $r->created_at ? $r->created_at->setTimezone('Asia/Riyadh')->toIso8601String() : null,
                        'accepted_at' => in_array($status, ['accepted', 'refunded', 'completed', 'paid']) && $r->updated_at ? $r->updated_at->setTimezone('Asia/Riyadh')->toIso8601String() : null,
                        'refunded_at' => in_array($status, ['refunded', 'completed', 'paid']) && $r->refunded_at ? $r->refunded_at->setTimezone('Asia/Riyadh')->toIso8601String() : null,
                        'rejected_at' => in_array($status, ['rejected', 'failed']) && $r->failed_at ? $r->failed_at->setTimezone('Asia/Riyadh')->toIso8601String() : null,
                        'rejection_reason' => in_array($status, ['rejected', 'failed']) ? ($r->failure_reason ?: $r->admin_notes) : null,
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
                    'total_spent' => round($capturedPaymentsSum, 2),
                    'total_refunded' => round($completedRefundsSum, 2),
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
