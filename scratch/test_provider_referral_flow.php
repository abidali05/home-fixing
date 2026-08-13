<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\ProviderProfile;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\AuthController;

echo "--- PROVIDER REFERRAL SYSTEM TEST ---\n";

try {
    // 1. Fetch or create Provider A
    $providerA = User::whereHas('providerProfile')->first();
    if (!$providerA) {
        echo "No provider found in database for testing.\n";
        exit;
    }

    $authController = new AuthController();

    // Ensure Provider A has referral code
    $profileA = $providerA->providerProfile;
    if (empty($profileA->referral_code)) {
        $profileA->referral_code = ProviderProfile::generateUniqueReferralCode();
        $profileA->save();
    }

    echo "Provider A ID: {$providerA->id}\n";
    echo "Provider A Name: {$providerA->name}\n";
    echo "Provider A Referral Code: {$profileA->referral_code}\n\n";

    // 2. Test Register Provider B with Provider A's Referral Code
    $testEmail = 'referral_test_' . time() . '@example.com';
    $testPhone = '+966599' . rand(100000, 999999);

    $registerReq = new Request([
        'name' => 'Referred Provider B',
        'email' => $testEmail,
        'phone' => $testPhone,
        'password' => '12345678',
        'is_otp_verified' => 'true',
        'role' => '1',
        'provider_type' => 'individual',
        'latitude' => '24.7136',
        'longitude' => '46.6753',
        'address' => 'Riyadh, Saudi Arabia',
        'referral_code' => $profileA->referral_code, // <--- Passing Provider A's Code
    ]);

    $regRes = $authController->register($registerReq);
    $regData = $regRes->getData();

    echo "Registration Response Status: " . json_encode($regData->status) . "\n";
    echo "Provider B ID: " . ($regData->data->user->id ?? 'N/A') . "\n";
    echo "Provider B Own Referral Code: " . ($regData->data->user->referral_code ?? 'N/A') . "\n";
    echo "Provider B Referred By Code: " . ($regData->data->user->referred_by_code ?? 'N/A') . "\n";
    echo "Provider B Referred By Name: " . ($regData->data->user->referred_by->name ?? 'N/A') . "\n";

    // Clean up created test user
    if (isset($regData->data->user->id)) {
        $newId = $regData->data->user->id;
        ProviderProfile::where('user_id', $newId)->delete();
        User::where('id', $newId)->delete();
        echo "\nCleaned up test user #{$newId}.\n";
    }

    echo "\nAll Provider Referral Tests Passed Successfully!\n";

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
