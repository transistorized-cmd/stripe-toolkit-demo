# Stripe Toolkit · Webhooks — interactive demo

A small Laravel storefront that exercises every part of the
[`transistorized-cmd/stripe-toolkit-webhooks`](https://github.com/transistorized-cmd/stripe-toolkit-webhooks)
kit end-to-end: hosted Stripe Checkout, signed webhooks landing on the
kit's controller, queued handlers flipping orders from `pending` to
`paid`, plus a built-in inspector and reconcile tooling for the cases
where the happy path doesn't happen.

> Built on top of `laravel/laravel ^12` and the kit installed via
> `composer require transistorized-cmd/stripe-toolkit-webhooks`.
> Designed to be downloaded, ran in three commands, and explored in a
> browser.

## Quickstart

```bash
git clone https://github.com/transistorized-cmd/stripe-toolkit-demo.git
cd stripe-toolkit-demo
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate

# Edit .env with your Stripe TEST keys (pk_test_…, sk_test_…)
# from https://dashboard.stripe.com/test/apikeys

./demo.sh
```

The launcher starts all three required services in one terminal,
captures `stripe listen`'s webhook signing secret into `.env`
automatically, and prints the URLs:

```
━━━ ready ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

  Storefront    http://localhost:8000
    LAN         http://192.168.1.152:8000
    Tailscale   http://100.110.204.49:8000
  Inspector     http://localhost:8000/stripe-webhooks-debug

  Ctrl+C to stop all three services.
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

Open the storefront in a browser, click **Pay with Stripe**, use
Stripe's test card `4242 4242 4242 4242`, and watch the order flip to
`paid` in real time as the webhook arrives.

For the full guided tour (10 scenarios beyond the happy path,
troubleshooting, manual three-terminal mode), see [DEMO.md](DEMO.md).

## What's wired up

### Storefront flow

- `/` — landing with the demo product (one SKU, configurable in
  `.env`)
- `POST /checkout` — creates an `Order(pending)`, opens a Stripe
  Checkout Session, redirects to the hosted page
- `/orders/{id}` — success page that polls every 2s and flips to
  `paid` when the webhook arrives. Includes a **↻ Reconcile with
  Stripe** button that asks the API directly when the webhook is
  late or never arrived
- `/orders/{id}/cancelled` — cancel_url destination
- `/orders` — list of all orders with status badges + a **↺ Reset
  demo** button (wipes orders + kit tables + queue, dev-mode only)

### Webhook handler

- `app/Stripe/Handlers/MarkOrderPaidOnCheckoutCompleted.php` —
  registered for `checkout.session.completed` and
  `checkout.session.async_payment_succeeded` via the kit's
  `#[StripeEvent('…')]` attribute. Reads `metadata.order_id`, calls
  `Order::markPaid()`. Idempotent, so Stripe re-deliveries and manual
  replays are safe.
- `app/Stripe/Handlers/LogPaymentSucceeded.php` — second handler on
  `payment_intent.succeeded`, kept around so each checkout shows two
  handler runs in the inspector (proof that "multiple handlers per
  event" works).

### Inspector at `/stripe-webhooks-debug`

The kit's debug UI, automatically enabled in `local`/`testing`. From
the demo you can:

- **Read** the live stream of webhook calls with payment outcome
  badges: `✓ paid` / `✗ <code>` / `⏳ in flight` / `○ n/a`
- **Trigger real Stripe events** with one click (success / decline /
  insufficient funds / 3DS / refund last paid / create customer)
- **Send synthetic events** (locally signed, never touch Stripe) for
  CI-style testing
- **Inspect handler runs** with stack traces and the raw payload
- **Duplicate** any past event with a fresh `event_id`

### Service-health detection

The demo checks via `pgrep` whether the queue worker and `stripe
listen` are running, and surfaces it in two places:

- **Yellow banner** at the top of every page when something's missing,
  with the exact command to fix it. Cached 5 seconds — no measurable
  cost on page reloads.
- **Pre-checkout block**: clicking *Pay with Stripe* with services
  down redirects back to the landing with a clear callout instead of
  silently creating an order that would never reach `paid`.

Both auto-disabled in `production`.

## Configuration

All config lives in `.env`. Three Stripe keys are required (test
mode), the rest is optional. See [`.env.example`](.env.example) for
the full annotated list:

```bash
STRIPE_KEY=pk_test_…
STRIPE_SECRET=sk_test_…
STRIPE_WEBHOOK_SECRET=whsec_…   # auto-populated by ./demo.sh

# Optional product knobs:
DEMO_PRODUCT_NAME="Bulletproof Coffee"
DEMO_PRODUCT_PRICE=420
DEMO_PRODUCT_CURRENCY=eur
```

## Reset

```bash
php artisan demo:reset --force      # CLI
# or click "↺ Reset demo" on /orders
```

Truncates `orders`, `stripe_webhook_calls`, `stripe_webhook_handler_runs`,
`jobs`, `failed_jobs`. Resets autoincrement so the next order starts
at `#1`. Auto-disabled in `production`.

## Layout

```
stripe-toolkit-demo/
├── demo.sh                       launcher for the 3 services
├── DEMO.md                       full guided tour with 10 scenarios
├── README.md                     ← you are here
├── .env.example                  annotated env, all Stripe knobs
├── app/
│   ├── Http/Controllers/CheckoutController.php
│   ├── Models/Order.php
│   ├── Services/DemoServiceCheck.php       service health detection
│   ├── Console/Commands/DemoResetCommand.php
│   └── Stripe/Handlers/
│       ├── MarkOrderPaidOnCheckoutCompleted.php
│       └── LogPaymentSucceeded.php
├── resources/views/
│   ├── checkout/                 demo storefront views
│   └── partials/
│       └── service-warning.blade.php
├── routes/web.php
└── database/database.sqlite      not committed — recreated by migrate
```

## License

Demo code: MIT, free to copy from. The
[`transistorized-cmd/stripe-toolkit-webhooks`](../stripe-toolkit-webhooks)
kit it depends on is also MIT.
