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
        if (Schema::hasTable('marketplace_orders')) {
            Schema::table('marketplace_orders', function (Blueprint $table) {
                if (!Schema::hasColumn('marketplace_orders', 'transaction_id')) {
                    $table->string('transaction_id', 100)->nullable()->after('payment_status');
                }
                if (!Schema::hasColumn('marketplace_orders', 'paid_at')) {
                    $table->timestamp('paid_at')->nullable()->after('transaction_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('marketplace_orders')) {
            Schema::table('marketplace_orders', function (Blueprint $table) {
                if (Schema::hasColumn('marketplace_orders', 'paid_at')) {
                    $table->dropColumn('paid_at');
                }
                if (Schema::hasColumn('marketplace_orders', 'transaction_id')) {
                    $table->dropColumn('transaction_id');
                }
            });
        }
    }
};
