<?php


namespace RandomState\Mint\Exceptions;


use RandomState\Mint\Mint\Payment;

class PaymentError extends IncompletePayment
{
    public static function invalidPaymentMethod(Payment $payment)
    {
        return new self(
            $payment,
            'The payment attempt failed because the supplied payment method is invalid.',
        );
    }
}