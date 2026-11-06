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
        Schema::table('system_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('system_settings', 'azhl_fee')) {
                $table->decimal('azhl_fee', 12, 2)->default(5.00)->after('azhl_percentage');
            }
            if (!Schema::hasColumn('system_settings', 'referral_amount')) {
                $table->decimal('referral_amount', 12, 2)->default(10.00)->after('azhl_fee');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            if (Schema::hasColumn('system_settings', 'azhl_fee')) {
                $table->dropColumn('azhl_fee');
            }
            if (Schema::hasColumn('system_settings', 'referral_amount')) {
                $table->dropColumn('referral_amount');
            }
        });
    }
};
