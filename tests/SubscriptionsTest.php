<?php


namespace RandomState\Mint\Tests;


use Illuminate\Support\Str;
use RandomState\Mint\Subscription;
use RandomState\Mint\SubscriptionItem;
use RandomState\Mint\Tests\Fixtures\User;
use Stripe\Stripe;
use Stripe\Subscription as StripeSubscription;
use Stripe\TaxRate;

class SubscriptionsTest extends TestCase
{
    /**
     * @test
     */
    public function can_subscribe_to_a_plan()
    {
        $paymentMethod = $this->validPaymentMethod();
        $plan = $this->dummyPlan('test');

        $billable = $this->dummyBillable();

        $subscription = $billable
            ->newSubscription($plan->id)
            ->create($paymentMethod->id);

        $this->assertEquals($plan->id, $subscription->items()->first()->stripe_plan);
        $this->assertEquals($billable->stripeCustomerId(), $subscription->stripe_customer);
        $this->assertNull($subscription->trial_ends_at);

        // Check subscription status
        $this->assertTrue($subscription->active());

        $this->assertTrue($billable->subscribed());
        $this->assertTrue($billable->subscribed($plan->id));
    }

    /**
     * @test
     */
    public function can_subscribe_with_trial_days()
    {
        $paymentMethod = $this->validPaymentMethod();
        $plan = $this->dummyPlan('test');

        $billable = $this->dummyBillable();

        $subscription = $billable->newSubscription($plan->id)
            ->trialDays(10)
            ->create($paymentMethod->id);

        $this->assertEquals(now()->addDays(10)->startOfDay(), $subscription->trial_ends_at->startOfDay());

        // Check subscription status
        $this->assertTrue($subscription->isOnTrial());
        $this->assertTrue($subscription->subscribed());

        $this->assertTrue($billable->subscribed($plan->id));
        $this->assertTrue($billable->subscribed());
    }

    /**
     * @test
     */
    public function can_adjust_quantity()
    {
        $paymentMethod = $this->validPaymentMethod();
        $plan = $this->dummyPlan('test');

        $billable = $this->dummyBillable();

        $subscription = $billable->newSubscription($plan->id)
            ->create($paymentMethod->id);

        /** @var SubscriptionItem $item */
        $item = $subscription->items()->first();
        $this->assertEquals(1, $item->quantity);

        $item->incrementQuantity();
        $this->assertEquals(2, $item->quantity);

        $item->decrementQuantity();
        $this->assertEquals(1, $item->quantity);

        $item->updateQuantity(10);
        $this->assertEquals(10, $item->quantity);
    }

    /**
     * @test
     */
    public function can_subscribe_with_tax_rates()
    {
        $paymentMethod = $this->validPaymentMethod();
        $plan = $this->dummyPlan('test');

        Stripe::setApiKey(config('mint.secret_key'));
        $germanVat = TaxRate::create([
            'display_name' => 'VAT',
            'description' => 'VAT - Germany',
            'jurisdiction' => 'DE',
            'percentage' => 19,
            'inclusive' => false,
        ]);

        $billable = $this->dummyBillable();

        $subscription = $billable
            ->newSubscription(SubscriptionItem::build($plan->id)->withTaxRates($germanVat->id))
            ->create($paymentMethod->id);

        $item = $subscription->items()->first();
        $stripeItem = $item->asStripe();

        $this->assertEquals($germanVat->id, $stripeItem->tax_rates[0]->id);
    }

    /**
     * @test
     */
    public function can_check_if_subscription_on_trial()
    {
        $billable = $this->dummyBillable();
        $subscription = Subscription::create([
            'stripe_id' => Str::random(),
            'billable_id' => $billable->id,
            'status' => 'trialing',
            'trial_ends_at' => now()->addDays(7),
        ]);

        $this->assertTrue($subscription->onTrial());
    }

    /**
     * @test
     */
    public function can_check_if_subscription_in_grace_period()
    {
        $billable = $this->dummyBillable();
        $subscription = Subscription::create([
            'stripe_id' => Str::random(),
            'billable_id' => $billable->id,
            'status' => 'active',
            'ends_at' => now()->addDays(7),
        ]);

        $this->assertTrue($subscription->onGracePeriod());
    }

    /**
     * @test
     */
    public function can_cancel_a_subscription_at_end_of_period()
    {
        $paymentMethod = $this->validPaymentMethod();
        $plan = $this->dummyPlan('test');

        $billable = $this->dummyBillable();

        $subscription = $billable->newSubscription($plan->id)
            ->trialDays(10)
            ->create($paymentMethod->id);

        $this->assertTrue($subscription->active());

        $subscription->cancel();

        $this->assertTrue($subscription->onGracePeriod());
    }
    
    /**
     * @test
     */
    public function can_cancel_a_subscription_immediately() 
    {
        $paymentMethod = $this->validPaymentMethod();
        $plan = $this->dummyPlan('test');

        $billable = $this->dummyBillable();

        $subscription = $billable->newSubscription($plan->id)
            ->trialDays(10)
            ->create($paymentMethod->id);

        $this->assertTrue($subscription->active());

        $subscription->cancelNow();
        $this->assertFalse($subscription->active());
        $this->assertEquals(StripeSubscription::STATUS_CANCELED, $subscription->status);
    }

    /**
     * @test
     */
    public function can_resume_a_subscription()
    {
        $paymentMethod = $this->validPaymentMethod();
        $plan = $this->dummyPlan('test');

        $billable = $this->dummyBillable();

        $subscription = $billable->newSubscription($plan->id)
            ->trialDays(10)
            ->create($paymentMethod->id);

        $this->assertTrue($subscription->active());

        $subscription->cancel();
        $subscription->resume();

        $this->assertTrue($subscription->active());
        $this->assertNull($subscription->ends_at);
    }
}