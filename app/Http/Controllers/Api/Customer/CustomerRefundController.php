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
}
