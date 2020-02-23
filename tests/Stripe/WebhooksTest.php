<?php


namespace RandomState\Mint\Tests\Stripe;


use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use RandomState\Mint\Tests\Contracts\WebhooksContractTests;
use RandomState\Mint\Tests\TestCase;
use RandomState\Stripe\Stripe\Events;
use RandomState\Stripe\Stripe\WebhookListener;
use RandomState\Stripe\Stripe\WebhookSigner;

class WebhooksTest extends TestCase
{
    use WebhooksContractTests;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Router $router */
        $router = $this->app['router'];

        $router->group([], function () {
            Route::mint();
        });

        $this->webhooks = new WebhookListener(
            new Events(config('mint.secret_key')),
            new WebhookSigner(config('mint.secret_key'))
        );
    }
}