<?php


namespace RandomState\Mint;


use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use RandomState\Mint\Events\StripeWebhookSubscriber;
use RandomState\Mint\Http\Controllers\StripeWebhookController;
use RandomState\Mint\Http\Middleware\VerifyStripeSignature;
use RandomState\Stripe\BillingProvider;
use RandomState\Stripe\Stripe;
use Stripe\Stripe as BaseStripe;

class MintServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/mint.php', 'mint');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        if(config('mint.webhooks.sync')) {
            /** @var Dispatcher $events */
            $events = $this->app['events'];
            $events->subscribe(StripeWebhookSubscriber::class);
        }

        BaseStripe::setApiVersion(config('mint.api_version'));
        BaseStripe::setApiKey(config('mint.secret_key'));
    }

    public function register()
    {
        $this->app->bind(BillingProvider::class, function () {
            return new Stripe(config('mint.secret_key'));
        });

        $this->app->bind(VerifyStripeSignature::class, function () {
            return new VerifyStripeSignature(
                config('mint.secret_key'),
                config('mint.tolerance'),
            );
        });

        Route::macro('mint', function () {
            Route::post(config('mint.webhooks.path'),
                [StripeWebhookController::class, 'process'])->name('mint.stripe.webhook');
        });
    }
}