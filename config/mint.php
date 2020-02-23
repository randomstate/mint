<?php

return [

    'publishable_key' => env("STRIPE_PUBLISHABLE_KEY"),
    'secret_key' => env("STRIPE_SECRET_KEY"),
    'model' => env("MINT_MODEL", \App\User::class),
    'webhooks' => [
        'path' => '/webhooks/stripe',
        'sync' => env("MINT_WEBHOOK_SYNC", true),
    ],
    'api_version' => '2019-12-03',
    'currency' => 'usd',
    'currency_locale' => 'en',
];