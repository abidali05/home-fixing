<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin\SystemSettingModel;
use App\Models\BankAccount;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceOrderItem;
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
     * Helper to calculate dynamic fees & net provider earnings based on System Settings
     */
    private function calculateOrderFinancials(float $repairPrice, float $extraAmount = 0.0, string $extraStatus = 'none', $order = null): array
    {
        if ($order instanceof Orders) {
            return $order->calculateAndSyncFinancials(true);
        }

        $settings = SystemSettingModel::first();

        $customerAppFee = ($settings && $settings->customer_app_fee !== null) ? (float) $settings->customer_app_fee : 0.00;
        $azhlPct = (float) ($settings->azhl_percentage ?? 10.00);

        $applicableExtra = ($extraStatus !== 'rejected' && $extraAmount > 0) ? $extraAmount : 0.00;
        $finalBase = $repairPrice + $applicableExtra;
        $subtotal = $finalBase + $customerAppFee;

        // Taxes & Gateway Fees removed (0.00)
        $totalGatewayFee = 0.00;
        $gatewayVat = 0.00;
        $customerTotal = $subtotal;

        // Provider Net: Final Base minus 1 percentage (Azhl Commission)
        $azhlFee = round($finalBase * ($azhlPct / 100), 2);
        $netProviderAmount = max(0, round($finalBase - $azhlFee, 2));

        return [
            'repair_price' => (float) number_format($repairPrice, 2, '.', ''),
            'extra_amount' => (float) number_format($extraAmount, 2, '.', ''),
            'extra_amount_status' => $extraStatus,
            'accepted_extra' => (float) number_format($applicableExtra, 2, '.', ''),
            'final_base_price' => (float) number_format($finalBase, 2, '.', ''),
            'customer_app_fee' => (float) number_format($customerAppFee, 2, '.', ''),
            'subtotal' => (float) number_format($subtotal, 2, '.', ''),
            'gateway_fee' => 0.00,
            'gateway_vat' => 0.00,
            'customer_total' => (float) number_format($customerTotal, 2, '.', ''),
            'azhl_percentage' => (float) number_format($azhlPct, 2, '.', ''),
            'azhl_fee' => (float) number_format($azhlFee, 2, '.', ''),
            'net_amount' => (float) number_format($netProviderAmount, 2, '.', ''),
        ];
    }

    /**
     * Calculate marketplace order financials based on marketplace_commission_percentage
     */
    private function calculateMarketplaceFinancials($productSubtotal): array
    {
        $settings = SystemSettingModel::first();
        $marketplaceCustomerAppFee = (float) ($settings->marketplace_customer_app_fee ?? $settings->customer_app_fee ?? 3.00);
        $commissionPct = (float) ($settings->marketplace_commission_percentage ?? $settings->azhl_percentage ?? 10.00);

        $subtotal = (float) $productSubtotal;
        $customerTotal = round($subtotal + $marketplaceCustomerAppFee, 2);

        // Seller Net: Product subtotal minus marketplace commission percentage
        $azhlFee = round($subtotal * ($commissionPct / 100), 2);
        $netAmount = max(0, round($subtotal - $azhlFee, 2));

        return [
            'products_subtotal' => $subtotal,
            'subtotal' => $subtotal,
            'marketplace_vat_percentage' => 0.00,
            'total_product_vat' => 0.00,
            'vat_amount' => 0.00,
            'tax_amount' => 0.00,
            'products_total_with_vat' => $subtotal,
            'customer_paid_with_tax' => $customerTotal,
            'gross_amount' => $subtotal,
            'customer_total' => $customerTotal,
            'total_amount' => $customerTotal,
            'customer_app_fee' => $marketplaceCustomerAppFee,
            'marketplace_customer_app_fee' => $marketplaceCustomerAppFee,
            'gateway_fee' => 0.0,
            'azhl_percentage' => $commissionPct,
            'marketplace_commission_percentage' => $commissionPct,
            'azhl_fee' => $azhlFee,
            'net_amount' => $netAmount,
            'currency' => 'SAR',
        ];
    }

    /**
     * Extract base repair price from order or accepted bid
     */
    private function extractRepairPrice($ord): float
    {
        $rawPrice = (float) ($ord->price ?? 0);
        if (!empty($ord->job_id)) {
            $acceptedBid = \App\Models\BidModel::where('job_id', $ord->job_id)->whereIn('status', ['accepted', 'completed', 'hired'])->first();
            if ($acceptedBid && (float) $acceptedBid->price > 0) {
                return (float) $acceptedBid->price;
            }
        }

        if ($rawPrice > 103) {
            $settings = SystemSettingModel::first();
            $customerAppFee = ($settings && $settings->customer_app_fee !== null) ? (float) $settings->customer_app_fee : 0.00;
            $gatewayFeePct = (float) ($settings->payment_gateway_fee_percentage ?? 2.50);
            $gatewayFixedFee = (float) ($settings->payment_gateway_fixed_fee ?? 0.00);
            $gatewayVatPct = (float) ($settings->payment_gateway_vat_percentage ?? 15.00);

            $approxSubtotal = $rawPrice / (1 + ($gatewayFeePct / 100) * (1 + $gatewayVatPct / 100));
            $estimatedRepair = max(0, $approxSubtotal - $customerAppFee);

            if (abs($estimatedRepair - round($estimatedRepair)) < 0.1) {
                return (float) round($estimatedRepair);
            }
            return (float) round($estimatedRepair, 2);
        }

        return $rawPrice;
    }

    /**
     * Calculate wallet statistics for a given user and account_type
     * Single source of truth for walletSummary & requestWithdrawal
     */
    private function calculateWalletBalance($userId, string $accountType = 'provider'): array
    {
        if ($accountType === 'provider') {
            // 1. Pending Amount: Net pending amount
            $pendingOrders = Orders::where('provider_id', $userId)
                ->whereIn('status', ['open', 'pending', 'accepted', 'on_the_way', 'arrived', 'working', 'provider_completed', 'quoted'])
                ->get();

            $pendingAmount = 0.0;
            foreach ($pendingOrders as $ord) {
                $repairPrice = $this->extractRepairPrice($ord);
                $financials = $this->calculateOrderFinancials($repairPrice, (float) ($ord->extra_amount ?? 0), (string) ($ord->extra_amount_status ?? 'none'), $ord);
                $pendingAmount += $financials['net_amount'];
            }

            // 2. Completed Orders Net Earnings
            $completedOrders = Orders::where('provider_id', $userId)
                ->where('status', 'completed')
                ->get();

            $orderEarnings = 0.0;
            foreach ($completedOrders as $ord) {
                $repairPrice = $this->extractRepairPrice($ord);
                $financials = $this->calculateOrderFinancials($repairPrice, (float) ($ord->extra_amount ?? 0), (string) ($ord->extra_amount_status ?? 'none'), $ord);
                $orderEarnings += $financials['net_amount'];
            }

            // 3. Referral Bonus Credits earned by this user as a Referrer (paid by Azhl out of its pocket)
            $referralEarnings = (float) ReferralReward::where('referrer_id', $userId)->sum('reward_amount');

            $totalEarnings = $orderEarnings + $referralEarnings;

            // 4. Total Withdrawn: Sum of completed manual withdrawals + completed Tap auto-splits!
            $manualWithdrawn = (float) Withdrawal::where('user_id', $userId)
                ->where('account_type', 'provider')
                ->where('status', 'completed')
                ->sum('amount');

            $autoTransferred = (float) Payment::where('provider_id', $userId)
                ->where('status', 'captured')
                ->whereNotNull('tap_destination_id')
                ->where('tap_split_amount', '>', 0)
                ->sum('tap_split_amount');

            $totalWithdrawn = $manualWithdrawn + $autoTransferred;

            // 5. Reserved Funds: Active withdrawal requests currently requested or accepted
            $reservedAmount = (float) Withdrawal::where('user_id', $userId)
                ->where('account_type', 'provider')
                ->whereIn('status', ['requested', 'pending', 'accepted', 'approved'])
                ->sum('amount');
        } else {
            // Marketplace Seller Calculations
            $sellerItems = MarketplaceOrderItem::with('order')
                ->where(function ($q) use ($userId) {
                    $q->where('shop_id', $userId)
                      ->orWhereHas('product', fn($p) => $p->where('user_id', $userId));
                })
                ->get();

            $pendingAmount = 0.0;
            $orderEarnings = 0.0;

            foreach ($sellerItems as $item) {
                $order = $item->order;
                if (!$order) {
                    continue;
                }

                $orderStatus = strtolower($order->status ?: 'pending');
                if (in_array($orderStatus, ['cancelled', 'cancel', 'reject', 'rejected'])) {
                    continue;
                }

                $itemSubtotal = (float) ($item->total_price ?? 0);
                if ($itemSubtotal <= 0) {
                    $itemSubtotal = (float) ($item->base_price ?? 0) * (int) ($item->quantity ?? 1);
                }

                $financials = $this->calculateMarketplaceFinancials($itemSubtotal);
                $net = $financials['net_amount'];

                if ($orderStatus === 'completed') {
                    $orderEarnings += $net;
                } else {
                    $pendingAmount += $net;
                }
            }

            $referralEarnings = (float) ReferralReward::where('referrer_id', $userId)->sum('reward_amount');
            $totalEarnings = $orderEarnings + $referralEarnings;

            $manualWithdrawn = (float) Withdrawal::where('user_id', $userId)
                ->where('account_type', 'marketplace')
                ->where('status', 'completed')
                ->sum('amount');

            $autoTransferred = (float) Payment::where('status', 'captured')
                ->whereNotNull('tap_destination_id')
                ->where('tap_split_amount', '>', 0)
                ->where(function ($q) use ($userId) {
                    $q->whereHas('marketplaceOrder.items', function ($mq) use ($userId) {
                        $mq->where('shop_id', $userId);
                    });
                })
                ->sum('tap_split_amount');

            $totalWithdrawn = $manualWithdrawn + $autoTransferred;

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
        $azhlPercentage = (float) ($settings->azhl_percentage ?? 10.00);

        $transactions = collect();

        // 1. Credit Transactions (Order Earnings & Referral Bonuses)
        if (in_array($filter, ['all', 'credit'])) {
            if ($accountType === 'provider') {
                $providerOrders = Orders::with(['job.category'])
                    ->where('provider_id', $user->id)
                    ->get();

                foreach ($providerOrders as $ord) {
                    $repairPrice = $this->extractRepairPrice($ord);
                    $financials = $this->calculateOrderFinancials($repairPrice, (float) ($ord->extra_amount ?? 0), (string) ($ord->extra_amount_status ?? 'none'), $ord);
                    $gross = $financials['final_base_price'];
                    $azhlFee = $financials['azhl_fee'];
                    $gatewayFee = $financials['gateway_fee'];
                    $net = $financials['net_amount'];
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
                        'repair_price' => number_format($repairPrice, 2, '.', ''),
                        'extra_amount' => number_format((float) ($ord->extra_amount ?? 0), 2, '.', ''),
                        'extra_amount_reason' => $ord->extra_amount_reason,
                        'extra_amount_status' => (string) ($ord->extra_amount_status ?: 'none'),
                        'accepted_extra' => number_format($financials['accepted_extra'], 2, '.', ''),
                        'gross_amount' => number_format($gross, 2, '.', ''),
                        'final_base_price' => number_format($gross, 2, '.', ''),
                        'azhl_fee' => number_format($azhlFee, 2, '.', ''),
                        'gateway_fee' => number_format($gatewayFee, 2, '.', ''),
                        'customer_app_fee' => number_format($financials['customer_app_fee'], 2, '.', ''),
                        'customer_total' => number_format($financials['customer_total'], 2, '.', ''),
                        'total_amount' => number_format($financials['customer_total'], 2, '.', ''),
                        'referral_fee' => '0.00',
                        'net_amount' => number_format($net, 2, '.', ''),
                        'completed_at' => $orderStatus === 'completed' ? ($ord->updated_at ? $ord->updated_at->toIso8601String() : null) : null,
                    ];

                    $transactions->push([
                        'id' => (int) $ord->id,
                        'type' => $type,
                        'label' => $label,
                        'amount' => number_format($net, 2, '.', ''),
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
                        'amount' => number_format((float) $refReward->reward_amount, 2, '.', ''),
                        'currency' => 'SAR',
                        'created_at' => $refReward->created_at ? $refReward->created_at->toIso8601String() : null,
                        'credit' => [
                            'order_id' => (int) ($refReward->order_id ?: 0),
                            'order_no' => $refReward->order_id ? ('ORD-' . str_pad($refReward->order_id, 6, '0', STR_PAD_LEFT)) : 'REF-BONUS',
                            'order_title' => 'Referral Reward for Provider ' . (optional($referredUser)->name ?: ('#' . $refReward->referred_user_id)),
                            'order_status' => 'completed',
                            'gross_amount' => number_format((float) $refReward->reward_amount, 2, '.', ''),
                            'azhl_fee' => '0.00',
                            'referral_fee' => '0.00',
                            'net_amount' => number_format((float) $refReward->reward_amount, 2, '.', ''),
                            'completed_at' => $refReward->created_at ? $refReward->created_at->toIso8601String() : null,
                        ],
                        'cancelled' => null,
                        'withdraw' => null,
                    ]);
                }
            } else {
                $marketplaceOrders = MarketplaceOrder::with(['items.product', 'payment'])
                    ->whereHas('items', function ($query) use ($user) {
                        $query->where('shop_id', $user->id)
                              ->orWhereHas('product', fn($p) => $p->where('user_id', $user->id));
                    })
                    ->get();

                foreach ($marketplaceOrders as $mktOrder) {
                    $sellerItems = $mktOrder->items->filter(function ($it) use ($user) {
                        return (int) $it->shop_id === (int) $user->id
                            || (int) optional($it->product)->user_id === (int) $user->id;
                    });

                    if ($sellerItems->isEmpty()) {
                        continue;
                    }

                    $subtotal = 0.0;
                    foreach ($sellerItems as $it) {
                        $itemTotal = (float) ($it->total_price ?? 0);
                        if ($itemTotal <= 0) {
                            $itemTotal = (float) ($it->base_price ?? 0) * (int) ($it->quantity ?? 1);
                        }
                        $subtotal += $itemTotal;
                    }

                    $isAllOrderItems = $sellerItems->count() === $mktOrder->items->count();
                    if ($isAllOrderItems && (float) $mktOrder->subtotal > 0) {
                        $subtotal = (float) $mktOrder->subtotal;
                    }

                    $financials = $this->calculateMarketplaceFinancials($subtotal);

                    if ($isAllOrderItems && (float) $mktOrder->tax_amount > 0) {
                        $financials['total_product_vat'] = (float) $mktOrder->tax_amount;
                        $financials['vat_amount'] = (float) $mktOrder->tax_amount;
                        $financials['tax_amount'] = (float) $mktOrder->tax_amount;
                    }
                    if ($isAllOrderItems && (float) $mktOrder->total_amount > 0) {
                        $financials['products_total_with_vat'] = (float) $mktOrder->total_amount;
                        $financials['customer_paid_with_tax'] = (float) $mktOrder->total_amount;
                    }

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

                    $titles = $sellerItems->map(fn($it) => $it->product_name ?: optional($it->product)->product_name)->filter()->unique()->join(', ');

                    $orderPayload = [
                        'order_id' => (int) $mktOrder->id,
                        'order_no' => $mktOrder->order_number ? '#' . $mktOrder->order_number : ('ORD-' . str_pad($mktOrder->id, 6, '0', STR_PAD_LEFT)),
                        'order_title' => $titles ?: 'Marketplace Product Order',
                        'order_status' => $orderStatus,
                        'subtotal' => number_format($financials['products_subtotal'], 2, '.', ''),
                        'products_subtotal' => number_format($financials['products_subtotal'], 2, '.', ''),
                        'marketplace_vat_percentage' => number_format($financials['marketplace_vat_percentage'], 2, '.', ''),
                        'total_product_vat' => number_format($financials['total_product_vat'], 2, '.', ''),
                        'vat_amount' => number_format($financials['vat_amount'], 2, '.', ''),
                        'products_total_with_vat' => number_format($financials['products_total_with_vat'], 2, '.', ''),
                        'gross_amount' => number_format($financials['gross_amount'], 2, '.', ''),
                        'customer_total' => number_format($financials['customer_total'], 2, '.', ''),
                        'total_amount' => number_format($financials['total_amount'], 2, '.', ''),
                        'azhl_percentage' => number_format($financials['azhl_percentage'], 2, '.', ''),
                        'azhl_fee' => number_format($financials['azhl_fee'], 2, '.', ''),
                        'customer_app_fee' => '0.00',
                        'gateway_fee' => '0.00',
                        'referral_fee' => '0.00',
                        'net_amount' => number_format($financials['net_amount'], 2, '.', ''),
                        'completed_at' => $orderStatus === 'completed' ? ($mktOrder->updated_at ? $mktOrder->updated_at->toIso8601String() : null) : null,
                    ];

                    $transactions->push([
                        'id' => (int) $mktOrder->id,
                        'type' => $type,
                        'label' => $label,
                        'amount' => number_format($financials['net_amount'], 2, '.', ''),
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

            // Include Tap Auto-Split direct payouts
            if ($accountType === 'provider') {
                $autoSplitPayments = Payment::where('provider_id', $user->id)
                    ->where('status', 'captured')
                    ->whereNotNull('tap_destination_id')
                    ->where('tap_split_amount', '>', 0)
                    ->get();

                foreach ($autoSplitPayments as $ap) {
                    $transactions->push([
                        'id' => (int) (900000 + $ap->id),
                        'type' => 'withdraw',
                        'label' => 'Auto-Payout (Tap)',
                        'amount' => round((float) $ap->tap_split_amount, 2),
                        'currency' => strtoupper($ap->currency ?: 'SAR'),
                        'created_at' => $ap->updated_at ? $ap->updated_at->setTimezone('Asia/Riyadh')->toIso8601String() : null,
                        'credit' => null,
                        'withdraw' => [
                            'withdrawal_id' => (int) $ap->id,
                            'withdrawal_no' => 'TAP-SPLIT-' . str_pad($ap->id, 6, '0', STR_PAD_LEFT),
                            'bank_name' => 'Bank Account (Tap Auto-Transfer)',
                            'status' => 'completed',
                            'requested_at' => $ap->created_at ? $ap->created_at->setTimezone('Asia/Riyadh')->toIso8601String() : null,
                            'accepted_at' => $ap->updated_at ? $ap->updated_at->setTimezone('Asia/Riyadh')->toIso8601String() : null,
                            'completed_at' => $ap->updated_at ? $ap->updated_at->setTimezone('Asia/Riyadh')->toIso8601String() : null,
                            'rejected_at' => null,
                            'rejection_reason' => null,
                        ]
                    ]);
                }
            } else {
                $autoSplitPayments = Payment::where('status', 'captured')
                    ->whereNotNull('tap_destination_id')
                    ->where('tap_split_amount', '>', 0)
                    ->where(function ($q) use ($user) {
                        $q->whereHas('marketplaceOrder.items', function ($mq) use ($user) {
                            $mq->where('shop_id', $user->id);
                        });
                    })
                    ->get();

                foreach ($autoSplitPayments as $ap) {
                    $transactions->push([
                        'id' => (int) (900000 + $ap->id),
                        'type' => 'withdraw',
                        'label' => 'Auto-Payout (Tap)',
                        'amount' => round((float) $ap->tap_split_amount, 2),
                        'currency' => strtoupper($ap->currency ?: 'SAR'),
                        'created_at' => $ap->updated_at ? $ap->updated_at->setTimezone('Asia/Riyadh')->toIso8601String() : null,
                        'credit' => null,
                        'withdraw' => [
                            'withdrawal_id' => (int) $ap->id,
                            'withdrawal_no' => 'TAP-SPLIT-' . str_pad($ap->id, 6, '0', STR_PAD_LEFT),
                            'bank_name' => 'Bank Account (Tap Auto-Transfer)',
                            'status' => 'completed',
                            'requested_at' => $ap->created_at ? $ap->created_at->setTimezone('Asia/Riyadh')->toIso8601String() : null,
                            'accepted_at' => $ap->updated_at ? $ap->updated_at->setTimezone('Asia/Riyadh')->toIso8601String() : null,
                            'completed_at' => $ap->updated_at ? $ap->updated_at->setTimezone('Asia/Riyadh')->toIso8601String() : null,
                            'rejected_at' => null,
                            'rejection_reason' => null,
                        ]
                    ]);
                }
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
