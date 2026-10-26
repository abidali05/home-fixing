<?php

use App\Models\User;
use App\Models\AdminUsers;
use App\Models\AccountActiveRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    // 1. Insert roles
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

    $this->roleAdmin = 2;
    DB::table('roles')->insert([
        'id' => 2,
        'name' => 'Admin',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // 2. Insert country
    $this->country = DB::table('countries')->insertGetId([
        'name' => 'United States',
        'country_code' => 'US',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

test('inactive provider user (role 1) can submit activation request', function () {
    $user = User::factory()->create([
        'role' => $this->roleProvider,
        'country' => $this->country,
        'status' => 'active',
        'provider_status' => 'inactive',
    ]);

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/active-account-request', [
        'message' => 'Please activate my provider account.',
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('message', 'Activation request submitted successfully.');
    $response->assertJsonStructure(['data' => ['id', 'user_id', 'message']]);

    // Check DB
    $request = AccountActiveRequest::where('user_id', $user->id)->first();
    expect($request)->not->toBeNull();
    expect($request->message)->toBe('Please activate my provider account.');
});

test('active provider user (role 1) cannot submit activation request', function () {
    $user = User::factory()->create([
        'role' => $this->roleProvider,
        'country' => $this->country,
        'status' => 'active',
        'provider_status' => 'active',
    ]);

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/active-account-request', [
        'message' => 'Already active.',
    ]);

    $response->assertStatus(400);
});

test('validation requires message', function () {
    $user = User::factory()->create([
        'role' => $this->roleCustomer,
        'country' => $this->country,
        'status' => 'inactive',
    ]);

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/active-account-request', []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['message']);
});

test('admin can view active requests list and activate user based on role', function () {
    // Create inactive provider user and activation request
    $user = User::factory()->create([
        'role' => $this->roleProvider,
        'country' => $this->country,
        'status' => 'active',
        'provider_status' => 'inactive',
    ]);

    $activeRequest = AccountActiveRequest::create([
        'user_id' => $user->id,
        'message' => 'Activation requested for provider account.',
    ]);

    // Create admin user
    $admin = AdminUsers::create([
        'name' => 'Admin User',
        'email' => 'admin@test.com',
        'phone' => '+966569999999',
        'role' => $this->roleAdmin,
        'status' => 'active',
    ]);

    // 1. Check admin index list (via ajax)
    $responseIndex = $this->actingAs($admin, 'admin')->getJson('/account-active-requests');
    $responseIndex->assertStatus(200);

    // 2. Activate the user via admin endpoint
    $responseActivate = $this->actingAs($admin, 'admin')->postJson("/account-active-requests/activate/{$activeRequest->id}");
    $responseActivate->assertStatus(200);

    // Check DB
    $user->refresh();
    expect($user->provider_status)->toBe('active');

    $requestInDb = AccountActiveRequest::find($activeRequest->id);
    expect($requestInDb)->toBeNull();
});
