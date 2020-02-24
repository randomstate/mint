<?php


namespace RandomState\Mint\Tests\Fake;


use RandomState\Mint\Mint;
use RandomState\Mint\Tests\TestCase;
use RandomState\Stripe\BillingProvider;
use RandomState\Stripe\Fake;

class FakeTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(BillingProvider::class, $fake = new Fake());
        $this->mint = $this->app->make(Mint::class);

        $this->stripe = $fake;
    }
}