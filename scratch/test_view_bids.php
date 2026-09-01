<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\Api\User\HiringController;
use App\Models\BidModel;

$bid = BidModel::first();
if (!$bid) {
    echo "No bid found in database for testing.\n";
    exit;
}

$jobId = $bid->job_id;

$controller = app(HiringController::class);
$response = $controller->view_bids_by_request($jobId);

echo "Output for view_bids_by_request/{$jobId}:\n" . json_encode($response->getData(), JSON_PRETTY_PRINT) . "\n";
