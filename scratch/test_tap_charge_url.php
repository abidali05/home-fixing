<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Payment;
use App\Models\User;
use App\Models\JobRequestModel;
use App\Models\BidModel;
use App\Services\Payment\TapPaymentService;

echo "--- TAP CHARGE URL TEST ---\n";

$user = User::first();
$job = JobRequestModel::first();
$bid = BidModel::first();

if (!$user || !$job || !$bid) {
    echo "Required database records missing.\n";
    exit;
}

$payment = Payment::create([
    'user_id' => $user->id,
    'job_id' => $job->id,
    'bid_id' => $bid->id,
    'provider_id' => $user->id,
    'amount' => 10.00,
    'currency' => 'SAR',
    'gateway' => 'tap',
    'status' => 'pending',
]);

$service = app(TapPaymentService::class);
try {
    $res = $service->createCharge($payment, 'src_all');
    echo "Charge ID: " . ($res['id'] ?? 'N/A') . "\n";
    echo "Transaction URL: " . ($res['transaction']['url'] ?? 'N/A') . "\n";
    echo "Full Transaction Data:\n";
    print_r($res['transaction'] ?? []);
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
