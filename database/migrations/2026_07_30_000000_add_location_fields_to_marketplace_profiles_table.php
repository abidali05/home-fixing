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
        Schema::table('marketplace_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_profiles', 'address')) {
                $table->string('address')->nullable();
            }
            if (!Schema::hasColumn('marketplace_profiles', 'latitude')) {
                $table->string('latitude')->nullable();
            }
            if (!Schema::hasColumn('marketplace_profiles', 'longitude')) {
                $table->string('longitude')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketplace_profiles', function (Blueprint $table) {
            $table->dropColumn(['address', 'latitude', 'longitude']);
        });
    }
};
