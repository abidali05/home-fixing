<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Payment;
use App\Models\User;
use App\Models\JobRequestModel;
use App\Models\BidModel;
use Illuminate\Support\Facades\Http;

echo "--- TAP SANDBOX AMOUNT TRIGGER TEST ---\n";

$secretKey = config('services.tap.secret_key');
$payment = Payment::latest()->first();

foreach ([10.00, 25.00, 100.00] as $amt) {
    $payload = [
        'amount' => $amt,
        'currency' => 'SAR',
        'threeDSecure' => true,
        'save_card' => false,
        'description' => "Test amount {$amt}",
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
        echo "Amount={$amt} | Charge ID: " . ($data['id'] ?? 'N/A') . " | Status: " . ($data['status'] ?? 'N/A') . " | URL: " . ($data['transaction']['url'] ?? 'N/A') . "\n";
    } catch (\Throwable $e) {
        echo "Amount={$amt} | Error: " . $e->getMessage() . "\n";
    }
}
