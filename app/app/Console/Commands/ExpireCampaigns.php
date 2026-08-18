<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireCampaigns extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'campaigns:expire';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Disable campaign products whose campaigns have ended';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $expiredCampaigns = Campaign::query()
            ->whereDate('end_date', '<', now()->toDateString())
            ->whereHas('product', function ($query) {
                $query->where('is_campaign', true);
            })
            ->get(['id', 'product_id']);

        if ($expiredCampaigns->isEmpty()) {
            $this->info('No expired campaigns found.');

            return self::SUCCESS;
        }

        $productIds = $expiredCampaigns
            ->pluck('product_id')
            ->filter()
            ->unique()
            ->values();

        DB::transaction(function () use ($productIds) {
            Product::query()
                ->whereIn('id', $productIds)
                ->update(['is_campaign' => false]);

            Campaign::query()
                ->whereIn('product_id', $productIds)
                ->whereDate('end_date', '<', now()->toDateString())
                ->update(['status' => 'inactive']);
        });

        $this->info("Expired campaigns processed for {$productIds->count()} product(s).");

        return self::SUCCESS;
    }
}
