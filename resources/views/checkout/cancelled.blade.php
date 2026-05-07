@extends('checkout.layout')

@section('title', 'Order #'.$order->id.' · cancelled')

@section('content')
    <div class="card">
        <div class="row">
            <h1 style="margin: 0;">Order #{{ $order->id }}</h1>
            <span class="badge {{ $order->status }}">{{ $order->status }}</span>
        </div>

        <p class="muted" style="margin-top: 16px;">{{ $order->product_name }} · {{ $order->formattedAmount() }}</p>

        <p style="margin-top: 24px;">No charge was made. You closed the Stripe Checkout page or cancelled the payment.</p>

        <div style="margin-top: 24px; display: flex; gap: 8px;">
            <a href="{{ route('checkout.landing') }}" class="button">Try again</a>
            <a href="{{ route('checkout.index') }}" class="button secondary">All orders</a>
        </div>
    </div>
@endsection
