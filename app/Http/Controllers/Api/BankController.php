<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\VerifyIbanRequest;
use App\Services\Banking\IbanApiService;
use RuntimeException;

class BankController extends Controller
{
    public function verifyIban(
        VerifyIbanRequest $request,
        IbanApiService $ibanService
    ) {
        try {

            $result = $ibanService->verify(
                $request->iban
            );
            if (!$result['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'data' => null,
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result['data'],
            ], 200);

        } catch (RuntimeException $exception) {

            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Unable to verify IBAN at the moment.',
                'data' => null,
            ], 503);
        }
    }
}