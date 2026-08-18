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
            config('services.ibanapi.base_url'),
            '/'
        );

        $this->apiKey = config('services.ibanapi.key');

        if (empty($this->apiKey)) {
            throw new RuntimeException(
                'IBAN API key is not configured.'
            );
        }
    }

    public function verify(string $iban): array
    {
        try {

            $response = Http::asForm()
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(10)
                ->post(
                    "{$this->baseUrl}/validate",
                    [
                        'iban' => $iban,
                        'api_key' => $this->apiKey,
                    ]
                );

        } catch (ConnectionException $exception) {

            Log::error('IBANAPI connection error', [
                'error' => $exception->getMessage(),
            ]);

            throw new RuntimeException(
                'IBAN verification service is currently unavailable.'
            );
        }

        $body = $response->json();

        if (!is_array($body)) {

            Log::error('Invalid IBANAPI response', [
                'status' => $response->status(),
            ]);

            throw new RuntimeException(
                'Invalid response from IBAN verification service.'
            );
        }

        /*
         * Third-party API error
         */
        if (!$response->successful()) {

            Log::warning('IBANAPI request failed', [
                'status' => $response->status(),
                'result' => $body['result'] ?? null,
                'message' => $body['message'] ?? null,
            ]);

            if ($response->status() === 401) {
                throw new RuntimeException(
                    'IBAN verification configuration error.'
                );
            }

            return [
                'valid' => false,
                'message' => $body['message'] ?? 'Invalid IBAN.',
                'data' => null,
            ];
        }

        /*
         * IBANAPI uses result = 200 for successful validation.
         */
        if (($body['result'] ?? null) != 200) {

            return [
                'valid' => false,
                'message' => $body['message'] ?? 'Invalid IBAN.',
                'data' => null,
            ];
        }

        $data = $body['data'] ?? [];
        $bank = $data['bank'] ?? [];

        return [
            'valid' => true,

            'message' => 'IBAN verified successfully.',

            'data' => [

                'iban' => $this->maskIban($iban),

                'country' => [
                    'code' => $data['country_code'] ?? null,
                    'name' => $data['country_name'] ?? null,
                    'currency' => $data['currency_code'] ?? null,
                ],

                'bank' => [
                    'name' => $bank['bank_name'] ?? null,
                    'bic' => $bank['bic'] ?? null,
                    'phone' => $bank['phone'] ?? null,
                    'address' => $bank['address'] ?? null,
                    'city' => $bank['city'] ?? null,
                    'state' => $bank['state'] ?? null,
                    'zip' => $bank['zip'] ?? null,
                ],
            ],
        ];
    }

    private function maskIban(string $iban): string
    {
        return substr($iban, 0, 4)
            . str_repeat('*', strlen($iban) - 8)
            . substr($iban, -4);
    }
}