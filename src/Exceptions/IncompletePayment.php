<?php


namespace RandomState\Mint\Exceptions;


use Exception;
use RandomState\Mint\Mint\Payment;

abstract class IncompletePayment extends Exception
{
    /**
     * @var Payment
     */
    protected $payment;

    public function __construct(Payment $payment,  $message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);

        $this->payment = $payment;
    }
}