<?php


namespace RandomState\Mint\Tests\Stripe;


use RandomState\Mint\Tests\Contracts\StripeWebhookSubscriberContractTests;
use RandomState\Mint\Tests\TestCase;

/**
 * @group integration
 */
class StripeWebhookSubscriberTest extends TestCase
{
    use StripeWebhookSubscriberContractTests;
}