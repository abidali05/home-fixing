<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AuthenticaService
{
    protected string $baseUrl;
    protected string $apiKey;
    protected string $appHash;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.authentica.base_url', 'https://api.authentica.sa/api/v2'), '/');
        $this->apiKey = config('services.authentica.api_key', '$2y$10$ypBhodlFFB3Rb.YhEBjJq.Jr0XcydJONFKYxBu.elHOzgRqgSunuG');
        $this->appHash = config('services.authentica.app_hash', 'Ii43T702uXm');
    }

    /**
     * Send OTP via Authentica API (SMS / WhatsApp / Email)
     */
    public function sendOtp(string $phone, string $method = 'sms', ?string $appHash = null, ?string $customOtp = null): array
    {
        $hash = $appHash ?: $this->appHash;
        $otpCode = $customOtp ?: (string) random_int(100000, 999999);

        $messageText = "Your Azhl verification code is {$otpCode}\n{$hash}";

        $payload = [
            'phone' => $phone,
            'method' => $method ?: 'sms',
            'otp' => $otpCode,
            'message' => $messageText,
            'body' => $messageText,
            'text' => $messageText,
            'sms_text' => $messageText,
        ];

        if (!empty($hash)) {
            $payload['app_hash'] = $hash;
            $payload['hash'] = $hash;
        }

        Log::info("AuthenticaService: Sending 6-digit OTP ({$otpCode}) to {$phone} via {$method}", ['payload' => $payload]);

        $response = Http::withHeaders([
            'X-Authorization' => $this->apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->post("{$this->baseUrl}/send-otp", $payload);

        $responseData = $response->json() ?? [];

        Log::info("AuthenticaService: Send OTP response for {$phone}", [
            'http_code' => $response->status(),
            'response' => $responseData,
        ]);

        if ($response->failed() || (isset($responseData['success']) && !$responseData['success']) || (isset($responseData['status']) && !$responseData['status'])) {
            $errorMessage = $responseData['message'] ?? ($responseData['errors']['phone'][0] ?? 'Failed to send OTP via Authentica API.');
            Log::error("AuthenticaService: Failed to send OTP to {$phone}: {$errorMessage}");
            throw new \RuntimeException($errorMessage, $response->status() ?: 422);
        }

        return $responseData;
    }

    /**
     * Verify OTP via Authentica API
     */
    public function verifyOtp(string $phone, string $otp): bool
    {
        $payload = [
            'phone' => $phone,
            'otp' => (string) $otp,
        ];

        Log::info("AuthenticaService: Verifying OTP for {$phone}", ['payload' => $payload]);

        $response = Http::withHeaders([
            'X-Authorization' => $this->apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->post("{$this->baseUrl}/verify-otp", $payload);

        $responseData = $response->json() ?? [];

        Log::info("AuthenticaService: Verify OTP response for {$phone}", [
            'http_code' => $response->status(),
            'response' => $responseData,
        ]);

        if ($response->successful()) {
            $status = $responseData['status'] ?? ($responseData['success'] ?? false);
            if ($status) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check Authentica Account Balance
     */
    public function getBalance(): array
    {
        $response = Http::withHeaders([
            'X-Authorization' => $this->apiKey,
            'Accept' => 'application/json',
        ])->get("{$this->baseUrl}/balance");

        return $response->json() ?? [];
    }
}
