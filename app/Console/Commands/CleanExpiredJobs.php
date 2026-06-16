<?php

namespace App\Console\Commands;

use App\Models\JobRequestModel;
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

        // Find and delete pending jobs older than 1 hour
        $expiredJobsCount = JobRequestModel::where('status', 'pending')
            ->where('created_at', '<=', $cutoff)
            ->delete();

        $this->info("Successfully deleted {$expiredJobsCount} expired job request(s).");

        return self::SUCCESS;
    }
}
