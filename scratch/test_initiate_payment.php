<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\Api\Marketplace\MarketplacePaymentController;
use App\Models\Cart;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;

$user = User::find(141) ?? User::first();
auth('sanctum')->setUser($user);

// Clear cart and add a 20 SAR product
Cart::where('user_id', $user->id)->delete();
$product = Product::first();
if ($product) {
    $product->sale_price = 20.00;
    $product->save();

    Cart::create([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'base_price' => 20.00,
        'total_price' => 20.00
    ]);
}

$controller = app(MarketplacePaymentController::class);
$request = Request::create('/api/v1/marketplace/cart/initiate-payment', 'POST', [
    'shipping_address' => 'Riyadh, Saudi Arabia',
    'shipping_cost' => 0.00
]);

$response = $controller->initiateCartPayment($request);

echo "POST /marketplace/cart/initiate-payment Response:\n" . json_encode($response->getData(), JSON_PRETTY_PRINT) . "\n";
