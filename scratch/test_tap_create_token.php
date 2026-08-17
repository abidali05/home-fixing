<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;
use App\Models\Payment;
use App\Services\Payment\TapPaymentService;

echo "--- TAP CREATE TOKEN & CHARGE TEST ---\n";

$secretKey = config('services.tap.secret_key');

$cards = [
    ['number' => '4000000000000002', 'month' => '12', 'year' => '28', 'cvc' => '123', 'name' => 'Mada Test'],
    ['number' => '4000000000000001', 'month' => '12', 'year' => '28', 'cvc' => '123', 'name' => 'Visa Test'],
    ['number' => '5888450000000001', 'month' => '12', 'year' => '28', 'cvc' => '123', 'name' => 'Mada Saudi'],
];

foreach ($cards as $c) {
    $res = Http::withToken($secretKey)->acceptJson()->post('https://api.tap.company/v2/tokens', [
        'card' => [
            'number' => $c['number'],
            'exp_month' => $c['month'],
            'exp_year' => $c['year'],
            'cvc' => $c['cvc'],
            'name' => $c['name'],
        ]
    ]);

    $data = $res->json();
    echo "Card: {$c['number']} | Token Status: " . ($res->status()) . " | Token ID: " . ($data['id'] ?? 'N/A') . " | Error: " . json_encode($data['errors'] ?? []) . "\n";

    if (isset($data['id'])) {
        $payment = Payment::latest()->first();
        if ($payment) {
            $service = app(TapPaymentService::class);
            try {
                $chargeRes = $service->createCharge($payment, $data['id']);
                echo "--> Charge ID: " . ($chargeRes['id'] ?? 'N/A') . " | Status: " . ($chargeRes['status'] ?? 'N/A') . " | Code: " . ($chargeRes['response']['code'] ?? 'N/A') . " | Msg: " . ($chargeRes['response']['message'] ?? 'N/A') . "\n";
            } catch (\Throwable $e) {
                echo "--> Charge Error: " . $e->getMessage() . "\n";
            }
        }
    }
}
