<?php


namespace RandomState\Mint\Http\Middleware;


use Closure;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class VerifyStripeSignature
{
    protected $secretKey;

    /**
     * @var int
     */
    protected $tolerance;

    public function __construct($secretKey, $tolerance = null)
    {
        $this->secretKey = $secretKey;
        $this->tolerance = $tolerance;
    }

    public function handle($request, Closure $next)
    {
        try {
            Webhook::constructEvent(
                $request->getContent(),
                $request->header('stripe-signature'),
                $this->secretKey,
                $this->tolerance
            );
        } catch(SignatureVerificationException $e) {
            throw new AccessDeniedHttpException($e->getMessage(), $e);
        }

        return $next($request);
    }
}