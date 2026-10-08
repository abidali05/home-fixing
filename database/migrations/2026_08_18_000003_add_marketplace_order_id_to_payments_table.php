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
        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'marketplace_order_id')) {
                $table->unsignedBigInteger('marketplace_order_id')->nullable()->after('provider_id')->index();
            }
            $table->unsignedBigInteger('job_id')->nullable()->change();
            $table->unsignedBigInteger('bid_id')->nullable()->change();
            $table->unsignedBigInteger('provider_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'marketplace_order_id')) {
                $table->dropColumn('marketplace_order_id');
            }
        });
    }
};
