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
        Schema::table('store_visits', function (Blueprint $table) {
            $table->unsignedInteger('visit_count')->default(1)->after('visitor_user_id');
        });

        Schema::table('product_views', function (Blueprint $table) {
            $table->unsignedInteger('view_count')->default(1)->after('is_through_campaign');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('store_visits', function (Blueprint $table) {
            $table->dropColumn('visit_count');
        });

        Schema::table('product_views', function (Blueprint $table) {
            $table->dropColumn('view_count');
        });
    }
};
