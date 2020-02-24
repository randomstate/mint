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
use RandomState\Stripe\Fake;

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

        $this->provideTestExpansions();
    }

    protected function provideTestExpansions()
    {
        Fake\Customer::expand('invoice_settings', function($customer) {
            return new class($customer) {
                protected $customer;

                public $default_payment_method;

                public function __construct($customer)
                {
                    $this->customer = $customer;
                    $this->default_payment_method = app(BillingProvider::class)->paymentMethods()->retrieve($this->customer->invoice_settings->default_payment_method);
                }

                public function expandDefaultPaymentMethod()
                {
                    return $this->default_payment_method;
                }
            };
        });

        Fake\Customer::expand('default_source', function($customer) {
            return app(Fake\DummySourceFactory::class)->build($customer->invoice_settings->default_payment_method);
        });

        Fake\Plan::expand('product', function($plan) {
            return $plan->product;
        });

        Fake\Subscription::expand('latest_invoice', function() {
            return Fake\Invoice::constructFrom([]);
        });
    }
}