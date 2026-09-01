<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\Api\AuthController;
use App\Models\User;

$user = User::find(149);
auth('sanctum')->setUser($user);

$controller = app(AuthController::class);
$response = $controller->get_profile();

echo "Profile API Output for Provider #149:\n" . json_encode($response->getData(), JSON_PRETTY_PRINT) . "\n";
