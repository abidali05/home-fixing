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
        if (Schema::hasTable('system_settings') && !Schema::hasColumn('system_settings', 'marketplace_commission_percentage')) {
            Schema::table('system_settings', function (Blueprint $table) {
                $table->decimal('marketplace_commission_percentage', 5, 2)
                    ->default(10.00)
                    ->after('marketplace_customer_app_fee');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('system_settings') && Schema::hasColumn('system_settings', 'marketplace_commission_percentage')) {
            Schema::table('system_settings', function (Blueprint $table) {
                $table->dropColumn('marketplace_commission_percentage');
            });
        }
    }
};
