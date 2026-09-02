<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

if (!Schema::hasColumn('system_settings', 'marketplace_vat_percentage')) {
    Schema::table('system_settings', function (Blueprint $table) {
        $table->decimal('marketplace_vat_percentage', 5, 2)->default(15.00)->after('payment_gateway_vat_percentage');
    });
    echo "Added marketplace_vat_percentage column successfully.\n";
} else {
    echo "marketplace_vat_percentage column already exists.\n";
}
