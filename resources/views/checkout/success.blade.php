@extends('checkout.layout')

@section('title', 'Order #'.$order->id)

@section('content')
    <div class="card" id="orderCard">
        <div class="row">
            <h1 style="margin: 0;">Order #{{ $order->id }}</h1>
            <span class="badge {{ $order->status }}" id="statusBadge">{{ $order->status }}</span>
        </div>

        <p class="muted" style="margin-top: 16px;">{{ $order->product_name }} · {{ $order->formattedAmount() }}</p>

        <div id="waiting" style="{{ $order->isPaid() ? 'display:none;' : '' }} margin-top: 24px;">
            <div class="row" style="background: rgba(245, 158, 11, 0.08); border: 1px solid var(--warn); border-radius: 8px; padding: 16px;">
                <span class="pulse"></span>
                <div style="flex: 1;">
                    <strong style="display: block;">Waiting for the webhook…</strong>
                    <span class="muted">Stripe charged your card. The kit is verifying the signature, persisting the call, and dispatching the handler. Should take under a second.</span>
                </div>
            </div>

            @if (session('reconcile_error'))
                <div style="margin-top: 12px; background: rgba(239, 68, 68, 0.08); border: 1px solid var(--bad); border-radius: 8px; padding: 12px 16px; font-size: 13px;">
                    <strong style="color: var(--bad);">Reconcile failed:</strong> {{ session('reconcile_error') }}
                </div>
            @endif

            <div style="margin-top: 12px; padding: 12px 16px; border: 1px dashed var(--border); border-radius: 8px; font-size: 13px;">
                <p class="muted" style="margin: 0 0 10px;">
                    Stuck for too long? Stripe keeps the truth — ask the API directly.
                    The kit's <code>StripeReconciler</code> fetches the live state of the
                    Checkout Session and applies it locally if the payment actually went
                    through.
                </p>
                <form method="POST" action="{{ route('checkout.reconcile', ['order' => $order->id]) }}" style="margin: 0;">
                    @csrf
                    <button type="submit" class="button secondary" style="background: var(--accent); color: var(--bg); border-color: var(--accent);">
                        ↻ Reconcile with Stripe
                    </button>
                </form>
            </div>
        </div>

        <div id="paid" style="{{ $order->isPaid() ? '' : 'display:none;' }} margin-top: 24px;">
            <div class="row" style="background: rgba(34, 197, 94, 0.08); border: 1px solid var(--good); border-radius: 8px; padding: 16px;">
                <strong style="color: var(--good); font-size: 18px;">✓</strong>
                <div style="flex: 1;">
                    <strong style="display: block; color: var(--good);">Paid · order fulfilled</strong>
                    <span class="muted">The webhook arrived, the handler ran inside a queued job, and the order was marked paid. <a href="/stripe-webhooks-debug" target="_blank">See the call in the inspector ↗</a></span>
                </div>
            </div>
        </div>

        <dl class="meta-row" id="meta">
            <dt>session</dt>
            <dd>{{ $order->stripe_checkout_session_id ?? '—' }}</dd>
            <dt>payment intent</dt>
            <dd id="metaPaymentIntent">{{ $order->stripe_payment_intent_id ?? '—' }}</dd>
            <dt>customer</dt>
            <dd id="metaEmail">{{ $order->customer_email ?? '—' }}</dd>
            <dt>paid at</dt>
            <dd id="metaPaidAt">{{ $order->paid_at?->toIso8601String() ?? '—' }}</dd>
        </dl>

        <div style="margin-top: 24px; display: flex; gap: 8px;">
            <a href="{{ route('checkout.index') }}" class="button secondary">All orders</a>
            <a href="{{ route('checkout.landing') }}" class="button secondary">Buy another</a>
        </div>
    </div>

    @unless ($order->isPaid())
        <script>
            (function () {
                const orderId = {{ $order->id }};
                const statusUrl = "{{ route('checkout.status', ['order' => $order->id]) }}";

                async function poll() {
                    try {
                        const r = await fetch(statusUrl, { headers: { Accept: 'application/json' }});
                        if (!r.ok) return;
                        const data = await r.json();
                        if (data.status === 'paid') {
                            document.getElementById('statusBadge').textContent = 'paid';
                            document.getElementById('statusBadge').className = 'badge paid';
                            document.getElementById('waiting').style.display = 'none';
                            document.getElementById('paid').style.display = 'block';
                            document.getElementById('metaPaymentIntent').textContent = data.payment_intent_id ?? '—';
                            document.getElementById('metaEmail').textContent = data.customer_email ?? '—';
                            document.getElementById('metaPaidAt').textContent = data.paid_at ?? '—';
                            return; // stop polling
                        }
                        setTimeout(poll, 2000);
                    } catch (e) {
                        setTimeout(poll, 4000);
                    }
                }
                setTimeout(poll, 1500);
            })();
        </script>
    @endunless
@endsection
