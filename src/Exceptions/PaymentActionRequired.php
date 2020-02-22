<?php


namespace RandomState\Mint\Exceptions;


use RandomState\Mint\Mint\Payment;

class PaymentActionRequired extends IncompletePayment
{
    public static function incomplete(Payment $payment) {
        return new self(
            $payment,
            'Additional action is required before this payment can be completed.'
        );
    }
}