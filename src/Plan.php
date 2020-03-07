<?php


namespace RandomState\Mint;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Formatter\IntlMoneyFormatter;
use Money\Money;
use NumberFormatter;
use RandomState\Mint\Mint\Billing;
use RandomState\Mint\Mint\MoneyFormatter;
use Stripe\Plan as StripePlan;

class Plan extends Model
{
    use SoftDeletes, Billing;

    protected $guarded = [];
    protected $casts = ['metadata' => 'json', 'product_metadata' => 'json'];

    public function syncFromStripe(StripePlan $plan)
    {
        if(!is_object($plan->product)) {
            $plan = $this->asStripe(['expand' => ['product']]);
        }

        $this->nickname = $plan->nickname;
        $this->amount = $plan->amount;
        $this->currency = $plan->currency;
        $this->interval = $plan->interval;
        $this->interval_count = $plan->interval_count;
        $this->trial_period_days = $plan->trial_period_days;
        $this->billing_scheme = $plan->billing_scheme;
        $this->tiers = $plan->tiers;
        $this->tiers_mode = $plan->tiers_mode;
        $this->metadata = $plan->metadata->toArray();

        $this->product_name = $plan->product->name;
        $this->product_description = $plan->product->description;
        $this->product_unit_label = $plan->product->unit_label;
        $this->product_metadata = $plan->product->metadata;

        $this->save();
    }

    public function getPriceDescriptionAttribute($value)
    {
        return $value ?? $this->generatePriceDescription();
    }

    protected function generatePriceDescription($trialDays = null)
    {
        $parts = [];

        if($trialDays ?? $this->trial_period_days) {
            $parts[] = vsprintf("Free for %s days then ", $trialDays ?? $this->trial_period_days);
        }

        $parts[] = vsprintf(
            "%s every %s",
            [
                $this->formattedAmount(),
                $this->formattedInterval(),
            ]
        );

        return implode('', $parts);
    }

    public function formattedAmount()
    {
        $money = new Money($this->amount, new Currency(strtoupper($this->currency ?? config('mint.currency'))));

        $numberFormatter = new NumberFormatter(config('mint.currency_locale'), NumberFormatter::CURRENCY);
        $moneyFormatter = new IntlMoneyFormatter($numberFormatter, new ISOCurrencies());

        return $moneyFormatter->format($money);
    }

    public function formattedInterval()
    {
        if($this->interval_count == 1) {
            return $this->interval;
        }

        return vsprintf("%s %s", [$this->interval_count, Str::plural($this->interval)]);
    }

    public function asStripe($params = [])
    {
        return $this->stripe()->plans()->retrieve(array_merge($params, [
            'id' => $this->stripe_id,
        ]));
    }

}