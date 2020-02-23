<?php


namespace RandomState\Mint;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use RandomState\Mint\Mint\Billing;
use RandomState\Mint\Mint\SubscriptionItemBuilder;
use RandomState\Stripe\BillingProvider;

class SubscriptionItem extends Model
{
    use Billing, SoftDeletes;

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
        return $this->stripe()->subscriptions()->items()->retrieve([
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
        $this->fill($this->syncFromStripePayload($stripeItem));

        return $this->save();
    }

    public static function syncFromStripePayload(\Stripe\SubscriptionItem $stripeItem)
    {
        return [
          'stripe_plan' => $stripeItem->plan->id,
          'quantity' => $stripeItem->quantity,
        ];
    }
}