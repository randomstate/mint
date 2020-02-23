<?php


namespace RandomState\Mint\Tests\Stripe;


use RandomState\Mint\Tests\TestCase;
use RandomState\Stripe\BillingProvider;
use RandomState\Stripe\Stripe;

class StripeTest extends TestCase
{
    /**
     * @test
     */
    public function can_resolve_stripe()
    {
        $provider = $this->app->make(BillingProvider::class);
        $this->assertInstanceOf(Stripe::class, $provider);
    }
}