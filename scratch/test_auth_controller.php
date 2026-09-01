<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\Api\AuthController;
use App\Services\AuthenticaService;
use Illuminate\Http\Request;

$controller = app(AuthController::class);
$authenticaService = app(AuthenticaService::class);

// Test balance
$balance = $authenticaService->getBalance();
echo "Authentica Balance API Result: " . json_encode($balance) . "\n";

// Test Test Number Bypass
$req1 = Request::create('/api/v1/send-otp', 'POST', [
    'phone' => '+966531301053'
]);

$res1 = $controller->send_otp($req1, $authenticaService);
echo "Send OTP Result: " . json_encode($res1->getData()) . "\n";

$req2 = Request::create('/api/v1/verify-otp', 'POST', [
    'phone' => '+966531301053',
    'otp' => '123456'
]);

$res2 = $controller->verify_otp($req2, $authenticaService);
echo "Verify OTP Result: " . json_encode($res2->getData()) . "\n";
