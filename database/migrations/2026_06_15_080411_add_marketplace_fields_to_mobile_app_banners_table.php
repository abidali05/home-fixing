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
        Schema::table('mobile_app_banners', function (Blueprint $table) {
            $table->boolean('showMarketplace')->default(false)->after('path');
            $table->foreignId('marketplace_id')
                ->nullable()
                ->after('showMarketplace')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mobile_app_banners', function (Blueprint $table) {
            $table->dropForeign(['marketplace_id']);
            $table->dropColumn(['showMarketplace', 'marketplace_id']);
        });
    }
};
