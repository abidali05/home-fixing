<?php

use App\Models\User;
use App\Models\JobRequestModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');

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

test('post service request saves correct equipment option', function () {
    $user = User::factory()->create([
        'role' => $this->roleCustomer,
        'country' => $this->country,
    ]);

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/post-service-request', [
        'service_id' => $this->category,
        'description' => 'Fix my leak',
        'date' => '2026-06-20',
        'time' => '14:30',
        'address' => '123 Test St',
        'latitude' => 37.7749,
        'longitude' => -122.4194,
        'place_pictures' => [
            UploadedFile::fake()->image('leak.jpg')
        ],
        'equipment_option' => 'I don’t have the required equipment.',
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('data.equipment_option', 'I don’t have the required equipment.');
    
    $job = JobRequestModel::latest('id')->first();
    expect($job->equipment_option)->toBe('I don’t have the required equipment.');
});

test('direct hire saves correct equipment option', function () {
    $user = User::factory()->create([
        'role' => $this->roleCustomer,
        'country' => $this->country,
    ]);

    $provider = User::factory()->create([
        'role' => $this->roleProvider,
        'country' => $this->country,
        'provider_status' => 'active'
    ]);

    // Create provider profile
    $provider->providerProfile()->create([
        'service_category' => json_encode([$this->category]),
        'experience' => '5 years',
    ]);

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/direct-hire', [
        'service_id' => $this->category,
        'provider_id' => $provider->id,
        'address' => '123 Test St',
        'latitude' => 37.7749,
        'longitude' => -122.4194,
        'description' => 'Fix leak',
        'job_date' => '2026-06-20',
        'job_time' => '14:30',
        'equipment_option' => 'I need the service provider to bring it.',
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('data.equipment_option', 'I need the service provider to bring it.');

    $job = JobRequestModel::latest('id')->first();
    expect($job->equipment_option)->toBe('I need the service provider to bring it.');
});

test('submitting numeric 0 or 1 as equipment option is valid and saved correctly', function () {
    $user = User::factory()->create([
        'role' => $this->roleCustomer,
        'country' => $this->country,
    ]);

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/post-service-request', [
        'service_id' => $this->category,
        'description' => 'Fix my leak',
        'date' => '2026-06-20',
        'time' => '14:30',
        'address' => '123 Test St',
        'latitude' => 37.7749,
        'longitude' => -122.4194,
        'place_pictures' => [
            UploadedFile::fake()->image('leak.jpg')
        ],
        'equipment_option' => 1, // sending integer 1
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('data.equipment_option', 1);

    $job = JobRequestModel::latest('id')->first();
    expect($job->equipment_option)->toEqual(1);
});

test('post service request accepts video up to 5mb', function () {
    $user = User::factory()->create([
        'role' => $this->roleCustomer,
        'country' => $this->country,
    ]);

    // 4MB video (4096 KB)
    $videoFile = UploadedFile::fake()->create('leak_video.mp4', 4096, 'video/mp4');
    $imageFile = UploadedFile::fake()->image('leak.jpg');

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/post-service-request', [
        'service_id' => $this->category,
        'description' => 'Fix my leak video',
        'date' => '2026-06-20',
        'time' => '14:30',
        'address' => '123 Test St',
        'latitude' => 37.7749,
        'longitude' => -122.4194,
        'place_pictures' => [
            $imageFile
        ],
        'video' => $videoFile,
    ]);

    $response->assertStatus(200);

    // Assert that the job has 1 image attachment and 1 separate video path
    $job = JobRequestModel::latest('id')->first();
    expect($job->images)->toHaveCount(1);
    expect($job->video)->not->toBeNull();
    expect($job->video)->toContain('uploads/job_gallery');
});

test('post service request rejects video above 5mb', function () {
    $user = User::factory()->create([
        'role' => $this->roleCustomer,
        'country' => $this->country,
    ]);

    // 6MB video (6144 KB)
    $videoFile = UploadedFile::fake()->create('leak_video.mp4', 6144, 'video/mp4');
    $imageFile = UploadedFile::fake()->image('leak.jpg');

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/post-service-request', [
        'service_id' => $this->category,
        'description' => 'Fix my leak video',
        'date' => '2026-06-20',
        'time' => '14:30',
        'address' => '123 Test St',
        'latitude' => 37.7749,
        'longitude' => -122.4194,
        'place_pictures' => [
            $imageFile
        ],
        'video' => $videoFile,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['video']);
});


