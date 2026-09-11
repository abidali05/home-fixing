<?php

use App\Models\User;
use App\Models\JobRequestModel;
use App\Models\BidModel;
use App\Models\Orders;
use App\Models\Reviews;
use App\Models\OrderTracking;
use App\Models\Campaign;
use App\Models\Product;
use App\Models\MarketplaceProfile;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceOrderItem;
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

    // Create an 'open' status order that should be filtered out
    Orders::create([
        'provider_id' => $provider->id,
        'user_id' => $user->id,
        'job_id' => $job->id,
        'source' => 'bid',
        'address' => $job->address,
        'details' => $job->description,
        'price' => $job->price,
        'status' => 'open',
        'paid_to_system' => 0,
    ]);

    // Create a 'completed' status order that should be filtered out
    Orders::create([
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

    $response = $this->actingAs($provider, 'sanctum')->getJson('/api/v1/provider-home');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            'post_requests',
            'direct_hires',
            'orders',
        ]
    ]);

    // Should only contain the pending one, not open or completed
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

test('my service requests endpoint returns order_status for each request', function () {
    $user = User::factory()->create([
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
        'status' => 'working',
        'paid_to_system' => 0,
    ]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/my-service-requests');

    $response->assertStatus(200);
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.id', $job->id);
    $response->assertJsonPath('data.0.order_status', 'working');
});

test('otp generation and verification via cache works successfully', function () {
    $messagesMock = Mockery::mock();
    $messagesMock->shouldReceive('create')->once()->andReturn((object)['sid' => 'SMxxx']);

    $twilioMock = Mockery::mock(\Twilio\Rest\Client::class);
    $twilioMock->messages = $messagesMock;

    $this->app->instance(\Twilio\Rest\Client::class, $twilioMock);

    // 1. Send OTP
    $response = $this->postJson('/api/v1/send-otp', [
        'phone' => '+966567777777'
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'status' => 200,
        'message' => 'OTP sent successfully'
    ]);

    // Check cache has OTP
    $otp = \Illuminate\Support\Facades\Cache::get('otp_+966567777777');
    $this->assertNotNull($otp);
    $this->assertEquals(6, strlen($otp));

    // 2. Verify OTP with incorrect code
    $verifyResponse1 = $this->postJson('/api/v1/verify-otp', [
        'phone' => '+966567777777',
        'otp' => '111111'
    ]);
    $verifyResponse1->assertStatus(422);

    // 3. Verify OTP with correct code
    $verifyResponse2 = $this->postJson('/api/v1/verify-otp', [
        'phone' => '+966567777777',
        'otp' => $otp
    ]);
    $verifyResponse2->assertStatus(200);
    $verifyResponse2->assertJson([
        'status' => 200,
        'message' => 'OTP verified successfully'
    ]);

    // Check cache is cleared
    $this->assertNull(\Illuminate\Support\Facades\Cache::get('otp_+966567777777'));
});

test('track order endpoint returns formatted user and provider profile images in uploads/profile_images', function () {
    $user = User::factory()->create([
        'name' => 'John Client',
        'role' => $this->roleCustomer,
        'country' => $this->country,
        'profile_image' => 'client_pic.png',
    ]);

    $provider = User::factory()->create([
        'name' => 'Peter Provider',
        'role' => $this->roleProvider,
        'country' => $this->country,
        'provider_status' => 'active',
        'profile_image' => 'provider_pic.png',
    ]);

    $job = JobRequestModel::create([
        'user_id' => $user->id,
        'category_id' => $this->category,
        'description' => 'Fix pipe leaking',
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

    $tracking = OrderTracking::create([
        'order_id' => $order->id,
        'status' => 'working',
        'details' => 'Provider started work',
        'latitude' => 37.7749,
        'longitude' => -122.4194,
    ]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/track-order/' . $order->id);

    $response->assertStatus(200);
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.order.user.profile_image', asset('uploads/profile_images/client_pic.png'));
    $response->assertJsonPath('data.0.order.provider.profile_image', asset('uploads/profile_images/provider_pic.png'));
});

test('post service request validates video file size and returns custom error message', function () {
    $user = User::factory()->create([
        'role' => $this->roleCustomer,
        'country' => $this->country,
    ]);

    $file = \Illuminate\Http\UploadedFile::fake()->create('intro.mp4', 11000); // 11 MB, larger than 10MB (10240 KB)

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/post-service-request', [
        'service_id' => $this->category,
        'description' => 'Fix heater',
        'date' => '2026-07-30',
        'time' => '10:00',
        'address' => '456 Main St',
        'latitude' => 37.7749,
        'longitude' => -122.4194,
        'place_pictures' => [
            \Illuminate\Http\UploadedFile::fake()->image('room.jpg')
        ],
        'video' => $file
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['video']);
    $response->assertJsonPath('errors.video.0', 'The video must not be greater than 10mb.');
});

test('active campaigns endpoint returns marketplace shop data with product', function () {
    DB::table('roles')->insert([
        'id' => 2,
        'name' => 'Seller',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $seller = User::factory()->create([
        'role' => 2, // Role 2 is for Marketplace seller/shop
        'country' => $this->country,
    ]);

    $shop = MarketplaceProfile::create([
        'user_id' => $seller->id,
        'shop_title' => 'Super Shop',
        'shop_logo' => 'logo.png',
        'shop_banner_image' => 'banner.png',
        'service_category' => [$this->category],
        'delivery_charges' => 5.0,
    ]);

    $product = Product::create([
        'user_id' => $seller->id,
        'product_name' => 'Test Product',
        'product_description' => 'Test Description',
        'category_id' => $this->category,
        'price' => 100,
        'sale_price' => 90,
        'total_stock' => 10,
        'status' => 'active',
        'is_campaign' => true,
    ]);

    $campaign = Campaign::create([
        'product_id' => $product->id,
        'title' => 'Mega Sale',
        'subtitle' => 'Save up to 50%',
        'campaign_image' => 'campaign_images/test.jpg',
        'start_date' => '2026-07-01',
        'end_date' => '2026-08-01',
        'status' => 'active',
    ]);

    $response = $this->getJson('/api/v1/marketplace/active-campaigns');

    $response->assertStatus(200);
    $response->assertJsonPath('data.0.title', 'Mega Sale');
    $response->assertJsonPath('data.0.product.product_name', 'Test Product');
    $response->assertJsonPath('data.0.product.shop.shop_title', 'Super Shop');
    $response->assertJsonPath('data.0.product.shop.shop_logo', asset('uploads/shop_logos/logo.png'));
});

test('shop analytics total earnings sums total_amount of completed orders instead of item prices', function () {
    DB::table('roles')->insert([
        'id' => 2,
        'name' => 'Seller',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $seller = User::factory()->create([
        'role' => 2, // Role 2 is for Marketplace seller/shop
        'country' => $this->country,
    ]);

    $shop = MarketplaceProfile::create([
        'user_id' => $seller->id,
        'shop_title' => 'Super Shop',
        'service_category' => [$this->category],
    ]);

    $product = Product::create([
        'user_id' => $seller->id,
        'product_name' => 'Test Product',
        'product_description' => 'Test Description',
        'category_id' => $this->category,
        'price' => 100,
        'sale_price' => 90,
        'total_stock' => 10,
        'status' => 'active',
    ]);

    // Order 1: Completed. Subtotal 100, total_amount 115 (including VAT)
    $order1 = MarketplaceOrder::create([
        'user_id' => $seller->id,
        'order_number' => 'ORD-1111',
        'shipping_address' => 'Test Address',
        'subtotal' => 100,
        'tax_amount' => 15,
        'total_amount' => 115,
        'status' => 'completed',
    ]);

    MarketplaceOrderItem::create([
        'marketplace_order_id' => $order1->id,
        'product_id' => $product->id,
        'shop_id' => $seller->id,
        'product_name' => $product->product_name,
        'quantity' => 1,
        'base_price' => 100,
        'total_price' => 100,
    ]);

    // Order 2: Completed. Subtotal 200, total_amount 230 (including VAT)
    $order2 = MarketplaceOrder::create([
        'user_id' => $seller->id,
        'order_number' => 'ORD-2222',
        'shipping_address' => 'Test Address',
        'subtotal' => 200,
        'tax_amount' => 30,
        'total_amount' => 230,
        'status' => 'completed',
    ]);

    MarketplaceOrderItem::create([
        'marketplace_order_id' => $order2->id,
        'product_id' => $product->id,
        'shop_id' => $seller->id,
        'product_name' => $product->product_name,
        'quantity' => 2,
        'base_price' => 100,
        'total_price' => 200,
    ]);

    // Order 3: Cancelled/Rejected. Should not be counted in earnings.
    $order3 = MarketplaceOrder::create([
        'user_id' => $seller->id,
        'order_number' => 'ORD-3333',
        'shipping_address' => 'Test Address',
        'subtotal' => 50,
        'tax_amount' => 7.5,
        'total_amount' => 57.5,
        'status' => 'reject',
    ]);

    MarketplaceOrderItem::create([
        'marketplace_order_id' => $order3->id,
        'product_id' => $product->id,
        'shop_id' => $seller->id,
        'product_name' => $product->product_name,
        'quantity' => 1,
        'base_price' => 50,
        'total_price' => 50,
    ]);

    $response = $this->actingAs($seller, 'sanctum')->getJson('/api/v1/marketplace/shop/analytics?period=this_year');

    $response->assertStatus(200);
    // Sum of completed orders total_amount is 115 + 230 = 345 SAR
    // Sum of product item prices is 100 + 200 = 300 SAR
    // So total_earning should be exactly 345.00
    $response->assertJsonPath('data.summary.total_earning', 345);
    $response->assertJsonPath('data.summary.completed_orders', 2);
    $response->assertJsonPath('data.summary.cancelled_orders', 1);
});

test('post_service_request and direct_hire accept null description', function () {
    $customer = User::factory()->create([
        'role' => $this->roleCustomer,
        'country' => $this->country,
    ]);

    $provider = User::factory()->create([
        'role' => $this->roleProvider,
        'country' => $this->country,
        'provider_status' => 'active',
    ]);

    $picture1 = \Illuminate\Http\UploadedFile::fake()->image('picture1.jpg');
    $picture2 = \Illuminate\Http\UploadedFile::fake()->image('picture2.jpg');

    $this->actingAs($customer, 'sanctum');

    // Test post_service_request with null description
    $response1 = $this->postJson('/api/v1/post-service-request', [
        'service_id' => $this->category,
        'description' => null,
        'date' => '2026-08-01',
        'time' => '10:00',
        'address' => 'Test Customer Address',
        'latitude' => 24.7136,
        'longitude' => 46.6753,
        'place_pictures' => [$picture1],
    ]);
    $response1->assertStatus(200);

    // Test direct_hire with null description
    $response2 = $this->postJson('/api/v1/direct-hire', [
        'service_id' => $this->category,
        'provider_id' => $provider->id,
        'description' => null,
        'job_date' => '2026-08-01',
        'job_time' => '10:00',
        'address' => 'Test Customer Address',
        'latitude' => 24.7136,
        'longitude' => 46.6753,
        'place_pictures' => [$picture2],
    ]);
    $response2->assertStatus(200);
});

test('add product and update product accept null or omitted sale_price', function () {
    DB::table('roles')->insertOrIgnore([
        'id' => 2,
        'name' => 'Seller',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $seller = User::factory()->create([
        'role' => 2,
        'country' => $this->country,
    ]);

    $shop = MarketplaceProfile::create([
        'user_id' => $seller->id,
        'shop_title' => 'Super Shop',
        'service_category' => [$this->category],
    ]);

    $this->actingAs($seller, 'sanctum');

    // Create a temporary file for the banner image
    $bannerFile = \Illuminate\Http\UploadedFile::fake()->image('banner.jpg');

    // 1. Add product with null sale_price
    $responseAdd = $this->postJson('/api/v1/marketplace/product/add', [
        'banner_image' => $bannerFile,
        'category_id' => $this->category,
        'status' => 'publish',
        'product_name' => 'Optional Sale Price Product',
        'product_description' => 'Test Description',
        'price' => '100',
        'sale_price' => null,
        'tax_status' => 'taxable',
        'total_stock' => 10,
        'sku' => 'SKU-OPT-SALE',
    ]);

    $responseAdd->assertStatus(201);
    $productId = $responseAdd->json('product.id');
    expect($responseAdd->json('product.sale_price'))->toBeNull();

    // 2. Update product to set sale_price (numeric validation)
    $responseUpdate1 = $this->postJson("/api/v1/marketplace/product/update/{$productId}", [
        'sale_price' => 85.50,
    ]);
    $responseUpdate1->assertStatus(200);
    
    // Fetch from database to verify
    $product = Product::find($productId);
    expect((float) $product->sale_price)->toEqual(85.50);

    // 3. Update product to clear sale_price (pass null)
    $responseUpdate2 = $this->postJson("/api/v1/marketplace/product/update/{$productId}", [
        'sale_price' => null,
    ]);
    $responseUpdate2->assertStatus(200);

    $product->refresh();
    expect($product->sale_price)->toBeNull();
});

test('become_seller and update_profile store marketplace location separately from user profile location', function () {
    DB::table('roles')->insertOrIgnore([
        'id' => 2,
        'name' => 'Seller',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $user = User::factory()->create([
        'role' => 0, // Customer role
        'country' => $this->country,
        'address' => 'Customer Personal Address',
        'latitude' => '24.1111',
        'longitude' => '46.1111',
    ]);

    $this->actingAs($user, 'sanctum');

    $logo = \Illuminate\Http\UploadedFile::fake()->image('logo.jpg');

    // Call become-seller with a marketplace location
    $responseBecome = $this->postJson('/api/v1/become-seller', [
        'shop_logo' => $logo,
        'shop_title' => 'My Separated Location Shop',
        'marketplace_address' => 'Shop Location Address',
        'marketplace_latitude' => 24.2222,
        'marketplace_longitude' => 46.2222,
    ]);

    $responseBecome->assertStatus(200);

    // Verify marketplace profile has the correct separated location
    $profile = MarketplaceProfile::where('user_id', $user->id)->first();
    expect($profile->address)->toBe('Shop Location Address');
    expect((float)$profile->latitude)->toEqual(24.2222);
    expect((float)$profile->longitude)->toEqual(46.2222);

    // Verify user's personal location is untouched
    $user->refresh();
    expect($user->address)->toBe('Customer Personal Address');
    expect($user->latitude)->toBe('24.1111');
    expect($user->longitude)->toBe('46.1111');

    // Now update profile to change marketplace location separately
    $responseUpdate = $this->postJson('/api/v1/update-profile', [
        'address' => 'Updated Personal Address',
        'latitude' => 24.3333,
        'longitude' => 46.3333,
        'marketplace_address' => 'Updated Shop Address',
        'marketplace_latitude' => 24.4444,
        'marketplace_longitude' => 46.4444,
    ]);

    $responseUpdate->assertStatus(200);

    // Verify both locations are updated separately and correctly
    $user->refresh();
    $profile->refresh();

    expect($user->address)->toBe('Updated Personal Address');
    expect((float)$user->latitude)->toEqual(24.3333);
    expect((float)$user->longitude)->toEqual(46.3333);

    expect($profile->address)->toBe('Updated Shop Address');
    expect((float)$profile->latitude)->toEqual(24.4444);
    expect((float)$profile->longitude)->toEqual(46.4444);
});

test('user home and get all marketplaces endpoints filter by marketplace profile coordinates instead of user coordinates', function () {
    if (DB::connection() instanceof \Illuminate\Database\SQLiteConnection) {
        $pdo = DB::connection()->getPdo();
        $pdo->sqliteCreateFunction('acos', 'acos', 1);
        $pdo->sqliteCreateFunction('cos', 'cos', 1);
        $pdo->sqliteCreateFunction('sin', 'sin', 1);
        $pdo->sqliteCreateFunction('radians', 'deg2rad', 1);
    }

    DB::table('roles')->insertOrIgnore([
        'id' => 2,
        'name' => 'Seller',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Customer is at (24.7136, 46.6753)
    $customer = User::factory()->create([
        'role' => 0,
        'country' => $this->country,
        'latitude' => 24.7136,
        'longitude' => 46.6753,
    ]);

    // Seller 1: User coordinates are far (25.5, 47.5), but marketplace coordinates are close (24.7150, 46.6760) -> SHOULD BE IN RANGE
    $sellerClose = User::factory()->create([
        'role' => 2,
        'country' => $this->country,
        'marketplace_status' => 'active',
        'latitude' => 25.5000,
        'longitude' => 47.5000,
    ]);
    MarketplaceProfile::create([
        'user_id' => $sellerClose->id,
        'shop_title' => 'Close Shop',
        'latitude' => 24.7150,
        'longitude' => 46.6760,
    ]);

    // Seller 2: User coordinates are close (24.7136, 46.6753), but marketplace coordinates are far (25.5000, 47.5000) -> SHOULD NOT BE IN RANGE
    $sellerFar = User::factory()->create([
        'role' => 2,
        'country' => $this->country,
        'marketplace_status' => 'active',
        'latitude' => 24.7136,
        'longitude' => 46.6753,
    ]);
    MarketplaceProfile::create([
        'user_id' => $sellerFar->id,
        'shop_title' => 'Far Shop',
        'latitude' => 25.5000,
        'longitude' => 47.5000,
    ]);

    $this->actingAs($customer, 'sanctum');

    // Test 1: user-home API
    $responseHome = $this->getJson('/api/v1/user-home');
    $responseHome->assertStatus(200);
    $homeMarketplaces = collect($responseHome->json('data.marketplaces'));

    // Should only contain the Close Shop
    expect($homeMarketplaces->pluck('id'))->toContain($sellerClose->id);
    expect($homeMarketplaces->pluck('id'))->not->toContain($sellerFar->id);

    // Test 2: marketplace/get-all API
    $responseAll = $this->getJson('/api/v1/marketplace/get-all');
    $responseAll->assertStatus(200);
    $allMarketplaces = collect($responseAll->json('data'));

    // Should only contain the Close Shop
    expect($allMarketplaces->pluck('id'))->toContain($sellerClose->id);
    expect($allMarketplaces->pluck('id'))->not->toContain($sellerFar->id);
});

test('user home returns active marketplace orders for customer', function () {
    $customer = User::factory()->create([
        'role' => $this->roleCustomer,
        'country' => $this->country,
    ]);

    $this->actingAs($customer, 'sanctum');

    // Create 5 active marketplace orders
    MarketplaceOrder::withoutTimestamps(function () use ($customer) {
        for ($i = 1; $i <= 5; $i++) {
            $o = MarketplaceOrder::create([
                'user_id' => $customer->id,
                'order_number' => "ORD-ACT-{$i}",
                'status' => 'pending',
                'total_amount' => 100 * $i,
            ]);
            $o->created_at = now()->addMinutes($i);
            $o->save();
        }

        // Create 1 completed marketplace order (should not be in active orders list)
        $oComp = MarketplaceOrder::create([
            'user_id' => $customer->id,
            'order_number' => "ORD-ACT-COMP",
            'status' => 'completed',
            'total_amount' => 500,
        ]);
        $oComp->created_at = now()->addMinutes(6);
        $oComp->save();

        // Create 1 rejected marketplace order (should not be in active orders list)
        $oRej = MarketplaceOrder::create([
            'user_id' => $customer->id,
            'order_number' => "ORD-ACT-REJ",
            'status' => 'reject',
            'total_amount' => 500,
        ]);
        $oRej->created_at = now()->addMinutes(7);
        $oRej->save();
    });

    $response = $this->getJson('/api/v1/user-home');
    $response->assertStatus(200);

    $activeMarketplaceOrders = $response->json('data.active_marketplace_orders');

    // Should return exactly 4 active orders (since limit is 4)
    expect($activeMarketplaceOrders)->toHaveCount(4);

    // Verify ordering is latest (descending order number/date)
    expect($activeMarketplaceOrders[0]['order_number'])->toBe('ORD-ACT-5');
    expect($activeMarketplaceOrders[1]['order_number'])->toBe('ORD-ACT-4');
    expect($activeMarketplaceOrders[2]['order_number'])->toBe('ORD-ACT-3');
    expect($activeMarketplaceOrders[3]['order_number'])->toBe('ORD-ACT-2');
});

test('profile endpoint returns marketplace location details for seller', function () {
    DB::table('roles')->insertOrIgnore([
        'id' => 2,
        'name' => 'Seller',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $seller = User::factory()->create([
        'role' => 2,
        'country' => $this->country,
        'marketplace_status' => 'active',
        'latitude' => 25.5000,
        'longitude' => 47.5000,
        'address' => 'User Personal Address',
    ]);

    MarketplaceProfile::create([
        'user_id' => $seller->id,
        'shop_title' => 'Seller Shop',
        'address' => 'Shop Business Address',
        'latitude' => 24.1111,
        'longitude' => 46.2222,
    ]);

    $response = $this->actingAs($seller, 'sanctum')->getJson('/api/v1/profile');

    $response->assertStatus(200);
    $response->assertJsonPath('data.address', 'User Personal Address');
    $response->assertJsonPath('data.latitude', '25.5');
    $response->assertJsonPath('data.longitude', '47.5');
    $response->assertJsonPath('data.marketplace_profile.address', 'Shop Business Address');
    $response->assertJsonPath('data.marketplace_profile.latitude', '24.1111');
    $response->assertJsonPath('data.marketplace_profile.longitude', '46.2222');
});

test('service order receipt endpoint returns correct receipt structure and values', function () {
    $user = User::factory()->create([
        'name' => 'Test Customer',
        'role' => $this->roleCustomer,
        'country' => $this->country,
    ]);

    $provider = User::factory()->create([
        'name' => 'Test Provider',
        'role' => $this->roleProvider,
        'country' => $this->country,
        'provider_status' => 'active',
    ]);

    $job = JobRequestModel::create([
        'user_id' => $user->id,
        'category_id' => $this->category,
        'description' => 'Clean the AC units',
        'job_date' => '2026-06-25',
        'job_time' => '10:00',
        'address' => 'Riyadh Road',
        'latitude' => 24.1234,
        'longitude' => 46.1234,
        'price' => 150.00,
        'status' => 'pending',
    ]);

    $order = Orders::create([
        'provider_id' => $provider->id,
        'user_id' => $user->id,
        'job_id' => $job->id,
        'source' => 'direct',
        'address' => $job->address,
        'details' => $job->description,
        'price' => 150.00,
        'status' => 'completed',
        'paid_to_system' => 0,
    ]);

    $response = $this->actingAs($user, 'sanctum')->getJson("/api/v1/orders/{$order->id}/receipt");

    $response->assertStatus(200);
    $response->assertJsonPath('data.order_id', $order->id);
    $response->assertJsonPath('data.amount', 150);
    $response->assertJsonPath('data.customer.name', 'Test Customer');
    $response->assertJsonPath('data.provider.name', 'Test Provider');
    $response->assertJsonPath('data.job_details.description', 'Clean the AC units');
});

test('marketplace order receipt endpoint returns correct receipt structure and values', function () {
    DB::table('roles')->insertOrIgnore([
        'id' => 2,
        'name' => 'Seller',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $customer = User::factory()->create([
        'name' => 'Customer User',
        'role' => $this->roleCustomer,
        'country' => $this->country,
    ]);

    $seller = User::factory()->create([
        'name' => 'Seller User',
        'role' => 2,
        'country' => $this->country,
        'marketplace_status' => 'active',
    ]);

    $profile = MarketplaceProfile::create([
        'user_id' => $seller->id,
        'shop_title' => 'Gadgets Store',
        'address' => 'Riyadh Shop Address',
        'latitude' => 24.4444,
        'longitude' => 46.4444,
    ]);

    $product = \App\Models\Product::create([
        'user_id' => $seller->id,
        'category_id' => $this->category,
        'product_name' => 'Wireless Keyboard',
        'price' => 80.00,
        'total_stock' => 10,
        'product_description' => 'Mechanical keyboard',
        'status' => 'active',
    ]);

    $order = MarketplaceOrder::create([
        'user_id' => $customer->id,
        'order_number' => 'MKT-ORD-1122',
        'status' => 'pending',
        'total_amount' => 170.00,
        'shipping_cost' => 10.00,
        'payment_status' => 'paid',
    ]);

    \App\Models\MarketplaceOrderItem::create([
        'marketplace_order_id' => $order->id,
        'product_id' => $product->id,
        'shop_id' => $seller->id,
        'product_name' => $product->product_name,
        'quantity' => 2,
        'base_price' => 80.00,
        'total_price' => 160.00,
    ]);

    // Test as customer (can see everything)
    $response = $this->actingAs($customer, 'sanctum')->getJson("/api/v1/marketplace/orders/{$order->id}/receipt");

    $response->assertStatus(200);
    $response->assertJsonPath('data.order_id', $order->id);
    $response->assertJsonPath('data.order_number', 'MKT-ORD-1122');
    $response->assertJsonPath('data.subtotal', 160);
    $response->assertJsonPath('data.delivery_charges', 10);
    $response->assertJsonPath('data.total_amount', 170);
    $response->assertJsonPath('data.items.0.product_title', 'Wireless Keyboard');
    $response->assertJsonPath('data.items.0.shop_title', 'Gadgets Store');

    // Test as seller (only sees their items and no delivery_charges in total_amount)
    $responseSeller = $this->actingAs($seller, 'sanctum')->getJson("/api/v1/marketplace/orders/{$order->id}/receipt");

    $responseSeller->assertStatus(200);
    $responseSeller->assertJsonPath('data.subtotal', 160);
    $responseSeller->assertJsonPath('data.delivery_charges', 0);
    $responseSeller->assertJsonPath('data.total_amount', 160);
});
