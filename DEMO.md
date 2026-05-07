# Stripe Toolkit · Webhooks — interactive demo

A small Laravel storefront that runs the full Stripe Checkout flow
end-to-end and lets you observe the webhook arriving in real time.

```
┌──────────────────────────┐     ┌────────────────┐     ┌─────────────┐
│   /                      │ POST│  /checkout     │ 302 │  Stripe     │
│   "Buy a Coffee · €4.20" │ ───►│  creates Order │ ───►│  Checkout   │
└──────────────────────────┘     │  + Stripe      │     │  (hosted)   │
                                 │  Session       │     └──────┬──────┘
                                 └────────────────┘            │
                                                                │
       ┌───────────────────────────┐                            ▼
       │  /orders/{id}             │ ◄──────── 302 ─────────────┤
       │  polls /orders/{id}/status│                            │
       │  shows pending → paid     │                            │
       └───────────────────────────┘                            │
                       ▲                                        │
                       │                                        ▼
              ┌────────┴───────────────────────────────────────────────┐
              │  Stripe ─POST checkout.session.completed─►            │
              │  /stripe/webhook                                      │
              │   1. signature verify (HMAC SHA256)                   │
              │   2. persist stripe_webhook_calls (UNIQUE event_id)   │
              │   3. queue ProcessStripeWebhook → RunStripeHandler    │
              │   4. MarkOrderPaidOnCheckoutCompleted updates Order   │
              └───────────────────────────────────────────────────────┘
```

## Prerequisites

- PHP 8.4 (or 8.2+)
- Composer
- A Stripe account in test mode (free at <https://stripe.com>)
- The Stripe CLI for local webhook forwarding (recommended):
  <https://stripe.com/docs/stripe-cli> · `stripe login` once

## One-time setup

```bash
cd stripe-toolkit-demo
composer install
php artisan migrate
```

Open `.env` and add your Stripe credentials. Get them from
<https://dashboard.stripe.com/test/apikeys>:

```bash
STRIPE_KEY=pk_test_…            # Publishable key
STRIPE_SECRET=sk_test_…         # Secret key

# For STRIPE_WEBHOOK_SECRET, see "Run it" below.
STRIPE_WEBHOOK_SECRET=whsec_…
```

Optional knobs (with sensible defaults):

```bash
DEMO_PRODUCT_NAME="Bulletproof Coffee"
DEMO_PRODUCT_PRICE=420          # cents
DEMO_PRODUCT_CURRENCY=eur
```

## Run it

### One-shot launcher (recommended)

```bash
./demo.sh
# or
composer demo
```

The script starts all three services in a single terminal with
color-coded prefixes (`[stripe]`, `[app   ]`, `[queue ]`):

1. `stripe listen --forward-to http://localhost:8000/stripe/webhook`
   (captures the `whsec_…` and syncs it into your `.env` automatically
   so signature verification matches without manual copy-paste)
2. `php artisan serve` with `PHP_CLI_SERVER_WORKERS=4 --no-reload`
3. `php artisan queue:work --queue=stripe-webhooks`

Once it's ready, you'll see a banner with the URLs:

```
╭───────────────────────────────────────────────────────────╮
│  Demo running. Ctrl+C to stop everything.                 │
├───────────────────────────────────────────────────────────┤
│  Storefront    http://localhost:8000                      │
│    LAN         http://192.168.1.152  :8000                │
│    Tailscale   http://100.110.204.49 :8000                │
│  Inspector     http://localhost:8000/stripe-webhooks-debug│
╰───────────────────────────────────────────────────────────╯
```

`Ctrl+C` stops everything cleanly. The script auto-reverts if the
Stripe CLI fails to authenticate (run `stripe login` first), if `.env`
is missing, or if `STRIPE_KEY` / `STRIPE_SECRET` still hold
placeholders.

For a custom port: `PORT=8080 ./demo.sh`.

### Manual mode (three terminals)

If you want to see each service's output in its own window, run them
manually as below.

### Terminal 1 — the Laravel app

For local-only access (single machine):

```bash
PHP_CLI_SERVER_WORKERS=4 php artisan serve --no-reload
```

For LAN access (test from another device on the same network — phone,
tablet, second laptop):

```bash
PHP_CLI_SERVER_WORKERS=4 php artisan serve --host=0.0.0.0 --port=8000 --no-reload
```

When binding to `0.0.0.0`, also point `APP_URL` in `.env` at the
machine's LAN IP so Stripe Checkout's `success_url` redirects come
back to a hostname the paying device can reach:

```bash
# .env
APP_URL=http://192.168.1.152:8000     # use your machine's LAN IP
```

Find your LAN IP with `hostname -I | awk '{print $1}'` (Linux/Mac) or
`ipconfig` (Windows). Once changed, the kit and the demo will generate
absolute URLs against that hostname automatically.

`PHP_CLI_SERVER_WORKERS=4 --no-reload` is required regardless — the
debug inspector's form trigger makes the app POST to itself, which
deadlocks on PHP's single-threaded built-in server.

> **Note:** the LAN setup works for any device that can reach your
> machine's IP — laptops/phones on the same wifi, machines on the same
> Tailnet (use the `100.x.y.z` IP), etc. For payments from outside the
> LAN (real mobile data, external collaborators), you need a public
> tunnel — see Terminal 3 below.

### Terminal 2 — the queue worker

```bash
php artisan queue:work --queue=stripe-webhooks
```

If you set `QUEUE_CONNECTION=sync` in `.env`, you can skip this — jobs
will run inline. The default `database` connection is more realistic.

### Terminal 3 — Stripe CLI forwarding

```bash
stripe listen --forward-to http://localhost:8000/stripe/webhook
```

The CLI prints something like:

```
> Ready! Your webhook signing secret is whsec_xxxxxxxxxxxxxxxxxxxxxxxx
```

**Copy that `whsec_…` into `.env`** as `STRIPE_WEBHOOK_SECRET`. The
secret persists across CLI restarts (per Stripe account), so you only
need to do this once.

If you need to expose the app publicly instead (mobile testing, real
Stripe Dashboard endpoints, etc.), use a tunnel: `tailscale funnel 8000`
or `cloudflared tunnel --url http://localhost:8000`. Configure a
webhook endpoint in the Stripe Dashboard pointing at
`https://<tunnel>/stripe/webhook` and copy its signing secret.

### Picking an API version when creating the endpoint

In the Stripe Dashboard's "Create an event destination" wizard you'll
see three API versions:

- **Latest stable** (e.g. `2026-04-22.dahlia`) — **pick this** for the
  demo. Aligns with the modern SDK; all the fields the demo handler
  reads (`session.metadata`, `session.payment_status`,
  `session.payment_intent`, etc.) are present.
- **Your account's current version** (e.g. `2023-10-16`) — also works.
  All the fields the demo touches have been stable for years.
- **`.preview`** — avoid. Useful for testing forthcoming features but
  may include shapes the SDK doesn't yet deserialize cleanly.

Stripe locks the version per endpoint independently of your account
default, which is useful for gradual migrations later. For the demo,
just pick the latest stable.

When using `stripe listen` (Path A), the CLI uses your account's
default API version automatically — no choice to make.

## Try it

1. Open <http://localhost:8000>
2. Click **Pay with Stripe**
3. On the hosted Checkout page, paste the test card:
   - **Card**: `4242 4242 4242 4242`
   - **Expiry**: any future date (e.g. `12/34`)
   - **CVC**: any 3 digits (e.g. `123`)
   - **Postcode**: any
4. Click **Pay**
5. You'll land on `/orders/{id}` showing **pending** with a pulsing dot.
   Within a couple of seconds the status flips to **paid** as the
   webhook is processed.

In a separate tab, open <http://localhost:8000/stripe-webhooks-debug>
to see the call land in the inspector with its handler runs and full
payload.

## Other test cards

Stripe ships a [zoo of test cards](https://stripe.com/docs/testing).
A few useful ones:

| Card | Effect |
|---|---|
| `4242 4242 4242 4242` | Successful charge |
| `4000 0000 0000 0002` | Generic decline |
| `4000 0025 0000 3155` | Requires 3D Secure authentication |
| `4000 0000 0000 9995` | Insufficient funds |
| `4000 0027 6000 3184` | 3D Secure 2 challenge |

## Beyond the happy path — scenarios worth running

Each of these takes under a minute and exercises a different part of
the kit. Open the inspector at `/stripe-webhooks-debug` in another tab
to watch what's happening server-side.

### 1. Idempotent replay — the same event delivered twice

Pay normally. Then in the Stripe CLI terminal:

```bash
stripe events list --limit 5             # find the latest evt_…
stripe events resend evt_…
```

The second delivery returns `200 {"status":"duplicate"}` and **does
not** re-run your handler. The `stripe_webhook_calls` row count for
that `event.id` stays at 1.

**What it proves:** event-level idempotency is enforced before any
handler executes. Stripe's own retries (which happen for real on
flaky networks) won't double-process payments.

### 2. Declined card — multi-attempt session and `charge.failed` events

Click **Pay** → use card `4000 0000 0000 0002` → Stripe rejects on the
hosted Checkout page and shows "Your card was declined". The user
stays on the Checkout page and can retry with a different card.

In the inspector you'll see TWO events arrive immediately for the
declined attempt:

- `charge.failed`
- `payment_intent.payment_failed`

Both show with a **no-op** badge (gray) — the kit persisted them for
audit, but no handler is registered for these types so no business
action was taken. The order stays in `pending`.

Now retry on the same Checkout page with `4242 4242 4242 4242`. A new
charge succeeds, `checkout.session.completed` fires, the handler marks
the order as paid. The inspector shows the full story: failed attempts
followed by a successful one, all under the same Checkout Session.

**What it proves:**
- Stripe Checkout supports multi-attempt sessions; the kit faithfully
  records every event including the failed attempts.
- The kit's `processed` status doesn't mean "payment succeeded" — it
  means "the kit handled this webhook". Events without a registered
  handler get the **no-op** badge so you can tell at a glance.
- Order state is driven by handlers you write, not by the kit's own
  status. If you wanted to mark orders as `failed` on first decline,
  you'd register a `payment_intent.payment_failed` handler — but that
  would be wrong because users do retry on the same session.

### 3. 3D Secure flow — multi-step authentication

Use card `4000 0025 0000 3155` → Stripe Checkout asks for a 3DS
challenge → click **Complete authentication** → payment succeeds →
webhook arrives → order flips to paid.

**What it proves:** the kit doesn't care how many round-trips Stripe
needed. It only sees the final `checkout.session.completed`.

### 4. User cancels checkout — `cancel_url` flow

Click **Pay** → on the hosted page, click the back arrow / close the
tab. Stripe redirects to `cancel_url`. The order is marked as
`cancelled` in the local DB. No webhook involved.

**What it proves:** abandoned-cart state is local; the kit isn't
needed for it.

### 5. Refund from the Stripe Dashboard — surprise events

After paying, go to <https://dashboard.stripe.com/test/payments>,
find the test charge, click **Refund**. Stripe fires `charge.refunded`
to your endpoint. With no handler registered for that type, the
WebhookCall lands in `processed` status (kit considers "no handlers"
as success).

**What it proves:** the kit faithfully persists every event Stripe
sends, even ones you don't act on yet. When you later add a refund
handler, the historical events are still there to inspect or replay.

### 6. Webhook lost — the killer scenario this kit was built for

In the Stripe CLI terminal, **kill `stripe listen`** with `Ctrl+C`.
Then go pay normally with `4242…`. Stripe charges the card; the
webhook can't reach you because the CLI tunnel is gone. The order
stays in `pending` indefinitely.

Now visit `/orders/{id}` — see the **↻ Reconcile with Stripe** button
under "Waiting for the webhook…". Click it. The kit asks Stripe what
the truth is, sees `payment_status=paid`, marks the order paid.

Restart `stripe listen`, pay again — normal flow returns.

**What it proves:** webhooks are best-effort; Stripe's API is
authoritative. The kit gives you a one-button recovery path so a
delivery failure doesn't strand orders.

### 7. Wrong signing secret — the failure mode you'll actually hit in production

In `.env`, change `STRIPE_WEBHOOK_SECRET` to something invalid like
`whsec_wrong`. **Restart `php artisan serve`** (PHP's dev server caches
env at start). Pay normally. The CLI shows `<-- [400] POST` for every
event; nothing reaches your DB.

Restore the correct secret, restart serve, click **Reconcile** on the
stuck order — recovers.

**What it proves:** the kit fails closed. A bad signature never makes
it past the controller, even if it would have been a valid event.
Combined with reconcile, you can recover without losing orders.

### 8. Queue worker down — events queue up safely

Stop the queue worker with `Ctrl+C`. Pay normally. The webhook arrives
and is persisted, but no handler runs. The order stays `pending`. Look
at `/stripe-webhooks-debug` — the call shows `received` status with no
handler runs.

Restart `php artisan queue:work --queue=stripe-webhooks`. Within a
second the handler executes and the order flips to paid.

**What it proves:** the store-then-process design tolerates worker
downtime. Events queue up safely instead of being dropped on the floor.

### 9. Synthetic events via the inspector — no Stripe account needed

Visit `/stripe-webhooks-debug`. The form at the top signs payloads
server-side and POSTs them to your own endpoint. You can:

- Send any of 10 preset event types
- Force a specific `event.id` to test idempotency
- Tick "tamper signature" to get a 400
- Set `timestamp_skew=600` to test tolerance rejection
- Paste arbitrary JSON in `data.object` to mimic any Stripe shape

**What it proves:** every code path can be exercised in isolation,
fast, without involving Stripe. Useful for CI/local debugging.

### 10. Dead-letter a handler — what happens when business logic explodes

In the inspector form, paste this `data.object` JSON for
`payment_intent.succeeded`:

```json
{ "id": "pi_test_drop", "object": "payment_intent", "amount": 0, "currency": "eur" }
```

Then send. The `LogPaymentSucceeded` handler runs, logs, returns
fine. But if you wrote your own handler that throws on `amount === 0`,
it would dead-letter after retries. View the result in the inspector:
the `WebhookHandlerRun` shows `dead_letter` status with the stack
trace, while OTHER handlers for the same event still ran.

**What it proves:** per-handler retries — one bad handler doesn't
poison the rest of the event's processing.

## What's wired up

- **Order model** (`app/Models/Order.php`) tracks `pending → paid →
  cancelled / refunded / failed`.
- **CheckoutController** (`app/Http/Controllers/CheckoutController.php`)
  creates the Stripe Session and pushes the user to hosted Checkout;
  the success page polls `/orders/{id}/status` every 2s.
- **MarkOrderPaidOnCheckoutCompleted**
  (`app/Stripe/Handlers/MarkOrderPaidOnCheckoutCompleted.php`) is the
  webhook handler. It reads `metadata.order_id`, calls `Order::markPaid()`,
  and logs.
- **LogPaymentSucceeded** (also in `app/Stripe/Handlers/`) is a second
  handler kept around so you see two handler-run rows per checkout in
  the inspector — proof that multiple handlers per event work.

## Resetting the demo

Three ways, pick the one that suits where you are.

**From the UI** — visit `/orders` and click **↺ Reset demo** (top
right). Confirms before wiping. Disabled in production.

**From the terminal**:

```bash
php artisan demo:reset --force
```

Truncates `orders`, `stripe_webhook_calls`, `stripe_webhook_handler_runs`,
`jobs`, and `failed_jobs`. Resets the autoincrement counter so order ids
start at 1 again. Does NOT touch `users`, `sessions`, `cache`, or your
`.env`.

**Nuke everything and migrate fresh**:

```bash
rm database/database.sqlite
php artisan migrate
```

## Troubleshooting

- **`Stripe-Signature header is missing`** — the Stripe CLI isn't
  pointed at this server, or `stripe listen` was killed. Restart it.
- **`Stripe signature verification failed`** — the `STRIPE_WEBHOOK_SECRET`
  in `.env` doesn't match the one Stripe is signing with. Re-copy the
  `whsec_…` from `stripe listen`'s output.
- **Status stays pending forever** — your queue worker isn't running.
  Run `php artisan queue:work --queue=stripe-webhooks` in another
  terminal.
- **419 on the form submit** — `bootstrap/app.php` doesn't exclude
  `stripe/webhook` from CSRF. This demo already has it configured; if
  you copied files to a fresh app, mirror the exclusion list.

See [the kit's troubleshooting guide](https://github.com/transistorized-cmd/stripe-toolkit-webhooks/blob/main/docs/troubleshooting.md)
for more.
