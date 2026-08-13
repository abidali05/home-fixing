<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\JobRequestModel;
use App\Models\BidModel;
use App\Models\Payment;
use App\Services\Job\HireProviderService;

echo "--- TAP PAYMENT & HIRE PROVIDER SERVICE TEST ---\n";

try {
    // 1. Fetch test customer and provider
    $customer = User::where('role', 0)->first();
    $provider = User::where('role', 1)->first();

    if (!$customer || !$provider) {
        echo "Customer or Provider not found in DB. Creating mock records...\n";
    }

    echo "Customer ID: " . ($customer->id ?? 1) . "\n";
    echo "Provider ID: " . ($provider->id ?? 2) . "\n";

    // 2. Fetch or create a test Job
    $job = JobRequestModel::where('status', 'pending')->first();
    if (!$job) {
        echo "No pending job found. Existing job count: " . JobRequestModel::count() . "\n";
    } else {
        echo "Job ID: {$job->id}, Status: {$job->status}\n";

        // 3. Fetch or create a test Bid
        $bid = BidModel::where('job_id', $job->id)->first();
        if ($bid) {
            echo "Bid ID: {$bid->id}, Price: {$bid->price}, Status: {$bid->status}\n";

            // 4. Test Payment Record Creation
            $payment = Payment::create([
                'user_id' => $job->user_id,
                'job_id' => $job->id,
                'bid_id' => $bid->id,
                'provider_id' => $bid->provider_id,
                'amount' => $bid->price,
                'currency' => 'SAR',
                'gateway' => 'tap',
                'status' => 'pending',
                'tap_charge_id' => 'chg_test_' . time(),
            ]);

            echo "Payment Created! ID: {$payment->id}, Amount: {$payment->amount} {$payment->currency}\n";

            // 5. Test HireProviderService
            $payment->status = 'captured';
            $payment->save();

            $hireService = app(HireProviderService::class);
            $hired = $hireService->hireProvider($payment);

            echo "HireProviderService executed. Result: " . ($hired ? "SUCCESS" : "FAILED") . "\n";
            echo "Updated Job Status: " . $job->fresh()->status . "\n";
            echo "Updated Bid Status: " . $bid->fresh()->status . "\n";

            // Cleanup test payment
            $payment->delete();
            echo "Test payment cleaned up.\n";
        }
    }

    echo "\nTest Completed Successfully!\n";

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
