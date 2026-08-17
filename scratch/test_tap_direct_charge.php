<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Payment;
use App\Models\User;
use App\Models\JobRequestModel;
use App\Models\BidModel;
use App\Services\Payment\TapPaymentService;

echo "--- TAP DIRECT TOKENS TEST ---\n";

$user = User::first();
$job = JobRequestModel::first();
$bid = BidModel::first();

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

$tokens = ['tok_visa_success', 'tok_mada_success', 'tok_in_visa_success', 'src_card', 'src_sa.mada'];

foreach ($tokens as $token) {
    try {
        $res = $service->createCharge($payment, $token);
        echo "Token: {$token} | Status: " . ($res['status'] ?? 'N/A') . " | Response Code: " . ($res['response']['code'] ?? 'N/A') . " | Msg: " . ($res['response']['message'] ?? 'N/A') . "\n";
    } catch (\Throwable $e) {
        echo "Token: {$token} | Error: " . $e->getMessage() . "\n";
    }
}
