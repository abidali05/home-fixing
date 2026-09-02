<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\Admin\SystemSettingController;
use App\Models\User;
use Illuminate\Http\Request;

$admin = User::where('role', 0)->first() ?? User::first();
auth('admin')->setUser($admin);

$controller = app(SystemSettingController::class);
$response = $controller->index(Request::create('/settings', 'GET'));

echo "System Settings View loaded successfully: " . $response->name() . "\n";
$settingsData = $response->getData()['settings'];
echo "Marketplace Product VAT Percentage: " . ($settingsData->marketplace_vat_percentage ?? '15.00') . "%\n";
