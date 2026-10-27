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
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'payment_due')) {
                $table->decimal('payment_due', 10, 2)->default(0.00)->after('role');
            }
            if (!Schema::hasColumn('users', 'max_due_threshold')) {
                $table->decimal('max_due_threshold', 10, 2)->default(25.00)->after('payment_due');
            }
            if (!Schema::hasColumn('users', 'is_due_blocked')) {
                $table->tinyInteger('is_due_blocked')->default(0)->after('max_due_threshold');
            }
        });

        if (!Schema::hasTable('provider_dues_ledger')) {
            Schema::create('provider_dues_ledger', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('provider_id');
                $table->unsignedBigInteger('order_id')->nullable();
                $table->string('type', 50); // debit_cash_order, credit_wallet_deduction, credit_online_payment, credit_manual_adjustment, repayment
                $table->decimal('amount', 10, 2);
                $table->decimal('previous_due', 10, 2);
                $table->decimal('current_due', 10, 2);
                $table->string('notes', 255)->nullable();
                $table->timestamps();

                $table->foreign('provider_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('order_id')->references('id')->on('orders')->onDelete('set null');
            });
        }

        if (!Schema::hasTable('provider_commission_repayments')) {
            Schema::create('provider_commission_repayments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('provider_id');
                $table->string('tap_charge_id', 100)->nullable()->unique();
                $table->string('transaction_reference', 100)->unique();
                $table->decimal('amount_paid', 10, 2);
                $table->string('payment_method', 50); // tap_card_mada, tap_card_visa, apple_pay, bank_transfer, admin_override
                $table->string('status', 20)->default('pending'); // pending, verified, failed
                $table->timestamp('verified_at')->nullable();
                $table->unsignedBigInteger('verified_by_admin_id')->nullable();
                $table->json('gateway_response')->nullable();
                $table->timestamps();

                $table->foreign('provider_id')->references('id')->on('users')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provider_commission_repayments');

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'is_due_blocked')) {
                $table->dropColumn('is_due_blocked');
            }
        });
    }
};
