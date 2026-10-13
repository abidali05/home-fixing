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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('job_id')->index();
            $table->unsignedBigInteger('bid_id')->index();
            $table->unsignedBigInteger('provider_id')->index();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 10)->default('SAR');
            $table->string('gateway', 50)->default('tap');
            $table->enum('status', ['pending', 'processing', 'captured', 'failed', 'cancelled'])->default('pending');
            $table->string('tap_charge_id')->nullable()->index();
            $table->json('gateway_response')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('job_id')->references('id')->on('jobss')->onDelete('cascade');
            $table->foreign('bid_id')->references('id')->on('bids')->onDelete('cascade');
            $table->foreign('provider_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
