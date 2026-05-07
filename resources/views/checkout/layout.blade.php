<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Stripe Toolkit · Webhooks Demo')</title>
    @yield('head')
    <style>
        :root {
            --bg: #0f172a;
            --panel: #1e293b;
            --border: #334155;
            --text: #e2e8f0;
            --muted: #94a3b8;
            --accent: #38bdf8;
            --good: #22c55e;
            --warn: #f59e0b;
            --bad: #ef4444;
            --grey: #64748b;
            --stripe: #635BFF;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            font-size: 15px;
            line-height: 1.5;
        }
        a { color: var(--accent); text-decoration: none; }
        a:hover { text-decoration: underline; }
        header {
            padding: 16px 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 24px;
        }
        header .brand {
            font-weight: 600;
            font-size: 16px;
            color: var(--text);
        }
        header nav {
            display: flex;
            gap: 16px;
            font-size: 13px;
        }
        header .grow { flex: 1; }
        main {
            max-width: 720px;
            margin: 32px auto;
            padding: 0 24px;
        }
        .card {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 24px;
        }
        h1 {
            margin: 0 0 12px;
            font-size: 24px;
            font-weight: 700;
        }
        h2 {
            margin: 0 0 8px;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--muted);
            font-weight: 600;
        }
        p { margin: 0 0 12px; color: var(--text); }
        p.muted { color: var(--muted); font-size: 13px; }
        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            background: var(--grey);
            color: white;
        }
        .badge.pending { background: var(--warn); }
        .badge.paid { background: var(--good); }
        .badge.cancelled { background: var(--grey); }
        .badge.refunded { background: var(--accent); color: var(--bg); }
        .badge.failed { background: var(--bad); }
        button, .button {
            display: inline-block;
            background: var(--stripe);
            color: white;
            border: 0;
            border-radius: 8px;
            padding: 12px 24px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
        }
        button:hover, .button:hover { filter: brightness(1.1); text-decoration: none; }
        button.secondary, .button.secondary {
            background: transparent;
            color: var(--muted);
            border: 1px solid var(--border);
            font-weight: 500;
            font-size: 13px;
            padding: 8px 14px;
        }
        button.secondary:hover, .button.secondary:hover {
            color: var(--text);
            border-color: var(--accent);
        }
        .row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-variant-numeric: tabular-nums;
            font-size: 14px;
        }
        th, td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        th {
            background: rgba(255, 255, 255, 0.03);
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--muted);
        }
        tr:last-child td { border-bottom: 0; }
        code, pre {
            font-family: ui-monospace, "SF Mono", Menlo, monospace;
            font-size: 13px;
            color: var(--accent);
        }
        .price {
            font-size: 32px;
            font-weight: 700;
            color: var(--text);
            font-variant-numeric: tabular-nums;
        }
        .price-currency {
            font-size: 18px;
            color: var(--muted);
            margin-left: 4px;
        }
        .test-card {
            background: rgba(56, 189, 248, 0.06);
            border: 1px dashed var(--accent);
            border-radius: 8px;
            padding: 12px 16px;
            margin: 16px 0;
            font-size: 13px;
        }
        .test-card code { font-size: 14px; color: var(--accent); }
        .meta-row {
            display: grid;
            grid-template-columns: 140px 1fr;
            gap: 6px 16px;
            font-size: 13px;
            margin-top: 12px;
        }
        .meta-row dt { color: var(--muted); }
        .meta-row dd {
            margin: 0;
            font-family: ui-monospace, "SF Mono", Menlo, monospace;
            color: var(--text);
            word-break: break-all;
        }
        .pulse {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--warn);
            animation: pulse 1.2s ease-in-out infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(1.3); }
        }
    </style>
</head>
<body>
{!! $globalBanner ?? '' !!}
<header>
    <a href="{{ route('checkout.landing') }}" class="brand">Stripe Toolkit · Webhooks Demo</a>
    <nav>
        <a href="{{ route('checkout.landing') }}">Buy</a>
        <a href="{{ route('checkout.index') }}">Orders</a>
        <a href="/stripe-webhooks-debug" target="_blank">Inspector ↗</a>
    </nav>
    <span class="grow"></span>
    <span class="badge" style="background: var(--grey)">test mode</span>
</header>
<main>
    @yield('content')
</main>
</body>
</html>
