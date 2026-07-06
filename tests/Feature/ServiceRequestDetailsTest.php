<?php

use App\Models\User;
use App\Models\JobRequestModel;
use App\Models\BidModel;
use App\Models\Orders;
use App\Models\Reviews;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->roleCustomer = 0;
    DB::table('roles')->insert([
        'id' => 0,
        'name' => 'User',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->roleProvider = 1;
    DB::table('roles')->insert([
        'id' => 1,
        'name' => 'Provider',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->country = DB::table('countries')->insertGetId([
        'name' => 'United States',
        'country_code' => 'US',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->category = DB::table('categories')->insertGetId([
        'name' => 'Plumbing',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

test('service request details returns null for hired provider and order status when no order exists', function () {
    $user = User::factory()->create([
        'role' => $this->roleCustomer,
        'country' => $this->country,
    ]);

    $job = JobRequestModel::create([
        'user_id' => $user->id,
        'category_id' => $this->category,
        'description' => 'Fix leak',
        'job_date' => '2026-06-20',
        'job_time' => '14:30',
        'price' => 100,
        'status' => 'pending',
        'address' => '123 Test St',
        'latitude' => 37.7749,
        'longitude' => -122.4194,
    ]);

    $response = $this->actingAs($user, 'sanctum')->getJson("/api/v1/service-request-details/{$job->id}");

    $response->assertStatus(200);
    $response->assertJsonPath('data.hired_provider', null);
    $response->assertJsonPath('data.order_status', null);
});

test('service request details returns hired provider and order status when order exists', function () {
    $user = User::factory()->create([
        'role' => $this->roleCustomer,
        'country' => $this->country,
    ]);

    $provider = User::factory()->create([
        'role' => $this->roleProvider,
        'country' => $this->country,
        'provider_status' => 'active',
        'profile_image' => 'provider_avatar.png'
    ]);

    $job = JobRequestModel::create([
        'user_id' => $user->id,
        'category_id' => $this->category,
        'description' => 'Fix leak',
        'job_date' => '2026-06-20',
        'job_time' => '14:30',
        'price' => 100,
        'status' => 'quoted',
        'address' => '123 Test St',
        'latitude' => 37.7749,
        'longitude' => -122.4194,
    ]);

    $order = Orders::create([
        'provider_id' => $provider->id,
        'user_id' => $user->id,
        'job_id' => $job->id,
        'source' => 'bid',
        'address' => $job->address,
        'details' => $job->description,
        'price' => $job->price,
        'status' => 'working',
        'paid_to_system' => 0,
    ]);

    $response = $this->actingAs($user, 'sanctum')->getJson("/api/v1/service-request-details/{$job->id}");

    $response->assertStatus(200);
    $response->assertJsonPath('data.order_status', 'working');
    $response->assertJsonPath('data.hired_provider.id', $provider->id);
    $response->assertJsonPath('data.hired_provider.profile_image', asset('uploads/profile_images/provider_avatar.png'));
});

test('post service request creates an order with status open and null provider', function () {
    $user = User::factory()->create([
        'role' => $this->roleCustomer,
        'country' => $this->country,
    ]);

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/post-service-request', [
        'service_id' => $this->category,
        'description' => 'Need clean plumbing',
        'date' => '2026-07-10',
        'time' => '10:00',
        'address' => '456 Flow Rd',
        'latitude' => 40.7128,
        'longitude' => -74.0060,
        'place_pictures' => [
            \Illuminate\Http\UploadedFile::fake()->image('plumbing.jpg')
        ]
    ]);

    $response->assertStatus(200);

    // Verify order was created in database
    $job = JobRequestModel::latest('id')->first();
    $order = Orders::where('job_id', $job->id)->first();

    expect($order)->not->toBeNull();
    expect($order->status)->toBe('open');
    expect($order->provider_id)->toBeNull();
    expect($order->user_id)->toBe($user->id);
    expect($order->address)->toBe('456 Flow Rd');
});

test('direct hire creates an order with status open and specified provider', function () {
    $user = User::factory()->create([
        'role' => $this->roleCustomer,
        'country' => $this->country,
    ]);

    $provider = User::factory()->create([
        'role' => $this->roleProvider,
        'country' => $this->country,
        'provider_status' => 'active',
        'charge_type' => 'hourly',
        'charge_amount' => 50,
    ]);

    // Create provider profile
    $provider->providerProfile()->create([
        'service_category' => json_encode([$this->category]),
        'experience' => '5 years',
    ]);

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/direct-hire', [
        'service_id' => $this->category,
        'provider_id' => $provider->id,
        'address' => '789 Direct St',
        'latitude' => 37.7749,
        'longitude' => -122.4194,
        'description' => 'Fix leak immediately',
        'job_date' => '2026-07-20',
        'job_time' => '12:00',
    ]);

    $response->assertStatus(200);

    // Verify order was created in database
    $job = JobRequestModel::latest('id')->first();
    $order = Orders::where('job_id', $job->id)->first();

    expect($order)->not->toBeNull();
    expect($order->status)->toBe('open');
    expect($order->provider_id)->toBe($provider->id);
    expect($order->user_id)->toBe($user->id);
    expect($order->address)->toBe('789 Direct St');
    expect($order->source)->toBe('direct_hiring');
});

test('my orders endpoint returns open orders list', function () {
    $user = User::factory()->create([
        'role' => $this->roleCustomer,
        'country' => $this->country,
    ]);

    $job = JobRequestModel::create([
        'user_id' => $user->id,
        'category_id' => $this->category,
        'description' => 'Fix leak',
        'job_date' => '2026-06-20',
        'job_time' => '14:30',
        'price' => 100,
        'status' => 'pending',
        'address' => '123 Test St',
        'latitude' => 37.7749,
        'longitude' => -122.4194,
    ]);

    Orders::create([
        'provider_id' => null,
        'user_id' => $user->id,
        'job_id' => $job->id,
        'source' => 'bid',
        'address' => $job->address,
        'details' => $job->description,
        'price' => $job->price,
        'status' => 'open',
        'paid_to_system' => 0,
    ]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/my-orders');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            'ongoing_orders',
            'completed_orders',
            'scheduled_orders',
            'cancelled_orders',
            'open_orders',
        ]
    ]);

    $response->assertJsonCount(1, 'data.open_orders');
    $response->assertJsonPath('data.open_orders.0.status', 'open');
});

test('provider home endpoint returns latest orders of provider', function () {
    $user = User::factory()->create([
        'role' => $this->roleCustomer,
        'country' => $this->country,
    ]);

    $provider = User::factory()->create([
        'role' => $this->roleProvider,
        'country' => $this->country,
        'provider_status' => 'active',
    ]);

    // Create provider profile
    $provider->providerProfile()->create([
        'service_category' => json_encode([$this->category]),
        'experience' => '5 years',
    ]);

    $job = JobRequestModel::create([
        'user_id' => $user->id,
        'category_id' => $this->category,
        'description' => 'Fix leak',
        'job_date' => '2026-06-20',
        'job_time' => '14:30',
        'price' => 100,
        'status' => 'quoted',
        'address' => '123 Test St',
        'latitude' => 37.7749,
        'longitude' => -122.4194,
    ]);

    $order = Orders::create([
        'provider_id' => $provider->id,
        'user_id' => $user->id,
        'job_id' => $job->id,
        'source' => 'bid',
        'address' => $job->address,
        'details' => $job->description,
        'price' => $job->price,
        'status' => 'pending',
        'paid_to_system' => 0,
    ]);

    $response = $this->actingAs($provider, 'sanctum')->getJson('/api/v1/provider-home');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            'post_requests',
            'direct_hires',
            'orders',
        ]
    ]);

    $response->assertJsonCount(1, 'data.orders');
    $response->assertJsonPath('data.orders.0.id', $order->id);
});

test('provider reviews endpoint returns reviews of provider with user details', function () {
    $user = User::factory()->create([
        'name' => 'John Doe',
        'role' => $this->roleCustomer,
        'country' => $this->country,
    ]);

    $provider = User::factory()->create([
        'role' => $this->roleProvider,
        'country' => $this->country,
        'provider_status' => 'active',
    ]);

    $job = JobRequestModel::create([
        'user_id' => $user->id,
        'category_id' => $this->category,
        'description' => 'Fix leak',
        'job_date' => '2026-06-20',
        'job_time' => '14:30',
        'price' => 100,
        'status' => 'quoted',
        'address' => '123 Test St',
        'latitude' => 37.7749,
        'longitude' => -122.4194,
    ]);

    $order = Orders::create([
        'provider_id' => $provider->id,
        'user_id' => $user->id,
        'job_id' => $job->id,
        'source' => 'bid',
        'address' => $job->address,
        'details' => $job->description,
        'price' => $job->price,
        'status' => 'completed',
        'paid_to_system' => 0,
    ]);

    Reviews::create([
        'order_id' => $order->id,
        'user_id' => $user->id,
        'provider_id' => $provider->id,
        'rating' => 5,
        'review' => 'Excellent service!',
    ]);

    $response = $this->actingAs($provider, 'sanctum')->getJson('/api/v1/provider-reviews');

    $response->assertStatus(200);
    $response->assertJsonCount(1, 'data.data');
    $response->assertJsonPath('data.data.0.rating', 5);
    $response->assertJsonPath('data.data.0.review', 'Excellent service!');
    $response->assertJsonPath('data.data.0.user.name', 'John Doe');
});
