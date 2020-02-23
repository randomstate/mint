<?php


namespace RandomState\Mint\Tests\Contracts;


use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event as LaravelEvent;
use Illuminate\Support\Facades\Route;
use RandomState\Stripe\Stripe\Events;
use RandomState\Stripe\Stripe\WebhookListener;
use RandomState\Stripe\Stripe\WebhookSigner;
use Stripe\Event;

trait WebhooksContractTests
{
    /**
     * @var WebhookListener
     */
    protected $webhooks;

    /**
     * @test
     */
    public function rejects_invalid_stripe_webhooks()
    {
        $this->webhooks->listen(function (Event $event, $signature) {
            $this->postJson(
                '/webhooks/stripe',
                $event->jsonSerialize(),
                [
                    'stripe-signature' => 'not_real_signature',
                ]
            )->assertStatus(403);
        });

        $this->webhooks->during(function () {
            $this->stripe->customers()->create();
        });
    }

    /**
     * @test
     */
    public function accepts_valid_stripe_webhooks_and_dispatches_events()
    {
        LaravelEvent::fake();

        $this->webhooks->listen(function (Event $event, $signature) {
            $this->postJson(
                '/webhooks/stripe',
                $event->jsonSerialize(),
                [
                    'stripe-signature' => $signature
                ]
            )->assertStatus(200);
        });

        $this->webhooks->during(function () {
            $this->stripe->customers()->create();
        });

        LaravelEvent::assertDispatched('stripe:customer.created');
    }
}
