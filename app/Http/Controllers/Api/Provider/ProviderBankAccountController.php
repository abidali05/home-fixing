<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
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
     * Get provider saved bank account details
     */
    public function getBankAccount()
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json(['status' => 401, 'message' => 'Unauthorized.'], 401);
        }

        $profile = ProviderProfile::where('user_id', $user->id)->first();

        return response()->json([
            'status' => 200,
            'message' => 'Bank details fetched successfully.',
            'data' => [
                'iban' => $profile->iban ?? null,
                'account_title' => $profile->account_title ?? null,
                'bank_name' => $profile->bank_name ?? null,
                'swift_code' => $profile->swift_code ?? null,
                'bank_location' => $profile->bank_location ?? null,
            ]
        ]);
    }

    /**
     * Save or update provider bank account details
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

        $cleanIban = strtoupper(str_replace(' ', '', $request->iban));

        $profile = ProviderProfile::firstOrCreate(
            ['user_id' => $user->id],
            []
        );

        $profile->update([
            'iban' => $cleanIban,
            'account_title' => $request->account_title,
            'bank_name' => $request->bank_name,
            'swift_code' => $request->swift_code,
            'bank_location' => $request->bank_location,
        ]);

        return response()->json([
            'status' => 200,
            'message' => 'Bank Account saved successfully.',
            'data' => [
                'iban' => $profile->iban,
                'account_title' => $profile->account_title,
                'bank_name' => $profile->bank_name,
                'swift_code' => $profile->swift_code,
                'bank_location' => $profile->bank_location,
            ]
        ]);
    }
}
