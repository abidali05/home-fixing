<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\Admin\DashboardController;

echo "--- TESTING DASHBOARD CONTROLLER ---\n";

try {
    $controller = new DashboardController();
    $res = $controller->index();

    echo "Dashboard View Name: " . $res->name() . "\n";
    echo "Cards count: " . count($res->getData()['cards']) . "\n";
    echo "Service Revenue: " . ($res->getData()['financialSummary']['service_revenue'] ?? 'N/A') . "\n";

    echo "\nDashboard Test Passed Successfully!\n";

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
