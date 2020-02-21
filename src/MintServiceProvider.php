<?php


namespace RandomState\Mint;


use Illuminate\Support\ServiceProvider;
use RandomState\Stripe\BillingProvider;
use RandomState\Stripe\Stripe;

class MintServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/mint.php', 'mint');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    public function register()
    {
        $this->app->bind(BillingProvider::class, function() {
            return new Stripe(config('mint.secret_key'));
        });
    }
}