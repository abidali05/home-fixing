<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\Api\GeneralContoller;

$controller = app(GeneralContoller::class);
$response = $controller->system_settings();

echo "GET /system-settings Response:\n" . json_encode($response->getData(), JSON_PRETTY_PRINT) . "\n";
