<?php


namespace RandomState\Mint\Tests\Fake;


use RandomState\Mint\Tests\Contracts\SubscriptionsContractsTests;
use RandomState\Stripe\Fake\Invoice;
use Stripe\PaymentIntent;

class SubscriptionsTest extends FakeTestCase
{
    use SubscriptionsContractsTests;

    protected function setUp(): void
    {
        parent::setUp();

        Invoice::expand('payment_intent', function() {
            return PaymentIntent::constructFrom([]);
        });
    }
}