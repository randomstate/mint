## Introduction

## Installation

Require the library using composer:

```
composer require randomstate/mint
```

## Database Migrations

Mint provides migrations for the models it uses out of the box. These are automatically registered
with laravel. You will need to run `php artisan migrate` to initialize these tables.

The library assumes your application uses a `users` table that should contain the Stripe customer ID.
If you need to customize this, please publish the migrations first.

```
php artisan vendor:publish
```

## Configuration

### Billable Model

Add the Mint Billable trait to your User model. This will give it access to the fluent interface Mint
provides on each customer. If you are charging organisations or teams, publish &  customize the customer
migration to point to the appropriate table, and add the Billable trait to your org/team model. 

```php
use RandomState\Mint\Mint\Billable;

class User extends Authenticatable
{
    use Billable;
}
```

### API Keys

Add your Stripe API Keys to your .env file:
```dotenv
STRIPE_SECRET_KEY=
STRIPE_WEBHOOK_SECRET=
```