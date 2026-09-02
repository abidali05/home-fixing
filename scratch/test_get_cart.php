<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\Api\AuthController;
use App\Models\Cart;
use App\Models\Product;
use App\Models\User;

$user = User::find(141) ?? User::first();
auth('sanctum')->setUser($user);

// Clear cart and add a 100 SAR product
Cart::where('user_id', $user->id)->delete();
$product = Product::first();
if ($product) {
    $product->sale_price = 100.00;
    $product->save();

    Cart::create([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'base_price' => 100.00,
        'total_price' => 100.00
    ]);
}

$controller = app(AuthController::class);
$response = $controller->getCart();

echo "GET /cart Response for 100 SAR Product:\n" . json_encode($response->getData(), JSON_PRETTY_PRINT) . "\n";
