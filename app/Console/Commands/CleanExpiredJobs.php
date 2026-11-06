<?php

namespace App\Console\Commands;

use App\Models\BidModel;
use App\Models\JobRequestImages;
use App\Models\JobRequestModel;
use App\Models\Orders;
use Illuminate\Console\Command;

class CleanExpiredJobs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'jobs:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically delete jobs if no provider is hired or no bids are received within 1 hour of creation';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $cutoff = now()->subHours(1);

        // Find expired jobs (pending jobs older than 1 hour)
        $expiredJobs = JobRequestModel::where('status', 'pending')
            ->where('created_at', '<=', $cutoff)
            ->get();

        if ($expiredJobs->isNotEmpty()) {
            $expiredJobIds = $expiredJobs->pluck('id')->toArray();

            // 1. Delete associated image files from disk and database
            $images = JobRequestImages::whereIn('job_id', $expiredJobIds)->get();
            foreach ($images as $image) {
                $rawImage = basename((string) $image->path);
                if (!empty($rawImage)) {
                    $imagePath = public_path('uploads/job_gallery/' . $rawImage);
                    if (file_exists($imagePath) && is_file($imagePath)) {
                        @unlink($imagePath);
                    }
                }
            }
            JobRequestImages::whereIn('job_id', $expiredJobIds)->delete();

            // 2. Delete associated video files from disk
            foreach ($expiredJobs as $job) {
                $rawVideo = basename((string) $job->getRawOriginal('video'));
                if (!empty($rawVideo)) {
                    $videoPath = public_path('uploads/job_gallery/' . $rawVideo);
                    if (file_exists($videoPath) && is_file($videoPath)) {
                        @unlink($videoPath);
                    }
                }
            }

            // 3. Delete associated bids
            BidModel::whereIn('job_id', $expiredJobIds)->delete();

            // 4. Delete associated orders
            Orders::whereIn('job_id', $expiredJobIds)->delete();

            // 5. Delete the jobs
            $expiredJobsCount = JobRequestModel::whereIn('id', $expiredJobIds)->delete();
        } else {
            $expiredJobsCount = 0;
        }

        $this->info("Successfully deleted {$expiredJobsCount} expired job request(s) along with their media and records.");

        return self::SUCCESS;
    }
}
