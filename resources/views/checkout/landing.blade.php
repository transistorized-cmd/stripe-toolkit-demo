@extends('checkout.layout')

@section('title', 'Buy ' . $productName)

@section('content')
    @if (session('checkout_blocked'))
        <div class="card" style="margin-bottom: 16px; border-left: 4px solid var(--bad); background: rgba(239, 68, 68, 0.06);">
            <strong style="color: var(--bad); display: block; margin-bottom: 6px;">✗ Checkout blocked</strong>
            <p style="margin: 0; font-size: 13px;">{{ session('checkout_blocked') }}</p>
        </div>
    @endif

    <div class="card">
        <h2>Demo product</h2>
        <h1>{{ $productName }}</h1>
        <p class="muted">A small purchase that exercises the full Stripe webhook flow end-to-end. The order starts <strong>pending</strong>; when Stripe fires <code>checkout.session.completed</code>, the webhook is verified, persisted, and dispatched to a handler that marks the order paid — all observable from the inspector.</p>

        <div class="row" style="margin-top: 24px;">
            <div>
                <span class="price">{{ number_format($priceCents / 100, 2) }}</span>
                <span class="price-currency">{{ strtoupper($currency) }}</span>
            </div>
            <form method="POST" action="{{ route('checkout.start') }}">
                @csrf
                <button type="submit">Pay with Stripe →</button>
            </form>
        </div>

        <div class="test-card">
            Test card: <code>4242 4242 4242 4242</code> · any future expiry · any CVC · any postcode.<br>
            Other useful test cards: <code>4000 0000 0000 0002</code> declines, <code>4000 0025 0000 3155</code> requires 3DS auth.
        </div>

        <p class="muted" style="margin-top: 16px;">
            After paying, you'll be redirected to a status page that polls every 2&nbsp;seconds.
            Open the <a href="/stripe-webhooks-debug" target="_blank">inspector</a> in another tab to watch the webhook arrive in real time.
        </p>
    </div>
@endsection
