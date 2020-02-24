<?php


namespace RandomState\Mint\Tests\Stripe;


use RandomState\Mint\Tests\Contracts\BillableContractTests;
use RandomState\Mint\Tests\TestCase;

/**
 * @group integration
 */
class BillableTest extends TestCase
{
    use BillableContractTests;
}