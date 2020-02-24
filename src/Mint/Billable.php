<?php


namespace RandomState\Mint\Mint;


use Illuminate\Support\Collection;
use RandomState\Mint\Subscription;
use RandomState\Mint\SubscriptionItem;
use Stripe\Exception\CardException;
use Stripe\Invoice;

trait Billable
{
    use Billing;

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

    public function asStripe($params = [])
    {
        return $this->stripe()->customers()->retrieve(array_merge($params, [
            'id' => $this->stripeCustomerId(),
        ]));
    }

    public function defaultPaymentMethod()
    {
        $customer = $this->asStripe([
            'expand' => [
                'invoice_settings.default_payment_method',
                'default_source',
            ]
        ]);

        return $customer->invoice_settings->default_payment_method ?? $customer->default_source;
    }

    public function newSubscription(...$plans)
    {
        return (new SubscriptionBuilder($this, $plans));
    }

    public function updateDefaultPaymentMethod($paymentMethod)
    {
        $stripePaymentMethod = $this->addPaymentMethod($paymentMethod);

        $this->stripe()->customers()->update($this->stripeCustomerId(), [
            'invoice_settings' => [
                'default_payment_method' => $stripePaymentMethod->id,
            ]
        ]);

        return $this;
    }

    public function removePaymentMethod($paymentMethod)
    {
        $stripePaymentMethod = $this->stripe()->paymentMethods()->retrieve($paymentMethod);
        $stripePaymentMethod->detach();

        return true;
    }

    public function paymentMethods($type = 'card')
    {
        return new Collection(
            $this->stripe()
                ->paymentMethods()
                ->all(['customer' => $this->stripeCustomerId(), 'type' => $type])->data
        );
    }

    public function addPaymentMethod($paymentMethod)
    {
        $stripePaymentMethod = $this->stripe()->paymentMethods()->retrieve($paymentMethod);
        $stripePaymentMethod->attach([
            'customer' => $this->stripeCustomerId(),
        ]);

        return $stripePaymentMethod;
    }

    /**
     * @return Subscription | null
     */
    public function subscription()
    {
        return $this->subscriptions()->first();
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, 'billable_id');
    }

    public function invoice(array $options = [])
    {
        /** @var Invoice $invoice */
        $invoice = $this->stripe()->invoices()->create(array_merge($options, [
            'customer' => $this->stripeCustomerId(),
        ]));

        try {
            return $invoice->pay();
        } catch (CardException $e) {
            $invoice = $this->stripe()->invoices()->retrieve([
                'id' => $invoice->id,
                'expand' => ['payment_intent']
            ]);

            (new Payment($invoice->payment_intent))->validate();
        }
    }

    public function stripeCustomerId()
    {
        if (!$this->stripe_id) {
            $customer = $this->stripe()->customers()->create();
            $this->stripe_id = $customer->id;
            $this->save();
        }

        return $this->stripe_id;
    }
}