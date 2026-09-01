<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\Api\Customer\CustomerTransactionController;
use App\Models\User;
use Illuminate\Http\Request;

$customer = User::find(141);
auth('sanctum')->setUser($customer);

$controller = app(CustomerTransactionController::class);

$req = Request::create("/api/v1/customer/transactions", 'GET');
$res = $controller->transactionHistory($req);

echo "Output for GET customer/transactions:\n" . json_encode($res->getData(), JSON_PRETTY_PRINT) . "\n";
