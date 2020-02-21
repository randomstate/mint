<?php


namespace RandomState\Mint\Tests;


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