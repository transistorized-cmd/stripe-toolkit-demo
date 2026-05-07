@extends('checkout.layout')

@section('title', 'Orders')

@section('content')
    <div class="row" style="margin-bottom: 16px;">
        <h1 style="margin: 0;">Orders</h1>
        <div style="display: flex; gap: 8px;">
            @unless (app()->environment('production'))
                <form method="POST" action="{{ route('demo.reset') }}" style="margin: 0;"
                      onsubmit="return confirm('Wipe ALL orders, webhook calls, handler runs, and queued jobs? This cannot be undone.');">
                    @csrf
                    <button type="submit" class="button secondary" title="Truncate orders + kit tables + queue">
                        ↺ Reset demo
                    </button>
                </form>
            @endunless
            <a href="{{ route('checkout.landing') }}" class="button">New order</a>
        </div>
    </div>

    @if (session('reset_summary'))
        <div style="background: rgba(34, 197, 94, 0.08); border: 1px solid var(--good); border-radius: 8px; padding: 12px 16px; margin-bottom: 16px; font-size: 13px;">
            <strong style="color: var(--good);">Reset complete</strong>
            <span class="muted"> · {{ session('reset_summary') }}</span>
        </div>
    @endif

    @if ($orders->isEmpty())
        <div class="card">
            <p class="muted">No orders yet. <a href="{{ route('checkout.landing') }}">Place one →</a></p>
        </div>
    @else
        <div class="card" style="padding: 0; overflow: hidden;">
            <table>
                <thead>
                <tr>
                    <th>#</th>
                    <th>product</th>
                    <th>amount</th>
                    <th>status</th>
                    <th>email</th>
                    <th>created</th>
                    <th>paid</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($orders as $order)
                    <tr>
                        <td><a href="{{ route('checkout.success', ['order' => $order->id]) }}">{{ $order->id }}</a></td>
                        <td>{{ $order->product_name }}</td>
                        <td>{{ $order->formattedAmount() }}</td>
                        <td><span class="badge {{ $order->status }}">{{ $order->status }}</span></td>
                        <td>{{ $order->customer_email ?? '—' }}</td>
                        <td title="{{ $order->created_at }}">{{ $order->created_at->diffForHumans() }}</td>
                        <td title="{{ $order->paid_at }}">{{ $order->paid_at?->diffForHumans() ?? '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <p class="muted" style="margin-top: 16px; text-align: center;">
        For the full webhook trail, open the <a href="/stripe-webhooks-debug" target="_blank">inspector ↗</a>
    </p>
@endsection
