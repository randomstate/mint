<?php


namespace RandomState\Mint\Tests\Contracts;


trait SubscriptionPreviewsContractTests
{
    /**
     * @test
     */
    public function can_estimate_cost_of_subscription_before_creation()
    {
        $plan = $this->dummyPlan('test', 10000);
        $billable = $this->dummyBillable();
        $coupon = $this->dummyCoupon(10);

        $preview = $billable->newSubscription($plan->id)
            ->withCoupon($coupon)
            ->preview();

        $this->assertEquals(9000, $preview->amount_remaining);
    }

    /**
     * @test
     */
    public function can_estimate_cost_of_subscription_changes()
    {
        $paymentMethod = $this->validPaymentMethod();
        $plan = $this->dummyPlan('test', 10000);
        $billable = $this->dummyBillable();

        $billable->newSubscription($plan->id)
            ->create($paymentMethod->id);

        $coupon = $this->dummyCoupon(10);
        $preview = $billable
            ->subscription()
            ->makeChanges()
            ->addCoupon($coupon->id)
            ->skipTrial()
            ->preview();

        $this->assertEquals(9000, $preview->amount_remaining);
    }
}
