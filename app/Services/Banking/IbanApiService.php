<?php

namespace App\Services\Banking;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class IbanApiService
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(
            (string) (config('services.ibanapi.base_url') ?: 'https://api.ibanapi.com/v1'),
            '/'
        );

        $this->apiKey = (string) (config('services.ibanapi.key') ?: '');
    }

    /**
     * Verify IBAN using IBANAPI gateway with fallback format validation
     */
    public function verify(string $iban): array
    {
        $cleanIban = strtoupper(preg_replace('/[^A-Z0-9]/', '', $iban));

        // Format check
        if (empty($cleanIban)) {
            return [
                'valid' => false,
                'message' => 'IBAN cannot be empty.',
                'data' => null,
            ];
        }

        // 1. If API Key is configured, attempt IBANAPI service call
        if (!empty($this->apiKey)) {
            try {
                $response = Http::asForm()
                    ->acceptJson()
                    ->connectTimeout(5)
                    ->timeout(10)
                    ->post("{$this->baseUrl}/validate", [
                        'iban' => $cleanIban,
                        'api_key' => $this->apiKey,
                    ]);

                if ($response->successful()) {
                    $body = $response->json();
                    if (is_array($body)) {
                        $resultValid = filter_var($body['result'] ?? false, FILTER_VALIDATE_BOOLEAN) || ($body['result'] ?? '') == '200';
                        $bankData = $body['bank_data'] ?? $body['bank'] ?? [];

                        return [
                            'valid' => $resultValid,
                            'message' => $resultValid ? 'IBAN verified successfully.' : ($body['message'] ?? 'Invalid IBAN format or check digits.'),
                            'data' => [
                                'iban' => $cleanIban,
                                'bank_name' => $bankData['name'] ?? $bankData['bank_name'] ?? 'Bank Account',
                                'swift_code' => $bankData['bic'] ?? $bankData['swift'] ?? null,
                                'country' => $bankData['country'] ?? 'Saudi Arabia',
                            ],
                        ];
                    }
                }
            } catch (\Throwable $exception) {
                Log::warning('IBANAPI external call failed, using fallback validation: ' . $exception->getMessage());
            }
        }

        // 2. Fallback Format Validation (Saudi Arabia & International IBANs)
        return $this->fallbackValidate($cleanIban);
    }

    /**
     * Local Fallback Validation for Saudi IBANs (SA + 22 characters = 24 chars)
     */
    private function fallbackValidate(string $iban): array
    {
        // General IBAN length check (15-34 chars)
        if (strlen($iban) < 15 || strlen($iban) > 34) {
            return [
                'valid' => false,
                'message' => 'Invalid IBAN length.',
                'data' => null,
            ];
        }

        // Saudi Arabia Specific IBAN Validation (Starts with SA, 24 characters)
        if (str_starts_with($iban, 'SA')) {
            if (strlen($iban) !== 24) {
                return [
                    'valid' => false,
                    'message' => 'Saudi Arabia IBAN must be exactly 24 characters (e.g. SA2810000011100000461309).',
                    'data' => null,
                ];
            }

            // Extract Bank Code from Saudi IBAN (digits 5..6)
            $bankCode = substr($iban, 4, 2);
            $saudiBanks = [
                '10' => 'NCB / Saudi National Bank (SNB)',
                '15' => 'Bank AlJazira',
                '20' => 'Al Inma Bank',
                '25' => 'Al Rajhi Bank',
                '30' => 'Arab National Bank (ANB)',
                '40' => 'Saudi British Bank (SAB)',
                '45' => 'Saudi Fransi Bank',
                '55' => 'Banque Saudi Fransi',
                '60' => 'Bank Albilad',
                '65' => 'Saudi Investment Bank (SAIB)',
                '80' => 'Riyad Bank',
            ];

            $bankName = $saudiBanks[$bankCode] ?? 'Saudi Bank';

            return [
                'valid' => true,
                'message' => 'IBAN format validated successfully.',
                'data' => [
                    'iban' => $iban,
                    'bank_name' => $bankName,
                    'swift_code' => null,
                    'country' => 'Saudi Arabia',
                ],
            ];
        }

        // Generic International IBAN Regex Validation
        $isValidFormat = (bool) preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/', $iban);

        return [
            'valid' => $isValidFormat,
            'message' => $isValidFormat ? 'IBAN format validated.' : 'Invalid IBAN characters or format.',
            'data' => $isValidFormat ? [
                'iban' => $iban,
                'bank_name' => 'Bank Account',
                'swift_code' => null,
                'country' => substr($iban, 0, 2),
            ] : null,
        ];
    }
}