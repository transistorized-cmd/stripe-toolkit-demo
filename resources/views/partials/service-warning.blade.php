@php
    /** @var array<int,array{name:string,command:string,reason:string}> $missing */
@endphp

@if (! empty($missing))
    <div style="background: rgba(245, 158, 11, 0.10); border-bottom: 2px solid var(--warn, #f59e0b); padding: 12px 24px; font-size: 13px; color: var(--text, #e2e8f0); font-family: -apple-system, BlinkMacSystemFont, system-ui, sans-serif;">
        <div style="font-weight: 700; color: var(--warn, #f59e0b); margin-bottom: 8px;">
            ⚠ Demo service{{ count($missing) > 1 ? 's' : '' }} not running
        </div>
        @foreach ($missing as $svc)
            <div style="margin-bottom: 6px; padding-left: 4px;">
                <strong>{{ $svc['name'] }}</strong>
                <span style="color: var(--muted, #94a3b8);"> — {{ $svc['reason'] }}</span><br>
                <span style="color: var(--muted, #94a3b8);">Start it:</span>
                <code style="background: rgba(0, 0, 0, 0.35); padding: 2px 8px; border-radius: 3px; font-size: 12px;">{{ $svc['command'] }}</code>
            </div>
        @endforeach
        <div style="margin-top: 8px; color: var(--muted, #94a3b8); font-size: 12px;">
            Or start everything in one go:
            <code style="background: rgba(0, 0, 0, 0.35); padding: 2px 8px; border-radius: 3px; font-size: 12px;">./demo.sh</code>
        </div>
    </div>
@endif
