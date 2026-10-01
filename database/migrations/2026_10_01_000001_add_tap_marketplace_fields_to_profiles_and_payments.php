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
        // 1. Provider Profiles
        if (Schema::hasTable('provider_profiles')) {
            Schema::table('provider_profiles', function (Blueprint $table) {
                if (!Schema::hasColumn('provider_profiles', 'tap_lead_id')) {
                    $table->string('tap_lead_id', 100)->nullable()->after('bank_location');
                }
                if (!Schema::hasColumn('provider_profiles', 'tap_destination_id')) {
                    $table->string('tap_destination_id', 100)->nullable()->index()->after('tap_lead_id');
                }
                if (!Schema::hasColumn('provider_profiles', 'tap_kyc_status')) {
                    $table->string('tap_kyc_status', 50)->default('pending')->after('tap_destination_id');
                }
                if (!Schema::hasColumn('provider_profiles', 'tap_payout_enabled')) {
                    $table->boolean('tap_payout_enabled')->default(false)->after('tap_kyc_status');
                }
            });
        }

        // 2. Marketplace Profiles
        if (Schema::hasTable('marketplace_profiles')) {
            Schema::table('marketplace_profiles', function (Blueprint $table) {
                if (!Schema::hasColumn('marketplace_profiles', 'tap_lead_id')) {
                    $table->string('tap_lead_id', 100)->nullable()->after('bank_location');
                }
                if (!Schema::hasColumn('marketplace_profiles', 'tap_destination_id')) {
                    $table->string('tap_destination_id', 100)->nullable()->index()->after('tap_lead_id');
                }
                if (!Schema::hasColumn('marketplace_profiles', 'tap_kyc_status')) {
                    $table->string('tap_kyc_status', 50)->default('pending')->after('tap_destination_id');
                }
                if (!Schema::hasColumn('marketplace_profiles', 'tap_payout_enabled')) {
                    $table->boolean('tap_payout_enabled')->default(false)->after('tap_kyc_status');
                }
            });
        }

        // 3. Payments
        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                if (!Schema::hasColumn('payments', 'tap_destination_id')) {
                    $table->string('tap_destination_id', 100)->nullable()->after('tap_charge_id');
                }
                if (!Schema::hasColumn('payments', 'tap_split_amount')) {
                    $table->decimal('tap_split_amount', 10, 2)->nullable()->after('tap_destination_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('provider_profiles')) {
            Schema::table('provider_profiles', function (Blueprint $table) {
                $table->dropColumn(['tap_lead_id', 'tap_destination_id', 'tap_kyc_status', 'tap_payout_enabled']);
            });
        }

        if (Schema::hasTable('marketplace_profiles')) {
            Schema::table('marketplace_profiles', function (Blueprint $table) {
                $table->dropColumn(['tap_lead_id', 'tap_destination_id', 'tap_kyc_status', 'tap_payout_enabled']);
            });
        }

        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropColumn(['tap_destination_id', 'tap_split_amount']);
            });
        }
    }
};
