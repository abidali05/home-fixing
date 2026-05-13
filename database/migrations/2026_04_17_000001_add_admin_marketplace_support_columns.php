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
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'provider_status')) {
                $table->enum('provider_status', ['active', 'inactive', 'suspended', 'banned'])
                    ->default('inactive')
                    ->after('status');
            }

            if (!Schema::hasColumn('users', 'marketplace_status')) {
                $table->enum('marketplace_status', ['active', 'inactive', 'suspended', 'banned'])
                    ->default('inactive')
                    ->after('provider_status');
            }
        });

        DB::table('users')->orderBy('id')->chunkById(100, function ($users) {
            foreach ($users as $user) {
                $roles = array_filter(array_map('trim', explode(',', (string) ($user->has_roles ?? ''))));
                $status = in_array($user->status, ['active', 'inactive', 'suspended', 'banned'], true)
                    ? $user->status
                    : 'inactive';

                $updates = [];

                if ((string) $user->role === '1' || in_array('1', $roles, true)) {
                    $updates['provider_status'] = $status;
                }

                if ((string) $user->role === '2' || in_array('2', $roles, true)) {
                    $updates['marketplace_status'] = $status;
                }

                if (!empty($updates)) {
                    DB::table('users')->where('id', $user->id)->update($updates);
                }
            }
        });

        Schema::table('marketplace_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_orders', 'payment_status')) {
                $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded'])
                    ->default('pending')
                    ->after('payment_method');
            }
        });

        DB::table('marketplace_orders')
            ->whereNull('payment_status')
            ->update([
                'payment_status' => DB::raw("
                    CASE
                        WHEN status = 'completed' THEN 'paid'
                        ELSE 'pending'
                    END
                "),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketplace_orders', function (Blueprint $table) {
            if (Schema::hasColumn('marketplace_orders', 'payment_status')) {
                $table->dropColumn('payment_status');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'marketplace_status')) {
                $table->dropColumn('marketplace_status');
            }

            if (Schema::hasColumn('users', 'provider_status')) {
                $table->dropColumn('provider_status');
            }
        });
    }
};
