<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Orders;
use App\Http\Controllers\Admin\OrderController;

echo "--- TESTING ORDER RECEIPT GENERATION ---\n";

try {
    $order = Orders::first();

    if (!$order) {
        echo "No order found in database.\n";
        exit;
    }

    echo "Testing Order #{$order->id}...\n";

    $controller = new OrderController();
    $res = $controller->receipt($order->id);

    echo "Receipt View Name: " . $res->name() . "\n";
    echo "Receipt No: " . ($res->getData()['receiptNo'] ?? 'N/A') . "\n";
    echo "Category: " . ($res->getData()['categoryName'] ?? 'N/A') . "\n";
    echo "Details: " . ($res->getData()['detailsText'] ?? 'N/A') . "\n";

    echo "\nOrder Receipt Test Passed Successfully!\n";

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
