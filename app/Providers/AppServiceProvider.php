<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\FirebaseService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(FirebaseService::class, function () {
            return new FirebaseService();
        });

        $this->app->singleton(\Twilio\Rest\Client::class, function () {
            return new \Twilio\Rest\Client(config('services.twilio.sid'), config('services.twilio.token'));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
