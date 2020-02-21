<?php


namespace RandomState\Mint\Mint;


class SubscriptionItemBuilder
{
    protected $planId;

    /**
     * @var array
     */
    protected $taxRates = [];

    public function __construct($planId)
    {
        $this->planId = $planId;
    }

    public function withTaxRates(string ...$taxRates)
    {
        $this->taxRates = $taxRates;

        return $this;
    }

    public function toStripePayload()
    {
        return [
            'plan' => $this->planId,
            'tax_rates' => $this->taxRates,
        ];
    }
}