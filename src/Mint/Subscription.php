<?php


namespace RandomState\Mint\Mint;


use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use RandomState\Stripe\BillingProvider;
use Stripe\Subscription as StripeSubscription;

class Subscription extends Model
{
    protected $guarded = [];
    protected $with = ['items'];

    protected $dates = [
        'ends_at',
        'trial_ends_at',
        'created_at',
        'updated_at',
    ];

    public static $activeStates = [
        StripeSubscription::STATUS_ACTIVE,
        StripeSubscription::STATUS_TRIALING,
        StripeSubscription::STATUS_PAST_DUE,
    ];

    public function items()
    {
        return $this->hasMany(SubscriptionItem::class);
    }

    public function active()
    {
        return in_array($this->status, static::$activeStates);
    }

    public function onTrial()
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    public function onGracePeriod()
    {
        return $this->ends_at && $this->ends_at->isFuture();
    }

    public function incomplete()
    {
        return in_array($this->status, [
           StripeSubscription::STATUS_INCOMPLETE,
           StripeSubscription::STATUS_INCOMPLETE_EXPIRED,
        ]);
    }

    /**
     * @param $plan
     * @param $newPlan
     * @return SubscriptionUpdater
     */
    public function switch($plan, $newPlan)
    {
        /** @var SubscriptionItem $item */
        $item = $this->items()->firstWhere('stripe_plan', $plan);

        return (new SubscriptionUpdater($this))
            ->switch($item, $newPlan);
    }

    public function asStripe()
    {
        return app(BillingProvider::class)->subscriptions()->retrieve([
            'id' => $this->stripe_id,
        ]);
    }

    public function billable()
    {
        return $this->belongsTo(config('mint.model'), 'billable_id');
    }

    public function invoice()
    {
        return $this->billable->invoice(['subscription' => $this->stripe_id]);
    }

    public function cancel()
    {
        $subscription = app(BillingProvider::class)->subscriptions()->update($this->stripe_id, [
            'cancel_at_period_end' => true,
        ]);

        if ($this->onTrial()) {
            $this->ends_at = $this->trial_ends_at;
        } else {
            $this->ends_at = $subscription->current_period_end;
        }

        return $this->syncFromStripe($subscription);
    }

    public function cancelNow()
    {
        $subscription = $this->asStripe();
        $subscription->cancel();

        $this->ends_at = Carbon::now();

        return $this->syncFromStripe($subscription);
    }

    public function resume()
    {
        $subscription = app(BillingProvider::class)->subscriptions()->update($this->stripe_id, [
            'cancel_at_period_end' => false,
            'items' => $this->items->map(function (SubscriptionItem $item) {
                return [
                    'id' => $item->stripe_id,
                    'plan' => $item->stripe_plan,
                ];
            })->toArray(),
            'trial_end' => $this->onTrial() ? $this->trial_ends_at->getTimestamp() : null,
        ]);

        $this->ends_at = null;

        return $this->syncFromStripe($subscription);
    }

    public function syncFromStripe(StripeSubscription $subscription)
    {
        $this->status = $subscription->status;
        $this->trial_ends_at = $subscription->trial_end;

        $this->save();

        return $this;
    }
}