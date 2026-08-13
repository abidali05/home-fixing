<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\AuthController;

echo "--- INVALID REFERRAL CODE TEST ---\n";

try {
    $user = User::first();
    auth('sanctum')->setUser($user);

    $authController = new AuthController();

    // 1. Test becomeProvider with INVALID Referral Code
    $becomeReq = new Request([
        'provider_type' => 'individual',
        'latitude' => '24.7136',
        'longitude' => '46.6753',
        'address' => 'Riyadh',
        'referral_code' => 'INVALID_REF_99999', // <--- WRONG CODE
    ]);

    $res1 = $authController->becomeProvider($becomeReq);
    echo "Become Provider (Invalid Code) Status: " . $res1->getStatusCode() . "\n";
    echo "Become Provider Response: " . json_encode($res1->getData()) . "\n\n";

    // 2. Test Register with INVALID Referral Code
    $regReq = new Request([
        'name' => 'Wrong Code User',
        'email' => 'wrongcode@example.com',
        'phone' => '+966599999999',
        'password' => '12345678',
        'is_otp_verified' => 'true',
        'role' => '1',
        'provider_type' => 'individual',
        'latitude' => '24.7136',
        'longitude' => '46.6753',
        'address' => 'Riyadh',
        'referral_code' => 'INVALID_REF_99999', // <--- WRONG CODE
    ]);

    $res2 = $authController->register($regReq);
    echo "Register (Invalid Code) Status: " . $res2->getStatusCode() . "\n";
    echo "Register Response: " . json_encode($res2->getData()) . "\n";

    echo "\nInvalid Referral Code Test Completed Successfully!\n";

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
