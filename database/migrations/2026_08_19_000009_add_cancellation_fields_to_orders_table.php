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
            if (!Schema::hasColumn('orders', 'cancelled_by_type')) {
                $table->string('cancelled_by_type', 20)->nullable(); // customer, provider, marketplace
            }
            if (!Schema::hasColumn('orders', 'cancelled_by_id')) {
                $table->unsignedBigInteger('cancelled_by_id')->nullable();
            }
            if (!Schema::hasColumn('orders', 'cancellation_reason')) {
                $table->text('cancellation_reason')->nullable();
            }
            if (!Schema::hasColumn('orders', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable();
            }
            if (!Schema::hasColumn('orders', 'refund_status')) {
                $table->string('refund_status', 30)->nullable()->default('not_required');
            }
            if (!Schema::hasColumn('orders', 'refund_id')) {
                $table->unsignedBigInteger('refund_id')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'cancelled_by_type',
                'cancelled_by_id',
                'cancellation_reason',
                'cancelled_at',
                'refund_status',
                'refund_id',
            ]);
        });
    }
};
