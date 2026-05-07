<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\ResetDemoState;
use Illuminate\Console\Command;

class DemoResetCommand extends Command
{
    protected $signature = 'demo:reset {--force : Skip the confirmation prompt}';

    protected $description = 'Wipe demo state (orders + kit tables + queue) so the demo can be run from scratch.';

    public function handle(ResetDemoState $reset): int
    {
        if (app()->environment('production')) {
            $this->components->error('demo:reset is disabled in production.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('Wipe ALL orders, webhook calls, handler runs, and queued jobs?', false)) {
            $this->components->info('Aborted.');

            return self::SUCCESS;
        }

        $stats = $reset();

        $this->components->twoColumnDetail('orders deleted', "<fg=green>{$stats['orders']}</>");
        $this->components->twoColumnDetail('webhook calls deleted', "<fg=green>{$stats['calls']}</>");
        $this->components->twoColumnDetail('handler runs deleted', "<fg=green>{$stats['runs']}</>");
        $this->components->info('Demo reset.');

        return self::SUCCESS;
    }
}
