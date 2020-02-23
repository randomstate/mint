<?php


namespace RandomState\Mint\Tests\Contracts;


trait BillableContractTests
{
    /**
     * @test
     */
    public function can_update_default_payment_method()
    {
       $billable = $this->dummyBillable();

       $billable->updateDefaultPaymentMethod('pm_card_visa');
       $this->assertEquals('4242', $billable->defaultPaymentMethod()->card->last4);
    }

    /**
     * @test
     */
    public function can_add_payment_method()
    {
        $billable = $this->dummyBillable();

        $pm = $billable->addPaymentMethod('pm_card_visa');

        $found = $this->stripe->paymentMethods()->retrieve($pm->id);

        $this->assertEquals($found->customer, $billable->stripeCustomerId());
    }

    /**
     * @test
     */
    public function can_remove_payment_method()
    {
        $billable = $this->dummyBillable();

        $pm = $billable->addPaymentMethod('pm_card_visa');
        $billable->removePaymentMethod($pm->id);

        $this->assertnull($pm->refresh()->customer);
    }

    /**
     * @test
     */
    public function can_list_payment_methods()
    {
        $billable = $this->dummyBillable();
        $billable->addPaymentMethod('pm_card_visa');
        $billable->addPaymentMethod('pm_card_mastercard');

        $paymentMethods = $billable->paymentMethods();

        $this->assertCount(2, $paymentMethods);
        $this->assertEquals('mastercard', $paymentMethods->first()->card->brand);
        $this->assertEquals('visa', $paymentMethods->get(1)->card->brand);
    }
}