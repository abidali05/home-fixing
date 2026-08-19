<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CustomerBankAccountController extends Controller
{
    /**
     * Get Customer Bank Accounts (Max 3)
     * GET /api/v1/customer/bank-accounts
     */
    public function getBankAccounts(Request $request)
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $bankAccounts = BankAccount::where('user_id', $user->id)
            ->where('account_type', 'customer')
            ->orderByDesc('is_primary')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Customer bank accounts retrieved successfully.',
            'data' => [
                'bank_accounts' => $bankAccounts,
                'total' => $bankAccounts->count(),
                'max_allowed' => 3,
            ]
        ]);
    }

    /**
     * Add Customer Bank Account (Max limit 3)
     * POST /api/v1/customer/bank-accounts
     */
    public function saveBankAccount(Request $request)
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $existingCount = BankAccount::where('user_id', $user->id)
            ->where('account_type', 'customer')
            ->count();

        if ($existingCount >= 3) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot add more than 3 bank accounts.',
                'errors' => [
                    'bank_account' => ['Maximum 3 bank accounts allowed per customer.']
                ]
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'bank_name' => 'required|string|max:100',
            'account_title' => 'required|string|max:150',
            'iban' => 'required|string|min:15|max:34',
            'account_number' => 'nullable|string|max:50',
            'swift_code' => 'nullable|string|max:20',
            'is_primary' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 422);
        }

        $isPrimary = $request->boolean('is_primary', $existingCount === 0);

        if ($isPrimary) {
            BankAccount::where('user_id', $user->id)
                ->where('account_type', 'customer')
                ->update(['is_primary' => false]);
        }

        $bankAccount = BankAccount::create([
            'user_id' => $user->id,
            'account_type' => 'customer',
            'bank_name' => trim($request->bank_name),
            'account_title' => trim($request->account_title),
            'iban' => strtoupper(trim($request->iban)),
            'account_number' => $request->account_number ? trim($request->account_number) : null,
            'swift_code' => $request->swift_code ? strtoupper(trim($request->swift_code)) : null,
            'is_primary' => $isPrimary,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Bank account added successfully.',
            'data' => $bankAccount
        ], 201);
    }

    /**
     * Update Customer Bank Account
     * PUT /api/v1/customer/bank-accounts/{id}
     */
    public function updateBankAccount(Request $request, $id)
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $bankAccount = BankAccount::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$bankAccount) {
            return response()->json([
                'success' => false,
                'message' => 'Bank account not found.'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'bank_name' => 'sometimes|required|string|max:100',
            'account_title' => 'sometimes|required|string|max:150',
            'iban' => 'sometimes|required|string|min:15|max:34',
            'account_number' => 'nullable|string|max:50',
            'swift_code' => 'nullable|string|max:20',
            'is_primary' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 422);
        }

        if ($request->has('is_primary') && $request->boolean('is_primary')) {
            BankAccount::where('user_id', $user->id)
                ->where('account_type', 'customer')
                ->update(['is_primary' => false]);
        }

        $bankAccount->update(array_filter([
            'bank_name' => $request->has('bank_name') ? trim($request->bank_name) : null,
            'account_title' => $request->has('account_title') ? trim($request->account_title) : null,
            'iban' => $request->has('iban') ? strtoupper(trim($request->iban)) : null,
            'account_number' => $request->has('account_number') ? trim($request->account_number) : null,
            'swift_code' => $request->has('swift_code') ? strtoupper(trim($request->swift_code)) : null,
            'is_primary' => $request->has('is_primary') ? $request->boolean('is_primary') : null,
        ], fn($v) => $v !== null));

        return response()->json([
            'success' => true,
            'message' => 'Bank account updated successfully.',
            'data' => $bankAccount
        ]);
    }
}
