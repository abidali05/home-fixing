<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Payment;
use App\Models\User;
use App\Models\JobRequestModel;
use App\Models\BidModel;
use Illuminate\Support\Facades\Http;

$secretKey = config('services.tap.secret_key');
$payment = Payment::latest()->first();

$payload = [
    'amount' => 10.00,
    'currency' => 'SAR',
    'threeDSecure' => false,
    'save_card' => false,
    'description' => "Test 3DS false",
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

$res = Http::withToken($secretKey)->acceptJson()->post('https://api.tap.company/v2/charges', $payload);
print_r($res->json());
