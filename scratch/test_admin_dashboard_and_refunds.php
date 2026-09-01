<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\RefundAdminController;
use App\Models\User;
use Illuminate\Http\Request;

$admin = User::where('role', 0)->first() ?? User::first();
auth('admin')->setUser($admin);

$dashController = app(DashboardController::class);
$dashRes = $dashController->index();
echo "Dashboard loaded successfully. View name: " . $dashRes->name() . "\n";

$refundController = app(RefundAdminController::class);
$req = Request::create('/refunds', 'GET');
$req->headers->set('Accept', 'application/json');
$refundRes = $refundController->index($req);
echo "Refunds API Output:\n" . json_encode($refundRes->getData(), JSON_PRETTY_PRINT) . "\n";
