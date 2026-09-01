<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\Api\User\OrdersController;
use App\Models\Orders;
use App\Models\User;

$order = Orders::find(180) ?? Orders::first();
if (!$order) {
    echo "No order found for test.\n";
    exit;
}

$user = User::find($order->user_id) ?? User::find($order->provider_id) ?? User::first();
auth('sanctum')->setUser($user);

$controller = app(OrdersController::class);
$response = $controller->getReceipt($order->id);

echo "Receipt Output for Order #{$order->id}:\n" . json_encode($response->getData(), JSON_PRETTY_PRINT) . "\n";
