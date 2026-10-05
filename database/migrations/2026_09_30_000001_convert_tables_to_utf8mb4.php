<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Privacies table
        if (Schema::hasTable('privacies')) {
            try {
                DB::statement('ALTER TABLE `privacies` MODIFY `role` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL');
            } catch (\Throwable $e) {}

            try {
                DB::statement('ALTER TABLE `privacies` ENGINE = InnoDB');
            } catch (\Throwable $e) {}

            try {
                DB::statement('ALTER TABLE `privacies` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            } catch (\Throwable $e) {}

            try {
                DB::statement('ALTER TABLE `privacies` MODIFY `content` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL');
            } catch (\Throwable $e) {}
        }

        // 2. Terms & Conditions table
        if (Schema::hasTable('terms_conditions')) {
            try {
                DB::statement('ALTER TABLE `terms_conditions` MODIFY `role` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL');
            } catch (\Throwable $e) {}

            try {
                DB::statement('ALTER TABLE `terms_conditions` ENGINE = InnoDB');
            } catch (\Throwable $e) {}

            try {
                DB::statement('ALTER TABLE `terms_conditions` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            } catch (\Throwable $e) {}

            try {
                DB::statement('ALTER TABLE `terms_conditions` MODIFY `content` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL');
            } catch (\Throwable $e) {}
        }

        // 3. Other tables that were using latin1
        $otherTables = [
            'account_active_requests',
            'app_versions',
            'campaigns',
            'carts',
            'favorite_marketplaces',
            'job_notifications',
            'marketplace_shop_reviews',
            'product_views',
            'products',
            'store_visits',
        ];

        foreach ($otherTables as $tableName) {
            if (Schema::hasTable($tableName)) {
                try {
                    DB::statement("ALTER TABLE `{$tableName}` ENGINE = InnoDB");
                } catch (\Throwable $e) {}

                try {
                    DB::statement("ALTER TABLE `{$tableName}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                } catch (\Throwable $e) {}
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to downgrade character sets back to latin1
    }
};
