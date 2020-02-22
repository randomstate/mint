<?php


namespace RandomState\Mint\Mint;


use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RandomState\Mint\Tests\Fixtures\Billable;
use RandomState\Mint\Tests\Fixtures\Customer;
use RandomState\Mint\Tests\Fixtures\User;
use RandomState\Stripe\BillingProvider;

class SubscriptionBuilder
{
    /**
     * @var Customer
     */
    protected $billable;

    /**
     * @var int
     */
    protected $trialDays;

    /**
     * @var Collection
     */
    protected $items;

    public function __construct(User $billable, array $plans)
    {
        $this->billable = $billable;
        $this->items = $this->toSubscriptionItemBuilders($plans);
    }

    public function trialDays(int $days)
    {
        $this->trialDays = $days;

        return $this;
    }

    public function create($paymentMethod = null)
    {
        /** @var BillingProvider $stripe */
        $stripe = app(BillingProvider::class);
        $customer = $this->billable->asStripe();

        if($paymentMethod) {
            $this->billable->updateDefaultPaymentMethod($paymentMethod);
        }

        $payload = [
            'expand' => ['latest_invoice.payment_intent'],
            'customer' => $customer->id,
            'items' => $this->items->map(function(SubscriptionItemBuilder $builder) {
                return $builder->toStripePayload();
            })->toArray(),
        ];

        if(!$this->trialDays) {
            $payload['trial_from_plan'] = true;
        } else {
            $payload['trial_end'] = $this->getTrialEnd();
        }

        $stripeSubscription = $stripe
            ->subscriptions()
            ->create($payload);


        DB::beginTransaction();

        $subscription = Subscription::create([
            'stripe_id' => $stripeSubscription->id,
            'billable_id' => $this->billable->id,
            'trial_ends_at' => $stripeSubscription->trial_end,
            'status' => $stripeSubscription->status,
        ]);

        if($subscription->incomplete()) {
            (new Payment(
                $stripeSubscription->latest_invoice->payment_intent
            ))->validate();
        }

        $items = [];
        foreach($stripeSubscription->items as $item) {
            $items[] = [
                'stripe_id' => $item->id,
                'stripe_plan' => $item->plan->id,
                'quantity' => $item->quantity,
            ];
        }

        $subscription->items()->createMany($items);

        DB::commit();

        return $subscription;
    }

    protected function getTrialEnd()
    {
        if($this->trialDays) {
            return now()->addDays($this->trialDays)->getTimestamp();
        }

        return null;
    }

    protected function toSubscriptionItemBuilders($plans)
    {
        return collect($plans)->map(function($plan) {
            if(is_string($plan)) {
                return SubscriptionItem::build($plan);
            }

            return $plan;
        });
    }
}