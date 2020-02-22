<?php


namespace RandomState\Mint\Tests\Fixtures;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RandomState\Mint\Mint\Payment;
use RandomState\Mint\Mint\Subscription;
use RandomState\Mint\Mint\SubscriptionBuilder;
use RandomState\Mint\Mint\SubscriptionItem;
use RandomState\Stripe\BillingProvider;
use Stripe\Exception\CardException;
use Stripe\Invoice;

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

    public function asStripe($params = [])
    {
        return app(BillingProvider::class)->customers()->retrieve(array_merge($params, [
            'id' => $this->stripeCustomerId(),
        ]));
    }

//    public function updateDefaultPaymentMethod($paymentMethod)
//    {
//        $this->asStripe()->
//    }

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
        /** @var Invoice $invoice */
        $invoice = $this->stripe()->invoices()->create(array_merge($options, [
            'customer' => $this->stripeCustomerId(),
        ]));

        try {
            return $invoice->pay();
        } catch(CardException $e) {
            $invoice = $this->stripe()->invoices()->retrieve([
                'id' => $invoice->id,
                'expand' => ['payment_intent']
            ]);

            (new Payment($invoice->payment_intent))->validate();
        }
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, 'billable_id');
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

    /**
     * @return BillingProvider
     */
    protected function stripe()
    {
        return app(BillingProvider::class);
    }

    public function addPaymentMethod($paymentMethod)
    {
        $stripePaymentMethod = $this->stripe()->paymentMethods()->retrieve($paymentMethod);
        $stripePaymentMethod->attach([
            'customer' => $this->stripeCustomerId(),
        ]);

        return $stripePaymentMethod;
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
}