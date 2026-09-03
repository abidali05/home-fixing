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
        Schema::table('system_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('system_settings', 'customer_app_fee')) {
                $table->decimal('customer_app_fee', 8, 2)->default(3.00)->after('azhl_fee');
            }
            if (!Schema::hasColumn('system_settings', 'payment_gateway_fee_percentage')) {
                $table->decimal('payment_gateway_fee_percentage', 5, 2)->default(2.50)->after('customer_app_fee');
            }
            if (!Schema::hasColumn('system_settings', 'payment_gateway_fixed_fee')) {
                $table->decimal('payment_gateway_fixed_fee', 8, 2)->default(1.00)->after('payment_gateway_fee_percentage');
            }
            if (!Schema::hasColumn('system_settings', 'payment_gateway_vat_percentage')) {
                $table->decimal('payment_gateway_vat_percentage', 5, 2)->default(15.00)->after('payment_gateway_fixed_fee');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn([
                'customer_app_fee',
                'payment_gateway_fee_percentage',
                'payment_gateway_fixed_fee',
                'payment_gateway_vat_percentage',
            ]);
        });
    }
};
