<?php


namespace RandomState\Mint\Mint;


use Illuminate\Support\Collection;
use RandomState\Mint\Subscription;
use RandomState\Mint\SubscriptionItem;
use Stripe\Invoice;

class SubscriptionUpdater
{
    use Billing;

    /**
     * @var Subscription
     */
    protected $subscription;

    /**
     * @var PlanSwitch[] | Collection
     */
    protected $switches;

    /**
     * @var bool
     */
    protected $invoiceImmediately = false;

    /**
     * @var bool
     */
    protected $skipTrial = false;

    /**
     * @var string | null
     */
    protected $coupon = null;

    public function __construct(Subscription $subscription)
    {
        $this->subscription = $subscription;
        $this->switches = new Collection();
    }

    /**
     * @param SubscriptionItem $item
     * @param $newPlan
     * @return $this
     */
    public function switch(SubscriptionItem $item, $newPlan)
    {
        $this->switches->push(new PlanSwitch($item, $newPlan));

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
        $subscription = $this->subscription;
        $stripeSubscription = $this->stripe()->subscriptions()->update($subscription->stripe_id, [
            'items' => $this->switches->map(function (PlanSwitch $switch) {
                return [
                  'id' => $switch->item()->stripe_id,
                  'plan' => $switch->newPlan(),
                ];
            })->toArray(),
            'proration_behavior' => $this->prorationBehavior(),
            'trial_end' => $this->trialEnd(),
            'coupon' => $this->coupon,
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

    public function addCoupon($coupon)
    {
        $this->coupon = $coupon;

        return $this;
    }

    protected function trialEnd()
    {
        $subscription = $this->subscription;
        return ($subscription->onTrial() && !$this->skipTrial) ? $subscription->trial_ends_at->getTimestamp() : 'now';
    }

    public function preview()
    {
        return Invoice::upcoming([
            'subscription' => $this->subscription->stripe_id,
            'subscription_items' => $this->switches->map(function (PlanSwitch $switch) {
                return [
                    'id' => $switch->item()->stripe_id,
                    'plan' => $switch->newPlan(),
                ];
            })->toArray(),
            'coupon' => $this->coupon,
            'subscription_trial_end' => $this->trialEnd(),
        ]);
    }

    protected function prorationBehavior()
    {
        return 'create_prorations';
    }
}