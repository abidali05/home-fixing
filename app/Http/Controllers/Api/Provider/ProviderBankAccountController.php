<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\ProviderProfile;
use App\Services\Banking\IbanApiService;
use App\Services\Payment\TapMarketplaceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ProviderBankAccountController extends Controller
{
    /**
     * Validate IBAN and return bank metadata using IBAN API service
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
     * Get list of provider saved bank accounts
     */
    public function getBankAccounts()
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json(['status' => 401, 'message' => 'Unauthorized.'], 401);
        }

        $accounts = BankAccount::where('user_id', $user->id)
            ->where('account_type', 'provider')
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
            ->where('account_type', 'provider')
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

        // Limit Check: Max 3 accounts per provider
        $existingCount = BankAccount::where('user_id', $user->id)
            ->where('account_type', 'provider')
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
            'account_type' => 'provider',
            'iban' => $cleanIban,
            'account_title' => $request->account_title,
            'bank_name' => $request->bank_name,
            'swift_code' => $request->swift_code,
            'bank_location' => $request->bank_location,
            'is_default' => $existingCount === 0,
        ]);

        // Sync with provider_profile for backward compatibility
        $profile = ProviderProfile::firstOrCreate(['user_id' => $user->id], []);
        $profile->update([
            'iban' => $account->iban,
            'account_title' => $account->account_title,
            'bank_name' => $account->bank_name,
            'swift_code' => $account->swift_code,
            'bank_location' => $account->bank_location,
        ]);

        // Auto-onboard to Tap Marketplace for split payments
        try {
            $onboard = app(TapMarketplaceService::class)->onboardRetailer($user, 'provider', [
                'iban' => $account->iban,
                'account_title' => $account->account_title,
                'bank_name' => $account->bank_name,
            ]);
            $destId = $onboard['data']['destination_id'] ?? optional($user->providerProfile)->tap_destination_id;
            if ($destId) {
                $account->update(['tap_destination_id' => $destId]);
            }
            $account->refresh();
        } catch (\Throwable $e) {
            Log::warning("Tap Marketplace auto-onboarding error for provider #{$user->id}: " . $e->getMessage());
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
            ->where('account_type', 'provider')
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
     * Delete specific provider bank account
     */
    public function deleteBankAccount($id)
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json(['status' => 401, 'message' => 'Unauthorized.'], 401);
        }

        $account = BankAccount::where('id', $id)
            ->where('user_id', $user->id)
            ->where('account_type', 'provider')
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
     * Get Provider wallet summary (total_earnings, pending_amount, available_for_withdrawal, total_withdrawn)
     */
    public function financialSummary()
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $settings = \App\Models\Admin\SystemSettingModel::first();
        $azhlPercentage = (float) ($settings->azhl_percentage ?? 10.00);

        // 1. Pending Amount: Gross value of active/held provider orders
        $pendingAmount = (float) \App\Models\Payment::where('provider_id', $user->id)
            ->whereHas('job', function ($q) {
                $q->whereIn('status', ['quoted', 'hired', 'in_progress', 'pending']);
            })
            ->sum('amount');

        // 2. Total Earnings: Net provider credits from completed orders + referral rewards
        $totalEarnings = (float) $user->total_earnings;

        // 3. Total Withdrawn: Sum of completed manual withdrawals + auto-transfers via Tap
        $manualWithdrawn = (float) \App\Models\Withdrawal::where('user_id', $user->id)
            ->where('account_type', 'provider')
            ->where('status', 'completed')
            ->sum('amount');

        $autoTransferred = (float) \App\Models\Payment::where('provider_id', $user->id)
            ->where('status', 'captured')
            ->whereNotNull('tap_destination_id')
            ->where('tap_split_amount', '>', 0)
            ->sum('tap_split_amount');

        $totalWithdrawn = $manualWithdrawn + $autoTransferred;

        // 4. Reserved Funds: Active withdrawal requests currently requested or accepted
        $reservedAmount = (float) \App\Models\Withdrawal::where('user_id', $user->id)
            ->where('account_type', 'provider')
            ->whereIn('status', ['requested', 'pending', 'accepted', 'approved'])
            ->sum('amount');

        // 5. Available for Withdrawal: Net credits not withdrawn and not reserved
        $availableForWithdrawal = max(0, $totalEarnings - $totalWithdrawn - $reservedAmount);

        return response()->json([
            'success' => true,
            'message' => 'Provider wallet summary retrieved successfully.',
            'data' => [
                'total_earnings' => round($totalEarnings, 2),
                'pending_amount' => round($pendingAmount, 2),
                'available_for_withdrawal' => round($availableForWithdrawal, 2),
                'total_withdrawn' => round($totalWithdrawn, 2),
                'currency' => 'SAR'
            ]
        ]);
    }

    /**
     * Explicitly onboard provider to Tap Marketplace (or refresh destination/KYC)
     */
    public function tapOnboard(Request $request, TapMarketplaceService $marketplaceService)
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json(['status' => 401, 'message' => 'Unauthorized.'], 401);
        }

        $res = $marketplaceService->onboardRetailer($user, 'provider', $request->all());

        return response()->json([
            'status' => $res['success'] ? 200 : 400,
            'message' => $res['message'],
            'data' => $res['data']
        ], $res['success'] ? 200 : 400);
    }

    /**
     * Get Tap Marketplace account and payout status for provider
     */
    public function tapStatus(TapMarketplaceService $marketplaceService)
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json(['status' => 401, 'message' => 'Unauthorized.'], 401);
        }

        $status = $marketplaceService->getRetailerStatus($user, 'provider');

        return response()->json([
            'status' => 200,
            'message' => 'Tap Marketplace account status fetched.',
            'data' => $status
        ]);
    }
}
