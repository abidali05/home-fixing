<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\ProviderProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProviderBankAccountController extends Controller
{
    /**
     * Known Saudi Bank Mapping helper by IBAN 2-digit bank code.
     */
    private array $saudiBanks = [
        '10' => ['name' => 'Saudi National Bank (SNB)', 'swift' => 'NCBKSAJE', 'location' => 'JEDDAH, Saudi Arabia'],
        '20' => ['name' => 'Al Rajhi Bank', 'swift' => 'RJHISARI', 'location' => 'RIYADH, Saudi Arabia'],
        '15' => ['name' => 'Bank AlBilad', 'swift' => 'BLADSARI', 'location' => 'RIYADH, Saudi Arabia'],
        '05' => ['name' => 'Alinma Bank', 'swift' => 'INMASARI', 'location' => 'RIYADH, Saudi Arabia'],
        '50' => ['name' => 'Saudi Awwal Bank (SABB)', 'swift' => 'SABBSARI', 'location' => 'RIYADH, Saudi Arabia'],
        '55' => ['name' => 'Banque Saudi Fransi', 'swift' => 'BSFRSARI', 'location' => 'RIYADH, Saudi Arabia'],
        '65' => ['name' => 'Saudi Investment Bank (SAIB)', 'swift' => 'SAIBSARI', 'location' => 'RIYADH, Saudi Arabia'],
        '80' => ['name' => 'Arab National Bank (ANB)', 'swift' => 'ARNBSARI', 'location' => 'RIYADH, Saudi Arabia'],
        '60' => ['name' => 'Bank AlJazira', 'swift' => 'BJAZSARI', 'location' => 'JEDDAH, Saudi Arabia'],
        '45' => ['name' => 'Saudi British Bank', 'swift' => 'SABBKS22', 'location' => 'RIYADH, Saudi Arabia'],
    ];

    /**
     * Validate Saudi IBAN and return bank metadata
     */
    public function validateIban(Request $request)
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

        $iban = strtoupper(str_replace(' ', '', $request->iban));

        if (!str_starts_with($iban, 'SA') || strlen($iban) !== 24) {
            return response()->json([
                'status' => 400,
                'message' => 'Invalid Saudi IBAN format. Must start with SA followed by 22 digits.',
                'data' => null
            ], 400);
        }

        $bankCode = substr($iban, 4, 2);
        $bankInfo = $this->saudiBanks[$bankCode] ?? [
            'name' => 'Saudi Commercial Bank',
            'swift' => 'SAUDBANK',
            'location' => 'Saudi Arabia',
        ];

        return response()->json([
            'status' => 200,
            'message' => 'IBAN verified successfully.',
            'data' => [
                'iban' => $iban,
                'bank_name' => $bankInfo['name'],
                'swift_code' => $bankInfo['swift'],
                'bank_location' => $bankInfo['location'],
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

        // 2. Total Earnings: Sum of net provider credits from completed orders (gross - percentage azhl commission)
        $completedPayments = \App\Models\Payment::where('provider_id', $user->id)
            ->where('status', 'captured')
            ->whereHas('job', function ($q) {
                $q->whereIn('status', ['completed', 'accepted', 'finished']);
            })
            ->get();

        $totalEarnings = 0.0;
        foreach ($completedPayments as $payment) {
            $gross = (float) $payment->amount;
            $fee = $gross * ($azhlPercentage / 100);
            $net = max(0, $gross - $fee);
            $totalEarnings += $net;
        }

        // 3. Total Withdrawn: Sum of withdrawals where status = completed only
        $totalWithdrawn = (float) \App\Models\Withdrawal::where('user_id', $user->id)
            ->where('account_type', 'provider')
            ->where('status', 'completed')
            ->sum('amount');

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
}
