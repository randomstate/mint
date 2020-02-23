<?php


namespace RandomState\Mint\Tests;


use Faker\Generator;
use Illuminate\Contracts\Console\Kernel;
use RandomState\Mint\Mint;
use RandomState\Mint\MintServiceProvider;
use RandomState\Mint\Tests\Fixtures\User;
use RandomState\Stripe\BillingProvider;
use Stripe\Plan;

class TestCase extends \Tests\TestCase
{
    /**
     * @var BillingProvider
     */
    protected $stripe;

    /**
     * @var Mint
     */
    protected $mint;

    /**
     * @var Plan[]
     */
    protected $plans = [];

    /**
     * @var Generator
     */
    protected $faker;

    public function createApplication()
    {
        $app = require __DIR__.'/../vendor/laravel/laravel/bootstrap/app.php';
        $app->useEnvironmentPath(__DIR__ . '/..');

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->register(MintServiceProvider::class);
        $this->stripe = $this->app->make(BillingProvider::class);
        $this->mint = $this->app->make(Mint::class);
        $this->faker = $this->app->make(Generator::class);

        $this->artisan('migrate')->run();
    }

    protected function dummyPlan($nickname, $amount = null, $trialDays = null)
    {
        if($this->plans[$nickname] ?? false) {
            return $this->plans[$nickname];
        }

        $payload = [
            'nickname' => $nickname,
            'amount' => $amount ? $amount : $this->faker->randomNumber(6),
            'interval' => 'month',
            'currency' => 'usd',
            'product' => [
                'name' => 'app',
            ]
        ];

        if($trialDays) {
            $payload['trial_period_days'] = $trialDays;
        }

        return $this->plans[$nickname] = $this->stripe->plans()->create($payload);
    }

    /**
     * @return User
     */
    protected function dummyBillable()
    {
        return User::create([
            'name' => $this->faker->name,
            'email' => $this->faker->email,
            'password' => 'test',
        ]);
    }

    protected function dummyCoupon($percent)
    {
        return $this->stripe->coupons()->create([
            'duration' => 'forever',
            'percent_off' => $percent,
        ]);
    }

    protected function validPaymentMethod()
    {
        return $this->stripe->paymentMethods()->create([
            'type' => 'card',
            'card' => ['token' => 'tok_visa_debit']
        ]);
    }
}