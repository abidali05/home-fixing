<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin\SystemSettingModel;
use App\Models\BankAccount;
use App\Models\Payment;
use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WithdrawalController extends Controller
{
    /**
     * Submit a withdrawal request for Provider or Marketplace Seller
     */
    public function requestWithdrawal(Request $request)
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json(['status' => 401, 'message' => 'Unauthorized.'], 401);
        }

        $accountType = $request->input('account_type', 'provider');
        if (!in_array($accountType, ['provider', 'marketplace'])) {
            $accountType = 'provider';
        }

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
            'bank_account_id' => 'required|exists:bank_accounts,id',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if bank account belongs to user and matches account type
        $bankAccount = BankAccount::where('id', $request->bank_account_id)
            ->where('user_id', $user->id)
            ->where('account_type', $accountType)
            ->first();

        if (!$bankAccount) {
            return response()->json(['status' => 400, 'message' => 'Selected bank account is invalid or does not belong to you.'], 400);
        }

        $settings = SystemSettingModel::first();
        $azhlPercentage = (float) ($settings->azhl_percentage ?? 10.00);

        if ($accountType === 'provider') {
            $completedPayments = Payment::where('provider_id', $user->id)
                ->where('status', 'captured')
                ->whereHas('job', function ($q) {
                    $q->whereIn('status', ['completed', 'accepted', 'finished']);
                })
                ->get();

            $grossCompleted = (float) $completedPayments->sum('amount');
            $azhlFee = $grossCompleted * ($azhlPercentage / 100.00);
            $totalEarnings = max(0, $grossCompleted - $azhlFee);

            $totalWithdrawn = (float) Withdrawal::where('user_id', $user->id)
                ->where('account_type', 'provider')
                ->whereIn('status', ['completed', 'approved', 'paid'])
                ->sum('amount');

            $pendingWithdrawals = (float) Withdrawal::where('user_id', $user->id)
                ->where('account_type', 'provider')
                ->whereIn('status', ['requested', 'pending'])
                ->sum('amount');
        } else {
            $orderIds = \App\Models\MarketplaceOrderItem::where('shop_id', $user->id)
                ->pluck('marketplace_order_id')
                ->unique()
                ->filter();

            $completedPayments = Payment::whereIn('marketplace_order_id', $orderIds)
                ->where('status', 'captured')
                ->get();

            $grossCompleted = (float) $completedPayments->sum('amount');
            $azhlFee = $grossCompleted * ($azhlPercentage / 100.00);
            $totalEarnings = max(0, $grossCompleted - $azhlFee);

            $totalWithdrawn = (float) Withdrawal::where('user_id', $user->id)
                ->where('account_type', 'marketplace')
                ->whereIn('status', ['completed', 'approved', 'paid'])
                ->sum('amount');

            $pendingWithdrawals = (float) Withdrawal::where('user_id', $user->id)
                ->where('account_type', 'marketplace')
                ->whereIn('status', ['requested', 'pending'])
                ->sum('amount');
        }

        $availableBalance = max(0, $totalEarnings - $totalWithdrawn - $pendingWithdrawals);
        $requestedAmount = (float) $request->amount;

        if ($requestedAmount > $availableBalance) {
            return response()->json([
                'status' => 400,
                'message' => "Insufficient available balance. Your available balance for withdrawal is {$availableBalance} SAR.",
                'data' => [
                    'available_balance' => round($availableBalance, 2),
                    'requested_amount' => round($requestedAmount, 2),
                ]
            ], 400);
        }

        $withdrawal = Withdrawal::create([
            'user_id' => $user->id,
            'account_type' => $accountType,
            'bank_account_id' => $bankAccount->id,
            'amount' => $requestedAmount,
            'currency' => 'SAR',
            'status' => 'requested',
            'notes' => $request->notes,
        ]);

        $withdrawal->load('bankAccount');

        return response()->json([
            'status' => 200,
            'message' => 'Withdrawal request submitted successfully.',
            'data' => [
                'id' => $withdrawal->id,
                'withdrawal_request_id' => 'WTH-' . str_pad($withdrawal->id, 5, '0', STR_PAD_LEFT),
                'amount' => (float) $withdrawal->amount,
                'currency' => $withdrawal->currency,
                'status' => 'requested',
                'bank' => [
                    'id' => $bankAccount->id,
                    'bank_name' => $bankAccount->bank_name,
                    'account_title' => $bankAccount->account_title,
                    'iban' => $bankAccount->iban,
                ],
                'created_at' => $withdrawal->created_at ? $withdrawal->created_at->format('Y-m-d H:i:s') : null,
            ]
        ]);
    }

    /**
     * Get Transaction History API (Supports Pagination & Filter: null, all, credit, withdraw)
     */
    public function transactionHistory(Request $request)
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json(['status' => 401, 'message' => 'Unauthorized.'], 401);
        }

        $accountType = $request->input('account_type', 'provider');
        $filter = strtolower($request->input('filter', 'all'));
        if (empty($filter) || $filter === 'null') {
            $filter = 'all';
        }

        $settings = SystemSettingModel::first();
        $azhlPercentage = (float) ($settings->azhl_percentage ?? 10.00);

        $transactions = collect();

        // 1. Fetch Credits (Completed Job/Order Payments) if filter is 'all' or 'credit'
        if (in_array($filter, ['all', 'credit'])) {
            if ($accountType === 'provider') {
                $payments = Payment::with(['job.category'])
                    ->where('provider_id', $user->id)
                    ->where('status', 'captured')
                    ->whereHas('job', function ($q) {
                        $q->whereIn('status', ['completed', 'accepted', 'finished']);
                    })
                    ->get();

                foreach ($payments as $payment) {
                    $grossAmount = (float) $payment->amount;
                    $azhlFee = $grossAmount * ($azhlPercentage / 100.00);
                    $creditedAmount = max(0, $grossAmount - $azhlFee);
                    $job = $payment->job;

                    $transactions->push([
                        'id' => 'CRD-' . $payment->id,
                        'type' => 'credit',
                        'transaction_label' => 'Credit',
                        'order_title' => optional($job)->title ?: (optional(optional($job)->category)->name ?: 'AC Repair Service'),
                        'order_number' => optional($job)->id ? '#ORD-' . $job->id : '#ORD-' . $payment->job_id,
                        'gross_order_amount' => round($grossAmount, 2),
                        'azhl_fee' => round($azhlFee, 2),
                        'credited_amount' => round($creditedAmount, 2),
                        'order_status' => 'Completed',
                        'completed_date' => $payment->updated_at ? $payment->updated_at->format('Y-m-d') : null,
                        'completed_time' => $payment->updated_at ? $payment->updated_at->format('H:i:s') : null,
                        'created_at' => $payment->updated_at ? $payment->updated_at->toIso8601String() : null,
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

                foreach ($payments as $payment) {
                    $grossAmount = (float) $payment->amount;
                    $azhlFee = $grossAmount * ($azhlPercentage / 100.00);
                    $creditedAmount = max(0, $grossAmount - $azhlFee);
                    $order = $payment->marketplaceOrder;

                    $transactions->push([
                        'id' => 'CRD-MKT-' . $payment->id,
                        'type' => 'credit',
                        'transaction_label' => 'Credit',
                        'order_title' => 'Marketplace Product Order',
                        'order_number' => optional($order)->order_number ?: '#ORD-' . $payment->marketplace_order_id,
                        'gross_order_amount' => round($grossAmount, 2),
                        'azhl_fee' => round($azhlFee, 2),
                        'credited_amount' => round($creditedAmount, 2),
                        'order_status' => 'Completed',
                        'completed_date' => $payment->updated_at ? $payment->updated_at->format('Y-m-d') : null,
                        'completed_time' => $payment->updated_at ? $payment->updated_at->format('H:i:s') : null,
                        'created_at' => $payment->updated_at ? $payment->updated_at->toIso8601String() : null,
                    ]);
                }
            }
        }

        // 2. Fetch Withdrawals if filter is 'all' or 'withdraw'
        if (in_array($filter, ['all', 'withdraw'])) {
            $withdrawals = Withdrawal::with('bankAccount')
                ->where('user_id', $user->id)
                ->where('account_type', $accountType)
                ->get();

            foreach ($withdrawals as $withdrawal) {
                $statusDisplay = ucfirst($withdrawal->status);
                if ($withdrawal->status === 'requested' || $withdrawal->status === 'pending') {
                    $statusDisplay = 'Requested';
                } elseif ($withdrawal->status === 'approved' || $withdrawal->status === 'paid' || $withdrawal->status === 'completed') {
                    $statusDisplay = 'Completed';
                } elseif ($withdrawal->status === 'rejected') {
                    $statusDisplay = 'Rejected';
                }

                $bank = $withdrawal->bankAccount;

                $transactions->push([
                    'id' => 'WTH-' . str_pad($withdrawal->id, 5, '0', STR_PAD_LEFT),
                    'type' => 'withdraw',
                    'transaction_label' => 'Withdraw',
                    'withdrawal_request_id' => 'WTH-' . str_pad($withdrawal->id, 5, '0', STR_PAD_LEFT),
                    'withdrawal_amount' => round((float) $withdrawal->amount, 2),
                    'bank' => $bank ? [
                        'id' => $bank->id,
                        'bank_name' => $bank->bank_name,
                        'account_title' => $bank->account_title,
                        'iban' => $bank->iban,
                    ] : null,
                    'request_date' => $withdrawal->created_at ? $withdrawal->created_at->format('Y-m-d') : null,
                    'request_time' => $withdrawal->created_at ? $withdrawal->created_at->format('H:i:s') : null,
                    'status' => $statusDisplay,
                    'rejection_reason' => $withdrawal->status === 'rejected' ? ($withdrawal->admin_notes ?: 'Request rejected by admin') : null,
                    'created_at' => $withdrawal->created_at ? $withdrawal->created_at->toIso8601String() : null,
                ]);
            }
        }

        // Sort descending by created_at timestamp
        $sortedTransactions = $transactions->sortByDesc('created_at')->values();

        // Pagination setup
        $perPage = (int) $request->input('per_page', 15);
        $page = (int) $request->input('page', 1);
        $offset = ($page - 1) * $perPage;

        $paginatedData = $sortedTransactions->slice($offset, $perPage)->values();
        $total = $sortedTransactions->count();
        $lastPage = (int) ceil($total / $perPage);

        return response()->json([
            'status' => 200,
            'message' => 'Transaction history fetched successfully.',
            'data' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage ?: 1,
                'filter' => $filter,
                'transactions' => $paginatedData,
            ]
        ]);
    }
}
