<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\GeneralContoller;

echo "--- FAVORITE MARKETPLACE TEST ---\n";

try {
    $user = User::first();
    $seller = User::whereHas('marketplaceProfile')->first();

    if (!$user || !$seller) {
        echo "User or Seller with marketplaceProfile not found.\n";
        exit;
    }

    auth('sanctum')->setUser($user);

    echo "User ID: {$user->id}\n";
    echo "Seller/Marketplace ID: {$seller->id}\n";

    $controller = new GeneralContoller();

    // 1. Test Toggle (Add)
    $req = new Request(['marketplace_id' => $seller->id]);
    $res1 = $controller->toggle_favorite_marketplace($req);
    echo "Toggle Add Response: " . json_encode($res1->getData()) . "\n";

    // 2. Test Get Favorite IDs
    $res2 = $controller->get_favorite_marketplace_ids();
    echo "Favorite IDs Response: " . json_encode($res2->getData()) . "\n";

    // 3. Test Get Favorite Marketplaces List
    $res3 = $controller->get_favorite_marketplace();
    echo "Favorite List Response: " . json_encode($res3->getData()) . "\n";

    // 4. Test Toggle (Remove)
    $res4 = $controller->toggle_favorite_marketplace($req);
    echo "Toggle Remove Response: " . json_encode($res4->getData()) . "\n";

    echo "\nTest Completed Successfully!\n";

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
