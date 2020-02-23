<?php


namespace RandomState\Mint\Tests\Fixtures;


use Illuminate\Database\Eloquent\Model;
use RandomState\Mint\Mint\Billable;

class User extends Model
{
    use Billable;

    protected $guarded = [];

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