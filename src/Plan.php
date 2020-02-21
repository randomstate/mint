<?php


namespace RandomState\Mint;


use Illuminate\Database\Eloquent\Model;
use Stripe\Plan as StripePlan;

class Plan extends Model
{
    protected $guarded = [];

    public function sync(StripePlan $plan)
    {
        $this->nickname = $plan->nickname;
        $this->amount = $plan->amount;
        $this->currency = $plan->currency;
        $this->interval = $plan->interval;
        $this->interval_count = $plan->interval_count;
        $this->trial_period_days = $plan->trial_period_days;
        $this->billing_scheme = $plan->billing_scheme;
        $this->tiers = $plan->tiers;

        $this->save();
    }
}