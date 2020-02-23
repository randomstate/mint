<?php


namespace RandomState\Mint\Mint;


use RandomState\Mint\Exceptions\PaymentActionRequired;
use RandomState\Mint\Exceptions\PaymentError;
use Stripe\PaymentIntent;

class Payment
{
    /**
     * @var PaymentIntent
     */
    protected $intent;

    public function __construct(PaymentIntent $intent)
    {
        $this->intent = $intent;
    }

    public function validate()
    {
        if ($this->requiresPaymentMethod()) {
            throw PaymentError::invalidPaymentMethod($this);
        } elseif ($this->requiresAction()) {
            throw PaymentActionRequired::incomplete($this);
        }

        return $this;
    }

    public function requiresAction()
    {
        return $this->status() === PaymentIntent::STATUS_REQUIRES_ACTION;
    }

    public function requiresPaymentMethod()
    {
        return $this->status() === PaymentIntent::STATUS_REQUIRES_PAYMENT_METHOD;
    }

    protected function status()
    {
        return $this->intent->status;
    }

    public function intent()
    {
        return $this->intent;
    }
}