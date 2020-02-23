<?php


namespace RandomState\Mint\Mint;


use RandomState\Mint\SubscriptionItem;

class PlanSwitch
{
    /**
     * @var SubscriptionItem
     */
    protected SubscriptionItem $item;

    /**
     * @var string
     */
    protected $newPlan;

    public function __construct(SubscriptionItem $item, $newPlan)
    {
        $this->item = $item;
        $this->newPlan = $newPlan;
    }

    public function item()
    {
        return $this->item;
    }

    public function newPlan()
    {
        return $this->newPlan;
    }
}