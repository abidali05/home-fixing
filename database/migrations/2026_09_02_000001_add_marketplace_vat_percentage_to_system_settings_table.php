<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('system_settings') && !Schema::hasColumn('system_settings', 'marketplace_vat_percentage')) {
            Schema::table('system_settings', function (Blueprint $table) {
                $table->decimal('marketplace_vat_percentage', 5, 2)->default(15.00)->after('payment_gateway_vat_percentage');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('system_settings') && Schema::hasColumn('system_settings', 'marketplace_vat_percentage')) {
            Schema::table('system_settings', function (Blueprint $table) {
                $table->dropColumn('marketplace_vat_percentage');
            });
        }
    }
};
