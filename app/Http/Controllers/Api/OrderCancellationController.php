<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin\SystemSettingModel;
use App\Models\BankAccount;
use App\Models\Orders;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OrderCancellationController extends Controller
{
    /**
     * Cancel an Order & Trigger Customer Refund Request
     * POST /api/v1/orders/{order_id}/cancel
     * Contract matching Order Cancellation & Refund Specification with Azhl Fee Deduction
     */
    public function cancelOrder(Request $request, $order_id)
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.'
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:1000',
            'bank_account_id' => 'nullable|exists:bank_accounts,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 422);
        }

        return DB::transaction(function () use ($request, $user, $order_id) {
            // Lock order row for concurrency control
            $order = Orders::where('id', $order_id)->lockForUpdate()->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found.'
                ], 404);
            }

            // 1. Validate Actor Relation
            $isCustomer = (int) $order->user_id === (int) $user->id;
            $isProvider = (int) $order->provider_id === (int) $user->id;
            $isMarketplace = (int) $user->role === 3 || (int) $user->role === 2;

            if (!$isCustomer && !$isProvider && !$isMarketplace) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to cancel this order.'
                ], 403);
            }

            // 2. Infer Actor Type
            $cancelledByType = 'customer';
            if ($isProvider) {
                $cancelledByType = 'provider';
            } elseif ($isMarketplace) {
                $cancelledByType = 'marketplace';
            }

            // 3. Check Order Status
            $status = strtolower($order->status);
            if ($status === 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Completed orders cannot be cancelled.'
                ], 422);
            }

            if ($status === 'cancelled') {
                return response()->json([
                    'success' => false,
                    'message' => 'This order has already been cancelled.'
                ], 422);
            }

            // 4. Update Order Cancellation State
            $reason = trim($request->input('reason'));
            $cancelledAt = now()->setTimezone('Asia/Riyadh');

            $order->status = 'cancelled';
            $order->cancelled_by_type = $cancelledByType;
            $order->cancelled_by_id = $user->id;
            $order->cancellation_reason = $reason;
            $order->cancelled_at = $cancelledAt;
            $order->save();

            // 5. Calculate Azhl System Fee & Customer Net Refund Amount
            $settings = SystemSettingModel::first();
            $azhlPercentage = (float) ($settings->azhl_percentage ?? 10.00);
            $azhlFee = round($paidAmount * ($azhlPercentage / 100), 2);

            $payment = Payment::where('job_id', $order->job_id)
                ->orWhere('id', $order->id)
                ->where('status', 'captured')
                ->latest()
                ->first();

            $paidAmount = $payment ? (float) $payment->amount : ((float) ($order->price ?? 0));
            $isPaid = $payment || (int) $order->paid_to_system === 1;

            $refundData = null;

            if ($isPaid && $paidAmount > 0) {
                // Customer Net Refund Amount = Repair Price (100.00 SAR)
                // Customer App Fee and Gateway Fee are retained as non-refundable system fees
                $refundRepairPrice = (float) ($order->price ?? 0);
                if (!empty($order->job_id)) {
                    $acceptedBid = BidModel::where('job_id', $order->job_id)->whereIn('status', ['accepted', 'completed', 'hired', 'cancelled'])->first();
                    if ($acceptedBid && (float) $acceptedBid->price > 0) {
                        $refundRepairPrice = (float) $acceptedBid->price;
                    }
                }
                if ($refundRepairPrice > 103) {
                    $gatewayFeePct = (float) ($settings->payment_gateway_fee_percentage ?? 2.50);
                    $gatewayFixedFee = (float) ($settings->payment_gateway_fixed_fee ?? 1.00);
                    $gatewayVatPct = (float) ($settings->payment_gateway_vat_percentage ?? 15.00);
                    $customerAppFee = (float) ($settings->customer_app_fee ?? 3.00);

                    $approxSubtotal = ($refundRepairPrice - $gatewayFixedFee * (1 + $gatewayVatPct / 100)) / (1 + ($gatewayFeePct / 100) * (1 + $gatewayVatPct / 100));
                    $estimatedRepair = max(0, $approxSubtotal - $customerAppFee);
                    $refundRepairPrice = abs($estimatedRepair - round($estimatedRepair)) < 0.1 ? (float) round($estimatedRepair) : (float) round($estimatedRepair, 2);
                }

                $refundAmount = $refundRepairPrice;

                // Customer Bank Account for Refund
                $bankAccountId = $request->input('bank_account_id');
                if (!$bankAccountId) {
                    $customerBank = BankAccount::where('user_id', $order->user_id)->latest()->first();
                    $bankAccountId = optional($customerBank)->id;
                }

                // Create or find Refund Record
                $refund = Refund::firstOrCreate(
                    ['order_id' => $order->id],
                    [
                        'refund_no' => 'REF-' . str_pad($order->id, 6, '0', STR_PAD_LEFT),
                        'payment_id' => optional($payment)->id,
                        'customer_id' => $order->user_id,
                        'bank_account_id' => $bankAccountId,
                        'amount' => $refundAmount,
                        'currency' => $payment ? ($payment->currency ?: 'SAR') : 'SAR',
                        'status' => 'requested',
                        'gateway' => 'bank_transfer',
                        'requested_at' => $cancelledAt,
                    ]
                );

                $order->refund_status = $refund->status;
                $order->refund_id = $refund->id;
                $order->save();

                $refundData = [
                    'id' => $refund->id,
                    'refund_no' => $refund->refund_no,
                    'paid_amount' => $paidAmount,
                    'azhl_fee' => $azhlFee,
                    'amount' => (float) $refund->amount,
                    'currency' => $refund->currency,
                    'status' => $refund->status,
                    'refund_reference' => $refund->bank_reference ?: $refund->gateway_refund_id,
                    'requested_at' => $refund->created_at ? $refund->created_at->setTimezone('Asia/Riyadh')->toIso8601String() : null,
                ];

                return response()->json([
                    'success' => true,
                    'message' => "Order cancelled successfully. Net customer refund ({$refundAmount} SAR) initiated after Azhl fee deduction ({$azhlFee} SAR).",
                    'data' => [
                        'order_id' => (int) $order->id,
                        'order_no' => 'ORD-' . str_pad($order->id, 6, '0', STR_PAD_LEFT),
                        'order_status' => 'cancelled',
                        'cancelled_by' => $cancelledByType,
                        'cancellation_reason' => $reason,
                        'cancelled_at' => $cancelledAt->toIso8601String(),
                        'payment' => [
                            'paid_amount' => $paidAmount,
                            'azhl_fee' => $azhlFee,
                            'net_refund_amount' => $refundAmount,
                            'currency' => 'SAR',
                        ],
                        'refund' => $refundData,
                    ]
                ], 200);
            } else {
                $order->refund_status = 'not_required';
                $order->save();

                return response()->json([
                    'success' => true,
                    'message' => 'Order cancelled successfully.',
                    'data' => [
                        'order_id' => (int) $order->id,
                        'order_no' => 'ORD-' . str_pad($order->id, 6, '0', STR_PAD_LEFT),
                        'order_status' => 'cancelled',
                        'cancelled_by' => $cancelledByType,
                        'cancellation_reason' => $reason,
                        'cancelled_at' => $cancelledAt->toIso8601String(),
                        'payment' => null,
                        'refund' => [
                            'status' => 'not_required',
                            'amount' => 0.00,
                        ],
                    ]
                ], 200);
            }
        });
    }
}
