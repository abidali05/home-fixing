<?php

namespace App\Services\Banking;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
     * Verify IBAN using IBANAPI gateway with fallback validation
     */
    public function verify(string $iban): array
    {
        $cleanIban = strtoupper(preg_replace('/[^A-Z0-9]/', '', $iban));

        // Basic sanity check
        if (empty($cleanIban)) {
            return [
                'valid' => false,
                'message' => 'IBAN cannot be empty.',
                'data' => null,
            ];
        }

        if (strlen($cleanIban) < 15 || strlen($cleanIban) > 34) {
            return [
                'valid' => false,
                'message' => 'Invalid IBAN length.',
                'data' => null,
            ];
        }

        // 1. If API Key is configured, call official IBANAPI service
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

                $body = $response->json();

                if (is_array($body)) {
                    $resultCode = (int) ($body['result'] ?? 0);

                    if ($resultCode === 200) {
                        $bank = $body['data']['bank'] ?? ($body['bank_data'] ?? ($body['bank'] ?? []));
                        $city = $bank['city'] ?? null;
                        $country = $body['data']['country_name'] ?? 'Saudi Arabia';
                        $location = trim(($city ? $city . ', ' : '') . $country);

                        return [
                            'valid' => true,
                            'message' => 'IBAN verified successfully.',
                            'data' => [
                                'iban' => $cleanIban,
                                'bank_name' => $bank['bank_name'] ?? ($bank['name'] ?? 'Bank Account'),
                                'swift_code' => $bank['bic'] ?? ($bank['swift'] ?? null),
                                'bank_location' => !empty($location) ? $location : 'Saudi Arabia',
                                'city' => $city,
                                'country' => $country,
                            ],
                        ];
                    }

                    // Explicit rejection by IBANAPI (e.g., result: 400, "Invalid IBAN Number")
                    return [
                        'valid' => false,
                        'message' => $body['message'] ?? 'Invalid IBAN number.',
                        'data' => null,
                    ];
                }
            } catch (\Throwable $exception) {
                Log::warning('IBANAPI external call failed: ' . $exception->getMessage());
            }
        }

        // 2. Fallback Validation (if API key missing or external network error)
        return $this->fallbackValidate($cleanIban);
    }

    /**
     * Local Fallback Validation for Saudi IBANs (SA + 22 characters = 24 chars) with ISO 7064 MOD-97 check
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

        // Validate ISO 7064 MOD-97 Checksum
        if (!$this->validateMod97($iban)) {
            return [
                'valid' => false,
                'message' => 'Invalid IBAN checksum.',
                'data' => null,
            ];
        }

        // Saudi Arabia Specific IBAN Validation (Starts with SA, 24 characters)
        if (str_starts_with($iban, 'SA')) {
            if (strlen($iban) !== 24) {
                return [
                    'valid' => false,
                    'message' => 'Saudi Arabia IBAN must be exactly 24 characters.',
                    'data' => null,
                ];
            }

            // Extract Bank Code from Saudi IBAN (digits 5..6)
            $bankCode = substr($iban, 4, 2);
            $saudiBanks = [
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

            $bankInfo = $saudiBanks[$bankCode] ?? [
                'name' => 'Saudi Commercial Bank',
                'swift' => null,
                'location' => 'Saudi Arabia',
            ];

            return [
                'valid' => true,
                'message' => 'IBAN format validated successfully.',
                'data' => [
                    'iban' => $iban,
                    'bank_name' => $bankInfo['name'],
                    'swift_code' => $bankInfo['swift'],
                    'bank_location' => $bankInfo['location'],
                    'city' => null,
                    'country' => 'Saudi Arabia',
                ],
            ];
        }

        // Generic International IBAN
        return [
            'valid' => true,
            'message' => 'IBAN format validated.',
            'data' => [
                'iban' => $iban,
                'bank_name' => 'Bank Account',
                'swift_code' => null,
                'bank_location' => substr($iban, 0, 2),
                'city' => null,
                'country' => substr($iban, 0, 2),
            ],
        ];
    }

    /**
     * Validate IBAN checksum using standard MOD-97 algorithm (ISO 7064)
     */
    private function validateMod97(string $iban): bool
    {
        if (strlen($iban) < 4) {
            return false;
        }

        $reordered = substr($iban, 4) . substr($iban, 0, 4);
        $numeric = '';
        foreach (str_split($reordered) as $char) {
            if (ctype_digit($char)) {
                $numeric .= $char;
            } elseif (ctype_alpha($char)) {
                $numeric .= (ord($char) - 55);
            } else {
                return false;
            }
        }

        if (function_exists('bcmod')) {
            return bcmod($numeric, '97') === '1';
        }

        // Fallback chunked modulo without bcmath
        $remainder = 0;
        foreach (str_split($numeric, 7) as $chunk) {
            $remainder = ($remainder . $chunk) % 97;
        }

        return $remainder === 1;
    }
}