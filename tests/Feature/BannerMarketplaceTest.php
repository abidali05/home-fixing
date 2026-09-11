<?php

use App\Models\User;
use App\Models\Admin\MobileBanners;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user home api returns marketplace id with banner when showMarketplace is true', function () {
    // 1. Create seed data or countries/roles required by foreign keys
    // In users table, role and country are foreign keys. Let's create roles and countries first.
    $role = \Illuminate\Support\Facades\DB::table('roles')->insertGetId([
        'name' => 'User',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $country = \Illuminate\Support\Facades\DB::table('countries')->insertGetId([
        'name' => 'United States',
        'country_code' => 'US',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // 2. Create customer user
    $user = User::factory()->create([
        'role' => $role,
        'country' => $country,
    ]);

    // 3. Create marketplace seller user and their marketplace profile
    $seller = User::factory()->create([
        'role' => $role,
        'country' => $country,
        'marketplace_status' => 'active'
    ]);

    $seller->marketplaceProfile()->create([
        'shop_title' => 'Test Shop Title',
        'bio' => 'Test description',
        'shop_status' => 'active',
    ]);

    // 4. Create banners
    $bannerLinked = MobileBanners::create([
        'path' => 'linked_banner.jpg',
        'showMarketplace' => true,
        'marketplace_id' => $seller->id,
    ]);

    $bannerNormal = MobileBanners::create([
        'path' => 'normal_banner.jpg',
        'showMarketplace' => false,
        'marketplace_id' => $seller->id,
    ]);

    // 5. Call user-home API
    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/user-home');

    // 6. Assert response
    $response->assertStatus(200);

    $banners = $response->json('data.banners');

    // Since we order by id desc, the latest banner created (normal_banner) will be first
    expect($banners)->toHaveCount(2);

    // Assert normal banner (showMarketplace = false)
    expect($banners[0]['path'])->toContain('normal_banner.jpg');
    expect($banners[0])->not->toHaveKey('marketplace_id');
    expect($banners[0])->not->toHaveKey('showMarketplace');

    // Assert linked banner (showMarketplace = true)
    expect($banners[1]['path'])->toContain('linked_banner.jpg');
    expect($banners[1])->toHaveKey('marketplace_id');
    expect($banners[1]['marketplace_id'])->toBe($seller->id);
    expect($banners[1])->not->toHaveKey('showMarketplace');
});
