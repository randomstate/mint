<?php


namespace RandomState\Mint;


use Closure;
use RandomState\Mint\Mint\Plans;
use RandomState\Stripe\BillingProvider;

class Mint
{
    public static $ignoreMigrations = false;

    /**
     * @var null | Closure
     */
    public static $billableResolver = null;

    /**
     * @var BillingProvider
     */
    protected $stripe;

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
        if(static::$billableResolver) {
            return (static::$billableResolver)($customerId);
        }

        return config('mint.model')::where('stripe_id', $customerId)->firstOrFail();
    }
}