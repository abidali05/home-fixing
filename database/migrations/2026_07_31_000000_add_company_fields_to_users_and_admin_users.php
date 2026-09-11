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
        if (Schema::hasTable('admin_users')) {
            Schema::table('admin_users', function (Blueprint $table) {
                if (!Schema::hasColumn('admin_users', 'is_company')) {
                    $table->boolean('is_company')->default(false)->after('status');
                }
                if (!Schema::hasColumn('admin_users', 'company_name')) {
                    $table->string('company_name')->nullable()->after('is_company');
                }
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'company_id')) {
                    $table->unsignedBigInteger('company_id')->nullable()->after('id');
                    $table->foreign('company_id')->references('id')->on('admin_users')->onDelete('set null')->onUpdate('cascade');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (Schema::hasColumn('users', 'company_id')) {
                    $table->dropForeign(['company_id']);
                    $table->dropColumn('company_id');
                }
            });
        }

        if (Schema::hasTable('admin_users')) {
            Schema::table('admin_users', function (Blueprint $table) {
                if (Schema::hasColumn('admin_users', 'is_company')) {
                    $table->dropColumn('is_company');
                }
                if (Schema::hasColumn('admin_users', 'company_name')) {
                    $table->dropColumn('company_name');
                }
            });
        }
    }
};
