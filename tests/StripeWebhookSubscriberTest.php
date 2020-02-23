<?php


namespace RandomState\Mint\Tests;


use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use RandomState\Mint\Plan;
use RandomState\Stripe\Stripe\Events;
use RandomState\Stripe\Stripe\WebhookListener;
use RandomState\Stripe\Stripe\WebhookSigner;
use Stripe\Event;

class StripeWebhookSubscriberTest extends TestCase
{
    /**
     * @var WebhookListener
     */
    protected $webhooks;

    /**
     * @var Fixtures\User
     */
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutExceptionHandling();

        /** @var Router $router */
        $router = $this->app['router'];

        $router->group([], function () {
            Route::mint();
        });

        $this->webhooks = new WebhookListener(
            new Events(config('mint.secret_key')),
            new WebhookSigner(config('mint.secret_key'))
        );

        $this->webhooks->listen(function (Event $event, $signature) {
            $this->postJson('/webhooks/stripe', $event->jsonSerialize(),
                ['stripe-signature' => $signature])->assertOk();
        });

        $this->user = $this->dummyBillable();
        $this->user->stripeCustomerId(); // create user
        $this->user->updateDefaultPaymentMethod('pm_card_visa');

        $this->webhooks->record();
    }

    /**
     * @test
     */
    public function subscriptions_are_created()
    {
        $plan = $this->dummyPlan('test');
        $stripeSubscription = $this->stripe->subscriptions()->create([
            'customer' => $this->user->stripeCustomerId(),
            'items' => [
                ['plan' => $plan->id]
            ],
            'trial_end' => $trialEnd = now()->addDays(3)->getTimestamp(),
        ]);

        $this->webhooks->play();
        $this->webhooks->play();

        $this->assertEquals(1, $this->user->subscriptions()->count());
        $subscription = $this->user->subscriptions()->first();
        $this->assertEquals($stripeSubscription->id, $subscription->stripe_id);
        $this->assertEquals($stripeSubscription->status, $subscription->status);
        $this->assertEquals($trialEnd, $subscription->trial_ends_at->getTimestamp());

        $this->assertCount(1, $subscription->items);
    }

    /**
     * @test
     */
    public function subscriptions_are_updated()
    {
        $plan = $this->dummyPlan('test');

        $this->user->newSubscription($plan->id)
            ->create();

        $stripeSubscription = $this->user->subscription()->asStripe();
        $stripeSubscription->trial_end = $trialEnd = now()->addWeek()->getTimestamp();
        $stripeSubscription->save();

        $this->webhooks->play();

        $this->assertTrue($this->user->subscription()->onTrial());
        $this->assertNotNull($stripeSubscription->trial_end);
        $this->assertEquals($stripeSubscription->trial_end, $this->user->subscription()->trial_ends_at->getTimestamp());
    }

    /**
     * @test
     */
    public function subscriptions_are_canceled()
    {
        $plan = $this->dummyPlan('test');

        $this->user->newSubscription($plan->id)
            ->create();

        $stripeSubscription = $this->user->subscription()->asStripe()->delete();

        $this->webhooks->play();

        $this->assertFalse($this->user->subscription()->active());
        $this->assertEquals($stripeSubscription->ended_at, $this->user->subscription()->ends_at->getTimestamp());
    }

    /**
     * @test
     */
    public function subscription_items_are_updated()
    {
        $plan = $this->dummyPlan('test');

        $numberOfPlans = range(0, 19);
        $plans = [];

        foreach($numberOfPlans as $i) {
            $plans[] = $this->dummyPlan($i)->id;
        }

        $this->user->newSubscription($plan->id)
            ->create();

        $items = [];
        $firstItemId = $this->user->subscription()->items()->first()->stripe_id;

        foreach ($plans as $i => $plan) {
            $itemId = null;

            if ($i === 0) {
                $itemId = $firstItemId;
            }

            $items[] = [
                'id' => $itemId,
                'plan' => $plan,
            ];
        }

        $this->stripe->subscriptions()->update($this->user->subscription()->stripe_id, [
            'items' => $items,
        ]);

        // test that items are removed from DB when orphaned
        $this->stripe->subscriptions()->items()->delete($firstItemId);

        $this->webhooks->play();

        $this->assertCount(19, $items = $this->user->subscription()->items);
        $this->assertEquals($plans[1], $items->first()->stripe_plan); // first item should be one cursor down now that deletion occurred

    }


    /**
     * @test
     */
    public function plans_are_created()
    {
        $plan = $this->dummyPlan('test');

        $this->webhooks->play();

        $this->assertNotNull($found = Plan::first());
        $this->assertEquals($plan->id, $found->stripe_id);
    }

    /**
     * @test
     */
    public function plans_are_updated()
    {
        $plan = $this->dummyPlan('test');
        $plan->nickname = 'new nickname';
        $plan->metadata['bundle'] = 'pro';
        $plan->save();

        $this->webhooks->play();

        $this->assertNotNull($found = Plan::first());
        $this->assertEquals($plan->id, $found->stripe_id);
        $this->assertEquals('pro', $found->metadata['bundle']);
    }

    /**
     * @test
     */
    public function plans_are_deleted()
    {
        $plan = $this->dummyPlan('test');

        $this->webhooks->record();
        $plan->delete();
        $this->webhooks->play();

        $this->assertNull($found = Plan::first());
    }
}