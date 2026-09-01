<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\Api\GeneralContoller;
use App\Models\User;
use Illuminate\Http\Request;

$provider = User::find(149);
auth('sanctum')->setUser($provider);

$controller = app(GeneralContoller::class);

echo "--- 1. Testing Default Grouped Response ---\n";
$req1 = Request::create("/api/v1/provider-orders", 'GET');
$res1 = $controller->my_orders($req1);
$data1 = $res1->getData(true);
echo "Completed Orders Count: " . count($data1['data']['completed_orders']) . "\n";
if (!empty($data1['data']['completed_orders'])) {
    $first = $data1['data']['completed_orders'][0];
    echo "First Order Breakdown:\n" . json_encode($first['payment_breakdown'], JSON_PRETTY_PRINT) . "\n";
}

echo "\n--- 2. Testing Paginated Response (status=all&page=1&per_page=2) ---\n";
$req2 = Request::create("/api/v1/provider-orders?status=all&page=1&per_page=2", 'GET');
$res2 = $controller->my_orders($req2);
echo json_encode($res2->getData(true), JSON_PRETTY_PRINT) . "\n";
