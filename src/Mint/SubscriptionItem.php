<?php


namespace RandomState\Mint\Mint;


use Illuminate\Database\Eloquent\Model;
use RandomState\Stripe\BillingProvider;

class SubscriptionItem extends Model
{
    protected $guarded = [];

    public static function build(string $planId)
    {
        return new SubscriptionItemBuilder($planId);
    }

    public function incrementQuantity($count = 1)
    {
        return $this->updateQuantity($this->quantity + $count);
    }

    public function updateQuantity($quantity)
    {
        $stripeItem = $this->asStripe();
        $stripeItem->quantity = $quantity;
        $stripeItem->save();

        $this->quantity = $stripeItem->quantity;

        return $this;
    }

    public function decrementQuantity($count = 1)
    {
        return $this->updateQuantity(max(1, $this->quantity - $count));
    }

    public function asStripe()
    {
        return app(BillingProvider::class)->subscriptions()->items()->retrieve([
           'id' => $this->stripe_id,
        ]);
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function billable()
    {
        return $this->subscription->billable;
    }

    public function syncFromStripe(\Stripe\SubscriptionItem $stripeItem)
    {
        $this->stripe_plan = $stripeItem->plan->id;
        $this->quantity = $stripeItem->quantity;

        return $this->save();
    }
}