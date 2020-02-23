<?php


namespace RandomState\Mint\Tests;


class SwapPlansTest extends TestCase
{
    /**
     * @test
     */
    public function can_swap_plan()
    {
        $billable = $this->dummyBillable();
        $standard = $this->dummyPlan('standard', 10000);
        $pro = $this->dummyPlan('pro', 20000);

        $billable
            ->newSubscription($standard->id)
            ->create($this->validPaymentMethod()->id);

        $this->assertEquals($standard->id, ($subscription = $billable->subscriptions()->first())->items()->first()->stripe_plan);
        $latestInvoiceId = $subscription->asStripe()->latest_invoice;

        $billable->subscriptions()->first()
            ->switch($standard->id, $pro->id)
            ->update();

        $this->assertEquals($pro->id, $subscription->items()->first()->stripe_plan);
        $this->assertEquals($latestInvoiceId,$subscription->asStripe()->latest_invoice);
    }

    /**
     * @test
     */
    public function can_swap_plan_and_invoice_immediately()
    {
        $billable = $this->dummyBillable();

        $standard = $this->dummyPlan('standard', 10000);
        $pro = $this->dummyPlan('pro', 20000);

        $billable
            ->newSubscription($standard->id)
            ->create($this->validPaymentMethod()->id);

        $this->assertEquals($standard->id, ($subscription = $billable->subscriptions()->first())->items()->first()->stripe_plan);
        $latestInvoiceId = $subscription->asStripe()->latest_invoice;

        $billable->subscriptions()->first()
            ->switch($standard->id, $pro->id)
            ->invoiceImmediately()
            ->update()
        ;

        $this->assertEquals($pro->id, $subscription->items()->first()->stripe_plan);
        $this->assertNotEquals($latestInvoiceId,$subscription->asStripe()->latest_invoice);
    }

    /**
     * @test
     */
    public function skip_trial_when_swapping()
    {
        $billable = $this->dummyBillable();

        $standard = $this->dummyPlan('standard', 10000, 7);
        $pro = $this->dummyPlan('pro', 20000, 7);

        $subscription = $billable
            ->newSubscription($standard->id)
            ->create($this->validPaymentMethod()->id);

        $this->assertTrue($subscription->onTrial());

        $billable->subscriptions()->first()
            ->switch($standard->id, $pro->id)
            ->skipTrial()
            ->update()
        ;

        $this->assertFalse($subscription->fresh()->onTrial());
    }
}