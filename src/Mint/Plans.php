<?php


namespace RandomState\Mint\Mint;


use RandomState\Mint\Mint;
use RandomState\Mint\Plan;

class Plans
{
    /**
     * @var Mint
     */
    protected $mint;

    public function __construct(Mint $mint)
    {
        $this->mint = $mint;
    }

    public function sync(...$planIds)
    {
        foreach($planIds as $planId) {
            $plan = $this->mint->stripe()->plans()->retrieve([
                'id' => $planId,
                'expand' => ['product'],
            ]);

            Plan::firstOrNew(['stripe_id' => $planId])->syncFromStripe($plan);
        }
    }
}