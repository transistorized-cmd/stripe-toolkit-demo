<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ResetDemoState;
use App\Models\Order;
use App\Services\DemoServiceCheck;
use App\Support\StripeIds;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;
use Stripe\Checkout\Session as CheckoutSession;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use TransistorizedCmd\StripeToolkit\Webhooks\Support\StripeReconciler;

class CheckoutController extends Controller
{
    public function landing(): View
    {
        return view('checkout.landing', [
            'productName' => (string) config('services.demo.product_name'),
            'priceCents' => (int) config('services.demo.price_cents'),
            'currency' => (string) config('services.demo.currency'),
        ]);
    }

    public function start(
        Request $request,
        DemoServiceCheck $serviceCheck,
        StripeClient $stripe,
    ): RedirectResponse {
        $missing = $serviceCheck->missingServices();
        if ($missing !== []) {
            $names = collect($missing)->pluck('name')->implode(', ');

            return back()->with(
                'checkout_blocked',
                "Can't start checkout — {$names} not running. The order would be charged on Stripe but never reach 'paid' on this side. Start the missing services (or run `./demo.sh`) and retry."
            );
        }

        $productName = (string) config('services.demo.product_name');
        $priceCents = (int) config('services.demo.price_cents');
        $currency = (string) config('services.demo.currency');

        $order = Order::query()->create([
            'product_name' => $productName,
            'amount' => $priceCents,
            'currency' => $currency,
            'status' => Order::STATUS_PENDING,
        ]);

        try {
            /** @var CheckoutSession $session */
            $session = $stripe->checkout->sessions->create([
                'mode' => 'payment',
                'line_items' => [
                    [
                        'price_data' => [
                            'currency' => $currency,
                            'product_data' => ['name' => $productName],
                            'unit_amount' => $priceCents,
                        ],
                        'quantity' => 1,
                    ],
                ],
                'success_url' => route('checkout.success', ['order' => $order->id]).'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('checkout.cancelled', ['order' => $order->id]),
                'client_reference_id' => (string) $order->id,
                'metadata' => [
                    'order_id' => (string) $order->id,
                ],
            ]);
        } catch (ApiErrorException $e) {
            // Stripe rejected the session creation (bad key, malformed
            // request, network blip…). Mark the order failed so it
            // doesn't sit pending forever pretending money is in flight.
            $order->update(['status' => Order::STATUS_FAILED]);

            Log::warning('Stripe Checkout session creation failed', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
                'stripe_code' => $e->getStripeCode(),
            ]);

            return back()->with(
                'checkout_blocked',
                "Stripe rejected the checkout request: {$e->getMessage()}"
            );
        }

        $order->update(['stripe_checkout_session_id' => $session->id]);

        return redirect()->away((string) $session->url);
    }

    public function success(Order $order): View
    {
        return view('checkout.success', ['order' => $order->fresh()]);
    }

    public function cancelled(Order $order): View
    {
        if (! $order->isPaid() && $order->status === Order::STATUS_PENDING) {
            $order->update(['status' => Order::STATUS_CANCELLED]);
        }

        return view('checkout.cancelled', ['order' => $order->fresh()]);
    }

    public function status(Order $order): JsonResponse
    {
        return response()->json([
            'id' => $order->id,
            'status' => $order->status,
            'paid_at' => $order->paid_at?->toIso8601String(),
            'payment_intent_id' => $order->stripe_payment_intent_id,
            'customer_email' => $order->customer_email,
        ]);
    }

    public function index(): View
    {
        return view('checkout.index', [
            'orders' => Order::query()->orderByDesc('id')->limit(50)->get(),
        ]);
    }

    /**
     * Reconcile this order's state against Stripe directly.
     *
     * Used when the webhook never arrived (or failed signature verification
     * during a misconfigured period) and the order is stuck in `pending`
     * even though the payment succeeded on Stripe's side. Asks Stripe what
     * the truth is and applies it locally.
     *
     * The kit's StripeReconciler::fetchObject() does the API lookup; the
     * mark-paid logic mirrors what MarkOrderPaidOnCheckoutCompleted does
     * when the webhook arrives normally — both paths converge on
     * Order::markPaid().
     */
    public function reconcile(Order $order, StripeReconciler $reconciler): RedirectResponse
    {
        if ($order->isPaid() || $order->stripe_checkout_session_id === null) {
            return back();
        }

        try {
            /** @var Session $session */
            $session = $reconciler->fetchObject($order->stripe_checkout_session_id);
        } catch (\Throwable $e) {
            return back()->with('reconcile_error', $e->getMessage());
        }

        $paymentStatus = $session->payment_status ?? null;

        if ($paymentStatus !== 'paid') {
            return back()->with(
                'reconcile_error',
                "Stripe says this session is `{$paymentStatus}` — nothing to mark paid yet."
            );
        }

        $order->markPaid(
            paymentIntentId: StripeIds::paymentIntentFromSession($session),
            customerEmail: StripeIds::customerEmailFromSession($session),
        );

        return back()->with('reconciled', true);
    }

    /**
     * Wipe demo state so the user can run the flow again from scratch.
     * Disabled in production. The actual truncate logic lives in
     * `App\Actions\ResetDemoState` so the artisan command can share it.
     */
    public function reset(ResetDemoState $reset): RedirectResponse
    {
        if (app()->environment('production')) {
            abort(403, 'Reset is disabled in production.');
        }

        $stats = $reset();

        return redirect()->route('checkout.index')->with(
            'reset_summary',
            "Wiped {$stats['orders']} order(s), {$stats['calls']} webhook call(s), {$stats['runs']} handler run(s), and the queue."
        );
    }
}
