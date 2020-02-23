<?php


namespace RandomState\Mint\Http\Controllers;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use RandomState\Mint\Http\Middleware\VerifyStripeSignature;
use RandomState\Mint\Webhook;
use Stripe\Event;

class StripeWebhookController extends Controller
{
    public function __construct()
    {
        $this->middleware(VerifyStripeSignature::class);
    }

    public function process(Request $request)
    {
        $event = Event::constructFrom($request->all());

        Webhook::firstOrNew([
            'stripe_id' => $event->id,
        ])->fill([
            'api_version' => $event->api_version,
            'payload' => $event->toArray(),
            'type' => $event->type,
            'object_type' => $event->data->object->object,
            'stripe_created_at' => $event->created,
            'request' => $event->request,
        ])->save();

        event('stripe:'.$event->type, $event);
    }
}