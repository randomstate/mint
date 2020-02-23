<?php


namespace RandomState\Mint\Events;


use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Events\Dispatcher;
use RandomState\Mint\Mint;
use RandomState\Mint\Plan;
use RandomState\Mint\Subscription;
use RandomState\Mint\Webhook;
use Stripe\Event;
use Stripe\Exception\InvalidRequestException;

class StripeWebhookSubscriber implements ShouldQueue
{
    /**
     * @var Mint
     */
    protected $mint;

    public function __construct(Mint $mint)
    {
        $this->mint = $mint;
    }

    public function subscribe(Dispatcher $events)
    {
        $events->listen([
            'stripe:customer.subscription.created',
            'stripe:customer.subscription.updated',
        ], $this->method('onSubscriptionCreatedOrUpdated'));

        $events->listen('stripe:customer.subscription.deleted', $this->method('onSubscriptionDeleted'));

        $events->listen('stripe:plan.created', $this->method('onPlanCreated'));
        $events->listen('stripe:plan.updated', $this->method('onPlanUpdated'));
        $events->listen('stripe:plan.deleted', $this->method('onPlanDeleted'));
    }

    protected function method($method)
    {
        return StripeWebhookSubscriber::class . '@' . $method;
    }

    protected function getBillable($customerId)
    {
        return $this->mint->billable($customerId);
    }

    protected function ensureFresh(Event $event)
    {
        if($stale = Webhook::isStale($event)) {
            try {
                $event->data->object->refresh();
            } catch(InvalidRequestException $e) {} // must not be available on stripe api anymore
        }

        // Inform consumer if object was refreshed
        return !$stale;
    }

    public function onSubscriptionCreatedOrUpdated(Event $event)
    {
        $this->ensureFresh($event);

        $stripeSubscription = $event->data->object;

        $subscription = Subscription::firstOrNew([
            'stripe_id' => $stripeSubscription->id,
            'billable_id' => $this->getBillable($stripeSubscription->customer)->id,
        ])
            ->syncFromStripe($stripeSubscription)
            ->syncItemsFromStripe($stripeSubscription);

        $subscription->save();
    }

    public function onSubscriptionDeleted(Event $event)
    {
        $this->ensureFresh($event);

        $stripeSubscription = $event->data->object;

        $subscription = Subscription::firstOrNew([
            'stripe_id' => $stripeSubscription->id,
            'billable_id' => $this->getBillable($stripeSubscription->customer)->id,
        ])
            ->syncFromStripe($stripeSubscription);

        $subscription->save();
    }

    public function onPlanCreated(Event $event)
    {
        $this->ensureFresh($event);

        $stripePlan = $event->data->object;

        Plan::firstOrNew([
           'stripe_id' => $stripePlan->id
        ])->syncFromStripe($stripePlan);
    }

    public function onPlanUpdated(Event $event)
    {
        $this->ensureFresh($event);

        $stripePlan = $event->data->object;

        Plan::firstOrNew([
            'stripe_id' => $stripePlan->id
        ])->syncFromStripe($stripePlan);
    }

    public function onPlanDeleted(Event $event)
    {
        $this->ensureFresh($event);

        $stripePlan = $event->data->object;

        $plan = Plan::where('stripe_id', $stripePlan->id)->first();

        if($plan) {
            $plan->delete();
        }
    }
}