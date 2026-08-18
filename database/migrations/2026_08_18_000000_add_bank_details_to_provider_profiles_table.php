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
        Schema::table('provider_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('provider_profiles', 'iban')) {
                $table->string('iban', 35)->nullable()->after('certification');
            }
            if (!Schema::hasColumn('provider_profiles', 'account_title')) {
                $table->string('account_title', 255)->nullable()->after('iban');
            }
            if (!Schema::hasColumn('provider_profiles', 'bank_name')) {
                $table->string('bank_name', 255)->nullable()->after('account_title');
            }
            if (!Schema::hasColumn('provider_profiles', 'swift_code')) {
                $table->string('swift_code', 50)->nullable()->after('bank_name');
            }
            if (!Schema::hasColumn('provider_profiles', 'bank_location')) {
                $table->string('bank_location', 255)->nullable()->after('swift_code');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('provider_profiles', function (Blueprint $table) {
            $table->dropColumn(['iban', 'account_title', 'bank_name', 'swift_code', 'bank_location']);
        });
    }
};
