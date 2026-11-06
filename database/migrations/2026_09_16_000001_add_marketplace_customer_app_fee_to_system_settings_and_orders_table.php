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
        if (Schema::hasTable('system_settings') && !Schema::hasColumn('system_settings', 'marketplace_customer_app_fee')) {
            Schema::table('system_settings', function (Blueprint $table) {
                $table->decimal('marketplace_customer_app_fee', 8, 2)->default(3.00)->after('customer_app_fee');
            });
        }

        if (Schema::hasTable('marketplace_orders') && !Schema::hasColumn('marketplace_orders', 'customer_app_fee')) {
            Schema::table('marketplace_orders', function (Blueprint $table) {
                $table->decimal('customer_app_fee', 10, 2)->default(0.00)->after('subtotal');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('system_settings') && Schema::hasColumn('system_settings', 'marketplace_customer_app_fee')) {
            Schema::table('system_settings', function (Blueprint $table) {
                $table->dropColumn('marketplace_customer_app_fee');
            });
        }

        if (Schema::hasTable('marketplace_orders') && Schema::hasColumn('marketplace_orders', 'customer_app_fee')) {
            Schema::table('marketplace_orders', function (Blueprint $table) {
                $table->dropColumn('customer_app_fee');
            });
        }
    }
};
