<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Stripe Keys
    |--------------------------------------------------------------------------
    |
    | The Stripe publishable key and secret key give you access to Stripe's
    | API. The "publishable" key is typically used when interacting with
    | Stripe.js while the "secret" key accesses private API endpoints.
    |
    */
    'publishable_key' => env("STRIPE_PUBLISHABLE_KEY"),
    'secret_key' => env("STRIPE_SECRET_KEY"),

    /*
    |--------------------------------------------------------------------------
    | Mint Model
    |--------------------------------------------------------------------------
    |
    | This is the model in your application that implements the Billable trait
    | provided by Mint. It will serve as the primary model you use while
    | interacting with Cashier related methods, subscriptions, and so on.
    |
    | If you don't use Eloquent, you can still use the Billable trait included
    | but will need to wire up any ORM relationships yourself.
    |
    */
    'model' => env("MINT_MODEL", \App\User::class),

    /*
    |--------------------------------------------------------------------------
    | Stripe Webhooks
    |--------------------------------------------------------------------------
    |
    | Your Stripe webhook secret is used to prevent unauthorized requests to
    | your Stripe webhook handling controllers. The tolerance setting will
    | check the drift between the current time and the signed request's.
    |
    | You can customise the webhook endpoint path produced by the route macro
    | Route::mint(); here.
    |
    | To prevent Mint synchronizing Stripe & the Mint
    | model tables, set sync to false.
    */
    'webhooks' => [
        'path' => '/webhooks/stripe',
        'sync' => env("MINT_WEBHOOK_SYNC", true),
        'signing_secret' => env("STRIPE_WEBHOOK_SECRET"),
    ],

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | This is the default currency that will be used when generating charges
    | or plan price descriptions from your application.
    |
    */
    'currency' => 'usd',

    /*
    |--------------------------------------------------------------------------
    | Currency Locale
    |--------------------------------------------------------------------------
    |
    | This is the default locale in which your money values are formatted in
    | for display. To utilize other locales besides the default en locale
    | verify you have the "intl" PHP extension installed on the system.
    |
    */
    'currency_locale' => 'en',
];