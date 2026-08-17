<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Payment;
use App\Models\User;
use App\Models\JobRequestModel;
use App\Models\BidModel;
use Illuminate\Support\Facades\Http;

echo "--- TAP 3DS CONFIGURATION TEST ---\n";

$secretKey = config('services.tap.secret_key');
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

// Test 3DS = true vs 3DS = false
foreach ([true, false] as $threeDS) {
    $payload = [
        'amount' => 10.00,
        'currency' => 'SAR',
        'threeDSecure' => $threeDS,
        'save_card' => false,
        'description' => "Test 3DS {$threeDS}",
        'statement_descriptor' => "AZHL TEST",
        'metadata' => [
            'payment_id' => (string) $payment->id,
        ],
        'customer' => [
            'first_name' => 'Test',
            'email' => 'customer@azhl.com',
            'phone' => ['country_code' => '966', 'number' => '500000000'],
        ],
        'source' => ['id' => 'src_all'],
        'redirect' => ['url' => 'https://admin.azhlksa.com/tap/redirect'],
        'post' => ['url' => 'https://admin.azhlksa.com/api/v1/webhooks/tap'],
    ];

    try {
        $res = Http::timeout(30)->withToken($secretKey)->acceptJson()->post('https://api.tap.company/v2/charges', $payload);
        $data = $res->json();
        echo "threeDSecure=" . ($threeDS ? 'true' : 'false') . " | HTTP: " . $res->status() . " | Charge ID: " . ($data['id'] ?? 'N/A') . " | Status: " . ($data['status'] ?? 'N/A') . " | URL: " . ($data['transaction']['url'] ?? 'N/A') . "\n";
    } catch (\Throwable $e) {
        echo "threeDSecure=" . ($threeDS ? 'true' : 'false') . " | Error: " . $e->getMessage() . "\n";
    }
}
