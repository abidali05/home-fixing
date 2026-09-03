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
        Schema::table('bank_accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('bank_accounts', 'is_primary')) {
                $table->boolean('is_primary')->default(false)->after('swift_code');
            }
            if (!Schema::hasColumn('bank_accounts', 'account_number')) {
                $table->string('account_number', 50)->nullable()->after('account_title');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('bank_accounts', 'is_primary')) {
                $table->dropColumn('is_primary');
            }
            if (Schema::hasColumn('bank_accounts', 'account_number')) {
                $table->dropColumn('account_number');
            }
        });
    }
};
