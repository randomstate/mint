<?php


namespace RandomState\Mint\Mint;


use RandomState\Stripe\BillingProvider;

trait Billing
{
    /**
     * @return BillingProvider
     */
    protected function stripe()
    {
        return app(BillingProvider::class);
    }
}