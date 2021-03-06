<?php


namespace RandomState\Mint\Mint;


use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RandomState\Mint\Subscription;
use RandomState\Mint\SubscriptionItem;
use RandomState\Mint\Tests\Fixtures\User;
use Stripe\Invoice;

class SubscriptionBuilder
{
    use Billing;

    /**
     * @var Billable
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

    /**
     * @var string | null
     */
    protected $coupon = null;

    public function __construct($billable, array $plans)
    {
        $this->billable = $billable;
        $this->items = $this->toSubscriptionItemBuilders($plans);
    }

    public function trialDays(int $days)
    {
        $this->trialDays = $days;

        return $this;
    }

    /**
     * @param string | null $paymentMethod
     * @return Subscription
     * @throws \RandomState\Mint\Exceptions\PaymentActionRequired
     * @throws \RandomState\Mint\Exceptions\PaymentError
     */
    public function create($paymentMethod = null)
    {
        $customer = $this->billable->asStripe();

        if ($paymentMethod) {
            $this->billable->updateDefaultPaymentMethod($paymentMethod);
        }

        $payload = [
            'expand' => ['latest_invoice.payment_intent'],
            'customer' => $customer->id,
            'items' => $this->items->map(function (SubscriptionItemBuilder $builder) {
                return $builder->toStripePayload();
            })->toArray(),
            'coupon' => $this->coupon,
        ];

        if (!$this->trialDays) {
            $payload['trial_from_plan'] = true;
        } else {
            $payload['trial_end'] = $this->getTrialEnd();
        }

        $stripeSubscription = $this->stripe()
            ->subscriptions()
            ->create($payload);

        $subscription = Subscription::create([
            'stripe_id' => $stripeSubscription->id,
            'billable_id' => $this->billable->stripeCustomerId(),
            'trial_ends_at' => $stripeSubscription->trial_end,
            'status' => $stripeSubscription->status,
        ]);

        $items = [];
        foreach ($stripeSubscription->items as $item) {
            $items[] = [
                'stripe_id' => $item->id,
                'stripe_plan' => $item->plan->id,
                'quantity' => $item->quantity,
            ];
        }

        $subscription->items()->createMany($items);

        if ($subscription->incomplete()) {
            (new Payment(
                $stripeSubscription->latest_invoice->payment_intent
            ))->validate();
        }

        return $subscription;
    }

    public function withCoupon($coupon)
    {
        $this->coupon = $coupon;
        
        return $this;
    }

    public function preview()
    {
        return Invoice::upcoming([
            'customer' => $this->billable->stripeCustomerId(),
            'subscription_items' => $this->items->map(function (SubscriptionItemBuilder $builder) {
                return $builder->toStripePayload();
            })->toArray(),
            'coupon' => $this->coupon,
        ]);
    }

    protected function getTrialEnd()
    {
        if ($this->trialDays) {
            return now()->addDays($this->trialDays)->getTimestamp();
        }

        return null;
    }

    protected function toSubscriptionItemBuilders($plans)
    {
        return collect($plans)->map(function ($plan) {
            if (is_string($plan)) {
                return SubscriptionItem::build($plan);
            }

            return $plan;
        });
    }
}