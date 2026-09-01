<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\Api\PaymentController;
use App\Models\BidModel;
use App\Models\JobRequestModel;
use App\Models\User;
use Illuminate\Http\Request;

$bid = BidModel::first();
if (!$bid) {
    echo "No bid found.\n";
    exit;
}

$job = JobRequestModel::find($bid->job_id);
$customer = User::find($job->user_id);
auth('sanctum')->setUser($customer);

// Set job status to pending for testing initiate
$job->status = 'pending';
$job->save();

$controller = app(PaymentController::class);

$req = Request::create("/api/jobs/{$job->id}/bids/{$bid->id}/initiate-payment", 'POST');
$res = $controller->initiatePayment($req, $job->id, $bid->id);

echo "Output for POST jobs/{$job->id}/bids/{$bid->id}/initiate-payment:\n" . json_encode($res->getData(), JSON_PRETTY_PRINT) . "\n";
