<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\Api\WithdrawalController;
use App\Models\User;
use Illuminate\Http\Request;

$user = User::find(149) ?: User::first();
auth('sanctum')->setUser($user);

$controller = app(WithdrawalController::class);

// Test 1: Hit marketplace/seller/transactions WITHOUT query param account_type
$req1 = Request::create('/api/v1/marketplace/seller/transactions', 'GET');
$res1 = $controller->transactionHistory($req1);
echo "Hit /api/v1/marketplace/seller/transactions (User ID {$user->id}):\n" . json_encode($res1->getData(), JSON_PRETTY_PRINT) . "\n\n";

// Test 2: Hit provider/wallet/transactions WITHOUT query param account_type
$req2 = Request::create('/api/v1/provider/wallet/transactions', 'GET');
$res2 = $controller->transactionHistory($req2);
echo "Hit /api/v1/provider/wallet/transactions (User ID {$user->id}):\n" . json_encode($res2->getData(), JSON_PRETTY_PRINT) . "\n";
