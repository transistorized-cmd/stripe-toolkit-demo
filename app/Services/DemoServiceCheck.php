<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Detects whether the demo's auxiliary services (queue worker, Stripe
 * CLI listener) are running so the UI can warn the user before they
 * try a flow that would otherwise stall silently.
 *
 * Uses `pgrep` (Unix). On systems where it isn't available or
 * `shell_exec` is disabled, returns null for unknown — UI then hides
 * the banner rather than misreport.
 */
class DemoServiceCheck
{
    /** @var int seconds to cache the check across page reloads */
    private const CACHE_TTL = 5;

    /** @return array<int,array{name:string,command:string,reason:string}> */
    public function missingServices(): array
    {
        return Cache::remember('demo-service-check', self::CACHE_TTL, fn () => $this->compute());
    }

    /** @return array<int,array{name:string,command:string,reason:string}> */
    private function compute(): array
    {
        $missing = [];

        if ($this->isProcessRunning('queue:work') === false) {
            $missing[] = [
                'name' => 'Queue worker',
                'command' => 'php artisan queue:work --queue=stripe-webhooks',
                'reason' => 'Webhooks land but their handlers never run — orders stay pending.',
            ];
        }

        if ($this->isProcessRunning('stripe listen') === false) {
            $missing[] = [
                'name' => 'Stripe CLI listener',
                'command' => 'stripe listen --forward-to http://localhost:8000/stripe/webhook',
                'reason' => "Stripe can't reach localhost — webhooks never arrive at all.",
            ];
        }

        return $missing;
    }

    /**
     * @return bool|null true if a matching process exists; false if not;
     *                   null if we can't tell (no pgrep, shell_exec disabled).
     */
    private function isProcessRunning(string $needle): ?bool
    {
        if (! function_exists('shell_exec')) {
            return null;
        }

        // `pgrep -af` prints `<pid> <full command line>`. Pattern
        // matching with -f matches anywhere in the command line, which
        // also matches shell processes (bash/sh/zsh) whose command
        // happened to embed the needle as text. Filter those out so we
        // only count direct binary invocations.
        $cmd = 'pgrep -af '.escapeshellarg($needle).' 2>/dev/null';
        $output = shell_exec($cmd);

        if ($output === null || $output === false) {
            return null;
        }

        foreach (explode("\n", trim((string) $output)) as $line) {
            if ($line === '') {
                continue;
            }

            // Strip leading PID + whitespace.
            $command = (string) preg_replace('/^\d+\s+/', '', $line);

            // Skip shells whose command line embedded the needle.
            if (preg_match('#^(/[^ ]+/)?(bash|sh|zsh|fish|dash|grep|pgrep|awk)\b#', $command) === 1) {
                continue;
            }

            return true;
        }

        return false;
    }

    public function flush(): void
    {
        Cache::forget('demo-service-check');
    }
}
