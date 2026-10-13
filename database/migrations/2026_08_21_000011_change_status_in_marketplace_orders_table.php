<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('marketplace_orders')) {
            try {
                DB::statement("ALTER TABLE `marketplace_orders` MODIFY `status` VARCHAR(50) NOT NULL DEFAULT 'pending'");
            } catch (\Throwable $e) {
                Schema::table('marketplace_orders', function (Blueprint $table) {
                    $table->string('status', 50)->default('pending')->change();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No action required on rollback
    }
};
