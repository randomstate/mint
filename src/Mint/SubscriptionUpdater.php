<?php


namespace RandomState\Mint\Mint;


use RandomState\Stripe\BillingProvider;

class SubscriptionUpdater
{

    /**
     * @var Subscription
     */
    protected Subscription $subscription;

    /**
     * @var PlanSwitch[]
     */
    protected array $switches = [];

    /**
     * @var bool
     */
    protected bool $invoiceImmediately = false;

    /**
     * @var bool
     */
    protected bool $skipTrial = false;

    public function __construct(Subscription $subscription)
    {
        $this->subscription = $subscription;
    }

    /**
     * @param SubscriptionItem $item
     * @param $newPlan
     * @return $this
     */
    public function switch(SubscriptionItem $item, $newPlan)
    {
        $this->switches[] = new PlanSwitch($item, $newPlan);

        return $this;
    }

    /**
     * @param bool $immediately
     * @return $this
     */
    public function invoiceImmediately($immediately = true)
    {
        $this->invoiceImmediately = $immediately;

        return $this;
    }

    public function skipTrial($skip = true)
    {
        $this->skipTrial = $skip;

        return $this;
    }

    public function update()
    {
        /** @var BillingProvider $stripe */
        $stripe = app(BillingProvider::class);

        $subscription = $this->subscription;
        $stripeSubscription = $stripe->subscriptions()->update($subscription->stripe_id, [
            'items' => [
                array_map(function(PlanSwitch $switch) {
                    return [
                        'id' => $switch->item()->stripe_id,
                        'plan' => $switch->newPlan(),
                    ];
                }, $this->switches)
            ],
            'proration_behavior' => $this->prorationBehavior(),
            'trial_end' => ($subscription->onTrial() && !$this->skipTrial) ? $subscription->trial_ends_at->getTimestamp() : 'now',
        ]);

        if ($this->invoiceImmediately) {
            $subscription->invoice();
        }

        $subscription->syncFromStripe($stripeSubscription);

        foreach($this->switches as $switch) {
            $switch->item()->syncFromStripe($switch->item()->asStripe());
        }

        return true;
    }

    protected function prorationBehavior()
    {
        return 'create_prorations';
//        return $this->invoiceImmediately ? 'create_prorations' : 'none';
    }
}