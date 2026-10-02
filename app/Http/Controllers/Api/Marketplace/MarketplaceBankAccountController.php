<?php

namespace App\Http\Controllers\Api\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\MarketplaceProfile;
use App\Services\Banking\IbanApiService;
use App\Services\Payment\TapMarketplaceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class MarketplaceBankAccountController extends Controller
{
    /**
     * Validate Saudi IBAN and return bank metadata for Marketplace Seller using IBAN API service
     */
    public function validateIban(Request $request, IbanApiService $ibanService)
    {
        $validator = Validator::make($request->all(), [
            'iban' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $ibanService->verify($request->iban);

        if (!$result['valid']) {
            return response()->json([
                'status' => 400,
                'message' => $result['message'],
                'data' => null
            ], 400);
        }

        return response()->json([
            'status' => 200,
            'message' => $result['message'],
            'data' => [
                'iban' => $result['data']['iban'],
                'bank_name' => $result['data']['bank_name'],
                'swift_code' => $result['data']['swift_code'],
                'bank_location' => $result['data']['bank_location'],
            ]
        ]);
    }

    /**
     * Get list of marketplace seller saved bank accounts
     */
    public function getBankAccounts()
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json(['status' => 401, 'message' => 'Unauthorized.'], 401);
        }

        $accounts = BankAccount::where('user_id', $user->id)
            ->where('account_type', 'marketplace')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'status' => 200,
            'message' => 'Bank accounts fetched successfully.',
            'total' => $accounts->count(),
            'max_limit' => 3,
            'data' => $accounts
        ]);
    }

    /**
     * Get single bank account details for editing
     */
    public function showBankAccount($id)
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json(['status' => 401, 'message' => 'Unauthorized.'], 401);
        }

        $account = BankAccount::where('id', $id)
            ->where('user_id', $user->id)
            ->where('account_type', 'marketplace')
            ->first();

        if (!$account) {
            return response()->json(['status' => 404, 'message' => 'Bank Account not found.'], 404);
        }

        return response()->json([
            'status' => 200,
            'message' => 'Bank Account details fetched successfully.',
            'data' => $account
        ]);
    }

    /**
     * Save new bank account (Max limit = 3)
     */
    public function saveBankAccount(Request $request)
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json(['status' => 401, 'message' => 'Unauthorized.'], 401);
        }

        $validator = Validator::make($request->all(), [
            'iban' => 'required|string|max:35',
            'account_title' => 'required|string|max:255',
            'bank_name' => 'required|string|max:255',
            'swift_code' => 'nullable|string|max:50',
            'bank_location' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 422);
        }

        // Limit Check: Max 3 accounts per seller
        $existingCount = BankAccount::where('user_id', $user->id)
            ->where('account_type', 'marketplace')
            ->count();

        if ($existingCount >= 3) {
            return response()->json([
                'status' => 400,
                'message' => 'Maximum limit reached. You can only save up to 3 bank accounts.',
                'data' => null
            ], 400);
        }

        $cleanIban = strtoupper(str_replace(' ', '', $request->iban));

        $account = BankAccount::create([
            'user_id' => $user->id,
            'account_type' => 'marketplace',
            'iban' => $cleanIban,
            'account_title' => $request->account_title,
            'bank_name' => $request->bank_name,
            'swift_code' => $request->swift_code,
            'bank_location' => $request->bank_location,
            'is_default' => $existingCount === 0,
        ]);

        // Sync with marketplace_profile for backward compatibility
        $profile = MarketplaceProfile::firstOrCreate(['user_id' => $user->id], []);
        $profile->update([
            'iban' => $account->iban,
            'account_title' => $account->account_title,
            'bank_name' => $account->bank_name,
            'swift_code' => $account->swift_code,
            'bank_location' => $account->bank_location,
        ]);

        // Auto-onboard to Tap Marketplace for split payments
        try {
            $onboard = app(TapMarketplaceService::class)->onboardRetailer($user, 'marketplace', [
                'iban' => $account->iban,
                'account_title' => $account->account_title,
                'bank_name' => $account->bank_name,
            ]);
            $destId = $onboard['data']['destination_id'] ?? optional($user->marketplaceProfile)->tap_destination_id;
            if ($destId) {
                $account->update(['tap_destination_id' => $destId]);
            }
            $account->refresh();
        } catch (\Throwable $e) {
            Log::warning("Tap Marketplace auto-onboarding error for seller #{$user->id}: " . $e->getMessage());
        }

        return response()->json([
            'status' => 200,
            'message' => 'Bank Account added successfully.',
            'data' => $account
        ]);
    }

    /**
     * Update existing bank account details
     */
    public function updateBankAccount(Request $request, $id)
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json(['status' => 401, 'message' => 'Unauthorized.'], 401);
        }

        $account = BankAccount::where('id', $id)
            ->where('user_id', $user->id)
            ->where('account_type', 'marketplace')
            ->first();

        if (!$account) {
            return response()->json(['status' => 404, 'message' => 'Bank Account not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'iban' => 'required|string|max:35',
            'account_title' => 'required|string|max:255',
            'bank_name' => 'required|string|max:255',
            'swift_code' => 'nullable|string|max:50',
            'bank_location' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 422);
        }

        $cleanIban = strtoupper(str_replace(' ', '', $request->iban));

        $account->update([
            'iban' => $cleanIban,
            'account_title' => $request->account_title,
            'bank_name' => $request->bank_name,
            'swift_code' => $request->swift_code,
            'bank_location' => $request->bank_location,
        ]);

        return response()->json([
            'status' => 200,
            'message' => 'Bank Account updated successfully.',
            'data' => $account
        ]);
    }

    /**
     * Delete specific marketplace seller bank account
     */
    public function deleteBankAccount($id)
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json(['status' => 401, 'message' => 'Unauthorized.'], 401);
        }

        $account = BankAccount::where('id', $id)
            ->where('user_id', $user->id)
            ->where('account_type', 'marketplace')
            ->first();

        if (!$account) {
            return response()->json(['status' => 404, 'message' => 'Bank Account not found.'], 404);
        }

        $account->delete();

        return response()->json([
            'status' => 200,
            'message' => 'Bank Account deleted successfully.',
            'data' => null
        ]);
    }

    /**
     * Get Marketplace Seller detailed financial summary (total_earnings, available_for_withdraw, pending_amount, total_withdraw)
     */
    public function financialSummary()
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json(['status' => 401, 'message' => 'Unauthorized.'], 401);
        }

        $settings = \App\Models\Admin\SystemSettingModel::first();
        $commissionPercentage = (float) ($settings->marketplace_commission_percentage ?? $settings->azhl_percentage ?? 10.00);

        // Captured Seller Orders: calculate from base product items
        $capturedItems = \App\Models\MarketplaceOrderItem::where('shop_id', $user->id)
            ->whereHas('marketplaceOrder.payment', fn($q) => $q->where('status', 'captured'))
            ->get();

        $grossEarnings = 0.0;
        foreach ($capturedItems as $it) {
            $val = (float) ($it->total_price ?? 0);
            if ($val <= 0) {
                $val = (float) ($it->base_price ?? 0) * (int) ($it->quantity ?? 1);
            }
            $grossEarnings += $val;
        }

        $azhlCommission = round($grossEarnings * ($commissionPercentage / 100.00), 2);
        $netEarnings = max(0, round($grossEarnings - $azhlCommission, 2));

        // Pending Seller Items
        $pendingItems = \App\Models\MarketplaceOrderItem::where('shop_id', $user->id)
            ->whereHas('marketplaceOrder.payment', fn($q) => $q->whereIn('status', ['pending', 'processing', 'initiated']))
            ->get();

        $pendingPayments = 0.0;
        foreach ($pendingItems as $it) {
            $val = (float) ($it->total_price ?? 0);
            if ($val <= 0) {
                $val = (float) ($it->base_price ?? 0) * (int) ($it->quantity ?? 1);
            }
            $pendingPayments += $val;
        }

        // Total Withdrawals
        $totalWithdrawn = (float) \App\Models\Withdrawal::where('user_id', $user->id)
            ->where('account_type', 'marketplace')
            ->whereIn('status', ['approved', 'paid', 'completed'])
            ->sum('amount');

        $pendingWithdrawals = (float) \App\Models\Withdrawal::where('user_id', $user->id)
            ->where('account_type', 'marketplace')
            ->where('status', 'pending')
            ->sum('amount');

        $availableForWithdraw = max(0, $netEarnings - $totalWithdrawn - $pendingWithdrawals);

        return response()->json([
            'status' => 200,
            'message' => 'Marketplace financial summary fetched successfully.',
            'data' => [
                'currency' => 'SAR',
                'gross_total_earnings' => round($grossEarnings, 2),
                'net_total_earnings' => round($netEarnings, 2),
                'azhl_commission_percentage' => $commissionPercentage,
                'marketplace_commission_percentage' => $commissionPercentage,
                'azhl_commission_amount' => round($azhlCommission, 2),
                'available_for_withdraw' => round($availableForWithdraw, 2),
                'pending_amount' => round((float) $pendingPayments, 2),
                'total_withdraw' => round($totalWithdrawn, 2),
                'pending_withdraw_request' => round($pendingWithdrawals, 2),
            ]
        ]);
    }

    /**
     * Explicitly onboard marketplace seller to Tap Marketplace (or refresh destination/KYC)
     */
    public function tapOnboard(Request $request, TapMarketplaceService $marketplaceService)
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json(['status' => 401, 'message' => 'Unauthorized.'], 401);
        }

        $res = $marketplaceService->onboardRetailer($user, 'marketplace', $request->all());

        return response()->json([
            'status' => $res['success'] ? 200 : 400,
            'message' => $res['message'],
            'data' => $res['data']
        ], $res['success'] ? 200 : 400);
    }

    /**
     * Get Tap Marketplace account and payout status for seller
     */
    public function tapStatus(TapMarketplaceService $marketplaceService)
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json(['status' => 401, 'message' => 'Unauthorized.'], 401);
        }

        $status = $marketplaceService->getRetailerStatus($user, 'marketplace');

        return response()->json([
            'status' => 200,
            'message' => 'Tap Marketplace account status fetched.',
            'data' => $status
        ]);
    }
}
