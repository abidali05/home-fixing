<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\FirebaseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class SendJobRequestNotification implements ShouldQueue
{
    use Dispatchable, Queueable, SerializesModels;

    public int $jobId;
    public int $categoryId;

    protected FirebaseService $firebaseService;

    /**
     * Create a new job instance.
     */
    public function __construct(int $jobId, int $categoryId)
    {
        $this->jobId = $jobId;
        $this->categoryId = $categoryId;
    }

    /**
     * Execute the job.
     */
    public function handle(FirebaseService $firebaseService): void
    {
        $messaging = $firebaseService->messaging();

        Log::info('🚀 MAIN JOB STARTED', [
            'job_id' => $this->jobId,
            'category_id' => $this->categoryId
        ]);

        $start = microtime(true);

        try {
            Log::info('STEP 1: Query starting');

            $query = User::query()
                ->select(['id', 'fcm_token'])
                ->where('role', 1)
                ->whereNotNull('fcm_token')
                ->where('fcm_token', '!=', '')
                ->whereHas('providerProfile', function ($q) {
                    $q->where(function ($sub) {
                        $sub->whereJsonContains('service_category', (int) $this->categoryId)
                            ->orWhereJsonContains('service_category', (string) $this->categoryId);
                    });
                });

            Log::info('STEP 2: Query built successfully');

            $providers = $query->get();

            Log::info('STEP 3: Providers fetched', [
                'count' => $providers->count(),
                'provider_ids' => $providers->pluck('id')->toArray(),
            ]);

            foreach ($providers as $provider) {

                Log::info('PROVIDER DATA', [
                    'id' => $provider->id,
                    'fcm_token' => substr($provider->fcm_token, 0, 20) . '...'
                ]);

                if (!$provider->fcm_token) {
                    Log::warning('SKIPPED: Empty FCM token', [
                        'provider_id' => $provider->id
                    ]);
                    continue;
                }

                try {
                    Log::info('SENDING TO PROVIDER', [
                        'provider_id' => $provider->id,
                    ]);

                    $message = CloudMessage::withTarget('token', $provider->fcm_token)
                        ->withNotification(Notification::create(
                            'New Job Request',
                            'A new job request is available in your category'
                        ))
                        ->withData([
                            'job_id' => (string) $this->jobId,
                            'category_id' => (string) $this->categoryId,
                        ]);

                    $messaging->send($message);

                    Log::info('✅ SENT SUCCESS', [
                        'provider_id' => $provider->id
                    ]);

                } catch (\Throwable $e) {

                    Log::error('❌ FCM FAILED', [
                        'provider_id' => $provider->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            Log::info('🏁 ALL NOTIFICATIONS COMPLETED', [
                'execution_time_seconds' => microtime(true) - $start,
                'total_providers' => $providers->count()
            ]);

        } catch (\Throwable $e) {

            Log::error('❌ JOB FAILED', [
                'error' => $e->getMessage()
            ]);
        }
    }
}
