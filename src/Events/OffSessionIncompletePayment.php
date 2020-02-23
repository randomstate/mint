<?php


namespace RandomState\Mint\Events;


use Stripe\PaymentIntent;

class OffSessionIncompletePayment
{
    /**
     * @var PaymentIntent
     */
    protected $intent;

    public function __construct(PaymentIntent $intent)
    {
        $this->intent = $intent;
    }

    /**
     * @return PaymentIntent
     */
    public function intent()
    {
        return $this->intent;
    }
}