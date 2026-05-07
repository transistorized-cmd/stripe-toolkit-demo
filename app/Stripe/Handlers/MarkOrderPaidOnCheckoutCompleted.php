<?php

declare(strict_types=1);

namespace App\Stripe\Handlers;

use App\Models\Order;
use App\Support\StripeIds;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;
use TransistorizedCmd\StripeToolkit\Webhooks\Attributes\StripeEvent;
use TransistorizedCmd\StripeToolkit\Webhooks\Contracts\WebhookEventDTO;
use TransistorizedCmd\StripeToolkit\Webhooks\StripeWebhookHandler;

/**
 * Marks an order as paid when its Stripe Checkout Session completes.
 *
 * Two event types matter:
 *  - `checkout.session.completed`              — fires for every Checkout Session, including
 *                                                successful card payments
 *  - `checkout.session.async_payment_succeeded` — for delayed methods (SEPA, etc.)
 *
 * Idempotency: `Order::markPaid()` is a state setter that no-ops if the
 * order is already paid, so re-deliveries and manual replays are safe.
 */
#[StripeEvent('checkout.session.completed')]
#[StripeEvent('checkout.session.async_payment_succeeded')]
class MarkOrderPaidOnCheckoutCompleted extends StripeWebhookHandler
{
    public int $tries = 5;

    /** @var array<int,int> */
    public array $backoff = [10, 60, 300, 900, 1800];

    public function handle(WebhookEventDTO $event): void
    {
        /** @var Session $session */
        $session = $event->relatedObject();

        // Only act on sessions whose payment actually succeeded.
        // Async checkouts arrive as 'completed' but with payment_status='unpaid'
        // until the bank confirms; we wait for the async_payment_succeeded
        // event for those.
        if (($session->payment_status ?? null) !== 'paid' && $event->type() === 'checkout.session.completed') {
            Log::info('[demo] checkout.session.completed received but payment_status != paid; waiting for async_payment_succeeded', [
                'session_id' => $session->id,
                'payment_status' => $session->payment_status ?? null,
            ]);

            return;
        }

        $orderId = (int) ($session->metadata->order_id ?? $session->client_reference_id ?? 0);

        if ($orderId <= 0) {
            throw new \DomainException('Stripe session has no order_id in metadata or client_reference_id.');
        }

        /** @var Order $order */
        $order = Order::query()->findOrFail($orderId);

        $paymentIntentId = StripeIds::paymentIntentFromSession($session);
        $email = StripeIds::customerEmailFromSession($session);

        $order->markPaid(
            paymentIntentId: $paymentIntentId,
            customerEmail: $email,
        );

        Log::info('[demo] order marked as paid via webhook', [
            'order_id' => $order->id,
            'session_id' => $session->id,
            'payment_intent_id' => $paymentIntentId,
            'customer_email' => $email,
            'event_id' => $event->id(),
        ]);
    }
}
