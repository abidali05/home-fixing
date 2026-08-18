<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Payment;
use App\Models\User;
use App\Models\JobRequestModel;
use App\Models\BidModel;
use App\Services\Payment\TapPaymentService;

echo "--- TAP SOURCE TEST (src_sa.mada vs src_card vs src_all) ---\n";

$user = User::first();
$job = JobRequestModel::first();
$bid = BidModel::first();

if (!$user || !$job || !$bid) {
    echo "Records missing.\n";
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

foreach (['src_sa.mada', 'src_card', 'src_all'] as $src) {
    try {
        $res = $service->createCharge($payment, $src);
        echo "Source: {$src} | Status: " . ($res['status'] ?? 'N/A') . " | URL: " . ($res['transaction']['url'] ?? 'N/A') . "\n";
    } catch (\Throwable $e) {
        echo "Source: {$src} | Error: " . $e->getMessage() . "\n";
    }
}
