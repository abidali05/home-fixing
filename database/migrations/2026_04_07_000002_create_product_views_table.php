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
        Schema::create('product_views', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('viewer_user_id')->nullable();
            $table->unsignedBigInteger('campaign_id')->nullable();
            $table->boolean('is_through_campaign')->default(false);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('shop_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('viewer_user_id')->references('id')->on('users')->nullOnDelete()->onUpdate('cascade');
            $table->foreign('campaign_id')->references('id')->on('campaigns')->nullOnDelete()->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_views');
    }
};
