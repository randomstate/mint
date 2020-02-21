<?php


namespace RandomState\Mint\Tests;


use RandomState\Mint\Plan;

class PlansTest extends TestCase
{
    /**
     * @test
     */
    public function can_pull_stripe_plan()
    {
        $this->assertEquals(0, Plan::count());

        $plan = $this->stripe->plans()->create([
            'currency' => 'usd',
            'interval' => 'month',
            'amount' => 10000,
            'nickname' => 'starter',
            'trial_period_days' => 4,
            'product' => [
                'name' => 'test product',
                'type' => 'service',
            ]
        ]);

        $this->mint->plans()->sync($plan->id);

        $plan = Plan::first();

        $this->assertNotNull($plan, 'Plan is not null.');
        $this->assertEquals('usd', $plan->currency);
        $this->assertEquals('month', $plan->interval);
        $this->assertEquals(10000, $plan->amount);
        $this->assertEquals('starter', $plan->nickname);
        $this->assertEquals(4, $plan->trial_period_days);
    }
}