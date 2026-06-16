<?php

use App\Models\User;
use App\Models\JobRequestModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->roleCustomer = 0;
    DB::table('roles')->insert([
        'id' => 0,
        'name' => 'User',
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

test('cleanup deletes pending jobs older than 1 hour but keeps newer ones and non-pending ones', function () {
    $user = User::factory()->create([
        'role' => $this->roleCustomer,
        'country' => $this->country,
    ]);

    // 1. Pending job older than 1 hour (created 2 hours ago) -> Should be deleted
    $jobToDelete = JobRequestModel::create([
        'user_id' => $user->id,
        'category_id' => $this->category,
        'description' => 'Leaks everywhere',
        'status' => 'pending',
        'created_at' => now()->subHours(2),
    ]);

    // 2. Pending job newer than 1 hour (created 30 minutes ago) -> Should be kept
    $jobToKeepNew = JobRequestModel::create([
        'user_id' => $user->id,
        'category_id' => $this->category,
        'description' => 'Keep me, I am new',
        'status' => 'pending',
        'created_at' => now()->subMinutes(30),
    ]);

    // 3. Quoted job older than 1 hour (created 2 hours ago) -> Should be kept (hired)
    $jobToKeepQuoted = JobRequestModel::create([
        'user_id' => $user->id,
        'category_id' => $this->category,
        'description' => 'Keep me, provider hired',
        'status' => 'quoted',
        'created_at' => now()->subHours(2),
    ]);

    // Run the cleanup command
    Artisan::call('jobs:cleanup');

    // Assertions
    expect(JobRequestModel::find($jobToDelete->id))->toBeNull();
    expect(JobRequestModel::find($jobToKeepNew->id))->not->toBeNull();
    expect(JobRequestModel::find($jobToKeepQuoted->id))->not->toBeNull();
});
