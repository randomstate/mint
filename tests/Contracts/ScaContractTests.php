<?php


namespace RandomState\Mint\Tests\Contracts;


use Illuminate\Support\Facades\Event;
use RandomState\Mint\Events\PaymentActionRequiredOffSession;
use RandomState\Mint\Events\PaymentErrorOffSession;
use RandomState\Mint\Exceptions\PaymentActionRequired;
use RandomState\Mint\Exceptions\PaymentError;
use Stripe\Exception\CardException;

trait ScaContractTests
{
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

    /**
     * @test
     */
    public function off_session_payment_failed_events_are_dispatched()
    {
        // create a user with a subscription that fails SCA
        // assert events not fired

        // change default payment method to one that requires 3d secure
        // change subscription so that user is billed
        // assert event is fired (due to confirmation required)

        // update payment method to one that is confirmed but fails
        // assert event is fired (due to invalid payment method)
        Event::fake();

        $billable = $this->dummyBillable();

        $standardPlan = $this->dummyPlan('standard', 1000);
        $proPlan = $this->dummyPlan('pro', 2000);

        $billable->newSubscription($standardPlan->id)
            ->create($this->validPaymentMethod()->id);


        Event::assertNotDispatched(PaymentActionRequiredOffSession::class);
        Event::assertNotDispatched(PaymentErrorOffSession::class);

        $billable->updateDefaultPaymentMethod('pm_card_threeDSecure2Required');

        $this->stripe->subscriptions()->update($billable->subscription()->stripe_id, [
            'items' => [
                [
                    'id' => $billable->subscription()->items()->first()->stripe_id,
                    'plan' => $proPlan->id,
                ]
            ],
        ]);

        try {
            $billable->subscription()->invoice();
        } catch(\Throwable $t) {}; // ignore immediate errors

        // manually trigger sync that would happen in webhook:
        $billable->subscription()->syncFromStripe($billable->subscription()->asStripe());

        Event::assertDispatchedTimes(PaymentActionRequiredOffSession::class, 1);
        Event::assertNotDispatched(PaymentErrorOffSession::class);

        $billable->updateDefaultPaymentMethod('pm_card_chargeCustomerFail');
        $invoice = $this->stripe->invoices()->retrieve($billable->subscription()->asStripe()->latest_invoice);
        try {
            $invoice->pay();
        } catch(CardException $e) {};

        $billable->subscription()->syncFromStripe($billable->subscription()->asStripe());

        Event::assertDispatchedTimes(PaymentActionRequiredOffSession::class, 1);
        Event::assertDispatchedTimes(PaymentErrorOffSession::class, 1);
    }
}
