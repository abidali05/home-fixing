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
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'identity_document')) {
                $table->string('identity_document', 255)->nullable()->after('document_number');
            }
            if (!Schema::hasColumn('users', 'live_image')) {
                $table->string('live_image', 255)->nullable()->after('identity_document');
            }
            if (!Schema::hasColumn('users', 'verification_status')) {
                $table->string('verification_status', 50)->default('pending')->after('live_image');
            }
            if (!Schema::hasColumn('users', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('verification_status');
            }
        });

        Schema::table('provider_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('provider_profiles', 'identity_document')) {
                $table->string('identity_document', 255)->nullable()->after('document_number');
            }
            if (!Schema::hasColumn('provider_profiles', 'live_image')) {
                $table->string('live_image', 255)->nullable()->after('identity_document');
            }
            if (!Schema::hasColumn('provider_profiles', 'verification_status')) {
                $table->string('verification_status', 50)->default('pending')->after('live_image');
            }
            if (!Schema::hasColumn('provider_profiles', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('verification_status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $cols = [];
            foreach (['identity_document', 'live_image', 'verification_status', 'rejection_reason'] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $cols[] = $col;
                }
            }
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });

        Schema::table('provider_profiles', function (Blueprint $table) {
            $cols = [];
            foreach (['identity_document', 'live_image', 'verification_status', 'rejection_reason'] as $col) {
                if (Schema::hasColumn('provider_profiles', $col)) {
                    $cols[] = $col;
                }
            }
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
