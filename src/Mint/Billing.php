<?php


namespace RandomState\Mint\Mint;


use RandomState\Stripe\BillingProvider;

trait Billing
{
    protected function stripe()
    {
        return app(BillingProvider::class);
    }
}