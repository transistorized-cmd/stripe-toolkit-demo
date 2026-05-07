<?php

declare(strict_types=1);

namespace App\Stripe\Handlers;

use Illuminate\Support\Facades\Log;
use Stripe\PaymentIntent;
use TransistorizedCmd\StripeToolkit\Webhooks\Attributes\StripeEvent;
use TransistorizedCmd\StripeToolkit\Webhooks\Contracts\WebhookEventDTO;
use TransistorizedCmd\StripeToolkit\Webhooks\StripeWebhookHandler;

#[StripeEvent('payment_intent.succeeded')]
class LogPaymentSucceeded extends StripeWebhookHandler
{
    public int $tries = 1;

    public function handle(WebhookEventDTO $event): void
    {
        /** @var PaymentIntent|null $intent */
        $intent = $event->relatedObject();

        Log::info('[smoke] payment_intent.succeeded handler ran', [
            'event_id' => $event->id(),
            'amount' => $intent?->amount,
            'currency' => $intent?->currency,
            'customer' => $intent?->customer,
            'account_id' => $event->accountId(),
        ]);
    }
}
