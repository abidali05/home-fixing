<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin\SystemSettingModel;
use App\Models\BankAccount;
use App\Models\MarketplaceOrder;
use App\Models\Orders;
use App\Models\Payment;
use App\Models\ProviderProfile;
use App\Models\ReferralReward;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class WithdrawalController extends Controller
{
    /**
     * Calculate wallet statistics for a given user and account_type
     * Single source of truth for walletSummary & requestWithdrawal
     */
    private function calculateWalletBalance($userId, string $accountType = 'provider'): array
    {
        $settings = SystemSettingModel::first();
        $azhlFeePerOrder = (float) ($settings->azhl_fee ?? 5.00);

        if ($accountType === 'provider') {
            // 1. Pending Amount: Net pending amount (gross price minus azhl fee per order)
            $pendingOrders = Orders::where('provider_id', $userId)
                ->whereIn('status', ['open', 'pending', 'accepted', 'on_the_way', 'arrived', 'working', 'provider_completed', 'quoted'])
                ->get();

            $pendingAmount = 0.0;
            foreach ($pendingOrders as $ord) {
                $gross = (float) ($ord->price ?? 0);
                $netPending = max(0, $gross - $azhlFeePerOrder);
                $pendingAmount += $netPending;
            }

            // 2. Completed Orders Net Earnings (gross - fixed azhl_fee)
            $completedOrders = Orders::where('provider_id', $userId)
                ->where('status', 'completed')
                ->get();

            $orderEarnings = 0.0;
            foreach ($completedOrders as $ord) {
                $gross = (float) ($ord->price ?? 0);
                $net = max(0, $gross - $azhlFeePerOrder);
                $orderEarnings += $net;
            }

            // 3. Referral Bonus Credits earned by this user as a Referrer (paid by Azhl out of its pocket)
            $referralEarnings = (float) ReferralReward::where('referrer_id', $userId)->sum('reward_amount');

            $totalEarnings = $orderEarnings + $referralEarnings;

            // 4. Total Withdrawn: Sum of completed withdrawals
            $totalWithdrawn = (float) Withdrawal::where('user_id', $userId)
                ->where('account_type', 'provider')
                ->where('status', 'completed')
                ->sum('amount');

            // 5. Reserved Funds: Active withdrawal requests currently requested or accepted
            $reservedAmount = (float) Withdrawal::where('user_id', $userId)
                ->where('account_type', 'provider')
                ->whereIn('status', ['requested', 'pending', 'accepted', 'approved'])
                ->sum('amount');
        } else {
            // Marketplace Seller Calculations
            $orderIds = \App\Models\MarketplaceOrderItem::where('shop_id', $userId)
                ->pluck('marketplace_order_id')
                ->unique()
                ->filter();

            $pendingPayments = Payment::whereIn('marketplace_order_id', $orderIds)
                ->whereIn('status', ['pending', 'processing', 'initiated'])
                ->get();

            $pendingAmount = 0.0;
            foreach ($pendingPayments as $p) {
                $gross = (float) ($p->amount ?? 0);
                $pendingAmount += max(0, $gross - $azhlFeePerOrder);
            }

            $completedPayments = Payment::whereIn('marketplace_order_id', $orderIds)
                ->where('status', 'captured')
                ->get();

            $orderEarnings = 0.0;
            foreach ($completedPayments as $p) {
                $gross = (float) ($p->amount ?? 0);
                $net = max(0, $gross - $azhlFeePerOrder);
                $orderEarnings += $net;
            }

            $referralEarnings = (float) ReferralReward::where('referrer_id', $userId)->sum('reward_amount');
            $totalEarnings = $orderEarnings + $referralEarnings;

            $totalWithdrawn = (float) Withdrawal::where('user_id', $userId)
                ->where('account_type', 'marketplace')
                ->where('status', 'completed')
                ->sum('amount');

            $reservedAmount = (float) Withdrawal::where('user_id', $userId)
                ->where('account_type', 'marketplace')
                ->whereIn('status', ['requested', 'pending', 'accepted', 'approved'])
                ->sum('amount');
        }

        $availableForWithdrawal = max(0, $totalEarnings - $totalWithdrawn - $reservedAmount);

        return [
            'total_earnings' => round((float) ($totalEarnings ?? 0), 2),
            'pending_amount' => round((float) ($pendingAmount ?? 0), 2),
            'available_for_withdrawal' => round((float) ($availableForWithdrawal ?? 0), 2),
            'total_withdrawn' => round((float) ($totalWithdrawn ?? 0), 2),
            'reserved_amount' => round((float) ($reservedAmount ?? 0), 2),
            'currency' => 'SAR',
        ];
    }

    /**
     * Resolve account_type dynamically based on request query param, route path, or user active role
     */
    private function resolveAccountType(Request $request, User $user): string
    {
        $typeParam = strtolower((string) $request->input('account_type', ''));

        if (in_array($typeParam, ['provider', 'marketplace'], true)) {
            return $typeParam;
        }

        if ($request->is('*marketplace*') || (int) $user->role === 2) {
            return 'marketplace';
        }

        return 'provider';
    }

    /**
     * Provider / Marketplace Wallet Summary API
     * Contract matching Page 7 of Specification Doc
     */
    public function walletSummary(Request $request)
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $accountType = $this->resolveAccountType($request, $user);

        $stats = $this->calculateWalletBalance($user->id, $accountType);

        return response()->json([
            'success' => true,
            'message' => ucfirst($accountType) . ' wallet summary retrieved successfully.',
            'data' => [
                'account_type' => $accountType,
                'total_earnings' => $stats['total_earnings'],
                'pending_amount' => $stats['pending_amount'],
                'available_for_withdrawal' => $stats['available_for_withdrawal'],
                'total_withdrawn' => $stats['total_withdrawn'],
                'currency' => $stats['currency']
            ]
        ]);
    }

    /**
     * Submit a Provider / Marketplace Withdrawal Request
     * Contract matching Page 9 of Specification Doc
     */
    public function requestWithdrawal(Request $request)
    {
        try {
            $user = auth('sanctum')->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.'
                ], 401);
            }

            $accountType = $this->resolveAccountType($request, $user);

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
                    'message' => 'Invalid bank account.',
                    'errors' => [
                        'bank_account_id' => [
                            'Selected bank account does not belong to you.'
                        ]
                    ]
                ], 422);
            }

            $stats = $this->calculateWalletBalance(
                $user->id,
                $accountType
            );

            $availableBalance = (float) $stats['available_for_withdrawal'];
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
                'status' => 'pending',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal request submitted successfully.',
                'data' => [
                    'id' => $withdrawal->id,
                    'withdrawal_no' => 'WDR-' . str_pad(
                        $withdrawal->id,
                        6,
                        '0',
                        STR_PAD_LEFT
                    ),
                    'amount' => (float) $withdrawal->amount,
                    'currency' => $withdrawal->currency,
                    'account_type' => $accountType,
                    'status' => $withdrawal->status,
                    'bank_account' => [
                        'id' => $bankAccount->id,
                        'bank_name' => $bankAccount->bank_name,
                        'iban' => $bankAccount->iban,
                    ],
                    'requested_at' => $withdrawal->created_at
                        ? $withdrawal->created_at->toIso8601String()
                        : null,
                ]
            ], 200);
        } catch (\Throwable $e) {

            Log::error('Withdrawal request failed', [
                'user_id' => auth('sanctum')->id(),
                'request' => $request->all(),
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while processing your withdrawal request.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
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

        $accountType = $this->resolveAccountType($request, $user);
        $filter = strtolower($request->input('filter', 'all'));
        if (empty($filter) || $filter === 'null') {
            $filter = 'all';
        }

        $settings = SystemSettingModel::first();
        $azhlFeePerOrder = (float) ($settings->azhl_fee ?? 5.00);

        $transactions = collect();

        // 1. Credit Transactions (Order Earnings & Referral Bonuses)
        if (in_array($filter, ['all', 'credit'])) {
            if ($accountType === 'provider') {
                $providerOrders = Orders::with(['job.category'])
                    ->where('provider_id', $user->id)
                    ->get();

                foreach ($providerOrders as $ord) {
                    $gross = (float) ($ord->price ?? 0);
                    $azhlFee = $azhlFeePerOrder;
                    $net = max(0, $gross - $azhlFee);
                    $job = $ord->job;
                    $orderStatus = strtolower($ord->status ?: 'pending');

                    $type = 'credit';
                    $label = 'Pending Credit';
                    if (in_array($orderStatus, ['cancelled', 'cancel', 'reject', 'rejected'])) {
                        $type = 'cancelled';
                        $label = 'Cancelled Order';
                    } elseif ($orderStatus === 'completed') {
                        $type = 'credit';
                        $label = 'Credit';
                    }

                    $orderPayload = [
                        'order_id' => (int) $ord->id,
                        'order_no' => 'ORD-' . str_pad($ord->id, 6, '0', STR_PAD_LEFT),
                        'order_title' => optional($job)->title ?: (optional(optional($job)->category)->name ?: 'AC Repair Service'),
                        'order_status' => $orderStatus,
                        'gross_amount' => round($gross, 2),
                        'azhl_fee' => round($azhlFee, 2),
                        'referral_fee' => 0.00,
                        'net_amount' => round($net, 2),
                        'completed_at' => $orderStatus === 'completed' ? ($ord->updated_at ? $ord->updated_at->toIso8601String() : null) : null,
                    ];

                    $transactions->push([
                        'id' => (int) $ord->id,
                        'type' => $type,
                        'label' => $label,
                        'amount' => round($net, 2),
                        'currency' => 'SAR',
                        'created_at' => $ord->created_at ? $ord->created_at->setTimezone('Asia/Riyadh')->toIso8601String() : ($ord->updated_at ? $ord->updated_at->toIso8601String() : null),
                        'credit' => $type === 'cancelled' ? null : $orderPayload,
                        'cancelled' => $type === 'cancelled' ? $orderPayload : null,
                        'withdraw' => null,
                    ]);
                }

                // Add Referral Reward Credits earned as a Referrer
                $referralRewards = ReferralReward::with('referredUser')
                    ->where('referrer_id', $user->id)
                    ->get();

                foreach ($referralRewards as $refReward) {
                    $referredUser = $refReward->referredUser;

                    $transactions->push([
                        'id' => (int) (800000 + $refReward->id),
                        'type' => 'credit',
                        'label' => 'Referral Bonus',
                        'amount' => round((float) $refReward->reward_amount, 2),
                        'currency' => 'SAR',
                        'created_at' => $refReward->created_at ? $refReward->created_at->toIso8601String() : null,
                        'credit' => [
                            'order_id' => (int) ($refReward->order_id ?: 0),
                            'order_no' => $refReward->order_id ? ('ORD-' . str_pad($refReward->order_id, 6, '0', STR_PAD_LEFT)) : 'REF-BONUS',
                            'order_title' => 'Referral Reward for Provider ' . (optional($referredUser)->name ?: ('#' . $refReward->referred_user_id)),
                            'order_status' => 'completed',
                            'gross_amount' => round((float) $refReward->reward_amount, 2),
                            'azhl_fee' => 0.00,
                            'referral_fee' => 0.00,
                            'net_amount' => round((float) $refReward->reward_amount, 2),
                            'completed_at' => $refReward->created_at ? $refReward->created_at->toIso8601String() : null,
                        ],
                        'cancelled' => null,
                        'withdraw' => null,
                    ]);
                }
            } else {
                $marketplaceOrders = MarketplaceOrder::with(['items.product', 'payment'])
                    ->whereHas('items', function ($query) use ($user) {
                        $query->where('shop_id', $user->id);
                    })
                    ->get();

                foreach ($marketplaceOrders as $mktOrder) {
                    $item = $mktOrder->items->firstWhere('shop_id', $user->id);
                    $product = $item?->product;
                    $gross = (float) ($item?->total_price ?? ($mktOrder->total_amount ?? 0));
                    $azhlFee = $azhlFeePerOrder;
                    $net = max(0, $gross - $azhlFee);
                    $orderStatus = strtolower($mktOrder->status ?: 'pending');

                    $type = 'credit';
                    $label = 'Pending Credit';
                    if (in_array($orderStatus, ['cancelled', 'cancel', 'reject', 'rejected'])) {
                        $type = 'cancelled';
                        $label = 'Cancelled Order';
                    } elseif ($orderStatus === 'completed') {
                        $type = 'credit';
                        $label = 'Credit';
                    }

                    $orderPayload = [
                        'order_id' => (int) $mktOrder->id,
                        'order_no' => $mktOrder->order_number ? '#' . $mktOrder->order_number : ('ORD-' . str_pad($mktOrder->id, 6, '0', STR_PAD_LEFT)),
                        'order_title' => $item?->product_name ?: ($product?->product_name ?: 'Marketplace Product Order'),
                        'order_status' => $orderStatus,
                        'gross_amount' => round($gross, 2),
                        'azhl_fee' => round($azhlFee, 2),
                        'referral_fee' => 0.00,
                        'net_amount' => round($net, 2),
                        'completed_at' => $orderStatus === 'completed' ? ($mktOrder->updated_at ? $mktOrder->updated_at->toIso8601String() : null) : null,
                    ];

                    $transactions->push([
                        'id' => (int) $mktOrder->id,
                        'type' => $type,
                        'label' => $label,
                        'amount' => round($net, 2),
                        'currency' => 'SAR',
                        'created_at' => $mktOrder->created_at ? $mktOrder->created_at->setTimezone('Asia/Riyadh')->toIso8601String() : null,
                        'credit' => $type === 'cancelled' ? null : $orderPayload,
                        'cancelled' => $type === 'cancelled' ? $orderPayload : null,
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
                $status = strtolower($w->status ?: 'requested');

                $transactions->push([
                    'id' => (int) $w->id,
                    'type' => 'withdraw',
                    'label' => 'Withdraw',
                    'amount' => round((float) ($w->amount ?? 0), 2),
                    'currency' => strtoupper($w->currency ?: 'SAR'),
                    'created_at' => $w->created_at ? $w->created_at->setTimezone('Asia/Riyadh')->toIso8601String() : null,
                    'credit' => null,
                    'withdraw' => [
                        'withdrawal_id' => (int) $w->id,
                        'withdrawal_no' => 'WDR-' . str_pad($w->id, 6, '0', STR_PAD_LEFT),
                        'bank_name' => optional($bank)->bank_name ?: 'Bank',
                        'status' => $status,
                        'requested_at' => $w->created_at ? $w->created_at->setTimezone('Asia/Riyadh')->toIso8601String() : null,
                        'accepted_at' => in_array($status, ['accepted', 'completed', 'paid']) ? ($w->updated_at ? $w->updated_at->setTimezone('Asia/Riyadh')->toIso8601String() : null) : null,
                        'completed_at' => in_array($status, ['completed', 'paid']) ? ($w->updated_at ? $w->updated_at->setTimezone('Asia/Riyadh')->toIso8601String() : null) : null,
                        'rejected_at' => $status === 'rejected' ? ($w->updated_at ? $w->updated_at->setTimezone('Asia/Riyadh')->toIso8601String() : null) : null,
                        'rejection_reason' => $status === 'rejected' ? ($w->admin_notes ?: 'Request rejected by admin') : null,
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
