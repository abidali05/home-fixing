<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('provider_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('provider_profiles', 'referral_code')) {
                $table->string('referral_code', 30)->nullable()->unique()->after('certification');
            }
            if (!Schema::hasColumn('provider_profiles', 'referred_by_id')) {
                $table->unsignedBigInteger('referred_by_id')->nullable()->index()->after('referral_code');
            }
            if (!Schema::hasColumn('provider_profiles', 'referred_by_code')) {
                $table->string('referred_by_code', 30)->nullable()->index()->after('referred_by_id');
            }

            $table->foreign('referred_by_id')->references('id')->on('users')->onDelete('set null');
        });

        // Backfill referral_code for all existing provider profiles
        $profiles = DB::table('provider_profiles')->whereNull('referral_code')->get();
        foreach ($profiles as $profile) {
            do {
                $code = 'REF-' . strtoupper(Str::random(6));
            } while (DB::table('provider_profiles')->where('referral_code', $code)->exists());

            DB::table('provider_profiles')->where('id', $profile->id)->update([
                'referral_code' => $code,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('provider_profiles', function (Blueprint $table) {
            $table->dropForeign(['referred_by_id']);
            $table->dropColumn(['referral_code', 'referred_by_id', 'referred_by_code']);
        });
    }
};
