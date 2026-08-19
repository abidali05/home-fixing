<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin\SystemSettingModel;
use App\Models\BankAccount;
use App\Models\Orders;
use App\Models\Payment;
use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WithdrawalController extends Controller
{
    /**
     * Provider / Marketplace Wallet Summary API
     * Contract matching Page 6 of Specification Doc
     */
    public function walletSummary(Request $request)
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $accountType = $request->input('account_type', 'provider');
        if (!in_array($accountType, ['provider', 'marketplace'])) {
            $accountType = 'provider';
        }

        $settings = SystemSettingModel::first();
        $azhlFeePerOrder = (float) ($settings->azhl_fee ?? 5.00);

        if ($accountType === 'provider') {
            // 1. Pending Amount: Orders from orders table or payments table that are pending/in-progress
            $pendingOrdersPrice = (float) Orders::where('provider_id', $user->id)
                ->whereIn('status', ['open', 'pending', 'accepted', 'on_the_way', 'arrived', 'working', 'provider_completed', 'quoted'])
                ->sum('price');

            $pendingPaymentsAmount = (float) Payment::where('provider_id', $user->id)
                ->whereIn('status', ['pending', 'processing', 'initiated'])
                ->whereDoesntHave('job', function ($q) {
                    $q->whereIn('status', ['completed', 'finished', 'accepted']);
                })
                ->sum('amount');

            $pendingAmount = max($pendingOrdersPrice, $pendingPaymentsAmount);

            // 2. Completed Orders & Net Total Earnings
            // Fetch completed orders from Orders table
            $completedOrders = Orders::with(['job.category'])
                ->where('provider_id', $user->id)
                ->where('status', 'completed')
                ->get();

            // Also check captured payments if not already in completedOrders
            $capturedPayments = Payment::where('provider_id', $user->id)
                ->where('status', 'captured')
                ->get();

            $completedMap = collect();

            foreach ($completedOrders as $ord) {
                $gross = (float) ($ord->price ?? 0);
                $key = 'order_' . $ord->id;
                $completedMap->put($key, [
                    'gross' => $gross,
                    'net' => max(0, $gross - $azhlFeePerOrder),
                ]);
            }

            foreach ($capturedPayments as $pay) {
                $gross = (float) ($pay->amount ?? 0);
                $key = 'job_' . $pay->job_id;
                if (!$completedMap->has($key)) {
                    $completedMap->put($key, [
                        'gross' => $gross,
                        'net' => max(0, $gross - $azhlFeePerOrder),
                    ]);
                }
            }

            $totalEarnings = (float) $completedMap->sum('net');

            // 3. Total Withdrawn: Sum of completed withdrawals
            $totalWithdrawn = (float) Withdrawal::where('user_id', $user->id)
                ->where('account_type', 'provider')
                ->where('status', 'completed')
                ->sum('amount');

            // 4. Reserved Funds: Active withdrawal requests currently requested/pending/accepted
            $reservedAmount = (float) Withdrawal::where('user_id', $user->id)
                ->where('account_type', 'provider')
                ->whereIn('status', ['requested', 'pending', 'accepted', 'approved'])
                ->sum('amount');
        } else {
            // Marketplace Seller Calculations
            $orderIds = \App\Models\MarketplaceOrderItem::where('shop_id', $user->id)
                ->pluck('marketplace_order_id')
                ->unique()
                ->filter();

            $pendingAmount = (float) Payment::whereIn('marketplace_order_id', $orderIds)
                ->whereIn('status', ['pending', 'processing', 'initiated'])
                ->sum('amount');

            $completedPayments = Payment::whereIn('marketplace_order_id', $orderIds)
                ->where('status', 'captured')
                ->get();

            $totalEarnings = 0.0;
            foreach ($completedPayments as $p) {
                $gross = (float) ($p->amount ?? 0);
                $net = max(0, $gross - $azhlFeePerOrder);
                $totalEarnings += $net;
            }

            $totalWithdrawn = (float) Withdrawal::where('user_id', $user->id)
                ->where('account_type', 'marketplace')
                ->where('status', 'completed')
                ->sum('amount');

            $reservedAmount = (float) Withdrawal::where('user_id', $user->id)
                ->where('account_type', 'marketplace')
                ->whereIn('status', ['requested', 'pending', 'accepted', 'approved'])
                ->sum('amount');
        }

        $availableForWithdrawal = max(0, $totalEarnings - $totalWithdrawn - $reservedAmount);

        return response()->json([
            'success' => true,
            'message' => 'Provider wallet summary retrieved successfully.',
            'data' => [
                'total_earnings' => round((float) ($totalEarnings ?? 0), 2),
                'pending_amount' => round((float) ($pendingAmount ?? 0), 2),
                'available_for_withdrawal' => round((float) ($availableForWithdrawal ?? 0), 2),
                'total_withdrawn' => round((float) ($totalWithdrawn ?? 0), 2),
                'currency' => 'SAR'
            ]
        ]);
    }

    /**
     * Submit a Provider / Marketplace Withdrawal Request
     * Contract matching Page 9 of Specification Doc
     */
    public function requestWithdrawal(Request $request)
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $accountType = $request->input('account_type', 'provider');
        if (!in_array($accountType, ['provider', 'marketplace'])) {
            $accountType = 'provider';
        }

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|gt:0',
            'bank_account_id' => 'required|exists:bank_accounts,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 422);
        }

        $bankAccount = BankAccount::where('id', $request->bank_account_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$bankAccount) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => [
                    'bank_account_id' => ['Selected bank account does not belong to you.']
                ]
            ], 422);
        }

        $settings = SystemSettingModel::first();
        $azhlFeePerOrder = (float) ($settings->azhl_fee ?? 5.00);

        if ($accountType === 'provider') {
            $completedOrders = Orders::where('provider_id', $user->id)
                ->where('status', 'completed')
                ->get();

            $completedPayments = Payment::where('provider_id', $user->id)
                ->where('status', 'captured')
                ->get();

            $completedMap = collect();

            foreach ($completedOrders as $ord) {
                $gross = (float) ($ord->price ?? 0);
                $key = 'order_' . $ord->id;
                $completedMap->put($key, max(0, $gross - $azhlFeePerOrder));
            }

            foreach ($completedPayments as $pay) {
                $gross = (float) ($pay->amount ?? 0);
                $key = 'job_' . $pay->job_id;
                if (!$completedMap->has($key)) {
                    $completedMap->put($key, max(0, $gross - $azhlFeePerOrder));
                }
            }

            $totalEarnings = (float) $completedMap->sum();

            $totalWithdrawn = (float) Withdrawal::where('user_id', $user->id)
                ->where('account_type', 'provider')
                ->where('status', 'completed')
                ->sum('amount');

            $reservedAmount = (float) Withdrawal::where('user_id', $user->id)
                ->where('account_type', 'provider')
                ->whereIn('status', ['requested', 'pending', 'accepted', 'approved'])
                ->sum('amount');
        } else {
            $orderIds = \App\Models\MarketplaceOrderItem::where('shop_id', $user->id)
                ->pluck('marketplace_order_id')
                ->unique()
                ->filter();

            $completedPayments = Payment::whereIn('marketplace_order_id', $orderIds)
                ->where('status', 'captured')
                ->get();

            $totalEarnings = 0.0;
            foreach ($completedPayments as $p) {
                $totalEarnings += max(0, ((float)($p->amount ?? 0)) - $azhlFeePerOrder);
            }

            $totalWithdrawn = (float) Withdrawal::where('user_id', $user->id)
                ->where('account_type', 'marketplace')
                ->where('status', 'completed')
                ->sum('amount');

            $reservedAmount = (float) Withdrawal::where('user_id', $user->id)
                ->where('account_type', 'marketplace')
                ->whereIn('status', ['requested', 'pending', 'accepted', 'approved'])
                ->sum('amount');
        }

        $availableBalance = max(0, $totalEarnings - $totalWithdrawn - $reservedAmount);
        $requestedAmount = (float) $request->amount;

        if ($requestedAmount > $availableBalance) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient available balance.',
                'errors' => [
                    'amount' => [
                        'The withdrawal amount cannot exceed your available balance.'
                    ]
                ]
            ], 422);
        }

        $withdrawal = Withdrawal::create([
            'user_id' => $user->id,
            'account_type' => $accountType,
            'bank_account_id' => $bankAccount->id,
            'amount' => $requestedAmount,
            'currency' => 'SAR',
            'status' => 'requested',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Withdrawal request submitted successfully.',
            'data' => [
                'id' => $withdrawal->id,
                'withdrawal_no' => 'WDR-' . str_pad($withdrawal->id, 6, '0', STR_PAD_LEFT),
                'amount' => (float) $withdrawal->amount,
                'currency' => $withdrawal->currency,
                'status' => 'requested',
                'bank_account' => [
                    'id' => $bankAccount->id,
                    'bank_name' => $bankAccount->bank_name,
                    'iban' => $bankAccount->iban,
                ],
                'requested_at' => $withdrawal->created_at ? $withdrawal->created_at->toIso8601String() : null,
            ]
        ], 200);
    }

    /**
     * Provider / Marketplace Combined Paginated Transaction History API
     * Contract matching Page 7 & 8 of Specification Doc
     */
    public function transactionHistory(Request $request)
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $accountType = $request->input('account_type', 'provider');
        $filter = strtolower($request->input('filter', 'all'));
        if (empty($filter) || $filter === 'null') {
            $filter = 'all';
        }

        $settings = SystemSettingModel::first();
        $azhlFeePerOrder = (float) ($settings->azhl_fee ?? 5.00);

        $transactions = collect();

        // 1. Credit Transactions
        if (in_array($filter, ['all', 'credit'])) {
            if ($accountType === 'provider') {
                $completedOrders = Orders::with(['job.category'])
                    ->where('provider_id', $user->id)
                    ->where('status', 'completed')
                    ->get();

                $capturedPayments = Payment::with(['job.category'])
                    ->where('provider_id', $user->id)
                    ->where('status', 'captured')
                    ->get();

                $processedOrderIds = [];

                foreach ($completedOrders as $ord) {
                    $gross = (float) ($ord->price ?? 0);
                    $azhlFee = $azhlFeePerOrder;
                    $net = max(0, $gross - $azhlFee);
                    $job = $ord->job;
                    $processedOrderIds[] = $ord->id;

                    $transactions->push([
                        'id' => (int) $ord->id,
                        'type' => 'credit',
                        'label' => 'Credit',
                        'amount' => round($net, 2),
                        'currency' => 'SAR',
                        'created_at' => $ord->updated_at ? $ord->updated_at->toIso8601String() : null,
                        'credit' => [
                            'order_id' => (int) $ord->id,
                            'order_no' => 'ORD-' . str_pad($ord->id, 6, '0', STR_PAD_LEFT),
                            'order_title' => optional($job)->title ?: (optional(optional($job)->category)->name ?: 'AC Repair Service'),
                            'order_status' => 'completed',
                            'gross_amount' => round($gross, 2),
                            'azhl_fee' => round($azhlFee, 2),
                            'net_amount' => round($net, 2),
                            'completed_at' => $ord->updated_at ? $ord->updated_at->toIso8601String() : null,
                        ],
                        'withdraw' => null,
                    ]);
                }

                foreach ($capturedPayments as $p) {
                    if ($p->job_id && in_array($p->job_id, $processedOrderIds)) {
                        continue;
                    }
                    $gross = (float) ($p->amount ?? 0);
                    $azhlFee = $azhlFeePerOrder;
                    $net = max(0, $gross - $azhlFee);
                    $job = $p->job;

                    $transactions->push([
                        'id' => (int) (900000 + $p->id),
                        'type' => 'credit',
                        'label' => 'Credit',
                        'amount' => round($net, 2),
                        'currency' => strtoupper($p->currency ?: 'SAR'),
                        'created_at' => $p->updated_at ? $p->updated_at->toIso8601String() : null,
                        'credit' => [
                            'order_id' => (int) ($job ? $job->id : $p->job_id),
                            'order_no' => $job ? ('ORD-' . str_pad($job->id, 6, '0', STR_PAD_LEFT)) : ('ORD-' . $p->job_id),
                            'order_title' => optional($job)->title ?: (optional(optional($job)->category)->name ?: 'Service Order'),
                            'order_status' => 'completed',
                            'gross_amount' => round($gross, 2),
                            'azhl_fee' => round($azhlFee, 2),
                            'net_amount' => round($net, 2),
                            'completed_at' => $p->updated_at ? $p->updated_at->toIso8601String() : null,
                        ],
                        'withdraw' => null,
                    ]);
                }
            } else {
                $orderIds = \App\Models\MarketplaceOrderItem::where('shop_id', $user->id)
                    ->pluck('marketplace_order_id')
                    ->unique()
                    ->filter();

                $payments = Payment::with(['marketplaceOrder'])
                    ->whereIn('marketplace_order_id', $orderIds)
                    ->where('status', 'captured')
                    ->get();

                foreach ($payments as $p) {
                    $gross = (float) ($p->amount ?? 0);
                    $azhlFee = $azhlFeePerOrder;
                    $net = max(0, $gross - $azhlFee);
                    $order = $p->marketplaceOrder;

                    $transactions->push([
                        'id' => (int) $p->id,
                        'type' => 'credit',
                        'label' => 'Credit',
                        'amount' => round($net, 2),
                        'currency' => strtoupper($p->currency ?: 'SAR'),
                        'created_at' => $p->updated_at ? $p->updated_at->toIso8601String() : null,
                        'credit' => [
                            'order_id' => (int) ($order ? $order->id : $p->marketplace_order_id),
                            'order_no' => optional($order)->order_number ?: ('ORD-' . $p->marketplace_order_id),
                            'order_title' => 'Marketplace Product Order',
                            'order_status' => 'completed',
                            'gross_amount' => round($gross, 2),
                            'azhl_fee' => round($azhlFee, 2),
                            'net_amount' => round($net, 2),
                            'completed_at' => $p->updated_at ? $p->updated_at->toIso8601String() : null,
                        ],
                        'withdraw' => null,
                    ]);
                }
            }
        }

        // 2. Withdrawal Transactions
        if (in_array($filter, ['all', 'withdraw'])) {
            $withdrawals = Withdrawal::with('bankAccount')
                ->where('user_id', $user->id)
                ->where('account_type', $accountType)
                ->get();

            foreach ($withdrawals as $w) {
                $bank = $w->bankAccount;

                $transactions->push([
                    'id' => (int) $w->id,
                    'type' => 'withdraw',
                    'label' => 'Withdraw',
                    'amount' => round((float) ($w->amount ?? 0), 2),
                    'currency' => strtoupper($w->currency ?: 'SAR'),
                    'created_at' => $w->created_at ? $w->created_at->toIso8601String() : null,
                    'credit' => null,
                    'withdraw' => [
                        'withdrawal_id' => (int) $w->id,
                        'withdrawal_no' => 'WDR-' . str_pad($w->id, 6, '0', STR_PAD_LEFT),
                        'bank_name' => optional($bank)->bank_name ?: 'Bank',
                        'status' => $w->status ?: 'requested',
                        'requested_at' => $w->created_at ? $w->created_at->toIso8601String() : null,
                        'completed_at' => in_array($w->status, ['completed', 'paid']) && $w->updated_at ? $w->updated_at->toIso8601String() : null,
                        'rejected_at' => $w->status === 'rejected' && $w->updated_at ? $w->updated_at->toIso8601String() : null,
                        'rejection_reason' => $w->status === 'rejected' ? ($w->admin_notes ?: 'Request rejected by admin') : null,
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
            'message' => 'Transactions retrieved successfully.',
            'data' => [
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
