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
            if (!Schema::hasColumn('orders', 'payment_method')) {
                $table->string('payment_method', 20)->default('card')->after('price');
            }
            if (!Schema::hasColumn('orders', 'payment_status')) {
                $table->string('payment_status', 30)->default('pending')->after('payment_method');
            }
            if (!Schema::hasColumn('orders', 'cash_collected_amount')) {
                $table->decimal('cash_collected_amount', 10, 2)->default(0.00)->after('total_amount');
            }
            if (!Schema::hasColumn('orders', 'cash_received_at')) {
                $table->timestamp('cash_received_at')->nullable()->after('updated_at');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'payment_due')) {
                $table->decimal('payment_due', 10, 2)->default(0.00)->after('rating');
            }
            if (!Schema::hasColumn('users', 'max_due_threshold')) {
                $table->decimal('max_due_threshold', 10, 2)->default(25.00)->after('payment_due');
            }
        });

        if (!Schema::hasTable('provider_dues_ledger')) {
            Schema::create('provider_dues_ledger', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('provider_id');
                $table->unsignedBigInteger('order_id')->nullable();
                $table->string('type', 50); // debit_cash_order, credit_wallet_deduction, credit_online_payment, credit_manual_adjustment
                $table->decimal('amount', 10, 2);
                $table->decimal('previous_due', 10, 2);
                $table->decimal('current_due', 10, 2);
                $table->string('notes', 255)->nullable();
                $table->timestamps();

                $table->foreign('provider_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('order_id')->references('id')->on('orders')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provider_dues_ledger');

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'max_due_threshold')) {
                $table->dropColumn('max_due_threshold');
            }
            if (Schema::hasColumn('users', 'payment_due')) {
                $table->dropColumn('payment_due');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'cash_received_at')) {
                $table->dropColumn('cash_received_at');
            }
            if (Schema::hasColumn('orders', 'cash_collected_amount')) {
                $table->dropColumn('cash_collected_amount');
            }
            if (Schema::hasColumn('orders', 'payment_status')) {
                $table->dropColumn('payment_status');
            }
            if (Schema::hasColumn('orders', 'payment_method')) {
                $table->dropColumn('payment_method');
            }
        });
    }
};
