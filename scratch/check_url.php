<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Admin\SystemSettingModel;

$setting = SystemSettingModel::first();
$logoUrl = optional($setting)->logo ? asset('uploads/system_settings/' . $setting->logo) : asset('uploads/system_settings/Logo1.png');

echo "Generated Logo URL: " . $logoUrl . "\n";
echo "File exists check: " . (file_exists(public_path('uploads/system_settings/' . $setting->logo)) ? 'YES' : 'NO') . "\n";
