<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Admin\SystemSettingModel;

$setting = SystemSettingModel::first();
echo "Setting object:\n";
var_dump($setting ? $setting->toArray() : null);
