<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('banner_image');
            $table->json('product_images')->nullable();
            $table->unsignedBigInteger('category_id');
            $table->enum('status', ['publish', 'unpublish', 'pending', 'trash']);
            $table->string('product_name');
            $table->text('product_description');
            $table->decimal('price', 10, 2);
            $table->decimal('sale_price', 10, 2)->nullable();
            $table->string('discount_type')->nullable();
            $table->decimal('discount_value', 10, 2)->nullable();
            $table->string('tax_status');
            $table->boolean('installation_available')->nullable();
            $table->decimal('installation_price', 10, 2)->nullable();
            $table->text('installation_details')->nullable();
            $table->decimal('weight', 10, 2)->nullable();
            $table->decimal('height', 10, 2)->nullable();
            $table->decimal('width', 10, 2)->nullable();
            $table->decimal('length', 10, 2)->nullable();
            $table->integer('total_stock');
            $table->integer('limited_stock')->nullable();
            $table->string('sku');
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('products');
    }
};
