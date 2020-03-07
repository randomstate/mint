<?php


namespace RandomState\Mint;


use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use RandomState\Mint\Events\PaymentActionRequiredOffSession;
use RandomState\Mint\Events\PaymentErrorOffSession;
use RandomState\Mint\Exceptions\ExpiredSubscription;
use RandomState\Mint\Exceptions\PaymentActionRequired;
use RandomState\Mint\Exceptions\PaymentError;
use RandomState\Mint\Mint\Billing;
use RandomState\Mint\Mint\Payment;
use RandomState\Mint\Mint\SubscriptionUpdater;
use RandomState\Stripe\BillingProvider;
use Stripe\Subscription as StripeSubscription;

class Subscription extends Model
{
    use Billing;

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

    public function hasIncompletePayment()
    {
        return $this->pastDue() || $this->incomplete();
    }

    public function canceled()
    {
        return $this->status === StripeSubscription::STATUS_CANCELED;
    }

    public function pastDue()
    {
        return $this->status === StripeSubscription::STATUS_PAST_DUE;
    }

    /**
     * @param $plan
     * @param $newPlan
     * @return SubscriptionUpdater
     */
    public function switch($plan, $newPlan)
    {
        if($plan instanceof \Stripe\Plan){
            $plan = $plan->id;
        }

        if($newPlan instanceof \Stripe\Plan) {
            $newPlan = $newPlan->id;
        }

        /** @var SubscriptionItem $item */
        $item = $this->items()->firstWhere('stripe_plan', $plan);

        return (new SubscriptionUpdater($this))
            ->switch($item, $newPlan);
    }

    public function asStripe($params = [])
    {
        return $this->stripe()->subscriptions()->retrieve(array_merge($params,[
            'id' => $this->stripe_id,
        ]));
    }

    public function billable()
    {
        return $this->belongsTo(config('mint.model'), 'billable_id', 'stripe_id');
    }

    public function invoice()
    {
        return $this->billable->invoice(['subscription' => $this->stripe_id]);
    }

    public function cancel()
    {
        $subscription = $this->stripe()->subscriptions()->update($this->stripe_id, [
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
        $subscription = $this->stripe()->subscriptions()->update($this->stripe_id, [
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

        $endsAt = $subscription->cancel_at;

        if($endsAt) {
            $this->ends_at = $endsAt;
        }

        if($this->canceled()) {
            $this->ends_at = $subscription->ended_at;
        }

        // If active subscription but invoice went overdue from regular billing, check for SCA failure
        if($this->pastDue()) {
            try {
                (new Payment(
                    $this->asStripe([
                        'expand' => ['latest_invoice.payment_intent']
                    ])->latest_invoice->payment_intent
                ))->validate();
            } catch(PaymentActionRequired $e) {
                event(new PaymentActionRequiredOffSession($e->payment()->intent()));
            } catch(PaymentError $e) {
                event(new PaymentErrorOffSession($e->payment()->intent()));
            }
        }

        $this->save();

        return $this;
    }

    public function syncItemsFromStripe(StripeSubscription $subscription)
    {
        $itemIds = [];
        foreach($subscription->items->autoPagingIterator() as $item) {
            $itemIds[] = $item->id;

            $this->items()->updateOrCreate([
                'stripe_id' => $item->id,
            ], SubscriptionItem::syncFromStripePayload($item));
        }

        // remove orphaned items
        $orphaned = $this->items()->whereNotIn('stripe_id', $itemIds)->get();

        foreach($orphaned as $orphan) {
            $orphan->delete();
        }

        return $this;
    }

    public function makeChanges()
    {
        return new SubscriptionUpdater($this);
    }

    public function scopeActive(Builder $query)
    {
        return $query->whereIn('status', static::$activeStates);
    }

    /*
     * Validate the subscription by checking status & payment intent status
     */
    public function validate()
    {
        $stripeSubscription = $this->asStripe(['expand' => ['latest_invoice.payment_intent']]);
        if($stripeSubscription->status === StripeSubscription::STATUS_INCOMPLETE_EXPIRED) {
            throw new ExpiredSubscription($this);
        }

        (new Payment($stripeSubscription->latest_invoice->payment_intent))->validate();

        return true;
    }
}