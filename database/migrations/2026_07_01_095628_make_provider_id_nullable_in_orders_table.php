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
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('provider_id')->nullable()->change();
            $table->string('status')->default('pending')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('provider_id')->nullable(false)->change();
            $table->enum('status', ['pending', 'cancelled', 'on_the_way', 'arrived', 'working', 'provider_completed', 'completed', 'rejected', 'accepted'])->default('pending')->change();
        });
    }
};
