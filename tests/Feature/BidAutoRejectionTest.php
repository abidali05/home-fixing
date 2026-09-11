<?php

use App\Models\User;
use App\Models\JobRequestModel;
use App\Models\BidModel;
use App\Notifications\BidAcceptedNotification;
use App\Notifications\BidRejectedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Setup roles
    DB::table('roles')->insert([
        ['id' => 0, 'name' => 'User', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 1, 'name' => 'Provider', 'created_at' => now(), 'updated_at' => now()],
    ]);

    // Setup country
    $this->countryId = DB::table('countries')->insertGetId([
        'name' => 'United States',
        'country_code' => 'US',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Setup category
    $this->categoryId = DB::table('categories')->insertGetId([
        'name' => 'Plumbing',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

test('accepting a bid automatically rejects all other pending bids and sends custom notification to rejected providers', function () {
    // Fake notifications so they don't actually call external APIs (Firebase, etc.)
    Notification::fake();

    // Create a customer
    $customer = User::factory()->create([
        'role' => 0,
        'country' => $this->countryId,
    ]);

    // Create providers
    $provider1 = User::factory()->create(['role' => 1, 'country' => $this->countryId]);
    $provider2 = User::factory()->create(['role' => 1, 'country' => $this->countryId]);
    $provider3 = User::factory()->create(['role' => 1, 'country' => $this->countryId]);

    // Create a job request by the customer
    $job = JobRequestModel::create([
        'user_id' => $customer->id,
        'category_id' => $this->categoryId,
        'description' => 'Fix the pipe leak',
        'job_date' => '2026-06-20',
        'job_time' => '12:00',
        'status' => 'pending',
        'address' => '123 Main St',
        'latitude' => 37.7749,
        'longitude' => -122.4194,
    ]);

    // Create corresponding order
    DB::table('orders')->insert([
        'provider_id' => null,
        'user_id' => $customer->id,
        'job_id' => $job->id,
        'source' => 'bid',
        'address' => $job->address,
        'details' => $job->description,
        'price' => $job->price ?? 0,
        'status' => 'open',
        'paid_to_system' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Create bids
    $bid1 = BidModel::create([
        'job_id' => $job->id,
        'provider_id' => $provider1->id,
        'bid_details' => 'I can do this for $100',
        'price' => 100.00,
        'status' => 'pending',
    ]);

    $bid2 = BidModel::create([
        'job_id' => $job->id,
        'provider_id' => $provider2->id,
        'bid_details' => 'I can do this for $90',
        'price' => 90.00,
        'status' => 'pending',
    ]);

    $bid3 = BidModel::create([
        'job_id' => $job->id,
        'provider_id' => $provider3->id,
        'bid_details' => 'I can do this for $110',
        'price' => 110.00,
        'status' => 'pending',
    ]);

    // Accept bid 1
    $response = $this->actingAs($customer, 'sanctum')
        ->postJson("/api/v1/accept-bid/{$bid1->id}");

    $response->assertStatus(200);

    // Refresh database instances
    $job->refresh();
    $bid1->refresh();
    $bid2->refresh();
    $bid3->refresh();

    // Verify bid and job statuses
    expect($job->status)->toBe('quoted');
    expect($bid1->status)->toBe('accepted');
    expect($bid2->status)->toBe('rejected');
    expect($bid3->status)->toBe('rejected');

    // Verify order was created
    $order = DB::table('orders')->where('job_id', $job->id)->first();
    expect($order)->not->toBeNull();
    expect($order->provider_id)->toBe($provider1->id);
    expect($order->user_id)->toBe($customer->id);

    // Assert that BidAcceptedNotification was sent to provider1
    Notification::assertSentTo(
        $provider1,
        BidAcceptedNotification::class,
        function ($notification) use ($job, $customer) {
            $array = $notification->toArray($customer);
            return (int)$array['data']['job_id'] === (int)$job->id;
        }
    );

    // Assert that BidRejectedNotification with custom message was sent to provider2 and provider3
    Notification::assertSentTo(
        $provider2,
        BidRejectedNotification::class,
        function ($notification) use ($job, $customer) {
            $array = $notification->toArray($customer);
            return $array['message'] === 'Better luck next time. Your offer was not accepted for this request.';
        }
    );

    Notification::assertSentTo(
        $provider3,
        BidRejectedNotification::class,
        function ($notification) use ($job, $customer) {
            $array = $notification->toArray($customer);
            return $array['message'] === 'Better luck next time. Your offer was not accepted for this request.';
        }
    );
});
