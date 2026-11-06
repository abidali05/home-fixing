<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
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

        $query = BankAccount::where('user_id', $user->id)
            ->where('account_type', 'customer');

        if (Schema::hasColumn('bank_accounts', 'is_primary')) {
            $query->orderByDesc('is_primary');
        } elseif (Schema::hasColumn('bank_accounts', 'is_default')) {
            $query->orderByDesc('is_default');
        }

        $bankAccounts = $query->orderByDesc('id')->get();

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
            'is_default' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 422);
        }

        $isPrimary = $request->boolean('is_primary', $request->boolean('is_default', $existingCount === 0));

        if ($isPrimary) {
            $updateData = [];
            if (Schema::hasColumn('bank_accounts', 'is_primary')) {
                $updateData['is_primary'] = false;
            }
            if (Schema::hasColumn('bank_accounts', 'is_default')) {
                $updateData['is_default'] = false;
            }

            if (!empty($updateData)) {
                BankAccount::where('user_id', $user->id)
                    ->where('account_type', 'customer')
                    ->update($updateData);
            }
        }

        $createData = [
            'user_id' => $user->id,
            'account_type' => 'customer',
            'bank_name' => trim($request->bank_name),
            'account_title' => trim($request->account_title),
            'iban' => strtoupper(trim($request->iban)),
            'account_number' => $request->account_number ? trim($request->account_number) : null,
            'swift_code' => $request->swift_code ? strtoupper(trim($request->swift_code)) : null,
        ];

        if (Schema::hasColumn('bank_accounts', 'is_primary')) {
            $createData['is_primary'] = $isPrimary;
        }
        if (Schema::hasColumn('bank_accounts', 'is_default')) {
            $createData['is_default'] = $isPrimary;
        }

        $bankAccount = BankAccount::create($createData);

        return response()->json([
            'success' => true,
            'message' => 'Bank account added successfully.',
            'data' => $bankAccount
        ], 201);
    }

    /**
     * Update Customer Bank Account
     * PUT /api/v1/customer/bank-accounts/{id}
     * POST /api/v1/customer/bank-accounts/{id}/update
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
            'is_default' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 422);
        }

        $wantsPrimary = $request->has('is_primary') ? $request->boolean('is_primary') : ($request->has('is_default') ? $request->boolean('is_default') : false);

        if ($wantsPrimary) {
            $resetData = [];
            if (Schema::hasColumn('bank_accounts', 'is_primary')) {
                $resetData['is_primary'] = false;
            }
            if (Schema::hasColumn('bank_accounts', 'is_default')) {
                $resetData['is_default'] = false;
            }
            if (!empty($resetData)) {
                BankAccount::where('user_id', $user->id)
                    ->where('account_type', 'customer')
                    ->update($resetData);
            }
        }

        $updateData = [];
        if ($request->has('bank_name')) $updateData['bank_name'] = trim($request->bank_name);
        if ($request->has('account_title')) $updateData['account_title'] = trim($request->account_title);
        if ($request->has('iban')) $updateData['iban'] = strtoupper(trim($request->iban));
        if ($request->has('account_number')) $updateData['account_number'] = trim($request->account_number);
        if ($request->has('swift_code')) $updateData['swift_code'] = strtoupper(trim($request->swift_code));

        if ($request->has('is_primary') || $request->has('is_default')) {
            if (Schema::hasColumn('bank_accounts', 'is_primary')) {
                $updateData['is_primary'] = $wantsPrimary;
            }
            if (Schema::hasColumn('bank_accounts', 'is_default')) {
                $updateData['is_default'] = $wantsPrimary;
            }
        }

        $bankAccount->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Bank account updated successfully.',
            'data' => $bankAccount
        ]);
    }

    /**
     * Delete Customer Bank Account
     * DELETE /api/v1/customer/bank-accounts/{id}
     * POST /api/v1/customer/bank-accounts/{id}/delete
     */
    public function deleteBankAccount(Request $request, $id)
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

        $wasPrimary = ($bankAccount->is_primary ?? false) || ($bankAccount->is_default ?? false);
        $bankAccount->delete();

        if ($wasPrimary) {
            $nextPrimary = BankAccount::where('user_id', $user->id)
                ->where('account_type', 'customer')
                ->first();

            if ($nextPrimary) {
                $setPrimaryData = [];
                if (Schema::hasColumn('bank_accounts', 'is_primary')) {
                    $setPrimaryData['is_primary'] = true;
                }
                if (Schema::hasColumn('bank_accounts', 'is_default')) {
                    $setPrimaryData['is_default'] = true;
                }
                if (!empty($setPrimaryData)) {
                    $nextPrimary->update($setPrimaryData);
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Bank account deleted successfully.'
        ]);
    }
}
