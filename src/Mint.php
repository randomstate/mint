<?php


namespace RandomState\Mint;


use RandomState\Mint\Mint\Plans;
use RandomState\Stripe\BillingProvider;

class Mint
{
    /**
     * @var BillingProvider
     */
    protected BillingProvider $stripe;

    public function __construct(BillingProvider $stripe)
    {
        $this->stripe = $stripe;
    }

    public function plans()
    {
        return new Plans($this);
    }

    public function stripe()
    {
        return $this->stripe;
    }

    public function billable($customerId)
    {
        return config('mint.model')::where('stripe_id', $customerId)->firstOrFail();
    }
}