<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Small helpers for extracting ids out of Stripe response objects when
 * fields can be either a string id or an expanded resource. Keeps the
 * controller and webhook handler from re-deriving the same logic.
 */
final class StripeIds
{
    /**
     * Pull the PaymentIntent id out of a Checkout Session, regardless of
     * whether `payment_intent` was returned as a string id or expanded
     * into a full object.
     */
    public static function paymentIntentFromSession(object $session): string
    {
        $value = $session->payment_intent ?? null;

        if (is_string($value)) {
            return $value;
        }

        if (is_object($value) && isset($value->id) && is_string($value->id)) {
            return $value->id;
        }

        return '';
    }

    /**
     * Pull the customer email from a Checkout Session, preferring
     * `customer_details.email` (filled by Stripe Checkout) and falling
     * back to the legacy `customer_email` on the session.
     */
    public static function customerEmailFromSession(object $session): ?string
    {
        $email = $session->customer_details?->email
            ?? $session->customer_email
            ?? null;

        return is_string($email) && $email !== '' ? $email : null;
    }
}
