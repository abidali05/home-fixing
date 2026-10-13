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
        if (Schema::hasTable('marketplace_profiles')) {
            Schema::table('marketplace_profiles', function (Blueprint $table) {
                if (!Schema::hasColumn('marketplace_profiles', 'expires_at')) {
                    $table->timestamp('expires_at')->nullable()->after('longitude');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('marketplace_profiles')) {
            Schema::table('marketplace_profiles', function (Blueprint $table) {
                if (Schema::hasColumn('marketplace_profiles', 'expires_at')) {
                    $table->dropColumn('expires_at');
                }
            });
        }
    }
};
