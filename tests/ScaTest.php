<?php


namespace RandomState\Mint\Tests;


use RandomState\Mint\Exceptions\PaymentActionRequired;
use RandomState\Mint\Exceptions\PaymentError;

class ScaTest extends TestCase
{
    /*
     * Requires action
     * Requires payment method
     * Payment failed
     */

    /**
     * @test
     */
    public function error_when_payment_requires_action()
    {
        $this->expectException(PaymentActionRequired::class);

        $plan = $this->dummyPlan('test');
        $billable = $this->dummyBillable();

        $billable->newSubscription($plan->id)
            ->create('pm_card_threeDSecure2Required');
    }

    /**
     * @test
     */
    public function error_when_payment_fails()
    {
        $this->expectException(PaymentError::class);

        $plan = $this->dummyPlan('test');
        $billable = $this->dummyBillable();

        $billable->newSubscription($plan->id)
            ->create('pm_card_chargeCustomerFail');
    }

    /**
     * @test
     */
    public function error_when_manual_invoice_fails()
    {
        $this->expectException(PaymentActionRequired::class);

        $plan = $this->dummyPlan('test', 500);
        $plan2 = $this->dummyPlan('test2', 1000);

        $billable = $this->dummyBillable();

        $billable->newSubscription($plan->id)
            ->create('pm_card_visa');

        $billable->updateDefaultPaymentMethod('pm_card_threeDSecure2Required');

        $billable->subscriptions()
            ->first()
            ->switch($plan->id, $plan2->id)
            ->invoiceImmediately()
            ->update();
    }
}