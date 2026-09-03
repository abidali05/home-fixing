<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Orders;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CustomerTransactionController extends Controller
{
    /**
     * Helper to calculate itemized customer refund breakdown
     */
    private function calculateCustomerRefundBreakdown($order): array
    {
        $settings = \App\Models\Admin\SystemSettingModel::first();
        $customerAppFee = (float) ($settings->customer_app_fee ?? 3.00);
        $gatewayFeePct = (float) ($settings->payment_gateway_fee_percentage ?? 2.50);
        $gatewayFixedFee = (float) ($settings->payment_gateway_fixed_fee ?? 1.00);
        $gatewayVatPct = (float) ($settings->payment_gateway_vat_percentage ?? 15.00);

        $repairPrice = (float) ($order->price ?? 0);
        if ($order && !empty($order->job_id)) {
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
        $customerTotal = $repairPrice + $customerAppFee + $totalGatewayFee;

        $totalDeducted = $customerAppFee + $totalGatewayFee;
        $netRefund = $repairPrice;

        return [
            'paid_amount' => number_format($customerTotal, 2, '.', ''),
            'bid_price' => number_format($repairPrice, 2, '.', ''),
            'customer_app_fee' => number_format($customerAppFee, 2, '.', ''),
            'gateway_fee' => number_format($totalGatewayFee, 2, '.', ''),
            'total_deducted' => number_format($totalDeducted, 2, '.', ''),
            'net_refund_amount' => number_format($netRefund, 2, '.', ''),
            'amount' => number_format($netRefund, 2, '.', ''),
        ];
    }

    /**
     * Customer Combined Paginated Transaction History API (Debit Spends & Credit Refunds)
     * GET /api/v1/customer/transactions?page=1&per_page=20&filter=all
     */
    public function transactionHistory(Request $request)
    {
        try {
            $user = auth('sanctum')->user();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            }

            $filter = strtolower($request->input('filter', 'all'));
            if (empty($filter) || $filter === 'null') {
                $filter = 'all';
            }

            // Fetch all customer orders & refunds for fast cross-referencing
            $customerOrders = Orders::with(['job.category'])
                ->where('user_id', $user->id)
                ->get();

            $customerOrderIds = $customerOrders->pluck('id')->toArray();
            $customerJobIds = $customerOrders->pluck('job_id')->filter()->toArray();

            $allCustomerRefunds = Refund::where('customer_id', $user->id)
                ->orWhereIn('order_id', $customerOrderIds)
                ->get()
                ->keyBy('order_id');

            $transactions = collect();
            $processedOrderIds = [];
            $processedJobIds = [];

            // 1. Debit Transactions (Actual Customer Payments / Spends)
            if (in_array($filter, ['all', 'debit', 'payment', 'spend'])) {
                $payments = Payment::with(['job.category', 'marketplaceOrder'])
                    ->where('user_id', $user->id)
                    ->whereIn('status', ['captured', 'completed', 'paid', 'success'])
                    ->get();

                foreach ($payments as $p) {
                    // Find actual Order model for this payment
                    $order = null;
                    if ($p->job_id) {
                        $order = $customerOrders->where('job_id', $p->job_id)->first();
                    }
                    if (!$order && $p->marketplace_order_id) {
                        $order = $customerOrders->where('id', $p->marketplace_order_id)->first();
                    }

                    $job = $p->job ?: optional($order)->job;
                    $mkOrder = $p->marketplaceOrder;

                    $orderId = $order ? $order->id : ($job ? $job->id : ($mkOrder ? $mkOrder->id : $p->id));
                    $orderNo = $order ? ('ORD-' . str_pad($order->id, 6, '0', STR_PAD_LEFT)) : ($mkOrder && $mkOrder->order_number ? $mkOrder->order_number : ('ORD-' . str_pad($orderId, 6, '0', STR_PAD_LEFT)));
                    $orderTitle = optional($job)->title ?: (optional(optional($job)->category)->name ?: ($mkOrder ? 'Marketplace Product Order' : 'Service Order'));

                    if ($order) {
                        $processedOrderIds[] = (int) $order->id;
                    }
                    if ($p->job_id) {
                        $processedJobIds[] = (int) $p->job_id;
                    }

                    // Check if this order has an associated Refund Request
                    $associatedRefund = $order ? $allCustomerRefunds->get($order->id) : null;
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

                        $breakdown = $this->calculateCustomerRefundBreakdown($order);

                        $refundData = [
                            'refund_id' => (int) $associatedRefund->id,
                            'refund_no' => $associatedRefund->refund_no ?: ('REF-' . str_pad($associatedRefund->id, 6, '0', STR_PAD_LEFT)),
                            'order_id' => (int) ($order ? $order->id : 0),
                            'order_no' => $orderNo,
                            'paid_amount' => $breakdown['paid_amount'],
                            'bid_price' => $breakdown['bid_price'],
                            'customer_app_fee' => $breakdown['customer_app_fee'],
                            'gateway_fee' => $breakdown['gateway_fee'],
                            'total_deducted' => $breakdown['total_deducted'],
                            'net_refund_amount' => $breakdown['net_refund_amount'],
                            'amount' => $breakdown['amount'],
                            'currency' => strtoupper($associatedRefund->currency ?: 'SAR'),
                            'status' => $status,
                            'breakdown' => [
                                'paid_amount' => $breakdown['paid_amount'],
                                'bid_price' => $breakdown['bid_price'],
                                'customer_app_fee' => $breakdown['customer_app_fee'],
                                'gateway_fee' => $breakdown['gateway_fee'],
                                'total_deducted' => $breakdown['total_deducted'],
                                'net_refund_amount' => $breakdown['net_refund_amount'],
                            ],
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
                        'amount' => number_format((float) ($p->amount ?? 0), 2, '.', ''),
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

                // Fallback for Customer Paid Orders with paid_to_system = 1 NOT in Payment table
                $paidOrders = $customerOrders->where('paid_to_system', 1);

                foreach ($paidOrders as $ord) {
                    if (in_array((int) $ord->id, $processedOrderIds) || ($ord->job_id && in_array((int) $ord->job_id, $processedJobIds))) {
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

                        $breakdown = $this->calculateCustomerRefundBreakdown($ord);

                        $refundData = [
                            'refund_id' => (int) $associatedRefund->id,
                            'refund_no' => $associatedRefund->refund_no ?: ('REF-' . str_pad($associatedRefund->id, 6, '0', STR_PAD_LEFT)),
                            'order_id' => (int) $ord->id,
                            'order_no' => 'ORD-' . str_pad($ord->id, 6, '0', STR_PAD_LEFT),
                            'paid_amount' => $breakdown['paid_amount'],
                            'bid_price' => $breakdown['bid_price'],
                            'customer_app_fee' => $breakdown['customer_app_fee'],
                            'gateway_fee' => $breakdown['gateway_fee'],
                            'total_deducted' => $breakdown['total_deducted'],
                            'net_refund_amount' => $breakdown['net_refund_amount'],
                            'amount' => $breakdown['amount'],
                            'currency' => strtoupper($associatedRefund->currency ?: 'SAR'),
                            'status' => $status,
                            'breakdown' => [
                                'paid_amount' => $breakdown['paid_amount'],
                                'bid_price' => $breakdown['bid_price'],
                                'customer_app_fee' => $breakdown['customer_app_fee'],
                                'gateway_fee' => $breakdown['gateway_fee'],
                                'total_deducted' => $breakdown['total_deducted'],
                                'net_refund_amount' => $breakdown['net_refund_amount'],
                            ],
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
                        'amount' => number_format((float) ($ord->price ?? 0), 2, '.', ''),
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

            // 2. Credit Transactions (Customer Refund Requests & Settled Refunds)
            if (in_array($filter, ['all', 'credit', 'refund'])) {
                foreach ($allCustomerRefunds as $refundItem) {
                    $order = $refundItem->order ?: $customerOrders->where('id', $refundItem->order_id)->first();
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

                    $breakdown = $this->calculateCustomerRefundBreakdown($order);

                    $transactions->push([
                        'id' => (int) (500000 + $refundItem->id),
                        'type' => 'credit',
                        'label' => 'Refund Credit',
                        'amount' => $breakdown['amount'],
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
                            'paid_amount' => $breakdown['paid_amount'],
                            'bid_price' => $breakdown['bid_price'],
                            'customer_app_fee' => $breakdown['customer_app_fee'],
                            'gateway_fee' => $breakdown['gateway_fee'],
                            'total_deducted' => $breakdown['total_deducted'],
                            'net_refund_amount' => $breakdown['net_refund_amount'],
                            'amount' => $breakdown['amount'],
                            'currency' => strtoupper($refundItem->currency ?: 'SAR'),
                            'breakdown' => [
                                'paid_amount' => $breakdown['paid_amount'],
                                'bid_price' => $breakdown['bid_price'],
                                'customer_app_fee' => $breakdown['customer_app_fee'],
                                'gateway_fee' => $breakdown['gateway_fee'],
                                'total_deducted' => $breakdown['total_deducted'],
                                'net_refund_amount' => $breakdown['net_refund_amount'],
                            ],
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

            // Sort by created_at descending
            $sorted = $transactions->sortByDesc('created_at')->values();

            // Recalculate accurate financial summary totals from valid transactions
            $totalDebitSum = $sorted->where('type', 'debit')->sum('amount');
            $totalCreditSum = $sorted->where('type', 'credit')->filter(function ($t) {
                return isset($t['refund']['status']) && $t['refund']['status'] === 'completed';
            })->sum('amount');

            $pendingRefundsSum = $sorted->where('type', 'credit')->filter(function ($t) {
                return isset($t['refund']['status']) && in_array($t['refund']['status'], ['requested', 'accepted']);
            })->sum('amount');

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
                        'total_debit' => round($totalDebitSum, 2),
                        'total_credit' => round($totalCreditSum, 2),
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
        } catch (\Throwable $e) {
            Log::error('Error in CustomerTransactionController: ' . $e->getMessage() . ' at line ' . $e->getLine() . ' in ' . $e->getFile());

            return response()->json([
                'success' => false,
                'message' => 'Failed to load customer transactions: ' . $e->getMessage(),
                'error' => [
                    'line' => $e->getLine(),
                    'file' => basename($e->getFile()),
                ]
            ], 500);
        }
    }
}
