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
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('module_name');
            $table->timestamps();
        });

        Schema::create('roles_permissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('permission_id');
            $table->timestamps();

            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade')->onUpdate('cascade');
        });

        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('country_code');
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('phone')->unique();
            $table->date('dob')->nullable();
            $table->unsignedBigInteger('role');
            $table->text('address')->nullable();
            $table->text('bio')->nullable();
            $table->string('latitude')->nullable();
            $table->string('longitude')->nullable();
            $table->unsignedBigInteger('country');
            $table->integer('otp')->nullable();
            $table->unsignedBigInteger('service_category')->nullable();
            $table->string('experience')->nullable();
            $table->time('work_hour_start')->nullable();
            $table->time('work_hour_end')->nullable();
            $table->string('service_license')->nullable();
            $table->string('certification')->nullable();
            $table->string('charge_type')->nullable();
            $table->string('charge_amount')->nullable();
            $table->string('profile_image')->nullable();
            $table->string('has_roles')->nullable();
            $table->string('location_label')->nullable();
            $table->enum('status', ['active', 'banned', 'suspended', 'inactive'])->default('inactive');
            $table->timestamps();

            $table->foreign('role')->references('id')->on('roles')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('country')->references('id')->on('countries')->onDelete('cascade')->onUpdate('cascade');
            // $table->foreign('service_category')->references('id')->on('categories')->nullOnDelete()->onUpdate('cascade');
        });
        Schema::create('admin_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->unique();
            $table->unsignedBigInteger('role');
            $table->text('address')->nullable();
            $table->integer('otp')->nullable();
            $table->enum('status', ['active', 'banned', 'suspended', 'inactive'])->default('inactive');
            $table->timestamps();

            $table->foreign('role')->references('id')->on('roles')->onDelete('cascade')->onUpdate('cascade');
        });

        Schema::create('provider_profile_gallery', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('path');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
        });

        Schema::create('provider_skills', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('name');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
        });

        Schema::create('jobss', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('provider_id')->nullable();
            $table->unsignedBigInteger('category_id');
            $table->text('description')->nullable();
            $table->date('job_date')->nullable();
            $table->time('job_time')->nullable();
            $table->string('price')->nullable();
            $table->string('price_type')->nullable();
            $table->enum('status', ['pending', 'quoted', 'in_progress', 'completed', 'cancelled'])->default('pending');
            $table->text('address')->nullable();
            $table->string('latitude')->nullable();
            $table->string('longitude')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('category_id')->references('id')->on('categories')->restrictOnDelete()->onUpdate('cascade');
        });

        Schema::create('job_images', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('job_id');
            $table->string('path');
            $table->timestamps();

            $table->foreign('job_id')->references('id')->on('jobss')->onDelete('cascade')->onUpdate('cascade');
        });

        Schema::create('bids', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('job_id');
            $table->unsignedBigInteger('provider_id');
            $table->text('bid_details')->nullable();
            $table->decimal('price', 10, 2);
            $table->time('bid_time')->nullable();
            $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');
            $table->timestamps();

            $table->foreign('job_id')->references('id')->on('jobss')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('provider_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('provider_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('job_id')->nullable();
            $table->enum('source', ['bid', 'direct_hiring']);
            $table->text('address')->nullable();
            $table->text('details')->nullable();
            $table->enum('status', ['open', 'pending', 'cancelled', 'on_the_way', 'arrived', 'working', 'provider_completed', 'completed', 'rejected', 'accepted'])->default('open');
            $table->decimal('price', 10, 2)->nullable();
            $table->integer('paid_to_system')->nullable();
            $table->timestamps();

            $table->foreign('provider_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('job_id')->references('id')->on('jobss')->nullOnDelete()->onUpdate('cascade');
        });

        Schema::create('ratings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('provider_id');
            $table->integer('rating');
            $table->text('review')->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('provider_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
        });



        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('job_id');
            $table->unsignedBigInteger('provider_id');
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['pending', 'paid', 'refunded'])->default('pending');
            $table->enum('payment_method', ['gpay', 'applepay']);
            $table->timestamps();

            $table->foreign('job_id')->references('id')->on('jobss')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('provider_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
        });

        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('job_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('provider_id');
            $table->text('description')->nullable();
            $table->boolean('resolved')->default(false);
            $table->timestamps();

            $table->foreign('job_id')->references('id')->on('jobss')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('provider_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->enum('type', ['text', 'boolean', 'integer', 'json'])->default('text');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('title');
            $table->text('body');
            $table->boolean('is_read')->default(false);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists('notifications');
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('complaints');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('ratings');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('bids');
        Schema::dropIfExists('job_images');
        Schema::dropIfExists('jobss');
        Schema::dropIfExists('provider_skills');
        Schema::dropIfExists('provider_profile_gallery');
        Schema::dropIfExists('users');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('countries');
        Schema::dropIfExists('roles_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');

        Schema::enableForeignKeyConstraints();

        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
