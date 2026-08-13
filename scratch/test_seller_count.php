<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "--- SELLER COUNT TEST ---\n";

$marketplaceProfilesCount = DB::table('marketplace_profiles')->count();
echo "marketplace_profiles table count: " . $marketplaceProfilesCount . "\n";

$usersWithSellerRoleCount = DB::table('users')
    ->where(function ($b) {
        $b->where('role', '2')
            ->orWhere('role', 2)
            ->orWhere('has_roles', '2')
            ->orWhere('has_roles', 2)
            ->orWhere('has_roles', 'like', '%2%')
            ->orWhereRaw("FIND_IN_SET('2', has_roles)");
    })
    ->count();
echo "users table with role/has_roles='2' count: " . $usersWithSellerRoleCount . "\n";
