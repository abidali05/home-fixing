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
        Schema::create('provider_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('provider_type')->nullable();
            $table->string('company_name')->nullable();
            $table->string('company_logo')->nullable();
            $table->string('latitude')->nullable();
            $table->string('longitude')->nullable();
            $table->text('address')->nullable();
            $table->json('service_category')->nullable();
            $table->string('experience')->nullable();
            $table->time('work_hour_start')->nullable();
            $table->time('work_hour_end')->nullable();
            $table->text('bio')->nullable();
            $table->string('charge_type')->nullable();
            $table->string('charge_amount')->nullable();
            $table->string('document_type')->nullable();
            $table->string('document_number')->nullable();
            $table->string('service_license')->nullable();
            $table->string('certification')->nullable();
            $table->timestamps();
        });

        $users = DB::table('users')->get();

        foreach ($users as $user) {
            $hasRoles = array_filter(array_map('trim', explode(',', (string) ($user->has_roles ?? ''))));
            $isProvider = (string) $user->role === '1' || in_array('1', $hasRoles, true);

            if (!$isProvider) {
                continue;
            }

            DB::table('provider_profiles')->updateOrInsert(
                ['user_id' => $user->id],
                [
                    'provider_type' => $user->provider_type ?? null,
                    'company_name' => $user->company_name ?? null,
                    'company_logo' => $user->company_logo ?? null,
                    'latitude' => $user->latitude ?? null,
                    'longitude' => $user->longitude ?? null,
                    'address' => $user->address ?? null,
                    'service_category' => !empty($user->service_category) ? $user->service_category : json_encode([]),
                    'experience' => $user->experience ?? null,
                    'work_hour_start' => $user->work_hour_start ?? null,
                    'work_hour_end' => $user->work_hour_end ?? null,
                    'bio' => $user->bio ?? null,
                    'charge_type' => $user->charge_type ?? null,
                    'charge_amount' => $user->charge_amount ?? null,
                    'document_type' => $user->document_type ?? null,
                    'document_number' => $user->document_number ?? null,
                    'service_license' => $user->service_license ?? null,
                    'certification' => $user->certification ?? null,
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
        Schema::dropIfExists('provider_profiles');
    }
};
