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
        Schema::create('marketplace_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('shop_title')->nullable();
            $table->string('shop_logo')->nullable();
            $table->string('shop_banner_image')->nullable();
            $table->string('tag_line')->nullable();
            $table->decimal('delivery_charges', 10, 2)->nullable();
            $table->text('bio')->nullable();
            $table->json('service_category')->nullable();
            $table->json('operation_hours')->nullable();
            $table->string('shop_status')->nullable();
            $table->string('document_type')->nullable();
            $table->string('document_number')->nullable();
            $table->timestamps();
        });

        $users = DB::table('users')->get();

        foreach ($users as $user) {
            $hasRoles = array_filter(array_map('trim', explode(',', (string) ($user->has_roles ?? ''))));
            $isMarketplace = (string) $user->role === '2' || in_array('2', $hasRoles, true);

            if (!$isMarketplace) {
                continue;
            }

            DB::table('marketplace_profiles')->updateOrInsert(
                ['user_id' => $user->id],
                [
                    'shop_title' => $user->shop_title ?? null,
                    'shop_logo' => $user->shop_logo ?? null,
                    'shop_banner_image' => $user->shop_banner_image ?? null,
                    'tag_line' => $user->tag_line ?? null,
                    'delivery_charges' => $user->delivery_charges ?? null,
                    'bio' => $user->bio ?? null,
                    'service_category' => !empty($user->service_category) ? $user->service_category : json_encode([]),
                    'operation_hours' => $user->operation_hours ?? null,
                    'shop_status' => $user->shop_status ?? null,
                    'document_type' => $user->document_type ?? null,
                    'document_number' => $user->document_number ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketplace_profiles');
    }
};
