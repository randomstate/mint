<?php


namespace RandomState\Mint\Tests\Fixtures;


use Illuminate\Database\Eloquent\Model;
use RandomState\Mint\Mint\Subscription;
use RandomState\Mint\Mint\SubscriptionBuilder;
use RandomState\Mint\Mint\SubscriptionItem;
use RandomState\Stripe\BillingProvider;

class User extends Model
{
    protected $guarded = [];

    public function newSubscription(...$plans)
    {
        return (new SubscriptionBuilder($this, $plans));
    }

    public function stripeCustomerId()
    {
        if (!$this->stripe_id) {
            $customer = app(BillingProvider::class)->customers()->create();
            $this->stripe_id = $customer->id;
            $this->save();
        }

        return $this->stripe_id;
    }

    public function stripeCustomer()
    {
        return app(BillingProvider::class)->customers()->retrieve($this->stripeCustomerId());
    }

    public function subscribed($planId = null)
    {
        $query = SubscriptionItem::with([
            'subscription' => function ($query) {
                $query
                    ->whereIn('status', Subscription::$activeStates)
                    ->where('billable_id', $this->id);
            }
        ]);

        if ($planId) {
            $query->where('stripe_plan', $planId);
        }

        return $query->exists();
    }

    public function invoice(array $options = [])
    {
        $invoice = app(BillingProvider::class)->invoices()->create(array_merge($options, [
            'customer' => $this->stripeCustomerId(),
        ]));

        return $invoice->pay();
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, 'billable_id');
    }
}