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
            if (!Schema::hasColumn('orders', 'extra_amount')) {
                $table->decimal('extra_amount', 10, 2)->default(0.00)->after('price');
            }
            if (!Schema::hasColumn('orders', 'extra_amount_reason')) {
                $table->text('extra_amount_reason')->nullable()->after('extra_amount');
            }
            if (!Schema::hasColumn('orders', 'extra_amount_status')) {
                $table->string('extra_amount_status', 20)->default('none')->after('extra_amount_reason');
            }
            if (!Schema::hasColumn('orders', 'total_amount')) {
                $table->decimal('total_amount', 10, 2)->default(0.00)->after('extra_amount_status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $columnsToDrop = [];
            if (Schema::hasColumn('orders', 'extra_amount')) {
                $columnsToDrop[] = 'extra_amount';
            }
            if (Schema::hasColumn('orders', 'extra_amount_reason')) {
                $columnsToDrop[] = 'extra_amount_reason';
            }
            if (Schema::hasColumn('orders', 'extra_amount_status')) {
                $columnsToDrop[] = 'extra_amount_status';
            }
            if (Schema::hasColumn('orders', 'total_amount')) {
                $columnsToDrop[] = 'total_amount';
            }
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
