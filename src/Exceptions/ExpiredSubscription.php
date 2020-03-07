<?php


namespace RandomState\Mint\Exceptions;


use RandomState\Mint\Subscription;
use Throwable;

class ExpiredSubscription extends \Exception
{
    /**
     * @var Subscription
     */
    protected $subscription;

    public function __construct(Subscription $subscription, $code = 0, Throwable $previous = null)
    {
        parent::__construct("The subscription {$subscription->stripe_id} has expired. A new subscription should be created instead.", $code, $previous);
        $this->subscription = $subscription;
    }

    public function subscription()
    {
        return $this->subscription;
    }
}