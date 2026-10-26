<?php

namespace App\Jobs;

use App\Models\JobRequestModel;
use App\Models\User;
use App\Notifications\JobPostedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SendJobPostedNotifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $jobRequestId)
    {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        try {
            $jobRequest = JobRequestModel::find($this->jobRequestId);
            if (!$jobRequest) {
                return;
            }

            $jobLat = $jobRequest->latitude ? (float) $jobRequest->latitude : null;
            $jobLng = $jobRequest->longitude ? (float) $jobRequest->longitude : null;

            $providersQuery = User::query()
                ->where('role', 1)
                ->whereHas('providerProfile', function ($q) use ($jobRequest, $jobLat, $jobLng) {
                    $categoryId = (int) $jobRequest->category_id;

                    $q->where(function ($sub) use ($categoryId) {
                        $sub->whereJsonContains('service_category', $categoryId)
                            ->orWhereJsonContains('service_category', (string) $categoryId);
                    });

                    if ($jobLat && $jobLng) {
                        $q->whereNotNull('latitude')
                            ->whereNotNull('longitude')
                            ->whereRaw(
                                '(6371 * acos(least(1.0, greatest(-1.0, cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))))) <= 5',
                                [$jobLat, $jobLng, $jobLat]
                            );
                    }
                });

            $providers = $providersQuery->get();

            if ($providers->isNotEmpty()) {
                Notification::send($providers, new JobPostedNotification($jobRequest));
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send job posted notifications in job: ' . $e->getMessage(), [
                'job_request_id' => $this->jobRequestId,
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
