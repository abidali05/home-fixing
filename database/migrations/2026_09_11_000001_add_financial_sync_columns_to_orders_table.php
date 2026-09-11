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
            if (!Schema::hasColumn('orders', 'customer_app_fee')) {
                $table->decimal('customer_app_fee', 10, 2)->default(3.00)->after('total_amount');
            }
            if (!Schema::hasColumn('orders', 'azhl_percentage')) {
                $table->decimal('azhl_percentage', 5, 2)->default(5.00)->after('customer_app_fee');
            }
            if (!Schema::hasColumn('orders', 'azhl_fee')) {
                $table->decimal('azhl_fee', 10, 2)->default(5.00)->after('azhl_percentage');
            }
            if (!Schema::hasColumn('orders', 'gateway_fee_percentage')) {
                $table->decimal('gateway_fee_percentage', 5, 2)->default(2.50)->after('azhl_fee');
            }
            if (!Schema::hasColumn('orders', 'gateway_vat_percentage')) {
                $table->decimal('gateway_vat_percentage', 5, 2)->default(15.00)->after('gateway_fee_percentage');
            }
            if (!Schema::hasColumn('orders', 'gateway_fee')) {
                $table->decimal('gateway_fee', 10, 2)->default(0.00)->after('gateway_vat_percentage');
            }
            if (!Schema::hasColumn('orders', 'gateway_vat')) {
                $table->decimal('gateway_vat', 10, 2)->default(0.00)->after('gateway_fee');
            }
            if (!Schema::hasColumn('orders', 'net_amount')) {
                $table->decimal('net_amount', 10, 2)->default(0.00)->after('gateway_vat');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $columns = [
                'customer_app_fee',
                'azhl_percentage',
                'azhl_fee',
                'gateway_fee_percentage',
                'gateway_vat_percentage',
                'gateway_fee',
                'gateway_vat',
                'net_amount',
            ];
            foreach ($columns as $col) {
                if (Schema::hasColumn('orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
