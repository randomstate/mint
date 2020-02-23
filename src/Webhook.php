<?php


namespace RandomState\Mint;


use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Stripe\Event as StripeEvent;

class Webhook extends Model
{
    protected $table = 'stripe_webhooks';
    protected $guarded = [];
    protected $casts = [
        'payload' => 'array',
        'request' => 'array',
    ];

    protected $dates = [
        'stripe_created_at',
        'created_at',
        'updated_at',
    ];

    public static function isStale(StripeEvent $event)
    {
        return Webhook::where('object_type', $event->data->object->object)
            ->where('stripe_created_at', '>', Carbon::createFromTimestampUTC($event->created))
            ->exists();
    }
}