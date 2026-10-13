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
        Schema::create('referral_rewards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('referrer_id')->index(); // The user/provider who gets the referral bonus
            $table->unsignedBigInteger('referred_user_id')->unique()->index(); // The referred provider (1st completed order)
            $table->unsignedBigInteger('order_id')->nullable();
            $table->decimal('reward_amount', 12, 2)->default(10.00);
            $table->enum('status', ['credited', 'paid'])->default('credited');
            $table->timestamps();

            $table->foreign('referrer_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('referred_user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referral_rewards');
    }
};
