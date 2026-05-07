<?php

declare(strict_types=1);

namespace App\Actions;

use Illuminate\Support\Facades\DB;

/**
 * Wipe demo state so the demo can be run from scratch. Truncates
 * `orders` plus the kit's two tables and the queue. Doesn't touch
 * users, sessions, cache, or .env.
 *
 * Shared by the web reset endpoint (`CheckoutController::reset`) and
 * the artisan command (`demo:reset`).
 */
final class ResetDemoState
{
    /** @return array{orders:int,calls:int,runs:int} counts of rows removed */
    public function __invoke(): array
    {
        return DB::transaction(function (): array {
            $stats = [
                'orders' => DB::table('orders')->count(),
                'calls' => DB::table('stripe_webhook_calls')->count(),
                'runs' => DB::table('stripe_webhook_handler_runs')->count(),
            ];

            // Children first to satisfy the FK from handler_runs to calls.
            DB::table('stripe_webhook_handler_runs')->delete();
            DB::table('stripe_webhook_calls')->delete();
            DB::table('orders')->delete();
            DB::table('jobs')->delete();
            DB::table('failed_jobs')->delete();

            // SQLite-only: reset autoincrement so the next order is #1.
            if (DB::getDriverName() === 'sqlite') {
                DB::table('sqlite_sequence')
                    ->whereIn('name', [
                        'orders',
                        'stripe_webhook_calls',
                        'stripe_webhook_handler_runs',
                        'jobs',
                        'failed_jobs',
                    ])
                    ->delete();
            }

            return $stats;
        });
    }
}
