<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\Api\WithdrawalController;
use App\Models\User;
use Illuminate\Http\Request;

$user = User::where('role', 1)->first() ?: User::first();
auth('sanctum')->setUser($user);

$controller = app(WithdrawalController::class);

$request = Request::create('/api/v1/marketplace/seller/transactions', 'GET', [
    'account_type' => 'marketplace'
]);

$response = $controller->transactionHistory($request);
echo "Marketplace Transactions Output:\n" . json_encode($response->getData(), JSON_PRETTY_PRINT) . "\n";
