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
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->string('refund_no', 30)->unique();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('marketplace_order_id')->nullable();
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('bank_account_id')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 10)->default('SAR');
            $table->string('status', 30)->default('requested'); // not_required, requested, processing, refunded, failed, accepted, rejected
            $table->string('gateway', 50)->nullable()->default('bank_transfer');
            $table->string('gateway_refund_id')->nullable();
            $table->string('bank_reference')->nullable();
            $table->text('failure_reason')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('bank_account_id')->references('id')->on('bank_accounts')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
